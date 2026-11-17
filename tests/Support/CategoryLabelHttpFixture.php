<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY') {
    http_response_code(404);
    exit;
}
$output = realpath($argv[1] ?? '');
$count = (int)($argv[2] ?? 0);
$shape = $argv[3] ?? '';
if ($output === false) {
    throw new RuntimeException('Owned output required.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
require_once __DIR__ . '/CategoryMenuFixture.php';
[$runtime, $admin] = fullHttpEnvironment($output);
$admin->exec('USE `' . $runtime['schema'] . '`');
categoryMenuFixture($admin, $count, $shape, true);
