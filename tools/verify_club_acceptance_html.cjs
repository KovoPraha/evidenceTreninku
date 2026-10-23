// Artifact-only QA in a fresh headless browser; never opens the application or production.
const path=require('node:path');const fs=require('node:fs');
const {pathToFileURL}=require('node:url');
const {createRequire}=require('node:module');
const runtimeRequire=createRequire('C:/Users/Marek/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/.acceptance-loader.cjs');
const {chromium}=runtimeRequire('playwright');
(async()=>{
 const root=path.resolve(__dirname,'..');const out=path.join(root,'output/uat-accountant-20261023');const qa=path.join(root,'tmp/acceptance-20261023');fs.mkdirSync(qa,{recursive:true});
 const browser=await chromium.launch({channel:'chrome',headless:true});
 const context=await browser.newContext({viewport:{width:1440,height:1100},acceptDownloads:true});
 // No HTTP(S) requests, including accidental links or external assets, may leave this artifact check.
 await context.route(/^https?:/,route=>route.abort());
 const page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto(pathToFileURL(path.join(out,'index.html')).href);await page.locator('.case').first().waitFor();
 const cases=JSON.parse(fs.readFileSync(path.join(out,'prijimaci-scenare.json'),'utf8'));
 const assert=(ok,label)=>{if(!ok)throw Error(label)};
 assert(await page.locator('.case').count()===cases.length,'case count');
 assert(await page.locator('.state-label').evaluateAll(els=>els.every(e=>e.textContent==='NEPROVEDENO')),'initial human states');
 await page.locator('#search').fill('UAT-S02');assert(await page.locator('.case:not(.hidden)').count()===1,'search filter');
 await page.locator('#search').fill('');await page.locator('#priority').selectOption('P0');assert(await page.locator('.case:not(.hidden)').count()===cases.filter(c=>c.priority==='P0').length,'priority filter');
 await page.locator('#priority').selectOption('');await page.locator('#role').selectOption(cases[0].roles[0]);assert(await page.locator('.case:not(.hidden)').count()===cases.filter(c=>c.roles.includes(cases[0].roles[0])).length,'role filter');
 await page.locator('#role').selectOption('');await page.locator('#UAT-S01 > summary').click();
 await page.locator('#UAT-S01 [data-field=status]').selectOption('PROŠLO');await page.locator('#UAT-S01 [data-field=tester]').fill('AUTOMATED ARTIFACT UI CHECK ONLY');await page.locator('#UAT-S01 [data-field=note]').fill('Temporary browser fixture; not human UAT.');await page.locator('#UAT-S01 [data-field=note]').blur();
 await page.reload();await page.locator('.case').first().waitFor();assert(await page.locator('#done-count').textContent()==='1','local persistence');
 const downloadPromise=page.waitForEvent('download');await page.locator('#export').click();const download=await downloadPromise;const exported=path.join(qa,'ui-check-results-only.json');await download.saveAs(exported);const data=JSON.parse(fs.readFileSync(exported,'utf8'));assert(data.contract==='club-uat-results-v1'&&data.results.length===cases.length,'export contract');assert(data.production_authorized===false,'no approval');
 await page.evaluate(()=>localStorage.clear());await page.reload();await page.locator('.case').first().waitFor();assert(await page.locator('#done-count').textContent()==='0','clean default');
 page.once('dialog',dialog=>dialog.accept());
 await page.locator('#import').setInputFiles(exported);await page.waitForFunction(()=>document.querySelector('#done-count')?.textContent==='1');assert(await page.locator('#done-count').textContent()==='1','import roundtrip');
 await page.evaluate(()=>localStorage.clear());await page.reload();await page.locator('.case').first().waitFor();await page.evaluate(()=>scrollTo(0,0));
 await page.screenshot({path:path.join(qa,'guide-desktop.png'),fullPage:false});
 await page.locator('#UAT-S01 > summary').click();await page.locator('#UAT-S01').scrollIntoViewIfNeeded();await page.screenshot({path:path.join(qa,'guide-case.png'),fullPage:false});
 await page.setViewportSize({width:390,height:844});await page.goto(pathToFileURL(path.join(out,'index.html')).href);await page.screenshot({path:path.join(qa,'guide-mobile.png'),fullPage:false});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth),'mobile horizontal overflow');
 await page.setViewportSize({width:1440,height:1100});await page.goto(pathToFileURL(path.join(out,'ucetni/PRECTETE-UCETNI.html')).href);await page.screenshot({path:path.join(qa,'accountant-guide.png'),fullPage:false});
 assert(errors.length===0,'browser errors: '+errors.join('; '));
 await browser.close();const report={status:'PASS',checks:12,scenarios:cases.length,external_requests_allowed:false,human_uat:false,checks_exercised:['initial states','search','priority','role','local persistence','JSON export','JSON import roundtrip','production authorization false','reset to clean state','desktop screenshot','mobile no horizontal overflow','accountant page and no JS errors']};fs.writeFileSync(path.join(qa,'html-verification.json'),JSON.stringify(report,null,2));console.log(JSON.stringify(report));
})().catch(e=>{console.error(e.message);process.exitCode=1});
