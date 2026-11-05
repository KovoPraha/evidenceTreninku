const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function player(slides) {
    const timeouts = new Map();
    const intervals = [];
    const rendered = [];
    let nextId = 0;
    let nextResponse;
    const nodes = {
        'tv-boot': { textContent: JSON.stringify({ revision: 1, document: { name: 'Recepce', slides }, image_base: '/tv_image.php?id=', source: '/tv.php?id=1&format=json' }) },
        'tv-screen': {},
        'tv-fullscreen': { addEventListener() {} },
        'tv-fullscreen-message': {},
    };
    const context = {
        window: { TvCanvas: { create: () => ({ render: item => rendered.push(item) }) } },
        document: {
            getElementById: id => nodes[id], addEventListener() {},
            body: { classList: { add() {}, remove() {} } },
        },
        setTimeout(callback, delay) { const id = ++nextId; timeouts.set(id, { callback, delay }); return id; },
        clearTimeout(id) { timeouts.delete(id); },
        setInterval(callback) { intervals.push(callback); },
        fetch: async () => nextResponse,
        Image: class { set src(value) { this.onload(); } },
    };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../assets/tv-player.js'), 'utf8'), context);
    return {
        rendered,
        async refresh(body, status = 200) {
            nextResponse = { ok: status === 200, status, json: async () => body };
            await intervals[0]();
        },
        boundary(delay) {
            const entry = [...timeouts].find(([, value]) => value.delay === delay);
            assert.ok(entry, `Missing playback timer ${delay}`);
            timeouts.delete(entry[0]);
            entry[1].callback();
        },
    };
}
const slide = (id, enabled = true, duration = 2) => ({ id, enabled, duration, blocks: [], asset_id: null });

test('carousel respects duration, order, disabled slides and loops', () => {
    const app = player([slide('first'), slide('hidden', false), slide('second', true, 4)]);
    assert.equal(app.rendered.at(-1).id, 'first');
    app.boundary(2000);
    assert.equal(app.rendered.at(-1).id, 'second');
    app.boundary(4000);
    assert.equal(app.rendered.at(-1).id, 'first');
});

test('a single slide stays mounted across boundaries', () => {
    const app = player([slide('only')]);
    app.boundary(2000);
    app.boundary(2000);
    assert.equal(app.rendered.length, 1);
});

test('publication waits for the slide boundary and an archived program clears there', async () => {
    const app = player([slide('old')]);
    await app.refresh({ revision: 2, document: { name: 'Nový', slides: [slide('new')] } });
    assert.equal(app.rendered.at(-1).id, 'old');
    app.boundary(2000);
    assert.equal(app.rendered.at(-1).id, 'new');
    await app.refresh({ revision: 0, document: null }, 404);
    assert.equal(app.rendered.at(-1).id, 'new');
    app.boundary(2000);
    assert.equal(app.rendered.at(-1), null);
});

test('empty publication renders a neutral screen and server errors preserve playback', async () => {
    const app = player([]);
    assert.equal(app.rendered.at(-1), null);
    await app.refresh({ message: 'unavailable' }, 503);
    app.boundary(1000);
    assert.equal(app.rendered.length, 1);
});
