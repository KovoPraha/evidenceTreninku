'use strict';

const assert = require('node:assert/strict');

const origin = 'https://kis.kovopraha.cz';
const parentEmail = 'tester.karel@velocota.com';

function allowedRequest(method, rawUrl, body = '') {
  let url;
  try { url = new URL(rawUrl); } catch { return false; }
  if (url.origin !== origin) return false;
  if (method === 'GET') return true;
  if (method !== 'POST') return false;
  const action = new URLSearchParams(body).get('action');
  if (url.pathname === '/booking/prihlaseni.php') return action === null;
  if (url.pathname === '/booking/produkt.php') return action === 'add';
  if (url.pathname === '/booking/eshop.php') {
    return action === 'quantity' && new URLSearchParams(body).get('quantity') === '0';
  }
  return false;
}

if (process.argv.includes('--self-test')) {
  assert.equal(allowedRequest('POST', `${origin}/booking/prihlaseni.php`, 'email=x&heslo=y'), true);
  assert.equal(allowedRequest('POST', `${origin}/booking/produkt.php?id=266`, 'action=add'), true);
  assert.equal(allowedRequest('POST', `${origin}/booking/eshop.php`, 'action=quantity&quantity=0'), true);
  assert.equal(allowedRequest('POST', `${origin}/booking/eshop.php`, 'action=quantity&quantity=1'), false);
  assert.equal(allowedRequest('POST', `${origin}/booking/eshop.php`, 'action=checkout'), false);
  assert.equal(allowedRequest('POST', `${origin}/booking/objednavka.php`, 'action=stripe_checkout'), false);
  assert.equal(allowedRequest('GET', 'https://example.com/collect'), false);
  console.log('UAT request guard: OK');
  process.exit(0);
}

