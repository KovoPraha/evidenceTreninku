// Real Chrome, real requests, no network mocks or artificial CDN delay.
async (page) => {
  const config = JSON.parse('__SHARED_HEADER_CONFIG__');
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
  const cases = config.role === 'coach'
    ? [{id:'workspace',path:'/pracovni_pozice.php'}, {id:'mine',path:'/moje_treninky.php'}]
    : config.role === 'sports_lead' ? [{id:'training',path:'/sprava_treninku.php'}]
    : [{id:'public-profile',path:'/sportovec_treninky.php'}];
  let requests = new Map();
  cdp.on('Network.requestWillBeSent', e => {
    if (/\/bootstrap\.min\.css(?:\?|$)/.test(e.request.url) || /\/assets\/staff-header\.js(?:\?|$)/.test(e.request.url)) {
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
                bootstrap_js:typeof window.bootstrap?.Modal==='function',title:document.title,
                document_decoded_bytes:nav.decodedBodySize,document_encoded_bytes:nav.encodedBodySize,document_transfer_bytes:nav.transferSize,
                staff_resources:performance.getEntriesByType('resource').filter(r=>new URL(r.name).pathname.endsWith('/assets/staff-header.js')).map(r=>({encoded:r.encodedBodySize,decoded:r.decodedBodySize,transfer:r.transferSize})),
                staff_config:!!document.getElementById('appStaffHeaderConfig'),
                context_csrf_matches:!document.getElementById('appStaffHeaderConfig') || JSON.parse(document.getElementById('appStaffHeaderConfig').textContent).csrfToken===document.querySelector('input[name=csrf_token]')?.value};
            });
            const css = Array.from(requests.values()).filter(r=>new URL(r.url).pathname.endsWith('/bootstrap.min.css'));
            const scripts = Array.from(requests.values()).filter(r=>new URL(r.url).pathname.endsWith('/assets/staff-header.js'));
            const expectedScript = config.role!=='public' && variant==='after';
            if(measurement.styles.length!==1 || css.length!==1 || !measurement.bootstrap_js)throw new Error('Bootstrap changed');
            if(scripts.length!==(expectedScript?1:0) || measurement.staff_config!==expectedScript || !measurement.context_csrf_matches)
              throw new Error('Staff context/resource contract: '+JSON.stringify({variant,case:item.id,scripts,config:measurement.staff_config,csrf:measurement.context_csrf_matches}));
            if(css.concat(scripts).some(r=>![200,304].includes(r.status)))throw new Error('Local asset failed');
            if(expectedScript && scripts.some(r=>r.encoding!=='gzip' || r.cache_control!=='max-age=30'))throw new Error('Actual gzip/cache policy missing');
            if(expectedScript && round===-4) {
              const payload=await cdp.send('Network.getResponseBody',{requestId:scripts[0].id});
              if(payload.base64Encoded)throw new Error('Unexpected encoded script body');
              const sha=await page.evaluate(async text=>Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256',new TextEncoder().encode(text)))).map(n=>n.toString(16).padStart(2,'0')).join(''),payload.body);
              if(sha!==config.asset_sha256)throw new Error('Executed bundle payload identity mismatch');
            }
            if (measurement.fcp_ms===null) throw new Error('Real FCP missing');
            const key=item.id+'-'+viewport.id;
            const signature=await page.evaluate(async value=>{
              const digest=await crypto.subtle.digest('SHA-256',new TextEncoder().encode(value));
              return Array.from(new Uint8Array(digest)).map(n=>n.toString(16).padStart(2,'0')).join('');
            },JSON.stringify({shape:measurement.shape,overflow:measurement.overflow}));
            styleSignatures[key]??=signature;
            if (styleSignatures[key]!==signature) throw new Error('Computed style/geometry differs: '+key);
            if (config.role==='public' && await page.locator('.save-note-btn').count()!==0) throw new Error('Public writer exposed');
            const staffNetwork=measurement.staff_resources.reduce((n,r)=>n+(r.transfer>0?r.encoded:0),0);
            if(!response.headers()['content-encoding']?.includes('gzip'))throw new Error('HTML gzip missing');
            (round>=0?samples:warmups).push({case:item.id,viewport:viewport.id,cache,round,variant,
              ...measurement,staff_network_payload:staffNetwork,combined_payload_bytes:measurement.document_encoded_bytes+staffNetwork,ready_ms:contentReady,shape:undefined,style_sha256:signature,css,scripts});
            if (cache==='cold' && round===0) {
              const path=config.screenshot_dir+'/shared-header-'+config.role+'-'+key+'-'+variant+'.png';
              await page.screenshot({path,fullPage:false});
              screenshots.push({case:item.id,viewport:viewport.id,variant,path});
            }
          }
        }
      }
    }
  }
  // Functional checks are outside timing and use both real application variants.
  if(config.role!=='public') for(const variant of ['before','after']) {
    const casesPath=cases.find(x=>x.id===config.case_filter).path;
    await page.goto(config.urls[variant]+casesPath,{waitUntil:'load'});
    if(config.case_filter==='mine')await page.waitForFunction(()=>document.querySelector('#listWrap [data-coach-history]') && document.getElementById('spinner').style.display==='none');
    await page.waitForLoadState('networkidle');
    const result={variant,case:config.case_filter,viewport:config.viewport_filter,cache:config.cache_filter};
    const toggles=page.locator('.navbar-toggler');
    for(let i=0;i<await toggles.count();i++) {
      const toggle=toggles.nth(i);if(!await toggle.isVisible())continue;
      const kind=await toggle.getAttribute('data-bs-toggle'),target=await toggle.getAttribute('data-bs-target');
      if(!['collapse','offcanvas'].includes(kind) || !/^#[a-zA-Z][\w-]*$/.test(target))throw new Error('Unknown navigation target');
      await toggle.click();await page.waitForFunction(selector=>document.querySelector(selector)?.classList.contains('show'),target);
      if(kind==='offcanvas')await page.locator(target+' [data-bs-dismiss="offcanvas"]').first().click();else await toggle.click();
      await page.waitForFunction(selector=>!document.querySelector(selector)?.classList.contains('show'),target);
      result.navigation=kind;
    }
    await page.evaluate(()=>window.showToast('Syntetická kontrola hlavičky','success'));
    await page.locator('#toastContainer .toast.show').last().waitFor({state:'visible'});
    await page.locator('#toastContainer .toast').last().locator('[data-bs-dismiss="toast"]').click();
    result.toast=true;
    await page.locator('h1').first().click();
    await page.keyboard.press('Shift+/');
    await page.locator('#shortcutsModal.show').waitFor({state:'visible'});
    await page.keyboard.press('Escape');
    await page.waitForFunction(()=>!document.getElementById('shortcutsModal').classList.contains('show'));
    result.shortcuts=true;
    for(const accept of [false,true]) {
      await page.evaluate(()=>{window.__hjsConfirmResult=undefined;window.appConfirm('Syntetická kontrola potvrzení').then(value=>window.__hjsConfirmResult=value);});
      await page.locator('#appConfirmModal.show').waitFor({state:'visible'});
      if(accept)await page.locator('#appConfirmModalOk').click();
      else await page.locator('#appConfirmModal [data-bs-dismiss="modal"]').first().click();
      await page.waitForFunction(value=>window.__hjsConfirmResult===value,accept);
    }
    result.confirm_accept_cancel=true;
    const probe=await page.evaluate(async()=>{
      const form=document.createElement('form');form.action='about:blank';form.method='post';form.id='hjs_probe';
      form.innerHTML='<input name="draft" required><button type="submit">Test</button>';document.body.appendChild(form);
      const input=form.querySelector('input'),button=form.querySelector('button');
      const invalid=!form.checkValidity();input.value='Syntetický rozepsaný text';const valid=form.checkValidity();
      const submit=()=>form.dispatchEvent(new SubmitEvent('submit',{bubbles:true,cancelable:true,submitter:button}));
      submit();await new Promise(resolve=>setTimeout(resolve,40));const locked=button.disabled;
      window.dispatchEvent(new PageTransitionEvent('pageshow',{persisted:false}));const ordinary_keeps_lock=button.disabled;
      window.dispatchEvent(new PageTransitionEvent('pageshow',{persisted:true}));const restored=!button.disabled;
      form.dataset.noLock='';submit();await new Promise(resolve=>setTimeout(resolve,40));const bypass=!button.disabled;
      delete form.dataset.noLock;form.addEventListener('submit',event=>event.preventDefault(),{once:true});submit();
      await new Promise(resolve=>setTimeout(resolve,40));const canceled_unlocked=!button.disabled;
      const draft=input.value==='Syntetický rozepsaný text';form.remove();
      return {invalid,valid,locked,ordinary_keeps_lock,restored,bypass,canceled_unlocked,draft};
    });
    if(Object.values(probe).some(value=>value!==true))throw new Error('Real DOM submit/pageshow contract: '+JSON.stringify(probe));
    result.form_probe=probe;result.form_probe_method='Native validity and dispatched submit/pageshow events on an owned DOM form; no backend write';
    interactions.push(result);
  }
  if (errors.length || failed.length) throw new Error('Browser errors: '+JSON.stringify({errors,failed}));
  page.off('pageerror',onError);page.off('console',onConsole);page.off('requestfailed',onFailed);
  await cdp.detach();
  return {complete:true,role:config.role,browser_version:page.context().browser().version(),
    method:'17 alternating pairs after 4 warmups, real network, no throttling/mocks; cold clears browser cache',
    samples,warmups,style_signatures:styleSignatures,screenshots,console_errors:errors,failed_requests:failed,
    optional_favicon_errors:optionalFaviconErrors,interactions};
}
