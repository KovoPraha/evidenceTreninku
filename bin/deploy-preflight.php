<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Předletová kontrola produkčního serveru pro deploy workflow.
 *
 * Hosting používá omezený shell, který zakazuje `php -r`; kontrola proto běží
 * jako samostatný skript nahraný do deploy adresáře mimo webroot. Skript nic
 * nemění: pouze čte config a prostředí a při problému skončí nenulovým kódem
 * se srozumitelnou hláškou na STDERR.
 *
 * Vstup: env APP_HOST (produkční hostname), APP_ROOT (docroot s config.php)
 * a DEPLOY_PROBE=1. Omezený shell hostingu blokuje i argumenty za jménem
 * skriptu, proto se vše předává výhradně proměnnými prostředí; DEPLOY_PROBE
 * zároveň ověřuje, že env proměnné skutečně procházejí.
 */

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

if ((string)getenv('DEPLOY_PROBE') !== '1') {
    $fail('Env DEPLOY_PROBE=1 nedorazila — shell nepropustil proměnné prostředí.');
}

foreach (['pdo_mysql', 'zlib', 'json', 'hash'] as $extension) {
    if (!extension_loaded($extension)) {
        $fail('missing extension: ' . $extension);
    }
}

$appHost = trim((string)getenv('APP_HOST'));
$appRoot = trim((string)getenv('APP_ROOT'));
if ($appHost === '' || $appRoot === '') {
    $fail('APP_HOST a APP_ROOT musí být nastavené.');
}
if (!is_file($appRoot . '/config.php')) {
    $fail('config.php v APP_ROOT neexistuje.');
}

$_SERVER['HTTP_HOST'] = $appHost;
$_SERVER['SERVER_NAME'] = $appHost;
require $appRoot . '/config.php';

if (!defined('JE_LOKALNE') || JE_LOKALNE) {
    $fail('config.php se na produkčním hostu vyhodnotil jako lokální prostředí.');
}
foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $constant) {
    if (!defined($constant)) {
        $fail('config.php nedefinuje ' . $constant . '.');
    }
}

$pepper = defined('AUTH_RATE_LIMIT_PEPPER') ? constant('AUTH_RATE_LIMIT_PEPPER') : null;
if (!is_string($pepper) || strlen($pepper) < 32) {
    $fail('AUTH_RATE_LIMIT_PEPPER is missing or too short.');
}

echo json_encode(['ok' => true, 'php' => PHP_VERSION], JSON_THROW_ON_ERROR) . PHP_EOL;
