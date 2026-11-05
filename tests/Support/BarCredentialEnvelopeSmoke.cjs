const test = require('node:test');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const {seal, open, publicKey} = require('./BarCredentialEnvelope.cjs');
const pair = crypto.generateKeyPairSync('rsa', {modulusLength:4096,
    publicKeyEncoding:{type:'spki',format:'pem'}, privateKeyEncoding:{type:'pkcs8',format:'pem'}});
const run = '12345678901';
const fixture = {kind:'bar-lucka-transfer-v1', run_id:run, source_id:77, login:'lucka', email:'info1@kovopraha.cz', password:'a'.repeat(32)};
test('only the matching private key can read the one-account delivery', () => {
    const result = seal(fixture, pair.publicKey, run);
    assert.equal(result.bytes.length, 512);
    assert.equal(result.public_key_sha256.length, 64);
    assert.equal(result.bytes.includes(Buffer.from(fixture.password)), false);
    assert.deepEqual(open(result.bytes, pair.privateKey, run), fixture);
});
test('account and preparation run cannot be substituted', () => {
    assert.throws(() => seal({...fixture,login:'another'}, pair.publicKey, run));
    assert.throws(() => seal({...fixture,email:'another@example.invalid'}, pair.publicKey, run));
    const result = seal(fixture, pair.publicKey, run);
    assert.throws(() => open(result.bytes, pair.privateKey, '99999999999'));
});
test('private keys and weak keys are rejected as recipient input', () => {
    assert.throws(() => publicKey(pair.privateKey));
    const weak = crypto.generateKeyPairSync('rsa', {modulusLength:2048,publicKeyEncoding:{type:'spki',format:'pem'},privateKeyEncoding:{type:'pkcs8',format:'pem'}});
    assert.throws(() => publicKey(weak.publicKey));
    const result = seal(fixture,pair.publicKey,run);
    assert.throws(() => open(result.bytes,weak.privateKey,run));
});
