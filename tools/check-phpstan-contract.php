<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$project = dirname(__DIR__);
$root = sys_get_temp_dir() . '/evidence-phpstan-contract-' . bin2hex(random_bytes(8));
mkdir($root . '/booking', 0700, true);
mkdir($root . '/includes', 0700);
$fixtures = [
    'db.php' => '<?php',
    'includes/init.php' => '<?php',
    'kis_review_admin.php' => <<<'PHP'
<?php
require_once __DIR__ . '/includes/init.php';
$pdo->query('SELECT 1'); // Valid global import: no undefined-variable error.
function missingLocalPdo(): void { $pdo->query('SELECT 1'); }
$closure = function (): void { $pdo->query('SELECT 1'); };
function invalidResult(): int { return 'wrong'; }
PHP,
    'booking/eshop.php' => <<<'PHP'
<?php
require_once dirname(__DIR__) . '/db.php';
$pdo->query('SELECT 1'); // Valid dirname import.
PHP,
    'zavod_vysledky.php' => <<<'PHP'
<?php
$pdo->query('SELECT 1'); // Before import: must fail.
require_once __DIR__ . '/includes/init.php';
$other->query('SELECT 1'); // Wrong variable: must fail.
PHP,
    'member_charges_admin.php' => <<<'PHP'
<?php
if (random_int(0, 1)) { require_once __DIR__ . '/includes/init.php'; }
$pdo->query('SELECT 1'); // Conditional import: must fail.
PHP,
    'kis_sync_center.php' => <<<'PHP'
<?php
require_once __DIR__ . '/db.php';
$pdo->query('SELECT 1'); // Different bootstrap than reviewed: must fail.
PHP,
    'individualni_lekce_sprava.php' => <<<'PHP'
<?php
include __DIR__ . '/db.php';
$pdo->query('SELECT 1'); // Optional include: must fail.
PHP,
    'zavod_detail.php' => <<<'PHP'
<?php
require_once 'db.php';
unset($pdo);
$pdo->query('SELECT 1'); // Invalidated import: must fail.
PHP,
    'unreviewed.php' => <<<'PHP'
<?php
require_once __DIR__ . '/includes/init.php';
$pdo->query('SELECT 1'); // New entry point needs review: must fail.
function transactionState(PDO $pdo): void {
    if ($pdo->inTransaction()) { throw new LogicException(); }
    $pdo->beginTransaction();
    if ($pdo->inTransaction()) { $pdo->rollBack(); } // Mutable state: no always-false error.
}
PHP,
];
$config = [
    'parameters' => ['level' => 5, 'paths' => [$root], 'stubFiles' => [$project . '/tools/phpstan/Pdo.stub']],
    'services' => [[
        'class' => Evidence\PHPStan\BootstrapPdoExtension::class,
        'arguments' => ['parser' => '@defaultAnalysisParser', 'projectRoot' => $root],
        'tags' => ['phpstan.ignoreErrorExtension'],
    ]],
];
// JSON is valid NEON and keeps platform paths unambiguous.
$fixtures['contract.neon'] = json_encode($config, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
try {
    foreach ($fixtures as $file => $source) {
        file_put_contents($root . '/' . $file, $source);
    }
    $process = proc_open(
        [PHP_BINARY, $project . '/vendor/bin/phpstan', 'analyse', '-c', $root . '/contract.neon', '--no-progress', '--error-format=json'],
        [0 => ['pipe', 'r'], 1 => ['file', $root . '/report.json', 'w'], 2 => ['file', $root . '/stderr.txt', 'w']],
        $pipes,
        $project
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start PHPStan contract probe.');
    }
    fclose($pipes[0]);
    $exit = proc_close($process);
    $json = (string) file_get_contents($root . '/report.json');
    if ($json === '') {
        throw new RuntimeException('PHPStan probe failed: ' . file_get_contents($root . '/stderr.txt'));
    }
    $report = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    $actual = [];
    foreach ($report['files'] ?? [] as $file => $details) {
        foreach ($details['messages'] as $message) {
            $actual[] = basename($file) . ':' . $message['line'] . ':' . $message['identifier'];
        }
    }
    $expected = [
        'kis_review_admin.php:4:variable.undefined',
        'kis_review_admin.php:5:variable.undefined',
        'kis_review_admin.php:6:return.type',
        'zavod_vysledky.php:2:variable.undefined',
        'zavod_vysledky.php:4:variable.undefined',
        'member_charges_admin.php:3:variable.undefined',
        'kis_sync_center.php:3:variable.undefined',
        'individualni_lekce_sprava.php:3:variable.undefined',
        'zavod_detail.php:4:variable.undefined',
        'unreviewed.php:3:variable.undefined',
    ];
    sort($actual);
    sort($expected);
    if ($exit !== 1 || ($report['errors'] ?? []) !== [] || $actual !== $expected) {
        throw new RuntimeException('PHPStan contract mismatch: ' . json_encode([$exit, $actual, $report['errors'] ?? []]));
    }
    echo "PHPStan contract PASS: valid imports and transaction state accepted; 10 exact negative findings retained.\n";
} finally {
    foreach (array_merge(array_keys($fixtures), ['report.json', 'stderr.txt']) as $file) {
        if (is_file($root . '/' . $file)) {
            unlink($root . '/' . $file);
        }
    }
    rmdir($root . '/booking');
    rmdir($root . '/includes');
    rmdir($root);
}
