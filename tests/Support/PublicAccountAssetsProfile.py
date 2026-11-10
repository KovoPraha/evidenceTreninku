"""Owned MariaDB + real PHP/Apache/Chrome comparison of public account CSS duplicates."""
import base64, gzip, hashlib, json, os, re, shutil, signal, socket, statistics, subprocess, tempfile, time
from pathlib import Path
from WorkspaceAssetsApache import WorkspaceAssetsApache

root=Path(__file__).resolve().parents[2]
base='01b2ca386a221f34ed6300148192c9af2cf4e7d9'
assert os.name!='nt' and os.environ.get('EVIDENCE_PERFORMANCE_CONFIRM')=='SYNTHETIC-ONLY'
runtime=Path(tempfile.mkdtemp(prefix='kis-account-assets-'))
output=root/'output/account-assets-private';safe=root/'output/account-assets-safe'
output.mkdir(exist_ok=False);safe.mkdir(exist_ok=False)
(runtime/'sessions').mkdir();(runtime/'metrics').mkdir()
(output/'runtime.json').write_text(json.dumps(dict(runtime=str(runtime),datadir=os.environ['EVIDENCE_PERFORMANCE_DATADIR'],port=int(os.environ['EVIDENCE_MARIADB_SMOKE_PORT']),http_port=18099,synthetic_only=True)))
routes=['booking/'+p for p in ['prihlaseni.php','registrace.php','sportovec_prihlaseni.php','zapomenute_heslo.php']]
originals={p:(root/p).read_bytes() for p in routes}
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


def median(values):
    return statistics.median(values)



def browser_case(case,viewport,cache):
    session='account-assets'
    config=dict(urls=dict(before='http://127.0.0.1:18099',after='http://127.0.0.1:18100'),screenshot_dir=str(safe),case_filter=case,viewport_filter=viewport,cache_filter=cache,css_sha256=hashlib.sha256((root/'assets/vendor/bootstrap/bootstrap.min.css').read_bytes()).hexdigest())
    script=runtime/'account-browser.js'
    script.write_text((root/'tests/Support/PublicAccountAssetsBrowser.js').read_text().replace("JSON.parse('__ACCOUNT_ASSETS_CONFIG__')",json.dumps(config)))
    p=subprocess.run(['playwright-cli','--session',session,'run-code','--filename='+str(script)],cwd=root,text=True,stdout=subprocess.PIPE,stderr=subprocess.STDOUT,timeout=900)
    (runtime/'private-browser.log').write_text(p.stdout)
    if p.returncode or '### Error' in p.stdout:
        raise RuntimeError('Browser failure: '+p.stdout.split('### Error')[-1][:1800])
    report=json.JSONDecoder().raw_decode(p.stdout.split('### Result',1)[1].lstrip())[0]
    assert report['complete'] and len(report['samples'])==34 and not report['errors'] and not report['failed']
    groups={v:[s for s in report['samples'] if s['variant']==v] for v in ['before','after']}
    summary=dict(case=case,viewport=viewport,cache=cache)
    for variant,samples in groups.items():
        summary[variant]={k:median(s[k] for s in samples) for k in ['fcp_ms','ready_ms','document_encoded_bytes','css_network_payload','combined_payload_bytes','css_decoded_bytes']}
    for metric in ['fcp_ms','ready_ms']:
        assert summary['after'][metric]<summary['before'][metric]*1.2+20,('Browser regression',summary)
    if cache=='cold' and case!='control':
        assert summary['before']['css_network_payload']>summary['after']['css_network_payload']+20000,summary
    if cache=='cold':
        assert summary['before']['css_decoded_bytes']==232803*(1 if case=='control' else 2),summary
        assert summary['after']['css_decoded_bytes']==232803,summary
    if report['screenshots']:
        pair={s['variant']:s['path'] for s in report['screenshots']}
        code=r"$a=imagecreatefrompng($argv[1]);$b=imagecreatefrompng($argv[2]);if(imagesx($a)!==imagesx($b)||imagesy($a)!==imagesy($b))exit(1);for($y=0;$y<imagesy($a);$y++)for($x=0;$x<imagesx($a);$x++)if(imagecolorat($a,$x,$y)!==imagecolorat($b,$x,$y))exit(2);"
        subprocess.run(['php','-r',code,pair['before'],pair['after']],check=True)
        summary['decoded_pixels_equal']=True
    (safe/f'browser-{case}-{viewport}-{cache}.json.gz').write_bytes(gzip.compress(json.dumps(report).encode(),mtime=0))
    return summary

