// Actual browser on a guarded, synthetic localhost fixture; no mocked responses.
async (page) => {
  const config = JSON.parse('__ATHLETE_CONFIG__');
  const context = page.context();
  const cdp = await context.newCDPSession(page);
  await cdp.send('Network.enable');
  await page.setViewportSize({width:1036,height:703});
  await page.addInitScript(() => {
    window.__profileDone = {};
    window.__profileSequence = 0;
    const original = window.fetch;
    window.fetch = function(...args) {
      const url = new URL(typeof args[0] === 'string' ? args[0] : args[0].url, location.href);
      if (url.pathname.endsWith('/ajax_sportovec_treninky.php')) {
        const name = url.searchParams.get('view') === 'measurements' ? 'measurements' : 'history';
        const wrap = document.getElementById(name === 'history' ? 'trainingListWrap' : 'measurementListWrap');
        const start = performance.now(), sequence = ++window.__profileSequence;
        const observer = new MutationObserver(() => {
          if (wrap.querySelector('.spinner-border')) return;
          const inserted = performance.now();
          observer.disconnect();
          requestAnimationFrame(() => requestAnimationFrame(() => {
            window.__profileDone[name] = {sequence,fetch_to_dom_ms:inserted-start,
              fetch_to_render_ready_ms:performance.now()-start,
              rows:wrap.querySelectorAll(name === 'history' ? '.trenink-card' : '#mereniTable tbody tr').length};
          }));
        });
        observer.observe(wrap,{childList:true});
      }
      return original.apply(this,args);
    };
  });
  const errors = [], failed = [];
  page.on('pageerror', e => errors.push(String(e)));
  page.on('console', m => {if(m.type()==='error') errors.push(m.text());});
  page.on('requestfailed', r => {if(!r.failure()?.errorText?.includes('ERR_ABORTED')) failed.push(r.failure()?.errorText);});
  const wait = async (name, previous = 0) => page.waitForFunction(({name,previous}) => window.__profileDone[name]?.sequence > previous,
    {name,previous},{timeout:60000});
  const seq = async name => page.evaluate(n => window.__profileDone[n]?.sequence || 0,name);
  const report = {complete:false,variant:config.variant,browser_version:context.browser()?.version(),
    method:'Full document navigation and actual history AJAX to DOM/two frames. Before measurements are included in parent GET; after lazy AJAX recorded separately.',cases:[]};
  for(const name of ['default','all','measurements']) {
    const item = {id:name,samples:[]};
    for(const cache of ['cold','warm']) {
      await cdp.send('Network.setCacheDisabled',{cacheDisabled:cache==='cold'});
      for(let n=0;n<(cache==='cold'?3:11);n++) {
        if(cache==='cold') await cdp.send('Network.clearBrowserCache');
        const e0=errors.length,f0=failed.length;
        await page.goto(config.base_url+'/sportovec_treninky.php?id=1001',{waitUntil:'load',timeout:60000});
        await wait('history');
        if(name==='all') {
          const previous=await seq('history');await page.locator('#rokFilter').selectOption('0');await wait('history',previous);
        }
        if(name==='measurements') {
          await page.locator('#mainTabs button[data-bs-target="#pane-mereni"]').click();
          if(config.variant==='after') await wait('measurements');
        }
        await page.waitForLoadState('networkidle',{timeout:20000});
        const value=await page.evaluate(() => {
          const nav=performance.getEntriesByType('navigation')[0];
          return {document_ttfb_ms:nav.responseStart,document_load_ms:nav.loadEventEnd,
            document_bytes:nav.decodedBodySize,history:window.__profileDone.history,
            measurements:window.__profileDone.measurements || null,
            measurement_rows:document.querySelectorAll('#mereniTable tbody tr').length,
            write_controls:document.querySelectorAll('.save-note-btn').length};
        });
        value.console_errors=errors.slice(e0);value.failed_requests=failed.slice(f0);
        if(value.console_errors.length || value.failed_requests.length || value.write_controls!==0
          || (config.variant==='after' && name==='measurements' && value.measurement_rows!==25)
          || (config.variant==='before' && value.measurement_rows!==9000)) throw new Error('Profile browser scenario failed: '+name);
        if(cache==='cold'||n>0) item.samples.push({cache,...value});
      }
    }
    report.cases.push(item);
  }
  if(config.variant==='after') {
    await page.evaluate(() => sessionStorage.removeItem('sp_treninky_tab'));
    await page.goto(config.base_url+'/sportovec_treninky.php?id=1001',{waitUntil:'load'});await wait('history');
    if(await page.locator('#mereniTable').count()!==0) throw new Error('Hidden measurements were eagerly materialized');
    let previous=await seq('history');await page.locator('#rokFilter').selectOption('0');await wait('history',previous);
    const history = () => page.locator('#trainingListWrap .trenink-card').allTextContents();
    const first=await history();
    previous=await seq('history');await page.locator('#trainingListWrap .profile-pagination a').filter({hasText:'Další'}).click();await wait('history',previous);
    const second=await history();if(second.length!==25 || second.some(s=>first.includes(s))) throw new Error('History page overlap');
    previous=await seq('history');await page.locator('#trainingListWrap .profile-pagination a').filter({hasText:'Předchozí'}).click();await wait('history',previous);
    if(JSON.stringify(await history())!==JSON.stringify(first)) throw new Error('History previous page changed');
    previous=await seq('history');await page.locator('#rokFilter').selectOption(String(new Date().getFullYear()));await wait('history',previous);
    if(!(await page.locator('#trainingListWrap .profile-pagination').textContent()).includes('strana 1 ')) throw new Error('Year did not reset page');
    await page.locator('#mainTabs button[data-bs-target="#pane-mereni"]').click();await wait('measurements');
    previous=await seq('measurements');await page.locator('.mereni-filter-btn[data-typ="kolo"]').click();await wait('measurements',previous);
    const types=()=>page.locator('#mereniTable tbody tr').evaluateAll(rows=>rows.map(r=>r.dataset.typ));
    if((await types()).some(t=>t!=='kolo')) throw new Error('Global exact measurement type filter changed');
    previous=await seq('measurements');await page.locator('#measurementListWrap .profile-pagination a').filter({hasText:'Další'}).click();await wait('measurements',previous);
    if((await types()).length!==25 || (await types()).some(t=>t!=='kolo')) throw new Error('Measurement page lost type filter');
    previous=await seq('measurements');await page.locator('.mereni-filter-btn[data-typ="KOLO"]').click();await wait('measurements',previous);
    if((await types()).some(t=>t!=='KOLO') || !(await page.locator('#measurementListWrap .profile-pagination').textContent()).includes('strana 1 ')) {
      throw new Error('Case-sensitive type filter or page reset changed');
    }
    await page.reload({waitUntil:'load'});await wait('measurements');
    if(!await page.locator('#pane-mereni').evaluate(el=>el.classList.contains('active'))) throw new Error('Saved measurement tab failed');
    await page.waitForFunction(() => getComputedStyle(document.getElementById('pane-mereni')).opacity === '1');
    await page.screenshot({path:config.screenshot_dir+'/athlete-profile-after-desktop.png'});
    await page.setViewportSize({width:390,height:844});
    const pager=page.locator('#measurementListWrap .profile-pagination');await pager.scrollIntoViewIfNeeded();
    const box=await pager.boundingBox();if(!box||box.x<0||box.x+box.width>390.5) throw new Error('Mobile pager outside viewport');
    await page.screenshot({path:config.screenshot_dir+'/athlete-profile-after-mobile.png'});
    report.interactions={complete:true,lazy_measurements:true,saved_tab:true,history_next_previous:true,no_overlap:true,
      year_resets_page:true,global_exact_type_filter:true,measurement_pager_keeps_type:true,type_resets_page:true,
      read_only_note_controls:true,mobile_pager:true,production_writes:false};
  }
  await cdp.detach();report.complete=true;return report;
}
