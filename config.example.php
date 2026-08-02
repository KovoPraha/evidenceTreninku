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

// ── Integrace s Velocotou ─────────────────────────────────────────────────────

// true  = provoz s Velocotou (SSO, sdílená navigace)
// false = samostatný provoz s vlastním login.php
define('VELOCOTA_INTEGRATION', false);

// Cesta ke kořeni Velocota aplikace na serveru (pro include headeru)
define('VELOCOTA_ROOT', '/var/www/html/velocota');

// Base URL Evidence v kontextu Velocota (pro generování odkazů)
define('VELOCOTA_EVIDENCE_BASE_URL', $jeLokalne
    ? 'http://localhost/evidencePavel'
    : 'https://data.kovopraha.cz/evidence');

// ── Session klíče z Velocoty (NEMĚNIT bez koordinace s Velocota týmem) ────────
define('VELO_SESSION_USER_ID',  'velo_user_id');
define('VELO_SESSION_ROLE',     'velo_role');
define('VELO_SESSION_JMENO',    'velo_jmeno');
define('VELO_SESSION_EMAIL',    'velo_email');
define('VELO_SESSION_KLUB_ID',  'velo_klub_id');
