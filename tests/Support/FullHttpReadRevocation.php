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
$credentials = json_decode((string)file_get_contents($output . '/credentials.json'), true, 512, JSON_THROW_ON_ERROR);
if (is_file($output . '/cookie-other_coach.txt')) {
    unlink($output . '/cookie-other_coach.txt');
}
fullHttpLogin($runtime, $output, 'other_coach', $credentials['other_coach']);
$admin->exec('USE `' . $runtime['schema'] . '`');
$rows = $admin->query('SELECT * FROM staff_user_positions WHERE trainer_id=8')->fetchAll(PDO::FETCH_ASSOC);
$checks = [];
try {
    $admin->exec('DELETE FROM staff_user_positions WHERE trainer_id=8');
    $response = fullHttpRequest($runtime, $output, 'other_coach', '/planovac.php');
    if ($response['status'] !== 403) {
        throw new RuntimeException('A stale browser session retained a revoked role.');
    }
    $checks['removed_role'] = ['status' => 403, 'pass' => true];
    $admin->exec('UPDATE treneri SET aktivni=0 WHERE id=8');
    $response = fullHttpRequest($runtime, $output, 'other_coach', '/planovac.php');
    if ($response['status'] !== 303 || !str_contains($response['headers']['location'] ?? '', 'session=expired')) {
        throw new RuntimeException('Inactive account retained an authenticated session.');
    }
    $checks['inactive_account'] = ['status' => 303, 'pass' => true];
} finally {
    $admin->exec('UPDATE treneri SET aktivni=1 WHERE id=8');
    foreach ($rows as $row) {
        $columns = implode(',', array_map(static fn (string $c): string => '`' . $c . '`', array_keys($row)));
        $marks = implode(',', array_fill(0, count($row), '?'));
        $admin->prepare('INSERT INTO staff_user_positions(' . $columns . ') VALUES(' . $marks . ')')->execute(array_values($row));
    }
}
file_put_contents($output . '/http-revocation.json', json_encode(['complete' => true, 'synthetic_only' => true,
    'checks' => $checks, 'original_role_rows_restored' => true], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
echo "Fresh role/account revocation checks passed.\n";
