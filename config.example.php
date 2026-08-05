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
$appBaseUrl = getenv('APP_BASE_URL');
if (is_string($appBaseUrl) && $appBaseUrl !== '') {
    define('APP_BASE_URL', $appBaseUrl);
} elseif ($jeLokalne) {
    define('APP_BASE_URL', 'http://localhost/evidencePavel');
} else {
    define('APP_BASE_URL', 'https://data.kovopraha.cz/evidence');
}

// Absolutni adresar mimo verejny webroot. Na produkci jej nastavte v prostredi.
$privateStorageRoot = getenv('APP_PRIVATE_STORAGE_ROOT');
if (is_string($privateStorageRoot) && $privateStorageRoot !== '') {
    define('APP_PRIVATE_STORAGE_ROOT', $privateStorageRoot);
}

// ── Bezpečnost přihlášení ───────────────────────────────────────────────────
// POVINNÉ PŘED DEPLOYEM: unikátní náhodný secret o délce nejméně 32 znaků.
// Preferovaně jej nastavte na hostingu jako environment proměnnou, mimo webroot.
$authRateLimitPepper = getenv('AUTH_RATE_LIMIT_PEPPER');
if (is_string($authRateLimitPepper) && $authRateLimitPepper !== '') {
    define('AUTH_RATE_LIMIT_PEPPER', $authRateLimitPepper);
}
// Pokud hosting environment proměnné neumí, přidejte pouze do ignorovaného config.php:
// define('AUTH_RATE_LIMIT_PEPPER', '<NAHODNY-SECRET-ALESPOŇ-32-ZNAKU>');

// ── Databáze ─────────────────────────────────────────────────────────────────

if ($jeLokalne) {
    // XAMPP na vlastním počítači
    define('DB_HOST', '127.0.0.1');
    define('DB_NAME', 'evidence');
    define('DB_USER', 'root');
    define('DB_PASS', '');
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
