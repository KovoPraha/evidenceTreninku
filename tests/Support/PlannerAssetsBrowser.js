// Paired navigations through real PHP/Apache, with the real CDN request in the baseline.
async (page) => {
    const config = JSON.parse('__PLANNER_ASSETS_CONFIG__');
    await page.context().addCookies(config.cookies);
    await page.setViewportSize(config.viewport);
    await page.addInitScript(theme => localStorage.setItem('bs-theme', theme), config.theme);
    const cdp = await page.context().newCDPSession(page);
    await cdp.send('Network.enable');
    const css = new Map();
    const errors = [];
    const samples = [];
    const path = '/planovac.php';
    cdp.on('Network.responseReceived', event => {
        if (new URL(event.response.url).pathname.endsWith('/bootstrap.min.css')) {
            css.set(event.requestId, {url: event.response.url, status: event.response.status});
        }
    });
    const onError = error => errors.push(String(error));
    const onFailure = request => errors.push(new URL(request.url()).pathname + ': ' + request.failure()?.errorText);
    const onConsole = message => {
        if (message.type() === 'error' && !message.location().url.endsWith('/favicon.ico')) {
            errors.push(message.text());
        }
    };
    page.on('pageerror', onError);
    page.on('requestfailed', onFailure);
    page.on('console', onConsole);
    let styleSignature;
    for (let round = -3; round < 11; round++) {
        for (const variant of round % 2 === 0 ? ['before', 'after'] : ['after', 'before']) {
            await cdp.send('Network.setCacheDisabled', {cacheDisabled: config.cache === 'cold'});
            if (config.cache === 'cold') await cdp.send('Network.clearBrowserCache');
            css.clear();
            const response = await page.goto(config.urls[variant] + path, {waitUntil: 'load'});
            if (response.status() !== 200 || new URL(page.url()).pathname !== path) throw new Error('Planner unavailable');
            const ready = await page.evaluate(() => new Promise(resolve =>
                requestAnimationFrame(() => requestAnimationFrame(() => resolve(performance.now())))));
            await page.waitForLoadState('networkidle');
            const metrics = await page.evaluate(() => {
                const nav = performance.getEntriesByType('navigation')[0];
                const firstPaint = performance.getEntriesByName('first-contentful-paint')[0];
                const resources = performance.getEntriesByType('resource')
                    .filter(entry => new URL(entry.name).pathname.endsWith('/bootstrap.min.css'));
                return {
                    fcp_ms: firstPaint?.startTime,
                    html_bytes: nav.encodedBodySize,
                    css_bytes: resources.reduce((sum, entry) => sum + (entry.transferSize > 0 ? entry.encodedBodySize : 0), 0),
                    css_decoded_bytes: resources.reduce((sum, entry) => sum + entry.decodedBodySize, 0),
                    styles: Array.from(document.styleSheets).filter(sheet => sheet.href?.includes('bootstrap.min.css')).length,
                    shape: ['body', 'h1', '.navbar', '.day-header', '.plan-card', '.btn', '.form-select'].map(selector => {
                        const node = document.querySelector(selector);
                        if (!node) return null;
                        const style = getComputedStyle(node);
                        const box = node.getBoundingClientRect();
                        return [node.textContent.trim().slice(0, 100), style.color, style.backgroundColor,
                            style.fontSize, style.display, box.x, box.y, box.width, box.height];
                    }),
                    overflow: document.documentElement.scrollWidth > innerWidth,
                    bootstrap: typeof window.bootstrap?.Modal === 'function',
                };
            });
            const expected = variant === 'before' ? 2 : 1;
            if (metrics.styles !== expected || css.size !== expected || !metrics.bootstrap || !metrics.fcp_ms) {
                throw new Error('Planner stylesheet or timing contract: ' + JSON.stringify({variant, css: [...css.values()], metrics}));
            }
            if (config.cache === 'cold' && round === -3) {
                for (const requestId of css.keys()) {
                    const payload = await cdp.send('Network.getResponseBody', {requestId});
                    const hash = await page.evaluate(async text => {
                        const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(text));
                        return Array.from(new Uint8Array(digest), byte => byte.toString(16).padStart(2, '0')).join('');
                    }, payload.body);
                    if (payload.base64Encoded || hash !== config.css_sha256) throw new Error('Bootstrap payload mismatch');
                }
            }
            const signature = JSON.stringify([metrics.shape, metrics.overflow]);
            styleSignature ??= signature;
            if (styleSignature !== signature) throw new Error('Planner geometry or computed styles differ');
            const {shape, ...timing} = metrics;
            if (round >= 0) samples.push({variant, round, ...timing, ready_ms: ready});
            if (round === 0 && config.cache === 'cold') {
                await page.screenshot({path: config.screenshots[variant], animations: 'disabled'});
                const modal = page.locator('#modalKopirovat');
                await page.locator('[data-bs-target="#modalKopirovat"]').click();
                await modal.waitFor({state: 'visible'});
                await modal.locator('[data-bs-dismiss="modal"]').first().click();
                await modal.waitFor({state: 'hidden'});
            }
        }
    }
    page.off('pageerror', onError);
    page.off('requestfailed', onFailure);
    page.off('console', onConsole);
    await cdp.detach();
    if (errors.length) throw new Error('Unexpected browser errors: ' + JSON.stringify(errors));
    return {samples, complete: true};
};
