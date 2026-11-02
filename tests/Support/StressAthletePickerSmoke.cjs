'use strict';
const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../assets/js/stress-athlete-picker.js'), 'utf8');

function environment() {
    let timerId = 0;
    const timers = new Map(), pending = [];
    class Element {
        constructor() { this.listeners = {}; this.children = []; this.value = ''; this.attrs = {}; this.hidden = false; this.validity = ''; this.classList = {toggle() {}}; }
        addEventListener(type, fn) { (this.listeners[type] ??= []).push(fn); }
        fire(type, props = {}) {
            const event = {target: this, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, ...props};
            for (const fn of this.listeners[type] ?? []) fn(event);
            return event;
        }
        dispatchEvent(event) { this.fire(event.type); }
        replaceChildren(...children) { this.children = children; }
        contains(target) { return target === this || this.children.includes(target); }
        setAttribute(name, value) { this.attrs[name] = value; }
        removeAttribute(name) { delete this.attrs[name]; }
        setCustomValidity(value) { this.validity = value; }
        reportValidity() { this.reported = true; }
        focus() { this.fire('focus'); }
        scrollIntoView() {}
    }
    const picker = new Element(), input = new Element(), hidden = new Element(), list = new Element(), status = new Element(), form = new Element();
    picker.children = [input, hidden, list, status]; input.form = form;
    const elements = {'stress-athlete-picker': picker, 'stress-athlete-search': input, 'stress-athlete-id': hidden, 'stress-athlete-results': list, 'stress-athlete-status': status};
    const document = {getElementById: id => elements[id], createElement: () => new Element(), addEventListener() {}};
    vm.runInNewContext(source, {document, URLSearchParams, AbortController, Event,
        setTimeout: fn => { timers.set(++timerId, fn); return timerId; }, clearTimeout: id => timers.delete(id),
        fetch: (url, options) => new Promise(resolve => pending.push({url, options, resolve}))});
    return {input, hidden, list, status, form, picker, pending,
        flush() { const callbacks = Array.from(timers.values()); timers.clear(); callbacks.forEach(fn => fn()); },
        async respond(index, rows, statusCode = 200) { pending[index].resolve({ok: statusCode === 200, status: statusCode, json: async () => rows}); await new Promise(resolve => setImmediate(resolve)); }};
}
const person = {id: 123, label: 'TEST Anna · #123'};

test('initial browse, keyboard selection and form identity remain consistent', async () => {
    const e = environment(); e.input.focus(); e.flush();
    assert.match(e.pending[0].url, /browse=1/);
    await e.respond(0, [person]);
    assert.equal(e.list.children.length, 1);
    assert.equal(e.input.fire('keydown', {key: 'ArrowDown'}).defaultPrevented, true);
    assert.equal(e.input.fire('keydown', {key: 'Enter'}).defaultPrevented, true);
    assert.equal(e.hidden.value, '123'); assert.equal(e.input.value, person.label);
    assert.equal(e.form.fire('submit').defaultPrevented, false);
    e.input.value = 'Other'; e.input.fire('input');
    assert.equal(e.hidden.value, ''); assert.equal(e.form.fire('submit').defaultPrevented, true);
    assert.ok(e.input.validity);
});

test('mouse selection does not submit the form and stores the clicked ID', async () => {
    const e = environment(); e.input.focus(); e.flush(); await e.respond(0, [person]);
    const option = e.list.children[0];
    assert.equal(option.type, 'button'); assert.equal(option.fire('mousedown').defaultPrevented, true);
    option.fire('click'); assert.equal(e.hidden.value, '123'); assert.equal(e.list.hidden, true);
});

test('late responses cannot restore stale suggestions or select a different athlete', async () => {
    const e = environment(); e.input.value = 'Anna'; e.input.fire('input'); e.flush();
    e.input.value = 'Petr'; e.input.fire('input'); e.flush();
    await e.respond(1, [{id: 456, label: 'TEST Petr'}]);
    await e.respond(0, [person]);
    e.input.fire('keydown', {key: 'ArrowDown'}); e.input.fire('keydown', {key: 'Enter'});
    assert.equal(e.hidden.value, '456');
    e.input.value = 'New'; e.input.fire('input'); e.flush();
    e.picker.fire('focusout', {relatedTarget: null});
    await e.respond(2, [person]); assert.equal(e.list.hidden, true);
});

test('escape cancels pending search, empty results and session failure are visible', async () => {
    const e = environment(); e.input.focus(); e.input.fire('keydown', {key: 'Escape'}); e.flush();
    assert.equal(e.pending.length, 0);
    e.input.focus(); e.flush(); await e.respond(0, []);
    assert.match(e.status.textContent, /Žádný sportovec/);
    e.input.value = 'Anna'; e.input.fire('input'); e.flush(); await e.respond(1, {}, 403);
    assert.match(e.status.textContent, /Přihlášení nebo pracovní pozice/);
    assert.equal(e.hidden.value, '');
});
