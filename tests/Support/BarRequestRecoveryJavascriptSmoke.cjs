'use strict';
const {test} = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../assets/bar-requests.js'), 'utf8');
const tokenA = 'a'.repeat(64);
const tokenB = 'b'.repeat(64);
const key = 'kis.bar.pending:v1:7:1';

function record(url = 'http://localhost/bar/cashbox.php') {
    return {user: '7:1', token: tokenA, url,
        fields: [['csrf_token', 'old-csrf'], ['action', 'open'], ['token', tokenA], ['opening', '100.00'], ['reason', 'LOCALHOST verified cash']]};
}

function harness(options = {}) {
    class Element {
        constructor(tag) { this.tagName = tag.toUpperCase(); this.children = []; this.dataset = {}; this.events = {}; this.textContent = ''; this.attributes = {}; }
        appendChild(child) { this.children.push(child); return child; }
        replaceChildren() { this.children = []; }
        addEventListener(name, fn) { this.events[name] = fn; }
        focus() {}
        getAttribute(name) { return this.attributes[name] ?? null; }
    }
    class Form extends Element {
        constructor() { super('form'); this.method = 'post'; this.action = 'http://localhost/bar/checkout.php'; }
        get method() { return this.attributes.method; }
        set method(value) { this.attributes.method = value; }
        get action() { return this.attributes.action; }
        set action(value) { this.attributes.action = value; }
    }
    class Data {
        constructor(form) { this.fields = form.fields || form.children.filter(node => node.tagName === 'INPUT').map(node => [node.name, node.value]); }
        get(name) { return this.fields.find(pair => pair[0] === name)?.[1] ?? null; }
        [Symbol.iterator]() { return this.fields[Symbol.iterator](); }
    }
    const values = new Map(Object.entries(options.storage || {}));
    if (options.pending) values.set(key, JSON.stringify(options.pending));
    const storage = {
        get length() { return values.size; }, key(index) { return [...values.keys()][index]; },
        getItem(name) { return values.get(name) ?? null; },
        setItem(name, value) { if (options.storageFails) throw Error('quota'); values.set(name, value); },
        removeItem(name) { values.delete(name); }
    };
    const mount = new Element('div');
    mount.dataset = {user: '7:1', csrf: 'fresh-csrf', prefix: options.prefix || '/bar/', rejected: options.rejected || ''};
    const listeners = {}, windowListeners = {}, calls = [];
    const document = {getElementById() { return mount; }, createElement(tag) { return tag === 'form' ? new Form() : new Element(tag); },
        addEventListener(name, fn) { listeners[name] = fn; }};
    const location = new URL(options.url || 'http://localhost/bar/checkout.php');
    const window = {sessionStorage: storage, location, addEventListener(name, fn) { windowListeners[name] = fn; }};
    const fetch = async (url, settings) => {
        calls.push({url, settings});
        if (options.offline) throw Error('offline');
        return {ok: true, json: async () => options.response || {state: 'unknown', result_url: null}};
    };
    vm.runInNewContext(source, {document, window, HTMLFormElement: Form, FormData: Data, URL, AbortController, fetch, setTimeout, clearTimeout});
    const form = (token = tokenB, url = location.href) => {
        const item = new Form(); item.action = url;
        item.fields = [['csrf_token', 'fresh-csrf'], ['action', 'open'], ['token', token], ['opening', '100.00'], ['reason', 'LOCALHOST verified cash']];
        return item;
    };
    const submit = target => {
        const event = {target, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }};
        listeners.submit(event);
        return event;
    };
    const nodes = () => {
        const all = []; const visit = node => { all.push(node); node.children.forEach(visit); }; visit(mount); return all;
    };
    return {values, mount, calls, form, submit, nodes, windowListeners, ready: () => new Promise(resolve => setImmediate(resolve))};
}

test('confirmation is retained before submission without any automatic financial POST', async () => {
    const h = harness(); await h.ready();
    assert.equal(h.submit(h.form()).defaultPrevented, false);
    assert.equal(JSON.parse(h.values.get(key)).token, tokenB);
    assert.equal(h.calls.length, 0);
});

test('payment method select and hidden action field cannot shadow form attributes', async () => {
    const h = harness(); await h.ready();
    const form = h.form();
    Object.defineProperty(form, 'method', {value: {tagName: 'SELECT'}});
    Object.defineProperty(form, 'action', {value: {tagName: 'INPUT'}});
    assert.equal(h.submit(form).defaultPrevented, false);
    assert.equal(JSON.parse(h.values.get(key)).url, 'http://localhost/bar/checkout.php');
});

