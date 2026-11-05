const {test} = require('node:test');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const {seal, open} = require('./TvUatCredentialEnvelope.cjs');
const {publicKey, privateKey} = crypto.generateKeyPairSync('rsa', {modulusLength: 4096});
const pem = publicKey.export({type: 'spki', format: 'pem'});
const privatePem = privateKey.export({type: 'pkcs8', format: 'pem'});
const state = {run_id: '41999999999', login: 'TV-UAT-41999999999', password: 'a'.repeat(48) + 'Tv!', actor_id: 7,
    expires_at: Math.floor(Date.now() / 1000) + 3600, baseline_programs: {1: 'private internal fingerprint'}};
test('only the recipient can open the limited fixture fields', () => {
    const encrypted = seal(state, pem, state.run_id);
    assert.equal(encrypted.length, 512);
    assert.equal(encrypted.includes(Buffer.from(state.password)), false);
    const value = open(encrypted, privatePem, state.run_id);
    assert.equal(value.password, state.password);
    assert.equal('baseline_programs' in value, false);
});
test('expired, wrong-run and modified envelopes are refused', () => {
    assert.throws(() => seal({...state, expires_at: 1}, pem, state.run_id));
    assert.throws(() => seal(state, pem, '41999999998'));
    const encrypted = seal(state, pem, state.run_id);
    encrypted[0] ^= 1;
    assert.throws(() => open(encrypted, privatePem, state.run_id));
});
test('a private key can never be accepted as the public workflow input', () => {
    assert.throws(() => seal(state, privatePem, state.run_id));
});
