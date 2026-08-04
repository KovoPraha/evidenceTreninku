<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = getopt('', ['confirm-seed', 'json']);
$json = isset($options['json']);
$respond = static function (array $payload, int $exitCode = 0) use ($json): never {
    if ($json) {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    } else {
        foreach ($payload as $key => $value) {
            if (is_scalar($value) || $value === null) {
                echo $key . '=' . ($value === null ? 'null' : (string)$value) . PHP_EOL;
            }
        }
    }
    exit($exitCode);
};

$host = getenv('APP_HOST');
if (!is_string($host) || !in_array(strtolower($host), ['localhost', '127.0.0.1'], true)) {
    $respond(['status' => 'blocked', 'error' => 'M2.3 demo seed je povolen pouze pro explicitní localhost.'], 77);
}
if (!isset($options['confirm-seed'])) {
    $respond(['status' => 'dry_run', 'writes' => 0, 'hint' => 'Přidejte --confirm-seed pro vytvoření syntetického preview.']);
}

$_SERVER['HTTP_HOST'] = $host;
$_SERVER['SERVER_NAME'] = $host;
$root = dirname(__DIR__);

try {
    require_once $root . '/config.php';
    require_once $root . '/includes/kis_import_run_lib.php';
    if (!defined('JE_LOKALNE') || JE_LOKALNE !== true) {
        throw new RuntimeException('Demo seed odmítl jiné než lokální prostředí.');
    }
    foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $constant) {
        if (!defined($constant)) {
            throw new RuntimeException('Chybí lokální databázová konfigurace.');
        }
    }
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );

    $fixtures = [
        'users' => $root . '/tests/fixtures/kis/m23d-field-users.csv',
        'payments' => $root . '/tests/fixtures/kis/m23d-field-payments.csv',
        'rosters' => $root . '/tests/fixtures/kis/m23d-field-rosters.csv',
    ];
    $archive = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'evidencePavel-kis-source-archive';
    if (!is_dir($archive) && !mkdir($archive, 0700, true) && !is_dir($archive)) {
        throw new RuntimeException('Nelze vytvořit privátní localhost archiv KIS zdrojů.');
    }
    $artifactIds = [];
    foreach ($fixtures as $kind => $fixture) {
        $artifact = kisSourceArchive($pdo, $fixture, $kind, 'm23d-synthetic-v1', $archive, null);
        $artifactIds[$kind] = (int)$artifact['id'];
    }
    $manifest = kisSourceManifest($pdo, $artifactIds);

    $runs = $pdo->query(
        "SELECT * FROM kis_import_runs WHERE status='preview' AND source_users='m23d-field-users.csv' ORDER BY id DESC LIMIT 20"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($runs as $existing) {
        $storedManifest = json_decode((string)($existing['source_manifest_json'] ?? ''), true);
        $storedReport = kisImportStoredPreviewReport($existing);
        if (is_array($storedManifest)
            && hash_equals((string)$manifest['fingerprint'], (string)($storedManifest['fingerprint'] ?? ''))
            && $storedReport !== null
            && kisFieldContractStoredReport($existing) !== null
            && ($existingParity = kisImportStoredParityReport($existing)) !== null
            && (int)($existingParity['domains']['payment_prescriptions']['staged_rows'] ?? 0) > 0) {
            $respond([
                'status' => 'existing',
                'run_id' => (int)$existing['id'],
                'preview_status' => (string)$storedReport['status'],
                'fingerprint' => (string)$storedReport['fingerprint'],
                'url' => 'http://localhost/evidencePavel/kis_sync_center.php?run_id=' . (int)$existing['id'],
            ]);
        }
    }

    $runId = kisImportCreateRun(
        $pdo,
        [
            ['kis_external_id' => 'KIS-M23D-001', '_kis_external_id_raw' => 'KIS-M23D-001', 'jmeno' => 'Localhost', 'prijmeni' => 'PreviewOne', 'narozeni' => '2012-01-01', 'uciid' => 'M23-SYNTH-001', '_soupisky_parsed' => ['Testovaci soupiska'], 'kis_soupisky' => 'Testovaci soupiska', 'kis_aktivni' => 1, 'kis_platebne_aktivni' => 1, '_kis_payment' => ['paid_rows' => 1, 'open_rows' => 0], '_kis_payment_rows' => [['payment_external_id' => 'PAY-M23F-001', 'status' => 'paid', 'amount_minor' => 250000, 'outstanding_minor' => 0, 'currency' => 'CZK', 'due_on' => '2026-01-31', 'paid_on' => '2026-01-15']]],
            ['kis_external_id' => 'KIS-M23D-002', '_kis_external_id_raw' => 'KIS-M23D-002', 'jmeno' => 'Localhost', 'prijmeni' => 'PreviewTwo', 'narozeni' => '2013-02-02', 'uciid' => 'M23-SYNTH-002', '_soupisky_parsed' => ['Testovaci soupiska'], 'kis_soupisky' => 'Testovaci soupiska', 'kis_aktivni' => 1, 'kis_platebne_aktivni' => 1, '_kis_payment' => ['paid_rows' => 1, 'open_rows' => 0], '_kis_payment_rows' => [['payment_external_id' => 'PAY-M23F-002', 'status' => 'paid', 'amount_minor' => 180000, 'outstanding_minor' => 0, 'currency' => 'CZK', 'due_on' => '2026-01-31', 'paid_on' => '2026-01-20']]],
        ],
        [
            'users' => ['contract' => 'm23d-synthetic-v1', 'headers' => ['kisid', 'jmeno', 'prijmeni', 'datumnarozeni'], 'rows' => 2],
            'payments' => ['contract' => 'm23d-synthetic-v1', 'headers' => ['kisid', 'idplatby', 'stav', 'castka', 'datumuhrady'], 'rows' => 2],
            'soupisky' => ['contract' => 'm23d-synthetic-v1', 'headers' => ['kisid', 'soupiska', 'jmeno', 'prijmeni'], 'rows' => 2],
        ],
        [],
        ['users' => 'm23d-field-users.csv', 'payments' => 'm23d-field-payments.csv', 'rosters' => 'm23d-field-rosters.csv'],
        null,
        $artifactIds
    );
    $run = $pdo->query('SELECT * FROM kis_import_runs WHERE id=' . $runId)->fetch(PDO::FETCH_ASSOC);
    $report = kisImportStoredPreviewReport($run);
    $fieldReport = kisFieldContractStoredReport($run);
    $parityReport = kisImportStoredParityReport($run);
    if ($report === null || $fieldReport === null || $parityReport === null) {
        throw new RuntimeException('Uložený preview report neprošel kontrolou fingerprintu.');
    }
    $respond([
        'status' => 'created',
        'run_id' => $runId,
        'preview_status' => (string)$report['status'],
        'field_contract_status' => (string)$fieldReport['status'],
        'parity_status' => (string)$parityReport['status'],
        'parity_blockers' => (int)$parityReport['summary']['total_blockers'],
        'staged_payment_prescriptions' => (int)$parityReport['domains']['payment_prescriptions']['staged_rows'],
        'fingerprint' => (string)$report['fingerprint'],
        'url' => 'http://localhost/evidencePavel/kis_sync_center.php?run_id=' . $runId,
    ]);
} catch (Throwable $exception) {
    error_log('seed-kis-m23-preview.php: ' . $exception->getMessage());
    $respond(['status' => 'error', 'error' => 'M2.3 demo preview se nepodařilo bezpečně připravit.'], 1);
}
