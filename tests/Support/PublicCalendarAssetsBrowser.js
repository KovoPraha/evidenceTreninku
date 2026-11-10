// Real Chrome, real requests, no network mocks or artificial CDN delay.
async (page) => {
  const config = JSON.parse('__CALENDAR_ASSETS_CONFIG__');
  const cdp = await page.context().newCDPSession(page);
  await cdp.send('Network.enable');
  const errors = [], failed = [], samples = [], warmups = [], screenshots = [], styleSignatures = {}, optionalFaviconErrors = [], interactions = [];
  const onError=e=>errors.push(String(e));
  const onConsole=m=>{
    if(m.type()!=='error')return;
    const url=m.location().url;
    // Chromium probes this optional file even though the page never references it.
    if(url && new URL(url).pathname==='/favicon.ico' && m.text().includes('404')) {
      optionalFaviconErrors.push({url,message:m.text()});return;
    }
    errors.push({message:m.text(),url});
  };
  const onFailed=r=>failed.push({url:r.url().split('?')[0],error:r.failure()?.errorText});
  page.on('pageerror',onError);page.on('console',onConsole);page.on('requestfailed',onFailed);
  const cases = [
    {id:'calendar',path:'/booking/kalendar.php'},
    {id:'velodrome',path:'/booking/velodrom.php'},
    {id:'control',path:'/booking/klubovy_kalendar.php'}];
  let requests = new Map();
  cdp.on('Network.requestWillBeSent', e => {
    if (/\/bootstrap\.min\.css(?:\?|$)/.test(e.request.url)) {
      requests.set(e.requestId,{id:e.requestId,url:e.request.url,start:e.timestamp});
    }
  });
  cdp.on('Network.responseReceived', e => {
    if (requests.has(e.requestId)) Object.assign(requests.get(e.requestId),{
      status:e.response.status,cache:e.response.fromDiskCache||e.response.fromServiceWorker||false,
      protocol:e.response.protocol,encoding:e.response.headers['Content-Encoding']||e.response.headers['content-encoding'],cache_control:e.response.headers['Cache-Control']||e.response.headers['cache-control']});
  });
  cdp.on('Network.loadingFinished', e => {
    if (requests.has(e.requestId)) Object.assign(requests.get(e.requestId),{
      encoded_data_length:e.encodedDataLength,duration_ms:(e.timestamp-requests.get(e.requestId).start)*1000});
  });
  for (const viewport of [{id:'desktop',width:1036,height:703},{id:'mobile',width:390,height:844}]) {
    if (config.viewport_filter && viewport.id!==config.viewport_filter) continue;
    await page.setViewportSize({width:viewport.width,height:viewport.height});
    for (const item of cases) {
      if(config.case_filter && item.id!==config.case_filter)continue;
      for (const cache of ['cold','warm']) {
        if(config.cache_filter && cache!==config.cache_filter)continue;
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
              try {
                await page.waitForFunction(() => document.querySelector('#listWrap [data-coach-history]')
                  && getComputedStyle(document.querySelector('#spinner')).display==='none');
              } catch {
                const state=await page.evaluate(()=>({title:document.title,path:location.pathname,
                  list:document.querySelector('#listWrap')?.innerText.slice(0,250),
                  marker:!!document.querySelector('[data-coach-history]'),
                  spinner:document.querySelector('#spinner')?.style.display}));
                throw new Error('Mine content readiness: '+JSON.stringify({variant,state,errors,failed}));
              }
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
              const shape = ['body','h1','.navbar','.navbar-toggler','.nav-tabs','.card','.btn','.form-control'].map(selector=>{
                const el=document.querySelector(selector);
                if (!el) return {selector,missing:true};
                const style=getComputedStyle(el), box=el.getBoundingClientRect();
                return {selector,text:el.innerText.slice(0,100),styles:Object.fromEntries(fields.map(key=>[key,style[key]])),
                  box:{x:box.x,y:box.y,width:box.width,height:box.height}};
              });
              return {fcp_ms:paint?.startTime??null,ttfb_ms:nav.responseStart,load_ms:nav.loadEventEnd,
                ready_ms:performance.now(),styles,shape,
                overflow:document.documentElement.scrollWidth>document.documentElement.clientWidth,
                bootstrap_js:typeof window.bootstrap?.Modal==='function',title:document.title,
                document_decoded_bytes:nav.decodedBodySize,document_encoded_bytes:nav.encodedBodySize,document_transfer_bytes:nav.transferSize,
                css_resources:performance.getEntriesByType('resource').filter(r=>new URL(r.name).pathname.endsWith('/bootstrap.min.css')).map(r=>({encoded:r.encodedBodySize,decoded:r.decodedBodySize,transfer:r.transferSize})),
                resource_count:performance.getEntriesByType('resource').length};
            });
            const css = Array.from(requests.values()).filter(r=>new URL(r.url).pathname.endsWith('/bootstrap.min.css'));
            const expected=item.id!=='control' && variant==='before'?2:1;
            if(measurement.styles.length!==expected || css.length!==expected || !measurement.bootstrap_js)
              throw new Error('Bootstrap resource contract: '+JSON.stringify({variant,case:item.id,styles:measurement.styles,css,bootstrap:measurement.bootstrap_js}));
            if(css.some(r=>![200,304].includes(r.status)))throw new Error('CSS request failed');
            if(round===-4) for(const item of css) {
              const payload=await cdp.send('Network.getResponseBody',{requestId:item.id});
              if(payload.base64Encoded)throw new Error('Unexpected CSS encoding');
              const sha=await page.evaluate(async text=>Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256',new TextEncoder().encode(text)))).map(n=>n.toString(16).padStart(2,'0')).join(''),payload.body);
              if(sha!==config.css_sha256)throw new Error('CSS payload hash mismatch');
            }
            if (measurement.fcp_ms===null) throw new Error('Real FCP missing');
            const key=item.id+'-'+viewport.id;
            const signature=await page.evaluate(async value=>{
              const digest=await crypto.subtle.digest('SHA-256',new TextEncoder().encode(value));
              return Array.from(new Uint8Array(digest)).map(n=>n.toString(16).padStart(2,'0')).join('');
            },JSON.stringify({shape:measurement.shape,overflow:measurement.overflow}));
            styleSignatures[key]??=signature;
            if (styleSignatures[key]!==signature) throw new Error('Computed style/geometry differs: '+key);

            const cssNetwork=measurement.css_resources.reduce((n,r)=>n+(r.transfer>0?r.encoded:0),0);
            if(!response.headers()['content-encoding']?.includes('gzip'))throw new Error('HTML gzip missing');
            (round>=0?samples:warmups).push({case:item.id,viewport:viewport.id,cache,round,variant,
              ...measurement,css_network_payload:cssNetwork,css_decoded_bytes:measurement.css_resources.reduce((n,r)=>n+r.decoded,0),combined_payload_bytes:measurement.document_encoded_bytes+cssNetwork,ready_ms:contentReady,shape:undefined,style_sha256:signature,css});
            if (cache==='cold' && round===0) {
              const path=config.screenshot_dir+'/calendar-assets-'+key+'-'+variant+'.png';
              await page.screenshot({path,fullPage:false});
              screenshots.push({case:item.id,viewport:viewport.id,variant,path});
            }
          }
        }
      }
    }
  }
  const functional=[];
  for(const variant of ['before','after']) {
    const item=cases.find(x=>x.id===config.case_filter);
    await page.goto(config.urls[variant]+item.path,{waitUntil:'networkidle'});
    const row={variant,case:item.id,viewport:config.viewport_filter};
    if(await page.locator('.navbar-toggler:visible').count()) {
      const toggle=page.locator('.navbar-toggler:visible').first(), target=await toggle.getAttribute('data-bs-target');
      if(!/^#[a-zA-Z][\w-]*$/.test(target))throw new Error('Unknown navbar target');
      await page.evaluate(selector=>{const e=document.querySelector(selector);window.__navShown=false;window.__navHidden=false;e.addEventListener('shown.bs.collapse',()=>window.__navShown=true,{once:true});e.addEventListener('hidden.bs.collapse',()=>window.__navHidden=true,{once:true});},target);
      await toggle.click();await page.waitForFunction(()=>window.__navShown);
      await toggle.click();await page.waitForFunction(()=>window.__navHidden);row.mobile_navigation=true;
    }
    if(item.id==='calendar') {
      if(await page.locator('.cal-day.has-slots').count()===0)throw new Error('Synthetic calendar slots missing');
      {
        await page.locator('.cal-day.has-slots').first().click();
        await page.locator('#dayModal').waitFor({state:'visible'});
        if(!await page.locator('#dayModalBody').innerText())throw new Error('Calendar modal empty');
        await page.locator('#dayModal .btn-close').click();
        await page.locator('#dayModal').waitFor({state:'hidden'});row.day_modal=true;
      }
      const next=page.locator('a[href^="?od="]').last();
      if(await next.count()!==1)throw new Error('Month navigation missing');
      await next.click();await page.waitForLoadState('networkidle');
      if(!new URL(page.url()).searchParams.has('od')||!new URL(page.url()).pathname.endsWith('/kalendar.php'))throw new Error('Wrong month navigation target');
      row.month_navigation=true;
    } else if(item.id==='velodrome') {
      if(await page.locator('[id^="slot-"]').count()<2)throw new Error('Synthetic capacity cards missing');
      if(await page.locator('a[href="prihlaseni.php?redirect=velodrom.php"]').count()!==1)throw new Error('Login gate missing');
      row.capacity_and_login_gate=true;
    }
    functional.push(row);
  }
  page.off('pageerror',onError);page.off('console',onConsole);page.off('requestfailed',onFailed);await cdp.detach();
  return {complete:true,browser_version:page.context().browser().version(),samples,warmups,screenshots,style_signatures:styleSignatures,errors,failed,optional_favicon_errors:optionalFaviconErrors,functional,method:'real PHP/Apache/gzip and actual CDN;17 alternating pairs/4 warmups; no mock or artificial latency'};
}
