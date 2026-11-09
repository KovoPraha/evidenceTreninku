"""Full HTTP/browser comparison on an explicitly owned Linux CI database; no production config."""
import signal
import gzip
import hashlib
import json
import os
import re
import shutil
import socket
import statistics
import subprocess
import tempfile
import time
from pathlib import Path

root = Path(__file__).resolve().parents[2]
base = os.environ.get("BOOTSTRAP_ASSET_BASE_SHA", "")
if os.name == "nt" or not re.fullmatch(r"[0-9a-f]{40}", base):
    raise RuntimeError("Linux CI and an exact baseline commit required")
if os.environ.get("EVIDENCE_PERFORMANCE_CONFIRM") != "SYNTHETIC-ONLY":
    raise RuntimeError("Owned synthetic confirmation required")
diagnostic_only = False
runtime = Path(tempfile.mkdtemp(prefix="kis-bootstrap-assets-"))
output = root / ("output/bootstrap-assets-diagnostic-private" if diagnostic_only else "output/bootstrap-assets-ci-private")
safe = root / ("output/bootstrap-assets-diagnostic-safe" if diagnostic_only else "output/bootstrap-assets-ci-safe")
output.mkdir(parents=True, exist_ok=False)
safe.mkdir(parents=True, exist_ok=False)
(runtime / "sessions").mkdir()
(runtime / "metrics").mkdir()
(runtime / "screenshots").mkdir()
state = dict(runtime=str(runtime), datadir=os.environ["EVIDENCE_PERFORMANCE_DATADIR"],
             port=int(os.environ["EVIDENCE_MARIADB_SMOKE_PORT"]), http_port=18099, synthetic_only=True)
(output / "runtime.json").write_text(json.dumps(state))
routes = ["sportovec_treninky.php", "moje_treninky.php", "sprava_treninku.php", "ajax_sportovec_treninky.php", "includes/athlete_profile_read.php"]
originals = {p: (root / p).read_bytes() for p in routes}
baselines = {p: subprocess.check_output(["git", "show", base + ":" + p], cwd=root) for p in routes}
head = subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=root, text=True).strip()
source = {p: hashlib.sha256((root / p).read_bytes()).hexdigest() for p in routes + ["includes/athlete_profile_read.php", "includes/sports_activity.php", "includes/sports_activity_overview_read.php"]}
(safe / "source-identity.json").write_text(json.dumps(dict(baseline_sha=base, optimized_sha=head,
    source_sha256=source, baseline_source_sha256={p:hashlib.sha256(v).hexdigest() for p,v in baselines.items()}),indent=2))
servers = []
baseline_root = runtime / "baseline"
baseline_root.mkdir()
sessions = set()
cleanup_ok = False
database_alias_created = False


def read(name):
    return json.loads((output / name).read_text())


def php(script, *args):
    subprocess.run(["php", "-d", "memory_limit=512M", "tests/Support/" + script, *map(str, args)], cwd=root, check=True)


def database_alias(remove=False):
    # A GitHub service forwards 127.0.0.1 to its bridge. Restrict the extra account
    # to the actual root connection's source IP and this one owned schema.
    code = r'''
    $argv=[__FILE__,'--library']; require 'tests/Support/FullHttpReadProfile.php';
    [$runtime,$admin]=fullHttpEnvironment($argvOutput);
    $host=substr(strrchr((string)$admin->query('SELECT USER()')->fetchColumn(),'@'),1);
    if (!filter_var($host,FILTER_VALIDATE_IP)) { throw new RuntimeException('Expected bridge IP'); }
    if ($host==='127.0.0.1') { exit; }
    if (!preg_match('/^http_[a-f0-9]{10}$/D',$runtime['web_user'])) { throw new RuntimeException('Unexpected owned user'); }
    $account=$admin->quote($runtime['web_user']).'@'.$admin->quote($host);
    if ($removeAlias) { $admin->exec('DROP USER IF EXISTS '.$account); }
    else { require 'config.php'; $admin->exec('CREATE USER '.$account.' IDENTIFIED BY '.$admin->quote(DB_PASS));
      $admin->exec('GRANT SELECT,INSERT,UPDATE,DELETE ON `'.$runtime['schema'].'`.* TO '.$account); }
    '''
    prefix = "$argvOutput=" + json.dumps(str(output)) + ";$removeAlias=" + ("true" if remove else "false") + ";"
    subprocess.run(["php", "-r", prefix + code], cwd=root, check=True)


