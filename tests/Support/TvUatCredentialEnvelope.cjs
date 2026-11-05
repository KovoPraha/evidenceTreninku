const crypto = require('node:crypto');
const fs = require('node:fs/promises');
function fixture(value, expectedRun) {
    if (!value || !/^[0-9]{8,20}$/.test(value.run_id) || value.run_id !== expectedRun
        || value.login !== `TV-UAT-${value.run_id}` || !/^[a-f0-9]{48}Tv!$/.test(value.password)
        || !Number.isSafeInteger(value.actor_id) || value.actor_id < 1
        || !Number.isSafeInteger(value.expires_at) || value.expires_at <= Date.now() / 1000) {
        throw new Error('Invalid or expired dedicated TV fixture.');
    }
    return {run_id: value.run_id, login: value.login, password: value.password, actor_id: value.actor_id, expires_at: value.expires_at};
}
function seal(value, publicPem, expectedRun) {
    if (!publicPem.startsWith('-----BEGIN PUBLIC KEY-----')) throw new Error('A public SPKI key is required.');
    const key = crypto.createPublicKey(publicPem);
    if (key.asymmetricKeyType !== 'rsa' || key.asymmetricKeyDetails.modulusLength < 4096) throw new Error('RSA 4096 or stronger is required.');
    return crypto.publicEncrypt({key, oaepHash: 'sha256'}, Buffer.from(JSON.stringify(fixture(value, expectedRun))));
}
function open(bytes, privatePem, expectedRun) {
    const plaintext = crypto.privateDecrypt({key: privatePem, oaepHash: 'sha256'}, bytes);
    return fixture(JSON.parse(plaintext.toString('utf8')), expectedRun);
}
async function main() {
    const run = process.env.TV_UAT_EXPECTED_RUN_ID;
    if (process.argv.includes('--seal')) {
        const value = JSON.parse(await fs.readFile(process.env.TV_UAT_CREDENTIALS_FILE, 'utf8'));
        const publicPem = Buffer.from(process.env.TV_UAT_PUBLIC_KEY_B64, 'base64').toString('utf8');
        await fs.writeFile(process.env.TV_UAT_ENVELOPE_FILE, seal(value, publicPem, run));
    } else if (process.argv.includes('--open')) {
        const value = open(await fs.readFile(process.env.TV_UAT_ENVELOPE_FILE), await fs.readFile(process.env.TV_UAT_PRIVATE_KEY_FILE), run);
        await fs.writeFile(process.env.TV_UAT_CREDENTIALS_FILE, JSON.stringify(value), {mode: 0o600, flag: 'wx'});
    } else throw new Error('Unknown envelope operation.');
}
if (require.main === module) main().catch(() => { console.error('TV fixture envelope operation failed.'); process.exitCode = 1; });
module.exports = {seal, open};
