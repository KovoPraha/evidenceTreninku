<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$options = getopt('', ['confirm-seed', 'json']);
$json = isset($options['json']);
$respond = static function (array $payload, int $exitCode = 0) use ($json): never {
    echo $json
        ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL
        : implode(PHP_EOL, array_map(static fn(string $key, mixed $value): string => $key . '=' . (is_scalar($value) ? (string)$value : json_encode($value)), array_keys($payload), $payload)) . PHP_EOL;
    exit($exitCode);
};

$host = getenv('APP_HOST');
if (!is_string($host) || !in_array(strtolower($host), ['localhost', '127.0.0.1'], true)) {
    $respond(['status' => 'blocked', 'error' => 'M2.3g seed je povolen pouze pro explicitní localhost.'], 77);
}
if (!isset($options['confirm-seed'])) $respond(['status' => 'dry_run', 'writes' => 0, 'hint' => 'Přidejte --confirm-seed.']);

$_SERVER['HTTP_HOST'] = $host;
$_SERVER['SERVER_NAME'] = $host;
$root = dirname(__DIR__);
try {
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/config.php';
    require_once $root . '/includes/kis_sync_lib.php';
    require_once $root . '/includes/kis_import_run_lib.php';
    if (!defined('JE_LOKALNE') || JE_LOKALNE !== true) throw new RuntimeException('M2.3g seed odmítl jiné než lokální prostředí.');
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $fixtures = [
        'users' => $root . '/tests/fixtures/kis/m23g-charge-users.csv',
        'payments' => $root . '/tests/fixtures/kis/m23g-charge-payments.csv',
        'rosters' => $root . '/tests/fixtures/kis/m23g-charge-rosters.csv',
    ];
    $payload = kis_build_import($fixtures['users'], $fixtures['payments'], $fixtures['rosters']);
    if ($payload['warnings'] !== [] || count($payload['people']) !== 2) throw new RuntimeException('Syntetický M2.3g export neprošel datovým kontraktem.');

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    foreach ($payload['people'] as $person) {
        $externalId = (string)$person['kis_external_id'];
        $lookup = $pdo->prepare('SELECT id,jmeno,prijmeni FROM sportovci WHERE kis_external_id=?');
        $lookup->execute([$externalId]);
        $existing = $lookup->fetch(PDO::FETCH_ASSOC);
        if ($existing && ((string)$existing['jmeno'] !== (string)$person['jmeno'] || (string)$existing['prijmeni'] !== (string)$person['prijmeni'])) {
            throw new RuntimeException('Syntetické KIS ID koliduje s jinou lokální osobou.');
        }
        if (!$existing) {
            $pdo->prepare(
                'INSERT INTO sportovci(jmeno,hash,prijmeni,narozeni,kis_external_id,kis_aktivni,kis_platebne_aktivni,kis_neuhrazeno,kis_posledni_uhrada,kis_soupisky,first_name_norm,last_name_norm,kis_identity_key) '
                . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $person['jmeno'], bin2hex(random_bytes(32)), $person['prijmeni'], $person['narozeni'], $externalId,
                $person['kis_aktivni'], $person['kis_platebne_aktivni'], $person['kis_neuhrazeno'], $person['kis_posledni_uhrada'], $person['kis_soupisky'],
                kisMatchNormalizeText((string)$person['jmeno']), kisMatchNormalizeText((string)$person['prijmeni']),
                kisMatchNormalizeText((string)$person['jmeno']) . '|' . kisMatchNormalizeText((string)$person['prijmeni']) . '|' . (string)$person['narozeni'],
            ]);
        } else {
            $pdo->prepare(
                'UPDATE sportovci SET narozeni=?,kis_aktivni=?,kis_platebne_aktivni=?,kis_neuhrazeno=?,kis_posledni_uhrada=?,kis_soupisky=? WHERE id=?'
            )->execute([
                $person['narozeni'], $person['kis_aktivni'], $person['kis_platebne_aktivni'], $person['kis_neuhrazeno'],
                $person['kis_posledni_uhrada'], $person['kis_soupisky'], (int)$existing['id'],
            ]);
        }
    }
    if ($ownsTransaction) $pdo->commit();

    $archive = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'evidencePavel-kis-source-archive';
    if (!is_dir($archive) && !mkdir($archive, 0700, true) && !is_dir($archive)) throw new RuntimeException('Nelze vytvořit privátní localhost archiv KIS zdrojů.');
    $artifactIds = [];
    foreach ($fixtures as $kind => $fixture) {
        $artifact = kisSourceArchive($pdo, $fixture, $kind, 'm23g-charge-v1', $archive, null);
        $artifactIds[$kind] = (int)$artifact['id'];
    }
    $manifest = kisSourceManifest($pdo, $artifactIds);
    $runs = $pdo->query("SELECT * FROM kis_import_runs WHERE status='preview' AND source_users='m23g-charge-users.csv' ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($runs as $run) {
        $storedManifest = json_decode((string)($run['source_manifest_json'] ?? ''), true);
        $parity = kisImportStoredParityReport($run);
        if (is_array($storedManifest)
            && hash_equals((string)$manifest['fingerprint'], (string)($storedManifest['fingerprint'] ?? ''))
            && $parity !== null
            && (int)($parity['domains']['payment_prescriptions']['staged_rows'] ?? 0) === 2) {
            $respond(['status' => 'existing', 'run_id' => (int)$run['id'], 'parity_status' => $parity['status'], 'url' => 'http://localhost/evidencePavel/kis_sync_center.php?run_id=' . (int)$run['id']]);
        }
    }
    $runId = kisImportCreateRun(
        $pdo, $payload['people'], $payload['meta'], $payload['warnings'],
        ['users' => 'm23g-charge-users.csv', 'payments' => 'm23g-charge-payments.csv', 'rosters' => 'm23g-charge-rosters.csv'],
        null, $artifactIds
    );
    $run = $pdo->query('SELECT * FROM kis_import_runs WHERE id=' . $runId)->fetch(PDO::FETCH_ASSOC);
    $parity = kisImportStoredParityReport($run);
    if ($parity === null) throw new RuntimeException('M2.3g paritní report nebyl uložen.');
    $respond([
        'status' => 'created', 'run_id' => $runId, 'parity_status' => $parity['status'],
        'row_blockers' => (int)$parity['summary']['row_blockers'],
        'coverage_blockers' => $parity['coverage_blockers'],
        'staged_payment_prescriptions' => (int)$parity['domains']['payment_prescriptions']['staged_rows'],
        'url' => 'http://localhost/evidencePavel/kis_sync_center.php?run_id=' . $runId,
    ]);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('seed-kis-m23g-charge-preview.php: ' . $exception->getMessage());
    $respond(['status' => 'error', 'error' => 'M2.3g demo preview se nepodařilo bezpečně připravit.'], 1);
}