def cli(session, *args):
    sessions.add(session)
    p = subprocess.run(["playwright-cli", "--session", session, *map(str, args)], cwd=root,
                       stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True, timeout=1200)
    # CLI logs may contain synthetic login arguments: retain privately, never upload or print them.
    with (runtime / "private-browser.log").open("a") as f:
        f.write(p.stdout)
    if p.returncode or "### Error" in p.stdout:
        detail = ""
        if args[0] == "run-code" and "### Error" in p.stdout:
            detail = p.stdout.split("### Error", 1)[1].split("###", 1)[0].strip()[:1600]
            for credential in read("credentials.json").values():
                for value in [credential["login"], credential["password"]]:
                    detail = detail.replace(value, "[REDACTED]")
        raise RuntimeError("Browser CLI failed at " + args[0] + "; private output excluded from artifact; " + detail)
    return p.stdout


def result(text):
    return json.JSONDecoder().raw_decode(text.split("### Result", 1)[1].lstrip())[0]


def snapshot(text):
    for name in re.findall(r"[^\s()]+\.ya?ml", text):
        p = root / name
        if p.is_file():
            return p.read_text()
    return text


def browser(variant, role):
    port = 18099 if variant == 'before' else 18100
    base_url = 'http://127.0.0.1:' + str(port)
    session = "bootstrap-assets-ci-" + variant + "-" + role
    initial = snapshot(cli(session, "open", base_url + "/login.php", "--browser=chrome"))
    refs = {}
    for label in ["Jméno nebo e-mail", "Heslo"]:
        match = re.search(r'textbox "' + re.escape(label) + r'"[^\n]*\[ref=(e\d+)\]', initial)
        if not match:
            raise RuntimeError("Fresh login snapshot lacks " + label)
        refs[label] = match[1]
    button = re.search(r'button "Přihlásit se"[^\n]*\[ref=(e\d+)\]', initial)
    if not button:
        raise RuntimeError("Fresh login snapshot lacks submit")
    credential = read("credentials.json")[role]
    cli(session, "fill", refs["Jméno nebo e-mail"], credential["login"])
    cli(session, "fill", refs["Heslo"], credential["password"])
    cli(session, "click", button[1])
    cli(session, "snapshot")
    template = (root / "tests/Support/AthleteMeasurementBrowserProfile.js").read_text()
    template = template.replace("JSON.parse('__ATHLETE_MEASUREMENT_CONFIG__')", json.dumps(dict(variant=variant, base_url=base_url, screenshot_dir=str(safe))))
    script = runtime / (session + ".js")
    script.write_text(template)
    report = result(cli(session, "run-code", "--filename=" + str(script)))
    assert report["complete"] and len(report["samples"]) == 26
    assert not report["console_errors"] and not report["failed_requests"]
    (output / f"browser-{role}-{variant}.json").write_text(json.dumps(report))
    assert report["interactions"]["complete"]
    cli(session, "close")
    sessions.remove(session)


