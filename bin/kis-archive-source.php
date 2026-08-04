<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$args = getopt('', ['input:', 'kind:', 'contract:', 'archive-dir:', 'actor-id::', 'confirm-archive', 'json']);
$json = isset($args['json']);
$fail = static function (string $message, int $code = 64) use ($json): never {
    if ($json) {
        echo json_encode(['status' => 'error', 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
    } else {
        fwrite(STDERR, $message . PHP_EOL);
    }
    exit($code);
};

foreach (['input', 'kind', 'contract', 'archive-dir'] as $required) {
    if (!isset($args[$required]) || !is_string($args[$required]) || trim($args[$required]) === '') {
        $fail('Použití: APP_HOST=localhost php bin/kis-archive-source.php --input=SOUBOR --kind=users|payments|rosters --contract=VERZE --archive-dir=ADRESAR [--confirm-archive] [--json]');
    }
}
$host = getenv('APP_HOST');
if (!is_string($host) || !in_array(strtolower($host), ['localhost', '127.0.0.1'], true)) {
    $fail('M2 archivace je zatím povolena pouze pro explicitní localhost.', 77);
}
$_SERVER['HTTP_HOST'] = $host;
$_SERVER['SERVER_NAME'] = $host;

try {
    require_once $root . '/config.php';
    require_once $root . '/includes/kis_source_archive.php';
    if (!defined('JE_LOKALNE') || JE_LOKALNE !== true) {
        throw new RuntimeException('KIS archivace odmítla jiné než lokální prostředí.');
    }
    $inspection = kisSourceInspect((string)$args['input'], (string)$args['kind'], (string)$args['contract']);
    kisSourceArchiveRoot((string)$args['archive-dir']);
    if (!isset($args['confirm-archive'])) {
        $result = array_merge(['status' => 'dry_run', 'writes' => 0], $inspection);
    } else {
        foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $constant) {
            if (!defined($constant)) {
                throw new RuntimeException('Chybí databázová konfigurace.');
            }
        }
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
        );
        $result = array_merge(
            ['status' => 'archived'],
            kisSourceArchive(
                $pdo,
                (string)$args['input'],
                (string)$args['kind'],
                (string)$args['contract'],
                (string)$args['archive-dir'],
                isset($args['actor-id']) ? (int)$args['actor-id'] : null
            )
        );
    }
    echo $json
        ? json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL
        : implode(' | ', array_map(static fn(string $key, mixed $value): string => $key . '=' . (is_bool($value) ? ($value ? '1' : '0') : $value), array_keys($result), $result)) . PHP_EOL;
} catch (Throwable $exception) {
    error_log('kis-archive-source.php: ' . $exception->getMessage());
    $fail('Archivace KIS zdroje selhala bezpečně; zdrojový obsah nebyl vypsán.', 1);
}
