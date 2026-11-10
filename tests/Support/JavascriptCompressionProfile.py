"""Real Apache transport comparison; owns only a fresh, public static fixture.

No database, PHP configuration, credentials, live writes or network simulation.
Run from the repository root; JS_COMPRESSION_BROWSER=1 adds real Chrome.
"""
import base64
import contextlib
import gzip
import hashlib
import http.client
import json
import os
from pathlib import Path
import platform
import re
import shutil
import socket
import statistics
import subprocess
import tempfile
import time

ROOT = Path(__file__).resolve().parents[2]
# Compare the same committed assets with/without gzip. A historical fixture can
# still be reproduced explicitly; later application changes are not regressions.
BASE = os.environ.get("JS_COMPRESSION_SOURCE_REF", "HEAD")
SAFE = ROOT / "output/javascript-compression-safe"
SAFE.mkdir(parents=True, exist_ok=True)
TARGETS = ["assets/vendor/bootstrap/bootstrap.bundle.min.js", "assets/app-ui.js"]
WINDOWS = os.name == "nt"
APACHE = os.environ.get("JS_COMPRESSION_APACHE") or (
    "C:/xampp/apache/bin/httpd.exe" if WINDOWS else shutil.which("apache2"))
assert APACHE and Path(APACHE).is_file(), "A real Apache binary is required"
MODULES = Path("C:/xampp/apache/modules" if WINDOWS else "/usr/lib/apache2/modules")
SERVERS = []


def git(*args):
    return subprocess.check_output(["git", *args], cwd=ROOT)


def digest(body):
    return hashlib.sha256(body).hexdigest()


def request(port, path, encoding="gzip", method="GET", extra=None):
    conn = http.client.HTTPConnection("127.0.0.1", port, timeout=15)
    start = time.perf_counter()
    conn.request(method, "/" + path, headers={"Accept-Encoding": encoding, **(extra or {})})
    response = conn.getresponse()
    body = response.read()
    elapsed = (time.perf_counter() - start) * 1000
    headers = {k.lower(): v for k, v in response.getheaders()}
    result = dict(status=response.status, headers=headers, wire_bytes=len(body), total_ms=elapsed,
                  body=body, decoded=gzip.decompress(body) if headers.get("content-encoding") == "gzip" and body else body)
    conn.close()
    return result


def metadata(response):
    return {"status": response["status"], "wire_bytes": response["wire_bytes"],
            "decoded_bytes": len(response["decoded"]), "sha256": digest(response["decoded"]),
            "headers": {k: v for k, v in response["headers"].items() if k in
                        ["content-type", "content-encoding", "cache-control", "vary", "etag", "content-range"]}}


def free_port():
    with socket.socket() as s:
        s.bind(("127.0.0.1", 0))
        return s.getsockname()[1]


def start_server(directory, docroot, port):
    compiled = subprocess.check_output([APACHE, "-l"], text=True)
    names = ["authz_core", "authz_host", "mime", "filter", "deflate", "headers", "rewrite"]
    if not WINDOWS:
        if "mpm_" not in compiled:
            names.insert(0, "mpm_event")
        if "unixd.c" not in compiled:
            names.insert(0, "unixd")
    loads = "\n".join('LoadModule ' + n + '_module "' + (MODULES / ("mod_" + n + ".so")).as_posix() + '"'
                      for n in names if "mod_" + n + ".c" not in compiled)
    user = ""
    if not WINDOWS:
        import getpass
        import grp
        user = "User " + getpass.getuser() + "\nGroup " + grp.getgrgid(os.getgid()).gr_name
        if os.geteuid() == 0:
            user = "User www-data\nGroup www-data"
    config = f'''ServerRoot "{directory.as_posix()}"
{loads}
{user}
Listen 127.0.0.1:{port}
ServerName 127.0.0.1
PidFile "{(directory / 'apache.pid').as_posix()}"
ErrorLog "{(directory / 'apache-error.log').as_posix()}"
LogLevel warn
DocumentRoot "{docroot.as_posix()}"
TypesConfig "{'C:/xampp/apache/conf/mime.types' if WINDOWS else '/etc/mime.types'}"
AddType text/javascript .js
<Directory />
    AllowOverride None
</Directory>
<Directory "{docroot.as_posix()}">
    AllowOverride All
    Require all granted
</Directory>
# Reproduce observed hosting policy in both variants, independently of the change.
<FilesMatch "\\.js$">
    Header set Cache-Control "max-age=30"
</FilesMatch>
<FilesMatch "\\.css$">
    Header set Cache-Control "max-age=604800"
</FilesMatch>
AddOutputFilterByType DEFLATE text/css
'''
    config_path = directory / "apache.conf"
    config_path.write_text(config, encoding="utf8")
    subprocess.run([APACHE, "-t", "-f", str(config_path)], check=True, capture_output=True)
    log = (directory / "process.log").open("wb")
    process = subprocess.Popen([APACHE, "-X", "-f", str(config_path)], stdout=log, stderr=log)
    SERVERS.append((process, log))
    last_error = ""
    for _ in range(100):
        if process.poll() is not None:
            raise RuntimeError("Owned Apache exited: " + (directory / "process.log").read_text(errors="replace"))
        try:
            response = request(port, "probe.html", "identity")
            last_error = str(response["status"]) + response["body"].decode(errors="replace")[:500]
            if response["status"] == 200:
                return
        except (OSError, http.client.HTTPException) as error:
            last_error = str(error)
        time.sleep(.05)
    raise RuntimeError("Owned Apache did not start: " + last_error + "; " + (directory / "apache-error.log").read_text(errors="replace"))


