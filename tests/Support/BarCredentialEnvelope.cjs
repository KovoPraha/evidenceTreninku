const crypto = require('node:crypto');
const fs = require('node:fs/promises');

function credential(value, expectedRun) {
    if (!value || value.kind !== 'bar-lucka-transfer-v1' || !/^[0-9]{8,20}$/.test(value.run_id)
        || value.run_id !== expectedRun || value.login !== 'lucka' || value.email !== 'info1@kovopraha.cz'
        || !Number.isSafeInteger(value.source_id) || value.source_id < 1
        || typeof value.password !== 'string' || !/^[a-zA-Z0-9_-]{32}$/.test(value.password)) {
        throw new Error('Invalid single-account credential delivery.');
    }
    return {kind: value.kind, run_id: value.run_id, source_id: value.source_id, login: value.login, email: value.email, password: value.password};
}
function publicKey(publicPem) {
    if (!publicPem.startsWith('-----BEGIN PUBLIC KEY-----') || publicPem.includes('PRIVATE KEY')) throw new Error('Public SPKI key required.');
    const key = crypto.createPublicKey(publicPem);
    if (key.asymmetricKeyType !== 'rsa' || key.asymmetricKeyDetails.modulusLength < 4096) throw new Error('RSA 4096 or stronger is required.');
    return key;
}
function fingerprint(key) {
    return crypto.createHash('sha256').update(key.export({type:'spki', format:'der'})).digest('hex');
}
function seal(value, publicPem, expectedRun) {
    const key = publicKey(publicPem);
    const bytes = crypto.publicEncrypt({key, oaepHash:'sha256'}, Buffer.from(JSON.stringify(credential(value, expectedRun))));
    return {bytes, public_key_sha256:fingerprint(key)};
}
function open(bytes, privatePem, expectedRun) {
    const plaintext = crypto.privateDecrypt({key:privatePem, oaepHash:'sha256'}, bytes);
    try { return credential(JSON.parse(plaintext.toString('utf8')), expectedRun); }
    finally { plaintext.fill(0); }
}
async function main() {
    const run = process.env.BAR_CREDENTIAL_RUN;
    if (process.argv.includes('--check-key')) {
        publicKey(Buffer.from(process.env.BAR_CREDENTIAL_PUBLIC_KEY_B64 || '', 'base64').toString('utf8'));
    } else if (process.argv.includes('--seal')) {
        const result = seal(JSON.parse(await fs.readFile(process.env.BAR_CREDENTIAL_PLAIN_FILE, 'utf8')),
            Buffer.from(process.env.BAR_CREDENTIAL_PUBLIC_KEY_B64, 'base64').toString('utf8'), run);
        await fs.writeFile(process.env.BAR_CREDENTIAL_ENVELOPE_FILE, result.bytes, {mode:0o600, flag:'wx'});
        const report = JSON.parse(await fs.readFile(process.env.BAR_CREDENTIAL_REPORT_FILE, 'utf8'));
        report.credential_delivery = {algorithm:'RSA-4096-OAEP-SHA256', public_key_sha256:result.public_key_sha256,
            envelope_sha256:crypto.createHash('sha256').update(result.bytes).digest('hex')};
        await fs.writeFile(process.env.BAR_CREDENTIAL_REPORT_FILE, JSON.stringify(report), {mode:0o600});
    } else if (process.argv.includes('--open')) {
        const value = open(await fs.readFile(process.env.BAR_CREDENTIAL_ENVELOPE_FILE), await fs.readFile(process.env.BAR_CREDENTIAL_PRIVATE_KEY_FILE), run);
        await fs.writeFile(process.env.BAR_CREDENTIAL_PLAIN_FILE, JSON.stringify(value), {mode:0o600, flag:'wx'});
    } else throw new Error('Unknown credential-envelope operation.');
}
if (require.main === module) main().catch(() => { console.error('BAR credential envelope operation failed.'); process.exitCode = 1; });
module.exports = {credential, publicKey, seal, open};
