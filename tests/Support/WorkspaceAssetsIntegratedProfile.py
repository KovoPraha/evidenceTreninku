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
from WorkspaceAssetsApache import WorkspaceAssetsApache

root = Path(__file__).resolve().parents[2]
base = os.environ.get("WORKSPACE_ASSETS_BASE_SHA", "")
if os.name == "nt" or not re.fullmatch(r"[0-9a-f]{40}", base):
    raise RuntimeError("Linux CI and an exact baseline commit required")
if os.environ.get("EVIDENCE_PERFORMANCE_CONFIRM") != "SYNTHETIC-ONLY":
    raise RuntimeError("Owned synthetic confirmation required")
diagnostic_only = False
runtime = Path(tempfile.mkdtemp(prefix="kis-workspace-assets-"))
output = root / ("output/workspace-assets-diagnostic-private" if diagnostic_only else "output/workspace-assets-ci-private")
safe = root / ("output/workspace-assets-diagnostic-safe" if diagnostic_only else "output/workspace-assets-ci-safe")
output.mkdir(parents=True, exist_ok=False)
safe.mkdir(parents=True, exist_ok=False)
(runtime / "sessions").mkdir()
(runtime / "metrics").mkdir()
(runtime / "screenshots").mkdir()
state = dict(runtime=str(runtime), datadir=os.environ["EVIDENCE_PERFORMANCE_DATADIR"],
             port=int(os.environ["EVIDENCE_MARIADB_SMOKE_PORT"]), http_port=18099, synthetic_only=True)
(output / "runtime.json").write_text(json.dumps(state))
routes = ["sportovec_treninky.php", "moje_treninky.php", "sprava_treninku.php", "ajax_sportovec_treninky.php", "includes/athlete_profile_read.php", "hlavicka.php", "pracovni_pozice.php"]
originals = {p: (root / p).read_bytes() for p in routes}
baselines = {p: subprocess.check_output(["git", "show", base + ":" + p], cwd=root) for p in routes}
head = subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=root, text=True).strip()
source = {p: hashlib.sha256((root / p).read_bytes()).hexdigest() for p in routes + ["includes/athlete_profile_read.php", "includes/sports_activity.php", "includes/sports_activity_overview_read.php"]}
(safe / "source-identity.json").write_text(json.dumps(dict(baseline_sha=base, optimized_sha=head,
    source_sha256=source, baseline_source_sha256={p:hashlib.sha256(v).hexdigest() for p,v in baselines.items()}),indent=2))
servers = []
front = WorkspaceAssetsApache(runtime, root)
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