def asset_browser(role):
    session = "bootstrap-assets-paired-" + role
    if role == "public":
        token=read("bootstrap-browser-private.json")["token"]
        cli(session,"open","http://127.0.0.1:18099/sportovec_treninky.php?hash="+token,"--browser=chrome")
        cli(session,"snapshot")
    else:
        initial=snapshot(cli(session,"open","http://127.0.0.1:18099/login.php","--browser=chrome"))
        refs={}
        for label in ["Jméno nebo e-mail","Heslo"]:
            match=re.search(r'textbox "'+re.escape(label)+r'"[^\n]*\[ref=(e\d+)\]',initial)
            if not match: raise RuntimeError("Fresh asset login snapshot missing field")
            refs[label]=match[1]
        button=re.search(r'button "Přihlásit se"[^\n]*\[ref=(e\d+)\]',initial)
        if not button: raise RuntimeError("Fresh asset login snapshot missing submit")
        credential=read("credentials.json")[role]
        cli(session,"fill",refs["Jméno nebo e-mail"],credential["login"])
        cli(session,"fill",refs["Heslo"],credential["password"])
        cli(session,"click",button[1]); cli(session,"snapshot")
    template=(root/"tests/Support/BootstrapAssetsBrowser.js").read_text()
    report=dict(complete=False,role=role,samples=[],style_signatures={},screenshots=[],console_errors=[],failed_requests=[])
    for case in (["profile","mine"] if role=="coach" else ["training"] if role=="sports_lead" else ["public-profile"]):
        for viewport in ["desktop","mobile"]:
            for cache in ["cold","warm"]:
                config=dict(role=role,urls=dict(before="http://127.0.0.1:18099",after="http://127.0.0.1:18100"),
                    screenshot_dir=str(safe),case_filter=case,viewport_filter=viewport,cache_filter=cache)
                script=runtime/(session+".js")
                script.write_text(template.replace("JSON.parse('__BOOTSTRAP_ASSETS_CONFIG__')",json.dumps(config)))
                part=result(cli(session,"run-code","--filename="+str(script)))
                assert part["complete"] and len(part["samples"])==34
                for key,value in part["style_signatures"].items():
                    assert report["style_signatures"].get(key,value)==value,"Style drift across cache modes"
                    report["style_signatures"][key]=value
                for key in ["samples","screenshots","console_errors","failed_requests"]:
                    report[key].extend(part[key])
                report["browser_version"]=part["browser_version"];report["method"]=part["method"]
                (safe/("asset-browser-"+role+"-partial.json.gz")).write_bytes(gzip.compress(json.dumps(report).encode(),mtime=0))
    assert len(report["samples"]) == (272 if role=="coach" else 136)
    assert not report["console_errors"] and not report["failed_requests"]
    report["complete"]=True
    (safe/("asset-browser-"+role+".json.gz")).write_bytes(gzip.compress(json.dumps(report).encode(),mtime=0))
    for case in sorted(set((v["case"],v["viewport"],v["cache"]) for v in report["samples"])):
        old=[v for v in report["samples"] if (v["case"],v["viewport"],v["cache"])==case and v["variant"]=="before"]
        new=[v for v in report["samples"] if (v["case"],v["viewport"],v["cache"])==case and v["variant"]=="after"]
        for metric in ["fcp_ms","ready_ms"]:
            assert median(v[metric] for v in new)<median(v[metric] for v in old)*1.2+20,"Browser regression: "+str(case)+metric
    cli(session,"close"); sessions.remove(session)


def diagnostic_root(variant):
    # Copy only routes, reader and db.php. All application sources stay unchanged.
    target = runtime / ("diagnostic-" + variant)
    target.mkdir()
    for entry in root.iterdir():
        if entry.name.startswith(".") or entry.name in {"output", "db.php", "sportovec_treninky.php", "ajax_sportovec_treninky.php", "moje_treninky.php", "sprava_treninku.php", "includes"}:
            continue
        (target / entry.name).symlink_to(entry, target_is_directory=entry.is_dir())
    (target / "includes").mkdir()
    for entry in (root / "includes").iterdir():
        if entry.name != "athlete_profile_read.php":
            (target / "includes" / entry.name).symlink_to(entry, target_is_directory=entry.is_dir())
    for route in routes:
        (target / route).write_bytes(baselines[route] if variant == "before" else originals[route])
    db = (root / "db.php").read_text()
    needle = '$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);'
    assert db.count(needle) == 1
    replacement = '$pdo = new \\Tests\\Support\\QueryProfilePdo("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);'
    replacement += '\n    $pdo->profiling = true; $pdo->traceCallers = true;'
    db = db.replace(needle, replacement)
    guard = "if (!defined('DB_NAME') || !preg_match('/^p11_read_[a-f0-9]{16}$/D', DB_NAME) || !str_starts_with(DB_HOST, '127.0.0.1;port=')) { throw new RuntimeException('Owned diagnostic database required'); }"
    marker = '    $pdo = new '
    dependency = 'require_once ' + json.dumps(str(root / 'tests/Support/QueryProfilePdo.php')) + ';'
    db = db.replace(marker, '    ' + dependency + '\n    ' + guard + '\n' + marker, 1)
    (target / "db.php").write_text(db)
    return target


