"""Repeat the #375 medical fixture against accepted #381; localhost Windows only."""
import hashlib
import json
import os
from pathlib import Path
import re
import secrets
import socket
import statistics
import subprocess
import time

ROOT = Path(__file__).resolve().parents[2]
EXPECTED_ROOT = "C:/Users/Marek/Documents/Codex/evidencePavel-medical-valid-recheck-20261107"
if os.name != "nt" or ROOT.as_posix() != EXPECTED_ROOT:
    raise RuntimeError("Only this task's isolated Windows checkout is allowed")
OUT = ROOT / "output/medical-valid-scale-recheck-20261107"
OUT.mkdir(parents=True, exist_ok=False)
RUNTIME = Path("C:/xampp/tmp") / ("kis-medical-planner-scale-20261107-" + secrets.token_hex(4))
RUNTIME.mkdir(exist_ok=False)
for name in ["data", "sessions", "metrics", "mysql-tmp"]:
    (RUNTIME / name).mkdir()
PHP = "C:/xampp/php/php.exe"
MYSQL = "C:/xampp/mysql/bin"
ENV = dict(os.environ, EVIDENCE_PERFORMANCE_CONFIRM="SYNTHETIC-ONLY",
           EVIDENCE_MARIADB_SMOKE_PORT="33479", EVIDENCE_PERFORMANCE_DATADIR=(RUNTIME / "data").as_posix())
READ = ROOT / "includes/medical_clearance_read.php"
ORIGINAL = READ.read_bytes()
CONFIG = ROOT / "config.php"
if CONFIG.exists():
    raise RuntimeError("Unconfigured isolated worktree required")
BASE = "69f490332b0027232d7a58bffb578c28b2715ffa"


def sha(data):
    return hashlib.sha256(data).hexdigest()


def write(path, data):
    path.write_text(json.dumps(data, indent=2, ensure_ascii=False) + "\n", encoding="utf-8", newline="\n")


def read(path):
    return json.loads(path.read_text(encoding="utf-8-sig"))


def git(*args):
    return subprocess.check_output(["git", *args], cwd=ROOT)


def php(script, *args):
    subprocess.run([PHP, "-d", "memory_limit=1024M", "-d", "date.timezone=Europe/Prague",
                    "tests/Support/" + script, *map(str, args)], cwd=ROOT, env=ENV, check=True)


def wait_port(port, process):
    deadline = time.monotonic() + 60
    while time.monotonic() < deadline:
        if process.poll() is not None:
            raise RuntimeError("Owned service exited before readiness")
        try:
            with socket.create_connection(("127.0.0.1", port), timeout=1):
                return
        except OSError:
            time.sleep(.1)
    raise RuntimeError("Owned service readiness timeout")


def closed(port):
    try:
        with socket.create_connection(("127.0.0.1", port), timeout=5):
            return False
    except ConnectionRefusedError:
        return True


for port in [33479, 18115]:
    if not closed(port):
        raise RuntimeError("An existing service owns the requested port")
baseline = git("show", BASE + ":includes/medical_clearance_read.php").replace(b"\r\n", b"\n")
if baseline != git("show", "6b1860d5:includes/medical_clearance_read.php").replace(b"\r\n", b"\n"):
    raise RuntimeError("Accepted frozen baseline differs from the original #375 read")
sources = {}
for p in ["medical_clearance_admin.php", "includes/medical_clearance.php", "includes/medical_clearance_admin_view.php",
          "includes/staff_record_list.php", "includes/coach_scope.php", "includes/init.php", "hlavicka.php",
          "tests/Support/FullHttpReadFixture.php", "tests/Support/FullHttpReadProfile.php",
          "tests/Support/SyntheticReadPagesProfile.php", "tests/Support/MedicalPlannerScaleProfile.php"]:
    data = (ROOT / p).read_bytes().replace(b"\r\n", b"\n")
    if data != git("show", "HEAD:" + p).replace(b"\r\n", b"\n"):
        raise RuntimeError("Uncommitted application/fixture source: " + p)
    sources[p] = sha(data)
for p in ["medical_clearance_admin.php", "includes/medical_clearance.php", "includes/medical_clearance_admin_view.php",
          "includes/staff_record_list.php", "includes/coach_scope.php", "includes/init.php", "hlavicka.php"]:
    if sources[p] != sha(git("show", BASE + ":" + p).replace(b"\r\n", b"\n")):
        raise RuntimeError("Unrelated medical HTTP source differs from original study: " + p)
