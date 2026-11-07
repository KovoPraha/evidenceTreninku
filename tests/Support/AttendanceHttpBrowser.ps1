param(
 [Parameter(Mandatory)][ValidateSet('coach','sports_lead','other_coach')][string]$Role,
 [Parameter(Mandatory)][ValidateSet('before','after')][string]$Variant,
 [Parameter(Mandatory)][string]$Output
)
$ErrorActionPreference='Stop'
$out=(Resolve-Path -LiteralPath $Output).Path
$browserDir='output/playwright/attendance-integrated-http-20261107'
New-Item -ItemType Directory -Path $browserDir -Force | Out-Null
$runtime=Get-Content -LiteralPath "$out/runtime.json" | ConvertFrom-Json
if (!$runtime.synthetic_only -or $runtime.base_url -ne 'http://127.0.0.1:18105') { throw 'Owned localhost required.' }
$session="attendance-http-$Role-$Variant"
$env:ATTENDANCE_BENCH_OUTPUT=$out
$env:ATTENDANCE_BENCH_ROLE=$Role
$env:ATTENDANCE_BENCH_VARIANT=$Variant
$env:PYTHONUTF8='1'
@'
import os,json
from pathlib import Path
out=Path(os.environ['ATTENDANCE_BENCH_OUTPUT']);role=os.environ['ATTENDANCE_BENCH_ROLE'];variant=os.environ['ATTENDANCE_BENCH_VARIANT']
p=Path('output/playwright/attendance-integrated-http-20261107')
runtime=json.loads((out/'runtime.json').read_text(encoding='utf-8-sig'))
c=json.loads((out/'credentials.json').read_text(encoding='utf-8-sig'))[role]
login="async (page) => { const c = "+json.dumps(c)+"; await page.getByRole('textbox', {name: 'Jméno nebo e-mail', exact: true}).fill(c.login); await page.getByRole('textbox', {name: 'Heslo', exact: true}).fill(c.password); await page.getByRole('button', {name: 'Přihlásit se', exact: true}).click(); await page.waitForLoadState('load'); return {url: page.url(), title: await page.title()}; }"
(p/('private-login-'+role+'.js')).write_text(login,encoding='utf-8')
cases=[c for c in json.loads((out/'matrix.json').read_text(encoding='utf-8-sig')) if c['role']==role and c['status']==200]
assert len(cases)=={'coach':5,'sports_lead':3,'other_coach':1}[role]
source=Path('tests/Support/FullHttpBrowserProfile.js').read_text(encoding='utf-8')
source=source.replace('  const context = page.context();','  await page.setViewportSize({width: 1036, height: 703});\n  const context = page.context();')
for c in cases:
 config={'role':role,'base_url':runtime['base_url'],'cases':[c]}
 (p/(c['id']+'-'+variant+'-measure.js')).write_text(source.replace("JSON.parse('__P11_CONFIG__')",json.dumps(config)),encoding='utf-8')
(p/(role+'-cases.json')).write_text(json.dumps(cases),encoding='utf-8')
'@ | python -
if ($LASTEXITCODE -ne 0) { throw 'Cannot prepare browser profile.' }
function Invoke-Browser([string[]]$Arguments,[string]$Log) {
 & npx --yes --package @playwright/cli playwright-cli "-s=$session" @Arguments *> $Log
 if ($LASTEXITCODE -ne 0) { throw "Browser command failed; inspect local log $Log" }
}
try {
 Invoke-Browser @('open',"$($runtime.base_url)/login.php",'--headed') "$browserDir/open-$Role-$Variant.log"
 Invoke-Browser @('snapshot') "$browserDir/login-dom-$Role-$Variant.log"
 try {
  Invoke-Browser @('run-code','--filename',"$browserDir/private-login-$Role.js") "$browserDir/private-login-$Role.log"
 } finally {
  Remove-Item -LiteralPath "$browserDir/private-login-$Role.js","$browserDir/private-login-$Role.log" -ErrorAction SilentlyContinue
 }
 Invoke-Browser @('snapshot') "$browserDir/auth-$Role-$Variant.log"
 $cases=Get-Content -LiteralPath "$browserDir/$Role-cases.json" | ConvertFrom-Json
 foreach ($case in $cases) {
  $env:ATTENDANCE_BENCH_CASE=$case.id
  Invoke-Browser @('run-code','--filename',"$browserDir/$($case.id)-$Variant-measure.js") "$browserDir/browser-$($case.id)-$Variant.log"
  @'
import os,json
from pathlib import Path
out=Path(os.environ['ATTENDANCE_BENCH_OUTPUT']);variant=os.environ['ATTENDANCE_BENCH_VARIANT'];name=os.environ['ATTENDANCE_BENCH_CASE']
p=Path('output/playwright/attendance-integrated-http-20261107')
s=(p/('browser-'+name+'-'+variant+'.log')).read_text(encoding='utf-8-sig').split('### Result',1)[1].lstrip()
d=json.JSONDecoder().raw_decode(s)[0]
assert d['complete'] and len(d['cases'])==1 and len(d['cases'][0]['samples'])==13
assert all(s['status']==200 and s['navigation_count']==1 for s in d['cases'][0]['samples'])
(out/('browser-'+name+'-'+variant+'.json')).write_text(json.dumps(d,indent=2),encoding='utf-8')
print(name,variant,'13 browser observations complete')
'@ | python -
  if ($LASTEXITCODE -ne 0) { throw 'Incomplete case report.' }
 }
 @'
import os,json
from pathlib import Path
out=Path(os.environ['ATTENDANCE_BENCH_OUTPUT']);role=os.environ['ATTENDANCE_BENCH_ROLE'];variant=os.environ['ATTENDANCE_BENCH_VARIANT']
p=Path('output/playwright/attendance-integrated-http-20261107');cases=json.loads((p/(role+'-cases.json')).read_text())
data=[json.loads((out/('browser-'+c['id']+'-'+variant+'.json')).read_text()) for c in cases]
result={k:v for k,v in data[0].items() if k!='cases'};result['cases']=[]
for d in data:
 assert all(d[k]==result[k] for k in ('role','viewport','device_pixel_ratio','browser_version'))
 result['cases'].extend(d['cases'])
result['complete']=True
(out/('browser-'+role+'-'+variant+'.json')).write_text(json.dumps(result,indent=2),encoding='utf-8')
print(role,variant,'all',len(result['cases']),'cases complete')
'@ | python -
 if ($LASTEXITCODE -ne 0) { throw 'Cannot combine complete role report.' }
} finally {
 Remove-Item -LiteralPath "$browserDir/private-login-$Role.js","$browserDir/private-login-$Role.log" -ErrorAction SilentlyContinue
 & npx --yes --package @playwright/cli playwright-cli "-s=$session" close *> "$browserDir/close-$Role-$Variant.log"
}