def start_servers(instrument=False):
    if servers:
        raise RuntimeError("Owned workers already running")
    for variant, port, document_root in [("before",18099,baseline_root),("after",18100,root)]:
        if instrument:
            document_root = diagnostic_root(variant)
        args = ["php", "-d", "memory_limit=512M", "-d", "date.timezone=Europe/Prague", "-d", "display_errors=1",
                "-d", "session.save_handler=files", "-d", "session.save_path="+str(runtime/"sessions")]
        if instrument:
            args += ["-d", "auto_prepend_file="+str(root/"tests/Support/AthleteTargetedInstrument.php")]
        args += ["-S", "127.0.0.1:"+str(port), "-t", str(document_root)]
        env = os.environ | {"PHP_CLI_SERVER_WORKERS":"4", "P11_HTTP_METRICS":str(runtime/"metrics")}
        with (runtime/"private-http.log").open("ab") as log:
            process = subprocess.Popen(args,cwd=document_root,env=env,stdout=log,stderr=log,start_new_session=True)
        if os.getpgid(process.pid) != process.pid or process.pid <= 1:
            raise RuntimeError("Owned worker process-group identity required")
        servers.append((process,port))
        for _ in range(100):
            try:
                with socket.create_connection(("127.0.0.1",port),timeout=.2):
                    break
            except OSError:
                if process.poll() is not None:
                    raise RuntimeError("Owned worker failed")
                time.sleep(.1)
        else:
            raise RuntimeError("Owned workers unavailable")


def stop_servers():
    while servers:
        process,port = servers.pop()
        # start_new_session establishes this exact owned process group, including
        # all PHP CLI workers; never terminate another process by name/port.
        try:
            os.killpg(process.pid,signal.SIGTERM)
        except ProcessLookupError:
            pass
        process.wait(timeout=15)
        for _ in range(100):
            try:
                with socket.create_connection(("127.0.0.1",port),timeout=.1):
                    time.sleep(.05)
            except OSError:
                break
        else:
            raise RuntimeError("Owned PHP worker still listening")


def median(values):
    return statistics.median(values)


