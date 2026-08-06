<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$arguments = array_slice($argv, 1);

// Omezené hostingové shelly blokují argumenty za jménem skriptu; bez argumentů
// lze proto akci zadat proměnnými prostředí MIGRATE_ACTION=check|apply a
// MIGRATE_JSON=1. CLI argumenty mají přednost a chování se jinak nemění.
if ($arguments === []) {
    $environmentAction = strtolower(trim((string)getenv('MIGRATE_ACTION')));
    if (in_array($environmentAction, ['check', 'apply'], true)) {
        $arguments[] = '--' . $environmentAction;
        if ((string)getenv('MIGRATE_JSON') === '1') {
            $arguments[] = '--json';
        }
    }
}

$json = in_array('--json', $arguments, true);
$commands = array_values(array_intersect($arguments, ['--check', '--apply']));
$unknown = array_values(array_diff($arguments, ['--check', '--apply', '--json', '--help']));

$emit = static function (array $payload, bool $asJson): void {
    if ($asJson) {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
        return;
    }

    $state = ($payload['current'] ?? false) ? 'AKTUALNI' : 'NEAKTUALNI';
    echo $state . ': ' . ($payload['reason'] ?? 'unknown') . PHP_EOL;
    if (!empty($payload['message'])) {
        echo $payload['message'] . PHP_EOL;
    }
    if (!empty($payload['pending'])) {
        echo 'Cekajici migrace: ' . implode(', ', $payload['pending']) . PHP_EOL;
    }
};

if ($arguments === ['--help']) {
    echo "Pouziti: APP_HOST=<host> php bin/migrate.php --check|--apply [--json]\n";
    exit(0);
}

if (count($commands) !== 1
    || $unknown !== []
    || in_array('--help', $arguments, true)
    || count($arguments) !== count(array_unique($arguments))
) {
    fwrite(STDERR, "Pouziti: APP_HOST=<host> php bin/migrate.php --check|--apply [--json]\n");
    exit(64);
}

$appHost = getenv('APP_HOST');
if (!is_string($appHost)
    || $appHost === ''
    || !preg_match('/^[a-z0-9.-]+(?::\d+)?$/Di', $appHost)
) {
    fwrite(STDERR, "APP_HOST musi byt explicitne nastaveny na hostname aplikace.\n");
    exit(64);
}

$_SERVER['HTTP_HOST'] = $appHost;
$_SERVER['SERVER_NAME'] = (string)preg_replace('/:\d+$/', '', $appHost);

if (!is_file($root . '/config.php')) {
    $emit([
        'ok' => false,
        'current' => false,
        'reason' => 'config_missing',
        'message' => 'Chybi config.php.',
    ], $json);
    exit(1);
}

try {
    require_once $root . '/config.php';
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
    $legacyApplier = static function (PDO $connection) use ($root): void {
        $pdo = $connection;
        require $root . '/includes/auto_migrace.php';
    };
    $runner = new EvidenceMigrationRunner($pdo, $catalog, $legacyApplier);
    $result = $commands[0] === '--apply' ? $runner->apply() : $runner->check();
    $emit($result, $json);
    exit(($result['current'] ?? false) ? EvidenceMigrationExit::OK : EvidenceMigrationExit::PENDING);
} catch (EvidenceMigrationException $exception) {
    $emit([
        'ok' => false,
        'current' => false,
        'reason' => $exception->reason,
        'message' => $exception->getMessage(),
    ], $json);
    exit($exception->exitCode);
} catch (Throwable $exception) {
    $emit([
        'ok' => false,
        'current' => false,
        'reason' => 'migration_error',
        'message' => 'Migracni kontrola selhala; podrobnosti jsou pouze v serverovem logu.',
    ], $json);
    error_log('migrate.php: ' . $exception->getMessage());
    exit(1);
}
