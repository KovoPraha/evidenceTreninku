// Real Chrome and real Apache responses, without mocks or synthetic latency.
async (page) => {
  const config = '__JS_COMPRESSION_CONFIG__';
  const cdp = await page.context().newCDPSession(page);
  await cdp.send('Network.enable');
  const errors = [], failed = [], samples = [], warmups = [], interactions = [], hashes = {};
  page.on('pageerror', e => errors.push(String(e)));
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  page.on('requestfailed', r => failed.push({url:r.url(), error:r.failure()?.errorText}));
  const signatures = {}, pictures = {};
  for (const viewport of [{id:'desktop',width:1036,height:703}, {id:'mobile',width:390,height:844}]) {
    await page.setViewportSize({width:viewport.width,height:viewport.height});
    for (const cache of ['cold','warm']) {
      await cdp.send('Network.clearBrowserCache');
      for (let round=-4; round<17; round++) {
        for (const variant of round % 2 === 0 ? ['before','after'] : ['after','before']) {
          await cdp.send('Network.setCacheDisabled', {cacheDisabled:cache==='cold'});
          if (cache==='cold') await cdp.send('Network.clearBrowserCache');
          const response = await page.goto(config.urls[variant]+'/probe.html', {waitUntil:'load'});
          if (response.status()!==200) throw new Error('Fixture status '+response.status());
          await page.waitForFunction(() => document.body.classList.contains('app-ui-ready'));
          await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
          const m = await page.evaluate(() => {
            const js = performance.getEntriesByType('resource').filter(r=>r.initiatorType==='script');
            const nav = performance.getEntriesByType('navigation')[0];
            return {fcp_ms:performance.getEntriesByType('paint').find(p=>p.name==='first-contentful-paint')?.startTime || 0,
              load_ms:nav.loadEventEnd, js_encoded_bytes:js.reduce((n,r)=>n+r.encodedBodySize,0),
              js_transfer_bytes:js.reduce((n,r)=>n+r.transferSize,0),
              resources:js.map(r=>({path:new URL(r.name).pathname,encoded:r.encodedBodySize,decoded:r.decodedBodySize,transfer:r.transferSize})),
              shape:['body','h1','.btn','input'].map(sel=>{
                const el=document.querySelector(sel),s=getComputedStyle(el),b=el.getBoundingClientRect();
                return {sel,text:el.textContent,display:s.display,color:s.color,font:s.font,padding:s.padding,
                  box:{x:b.x,y:b.y,width:b.width,height:b.height}};
              }), dom:document.body.outerHTML};
          });
          if (m.resources.length!==2 || m.resources.reduce((n,r)=>n+r.decoded,0)!==89282) {
            throw new Error('Two unchanged JS resources required: '+JSON.stringify(m.resources));
          }
          if (cache==='cold') {
            const total=m.resources.reduce((n,r)=>n+r.encoded,0);
            if ((variant==='before' && total!==89282) || (variant==='after' && total>=35713)) {
              throw new Error('Cold wire-byte acceptance failed: '+JSON.stringify(m.resources));
            }
          }
          const signature=JSON.stringify({shape:m.shape,dom:m.dom});
          const key=viewport.id;
          if (signatures[key] && signatures[key]!==signature) throw new Error('DOM/style mismatch '+key);
          signatures[key]=signature;
          delete m.shape;delete m.dom;
          (round<0?warmups:samples).push({variant,viewport:viewport.id,cache,round,...m});
        }
      }
    }
    // Exercise the actual unmodified scripts outside timed navigation samples.
    for (const variant of ['before','after']) {
      await page.goto(config.urls[variant]+'/probe.html', {waitUntil:'load'});
      await page.waitForFunction(() => document.body.classList.contains('app-ui-ready'));
      const picture=await page.screenshot({animations:'disabled'});
      if (pictures[viewport.id] && !pictures[viewport.id].equals(picture)) throw new Error('Pixel mismatch '+viewport.id);
      pictures[viewport.id]=picture;
      const result=await page.evaluate(async () => {
        const hash = async bytes => Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256', bytes)), b=>b.toString(16).padStart(2,'0')).join('');
        const bodies={};
        for (const script of document.scripts) {
          if (script.src) bodies[new URL(script.src).pathname]=await hash(await (await fetch(script.src)).arrayBuffer());
        }
        if (!window.bootstrap?.Collapse || !window.bootstrap?.Toast || typeof window.showToast!=='function') throw new Error('Missing UI APIs');
        if (document.querySelector('input').labels[0].htmlFor!==document.querySelector('input').id) throw new Error('Accessible label not linked');
        window.showToast('Transport check','success');
        if (document.querySelector('.toast-body span')?.textContent!=='Transport check') throw new Error('Toast failed');
        const form=document.querySelector('form');
        form.addEventListener('submit',event=>event.preventDefault(),{once:true});
        form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));
        await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));
        if (form.dataset.appSubmitting || form.hasAttribute('aria-busy')) throw new Error('Cancelled form left locked');
        return bodies;
      });
      if (hashes[viewport.id] && JSON.stringify(hashes[viewport.id])!==JSON.stringify(result)) throw new Error('Decoded browser hash mismatch');
      hashes[viewport.id]=result;
      await page.getByRole('button',{name:'Toggle content'}).click();
      await page.waitForFunction(() => document.querySelector('#content').classList.contains('show'));
      interactions.push({variant,viewport:viewport.id,bootstrap_collapse:true,toast:true,accessible_labels:true,cancelled_form_unlocked:true,pixels_equal:true});
    }
  }
  return {complete:true,scope:'Synthetic public static fixture; real Apache and unchanged production JS. No authenticated UAT.',
    samples,warmups,interactions,decoded_sha256:hashes,errors,failed};
}
