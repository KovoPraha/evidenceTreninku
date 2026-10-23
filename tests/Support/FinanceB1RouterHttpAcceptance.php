<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
$runtime = getenv('LOCALAPPDATA') . '/EvidencePavelB1/finance-b1-20261022';
if (trim((string)file_get_contents($runtime . '/owner.txt')) !== $root) throw new RuntimeException('Unexpected local owner.');
$checks = 0;
foreach (['/login.php' => 200, '/.git' => 404, '/database/local-demo-schema.sql' => 404, '/docs/CURRENT_STATE.md' => 404, '/vendor/autoload.php' => 404, '/includes/auth.php' => 404, '/config.php' => 404, '/bin/prepare-club-local-review.php' => 404, '/tests/Support/FinanceB1HttpAcceptance.php' => 404, '/uploads/uctenky/a.pdf' => 404, '/login.php%20' => 404, '/login.php::$DATA' => 404, '/%252egit/config' => 404] as $path => $expected) {
    $curl = curl_init('http://127.0.0.1:18086' . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_PROTOCOLS => CURLPROTO_HTTP, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 15]);
    $body = curl_exec($curl); $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE); curl_close($curl);
    if ($status !== $expected) throw new RuntimeException('Unexpected HTTP status for ' . $path . ': ' . $status);
    if ($expected === 404 && $body !== "Not found.\n") throw new RuntimeException('Protected path leaked content: ' . $path);
    ++$checks;
}
$curl = curl_init('http://127.0.0.1:18086/login.php');
curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_PROXY=>'', CURLOPT_PROTOCOLS=>CURLPROTO_HTTP, CURLOPT_FOLLOWLOCATION=>false, CURLOPT_TIMEOUT=>15, CURLOPT_HTTPHEADER=>['Host: attacker.example.invalid']]);
$body=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
if($status!==404||$body!=="Not found.\n")throw new RuntimeException('Foreign Host must not reach the local application.');
++ $checks;
echo json_encode(['status' => 'PASS', 'read_only_http_checks' => $checks, 'production_mutations' => false], JSON_THROW_ON_ERROR) . PHP_EOL;
