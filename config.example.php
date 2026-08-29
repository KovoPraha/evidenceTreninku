<?php
/**
 * config.example.php
 * Vzor konfigurace. Zkopírujte jako config.php a doplňte skutečné údaje.
 * Soubor config.php NENÍ verzován (je v .gitignore).
 *
 * DŮLEŽITÉ: config.php je JEDEN soubor pro localhost i produkci —
 * prostředí se pozná samo (viz níže). Na disku i na serveru tedy leží
 * úplně stejný soubor, není co rozlišovat ani hlídat.
 */

// ── Rozpoznání prostředí ─────────────────────────────────────────────────────
// Lokální vývoj = Windows/XAMPP nebo adresa localhost.
// Vše ostatní (Linux hosting, ostrá doména) = produkce.

$appHost = trim((string)getenv('APP_HOST'));
$hostitel = strtolower((string)preg_replace(
    '/:\d+$/',
    '',
    $appHost !== '' ? $appHost : ($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? ''))
));

$jeLokalne = PHP_OS_FAMILY === 'Windows'
    || in_array($hostitel, ['localhost', '127.0.0.1', '::1', ''], true)
    || str_ends_with($hostitel, '.local')
    || str_ends_with($hostitel, '.test');

define('JE_LOKALNE', $jeLokalne);

// Jediny duveryhodny zaklad pro odkazy v e-mailech a notifikacich.
// Hodnota z HTTP Host se z bezpecnostnich duvodu nepouziva.
// Pro Stripe je APP_BASE_URL povinne a na produkci musi byt platna https:// URL
// (pro KIS: https://kis.kovopraha.cz); bez ni Stripe zustane fail-closed vypnuty.
$appBaseUrl = getenv('APP_BASE_URL');
if (is_string($appBaseUrl) && $appBaseUrl !== '') {
    define('APP_BASE_URL', $appBaseUrl);
} elseif ($jeLokalne) {
    define('APP_BASE_URL', 'http://localhost/evidencePavel');
} else {
    define('APP_BASE_URL', 'https://kis.kovopraha.cz');
}

// Absolutni adresar mimo verejny webroot. Na produkci jej nastavte v prostredi.
$privateStorageRoot = getenv('APP_PRIVATE_STORAGE_ROOT');
if (is_string($privateStorageRoot) && $privateStorageRoot !== '') {
    define('APP_PRIVATE_STORAGE_ROOT', $privateStorageRoot);
}

// ── Citlivé údaje sportovce (RČ) ───────────────────────────────────────────
// Produkce je bez všech tří hodnot fail-closed. Klíče jsou base64 kódovaných
// 32 náhodných bajtů; indexový klíč musí být jiný než každý šifrovací klíč.
// Preferované environment proměnné:
//   PERSON_RC_KEYRING_JSON={"v1":"<BASE64-32-BYTES>"}
//   PERSON_RC_ACTIVE_KEY_VERSION=v1
//   PERSON_RC_INDEX_KEY=<JINY-BASE64-32-BYTES>
// Pokud hosting environment neumí, přidejte pouze do ignorovaného config.php:
// define('PERSON_RC_KEYRING', ['v1' => '<BASE64-32-BYTES>']);
// define('PERSON_RC_ACTIVE_KEY_VERSION', 'v1');
// define('PERSON_RC_INDEX_KEY', '<JINY-BASE64-32-BYTES>');

// ── Bezpečnost přihlášení ───────────────────────────────────────────────────
// POVINNÉ PŘED DEPLOYEM: unikátní náhodný secret o délce nejméně 32 znaků.
// Preferovaně jej nastavte na hostingu jako environment proměnnou, mimo webroot.
$authRateLimitPepper = getenv('AUTH_RATE_LIMIT_PEPPER');
if (is_string($authRateLimitPepper) && $authRateLimitPepper !== '') {
    define('AUTH_RATE_LIMIT_PEPPER', $authRateLimitPepper);
}
// Pokud hosting environment proměnné neumí, přidejte pouze do ignorovaného config.php:
// define('AUTH_RATE_LIMIT_PEPPER', '<NAHODNY-SECRET-ALESPOŇ-32-ZNAKU>');

// Seznam IP adres nebo CIDR rozsahů reverzních proxy, kterým se smí věřit.
// Prázdné = žádná proxy, používá se výhradně REMOTE_ADDR (dnešní chování).
define('AUTH_TRUSTED_PROXIES', []);

// Účet zůstává přísně omezený. Vyšší IP práh počítá se sdílenými klubovými,
// školními a rodinnými sítěmi; úspěšná přihlášení se do něj nezapočítají.
define('AUTH_RATE_LIMIT_ACCOUNT_MAX_ATTEMPTS', 5);
define('AUTH_RATE_LIMIT_IP_MAX_ATTEMPTS', 40);

// ── Databáze ─────────────────────────────────────────────────────────────────

