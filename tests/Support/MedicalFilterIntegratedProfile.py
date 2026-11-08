"""Full HTTP/browser comparison on an explicitly owned Linux CI database; no production config."""
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
base = os.environ.get("MEDICAL_FILTER_BASE_SHA", "")
if os.name == "nt" or not re.fullmatch(r"[0-9a-f]{40}", base):
    raise RuntimeError("Linux CI and an exact baseline commit required")
if os.environ.get("EVIDENCE_PERFORMANCE_CONFIRM") != "SYNTHETIC-ONLY":
    raise RuntimeError("Owned synthetic confirmation required")
runtime = Path(tempfile.mkdtemp(prefix="kis-medical-filter-"))
output = root / "output/medical-filter-ci-private"
safe = root / "output/medical-filter-ci-safe"
output.mkdir(parents=True, exist_ok=False)
safe.mkdir(parents=True, exist_ok=False)
(runtime / "sessions").mkdir()
(runtime / "metrics").mkdir()
(runtime / "screenshots").mkdir()
state = dict(runtime=str(runtime), datadir=os.environ["EVIDENCE_PERFORMANCE_DATADIR"],
             port=int(os.environ["EVIDENCE_MARIADB_SMOKE_PORT"]), http_port=18099, synthetic_only=True)
(output / "runtime.json").write_text(json.dumps(state))
route = root / "includes/medical_clearance_read.php"
original = route.read_bytes()
state_route = root / "includes/medical_clearance_state_read.php"
original_state = state_route.read_bytes()
baseline = subprocess.check_output(["git", "show", base + ":includes/medical_clearance_read.php"], cwd=root)
baseline_state = subprocess.check_output(["git", "show", base + ":includes/medical_clearance_state_read.php"], cwd=root)
head = subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=root, text=True).strip()
source_files = ["medical_clearance_admin.php", "includes/medical_clearance.php", "includes/medical_clearance_admin_view.php",
                "includes/staff_record_list.php", "includes/coach_scope.php", "includes/init.php"]
for p in source_files:
    if subprocess.check_output(["git", "show", base + ":" + p], cwd=root) != (root / p).read_bytes():
        raise RuntimeError("Unrelated application source differs from baseline: " + p)
source = {p: hashlib.sha256((root / p).read_bytes()).hexdigest() for p in source_files}
source["includes/medical_clearance_state_read.php"] = hashlib.sha256((root / "includes/medical_clearance_state_read.php").read_bytes()).hexdigest()
source["includes/medical_clearance_read.php"] = hashlib.sha256(original).hexdigest()
source["includes/medical_clearance_filtered_read.php"] = hashlib.sha256((root / "includes/medical_clearance_filtered_read.php").read_bytes()).hexdigest()
source["baseline_medical_state_read"] = hashlib.sha256(baseline_state).hexdigest()
source["baseline_medical_read"] = hashlib.sha256(baseline).hexdigest()
server = None
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
        raise RuntimeError("Browser CLI failed at " + args[0] + "; private output excluded from artifact")
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
    session = "medical-filter-ci-" + variant + "-" + role
    initial = snapshot(cli(session, "open", "http://127.0.0.1:18099/login.php", "--browser=chrome"))
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
    cases = [c for c in read("matrix.json") if c["id"] in ["medical-filter-broad-search", "medical-filter-date-day", "medical-filter-rare-search",
        "medical-filter-empty-search", "medical-filter-combined", "medical-filter-plain-empty", "medical-filter-half-pending"]]
    assert len(cases) == 7
    template = (root / "tests/Support/FullHttpBrowserProfile.js").read_text()
    template = template.replace("JSON.parse('__P11_CONFIG__')", json.dumps(dict(role=role, base_url="http://127.0.0.1:18099", cases=cases)))
    template = template.replace("  const context =", "  await page.setViewportSize({width:1036,height:703});\n  const context =", 1)
    script = runtime / (session + ".js")
    script.write_text(template)
    report = result(cli(session, "run-code", "--filename=" + str(script)))
    assert report["complete"] and len(report["cases"]) == 7 and all(len(c["samples"]) == 13 for c in report["cases"])
    assert all(not s["console_errors"] and not s["failed_requests"] for c in report["cases"] for s in c["samples"])
    (output / f"browser-{role}-{variant}.json").write_text(json.dumps(report))
    cli(session, "close")
    sessions.remove(session)


