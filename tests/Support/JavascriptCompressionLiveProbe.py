"""Anonymous verified-TLS GETs only; safe transport evidence, never raw bodies.

Usage: python JavascriptCompressionLiveProbe.py before|after output.json [before.json]
The after comparison fails unless the actual hosting applies the new gzip policy.
"""
import gzip
import hashlib
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import time

ROOT = Path(__file__).resolve().parents[2]
MODE = sys.argv[1]
assert MODE in ["before", "after"]
DEST = Path(sys.argv[2])
BASELINE = json.loads(Path(sys.argv[3]).read_text(encoding="utf8")) if MODE == "after" else None
HOST = "kis.kovopraha.cz"


def get(path, encoding="gzip", method="GET"):
    start = time.perf_counter()
    # Use the same verified-TLS native curl transport as the existing release
    # probes. Some Windows direct Python TLS connections are closed by hosting.
    with tempfile.TemporaryDirectory(prefix="jsz-read-only-") as tmp:
        headers_file, body_file = Path(tmp) / "headers", Path(tmp) / "body"
        command = ["curl.exe" if os.name == "nt" else "curl", "--silent", "--show-error", "--proto", "=https",
                   "--max-time", "30", "--connect-timeout", "10", "--retry", "2", "--retry-all-errors",
                   "--retry-delay", "1", "--header", "Accept-Encoding: " + encoding,
                   "--dump-header", str(headers_file), "--output", str(body_file), "--write-out", "%{http_code}"]
        if method == "HEAD":
            command.append("--head")
        status = int(subprocess.check_output([*command, "https://" + HOST + "/" + path]))
        body = b"" if method == "HEAD" else body_file.read_bytes()
        blocks = headers_file.read_text(encoding="iso8859-1").strip().split("\n\n")
        headers = {k.lower(): v.strip() for line in blocks[-1].splitlines()[1:] if ":" in line
                   for k, v in [line.split(":", 1)]}
    decoded = gzip.decompress(body) if body and headers.get("content-encoding") == "gzip" else body
    return dict(status=status, wire_bytes=len(body), decoded_bytes=len(decoded),
                sha256=hashlib.sha256(decoded).hexdigest(), total_ms=(time.perf_counter()-start)*1000,
                headers={k: v for k, v in headers.items() if k in
                         ["content-type", "content-encoding", "cache-control", "vary", "etag", "content-length"]})


assets = subprocess.check_output(["git", "ls-files", "assets"], cwd=ROOT).decode().splitlines()
paths = [p for p in assets if p.endswith(".js")] + ["assets/vendor/bootstrap/bootstrap.min.css", "sw.js"]
report = dict(mode=MODE, host=HOST, cookies=False, redirects=False, writes=False,
              captured_utc=time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()), cases=[], denial={}, complete=False)
try:
    for path in paths:
        canonical = subprocess.check_output(["git", "show", "HEAD:" + path], cwd=ROOT)
        expected = hashlib.sha256(canonical).hexdigest()
        row = dict(path=path, gzip=get(path), identity=get(path, "identity"))
        for key in ["gzip", "identity"]:
            assert row[key]["status"] == 200 and row[key]["sha256"] == expected, (path, key, row[key])
        assert "content-encoding" not in row["identity"]["headers"], path
        if path in ["assets/app-ui.js", "assets/vendor/bootstrap/bootstrap.bundle.min.js"]:
            row["q0"] = get(path, "gzip;q=0, identity;q=1")
            row["head"] = get(path, method="HEAD")
            assert row["q0"]["status"] == 200 and row["q0"]["sha256"] == expected
            assert "content-encoding" not in row["q0"]["headers"]
            assert row["head"]["status"] == 200 and row["head"]["wire_bytes"] == 0
            assert row["head"]["headers"].get("content-encoding") == row["gzip"]["headers"].get("content-encoding")
        if BASELINE:
            old = next(c for c in BASELINE["cases"] if c["path"] == path)
            for key in ["gzip", "identity"]:
                assert old[key]["sha256"] == row[key]["sha256"], path
                assert old[key]["headers"].get("cache-control") == row[key]["headers"].get("cache-control"), path
            if path.startswith("assets/") and path.endswith(".js"):
                assert row["gzip"]["headers"].get("content-encoding") == "gzip", path
                assert "accept-encoding" in row["gzip"]["headers"].get("vary", "").lower(), path
            else:
                assert old["gzip"]["headers"].get("content-encoding") == row["gzip"]["headers"].get("content-encoding"), path
                assert old["gzip"]["wire_bytes"] == row["gzip"]["wire_bytes"], path
        report["cases"].append(row)
    for path in ["assets/.htaccess", ".htaccess", "config.php", "bin/diagnose-athlete-read-schema.php", "bin/migrate.php"]:
        report["denial"][path] = get(path, "identity")["status"]
        assert report["denial"][path] == 403, (path, report["denial"][path])
    targets = [c for c in report["cases"] if c["path"] in
               ["assets/app-ui.js", "assets/vendor/bootstrap/bootstrap.bundle.min.js"]]
    report["target_wire_bytes"] = sum(c["gzip"]["wire_bytes"] for c in targets)
    report["all_js_wire_bytes"] = sum(c["gzip"]["wire_bytes"] for c in report["cases"] if c["path"].startswith("assets/") and c["path"].endswith(".js"))
    if BASELINE:
        report["target_saved_bytes"] = BASELINE["target_wire_bytes"] - report["target_wire_bytes"]
        report["target_reduction_percent"] = report["target_saved_bytes"] / BASELINE["target_wire_bytes"] * 100
        assert report["target_reduction_percent"] >= 60
    report["complete"] = True
finally:
    DEST.parent.mkdir(parents=True, exist_ok=True)
    DEST.write_text(json.dumps(report, indent=2), encoding="utf8")
print(json.dumps({k: v for k, v in report.items() if k not in ["cases", "denial"]}))
