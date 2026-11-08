// Executed through Playwright CLI on an owned synthetic fixture. No network interception.
async (page) => {
  const config = JSON.parse('__COACH_CONFIG__');
  const context = page.context();
  const cdp = await context.newCDPSession(page);
  await cdp.send('Network.enable');
  await page.setViewportSize({width: 1036, height: 703});
  await page.addInitScript(() => {
    window.__coachHistorySequence = 0;
    const nativeFetch = window.fetch;
    let observer = null;
    window.fetch = function(...args) {
      const input = args[0];
      const url = new URL(typeof input === 'string' ? input : input.url, location.href);
      if (url.pathname.endsWith('/ajax_treninky.php')) {
        const sequence = ++window.__coachHistorySequence;
        window.__coachHistoryPending = {sequence, fetch_start_ms: performance.now()};
        if (!observer) {
          const wrap = document.getElementById('listWrap');
          observer = new MutationObserver(() => {
            const pending = window.__coachHistoryPending;
            if (!pending || !wrap.children.length) return;
            const domInserted = performance.now();
            requestAnimationFrame(() => requestAnimationFrame(() => {
              if (pending.sequence !== window.__coachHistorySequence) return;
              const meta = wrap.querySelector('[data-coach-history]');
              const resource = performance.getEntriesByType('resource').filter(r => new URL(r.name).pathname.endsWith('/ajax_treninky.php')).at(-1);
              window.__coachHistoryDone = {
                ...pending, dom_inserted_ms: domInserted, two_animation_frames_ms: performance.now(),
                fetch_to_dom_ms: domInserted - pending.fetch_start_ms,
                fetch_to_render_ready_ms: performance.now() - pending.fetch_start_ms,
                rows: wrap.querySelectorAll('.accordion-item').length,
                total: meta ? Number(meta.dataset.historyTotal) : wrap.querySelectorAll('.accordion-item').length,
                page: meta ? Number(meta.dataset.historyPage) : 1,
                ajax: resource ? {start_ms:resource.startTime, duration_ms:resource.duration,
                  response_start_ms:resource.responseStart, response_end_ms:resource.responseEnd,
                  encoded_bytes:resource.encodedBodySize, decoded_bytes:resource.decodedBodySize} : null
              };
            }));
          });
          observer.observe(wrap, {childList:true});
        }
      }
      return nativeFetch.apply(this, args);
    };
  });
  const report = {complete:false, variant:config.variant, role:'coach', cold_samples:3, warm_samples:10,
    browser_version:context.browser()?.version(), viewport:{width:1036,height:703},
    method:'Actual AJAX fetch start to DOM insertion and two animation frames; no network stubs; networkidle is excluded from metrics.', cases:[]};
  const errors = [], failed = [];
  page.on('pageerror', e => errors.push(String(e)));
  page.on('console', m => {if (m.type()==='error') errors.push(m.text());});
  page.on('requestfailed', r => failed.push({url:r.url(),error:r.failure()?.errorText}));
  const wait = async (previous = 0) => {
    await page.waitForFunction(p => window.__coachHistoryDone?.sequence > p
      && window.__coachHistoryDone.sequence === window.__coachHistorySequence
      && document.getElementById('spinner').style.display === 'none', previous, {timeout:60000});
  };
  const sequence = () => page.evaluate(() => window.__coachHistorySequence);
  for (const name of ['default','all','empty']) {
    const item = {id:name,samples:[]};
    for (const cache of ['cold','warm']) {
      await cdp.send('Network.setCacheDisabled',{cacheDisabled:cache==='cold'});
      for (let n = 0; n < (cache === 'cold' ? 3 : 11); n++) {
        if (cache==='cold') await cdp.send('Network.clearBrowserCache');
        const e0=errors.length, f0=failed.length;
        const response = await page.goto(config.base_url+'/moje_treninky.php',{waitUntil:'load',timeout:60000});
        await wait();
        if (name !== 'default') {
          const previous = await sequence();
          if (name === 'all') await page.locator('#btnClearFilter').click();
          else await page.locator('#filterSearch').fill('NoSuchCoachNeedle');
          await wait(previous);
        }
        await page.waitForLoadState('networkidle',{timeout:20000});
        const value = await page.evaluate(() => ({...window.__coachHistoryDone,
          url:location.href, navigation_count:document.querySelectorAll('nav.app-staff-topbar').length,
          count_label:document.getElementById('countLabel').textContent,
          document_ttfb_ms:performance.getEntriesByType('navigation')[0].responseStart,
          document_load_ms:performance.getEntriesByType('navigation')[0].loadEventEnd}));
        value.status=response.status();value.console_errors=errors.slice(e0);value.failed_requests=failed.slice(f0);
        if (value.status!==200 || value.navigation_count!==1 || !value.ajax || value.console_errors.length || value.failed_requests.length
          || value.url!==config.base_url+'/moje_treninky.php' || value.count_label!==(value.total>0 ? value.total+' tréninků' : '')
          || (name==='empty' && value.rows!==0)
          || (name==='all' && (value.total<1000 || (config.variant==='after' && value.rows!==25)))) {
          throw new Error('Actual coach AJAX/browser scenario failed: '+name);
        }
        if (cache==='cold' || n>0) item.samples.push({cache,...value});
      }
    }
    report.cases.push(item);
    if (name === 'all') await page.screenshot({path:config.screenshot_dir+'/coach-history-'+config.variant+'-desktop.png'});
  }
  if (config.variant==='after') {
    await page.goto(config.base_url+'/moje_treninky.php',{waitUntil:'load'});await wait();
    let previous=await sequence();await page.locator('#btnClearFilter').click();await wait(previous);
    previous=await sequence();await page.locator('#filterTeam').selectOption('1');await wait(previous);
    const ids=()=>page.locator('#listWrap .accordion-header').evaluateAll(nodes=>nodes.map(n=>n.id));
    const first=await ids();const total=await page.evaluate(()=>window.__coachHistoryDone.total);
    previous=await sequence();
    await page.getByRole('navigation',{name:'Stránkování tréninků'}).getByRole('link',{name:'Další',exact:true}).click();
    await wait(previous);const second=await ids();
    const secondState=await page.evaluate(()=>window.__coachHistoryDone);
    if(first.length!==25 || second.length!==25 || second.some(id=>first.includes(id)) || secondState.page!==2 || secondState.total!==total
      || await page.locator('#filterTeam').inputValue()!=='1') throw new Error('Coach pager/filter regression');
    previous=await sequence();
    await page.getByRole('navigation',{name:'Stránkování tréninků'}).getByRole('link',{name:'Předchozí',exact:true}).click();
    await wait(previous);if(JSON.stringify(await ids())!==JSON.stringify(first)) throw new Error('Previous page differs');
    previous=await sequence();await page.locator('#filterSearch').fill('NoSuchCoachNeedle');await wait(previous);
    const empty=await page.evaluate(()=>window.__coachHistoryDone);
    if(empty.rows!==0 || empty.total!==0 || empty.page!==1) throw new Error('Search must reset history page');
    previous=await sequence();await page.locator('#filterSearch').fill('');await wait(previous);
    const card=page.locator('#listWrap .accordion-item').first();
    await card.locator('.accordion-button').click();await card.locator('.accordion-collapse').waitFor({state:'visible'});
    const note=card.locator('[id^="poznamka-text-"]');const original=await note.textContent();
    await card.locator('.js-edit-poznamka').click();await card.locator('textarea').waitFor({state:'visible'});
    await card.locator('.js-cancel-poznamka').click();
    if(await note.textContent()!==original || !(await card.locator('[id^="poznamka-form-"]').evaluate(n=>n.classList.contains('d-none')))) {
      throw new Error('Note open/cancel changed the displayed note');
    }
    await page.setViewportSize({width:390,height:844});
    previous=await sequence();
    await page.getByRole('navigation',{name:'Stránkování tréninků'}).getByRole('link',{name:'Další',exact:true}).click();await wait(previous);
    const mobile=await page.getByRole('navigation',{name:'Stránkování tréninků'}).boundingBox();
    if((await ids()).length!==25 || !mobile || mobile.x<0 || mobile.x+mobile.width>390.5) throw new Error('Mobile pager unavailable');
    await page.screenshot({path:config.screenshot_dir+'/coach-history-after-mobile.png'});
    report.interactions={complete:true,next_previous:true,no_overlap:true,filters_preserved:true,search_reset:true,
      note_open_cancel:true,mobile_pager_within_viewport:true,production_writes:false};
  }
  await cdp.send('Network.setCacheDisabled',{cacheDisabled:false});await cdp.detach();
  report.complete=true;return report;
}