def browser(runtime, urls):
    session = "javascript-compression-" + str(os.getpid())
    executable = shutil.which("playwright-cli") or shutil.which("playwright-cli.cmd")
    command = [executable] if executable else [shutil.which("npx.cmd") or shutil.which("npx"),
                                               "--yes", "--package", "@playwright/cli@0.1.23", "playwright-cli"]
    assert command[0], "npx or playwright-cli is required"

    def cli(*args):
        p = subprocess.run([*command, "--session", session, *args], cwd=ROOT, stdout=subprocess.PIPE,
                           stderr=subprocess.STDOUT, text=True, encoding="utf8", timeout=900)
        (runtime / ("browser-" + args[0] + ".log")).write_text(p.stdout, encoding="utf8")
        assert p.returncode == 0 and "### Error" not in p.stdout, p.stdout[-3000:]
        return p.stdout

    script = runtime / "browser.js"
    template = (ROOT / "tests/Support/JavascriptCompressionBrowser.js").read_text(encoding="utf8")
    script.write_text(template.replace("'__JS_COMPRESSION_CONFIG__'", json.dumps(dict(urls=urls))), encoding="utf8")
    try:
        cli("open", urls["before"] + "/probe.html", "--browser=chrome")
        cli("snapshot")
        report = json.JSONDecoder().raw_decode(cli("run-code", "--filename=" + str(script)).split("### Result", 1)[1].lstrip())[0]
        (SAFE / "browser.json").write_text(json.dumps(report, indent=2), encoding="utf8")
        assert report["complete"] and len(report["samples"]) == 136
        assert not report["errors"] and not report["failed"]
        summary = []
        for viewport in ["desktop", "mobile"]:
            for cache in ["cold", "warm"]:
                subset = [s for s in report["samples"] if s["viewport"] == viewport and s["cache"] == cache]
                row = dict(viewport=viewport, cache=cache)
                for variant in ["before", "after"]:
                    group = [s for s in subset if s["variant"] == variant]
                    row[variant] = {k: statistics.median(s[k] for s in group) for k in
                                    ["fcp_ms", "load_ms", "js_encoded_bytes", "js_transfer_bytes"]}
                for key in ["fcp_ms", "load_ms"]:
                    assert row["after"][key] < row["before"][key] * 1.2 + 20, row
                if cache == "cold":
                    assert row["after"]["js_encoded_bytes"] < row["before"]["js_encoded_bytes"] * .4, row
                summary.append(row)
        (SAFE / "browser-summary.json").write_text(json.dumps(summary, indent=2), encoding="utf8")
        return summary
    finally:
        cli("close")


