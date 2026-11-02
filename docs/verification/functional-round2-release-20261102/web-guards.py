"""Anonymous GET-only exposure checks; no cookies, redirects or saved HTML."""
import datetime,json,urllib.request,urllib.error
from pathlib import Path

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,newurl):
        return None

opener=urllib.request.build_opener(NoRedirect())
results=[]
for path in ['/bin/seed-local-demo.php','/includes/localhost_demo_catalog.php','/testovaci_scenare.php']:
    request=urllib.request.Request('https://kis.kovopraha.cz'+path,headers={'User-Agent':'KIS-release274-readonly'})
    try: response=opener.open(request,timeout=45)
    except urllib.error.HTTPError as error: response=error
    with response:
        response.read()
        results.append({'path':path,'http':response.code,'ok':response.code in (403,404)})
report={'checked_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),'method':'GET only; no cookies or redirects',
        'checks':len(results),'passed':sum(x['ok'] for x in results),'results':results}
Path(__file__).with_name('web-guards.json').write_text(json.dumps(report,indent=2)+'\n',encoding='utf-8')
print(json.dumps(report,indent=2))
raise SystemExit(0 if all(x['ok'] for x in results) else 1)
