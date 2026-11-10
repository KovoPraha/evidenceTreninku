// Real Chrome, real Apache compression/cache headers, four physical PHP roots; no request mocks.
async (page) => {
  const config = JSON.parse('__STABLE_CONFIG__');
  const cdp = await page.context().newCDPSession(page);
  await cdp.send('Network.enable');
  const errors=[], failed=[], baselineCspErrors=[], samples=[], warmups=[], screenshots=[];
  let activeVariant='', activeCase='', requests=new Map();
  page.on('pageerror', e=>errors.push(String(e)));
  page.on('console', m=>{
    if(m.type()!=='error')return;
    const url=m.location().url||'';
    if(url.endsWith('/favicon.ico') && m.text().includes('404'))return;
    if(activeVariant==='before' && activeCase==='catalog' && m.text().includes('Executing inline event handler') && m.text().includes('script-src-attr')) {
      baselineCspErrors.push({variant:activeVariant,message:m.text()});return;
    }
    errors.push({variant:activeVariant,message:m.text(),url});
  });
  page.on('requestfailed', r=>failed.push({url:r.url().split('?')[0],error:r.failure()?.errorText}));
  const core = url => config.paths.includes(new URL(url).pathname.slice(1));
  cdp.on('Network.requestWillBeSent', e=>{
    if(core(e.request.url)) requests.set(e.requestId,{id:e.requestId,url:e.request.url});
  });
  cdp.on('Network.responseReceived', e=>{
    if(requests.has(e.requestId))Object.assign(requests.get(e.requestId),{status:e.response.status,disk:e.response.fromDiskCache||false,
      cache_control:e.response.headers['Cache-Control']||e.response.headers['cache-control']});
  });
  cdp.on('Network.responseReceivedExtraInfo', e=>{
    if(requests.has(e.requestId)) requests.get(e.requestId).wire_status=e.statusCode;
  });
  const control=async(variant,phase)=>{
    const response=await page.request.post(config.control+'/'+variant+'/'+phase,{headers:{Authorization:'Bearer '+config.control_token}});
    if(response.status()!==204)throw new Error('Owned release control failed '+response.status());
  };
  const navigate=async(variant,release,path)=>{
    activeVariant=variant;activeCase=path.includes('eshop')?'catalog':'login';requests=new Map();
    const response=await page.goto(config.urls[variant]+'/'+release+path,{waitUntil:'load'});
    if(response.status()!==200)throw new Error('Page failed '+response.status());
    if(config.cache_filter==='controls')await page.evaluate(()=>document.querySelectorAll('img').forEach(i=>i.loading='eager'));
    const ready=await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(()=>resolve(performance.now())))));
    await page.waitForLoadState('networkidle');
    const state=await page.evaluate(paths=>{
      const nav=performance.getEntriesByType('navigation')[0];
      const resources=performance.getEntriesByType('resource').filter(r=>paths.includes(new URL(r.name).pathname.slice(1)))
        .map(r=>({url:r.name,encoded:r.encodedBodySize,decoded:r.decodedBodySize,transfer:r.transferSize}));
      const shape=['body','h1','.navbar','.card','.btn','.form-control'].map(selector=>{
        const el=document.querySelector(selector);if(!el)return{selector,missing:true};
        const style=getComputedStyle(el),rect=el.getBoundingClientRect();
        return{selector,text:el.innerText.slice(0,100),color:style.color,font:style.font,padding:style.padding,
          box:{x:rect.x,y:rect.y,width:rect.width,height:rect.height}};
      });
      return{fcp_ms:performance.getEntriesByType('paint').find(p=>p.name==='first-contentful-paint')?.startTime,
        document_payload_bytes:nav.encodedBodySize,resources,shape,bootstrap:typeof window.bootstrap?.Modal==='function',
        images:Array.from(document.querySelectorAll('img')).map(i=>({src:new URL(i.currentSrc||i.src).pathname,
          visible:i.getBoundingClientRect().top<innerHeight&&i.getBoundingClientRect().bottom>0,valid:i.complete&&i.naturalWidth>0}))};
    },config.paths);
    if(state.fcp_ms===undefined || !state.bootstrap || state.resources.length!==5)throw new Error('Incomplete real page '+JSON.stringify(state));
    const network=Array.from(requests.values());
    if(network.length!==5)throw new Error('Missing core network records');
    state.core_payload_bytes=state.resources.reduce((sum,r)=>{
      const request=network.find(n=>n.url===r.url);
      return sum+(r.transfer>0 && request.wire_status!==304?r.encoded:0);
    },0);
    state.combined_payload_bytes=state.document_payload_bytes+state.core_payload_bytes;
    state.ready_ms=ready;state.network=network;
    return state;
  };
  const path=config.case_filter==='catalog'?'/booking/eshop.php':'/booking/prihlaseni.php';
  await page.setViewportSize(config.viewport_filter==='mobile'?{width:390,height:844}:{width:1036,height:703});
  const styles={};
  if(config.cache_filter==='controls') {
    // Real 31s expiration: CSS stays fresh; preserve and measure the existing 30s JS policy.
    await cdp.send('Network.setCacheDisabled',{cacheDisabled:false});await cdp.send('Network.clearBrowserCache');
    for(const variant of ['before','after']) {await control(variant,'r1');await navigate(variant,'r1',path);}
    await page.waitForTimeout(31000);
    const expiry={};
    for(const variant of ['before','after']) {await control(variant,'r2');expiry[variant]=await navigate(variant,'r2',path);}
    const cssBytes=s=>s.resources.filter(r=>new URL(r.url).pathname.endsWith('.css')).reduce((n,r)=>n+(r.transfer>0&&s.network.find(v=>v.url===r.url).wire_status!==304?r.encoded:0),0);
    if(cssBytes(expiry.before)<50000 || cssBytes(expiry.after)!==0)throw new Error('CSS expiration control failed');
    // A true byte change runs the actual offline builder in the owned after-r2 root.
    await cdp.send('Network.clearBrowserCache');
    const original=await navigate('after','r2',path);await control('after','content');
    const changed=await navigate('after','r2',path);
    const changedUrls=changed.resources.filter(r=>!original.resources.some(o=>o.url===r.url));
    if(changedUrls.length!==1 || !changedUrls[0].url.includes('/assets/app-ui.css?') || changedUrls[0].transfer===0)
      throw new Error('Content invalidation did not fetch exactly the changed stylesheet');
    if(JSON.stringify(original.shape)!==JSON.stringify(changed.shape))throw new Error('Comment-only mutation changed rendering');
    // Known broken photo exercises the actual CSP authorization, without weakening the guard.
    const fallback={};
    for(const variant of ['before','after']) {
      await control(variant,'broken');await cdp.send('Network.clearBrowserCache');
      const state=await navigate(variant,'r2','/booking/eshop.php');
      fallback[variant]=state.images;
    }
    if(!fallback.before.some(i=>!i.valid) || fallback.after.some(i=>!i.valid))throw new Error('Trusted image fallback failed');
    return{complete:!errors.length&&!failed.length,errors,failed,baselineCspErrors,expiry,content_invalidation:{changed_urls:changedUrls.map(r=>new URL(r.url).pathname),other_four_urls_unchanged:true,shape_equal:true},fallback};
  }
  for(let round=-4;round<17;round++) {
    for(const variant of round%2===0?['before','after']:['after','before']) {
      await cdp.send('Network.setCacheDisabled',{cacheDisabled:false});
      await cdp.send('Network.clearBrowserCache');
      if(config.cache_filter==='release') {
        await control(variant,'r1');await navigate(variant,'r1',path);await control(variant,'r2');
      } else {
        await control(variant,'r2');
        if(config.cache_filter==='warm')await navigate(variant,'r2',path);
      }
      const state=await navigate(variant,'r2',path);
      if(state.images.some(i=>i.visible&&!i.valid))throw new Error('Normal visible image failed');
      const shape=JSON.stringify(state.shape);
      styles[config.case_filter]??=shape;
      if(styles[config.case_filter]!==shape)throw new Error('Computed style/geometry mismatch');
      if(round===-4 && config.cache_filter==='cold') {
        for(const request of state.network) {
          const body=await cdp.send('Network.getResponseBody',{requestId:request.id});
          const digest=await page.evaluate(async text=>Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256',new TextEncoder().encode(text)))).map(n=>n.toString(16).padStart(2,'0')).join(''),body.body);
          if(body.base64Encoded||digest!==config.hashes[new URL(request.url).pathname.slice(1)])throw new Error('Actual core payload hash changed');
        }
      }
      if(round===0&&config.cache_filter==='cold') {
        const shot=config.screenshot_dir+'/stable-'+config.case_filter+'-'+config.viewport_filter+'-'+variant+'.png';
        await page.screenshot({path:shot});screenshots.push({variant,path:shot});
      }
      delete state.shape;
      (round>=0?samples:warmups).push({variant,round,...state});
    }
  }
  return{complete:!errors.length&&!failed.length,errors,failed,baselineCspErrors,samples,warmups,screenshots,styles,browser_version:page.context().browser().version()};
}
