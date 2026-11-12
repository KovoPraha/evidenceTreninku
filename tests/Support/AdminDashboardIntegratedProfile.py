"""Paired full HTTP administrative dashboard comparison on owned synthetic MariaDB and Apache."""
import base64, gzip, hashlib, json, os, shutil, signal, socket, statistics, subprocess, tempfile, time
from pathlib import Path
from WorkspaceAssetsApache import WorkspaceAssetsApache

root=Path(__file__).resolve().parents[2]
profile = "admin-dashboard"
base = "5cd6ad07b7f99b7b8d361f49520025d799e8776e"
assert os.environ["ADMIN_DASHBOARD_BASE_SHA"] == base
browser_enabled = os.environ["ADMIN_DASHBOARD_BROWSER"] == "1"
routes = ["admin_dashboard.php"]
assert os.name!='nt' and os.environ.get('EVIDENCE_PERFORMANCE_CONFIRM')=='SYNTHETIC-ONLY'
runtime = Path(tempfile.mkdtemp(prefix="kis-" + profile + "-"))
output = root / "output" / (profile + "-private")
safe = root / "output" / (profile + "-safe")
output.mkdir(exist_ok=False);safe.mkdir(exist_ok=False)
(runtime/'sessions').mkdir();(runtime/'metrics').mkdir()
(output/'runtime.json').write_text(json.dumps(dict(runtime=str(runtime),datadir=os.environ['EVIDENCE_PERFORMANCE_DATADIR'],port=int(os.environ['EVIDENCE_MARIADB_SMOKE_PORT']),http_port=18099,synthetic_only=True)))
original = {route: (root / route).read_bytes() for route in routes}
head=subprocess.check_output(['git','rev-parse','HEAD'],cwd=root,text=True).strip()
servers=[];baseline_root=runtime/'baseline';baseline_root.mkdir()
front=WorkspaceAssetsApache(runtime,root)
database_alias_created=False;cleanup_ok=False

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



def browser(path, cookies, viewport, theme, cache, css_hash):
    route_name = Path(path.split("?", 1)[0]).stem
    label = f"{route_name}-{theme}-{cache}"
    screenshots = {variant: str(safe / f"{label}-{variant}.png") for variant in ("before", "after")}
    config = dict(urls={"before": "http://127.0.0.1:18099", "after": "http://127.0.0.1:18100"},
                  viewport=viewport, theme=theme, cache=cache, cookies=cookies, path=path,
                  screenshots=screenshots, css_sha256=css_hash)
    template = (root / "tests/Support/AdminDashboardBrowser.js").read_text()
    script = runtime / "planner-browser.js"
    script.write_text(template.replace("JSON.parse('__PLANNER_ASSETS_CONFIG__')", json.dumps(config)))
    result = subprocess.run(["playwright-cli", "--session", profile, "run-code", "--filename=" + str(script)],
                            cwd=root, text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, timeout=300)
    (runtime / f"private-browser-{label}.log").write_text(result.stdout)
    if result.returncode or "### Error" in result.stdout:
        detail = result.stdout.split("### Error", 1)[-1].split("###", 1)[0][:1600]
        for cookie in cookies:
            detail = detail.replace(cookie["value"], "[REDACTED]")
        raise RuntimeError(f"Browser case {label} failed: {detail}")
    report = json.JSONDecoder().raw_decode(result.stdout.split("### Result", 1)[1].lstrip())[0]
    assert report["complete"] and len(report["samples"]) == 22
    summary = {"theme": theme, "viewport": viewport, "cache": cache}
    summary["route"] = route_name
    for variant in ("before", "after"):
        records = [row for row in report["samples"] if row["variant"] == variant]
        assert len(records) == 11
        summary[variant] = {field: statistics.median(row[field] for row in records)
                            for field in ("fcp_ms", "ready_ms", "html_bytes", "css_bytes", "css_decoded_bytes")}
    before, after = summary["before"], summary["after"]
    assert after["fcp_ms"] <= before["fcp_ms"] * 1.2 + 20, summary
    assert after["ready_ms"] <= before["ready_ms"] * 1.2 + 20, summary
    if cache == "cold":
        assert before["css_decoded_bytes"] == after["css_decoded_bytes"] == 465606, summary
        assert before["css_bytes"] == after["css_bytes"], summary
        compare_png = """$a=imagecreatefrompng($argv[1]);$b=imagecreatefrompng($argv[2]);
        if(imagesx($a)!==imagesx($b)||imagesy($a)!==imagesy($b))exit(1);
        for($y=0;$y<imagesy($a);$y++)for($x=0;$x<imagesx($a);$x++)if(imagecolorat($a,$x,$y)!==imagecolorat($b,$x,$y))exit(2);"""
        subprocess.run(["php", "-r", compare_png, screenshots["before"], screenshots["after"]], check=True)
        summary["pixels_equal"] = True
    (safe / f"browser-{label}.json").write_text(json.dumps(report, indent=2))
    return summary