def start_server(instrument=False):
    global server
    args = ["php", "-d", "memory_limit=512M", "-d", "date.timezone=Europe/Prague", "-d", "display_errors=1",
            "-d", "session.save_path=" + str(runtime / "sessions")]
    if instrument:
        args += ["-d", "auto_prepend_file=" + str(root / "tests/Support/FullHttpReadInstrument.php")]
    args += ["-S", "127.0.0.1:18099", "-t", str(root)]
    env = os.environ | {"P11_HTTP_METRICS": str(runtime / "metrics")}
    log = (runtime / "private-http.log").open("ab")
    server = subprocess.Popen(args, cwd=root, env=env, stdout=log, stderr=log)
    log.close()
    for _ in range(100):
        try:
            with socket.create_connection(("127.0.0.1", 18099), timeout=.2):
                return
        except OSError:
            if server.poll() is not None:
                raise RuntimeError("Owned PHP server failed")
            time.sleep(.1)
    raise RuntimeError("Owned PHP server unavailable")


def stop_server():
    global server
    if server:
        server.terminate()
        server.wait(timeout=15)
        server = None


try:
    php("FullHttpReadFixture.php", "--setup", output)
    database_alias()
    database_alias_created = True
    summaries = []
    php("MedicalFilterHttpProfile.php", output, "prepare")
    for size in ["10000-watches", "100000-watches"]:
        if size == "100000-watches":
            php("MedicalFilterHttpProfile.php", output, "expand")
        for variant, content in [("before", baseline), ("after", original)]:
            route.write_bytes(content)
            state_route.write_bytes(baseline_state if variant == "before" else original_state)
            start_server()
            if size == "10000-watches" and variant == "before":
                php("FullHttpReadProfile.php", "--login", output)
            php("MedicalFilterHttpProfile.php", output, variant)
            for role in ["registrar"]:
                browser(variant, role)
            stop_server()
            start_server(instrument=True)
            shutil.copyfile(output / f"http-{variant}.json", output / "http-timings.json")
            php("FullHttpReadDiagnostics.php", output)
            shutil.copyfile(output / "http-diagnostics.json", output / f"diagnostics-{variant}.json")
            stop_server()
        before, after = read("http-before.json"), read("http-after.json")
        assert before["complete"] and after["complete"]
        summary = dict(size=size, complete=True, baseline_sha=base, optimized_sha=head, source_sha256=source,
                       same_seed=True, cases=[], method="Complete plain HTTP, same fixture and normalized HTML; separate diagnostics and Chrome.")
        for left, right in zip(before["cases"], after["cases"]):
            assert (left["id"], left["status"], left["html_sha256"]) == (right["id"], right["status"], right["html_sha256"])
            if left["status"] == 200:
                summary["cases"].append(dict(id=left["id"], bytes=left["samples"][0]["bytes"],
                    before_median_ms=statistics.median(s["total_ms"] for s in left["samples"]),
                    after_median_ms=statistics.median(s["total_ms"] for s in right["samples"])))
        for variant in ["before", "after"]:
            d = read("diagnostics-" + variant + ".json")
            assert d["complete"] and d["core_unchanged"] and all(not c["write_commands"] for c in d["cases"])
        assert read("diagnostics-before.json")["core_checksums_before"] == read("diagnostics-after.json")["core_checksums_after"]
        names = [f"http-{v}.json" for v in ["before", "after"]] + [f"diagnostics-{v}.json" for v in ["before", "after"]]
        names += [f"browser-{role}-{v}.json" for role in ["registrar"] for v in ["before", "after"]]
        for name in names:
            value = json.loads((output / name).read_text())
            (safe / (size + "-" + name + ".gz")).write_bytes(gzip.compress(json.dumps(value, ensure_ascii=False).encode(), mtime=0))
        indexes = read("fixture-indexes.json")
        (safe / (size + "-fixture-indexes.json")).write_text(json.dumps(indexes, indent=2))
        summaries.append(summary)
        (safe / "integrated-summary.json").write_text(json.dumps(summaries, indent=2))
        print(json.dumps(summary["cases"], ensure_ascii=False))
    (safe / "integrated-summary.json").write_text(json.dumps(summaries, indent=2))
finally:
    stop_server()
    route.write_bytes(original)
    state_route.write_bytes(original_state)
    for session in list(sessions):
        try:
            cli(session, "close")
        except Exception:
            pass
    if (root / "config.php").exists():
        if database_alias_created:
            database_alias(remove=True)
            database_alias_created = False
        php("FullHttpReadCleanup.php", output)
        cleanup_ok = True
    (safe / "runner-cleanup.json").write_text(json.dumps(dict(owned_fixture_cleanup=cleanup_ok, php_server_stopped=server is None,
        route_restored=route.read_bytes() == original and state_route.read_bytes() == original_state, bridge_account_removed=not database_alias_created,
        private_artifacts_excluded=True)))
