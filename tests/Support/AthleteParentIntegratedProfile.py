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
base = os.environ.get("ATHLETE_PARENT_BASE_SHA", "")
if os.name == "nt" or not re.fullmatch(r"[0-9a-f]{40}", base):
    raise RuntimeError("Linux CI and an exact baseline commit required")
if os.environ.get("EVIDENCE_PERFORMANCE_CONFIRM") != "SYNTHETIC-ONLY":
    raise RuntimeError("Owned synthetic confirmation required")
runtime = Path(tempfile.mkdtemp(prefix="kis-athlete-parent-"))
output = root / "output/athlete-parent-ci-private"
safe = root / "output/athlete-parent-ci-safe"
output.mkdir(parents=True, exist_ok=False)
safe.mkdir(parents=True, exist_ok=False)
(runtime / "sessions").mkdir()
(runtime / "metrics").mkdir()
(runtime / "screenshots").mkdir()
state = dict(runtime=str(runtime), datadir=os.environ["EVIDENCE_PERFORMANCE_DATADIR"],
             port=int(os.environ["EVIDENCE_MARIADB_SMOKE_PORT"]), http_port=18099, synthetic_only=True)
(output / "runtime.json").write_text(json.dumps(state))
routes = ["sportovec_treninky.php"]
originals = {p: (root / p).read_bytes() for p in routes}
baselines = {p: subprocess.check_output(["git", "show", base + ":" + p], cwd=root) for p in routes}
head = subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=root, text=True).strip()
source = {p: hashlib.sha256((root / p).read_bytes()).hexdigest() for p in routes + ["includes/athlete_profile_read.php", "includes/sports_activity.php", "includes/sports_activity_overview_read.php"]}
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
    session = "athlete-parent-ci-" + variant + "-" + role
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
    template = (root / "tests/Support/AthleteParentBrowserProfile.js").read_text()
    template = template.replace("JSON.parse('__ATHLETE_PARENT_CONFIG__')", json.dumps(dict(variant=variant, base_url=base_url, screenshot_dir=str(safe))))
    script = runtime / (session + ".js")
    script.write_text(template)
    report = result(cli(session, "run-code", "--filename=" + str(script)))
    assert report["complete"] and len(report["samples"]) == 26
    (safe / f"browser-{role}-{variant}-diagnostic.json").write_text(json.dumps({
        "console_errors": report["console_errors"], "failed_requests": report["failed_requests"]}))
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
            args += ["-d", "auto_prepend_file="+str(root/"tests/Support/AthleteParentInstrument.php")]
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
    env=os.environ | {"EVIDENCE_ROSTER_READ_MARIADB":"1"}
    subprocess.run(["php","vendor/bin/phpunit","tests/Integration/SportsActivityOverviewReadTest.php","--log-junit",str(safe/"projection-tests.xml")],cwd=root,env=env,check=True)
    php("FullHttpReadFixture.php","--setup",output)
    database_alias()
    database_alias_created = True
    for entry in root.iterdir():
        if entry.name.startswith(".") or entry.name in {"output","config.php","sportovec_treninky.php"}:
            continue
        (baseline_root/entry.name).symlink_to(entry,target_is_directory=entry.is_dir())
    (baseline_root/"sportovec_treninky.php").write_bytes(baselines["sportovec_treninky.php"])
    start_servers()
    reports=[]
    php("TrainingHistoryHttpProfile.php",output,"prepare")
    php("AthleteProfileHttpProfile.php",output,"prepare")
    for size in [1000,10000,30000]:
        php("ProfileSessionHttpProfile.php",output,"prepare",size)
        php("AthleteParentHttpProfile.php",output,"measure",size)
        report=read(f"parent-http-{size}.json")
        reports.append(report)
        (safe/f"parent-http-{size}.json.gz").write_bytes(gzip.compress((output/f"parent-http-{size}.json").read_bytes(),mtime=0))
    php("AthleteParentHttpProfile.php",output,"security",30000)
    (safe/"security.json").write_bytes((output/"parent-security.json").read_bytes())
    if os.environ.get("ATHLETE_PARENT_BROWSER")=="1":
        browser("before","coach")
        browser("after","coach")
        for variant in ["before","after"]:
            name=f"browser-coach-{variant}.json"
            (safe/(name+".gz")).write_bytes(gzip.compress((output/name).read_bytes(),mtime=0))
    stop_servers()
    start_servers(instrument=True)
    php("AthleteParentHttpProfile.php",output,"diagnose",30000)
    variants=["before","after"] if os.environ.get("ATHLETE_PARENT_COMPARE")=="1" else ["before"]
    for variant in variants:
        diagnostic=json.loads((runtime/"metrics"/("parent-"+variant+".json")).read_text())
        (safe/("diagnostic-"+variant+".json")).write_text(json.dumps(diagnostic,indent=2))
        top=sorted(diagnostic["queries"],key=lambda q:q["execute_ms"]+q["fetch_ms"],reverse=True)[:5]
        print(json.dumps(dict(variant=variant,peak_php_bytes=diagnostic["peak_php_bytes"],top=top)))
    summary=dict(complete=True,baseline_sha=base,optimized_sha=head,source_sha256=source,comparison=os.environ.get("ATHLETE_PARENT_COMPARE")=="1",cases=[])
    for r in reports:
        summary["cases"].append(dict(size=r["size"],**{v:dict(total_median_ms=median(s["total_ms"] for s in r["samples"][v]),ttfb_median_ms=median(s["ttfb_ms"] for s in r["samples"][v])) for v in variants}))
    if summary["comparison"]:
        largest=summary["cases"][-1]
        assert largest["after"]["total_median_ms"] < largest["before"]["total_median_ms"]*.8,"No substantial parent HTTP benefit"
        old=json.loads((safe/"diagnostic-before.json").read_text())
        new=json.loads((safe/"diagnostic-after.json").read_text())
        assert new["peak_php_bytes"] < old["peak_php_bytes"]*.5,"Unused wide snapshot still materialized"
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
