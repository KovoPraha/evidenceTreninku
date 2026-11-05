/* Manual production drill. Credentials and cookies never enter evidence artifacts. */
const fs = require('node:fs/promises');
const path = require('node:path');
const crypto = require('node:crypto');
const zlib = require('node:zlib');
const { chromium } = require(process.env.TV_UAT_PLAYWRIGHT_PATH || 'playwright');
const localTest = process.env.TV_UAT_LOCAL_TEST === '1';
const BASE = localTest ? 'http://127.0.0.1:18094' : 'https://kis.kovopraha.cz';
const launchOptions = localTest ? {headless: true, channel: 'chrome'} : {headless: true};
const privateDir = process.env.TV_UAT_PRIVATE_DIR;
const output = process.env.TV_UAT_OUTPUT;
let phase = 'start';
const checks = [];
function check(ok, label) {
    if (!ok) { const error = new Error(label); error.safeCheck = label; throw error; }
    checks.push(label);
}
function crc32(bytes) {
    let crc = -1;
    for (const byte of bytes) {
        crc ^= byte;
        for (let i = 0; i < 8; i++) crc = (crc >>> 1) ^ ((crc & 1) ? 0xedb88320 : 0);
    }
    return (crc ^ -1) >>> 0;
}
function chunk(type, value) {
    const kind = Buffer.from(type);
    const length = Buffer.alloc(4); length.writeUInt32BE(value.length);
    const crc = Buffer.alloc(4); crc.writeUInt32BE(crc32(Buffer.concat([kind, value])));
    return Buffer.concat([length, kind, value, crc]);
}
function png(red, green, blue) {
    const width = 1600, height = 900;
    const header = Buffer.alloc(13);
    header.writeUInt32BE(width, 0); header.writeUInt32BE(height, 4); header[8] = 8; header[9] = 2;
    const rows = Buffer.alloc(height * (width * 3 + 1));
    for (let y = 0; y < height; y++) for (let x = 0; x < width; x++) {
        const offset = y * (width * 3 + 1) + 1 + x * 3;
        rows[offset] = red; rows[offset + 1] = green; rows[offset + 2] = blue;
    }
    return Buffer.concat([Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]), chunk('IHDR', header), chunk('IDAT', zlib.deflateSync(rows)), chunk('IEND', Buffer.alloc(0))]);
}
const hash = bytes => crypto.createHash('sha256').update(bytes).digest('hex');
async function jsonWrite(name, value) { await fs.writeFile(path.join(output, name), JSON.stringify(value, null, 2) + '\n'); }
async function postCleanup() {
    const cleanup = JSON.parse(await fs.readFile(path.join(output, 'cleanup.json'), 'utf8'));
    const previous = JSON.parse(await fs.readFile(path.join(output, 'browser.json'), 'utf8'));
    const browser = await chromium.launch(launchOptions);
    try {
        const anonymous = await browser.newContext();
        check(cleanup.ok && cleanup.actor_deactivated && cleanup.positions_revoked, 'server cleanup completed');
        for (const id of cleanup.program_ids) check((await anonymous.request.get(`${BASE}/tv/${id}`)).status() === 404, 'archived programme is not public');
        for (const asset of cleanup.images) {
            check(asset.valid_private_jpeg && asset.width === 1600 && asset.height === 900, 'stored private JPEG verified by server');
            check((await anonymous.request.get(`${BASE}/tv_image.php?id=${asset.id}`)).status() === 404, 'archived image is not public');
        }
        const statePath = path.join(privateDir, 'browser-auth.json');
        const authenticated = await browser.newContext({storageState: statePath});
        const response = await authenticated.request.get(`${BASE}/tv_programs_admin.php`, {maxRedirects: 0});
        check([302, 303].includes(response.status()), 'old authenticated session revoked');
        check(cleanup.other_programs_unchanged, 'other programmes unchanged');
        check(previous.ok, 'full browser flow passed');
        check(cleanup.images.length === 2, 'both uploaded images persisted');
        await jsonWrite('post-cleanup.json', {ok: true, checks, run_id: cleanup.run_id, program_ids: cleanup.program_ids});
    } finally { await browser.close(); }
}
async function main() {
    check(Boolean(privateDir && output), 'private working directory configured');
    await fs.mkdir(output, {recursive: true});
    if (process.argv.includes('--post-cleanup')) return postCleanup();
    const settings = JSON.parse(await fs.readFile(path.join(privateDir, 'credentials.json'), 'utf8'));
    check(/^[0-9]{8,20}$/.test(settings.run_id) && settings.expires_at * 1000 > Date.now(), 'fixture identity current');
    check(!localTest || settings.run_id.startsWith('999'), 'local mode requires an explicit local fixture');
    const name = `TEST TV ${settings.run_id}`;
    const firstText = `TEST TV – první zveřejnění ${settings.run_id}`;
    const secondText = `TEST TV – druhý snímek ${settings.run_id}`;
    const updatedText = `TEST TV – AKTUALIZOVÁNO ${settings.run_id}`;
    const firstImage = path.join(privateDir, 'first.png'), secondImage = path.join(privateDir, 'second.png');
    await fs.writeFile(firstImage, png(18, 52, 86)); await fs.writeFile(secondImage, png(95, 30, 60));
    const browser = await chromium.launch(launchOptions);
    const context = await browser.newContext({viewport: {width: 1600, height: 1000}});
    const anonymous = await browser.newContext({viewport: {width: 1920, height: 1080}});
    let programId = null;
    const assets = [];
    const revisions = [];
    let tvNavigations = 0;
    const page = await context.newPage();
    const operation = async action => {
        const pending = page.waitForResponse(response => response.url() === `${BASE}/tv_programs_admin.php` && response.request().method() === 'POST');
        await action();
        const response = await pending;
        const value = await response.json();
        if (response.status() !== 200 || !value.ok) {
            const error = new Error('Administration operation refused');
            error.safeCheck = 'administration operation refused';
            error.safeResponse = {status: response.status(), message: value.message};
            throw error;
        }
        check(true, 'administration operation accepted');
        return value.result;
    };
    const save = () => operation(() => page.getByRole('button', {name: 'Uložit koncept', exact: true}).click());
    const publish = async () => {
        page.once('dialog', dialog => dialog.accept());
        await Promise.all([page.waitForEvent('load'), page.getByRole('button', {name: 'Zveřejnit změny', exact: true}).click()]);
        await page.locator('#tv-editor-boot').waitFor({state: 'attached'});
        const published = await publicData();
        check(published.status === 200 && published.value.revision > 0, 'UI publication created public revision');
        revisions.push(published.value.revision);
        return published.value;
    };
    const publicData = async () => {
        const response = await anonymous.request.get(`${BASE}/tv.php?id=${programId}&format=json`);
        return {status: response.status(), value: await response.json()};
    };
    const boot = async () => JSON.parse(await page.locator('#tv-editor-boot').textContent());
    try {
        phase = 'normal sign-in';
        await page.goto(`${BASE}/login.php`);
        await page.getByRole('textbox', {name: 'Jméno nebo e-mail', exact: true}).fill(settings.login);
        await page.getByRole('textbox', {name: 'Heslo', exact: true}).fill(settings.password);
        await Promise.all([page.waitForURL('**/pracovni_pozice.php'), page.getByRole('button', {name: 'Přihlásit se', exact: true}).click()]);
        check(await page.getByRole('heading', {name: /Obsluha TV/}).isVisible(), 'TV-only user signs in normally');
        check((await context.request.get(`${BASE}/sprava_sportovcu.php`)).status() === 403, 'member administration denied to TV operator');
        phase = 'create separate programme';
        await page.goto(`${BASE}/tv_programs_admin.php`);
        await page.getByRole('textbox', {name: 'Název programu', exact: true}).fill(name);
        await Promise.all([page.waitForURL('**/tv_programs_admin.php?id=*'), page.getByRole('button', {name: 'Vytvořit program', exact: true}).click()]);
        programId = Number(new URL(page.url()).searchParams.get('id'));
        check(Number.isSafeInteger(programId) && programId > 0, 'test programme created');
        phase = 'upload and save text layout';
        await page.getByRole('button', {name: '+ Přidat snímek', exact: true}).click();
        await page.getByRole('textbox', {name: 'Název snímku', exact: true}).fill('První snímek');
        await page.getByRole('spinbutton', {name: 'Délka zobrazení (sekundy)', exact: true}).fill('2');
        assets.push((await operation(() => page.getByLabel('Nahrát / nahradit obrázek', {exact: true}).setInputFiles(firstImage))).id);
        await page.getByRole('button', {name: '+ Přidat text', exact: true}).click();
        await page.getByRole('textbox', {name: 'Text', exact: true}).fill(firstText);
        await page.getByRole('spinbutton', {name: 'Velikost písma', exact: true}).fill('72');
        await page.getByRole('spinbutton', {name: 'Pozice X', exact: true}).fill('80');
        await page.getByRole('spinbutton', {name: 'Pozice Y', exact: true}).fill('120');
        await page.getByRole('spinbutton', {name: 'Šířka bloku', exact: true}).fill('1640');
        await page.getByRole('button', {name: '+ Přidat text', exact: true}).click();
        await page.getByRole('textbox', {name: 'Text', exact: true}).fill('Samostatný testovací program – produkční ověření');
        await page.getByRole('spinbutton', {name: 'Velikost písma', exact: true}).fill('40');
        await page.getByRole('spinbutton', {name: 'Pozice Y', exact: true}).fill('300');
        await save();
        await page.reload();
        let draft = await boot();
        const initialBlock = draft.document.slides[0].blocks[0];
        check(initialBlock.text === firstText && initialBlock.font_size === 72 && initialBlock.x === 80 && initialBlock.y === 120, 'text and layout survive reload');
        check(draft.document.slides[0].blocks.length === 2 && draft.document.slides[0].asset_id === assets[0], 'image and both blocks persist');
        check((await publicData()).status === 404, 'draft is not public');
        check((await anonymous.request.get(`${BASE}/tv_image.php?id=${assets[0]}`)).status() === 404, 'draft image is private');
        const preview = await context.request.get(`${BASE}/tv_image.php?preview=1&id=${assets[0]}`);
        check(preview.status() === 200 && preview.headers()['content-type'].includes('image/jpeg'), 'authenticated JPEG preview loads');
        phase = 'carousel order and disabled slide';
        await page.getByRole('button', {name: '+ Přidat snímek', exact: true}).click();
        await page.getByRole('textbox', {name: 'Název snímku', exact: true}).fill('Druhý snímek');
        await page.getByRole('spinbutton', {name: 'Délka zobrazení (sekundy)', exact: true}).fill('3');
        await page.getByRole('button', {name: '+ Přidat text', exact: true}).click();
        await page.getByRole('textbox', {name: 'Text', exact: true}).fill(secondText);
        await page.getByRole('button', {name: '+ Přidat snímek', exact: true}).click();
        await page.getByRole('textbox', {name: 'Název snímku', exact: true}).fill('Neveřejný koncept');
        await page.getByRole('checkbox', {name: 'Snímek zařadit do vysílání', exact: true}).uncheck();
        await page.getByRole('button', {name: '+ Přidat text', exact: true}).click();
        await page.getByRole('textbox', {name: 'Text', exact: true}).fill('NEZVEŘEJNĚNÝ KONCEPT');
        await page.getByRole('button', {name: 'Posunout snímek 2 nahoru', exact: true}).click();
        await save();
        phase = 'first publication';
        await publish();
        const initial = await publicData();
        check(initial.status === 200 && initial.value.document.slides.length === 2, 'two enabled slides published');
        check(initial.value.document.slides[0].blocks[0].text === secondText, 'published order matches edited order');
        check(!JSON.stringify(initial.value).includes('NEZVEŘEJNĚNÝ KONCEPT'), 'disabled content excluded');
        const initialJpeg = await anonymous.request.get(`${BASE}/tv_image.php?id=${assets[0]}`);
        check(initialJpeg.status() === 200 && hash(await initialJpeg.body()) === hash(await preview.body()), 'public image equals stored preview');
        const tv = await anonymous.newPage();
        check((await tv.goto(`${BASE}/tv/${programId}`)).status() === 200, 'public numbered route loads');
        tv.on('framenavigated', frame => { if (frame === tv.mainFrame()) tvNavigations++; });
        await tv.getByText(secondText, {exact: true}).waitFor();
        await tv.getByText(firstText, {exact: true}).waitFor({timeout: 12000});
        await tv.screenshot({path: path.join(output, 'published-first.png')});
        await tv.getByText(secondText, {exact: true}).waitFor({timeout: 12000});
        check(true, 'carousel advances and loops');
        phase = 'replace image and edit text';
        await page.getByRole('button', {name: 'Vybrat snímek 2', exact: true}).click();
        await page.getByRole('combobox', {name: 'Vybraný blok', exact: true}).selectOption('0');
        await page.getByRole('textbox', {name: 'Text', exact: true}).fill(updatedText);
        await page.getByRole('spinbutton', {name: 'Velikost písma', exact: true}).fill('96');
        await page.getByRole('spinbutton', {name: 'Pozice X', exact: true}).fill('180');
        await page.getByRole('spinbutton', {name: 'Pozice Y', exact: true}).fill('160');
        assets.push((await operation(() => page.getByLabel('Nahrát / nahradit obrázek', {exact: true}).setInputFiles(secondImage))).id);
        await save();
        await page.reload();
        draft = await boot();
        const edited = draft.document.slides[1];
        check(edited.asset_id === assets[1] && edited.blocks[0].text === updatedText && edited.blocks[0].font_size === 96
            && edited.blocks[0].x === 180 && edited.blocks[0].y === 160, 'replacement image and edited text persist');
        check((await publicData()).value.document.slides[1].blocks[0].text === firstText, 'unpublished edits do not change broadcast');
        check((await anonymous.request.get(`${BASE}/tv_image.php?id=${assets[1]}`)).status() === 404, 'replacement stays private before publication');
        await page.screenshot({path: path.join(output, 'editor-persisted.png'), fullPage: true});
        phase = 'automatic live update';
        await publish();
        await tv.getByText(updatedText, {exact: true}).waitFor({timeout: 25000});
        check(tvNavigations === 0, 'existing player updated without reload');
        await tv.screenshot({path: path.join(output, 'published-updated.png')});
        const replacement = await anonymous.request.get(`${BASE}/tv_image.php?id=${assets[1]}`);
        check(replacement.status() === 200 && hash(await replacement.body()) !== hash(await initialJpeg.body()), 'public replacement image changed');
        check((await anonymous.request.get(`${BASE}/tv_image.php?id=${assets[0]}`)).status() === 404, 'old image no longer publicly referenced');
        phase = 'archive programme';
        await page.getByRole('link', {name: '← Všechny TV programy', exact: true}).click();
        const row = page.getByRole('row').filter({has: page.getByRole('link', {name, exact: true})});
        await row.getByRole('button', {name: 'Archivovat a zastavit vysílání', exact: true}).click();
        await row.getByText('Archivovaný', {exact: true}).waitFor();
        check((await publicData()).status === 404, 'archived broadcast returns 404');
        await tv.getByText('KOVO Praha', {exact: true}).waitFor({timeout: 25000});
        check(tvNavigations === 0, 'archived player stops without reload');
        await context.storageState({path: path.join(privateDir, 'browser-auth.json')});
        await fs.chmod(path.join(privateDir, 'browser-auth.json'), 0o600);
        await jsonWrite('browser.json', {ok: true, run_id: settings.run_id, program_id: programId, assets, revisions, tv_navigations: tvNavigations, checks});
    } catch (error) {
        await context.storageState({path: path.join(privateDir, 'browser-auth.json')});
        await fs.chmod(path.join(privateDir, 'browser-auth.json'), 0o600);
        await jsonWrite('browser.json', {ok: false, phase, error_type: error.name, failed_check: error.safeCheck,
            response: error.safeResponse, run_id: settings.run_id, program_id: programId, assets, revisions, checks});
        throw error;
    } finally { await browser.close(); }
}
main().catch(async error => {
    // Playwright call logs can contain submitted form values. Never print them.
    console.error(`TV UAT failed during ${phase} (${error.name}). See sanitized browser.json.`);
    process.exitCode = 1;
});