if ($jeLokalne) {
    // XAMPP na vlastním počítači. Samostatný port 3308 nekoliduje s jinými
    // lokálními projekty. Proměnné používá přenosný localhost spouštěč.
    $localDbHost = getenv('EVIDENCE_LOCAL_DB_HOST');
    $localDbName = getenv('EVIDENCE_LOCAL_DB_NAME');
    $localDbUser = getenv('EVIDENCE_LOCAL_DB_USER');
    $localDbPass = getenv('EVIDENCE_LOCAL_DB_PASS');
    define('DB_HOST', is_string($localDbHost) && $localDbHost !== '' ? $localDbHost : '127.0.0.1;port=3308');
    define('DB_NAME', is_string($localDbName) && $localDbName !== '' ? $localDbName : 'evidence');
    define('DB_USER', is_string($localDbUser) && $localDbUser !== '' ? $localDbUser : 'root');
    define('DB_PASS', is_string($localDbPass) ? $localDbPass : '');
} else {
    // Produkční hosting
    define('DB_HOST', '127.0.0.1');
    define('DB_NAME', 'nazev_produkcni_db');
    define('DB_USER', 'db_uzivatel');
    define('DB_PASS', 'heslo-sem');       // NIKDY nepřidávat do gitu
}

// ── Legacy Velocota bridge ───────────────────────────────────────────────────

// Evidence je samostatná aplikace. Toto je pouze vypnutá kompatibilní možnost
// pro starší experiment; není součástí cílové architektury ani deploy plánu.
// Nezapínat bez nového výslovného rozhodnutí a samostatného security review.
define('VELOCOTA_INTEGRATION', false);

// ── První bankovní checkout e-shopu ─────────────────────────────────────────
// Na produkci preferujte environment proměnné. Bez platného IBAN a názvu účtu
// checkout záměrně selže před vytvořením objednávky.
$shopBankIban = getenv('SHOP_BANK_IBAN');
$shopBankBic = getenv('SHOP_BANK_BIC');
$shopBankAccountLabel = getenv('SHOP_BANK_ACCOUNT_LABEL');
$shopBankDueDays = getenv('SHOP_BANK_DUE_DAYS');
if (is_string($shopBankIban) && $shopBankIban !== '') define('SHOP_BANK_IBAN', $shopBankIban);
if (is_string($shopBankBic) && $shopBankBic !== '') define('SHOP_BANK_BIC', $shopBankBic);
if (is_string($shopBankAccountLabel) && $shopBankAccountLabel !== '') define('SHOP_BANK_ACCOUNT_LABEL', $shopBankAccountLabel);
if (is_string($shopBankDueDays) && preg_match('/^[0-9]{1,2}$/D', $shopBankDueDays) === 1) define('SHOP_BANK_DUE_DAYS', (int)$shopBankDueDays);

// Bezpečné localhost demo. Bankovní kód 9999 nepatří skutečné bance.
if (JE_LOKALNE) {
    defined('AUTH_RATE_LIMIT_PEPPER') || define('AUTH_RATE_LIMIT_PEPPER', 'localhost-only-rate-limit-pepper-do-not-deploy');
    defined('SHOP_BANK_IBAN') || define('SHOP_BANK_IBAN', 'CZ7599999999999999999999');
    defined('SHOP_BANK_BIC') || define('SHOP_BANK_BIC', '');
    defined('SHOP_BANK_ACCOUNT_LABEL') || define('SHOP_BANK_ACCOUNT_LABEL', 'LOCALHOST TEST - NEPLATIT');
    defined('SHOP_BANK_DUE_DAYS') || define('SHOP_BANK_DUE_DAYS', 7);
}

// Read-only Fio import v shadow rezimu. Pouzijte vyhradne token typu "Sledovani uctu".
// Token nikdy neukladejte do tohoto souboru ani do Gitu; patri do FIO_API_TOKEN v prostredi.
$fioImportEnabled = getenv('FIO_IMPORT_ENABLED');
$fioImportLookbackDays = getenv('FIO_IMPORT_LOOKBACK_DAYS');
define('FIO_IMPORT_ENABLED', is_string($fioImportEnabled) && $fioImportEnabled === '1');
if (is_string($fioImportLookbackDays) && preg_match('/^(?:[1-9]|[12][0-9]|30)$/D', $fioImportLookbackDays) === 1) {
    define('FIO_IMPORT_LOOKBACK_DAYS', (int)$fioImportLookbackDays);
}

// ── Stripe Checkout (výchozí stav: vypnuto) ────────────────────────────────
// Klíče patří pouze do prostředí nebo do ignorovaného config.php. Na omezeném
// produkčním hostingu je při deployi nastaví putenv bootstrap ve stejném PHP
// procesu; nikdy je nevkládejte do repozitáře ani do GitHub Variables.
$stripeEnabled = getenv('STRIPE_ENABLED');
$stripeSecretKey = getenv('STRIPE_SECRET_KEY');
$stripePublishableKey = getenv('STRIPE_PUBLISHABLE_KEY');
$stripeWebhookSecret = getenv('STRIPE_WEBHOOK_SECRET');

defined('STRIPE_ENABLED') || define('STRIPE_ENABLED', is_string($stripeEnabled) && $stripeEnabled === '1');
if (!defined('STRIPE_SECRET_KEY') && is_string($stripeSecretKey) && $stripeSecretKey !== '') define('STRIPE_SECRET_KEY', $stripeSecretKey);
if (!defined('STRIPE_PUBLISHABLE_KEY') && is_string($stripePublishableKey) && $stripePublishableKey !== '') define('STRIPE_PUBLISHABLE_KEY', $stripePublishableKey);
if (!defined('STRIPE_WEBHOOK_SECRET') && is_string($stripeWebhookSecret) && $stripeWebhookSecret !== '') define('STRIPE_WEBHOOK_SECRET', $stripeWebhookSecret);
