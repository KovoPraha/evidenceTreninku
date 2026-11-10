const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

test('original photo screen renders three distinct cards and rotates without immediate repetition', () => {
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
  const first = container.children.map(el => el.dataset.produktId);
  assert.equal(first.length, 3);
  assert.equal(new Set(first).size, 3);
  queue.find(entry => entry.ms === 10000).fn();
  queue.find(entry => entry.ms === 720).fn();
  const second = container.children.map(el => el.dataset.produktId);
  assert.equal(second.length, 3);
  assert.ok(second.every(id => !first.includes(id)));
  assert.ok(queue.some(entry => entry.ms === 60000));
});
