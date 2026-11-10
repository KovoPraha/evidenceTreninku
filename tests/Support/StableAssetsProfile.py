"""Owned MariaDB, two physical releases/origin and real Apache/Chrome cache measurements."""
import gzip, hashlib, json, os, shutil, signal, socket, statistics, subprocess, tempfile, time
from pathlib import Path
from StableAssetsApache import StableAssetsApache

root = Path(__file__).resolve().parents[2]
base = '50ce79b2e290b926a70e46f8a729682f12a7b864'
assert os.name != 'nt' and os.environ.get('EVIDENCE_PERFORMANCE_CONFIRM') == 'SYNTHETIC-ONLY'
runtime = Path(tempfile.mkdtemp(prefix='kis-stable-assets-'))
output = root / 'output/stable-assets-private'
safe = root / 'output/stable-assets-safe'
output.mkdir(exist_ok=False); safe.mkdir(exist_ok=False)
(runtime/'sessions').mkdir(); (runtime/'metrics').mkdir()
(output/'runtime.json').write_text(json.dumps(dict(runtime=str(runtime),datadir=os.environ['EVIDENCE_PERFORMANCE_DATADIR'],port=int(os.environ['EVIDENCE_MARIADB_SMOKE_PORT']),http_port=18099,synthetic_only=True)))
paths = ['assets/vendor/bootstrap/bootstrap.min.css','assets/vendor/bootstrap/bootstrap.bundle.min.js','assets/vendor/bootstrap-icons/font/bootstrap-icons.css','assets/app-ui.css','assets/app-ui.js']
originals = {p:(root/p).read_bytes() for p in paths+['booking/eshop.php','includes/ui_shell.php']}
servers = []; roots = {}; database_alias_created = False; cleanup_ok = False
front = StableAssetsApache(runtime, root, paths)

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


def start_servers():
    for variant, release, port in [('before','r1',18197),('after','r1',18198),('before','r2',18199),('after','r2',18200)]:
        document = runtime/(variant+'-'+release); document.mkdir(); roots[variant,release]=document
        for entry in root.iterdir():
            if entry.name.startswith('.') or entry.name in {'output','config.php','booking','includes','assets'}: continue
            if entry.is_file() and entry.suffix == '.php': shutil.copy2(entry, document/entry.name)
            else: (document/entry.name).symlink_to(entry,target_is_directory=entry.is_dir())
        for directory in ['includes','booking','assets']: shutil.copytree(root/directory,document/directory)
        config=(root/'config.php').read_text().replace('http://127.0.0.1:18099','http://127.0.0.1:'+('18099' if variant=='before' else '18100'))
        (document/'config.php').write_text(config)
        if variant == 'before':
            for path in ['includes/ui_shell.php','booking/eshop.php']:
                (document/path).write_bytes(subprocess.check_output(['git','show',base+':'+path],cwd=root))
        stamp=1794302581 if release=='r1' else 1794311532
        for path in paths: os.utime(document/path,(stamp,stamp))
        args=['php','-d','memory_limit=512M','-d','date.timezone=Europe/Prague','-d','display_errors=1','-d','session.save_path='+str(runtime/'sessions'),'-S','127.0.0.1:'+str(port),'-t',str(document)]
        with (runtime/'private-http.log').open('ab') as log:
            process=subprocess.Popen(args,cwd=document,env=os.environ|{'PHP_CLI_SERVER_WORKERS':'4'},stdout=log,stderr=log,start_new_session=True)
        assert os.getpgid(process.pid)==process.pid and process.pid>1
        servers.append((process,port))
        for _ in range(100):
            try:
                with socket.create_connection(('127.0.0.1',port),timeout=.2): break
            except OSError:
                assert process.poll() is None; time.sleep(.1)
        else: raise RuntimeError('Owned PHP unavailable')


