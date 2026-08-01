<?php
declare(strict_types=1);

/**
 * Zpetne kompatibilni read-only kontrolni endpoint.
 * Nespousti db.php, auto-migraci ani zadny zapis. Pro deploy se preferuje
 * SSH prikaz: APP_HOST=<host> php bin/migrate.php --check --json
 */

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
$respond = static function (array $payload, int $httpCode = 200): never {
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
};

if (!is_file($root . '/config.php')) {
    $respond(['ok' => false, 'chyba' => 'Na serveru chybi config.php.'], 500);
}

try {
    require_once $root . '/config.php';

    if (!defined('DEPLOY_TOKEN') || DEPLOY_TOKEN === '') {
        $respond(['ok' => false, 'chyba' => 'V config.php chybi DEPLOY_TOKEN.'], 403);
    }
    if (!hash_equals((string)DEPLOY_TOKEN, (string)($_GET['token'] ?? ''))) {
        $respond(['ok' => false, 'chyba' => 'Neplatny token.'], 403);
    }

    require_once $root . '/includes/migration_runner.php';
    foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $constant) {
        if (!defined($constant)) {
            throw new RuntimeException('missing_database_configuration');
        }
    }

    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $catalog = EvidenceMigrationCatalog::load($root . '/migrations');
    $result = (new EvidenceMigrationRunner($pdo, $catalog))->check();

    $respond([
        'ok' => true,
        'verze_db' => $result['legacy_version'] ?? '(nenastavena)',
        'verze_kod' => LEGACY_SCHEMA_VERSION,
        'vse_aplikovano' => $result['current'],
        'duvod' => $result['reason'],
        'cekajici_migrace' => $result['pending'],
        'php' => PHP_VERSION,
    ]);
} catch (EvidenceMigrationException $exception) {
    $respond([
        'ok' => false,
        'chyba' => $exception->getMessage(),
        'duvod' => $exception->reason,
    ], 409);
} catch (Throwable $exception) {
    error_log('stav.php: ' . $exception->getMessage());
    $respond([
        'ok' => false,
        'chyba' => 'Stav migraci nelze bezpecne precist.',
    ], 500);
}
