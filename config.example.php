<?php
/**
 * config.example.php
 * Vzor konfigurace. Zkopírujte jako config.php a upravte.
 * Soubor config.php NENÍ verzován (je v .gitignore).
 *
 * POVINNÉ na produkci: db.php načítá tento soubor PŘED připojením k DB.
 * Bez konstant DB_HOST/DB_NAME/DB_USER/DB_PASS produkční server odmítne start.
 * Na localhostu (SERVER_NAME = localhost/127.0.0.1) konstanty nejsou povinné —
 * db.php použije výchozí hodnoty root / '' / evidence.
 */

// ── Databáze (povinné na produkci) ───────────────────────────────────────────

// define('DB_HOST', '127.0.0.1');
// define('DB_NAME', 'kovoprahacz09');      // název produkční DB
// define('DB_USER', 'kovoprahacz010');     // DB uživatel
// define('DB_PASS', 'heslo-sem');          // DB heslo — NIKDY nepřidávat do gitu

// ── Integrace s Velocotou ─────────────────────────────────────────────────────

// true  = produkce s Velocotou (SSO, sdílená navigace)
// false = standalone mód (lokální vývoj, vlastní login.php)
define('VELOCOTA_INTEGRATION', false);

// Cesta ke kořeni Velocota aplikace na serveru (pro include headeru)
// Příklad: '/var/www/html/velocota'
define('VELOCOTA_ROOT', '/var/www/html/velocota');

// Base URL Evidence v kontextu Velocota (pro generování odkazů)
// Příklad: 'https://kovopraha.cz/evidence'
define('VELOCOTA_EVIDENCE_BASE_URL', 'http://localhost/evidencePavel');

// ── Session klíče z Velocoty (NEMĚNIT bez koordinace s Velocota týmem) ────────
// Velocota zapisuje tyto klíče; Evidence je čte v auth/sso_bridge.php
define('VELO_SESSION_USER_ID',  'velo_user_id');
define('VELO_SESSION_ROLE',     'velo_role');
define('VELO_SESSION_JMENO',    'velo_jmeno');
define('VELO_SESSION_EMAIL',    'velo_email');
define('VELO_SESSION_KLUB_ID',  'velo_klub_id');