try:
    canonical_css = (root / "assets/vendor/bootstrap/bootstrap.min.css").read_bytes()
    css_hash = hashlib.sha256(canonical_css).hexdigest()
    assert base64.b64encode(hashlib.sha384(canonical_css).digest()).decode() == (
        "QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
    )
    baseline = {}
    sources = {}
    for route in routes:
        before = subprocess.check_output(["git", "show", base + ":" + route], cwd=root)
        baseline[route] = before
        sources[route] = dict(
            baseline=base, head=head,
            before_sha256=hashlib.sha256(before).hexdigest(),
            after_sha256=hashlib.sha256(original[route]).hexdigest(), bootstrap_sha256=css_hash)
    (safe / "source.json").write_text(json.dumps(sources, indent=2))
    php("FullHttpReadFixture.php", "--setup", output)
    database_alias()
    database_alias_created = True
    for entry in root.iterdir():
        if entry.name.startswith(".") or entry.name in {"output", "config.php", "includes"}:
            continue
        if entry.is_file() and entry.suffix == ".php":
            shutil.copy2(entry, baseline_root / entry.name)
        else:
            (baseline_root / entry.name).symlink_to(entry, target_is_directory=entry.is_dir())
    shutil.copytree(root / "includes", baseline_root / "includes")
    for route, before in baseline.items():
        (baseline_root / route).write_bytes(before)
    (baseline_root / "config.php").symlink_to(root / "config.php")
    start_servers()
    front.start()
    php("AdminDashboardHttp.php", output)
    native = json.loads((output / "http.json").read_text())
    for case, variants in native["samples"].items():
        if variants["before"][0]["status"] == 200:
            totals = {v: statistics.median(s["total_ms"] for s in samples) for v, samples in variants.items()}
            assert totals["after"] < totals["before"] * 1.2 + 20, (case, totals)
    (safe / "http.json.gz").write_bytes(gzip.compress(json.dumps(native).encode(), mtime=0))
    if browser_enabled:
        paths = ["/admin_dashboard.php"]
        cookies = []
        cookie_text = (output / "cookie-system_admin.txt").read_text()
        for line in cookie_text.splitlines():
            if line.startswith("#") and not line.startswith("#HttpOnly_"):
                continue
            if not line.strip():
                continue
            host, _, path, secure, _, name, value = line.removeprefix("#HttpOnly_").split("\t")
            assert host.lstrip(".") == "127.0.0.1" and secure in {"TRUE", "FALSE"}
            cookies.append(dict(name=name, value=value, domain="127.0.0.1", path=path,
                                secure=secure == "TRUE", httpOnly=line.startswith("#HttpOnly_"), sameSite="Lax"))
        assert cookies
        subprocess.run(["playwright-cli", "--session", profile, "open", "about:blank", "--browser=chrome"],
                       cwd=root, stdout=subprocess.DEVNULL, check=True)
        summaries = []
        for path in paths:
            for viewport, theme in [({"width": 1036, "height": 703}, "light"), ({"width": 390, "height": 844}, "dark")]:
                for cache in ("cold", "warm"):
                    summaries.append(browser(path, cookies, viewport, theme, cache, css_hash))
                    (safe / "browser-summary.json").write_text(json.dumps(summaries, indent=2))
    checksums = subprocess.check_output(
        ["php", "tests/Support/AdminDashboardHttp.php", str(output), "--verify"], cwd=root)
    (safe / "checksums.json").write_bytes(checksums)
finally:
    front.stop()
    stop_servers()
    if browser_enabled:
        subprocess.run(["playwright-cli", "--session", profile, "close"],
                       cwd=root, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    if (root / "config.php").exists():
        if database_alias_created:
            database_alias(remove=True)
            database_alias_created = False
        php("FullHttpReadCleanup.php", output)
        cleanup_ok = True
    (safe / "cleanup.json").write_text(json.dumps(dict(
        owned_schema_user_config_removed=cleanup_ok, owned_ports_closed=not servers,
        bridge_account_removed=not database_alias_created, private_artifacts_excluded=True,
        source_unchanged=all((root / route).read_bytes() == original[route] for route in routes)), indent=2))