try:
    os.environ["ATHLETE_TARGETED_COMPARE"]="1"
    env=os.environ | {"EVIDENCE_ROSTER_READ_MARIADB":"1"}
    subprocess.run(["php","vendor/bin/phpunit","tests/Integration/AthleteTargetedReadTest.php","--log-junit",str(safe/"projection-tests.xml")],cwd=root,env=env,check=True)
    php("FullHttpReadFixture.php","--setup",output)
    database_alias()
    database_alias_created = True
    for entry in root.iterdir():
        if entry.name.startswith(".") or entry.name in {"output","config.php","sportovec_treninky.php","ajax_sportovec_treninky.php","moje_treninky.php","sprava_treninku.php","includes"}:
            continue
        (baseline_root/entry.name).symlink_to(entry,target_is_directory=entry.is_dir())
    (baseline_root/"includes").mkdir()
    for entry in (root/"includes").iterdir():
        if entry.name != "athlete_profile_read.php":
            (baseline_root/"includes"/entry.name).symlink_to(entry,target_is_directory=entry.is_dir())
    for route in routes:
        (baseline_root/route).write_bytes(baselines[route])
    start_servers()
    reports=[]
    php("TrainingHistoryHttpProfile.php",output,"prepare")
    php("AthleteProfileHttpProfile.php",output,"prepare")
    php("AthleteTargetedHttpProfile.php",output,"prepare",1000)
    assert originals["includes/athlete_profile_read.php"] == baselines["includes/athlete_profile_read.php"], "Reader must remain unchanged"
    pattern=r'^[ \t]*<link href="https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.3/dist/css/bootstrap\.min\.css" rel="stylesheet"\s+integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW\+ALEwIH"\s+crossorigin="anonymous">[ \t]*\n'
    for route in ["sportovec_treninky.php","moje_treninky.php","sprava_treninku.php"]:
        removed,count=re.subn(pattern,"",baselines[route].decode(),flags=re.M)
        assert count==1 and removed.encode()==originals[route],"Unexpected app edit: "+route
    (safe/"exact-edit-contract.json").write_text(json.dumps(dict(only_three_exact_duplicate_links_removed=True,reader_unchanged=True)))
    asset=(root/"assets/vendor/bootstrap/bootstrap.min.css").read_bytes()
    import base64
    digest=base64.b64encode(hashlib.sha384(asset).digest()).decode()
    assert digest=="QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
    (safe/"css-payload.json").write_text(json.dumps(dict(bytes=len(asset),sha384=digest,unchanged=True)))
    subprocess.run(["php","vendor/bin/phpunit","tests/Integration/BootstrapAssetComparisonTest.php","--log-junit",str(safe/"comparison-tests.xml")],cwd=root,env=env,check=True)
    php("AthleteTargetedSchema.php",output)
    (safe/"fixture-schema.json").write_bytes((output/"fixture-schema.json").read_bytes())
    if diagnostic_only:
        for size in [1000,10000,30000]:
            php("ProfileSessionHttpProfile.php",output,"prepare",size)
            php("AthleteTargetedHeaderCandidates.php",output,size)
            name=f"header-candidates-{size}.json"
            (safe/name).write_bytes((output/name).read_bytes())
    for size in ([] if diagnostic_only else [1000,10000,30000]):
        php("ProfileSessionHttpProfile.php",output,"prepare",size)
        php("BootstrapAssetsParents.php",output,size)
        parent_report=read(f"asset-parents-{size}.json")
        for controls in parent_report["samples"].values():
            assert median(v["total_ms"] for v in controls["after"]) < median(v["total_ms"] for v in controls["before"])*1.2+20,"Parent regression"
        (safe/f"asset-parents-{size}.json.gz").write_bytes(gzip.compress((output/f"asset-parents-{size}.json").read_bytes(),mtime=0))
        if size==1000 and os.environ.get("BOOTSTRAP_ASSET_BROWSER")=="1":
            asset_browser("coach")
            asset_browser("sports_lead")
            asset_browser("public")
        php("AthleteTargetedHttpProfile.php",output,"measure",size)
        report=read(f"targeted-http-{size}.json")
        reports.append(report)
        (safe/f"measurement-statistics-{size}.json").write_bytes((output/f"measurement-statistics-{size}.json").read_bytes())
        (safe/f"targeted-http-{size}.json.gz").write_bytes(gzip.compress((output/f"targeted-http-{size}.json").read_bytes(),mtime=0))
    if not diagnostic_only:
        php("AthleteTargetedHttpProfile.php",output,"security",30000)
        (safe/"security.json").write_bytes((output/"parent-security.json").read_bytes())
    if not diagnostic_only and os.environ.get("BOOTSTRAP_ASSET_BROWSER")=="1":
        browser("before","coach")
        browser("after","coach")
        for variant in ["before","after"]:
            name=f"browser-coach-{variant}.json"
            (safe/(name+".gz")).write_bytes(gzip.compress((output/name).read_bytes(),mtime=0))
    variants=["before","after"]
    summary=dict(complete=True,diagnostic_only=diagnostic_only,baseline_sha=base,optimized_sha=head,source_sha256=source,baseline_source_sha256={p:hashlib.sha256(v).hexdigest() for p,v in baselines.items()},comparison=os.environ.get("BOOTSTRAP_ASSET_COMPARE")=="1",cases=[])
    for r in reports:
        summary["cases"].append(dict(size=r["size"],**{v:dict(total_median_ms=median(s["total_ms"] for s in r["samples"][v]),ttfb_median_ms=median(s["ttfb_ms"] for s in r["samples"][v])) for v in variants}))
    if summary["comparison"] and not diagnostic_only:
        for report in reports:
            for case, controls in report["controls"].items():
                assert median(v["total_ms"] for v in controls["after"]) < median(v["total_ms"] for v in controls["before"])*1.2+20, "Control regression: " + case

    (safe/"integrated-summary.json").write_text(json.dumps(summary,indent=2))
    print(json.dumps(summary["cases"]))

except Exception:
    if (output/"parent-difference.json").exists():
        (safe/"parent-difference.json").write_bytes((output/"parent-difference.json").read_bytes())
    private_log = runtime / "private-http.log"
    known_errors = []
    if private_log.exists():
        log = private_log.read_text(errors="replace")
        for marker in ["Class \"Tests\\Support\\QueryProfilePdo\" not found", "syntax error", "Owned diagnostic database required", "Expected owned diagnostic connection"]:
            if marker in log:
                known_errors.append(marker)
    (safe / "runner-error.json").write_text(json.dumps(dict(diagnostic_only=diagnostic_only, known_errors=known_errors)))
    raise

finally:
    stop_servers()
    for session in list(sessions):
        try:
            cli(session,"close")
        except Exception:
            pass
    if (root/"config.php").exists():
        if database_alias_created:
            database_alias(remove=True)
            database_alias_created=False
        php("FullHttpReadCleanup.php",output)
        cleanup_ok=True
    (safe/"runner-cleanup.json").write_text(json.dumps(dict(owned_fixture_cleanup=cleanup_ok,
        all_owned_worker_ports_closed=not servers,source_unchanged=all((root/p).read_bytes()==originals[p] for p in routes),
        bridge_account_removed=not database_alias_created,private_artifacts_excluded=True)))