try:
    php('FullHttpReadFixture.php','--setup',output)
    database_alias();database_alias_created=True
    # Physical PHP copies in BOTH nested booking/ and includes/. Symlinking booking
    # would execute optimized source in the baseline and invalidate comparison.
    for entry in root.iterdir():
        if entry.name.startswith('.') or entry.name in {'output','config.php','booking','includes'}:continue
        if entry.is_file() and entry.suffix=='.php':shutil.copy2(entry,baseline_root/entry.name)
        else:(baseline_root/entry.name).symlink_to(entry,target_is_directory=entry.is_dir())
    for name in ['booking','includes']:
        shutil.copytree(root/name,baseline_root/name)
    for route in routes:
        (baseline_root/route).write_bytes(subprocess.check_output(['git','show',base+':'+route],cwd=root))
    (baseline_root/'config.php').symlink_to(root/'config.php')
    # No application file outside the four explicitly selected routes may differ.
    # Replay the four CSS deletions on the CURRENT shared bootstrap. Every other
    # rendered PHP file must be byte-identical between the paired document roots.
    # The four selected routes keep their original exact source-token contract.
    shared_files=list(root.glob('*.php'))+list((root/'includes').rglob('*.php'))+list((root/'booking').rglob('*.php'))
    verified_shared=[]
    for path in shared_files:
        relative=path.relative_to(root).as_posix()
        if relative in routes or relative=='config.php':continue
        assert (baseline_root/relative).read_bytes()==path.read_bytes(),relative
        verified_shared.append(relative)
    (safe/'shared-context.json').write_text(json.dumps(dict(shared_context_sha=head,exact_shared_php_files=verified_shared),indent=2))
    contract=json.loads(subprocess.check_output(['php','tests/Support/PublicAccountAssetsSourceContract.php'],cwd=root))
    (safe/'exact-source-contract.json').write_text(json.dumps(contract,indent=2))
    canonical=(root/'assets/vendor/bootstrap/bootstrap.min.css').read_bytes()
    assert base64.b64encode(hashlib.sha384(canonical).digest()).decode()=='QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH'
    start_servers();front.start()
    php('PublicAccountAssetsHttp.php',output)
    native=json.loads((output/'account-http.json').read_text())
    (safe/'whole-http.json.gz').write_bytes(gzip.compress(json.dumps(native).encode(),mtime=0))
    native_summary={case:{v:{metric:median(s[metric] for s in samples) for metric in ['total_ms','ttfb_ms']} for v,samples in variants.items()} for case,variants in native['samples'].items()}
    for case,variants in native_summary.items():
        assert variants['after']['total_ms']<variants['before']['total_ms']*1.2+20,(case,variants)
    (safe/'native-summary.json').write_text(json.dumps(native_summary,indent=2))
    if os.environ.get('ACCOUNT_ASSETS_BROWSER')=='1':
        subprocess.run(['playwright-cli','--session','account-assets','open','http://127.0.0.1:18099/booking/prihlaseni.php','--browser=chrome'],cwd=root,check=True,stdout=subprocess.DEVNULL)
        summary=[]
        for case in ['login','registration','athlete','reset','trainer-reset','control']:
            for viewport in ['desktop','mobile']:
                for cache in ['cold','warm']:
                    summary.append(browser_case(case,viewport,cache))
                    (safe/'browser-summary.json').write_text(json.dumps(summary,indent=2))
    source_paths=routes+['db.php','includes/ui_shell.php','assets/.htaccess','assets/vendor/bootstrap/bootstrap.min.css','assets/vendor/bootstrap/bootstrap.bundle.min.js','assets/app-ui.css','assets/app-ui.js']
    (safe/'source-identity.json').write_text(json.dumps(dict(complete=True,baseline_sha=base,optimized_sha=head,source_sha256={p:hashlib.sha256((root/p).read_bytes()).hexdigest() for p in source_paths}),indent=2))
finally:
    front.stop();stop_servers()
    if os.environ.get('ACCOUNT_ASSETS_BROWSER')=='1':
        subprocess.run(['playwright-cli','--session','account-assets','close'],cwd=root,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    if (root/'config.php').exists():
        if database_alias_created:database_alias(remove=True);database_alias_created=False
        php('FullHttpReadCleanup.php',output);cleanup_ok=True
    (safe/'cleanup.json').write_text(json.dumps(dict(owned_schema_user_config_removed=cleanup_ok,owned_ports_closed=not servers,bridge_account_removed=not database_alias_created,source_unchanged=all((root/p).read_bytes()==originals[p] for p in routes),private_artifacts_excluded=True),indent=2))
