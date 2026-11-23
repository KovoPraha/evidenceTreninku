const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const flush = () => new Promise(resolve => setImmediate(resolve));

test('photo screen renders three distinct cards and rotates without immediate repetition', async () => {
  const rows = Array.from({length: 9}, (_, i) => ({id: i + 1, nazev: `LOCALHOST ${i}`, cena: '35 Kč',
    kategorie: 'LOCALHOST', barva: i % 6, popis: '<safe text>', obrazek: null, vyprodano: i === 2}));
  const queue = [];
  const element = () => ({children: [], dataset: {}, classList: {add() {}, remove() {}},
    appendChild(child) { this.children.push(child); }, replaceChildren(...children) { this.children = children; }});
  const container = element();
  const document = {hidden: false, body: {dataset: {refreshSeconds: '60'}}, createElement: element,
    getElementById: id => id === 'bar-screen-products' ? {textContent: JSON.stringify(rows)} : container};
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../assets/bar-photos.js'), 'utf8'), {
    document, requestAnimationFrame: fn => fn(), setTimeout: (fn, ms) => { queue.push({fn, ms}); return queue.length; },
    clearTimeout() {}, location: {reload() {}}
  });
  await flush();
  const first = container.children.map(el => el.dataset.produktId);
  assert.equal(first.length, 3);
  assert.equal(new Set(first).size, 3);
  queue.find(entry => entry.ms === 10000).fn();
  await flush();
  queue.find(entry => entry.ms === 720).fn();
  await flush();
  const second = container.children.map(el => el.dataset.produktId);
  assert.equal(second.length, 3);
  assert.ok(second.every(id => !first.includes(id)));
  assert.ok(queue.some(entry => entry.ms === 60000));
});

test('photo screen retains previous cards until the next images load', async () => {
  const rows = Array.from({length: 6}, (_, i) => ({id: i + 1, nazev: `LOCALHOST ${i}`, cena: '35 Kč',
    kategorie: 'LOCALHOST', barva: 0, popis: '', obrazek: `image-${i}.jpg`, vyprodano: false}));
  const queue = [];
  const images = [];
  const element = tag => {
    const result = {children: [], dataset: {}, listeners: {}, classList: {add() {}, remove() {}},
      appendChild(child) { this.children.push(child); }, replaceChildren(...children) { this.children = children; },
      addEventListener(name, fn) { this.listeners[name] = fn; }, remove() {}, decode() { return Promise.resolve(); }};
    if (tag === 'img') { result.complete = false; result.naturalWidth = 0; images.push(result); }
    return result;
  };
  const container = element('section');
  const document = {hidden: false, body: {dataset: {refreshSeconds: '60'}}, createElement: element,
    getElementById: id => id === 'bar-screen-products' ? {textContent: JSON.stringify(rows)} : container};
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../assets/bar-photos.js'), 'utf8'), {
    document, requestAnimationFrame: fn => fn(), setTimeout: (fn, ms) => { const timer = {fn, ms}; queue.push(timer); return timer; },
    clearTimeout: timer => { if (timer) timer.cancelled = true; }, location: {reload() {}}
  });
  assert.equal(container.children.length, 0);
  const load = entries => entries.forEach(img => { img.complete = true; img.naturalWidth = 960; img.listeners.load(); });
  load(images.slice(0, 3));
  await flush();
  const first = container.children.map(el => el.dataset.produktId);
  assert.equal(first.length, 3);
  queue.find(entry => entry.ms === 10000).fn();
  await flush();
  assert.deepEqual(container.children.map(el => el.dataset.produktId), first);
  load(images.slice(3, 6));
  await flush();
  assert.deepEqual(container.children.map(el => el.dataset.produktId), first);
  queue.find(entry => entry.ms === 720).fn();
  await flush();
  const second = container.children.map(el => el.dataset.produktId);
  assert.equal(second.length, 3);
  assert.ok(second.every(id => !first.includes(id)));
  queue.filter(entry => entry.ms === 10000).at(-1).fn();
  await flush();
  load(images.slice(6, 8));
  const failed = images[8];
  failed.complete = true;
  failed.listeners.error();
  await flush();
  queue.filter(entry => entry.ms === 720).at(-1).fn();
  await flush();
  assert.ok(container.children.some(card => card.children[0].textContent === 'KOVO BAR'));
});