def browser(case, viewport, cache):
    config=dict(urls=dict(before='http://127.0.0.1:18099',after='http://127.0.0.1:18100'),paths=paths,
        control='http://127.0.0.1:'+str(front.control.server_port),control_token=front.token,
        hashes={p:hashlib.sha256(originals[p]).hexdigest() for p in paths},
        screenshot_dir=str(safe),case_filter=case,viewport_filter=viewport,cache_filter=cache)
    script=runtime/'stable-browser.js'
    script.write_text((root/'tests/Support/StableAssetsBrowser.js').read_text().replace("JSON.parse('__STABLE_CONFIG__')",json.dumps(config)))
    p=subprocess.run(['playwright-cli','--session','stable-assets','run-code','--filename='+str(script)],cwd=root,text=True,stdout=subprocess.PIPE,stderr=subprocess.STDOUT,timeout=900)
    (runtime/'private-browser.log').write_text(p.stdout)
    if p.returncode or '### Error' in p.stdout:
        raise RuntimeError('Stable browser failure: '+p.stdout.split('### Error')[-1][:1800])
    report=json.JSONDecoder().raw_decode(p.stdout.split('### Result',1)[1].lstrip())[0]
    assert report['complete'] and not report['errors'] and not report['failed']
    (safe/f'browser-{case}-{viewport}-{cache}.json.gz').write_bytes(gzip.compress(json.dumps(report).encode(),mtime=0))
    if cache=='controls': return dict(case=case,viewport=viewport,controls_complete=True)
    row=dict(case=case,viewport=viewport,cache=cache)
    for variant in ['before','after']:
        samples=[s for s in report['samples'] if s['variant']==variant]
        assert len(samples)==17
        row[variant]={k:statistics.median(s[k] for s in samples) for k in ['fcp_ms','ready_ms','core_payload_bytes','document_payload_bytes','combined_payload_bytes']}
    for metric in ['fcp_ms','ready_ms']:
        assert row['after'][metric]<row['before'][metric]*1.2+20,('Browser regression',row)
    if cache=='release':
        assert row['before']['core_payload_bytes']>70000 and row['after']['core_payload_bytes']==0,row
    elif cache=='warm':
        assert row['before']['core_payload_bytes']==row['after']['core_payload_bytes']==0,row
    else:
        assert row['before']['core_payload_bytes']==row['after']['core_payload_bytes']>70000,row
    if report['screenshots']:
        pair={s['variant']:s['path'] for s in report['screenshots']}
        code=r"$a=imagecreatefrompng($argv[1]);$b=imagecreatefrompng($argv[2]);if(imagesx($a)!==imagesx($b)||imagesy($a)!==imagesy($b))exit(1);for($y=0;$y<imagesy($a);$y++)for($x=0;$x<imagesx($a);$x++)if(imagecolorat($a,$x,$y)!==imagecolorat($b,$x,$y))exit(2);"
        subprocess.run(['php','-r',code,pair['before'],pair['after']],check=True)
        row['decoded_pixels_equal']=True
    return row


try:
    contract=json.loads(subprocess.check_output(['php','tests/Support/StableAssetsSourceContract.php'],cwd=root))
    (safe/'source-contract.json').write_text(json.dumps(contract,indent=2))
    php('FullHttpReadFixture.php','--setup',output)
    database_alias();database_alias_created=True
    php('StableAssetsHttp.php',output,'--seed')
    start_servers();front.start()
    # Actual local synthetic JPEG, served under the same permitted image URL in both roots.
    for variant in ['before','after']:
        public=runtime/('apache-'+variant)/'public/uploads/shop-products';public.mkdir(parents=True)
        image=public/'00000000000000000000000000000001.jpg'
        subprocess.run(['php','-r',"$im=imagecreatetruecolor(64,64);imagefill($im,0,0,imagecolorallocate($im,40,120,180));imagejpeg($im,$argv[1],90);",str(image)],check=True)
    php('StableAssetsHttp.php',output)
    native=json.loads((output/'stable-http.json').read_text())
    (safe/'whole-http.json.gz').write_bytes(gzip.compress(json.dumps(native).encode(),mtime=0))
    native_summary={case:{v:{metric:statistics.median(s[metric] for s in samples) for metric in ['total_ms','ttfb_ms']} for v,samples in variants.items()} for case,variants in native['samples'].items()}
    for case,variants in native_summary.items():
        assert variants['after']['total_ms']<variants['before']['total_ms']*1.2+20,(case,variants)
    (safe/'native-summary.json').write_text(json.dumps(native_summary,indent=2))
    if os.environ.get('STABLE_ASSETS_BROWSER')=='1':
        subprocess.run(['playwright-cli','--session','stable-assets','open','http://127.0.0.1:18099/booking/prihlaseni.php','--browser=chrome'],cwd=root,check=True,stdout=subprocess.DEVNULL)
        summary=[]
        for case in ['login','catalog']:
            for viewport in ['desktop','mobile']:
                for cache in ['cold','warm','release']:
                    summary.append(browser(case,viewport,cache))
                    (safe/'browser-summary.json').write_text(json.dumps(summary,indent=2))
        browser('catalog','desktop','controls')
    assert all((root/p).read_bytes()==body for p,body in originals.items())
finally:
    front.stop();stop_servers()
    if os.environ.get('STABLE_ASSETS_BROWSER')=='1':
        subprocess.run(['playwright-cli','--session','stable-assets','close'],cwd=root,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    if (root/'config.php').exists():
        if database_alias_created: database_alias(remove=True);database_alias_created=False
        php('FullHttpReadCleanup.php',output);cleanup_ok=True
    (safe/'cleanup.json').write_text(json.dumps(dict(owned_schema_user_config_removed=cleanup_ok,owned_ports_closed=not servers,bridge_account_removed=not database_alias_created,source_unchanged=all((root/p).read_bytes()==body for p,body in originals.items()),private_artifacts_excluded=True),indent=2))
