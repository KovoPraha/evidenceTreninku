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
base = os.environ.get("PROFILE_SESSION_BASE_SHA", "")
if os.name == "nt" or not re.fullmatch(r"[0-9a-f]{40}", base):
    raise RuntimeError("Linux CI and an exact baseline commit required")
if os.environ.get("EVIDENCE_PERFORMANCE_CONFIRM") != "SYNTHETIC-ONLY":
    raise RuntimeError("Owned synthetic confirmation required")
runtime = Path(tempfile.mkdtemp(prefix="kis-profile-session-"))
output = root / "output/profile-session-ci-private"
safe = root / "output/profile-session-ci-safe"
output.mkdir(parents=True, exist_ok=False)
safe.mkdir(parents=True, exist_ok=False)
(runtime / "sessions").mkdir()
(runtime / "metrics").mkdir()
(runtime / "screenshots").mkdir()
state = dict(runtime=str(runtime), datadir=os.environ["EVIDENCE_PERFORMANCE_DATADIR"],
             port=int(os.environ["EVIDENCE_MARIADB_SMOKE_PORT"]), http_port=18099, synthetic_only=True)
(output / "runtime.json").write_text(json.dumps(state))
routes = ["ajax_sportovec_treninky.php"]
originals = {p: (root / p).read_bytes() for p in routes}
baselines = {p: subprocess.check_output(["git", "show", base + ":" + p], cwd=root) for p in routes}
head = subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=root, text=True).strip()
source = {p: hashlib.sha256((root / p).read_bytes()).hexdigest() for p in routes}
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
                       stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True, timeout=300)
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
    session = "profile-session-ci-" + variant + "-" + role
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
    template = (root / "tests/Support/ProfileSessionBrowserProfile.js").read_text()
    template = template.replace("JSON.parse('__PROFILE_SESSION_CONFIG__')", json.dumps(dict(variant=variant, base_url=base_url, screenshot_dir=str(safe))))
    script = runtime / (session + ".js")
    script.write_text(template)
    report = result(cli(session, "run-code", "--filename=" + str(script)))
    assert report["complete"] and len(report["samples"]) == 13
    assert not report["console_errors"] and not report["failed_requests"]
    (output / f"browser-{role}-{variant}.json").write_text(json.dumps(report))
    assert report["interactions"]["complete"]
    cli(session, "close")
    sessions.remove(session)


def start_servers(instrument=False):
    if servers:
        raise RuntimeError("Owned workers already running")
    for variant, port, document_root in [("before",18099,baseline_root),("after",18100,root)]:
        args = ["php", "-d", "memory_limit=512M", "-d", "date.timezone=Europe/Prague", "-d", "display_errors=1",
                "-d", "session.save_handler=files", "-d", "session.save_path="+str(runtime/"sessions")]
        if instrument:
            args += ["-d", "auto_prepend_file="+str(root/"tests/Support/ProfileSessionInstrument.php")]
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
    php("FullHttpReadFixture.php","--setup",output)
    database_alias()
    database_alias_created = True
    for entry in root.iterdir():
        if entry.name.startswith(".") or entry.name in {"output","config.php","ajax_sportovec_treninky.php"}:
            continue
        (baseline_root/entry.name).symlink_to(entry,target_is_directory=entry.is_dir())
    (baseline_root/"ajax_sportovec_treninky.php").write_bytes(baselines["ajax_sportovec_treninky.php"])
    start_servers()
    reports=[]
    php("TrainingHistoryHttpProfile.php",output,"prepare")
    php("AthleteProfileHttpProfile.php",output,"prepare")
    for size in [1000,10000,30000]:
        php("ProfileSessionHttpProfile.php",output,"prepare",size)
        php("ProfileSessionHttpProfile.php",output,"measure",size)
        report=read(f"profile-session-http-{size}.json")
        assert report["complete"] and report["domain_checksums_unchanged"]
        reports.append(report)
        (safe/f"profile-session-http-{size}.json.gz").write_bytes(gzip.compress((output/f"profile-session-http-{size}.json").read_bytes(),mtime=0))
    if os.environ.get("PROFILE_SESSION_BROWSER")=="1":
        browser("before","coach")
        browser("after","coach")
        for variant in ["before","after"]:
            name=f"browser-coach-{variant}.json"
            (safe/(name+".gz")).write_bytes(gzip.compress((output/name).read_bytes(),mtime=0))
    stop_servers()
    start_servers(instrument=True)
    php("ProfileSessionHttpProfile.php",output,"diagnose",30000)
    diagnostics=read("profile-session-lock-diagnostics.json")
    for variant in ["before","after"]:
        d=json.loads((runtime/"metrics"/("profile-"+variant+".json")).read_text())
        assert d["handler"]=="files" and d["identity_present"] and d["csrf_present"]
        assert d["session_active_at_shutdown"] == (variant=="before")
        diagnostics[variant]["shutdown"]=d
    (safe/"lock-diagnostics.json").write_text(json.dumps(diagnostics,indent=2))
    summary=dict(complete=True,baseline_sha=base,optimized_sha=head,source_sha256=source,
        handler="files",workers_per_server=4,method="Actual PHP HTTP/DB/bootstrap. Same-data alternating before/after, and alternating same/different-session controls; 4 warmups +17 pairs. No injected SQL delay. Diagnostic lock probe separate.",cases=[])
    for r in reports:
        case=dict(trainings=r["size"],measurements=r["measurements"])
        for variant in ["before","after"]:
            case[variant]={control:dict(short_median_ms=median(p["short"]["total_ms"] for p in r["controls"][variant][control]),
                long_median_ms=median(p["long"]["total_ms"] for p in r["controls"][variant][control]),pair_median_ms=median(p["pair_ms"] for p in r["controls"][variant][control])) for control in ["same","different","actual_same","actual_different"]}
            case[variant]["single_median_ms"]=median(s["total_ms"] for s in r["single"][variant])
        summary["cases"].append(case)
    largest=summary["cases"][-1]
    assert largest["after"]["actual_same"]["pair_median_ms"] < largest["before"]["actual_same"]["pair_median_ms"],"No actual two-read pair benefit"
    assert largest["after"]["same"]["short_median_ms"] < largest["before"]["same"]["short_median_ms"]*.5,"No substantial whole-HTTP session benefit"
    log=(runtime/"private-http.log").read_text()
    worker_ids=set(re.findall(r"\[(\d+)\].*GET /ajax_sportovec_treninky[.]php",log))
    assert len(worker_ids)>=2,"Concurrent workers not observed"
    summary["observed_profile_worker_count"]=len(worker_ids)
    (safe/"integrated-summary.json").write_text(json.dumps(summary,indent=2))
    print(json.dumps(summary["cases"]))
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