def asset_browser(role):
    session = "workspace-assets-paired-" + role
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
    template=(root/"tests/Support/WorkspaceAssetsBrowser.js").read_text()
    report=dict(complete=False,role=role,samples=[],warmups=[],style_signatures={},screenshots=[],console_errors=[],failed_requests=[],optional_favicon_errors=[],interactions=[])
    for case in (["workspace","mine"] if role=="coach" else ["training"] if role=="sports_lead" else ["public-profile"]):
        for viewport in ["desktop","mobile"]:
            for cache in ["cold","warm"]:
                config=dict(role=role,urls=dict(before="http://127.0.0.1:18099",after="http://127.0.0.1:18100"),
                    screenshot_dir=str(safe),case_filter=case,viewport_filter=viewport,cache_filter=cache,css_sha256=hashlib.sha256((root/"assets/vendor/bootstrap/bootstrap.min.css").read_bytes()).hexdigest())
                script=runtime/(session+".js")
                script.write_text(template.replace("JSON.parse('__WORKSPACE_ASSETS_CONFIG__')",json.dumps(config)))
                part=result(cli(session,"run-code","--filename="+str(script)))
                assert part["complete"] and len(part["samples"])==34
                for key,value in part["style_signatures"].items():
                    assert report["style_signatures"].get(key,value)==value,"Style drift across cache modes"
                    report["style_signatures"][key]=value
                for key in ["samples","warmups","screenshots","console_errors","failed_requests","optional_favicon_errors","interactions"]:
                    report[key].extend(part[key])
                report["browser_version"]=part["browser_version"];report["method"]=part["method"]
                (safe/("workspace-browser-"+role+"-partial.json.gz")).write_bytes(gzip.compress(json.dumps(report).encode(),mtime=0))
    assert len(report["samples"]) == (272 if role=="coach" else 136)
    assert not report["console_errors"] and not report["failed_requests"]
    report["complete"]=True
    (safe/("workspace-browser-"+role+".json.gz")).write_bytes(gzip.compress(json.dumps(report).encode(),mtime=0))
    for case in sorted(set((v["case"],v["viewport"],v["cache"]) for v in report["samples"])):
        old=[v for v in report["samples"] if (v["case"],v["viewport"],v["cache"])==case and v["variant"]=="before"]
        new=[v for v in report["samples"] if (v["case"],v["viewport"],v["cache"])==case and v["variant"]=="after"]
        for metric in ["fcp_ms","ready_ms"]:
            assert median(v[metric] for v in new)<median(v[metric] for v in old)*1.2+20,"Browser regression: "+str(case)+metric
    pixel_pairs=[]
    images={}
    for shot in report['screenshots']:
        images.setdefault((shot['case'],shot['viewport']),{})[shot['variant']]=shot['path']
    code=r"""$a=imagecreatefrompng($argv[1]);$b=imagecreatefrompng($argv[2]);
    if(imagesx($a)!==imagesx($b)||imagesy($a)!==imagesy($b))exit(1);
    for($y=0;$y<imagesy($a);$y++)for($x=0;$x<imagesx($a);$x++)if(imagecolorat($a,$x,$y)!==imagecolorat($b,$x,$y))exit(2);"""
    for key,pair in images.items():
        assert set(pair)=={'before','after'}
        subprocess.run(['php','-r',code,pair['before'],pair['after']],check=True)
        pixel_pairs.append(dict(case=key[0],viewport=key[1],decoded_pixels_equal=True))
    (safe/('workspace-pixels-'+role+'.json')).write_text(json.dumps(pixel_pairs,indent=2))
    payloads=[]
    for case in sorted(set((v["case"],v["viewport"]) for v in report["samples"])):
        for cache in ["cold","warm"]:
            groups={v:[s for s in report["samples"] if (s["case"],s["viewport"],s["cache"],s["variant"])==(*case,cache,v)] for v in ["before","after"]}
            row=dict(case=case[0],viewport=case[1],cache=cache)
            for v,g in groups.items():
                row[v]={k:median(s[k] for s in g) for k in ["document_encoded_bytes","document_decoded_bytes","css_network_payload","css_decoded_bytes","combined_payload_bytes","fcp_ms","ready_ms"]}
            if case[0]=='workspace':
                assert row['before']['css_decoded_bytes']==232803*2 and row['after']['css_decoded_bytes']==232803,row
                if cache=='cold':
                    assert row['before']['css_network_payload']-row['after']['css_network_payload']>=30000,row
            else:
                assert row['before']['css_decoded_bytes']==row['after']['css_decoded_bytes']==232803,row
            payloads.append(row)
    (safe/("workspace-browser-"+role+"-summary.json")).write_text(json.dumps(payloads,indent=2))
    cli(session,"close"); sessions.remove(session)


