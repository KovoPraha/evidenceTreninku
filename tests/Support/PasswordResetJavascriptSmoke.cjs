'use strict';
const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../booking/nove_heslo.php'), 'utf8');
// Execute the page's actual initial-fragment branch, without server-side template tags.
const start = source.indexOf('const fragment =');
const script = source.slice(start, source.indexOf('document.querySelectorAll', start))
    .replace(/<\?php[\s\S]*?\?>/g, '');

function run(hash) {
    const events = [];
    const field = {value: ''};
    const status = {textContent: ''};
    const form = {submit() { events.push(['post', field.value]); }};
    const context = {
        URLSearchParams,
        window: {location: {hash, pathname: '/booking/nove_heslo.php', search: '?pro=trener'}},
        history: {replaceState(_state, _title, url) { events.push(['clean-url', url]); }},
        document: {getElementById(id) {
            const element = {'password-reset-token': field, 'password-reset-check-form': form,
                'password-reset-check-status': status}[id];
            assert.ok(element, 'preflight must never access a password field');
            return element;
        }},
    };
    vm.runInNewContext(script, context);
    return {events, field, status};
}

test('fragment is removed before a token-only preflight POST; trainer mode survives', () => {
    const token = 'a'.repeat(64);
    const result = run('#token=' + token);
    assert.deepEqual(result.events, [['clean-url', '/booking/nove_heslo.php?pro=trener'], ['post', token]]);
});

test('missing or empty fragment rejects the link without a password form submission', () => {
    for (const fragment of ['', '#token=', '#other=value']) {
        const result = run(fragment);
        assert.equal(result.events.filter(event => event[0] === 'post').length, 0);
        assert.equal(result.status.textContent, 'Odkaz není platný nebo už byl použit.');
    }
});
