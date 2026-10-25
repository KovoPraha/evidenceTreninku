<?php
declare(strict_types=1);
// Explicit owned, synthetic runtime only. Never use production configuration.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
$runtime = realpath($argv[1] ?? '') ?: '';
$normalize = static fn(string $path): string => strtolower(rtrim(str_replace('\\', '/', $path), '/'));
$check = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
$check($runtime !== '' && is_file($runtime.'/owner.txt'), 'Explicit owned runtime required.');
$descriptor = json_decode((string)file_get_contents($runtime.'/environment.json'), true, 512, JSON_THROW_ON_ERROR);
$check($normalize(trim((string)file_get_contents($runtime.'/owner.txt'))) === $normalize($root), 'Wrong runtime owner.');
$check(realpath($descriptor['application_root'] ?? '') === realpath($root), 'Wrong application root.');
$check(($descriptor['synthetic_only'] ?? false) === true && ($descriptor['external_sends_disabled'] ?? false) === true, 'Synthetic isolated runtime required.');
require $root.'/config.php';
$port = (int)($descriptor['db_port'] ?? 0);
$check($port > 1024 && ($descriptor['db_host'] ?? '') === '127.0.0.1', 'Loopback database required.');
$check(DB_HOST === '127.0.0.1;port='.$port && DB_NAME === 'evidence_b1' && APP_BASE_URL === ($descriptor['base_url'] ?? ''), 'Unexpected database configuration.');
$check(JE_LOKALNE === true && parse_url(APP_BASE_URL, PHP_URL_HOST) === '127.0.0.1', 'Local application required.');
foreach (['FIO_IMPORT_ENABLED','FIO_AUTO_CONFIRM_ENABLED','SUMUP_ENABLED','STRIPE_ENABLED','VELOCOTA_INTEGRATION'] as $flag) {
    $check(defined($flag) && constant($flag) === false, 'External integration must be disabled.');
}
$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$identity = $pdo->query('SELECT @@port port,@@datadir path')->fetch();
$check((int)$identity['port'] === $port && str_starts_with($normalize($identity['path']).'/', $normalize($runtime).'/'), 'Database outside owned runtime.');
require_once $root.'/includes/kis_a06_acceptance.php';
putenv('APP_HOST=localhost');
$seed = static function () use ($root, $runtime, $check): void {
    $process = proc_open([PHP_BINARY, '-c', $runtime.'/php-web.ini', $root.'/bin/seed-local-demo.php'], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, $root);
    $check(is_resource($process), 'Could not start local seed.');
    fclose($pipes[0]);
    $result = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
    $exit = proc_close($process);
    $check($exit === 0, 'Seed failed: '.$error);
    // Never print the seed output: it contains synthetic login credentials.
    $check((json_decode($result, true, 512, JSON_THROW_ON_ERROR)['ok'] ?? false) === true, 'Seed did not report success.');
};
$seed();
$actor = (int)$pdo->query("SELECT id FROM treneri WHERE jmeno='localhost-admin' ORDER BY id LIMIT 1")->fetchColumn();
$team = (int)$pdo->query("SELECT id FROM club_teams WHERE code='LOCAL-U17-2027'")->fetchColumn();
$marker = 'LOCALHOST NEPLATIT seed sentinel '.bin2hex(random_bytes(5));
$pdo->prepare("INSERT INTO sportovci(jmeno,prijmeni,narozeni,email,telefon,hash,uci,stav_clenstvi) VALUES (?,?,'2012-01-01',?,'',?,0,'aktivni')")->execute(['LOCALHOST',$marker,'seed-sentinel-'.bin2hex(random_bytes(5)).'@localhost.test',bin2hex(random_bytes(32))]);
$sentinel = (int)$pdo->lastInsertId();
$member = kisRosterAddMember($pdo, $team, $sentinel, $actor, 'manual', '2027-01-01', $marker);
$before = $pdo->query('SELECT * FROM club_roster_members WHERE id='.(int)$member['id'])->fetch();
$checks = [];
for ($cycle=1; $cycle<=2; $cycle++) {
    $seed();
    $check($pdo->query('SELECT * FROM club_roster_members WHERE id='.(int)$member['id'])->fetch() === $before, 'Seed modified another synthetic person in a demo target team.');
    $checks[] = 'cycle '.$cycle.': unrelated target membership preserved';
    $scenario = kisA06Scenario($pdo);
    $check(!$scenario['complete'], 'Seed did not restore A06 previews.');
    $result = kisA06Execute($pdo,$actor,$marker,true,$scenario['batch_fingerprint'],$scenario['pending_fingerprints']);
    $check($result['moved_count'] === 2 && $result['skipped_count'] === 2, 'Wrong age/discipline/exception result.');
    $checks[] = 'cycle '.$cycle.': age progression and discipline moved, exceptions preserved';
    $again = kisA06Execute($pdo,$actor,$marker,true,$scenario['batch_fingerprint'],$scenario['pending_fingerprints']);
    $check($again['moved_count'] === 0 && kisA06Scenario($pdo)['complete'], 'A06 replay was not idempotent.');
    $checks[] = 'cycle '.$cycle.': repeated execution creates no duplicate';
}
$seed();
$check(!kisA06Scenario($pdo)['complete'], 'Final demo is not ready for another walkthrough.');
$checks[] = 'final seed restores a runnable demo';
echo json_encode(['status'=>'PASS','checks'=>count($checks),'results'=>$checks,'production_mutations'=>false],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
