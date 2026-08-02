<?php
require_once __DIR__ . '/includes/session_security.php';

// Načti config.php PŘED DB připojením (definuje DB_*, VELOCOTA_INTEGRATION, …)
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

// DB připojení — konstanty z config.php mají přednost před výchozími hodnotami.
// Localhost výchozí hodnoty fungují bez config.php (standalone vývoj).
if (defined('DB_HOST')) {
    $host = DB_HOST;
    $db   = DB_NAME;
    $user = DB_USER;
    $pass = DB_PASS;
} elseif (isset($_SERVER['SERVER_NAME']) && ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1')) {
    $host = '127.0.0.1';
    $db   = 'evidence';
    $user = 'root';
    $pass = '';
} else {
    // Produkce bez config.php — záměrně selže, aby bylo patrné, že chybí konfigurace.
    die('Chyba: chybí config.php s DB konstantami (DB_HOST, DB_NAME, DB_USER, DB_PASS).');
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Chyba připojení k databázi: ' . $e->getMessage());
}

// Auto-migrace DB schématu — spustí se vždy, ale provede ALTER jen pokud verze nesedí
require_once __DIR__ . '/includes/auto_migrace.php';

// Každá existující identita musí být aktivní a mít stejnou revokační verzi jako DB.
// Při neplatnosti request ukončíme: část legacy endpointů autorizuje ještě před db.php.
require_once __DIR__ . '/includes/auth_session.php';
if (!auth_session_validate($pdo)) {
    http_response_code(401);
    if (!headers_sent()) {
        header('Cache-Control: no-store');
        header('Content-Type: text/plain; charset=utf-8');
    }
    exit('Přihlášení již není platné. Přihlaste se znovu.');
}

// Legacy Velocota bridge není součástí cílové architektury Evidence.
// Feature flag musí zůstat false bez nového explicitního rozhodnutí a review.
if (defined('VELOCOTA_INTEGRATION') && VELOCOTA_INTEGRATION) {
    require_once __DIR__ . '/auth/sso_bridge.php';
    if (session_status() === PHP_SESSION_NONE) app_session_start();
    if (!velocotaSsoBridge($pdo)) {
        http_response_code(401);
        if (!headers_sent()) {
            header('Cache-Control: no-store');
            header('Content-Type: text/plain; charset=utf-8');
        }
        exit('Přihlášení již není platné. Přihlaste se znovu.');
    }
}
?>
