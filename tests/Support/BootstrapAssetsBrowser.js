// Real Chrome, real requests, no network mocks or artificial CDN delay.
async (page) => {
  const config = JSON.parse('__BOOTSTRAP_ASSETS_CONFIG__');
  const crypto = require('node:crypto');
  const cdp = await page.context().newCDPSession(page);
  await cdp.send('Network.enable');
  const errors = [], failed = [], samples = [], screenshots = [], styleSignatures = {};
  page.on('pageerror', e => errors.push(String(e)));
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  page.on('requestfailed', r => failed.push({url:r.url().split('?')[0],error:r.failure()?.errorText}));
  const cases = config.role === 'coach'
    ? [{id:'profile',path:'/sportovec_treninky.php?id=1001'}, {id:'mine',path:'/moje_treninky.php'}, {id:'training',path:'/sprava_treninku.php'}]
    : [{id:'public-profile',path:'/sportovec_treninky.php'}];
  let requests = new Map();
  cdp.on('Network.requestWillBeSent', e => {
    if (/\/bootstrap\.min\.css(?:\?|$)/.test(e.request.url)) {
      requests.set(e.requestId,{url:e.request.url,start:e.timestamp});
    }
  });
  cdp.on('Network.responseReceived', e => {
    if (requests.has(e.requestId)) Object.assign(requests.get(e.requestId),{
      status:e.response.status,cache:e.response.fromDiskCache||e.response.fromServiceWorker||false,
      protocol:e.response.protocol});
  });
  cdp.on('Network.loadingFinished', e => {
    if (requests.has(e.requestId)) Object.assign(requests.get(e.requestId),{
      encoded_data_length:e.encodedDataLength,duration_ms:(e.timestamp-requests.get(e.requestId).start)*1000});
  });
  for (const viewport of [{id:'desktop',width:1036,height:703},{id:'mobile',width:390,height:844}]) {
    await page.setViewportSize({width:viewport.width,height:viewport.height});
    for (const item of cases) {
      for (const cache of ['cold','warm']) {
        for (let round=-4; round<17; round++) {
          for (const variant of round%2===0 ? ['before','after'] : ['after','before']) {
            await cdp.send('Network.setCacheDisabled',{cacheDisabled:cache==='cold'});
            if (cache==='cold') await cdp.send('Network.clearBrowserCache');
            requests = new Map();
            const response = await page.goto(config.urls[variant]+item.path,{waitUntil:'load'});
            if (response.status()!==200) throw new Error('Asset page status '+response.status()+': '+item.id);
            if (item.id.includes('profile')) {
              await page.waitForFunction(() => document.querySelectorAll('#trainingListWrap .trenink-card').length===25);
            } else if (item.id==='mine') {
              await page.waitForFunction(() => document.querySelector('#listWrap')?.innerText.trim().length>10
                && !document.querySelector('#listWrap .spinner-border'));
            }
            const contentReady = await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(() => resolve(performance.now())))));
            await page.waitForLoadState('networkidle');
            await page.evaluate(() => window.scrollTo(0,0));
            await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
            const measurement = await page.evaluate(() => {
              const paint = performance.getEntriesByType('paint').find(p=>p.name==='first-contentful-paint');
              const nav = performance.getEntriesByType('navigation')[0];
              const styles = Array.from(document.styleSheets).filter(s=>s.href?.includes('bootstrap.min.css')).map(s=>s.href);
              const fields = ['display','color','backgroundColor','fontFamily','fontSize','fontWeight','borderRadius','padding','margin'];
              const shape = ['body','h1','.navbar','.navbar-toggler','.nav-tabs','.card','.btn','.form-select'].map(selector=>{
                const el=document.querySelector(selector);
                if (!el) return {selector,missing:true};
                const style=getComputedStyle(el), box=el.getBoundingClientRect();
                return {selector,text:el.innerText.slice(0,100),styles:Object.fromEntries(fields.map(key=>[key,style[key]])),
                  box:{x:box.x,y:box.y,width:box.width,height:box.height}};
              });
              return {fcp_ms:paint?.startTime??null,ttfb_ms:nav.responseStart,load_ms:nav.loadEventEnd,
                ready_ms:performance.now(),styles,shape,
                overflow:document.documentElement.scrollWidth>document.documentElement.clientWidth,
                bootstrap_js:typeof window.bootstrap?.Modal==='function',title:document.title};
            });
            const css = Array.from(requests.values());
            const expected = variant==='before' ? 2 : 1;
            if (measurement.styles.length!==expected || css.length!==expected || !measurement.bootstrap_js)
              throw new Error('Bootstrap resource contract: '+JSON.stringify({case:item.id,variant,styles:measurement.styles,css}));
            if (css.some(r=>![200,304].includes(r.status))) throw new Error('Bootstrap resource failed');
            if (variant==='after' && css.some(r=>r.url.includes('cdn.jsdelivr.net'))) throw new Error('CDN CSS retained');
            if (measurement.fcp_ms===null) throw new Error('Real FCP missing');
            const key=item.id+'-'+viewport.id;
            const signature=crypto.createHash('sha256').update(JSON.stringify(measurement.shape)).digest('hex');
            styleSignatures[key]??=signature;
            if (styleSignatures[key]!==signature) throw new Error('Computed style/geometry differs: '+key);
            if (config.role==='public' && await page.locator('.save-note-btn').count()!==0) throw new Error('Public writer exposed');
            if (round>=0) samples.push({case:item.id,viewport:viewport.id,cache,round,variant,
              ...measurement,ready_ms:contentReady,shape:undefined,style_sha256:signature,css});
            if (cache==='cold' && round===0) {
              const path=config.screenshot_dir+'/bootstrap-'+config.role+'-'+key+'-'+variant+'.png';
              await page.screenshot({path,fullPage:false});
              screenshots.push({case:item.id,viewport:viewport.id,variant,path});
            }
          }
        }
      }
    }
  }
  // An actual Bootstrap interaction after timing; restore the closed state.
  const toggle=page.locator('.navbar-toggler').first();
  if (await toggle.count() && await toggle.isVisible()) {
    await toggle.click();
    await page.waitForFunction(()=>document.querySelector('.navbar-collapse')?.classList.contains('show'));
    await toggle.click();
    await page.waitForFunction(()=>!document.querySelector('.navbar-collapse')?.classList.contains('show'));
  }
  if (errors.length || failed.length) throw new Error('Browser errors: '+JSON.stringify({errors,failed}));
  return {complete:true,role:config.role,browser_version:page.context().browser().version(),
    method:'17 alternating pairs after 4 warmups, real network, no throttling/mocks; cold clears browser cache',
    samples,style_signatures:styleSignatures,screenshots,console_errors:errors,failed_requests:failed};
};
