import concurrent.futures, datetime, hashlib, json, re, subprocess, sys, urllib.error, urllib.request
from pathlib import Path

root=Path.cwd()
base='https://kis.kovopraha.cz/'
def fetch(url, method='GET'):
    with urllib.request.urlopen(urllib.request.Request(url, method=method, headers={'Accept-Encoding':'identity'}),timeout=35) as r:
        return r.status,r.url,dict(r.headers),r.read()
rows=[]
for view,marker in [('text','class="sloupce"'),('photos','class="plocha"')]:
    status,url,headers,body=fetch(base+'bar/menu.php?view='+view)
    html=body.decode('utf-8'); assert status==200 and marker in html
    csp=headers.get('Content-Security-Policy','')
    directives={part.strip().split(' ',1)[0]:part.strip() for part in csp.split(';') if part.strip()}
    assert "'nonce-" in directives['script-src'] and "'unsafe-inline'" not in directives['script-src']
    assert "'strict-dynamic'" in directives['script-src'] and directives['script-src-attr']=="script-src-attr 'none'"
    assert headers.get('Cache-Control')=='no-store'
    rows.append({'view':view,'url':url,'status':status,'isolated_layout':True,'csp':csp,'bytes':len(body)})
    if view=='photos':
        products=json.loads(re.search(r'<script id="bar-screen-products"[^>]*>(.*?)</script>',html,re.S).group(1))
        assert len(products)==45 and len({p['id'] for p in products})==45
assets=[]
for name in ['bar-screen.css','bar-screen.js','bar-photos.css','bar-photos.js']:
    status,url,headers,body=fetch(base+'assets/'+name+'?v=20261110')
    expected=subprocess.check_output(['git','show','9ef52268e1f5a8925cb62af6ab6b59ba27f75ac6:assets/'+name])
    assert status==200 and body==expected,name
    assets.append({'asset':name,'status':status,'sha256':hashlib.sha256(body).hexdigest(),'deployed_bytes_match':True})
def image_check(p):
    status,url,headers,_=fetch(base+'bar/'+p['obrazek'],'HEAD')
    assert status==200 and headers.get('Content-Type','').startswith('image/'),p['id']
    return {'product_id':p['id'],'status':status,'content_type':headers['Content-Type']}
with concurrent.futures.ThreadPoolExecutor(max_workers=5) as pool:
    images=list(pool.map(image_check,[p for p in products if p['obrazek']]))
assert len(images)==45
try:
    fetch(base+'bar/menu.php?view=invalid')
    raise AssertionError('Invalid view was accepted')
except urllib.error.HTTPError as error:
    assert error.code==400
legacy=[]
if '--legacy' in sys.argv:
    for path,view in [('public/obrazovka.php','text'),('public/obrazovka2.php','photos')]:
        status,url,headers,body=fetch('https://bar.kovopraha.cz/'+path)
        assert status==200 and url==base+'bar/menu.php?view='+view
        legacy.append({'source':path,'final_url':url,'status':status})
report={'ok':True,'checked_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),'release_sha':'9ef52268e1f5a8925cb62af6ab6b59ba27f75ac6','pages':rows,'assets':assets,'invalid_view_status':400,'public_products':len(products),'images':images,'legacy':legacy,'visual_acceptance':'NOT RUN'}
file=root/'docs/verification/bar-display-stock-20261110'/('http-final.json' if legacy else 'http-deployed.json')
file.write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print(json.dumps({'ok':True,'pages':len(rows),'assets':len(assets),'products':len(products),'images':len(images),'legacy':len(legacy),'proof':str(file)}))