async function main() {
  const { chromium } = require('playwright');
  const password = process.env.KIS_UAT_TEST_PASSWORD || '';
  const productId = Number(process.env.KIS_UAT_PRODUCT_ID || '');
  assert(password.length > 0 && Number.isSafeInteger(productId) && productId > 0);
  let stage = 'start';
  let browser;
  let page;
  let attemptedAdd = false;
  let variantId = '';
  let cleanupOk = true;
  const result = {
    parent_registration_form: false,
    login: false,
    linked_child: false,
    child_registration_form: false,
    program_detail: false,
    cart_checkout_preview: false,
    roster_page: false,
    roster_rows: 0,
    order_submitted: false,
    cart_cleaned: false,
  };

  try {
    browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
      locale: 'cs-CZ',
      timezoneId: 'Europe/Prague',
      serviceWorkers: 'block',
      acceptDownloads: false,
    });
    await context.route('**/*', route => {
      const request = route.request();
      if (allowedRequest(request.method(), request.url(), request.postData() || '')) {
        return route.continue();
      }
      return route.abort();
    });
    page = await context.newPage();
    page.setDefaultTimeout(15000);

    stage = 'parent_registration_form';
    await page.goto(`${origin}/booking/registrace.php`, { waitUntil: 'domcontentloaded' });
    assert.equal(await page.locator('#registration-purpose-family').count(), 1);
    assert.equal(await page.locator('#registration-email').count(), 1);
    assert.equal(await page.getByRole('button', { name: 'Zaregistrovat se' }).count(), 1);
    result.parent_registration_form = true;

    stage = 'login';
    await page.goto(`${origin}/booking/prihlaseni.php`, { waitUntil: 'domcontentloaded' });
    await page.locator('#login-email').fill(parentEmail);
    await page.locator('#login-password').fill(password);
    await Promise.all([
      page.waitForURL(url => !url.pathname.endsWith('/prihlaseni.php')),
      page.getByRole('button', { name: 'Přihlásit se' }).click(),
    ]);
    result.login = true;

    stage = 'child';
    await page.goto(`${origin}/booking/moje_osoby.php`, { waitUntil: 'domcontentloaded' });
    const approved = page.locator('section.card').filter({ hasText: 'Schválené osoby' });
    assert.equal(await approved.count(), 1);
    assert(await approved.getByText('Tester Ema', { exact: false }).count() > 0);
    result.linked_child = true;

    stage = 'child_registration_form';
    assert.equal(await page.getByRole('link', { name: 'Registrovat nového sportovce' }).count(), 1);
    await page.goto(`${origin}/booking/registrace_sportovce.php`, { waitUntil: 'domcontentloaded' });
    assert.equal(await page.locator('#athlete-rc').count(), 1);
    assert.equal(await page.locator('#athlete-registration-acknowledged:required').count(), 1);
    assert.equal(await page.getByText('Novou žádost nyní nelze odeslat.', { exact: false }).count(), 0);
    result.child_registration_form = true;

    stage = 'roster';
    await page.goto(`${origin}/booking/moje_programy.php`, { waitUntil: 'domcontentloaded' });
    assert(await page.getByRole('heading', { name: 'Moje kroužky' }).count() === 1);
    const roster = page.locator('section.card').filter({ hasText: 'Účasti a zařazení do soupisek' });
    assert.equal(await roster.count(), 1);
    result.roster_rows = await roster.locator('tbody tr').count();
    if (await roster.getByText('Zatím není aktivována žádná účast.', { exact: false }).count()) {
      result.roster_rows = 0;
    }
    result.roster_page = true;

    stage = 'cart_preflight';
    await page.goto(`${origin}/booking/eshop.php#kosik`, { waitUntil: 'domcontentloaded' });
    assert(await page.locator('#kosik').getByText('Košík je prázdný.').count() === 1);

    stage = 'program_detail';
    await page.goto(`${origin}/booking/produkt.php?id=${productId}`, { waitUntil: 'domcontentloaded' });
    assert.equal(await page.locator('main h1').count(), 1);
    assert(await page.locator('main').getByText('kroužek', { exact: true }).count() > 0);
    if (productId === 266) {
      assert.match(await page.locator('main h1').innerText(), /Pumptrack/i);
      assert(await page.locator('main').getByText('6 500,00 Kč', { exact: false }).count() > 0);
      assert(await page.locator('main').getByText('11 500,00 Kč', { exact: false }).count() > 0);
    }
    const forms = page.locator('#shop-variant-list form').filter({ has: page.locator('input[name="action"][value="add"]') });
    let chosen;
    for (let i = 0; i < await forms.count(); i++) {
      const form = forms.nth(i);
      if (await form.locator('select[name="sportovec_id"]').count() === 0) continue;
      const addButton = form.getByRole('button', { name: 'Přihlásit účastníka' });
      if (await addButton.count() === 1 && await addButton.isEnabled()) {
        chosen = form;
        break;
      }
    }
    assert(chosen, 'No saleable program option');
    variantId = await chosen.locator('input[name="variant_id"]').inputValue();
    const childOption = chosen.locator('select[name="sportovec_id"] option').filter({ hasText: 'Tester Ema' });
    assert.equal(await childOption.count(), 1);
    await chosen.locator('select[name="sportovec_id"]').selectOption(await childOption.getAttribute('value'));
    result.program_detail = true;

    stage = 'cart_preview';
    attemptedAdd = true;
    await Promise.all([
      page.waitForURL(url => url.pathname.endsWith('/eshop.php')),
      chosen.getByRole('button', { name: 'Přihlásit účastníka' }).click(),
    ]);
    assert(await page.locator('#kosik').getByText('Dítě / účastník').count() > 0);
    const checkout = page.locator('#shop-checkout-form');
    assert.equal(await checkout.count(), 1);
    const termsCheckbox = checkout.locator('input[name^="program_terms_accept"]:required');
    assert.equal(await termsCheckbox.count(), 1);
    await termsCheckbox.check();
    const financeSelect = checkout.locator('#finance-sportovec');
    if (await financeSelect.count() === 1) {
      const financeChild = financeSelect.locator('option').filter({ hasText: 'Ema Tester' });
      assert.equal(await financeChild.count(), 1);
      await financeSelect.selectOption(await financeChild.getAttribute('value'));
    }
    assert(await checkout.evaluate(form => form.checkValidity()));
    assert(await checkout.getByRole('button', { name: 'Vytvořit objednávku' }).isEnabled());
    result.cart_checkout_preview = true;
  } catch {
    result.failure_stage = stage;
  } finally {
    if (page && attemptedAdd && variantId) {
      try {
        await page.goto(`${origin}/booking/eshop.php#kosik`, { waitUntil: 'domcontentloaded' });
        const remove = page.locator('#kosik form').filter({ has: page.locator('input[name="action"][value="quantity"]') })
          .filter({ has: page.locator(`input[name="variant_id"][value="${variantId}"]`) });
        if (await remove.count() === 1) {
          await remove.getByRole('button', { name: 'Odebrat' }).click();
          await page.waitForURL(url => url.pathname.endsWith('/eshop.php'));
        }
        result.cart_cleaned = await page.locator('#kosik').getByText('Košík je prázdný.').count() === 1;
        cleanupOk = result.cart_cleaned;
      } catch {
        cleanupOk = false;
      }
    } else if (!attemptedAdd) {
      result.cart_cleaned = true;
    }
    if (browser) await browser.close();
  }
  if (!cleanupOk) result.failure_stage = 'cart_cleanup';
  console.log(`UAT_RESULT ${JSON.stringify(result)}`);
  if (result.failure_stage) process.exitCode = 1;
}

main().catch(() => {
  console.log('UAT_RESULT {"failure_stage":"unexpected","order_submitted":false}');
  process.exitCode = 1;
});
