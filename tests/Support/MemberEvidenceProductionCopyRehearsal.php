<?php

declare(strict_types=1);

// This entry point can mutate only a disposable loopback database with an exact name.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__, 2) . '/includes/member_evidence_cutover.php';
require_once dirname(__DIR__, 2) . '/includes/migration_runner.php';

$phase = 'target';
try {
    $mode = $argv[1] ?? '';
    $directory = realpath((string) getenv('MEMBER_REHEARSAL_DIRECTORY'));
    $port = (string) getenv('MEMBER_REHEARSAL_PORT');
    if (getenv('MEMBER_REHEARSAL_CONFIRM') !== 'ISOLATED-COPY-ONLY'
        || !in_array($mode, ['rehearse', 'verify-restore'], true)
        || $directory === false || !ctype_digit($port) || (int) $port < 1024 || (int) $port > 65535) {
        throw new RuntimeException('Invalid isolated target.');
    }
    $pdo = new PDO(
        'mysql:host=127.0.0.1;port=' . $port . ';dbname=kis_member_rehearsal;charset=utf8mb4',
        'root',
        (string) getenv('MEMBER_REHEARSAL_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false]
    );
    $phase = 'snapshot_before';
    $before = memberEvidenceSnapshot($pdo);
    $snapshotPath = $directory . '/before.json';
    if ($mode === 'verify-restore') {
        $expected = json_decode((string) file_get_contents($snapshotPath), true, 512, JSON_THROW_ON_ERROR);
        if ($before !== $expected) {
            throw new RuntimeException('Restored database differs from the original backup.');
        }
        echo json_encode(['restore_parity' => true, 'tables' => count($before)], JSON_THROW_ON_ERROR) . PHP_EOL;
        exit(0);
    }
    if (file_exists($snapshotPath)) {
        throw new RuntimeException('Rehearsal output must be new.');
    }
    file_put_contents($snapshotPath, json_encode($before, JSON_THROW_ON_ERROR), LOCK_EX);
    chmod($snapshotPath, 0600);

    $phase = 'upgrade';
    $root = dirname(__DIR__, 2);
    $catalog = EvidenceMigrationCatalog::load($root . '/migrations');
    $legacyApplier = static function (PDO $connection) use ($root): void {
        $pdo = $connection;
        require $root . '/includes/auto_migrace.php';
    };
    $runner = new EvidenceMigrationRunner($pdo, $catalog, $legacyApplier);
    $upgrade = $runner->apply();
    if (!($upgrade['current'] ?? false) || !($runner->check()['current'] ?? false)) {
        throw new RuntimeException('Upgrade did not reach the current schema.');
    }

    $phase = 'copy_only_mapping';
    $rows = $pdo->query("SELECT id,name,activity_kind FROM club_teams WHERE season_id IS NOT NULL AND status='active' ORDER BY id")
        ->fetchAll();
    $teams = [];
    foreach ($rows as $row) {
        if (!in_array($row['activity_kind'], ['circle', 'competitive', 'other'], true)) {
            throw new RuntimeException('Explicit activity classification is missing.');
        }
        // Deliberately not a proposed production code/name map. Never publish this manifest.
        $teams[] = ['id' => (int) $row['id'], 'standing_code' => 'REHEARSAL-' . $row['id'],
            'name' => $row['name'], 'activity_kind' => $row['activity_kind']];
    }
    if ($teams === []) {
        throw new RuntimeException('No legacy active rosters to rehearse.');
    }
    $actor = (int) $pdo->query("SELECT t.id FROM treneri t WHERE t.aktivni=1 AND ("
        . "EXISTS(SELECT 1 FROM staff_user_positions p WHERE p.trainer_id=t.id AND p.position_code='registrar') "
        . 'OR EXISTS(SELECT 1 FROM staff_superadmins a WHERE a.trainer_id=t.id)) ORDER BY t.id LIMIT 1')->fetchColumn();
    if ($actor < 1) {
        throw new RuntimeException('No existing registrar in the isolated copy.');
    }
    $config = ['scope' => 'application_backup', 'cutover_on' => '2026-11-02', 'reason' => 'ISOLATED COPY REHEARSAL; opening and cleanup approved 2026-11-02',
        'teams' => $teams, 'delete' => []];
    $phase = 'opening_candidate';
    $config = memberEvidencePrepareOpening($pdo, $config, 435, 560);
    $phase = 'preview';
    $stable = memberEvidenceSnapshot($pdo);
    $manifest = memberEvidencePreview($pdo, $config);
    if ($stable !== memberEvidenceSnapshot($pdo)) {
        throw new RuntimeException('Preview changed the isolated copy.');
    }
    $backup = $directory . '/source.sql.gz';
    if (!is_file($backup) || filesize($backup) < 1) {
        throw new RuntimeException('Verified source backup is missing.');
    }
    $phase = 'apply';
    $result = memberEvidenceApply($pdo, $manifest, $manifest['fingerprint'], $actor, hash_file('sha256', $backup), true);
    $phase = 'replay';
    $replay = memberEvidenceApply($pdo, $manifest, $manifest['fingerprint'], $actor, hash_file('sha256', $backup), true);
    if (!$result['preserved_parity'] || !($replay['idempotent'] ?? false)) {
        throw new RuntimeException('Cutover or replay parity failed.');
    }
    echo json_encode(
        ['contract' => 'member-evidence-copy-rehearsal-v1', 'isolated_copy_only' => true,
        'source_backup_sha256' => hash_file('sha256', $backup), 'source_tables' => count($before),
        'upgraded_tables' => count($stable), 'converted_teams' => count($teams),
        'opening_on' => $result['opening_on'], 'opening_count' => $result['opening_count'],
        'deleted' => $result['deleted'], 'detached_claim_count' => $result['detached_claim_count'],
        'final_sync_pending' => $result['final_sync_pending'],
        'preview_read_only' => true, 'preserved_parity' => true, 'idempotent' => true,
        'production_mapping_approved' => false, 'attachments_verified' => false],
        JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT
    ) . PHP_EOL;
} catch (Throwable $error) {
    $failure = ['ok' => false, 'phase' => $phase, 'type' => get_class($error)];
    if ($error instanceof PDOException) {
        $failure['sqlstate'] = $error->errorInfo[0] ?? $error->getCode();
        $failure['driver_code'] = $error->errorInfo[1] ?? null;
    }
    // No exception text or individual row values from the production backup in CI logs.
    fwrite(STDERR, json_encode($failure, JSON_THROW_ON_ERROR) . PHP_EOL);
    exit(1);
}
