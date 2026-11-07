<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$output = realpath($argv[1] ?? '');
if ($output === false) {
    throw new RuntimeException('Owned output required.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
[$runtime, $admin] = fullHttpEnvironment($output);
if (!preg_match('/^http_[a-f0-9]{10}$/D', $runtime['web_user'])) {
    throw new RuntimeException('Unexpected synthetic database account.');
}
$admin->exec('SET GLOBAL general_log=OFF');
$admin->exec('DROP DATABASE `' . $runtime['schema'] . '`');
$admin->exec('DROP USER ' . $admin->quote($runtime['web_user']) . "@'127.0.0.1'");
$q = $admin->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');
$q->execute([$runtime['schema']]);
if ((int)$q->fetchColumn() !== 0) {
    throw new RuntimeException('Owned schema cleanup failed.');
}
// The environment guard verified this exact generated config hash before any mutation.
unlink(dirname(__DIR__, 2) . '/config.php');
foreach (array_merge([$output . '/credentials.json', $output . '/login-calibration.html', $output . '/case-calibration.html'], glob($output . '/cookie-*.txt') ?: []) as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}
file_put_contents($output . '/cleanup.json', json_encode(['synthetic_only' => true, 'schema_removed' => true,
    'web_user_removed' => true, 'owned_config_removed' => true, 'credentials_and_cookie_jars_removed' => true], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
echo "Owned schema/account/config and local credentials removed. Stop the two owned services separately.\n";