WIP = Path("C:/xampp/htdocs/evidencePavel/kis_rosters_admin.php")
wip_hash = sha(WIP.read_bytes())
if wip_hash != "7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5":
    raise RuntimeError("Original WIP changed before this task")
environment = dict(base=BASE, current=git("rev-parse", "HEAD").decode().strip(),
                   source_lf_sha256=sources, baseline_read_lf_sha256=sha(baseline),
                   current_read_lf_sha256=sha(ORIGINAL.replace(b"\r\n", b"\n")),
                   current_state_lf_sha256=sha((ROOT / "includes/medical_clearance_state_read.php").read_bytes().replace(b"\r\n", b"\n")),
                   platform="Windows, PHP8.2 / MariaDB10.4, shared workstation", synthetic_only=True,
                   method="One seed per level; full HTTP ABBA blocks, 5 warmups +15 retained timings per block; no outlier removal",
                   original_wip_sha256=wip_hash)
write(OUT / "environment.json", environment)
(RUNTIME / "my.ini").write_text("[mysqld]\nbasedir=C:/xampp/mysql\ndatadir=" + (RUNTIME / "data").as_posix()
    + "\nport=33479\nbind-address=127.0.0.1\ninnodb_buffer_pool_size=128M\nmax_allowed_packet=64M\n"
    + "skip-name-resolve\nquery_cache_type=0\nquery_cache_size=0\ntmpdir=" + (RUNTIME / "mysql-tmp").as_posix() + "\n")
state = dict(runtime=RUNTIME.as_posix(), datadir=(RUNTIME / "data").as_posix(), port=33479, http_port=18115, synthetic_only=True)
write(OUT / "runtime.json", state)
db = None
web = None
handles = []
setup_complete = False


def spawn(args, name, env=ENV):
    log = (OUT / (name + ".log")).open("wb")
    handles.append(log)
    return subprocess.Popen(args, cwd=ROOT, env=env, stdout=log, stderr=subprocess.STDOUT,
                            creationflags=subprocess.CREATE_NO_WINDOW)


def stop_web():
    global web
    if web is not None:
        web.terminate()
        web.wait(timeout=30)
        web = None
        r = read(OUT / "runtime.json")
        r["web_process_id"] = None
        write(OUT / "runtime.json", r)


def start_web(tag, instrument=False):
    global web
    args = [PHP, "-d", "session.save_path=" + (RUNTIME / "sessions").as_posix(),
            "-d", "session.gc_maxlifetime=14400", "-d", "date.timezone=Europe/Prague",
            "-d", "display_errors=1", "-d", "log_errors=1", "-d", "opcache.enable=0"]
    env = dict(ENV)
    if instrument:
        env["P11_HTTP_METRICS"] = (RUNTIME / "metrics").as_posix()
        args += ["-d", "auto_prepend_file=" + (ROOT / "tests/Support/FullHttpReadInstrument.php").as_posix()]
    else:
        env.pop("P11_HTTP_METRICS", None)
    args += ["-S", "127.0.0.1:18115", "-t", ROOT.as_posix()]
    web = spawn(args, "web-" + tag, env)
    r = read(OUT / "runtime.json")
    r["web_process_id"] = web.pid
    write(OUT / "runtime.json", r)
    wait_port(18115, web)