def main():
    tracked = git("ls-tree", "-r", "--name-only", BASE, "assets").decode().splitlines()
    assets = [p for p in tracked if p.endswith(".js")] + ["assets/vendor/bootstrap/bootstrap.min.css", "sw.js", ".htaccess"]
    originals = {p: git("show", BASE + ":" + p) for p in assets}
    config = (ROOT / "assets/.htaccess").read_bytes()
    assert re.search(rb'<FilesMatch "\\\.js\$">\s+SetOutputFilter DEFLATE', config)
    versions = dict(platform=platform.platform(), apache=subprocess.check_output([APACHE, "-v"], text=True).strip(),
                    baseline_sha=git("rev-parse", BASE).decode().strip(), head=git("rev-parse", "HEAD").decode().strip(),
                    config_sha256=digest(config), source_sha256={p: digest(b) for p, b in originals.items()})
    # Keep the small owned public fixture/logs in ignored output for diagnosis.
    # Windows Apache holds its logs until the outer finally terminates it.
    with contextlib.nullcontext(tempfile.mkdtemp(prefix="javascript-compression-", dir=ROOT / "output")) as tmp:
        runtime = Path(tmp)
        runtime.chmod(0o755)
        ports = dict(before=free_port(), after=free_port())
        html = b'''<!doctype html><html lang="cs"><meta charset="utf-8"><title>Public asset transport fixture</title>
<link rel="icon" href="data:,"><link rel="stylesheet" href="/assets/vendor/bootstrap/bootstrap.min.css">
<body class="app-admin-shell"><main class="container py-4"><h1>Public assets</h1>
<button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#content">Toggle content</button>
<div class="collapse" id="content"><div class="card card-body">Bootstrap content</div></div>
<form method="post"><label>Search</label><input name="q"><button type="submit">Submit</button></form>
<div id="toastContainer"></div></main>
<script src="/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script><script src="/assets/app-ui.js"></script></body></html>'''
        controls = {"assets/control.json": b'{"public":true}', "assets/control.png": base64.b64decode(
            "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII="),
                    "probe.html": html}
        for variant, port in ports.items():
            directory = runtime / variant
            docroot = directory / "public"
            for name, content in {**originals, **controls}.items():
                target = docroot / name
                target.parent.mkdir(parents=True, exist_ok=True)
                target.write_bytes(content)
            if variant == "after":
                (docroot / "assets/.htaccess").write_bytes(config)
            start_server(directory, docroot, port)

        results = []
        for path, source in {**originals, **controls}.items():
            if path == ".htaccess":
                continue
            row = dict(path=path, variants={})
            responses = {}
            for variant, port in ports.items():
                encoding_results = {}
                for encoding in ["gzip", "identity", "gzip;q=0, identity;q=1"]:
                    response = request(port, path, encoding)
                    assert response["status"] == 200 and response["decoded"] == source, (variant, path, encoding, metadata(response))
                    if encoding != "gzip":
                        assert "content-encoding" not in response["headers"], (path, encoding)
                    encoding_results[encoding] = metadata(response)
                row["variants"][variant] = encoding_results
                responses[variant] = request(port, path)
                head = request(port, path, method="HEAD")
                assert head["status"] == 200 and not head["body"]
                assert head["headers"].get("content-encoding") == responses[variant]["headers"].get("content-encoding")
                identity = request(port, path, "identity")
                conditional = request(port, path, "identity", extra={"If-None-Match": identity["headers"]["etag"]})
                assert conditional["status"] == 304 and not conditional["body"], (path, conditional)
                compressed_conditional = request(port, path, extra={"If-None-Match": responses[variant]["headers"]["etag"]})
                # Apache's default AddSuffix distinguishes encoded representations.
                # Depending on its version it may return a safe full 200 on gzip
                # revalidation rather than 304; never weaken ETags to force 304.
                assert compressed_conditional["status"] in [200, 304], (path, metadata(compressed_conditional))
                if compressed_conditional["status"] == 200:
                    assert compressed_conditional["decoded"] == source, path
                else:
                    assert not compressed_conditional["body"], path
                encoding_results["gzip_conditional_status"] = compressed_conditional["status"]
                partial = request(port, path, "identity", extra={"Range": "bytes=0-9"})
                assert partial["status"] == 206 and partial["body"] == source[:10], path
                if path.endswith(".js") and path.startswith("assets/"):
                    assert "max-age=30" in responses[variant]["headers"]["cache-control"]
            before, after = responses["before"], responses["after"]
            assert before["headers"].get("cache-control") == after["headers"].get("cache-control"), path
            for header in ["content-type", "x-content-type-options", "x-frame-options", "referrer-policy", "permissions-policy"]:
                assert before["headers"].get(header) == after["headers"].get(header), (path, header)
            if path.endswith(".js") and path.startswith("assets/"):
                assert "content-encoding" not in before["headers"] and after["headers"]["content-encoding"] == "gzip", path
                assert "accept-encoding" in after["headers"]["vary"].lower(), path
                if path in TARGETS:
                    assert after["wire_bytes"] < before["wire_bytes"] * .4
            else:
                assert before["body"] == after["body"], path
                assert before["headers"].get("content-encoding") == after["headers"].get("content-encoding"), path
            row["head_conditional_range"] = "PASS"
            results.append(row)
        denial = {}
        for variant, port in ports.items():
            denial[variant] = {p: request(port, p, "identity")["status"] for p in
                               [".htaccess", "assets/.htaccess", "config.php", "docs/", "tests/"]}
            assert all(s == 403 for s in denial[variant].values()), denial
        (SAFE / "native.json").write_text(json.dumps(dict(complete=True, **versions, cases=results, denial=denial), indent=2), encoding="utf8")
        if os.environ.get("JS_COMPRESSION_BROWSER") == "1":
            print(json.dumps(browser(runtime, {v: "http://127.0.0.1:" + str(p) for v, p in ports.items()})))
        print(json.dumps(dict(complete=True, apache=versions["apache"], public_js_files=sum(p.endswith(".js") and p.startswith("assets/") for p in originals),
                              target_before_bytes=sum(r["variants"]["before"]["gzip"]["wire_bytes"] for r in results if r["path"] in TARGETS),
                              target_after_bytes=sum(r["variants"]["after"]["gzip"]["wire_bytes"] for r in results if r["path"] in TARGETS))))


try:
    main()
finally:
    for process, log in reversed(SERVERS):
        process.terminate()
        try:
            process.wait(timeout=10)
        except subprocess.TimeoutExpired:
            process.kill()
            process.wait(timeout=10)
        log.close()
    if SERVERS:
        (SAFE / "cleanup.json").write_text(json.dumps(dict(all_owned_apache_processes_stopped=all(
            process.poll() is not None for process, _ in SERVERS), user_apache_service_untouched=True)), encoding="utf8")