def start_servers():
    if servers:
        raise RuntimeError("Owned workers already running")
    for variant, port, document_root in [("before",18199,baseline_root),("after",18200,root)]:
        args = ["php", "-d", "memory_limit=512M", "-d", "date.timezone=Europe/Prague", "-d", "display_errors=1",
                "-d", "session.save_handler=files", "-d", "session.save_path="+str(runtime/"sessions")]
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
        if entry.name.startswith(".") or entry.name in {"output","config.php","db.php","sportovec_treninky.php","ajax_sportovec_treninky.php","moje_treninky.php","sprava_treninku.php","hlavicka.php","pracovni_pozice.php","includes"}:
            continue
        if entry.is_file() and entry.suffix == ".php":
            shutil.copy2(entry, baseline_root/entry.name)
        else:
            (baseline_root/entry.name).symlink_to(entry,target_is_directory=entry.is_dir())
    (baseline_root/"includes").mkdir()
    for entry in (root/"includes").iterdir():
        if entry.name != "athlete_profile_read.php":
            if entry.is_file() and entry.suffix == ".php":
                shutil.copy2(entry, baseline_root/"includes"/entry.name)
            else:
                (baseline_root/"includes"/entry.name).symlink_to(entry,target_is_directory=entry.is_dir())
    for route in routes:
        (baseline_root/route).write_bytes(baselines[route])
    # Route ownership resolves SCRIPT_FILENAME against db.php's __DIR__.
    # A symlink would resolve to the other document root and skip that guard.
    database_source = subprocess.check_output(["git", "show", base + ":db.php"], cwd=root)
    assert database_source == (root/"db.php").read_bytes(), "Database bootstrap changed"
    (baseline_root/"db.php").write_bytes(database_source)
    header_source = subprocess.check_output(["git", "show", base + ":hlavicka.php"], cwd=root)
    assert header_source == baselines["hlavicka.php", "pracovni_pozice.php"], "Frozen shared header mismatch"
    (baseline_root/"config.php").symlink_to(root/"config.php")
    start_servers()
    front.start()
    reports=[]
    php("TrainingHistoryHttpProfile.php",output,"prepare")
    php("CoachHistoryHttpProfile.php",output,"prepare")
    php("AthleteProfileHttpProfile.php",output,"prepare")
    php("AthleteTargetedHttpProfile.php",output,"prepare",1000)
    for route in routes:
        if route != "pracovni_pozice.php":
            assert originals[route] == baselines[route], "Unrelated application source changed: "+route
    assert (root/'hlavicka.php').read_bytes()==baselines['hlavicka.php'], 'Shared header changed'
    old=subprocess.check_output(['git','show',base+':pracovni_pozice.php'],cwd=root)
    deleted=b'    <link href="<?=staffDashboardH(appUiUrl(\'assets/vendor/bootstrap/bootstrap.min.css\'))?>" rel="stylesheet">\n'
    assert old.count(deleted)==1 and originals['pracovni_pozice.php']==old.replace(deleted,b''), 'Only the duplicate link may change'
    (safe/'exact-edit-contract.json').write_text(json.dumps(dict(one_line_removed=True,header_unchanged=True,canonical_css_unchanged=True)))
    asset=(root/"assets/vendor/bootstrap/bootstrap.min.css").read_bytes()
    import base64
    digest=base64.b64encode(hashlib.sha384(asset).digest()).decode()
    assert digest=="QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
    (safe/"css-payload.json").write_text(json.dumps(dict(bytes=len(asset),sha384=digest,unchanged=True)))
    subprocess.run(["php","vendor/bin/phpunit","tests/Integration/WorkspaceAssetsComparisonTest.php","--log-junit",str(safe/"comparison-tests.xml")],cwd=root,env=env,check=True)
    php("AthleteTargetedSchema.php",output)
    (safe/"fixture-schema.json").write_bytes((output/"fixture-schema.json").read_bytes())
    for size in ([] if diagnostic_only else [1000,10000,30000]):
        php("ProfileSessionHttpProfile.php",output,"prepare",size)
        php("WorkspaceAssetsParents.php",output,size)
        parent_report=read(f"workspace-parents-{size}.json")
        for controls in parent_report["samples"].values():
            assert median(v["total_ms"] for v in controls["after"]) < median(v["total_ms"] for v in controls["before"])*1.2+20,"Parent regression"
        (safe/f"workspace-parents-{size}.json.gz").write_bytes(gzip.compress((output/f"workspace-parents-{size}.json").read_bytes(),mtime=0))
        if size==1000 and os.environ.get("WORKSPACE_ASSETS_BROWSER")=="1":
            asset_browser("coach")
            asset_browser("sports_lead")
            asset_browser("public")
        reports.append(parent_report)
    if not diagnostic_only:
        php("AthleteTargetedHttpProfile.php",output,"security",30000)
        (safe/"security.json").write_bytes((output/"parent-security.json").read_bytes())
    variants=["before","after"]
    summary=dict(complete=True,diagnostic_only=diagnostic_only,baseline_sha=base,optimized_sha=head,source_sha256=source,baseline_source_sha256={p:hashlib.sha256(v).hexdigest() for p,v in baselines.items()},comparison=os.environ.get("WORKSPACE_ASSETS_COMPARE")=="1",cases=[])
    for r in reports:
        summary["cases"].append(dict(size=r["size"],pages={case:{v:dict(total_median_ms=median(s["total_ms"] for s in controls[v]),ttfb_median_ms=median(s["ttfb_ms"] for s in controls[v])) for v in variants} for case,controls in r["samples"].items()}))
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
    front.stop()
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