try:
    subprocess.run([MYSQL + "/mysql_install_db.exe", "--datadir=" + (RUNTIME / "data").as_posix(), "--port=33479", "--silent"],
                   cwd=ROOT, check=True, creationflags=subprocess.CREATE_NO_WINDOW)
    db = spawn([MYSQL + "/mysqld.exe", "--defaults-file=" + (RUNTIME / "my.ini").as_posix(), "--console"], "mariadb")
    state["db_process_id"] = db.pid
    write(OUT / "runtime.json", state)
    wait_port(33479, db)
    php("FullHttpReadFixture.php", "--setup", OUT)
    setup_complete = True
    with (OUT / "native-tests.txt").open("wb") as test_log:
        subprocess.run([PHP, "vendor/bin/phpunit", "--configuration", "phpunit.xml.dist", "--filter", "MedicalStateReadTest",
                        "--log-junit", str(OUT / "native-tests.xml")], cwd=ROOT,
                       env=dict(ENV, EVIDENCE_ROSTER_READ_MARIADB="1"), stdout=test_log, stderr=subprocess.STDOUT, check=True)
    start_web("login")
    php("FullHttpReadProfile.php", "--login", OUT)
    stop_web()
    php("MedicalPlannerScaleProfile.php", "restrict", OUT, "baseline")
    summaries = []
    for level in ["baseline", "medical50k", "medical100k"]:
        php("MedicalPlannerScaleProfile.php", "seed", OUT, level)
        php("MedicalValidScaleCheck.php", "settings", OUT, level, "before-1")
        before_html = None
        for block, variant in enumerate(["before", "after", "after", "before"], 1):
            tag = variant + "-" + str(block)
            READ.write_bytes(baseline if variant == "before" else ORIGINAL)
            start_web(level + "-" + tag)
            if block in [1, 2]:
                php("MedicalValidScaleCheck.php", "verify", OUT, level, tag)
            php("MedicalValidScaleCheck.php", "measure", OUT, level, tag)
            stop_web()
            if block in [1, 2]:
                start_web(level + "-diag-" + tag, True)
                php("MedicalValidScaleCheck.php", "diagnose", OUT, level, tag)
                stop_web()
        READ.write_bytes(ORIGINAL)
        folder = OUT / level
        before = read(folder / "verify-before-1.json")
        after = read(folder / "verify-after-2.json")
        assert before["cases"] and len(before["cases"]) == len(after["cases"]) == 11
        for a, b in zip(before["cases"], after["cases"]):
            for key in ["id", "input", "domain_sha256", "html_sha256_lf_nonce", "total", "page", "pages"]:
                assert a[key] == b[key], (level, a["id"], key)
        assert before["negative"] == after["negative"]
        samples = {v: [] for v in ["before", "after"]}
        for block, variant in enumerate(["before", "after", "after", "before"], 1):
            d = read(folder / ("measure-" + variant + "-" + str(block) + ".json"))
            assert d["complete"] and d["checksums_unchanged"] and len(d["samples"]) == 15
            assert d["html_sha256_lf_nonce"] == before["cases"][0]["html_sha256_lf_nonce"]
            samples[variant] += [s["total_ms"] for s in d["samples"]]
        summaries.append(dict(level=level, samples_per_variant=30, full_domain_and_html_cases=11,
            before_median_ms=statistics.median(samples["before"]), after_median_ms=statistics.median(samples["after"]),
            before_p95_ms=sorted(samples["before"])[28], after_p95_ms=sorted(samples["after"])[28],
            before=read(folder / "diagnose-before-1.json"), after=read(folder / "diagnose-after-2.json")))
        write(OUT / "summary.json", dict(complete=False, levels=summaries))
        print("LEVEL " + level + " COMPLETE", flush=True)
    write(OUT / "summary.json", dict(complete=True, levels=summaries))
finally:
    stop_web()
    READ.write_bytes(ORIGINAL)
    if setup_complete:
        php("FullHttpReadCleanup.php", OUT)
    if db is not None and db.poll() is None:
        subprocess.run([MYSQL + "/mysqladmin.exe", "--protocol=TCP", "--host=127.0.0.1", "--port=33479", "--user=root", "shutdown"],
                       check=True, creationflags=subprocess.CREATE_NO_WINDOW)
        db.wait(timeout=60)
    for h in handles:
        h.close()
    removed = 0
    for p in (RUNTIME / "sessions").iterdir():
        if p.is_symlink() or not p.is_file() or not re.fullmatch(r"sess_[a-zA-Z0-9,-]+", p.name):
            raise RuntimeError("Unexpected file in owned session directory")
        p.unlink()
        removed += 1
    cleanup = read(OUT / "cleanup.json") if (OUT / "cleanup.json").exists() else {}
    cleanup.update(database_stopped=db is not None and db.poll() == 0, http_stopped=web is None,
                   ports_closed=all(closed(p) for p in [33479, 18115]), original_source_restored=READ.read_bytes() == ORIGINAL,
                   original_wip_unchanged=sha(WIP.read_bytes()) == wip_hash, session_files_removed=removed,
                   config_absent=not CONFIG.exists())
    write(OUT / "cleanup.json", cleanup)
    if not all(cleanup.get(k) for k in ["schema_removed", "web_user_removed", "owned_config_removed", "credentials_and_cookie_jars_removed",
        "database_stopped", "http_stopped", "ports_closed", "original_source_restored", "original_wip_unchanged", "config_absent"]):
        raise RuntimeError("Final owned cleanup incomplete")
    print("CLEANUP PASS", flush=True)
