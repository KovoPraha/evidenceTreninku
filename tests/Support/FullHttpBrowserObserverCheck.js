// Calibration: native CDP navigation versus Playwright CLI's instrumented page.goto.
async (page) => {
  const cdp = await page.context().newCDPSession(page);
  await cdp.send('Page.enable');
  let flags = null;
  try {
    const command = await cdp.send('Browser.getBrowserCommandLine');
    flags = command.arguments.filter(a => /accessib|force-render|headless/.test(a));
  } catch {
    // Real Chrome may omit --enable-automation; do not infer its launch flags.
  }
  const report = { complete: false, method: 'native Page.navigate; no Playwright page.goto; warm assets; three observations',
    accessibility_flags: flags, cases: [] };
  for (const path of ['/planovac.php?jen_moje=0', '/training_closure_admin.php?id=102']) {
    const samples = [];
    for (let i = 0; i < 3; i++) {
      const loaded = new Promise(resolve => cdp.once('Page.loadEventFired', resolve));
      await cdp.send('Page.navigate', { url: 'http://127.0.0.1:18094' + path });
      await loaded;
      const result = await cdp.send('Runtime.evaluate', { returnByValue: true,
        expression: `JSON.stringify((() => { const n=performance.getEntriesByType('navigation')[0]; return {
          url:location.href,status:n.responseStatus,ttfb_ms:n.responseStart,response_end_ms:n.responseEnd,
          dom_content_loaded_ms:n.domContentLoadedEventEnd,load_ms:n.loadEventEnd}; })())` });
      samples.push(JSON.parse(result.result.value));
    }
    report.cases.push({ path, samples });
  }
  await cdp.detach();
  report.complete = true;
  return report;
}