test('unknown result only performs a read and offers exact original token with fresh CSRF', async () => {
    const h = harness({pending: record()}); await h.ready();
    assert.equal(h.calls.length, 1);
    assert.equal(h.calls[0].settings.method, 'GET');
    assert.equal(h.calls[0].settings.headers['X-Bar-Request-Token'], tokenA);
    assert.equal(h.calls[0].url.includes(tokenA), false);
    const retry = h.nodes().find(node => node.tagName === 'FORM');
    assert.equal(retry.action, record().url);
    assert.equal(retry.children.find(node => node.name === 'token').value, tokenA);
    assert.equal(retry.children.find(node => node.name === 'csrf_token').value, 'fresh-csrf');
    assert.equal(h.submit(retry).defaultPrevented, false);
    assert.equal(h.calls.length, 1);
});

test('a second confirmation cannot replace an unresolved financial request', async () => {
    const h = harness({pending: record(), offline: true}); await h.ready();
    assert.equal(h.submit(h.form()).defaultPrevented, true);
    assert.equal(JSON.parse(h.values.get(key)).token, tokenA);
});

test('completed result blocks a newly generated back-navigation form until the user reviews it', async () => {
    const h = harness({pending: record(), response: {state: 'completed', result_url: '/bar/receipt.php?id=9'}}); await h.ready();
    assert.equal(h.values.has(key), true);
    assert.equal(h.submit(h.form()).defaultPrevented, true);
    assert.equal(h.nodes().find(node => node.tagName === 'A').href, 'http://localhost/bar/receipt.php?id=9');
    const reviewed = h.nodes().find(node => node.textContent === 'Pokračovat novou operací');
    reviewed.events.click();
    assert.equal(h.submit(h.form()).defaultPrevented, false);
    assert.equal(JSON.parse(h.values.get(key)).token, tokenB);
});

test('normal redirect to the saved result clears the pending form without another confirmation', async () => {
    const h = harness({pending: record(), url: 'http://localhost/bar/cashbox.php?id=9', response: {state: 'completed', result_url: '/bar/cashbox.php?id=9'}});
    await h.ready();
    assert.equal(h.values.has(key), false);
    assert.equal(h.mount.children.length, 0);
    assert.equal(h.submit(h.form()).defaultPrevented, false);
});

test('reloading before reviewing a completed result keeps the duplicate-confirmation guard', async () => {
    const response = {state: 'completed', result_url: '/bar/receipt.php?id=9'};
    const first = harness({pending: record(), response}); await first.ready();
    const reloaded = harness({storage: Object.fromEntries(first.values), response}); await reloaded.ready();
    assert.equal(reloaded.values.has(key), true);
    assert.equal(reloaded.submit(reloaded.form()).defaultPrevented, true);
});

test('explicit staff logout erases this tab financial draft without blocking logout', async () => {
    const h = harness({pending: record()}); await h.ready();
    assert.equal(h.submit(h.form(tokenB, 'http://localhost/logout.php')).defaultPrevented, false);
    assert.equal(h.values.has(key), false);
});

test('definitive rejection plus absent server result permits correcting the form', async () => {
    const h = harness({pending: record(), rejected: tokenA}); await h.ready();
    assert.equal(h.values.has(key), false);
    assert.equal(h.submit(h.form()).defaultPrevented, false);
});

test('another actor or revoked session version cannot inherit pending financial inputs', async () => {
    const h = harness({storage: {'kis.bar.pending:v1:8:1': JSON.stringify(record()), 'unrelated-module': 'preserved'}}); await h.ready();
    assert.equal(h.values.has('kis.bar.pending:v1:8:1'), false);
    assert.equal(h.values.get('unrelated-module'), 'preserved');
    assert.equal(h.calls.length, 0);
});

test('storage failure prevents an unprotected confirmation', async () => {
    const h = harness({storageFails: true}); await h.ready();
    assert.equal(h.submit(h.form()).defaultPrevented, true);
    assert.equal(h.calls.length, 0);
});

test('tampered destination cannot send a recovery form to another application or origin', async () => {
    for (const url of ['https://example.invalid/receive', 'http://localhost/bar/../sprava_treneru.php']) {
        const h = harness({pending: record(url)}); await h.ready();
        assert.equal(h.nodes().some(node => node.tagName === 'FORM'), false);
        assert.equal(h.calls.length, 0);
        assert.equal(h.submit(h.form()).defaultPrevented, true);
    }
});

test('a deployment below a path prefix keeps status checks and result links inside that module', async () => {
    const h = harness({pending: record('http://localhost/kis/bar/cashbox.php'), prefix: '/kis/bar/', url: 'http://localhost/kis/bar/checkout.php'});
    await h.ready();
    assert.equal(h.calls[0].url, '/kis/bar/request-status.php');
    assert.equal(h.nodes().find(node => node.tagName === 'FORM').action, 'http://localhost/kis/bar/cashbox.php');
});

test('binary invoice attachments are not copied into browser storage', async () => {
    const h = harness(); await h.ready();
    const form = h.form(); form.fields.push(['pdf', {size: 100}]);
    assert.equal(h.submit(form).defaultPrevented, false);
    assert.equal(h.values.has(key), false);
});
