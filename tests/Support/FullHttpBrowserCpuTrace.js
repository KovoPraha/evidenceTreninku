// Separate instrumented diagnosis, after plain browser measurements. Active role must be sports_lead.
async (page) => {
  const cdp = await page.context().newCDPSession(page);
  const report = { complete: false, method: 'CDP Timeline; single diagnostic run; nested durations are NOT additive or exclusive', cases: [] };
  for (const path of ['/planovac.php?jen_moje=0', '/training_closure_admin.php?id=101']) {
    const complete = new Promise(resolve => cdp.once('Tracing.tracingComplete', resolve));
    await cdp.send('Tracing.start', { categories: 'devtools.timeline,v8,blink.user_timing', transferMode: 'ReturnAsStream' });
    const response = await page.goto('http://127.0.0.1:18094' + path, { waitUntil: 'load', timeout: 60000 });
    await page.waitForLoadState('networkidle');
    await cdp.send('Tracing.end');
    const event = await complete;
    let text = '';
    let eof = false;
    while (!eof) {
      const chunk = await cdp.send('IO.read', { handle: event.stream });
      text += chunk.base64Encoded ? Buffer.from(chunk.data, 'base64').toString('utf8') : chunk.data;
      eof = chunk.eof;
    }
    await cdp.send('IO.close', { handle: event.stream });
    const trace = JSON.parse(text);
    const events = {};
    const scripts = {};
    for (const e of trace.traceEvents) {
      if (e.ph !== 'X' || typeof e.dur !== 'number') continue;
      events[e.name] ??= { name: e.name, count: 0, inclusive_ms: 0, max_ms: 0 };
      events[e.name].count++;
      events[e.name].inclusive_ms += e.dur / 1000;
      events[e.name].max_ms = Math.max(events[e.name].max_ms, e.dur / 1000);
      if (e.name === 'FunctionCall' && e.args?.data?.url) {
        const key = e.args.data.url + ':' + e.args.data.lineNumber;
        scripts[key] ??= { location: key, count: 0, inclusive_ms: 0 };
        scripts[key].count++;
        scripts[key].inclusive_ms += e.dur / 1000;
      }
    }
    report.cases.push({ path, status: response.status(), trace_events: trace.traceEvents.length,
      events: Object.values(events).sort((a,b) => b.inclusive_ms - a.inclusive_ms).slice(0, 25),
      scripts: Object.values(scripts).sort((a,b) => b.inclusive_ms - a.inclusive_ms).slice(0, 10) });
  }
  await cdp.detach();
  report.complete = true;
  return report;
}
