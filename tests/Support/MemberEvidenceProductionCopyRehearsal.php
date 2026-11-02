<?php

declare(strict_types=1);

// This entry point can mutate only a disposable loopback database with an exact name.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__, 2) . '/includes/member_evidence_cutover.php';
require_once dirname(__DIR__, 2) . '/includes/migration_runner.php';
require_once dirname(__DIR__, 2) . '/includes/trainer_pilot.php';
require_once dirname(__DIR__, 2) . '/includes/member_evidence_operation.php';

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
    $config = ['scope' => 'application_backup', 'cutover_on' => '2026-11-02', 'reason' => 'ISOLATED COPY REHEARSAL; opening and cleanup approved 2026-11-02',
        'teams' => $teams, 'delete' => []];
    $mappingPath = (string) getenv('MEMBER_REHEARSAL_MAP_FILE');
    $mappingHash = null;
    if ($mappingPath !== '') {
        $phase = 'approved_mapping';
        if (realpath(dirname($mappingPath)) !== $directory || !is_file($mappingPath) || filesize($mappingPath) > 100000) {
            throw new RuntimeException('Private mapping must be in the disposable directory.');
        }
        $config = json_decode((string) file_get_contents($mappingPath), true, 512, JSON_THROW_ON_ERROR);
        if (($config['scope'] ?? '') !== 'application_backup' || ($config['cutover_on'] ?? '') !== '2026-11-02'
            || count($config['teams'] ?? []) !== 44 || count($config['groups'] ?? []) !== 8) {
            throw new RuntimeException('Private mapping does not match the approved scope.');
        }
        foreach ($config['teams'] as $team) {
            if (!isset($team['expected_code'], $team['expected_name'])) {
                throw new RuntimeException('Private mapping must identify the source explicitly.');
            }
        }
        $mappingHash = hash_file('sha256', $mappingPath);
    }
    $phase = 'opening_candidate';
    $config = memberEvidencePrepareOpening($pdo, $config, 435, 560);
    $phase = 'isolated_technical_identity';
    $operation = ['confirmation' => 'PREVEST-CLENSTVI', 'actor' => 'isolated-rehearsal', 'run_id' => '1',
        'workflow_ref' => 'KovoPraha/evidenceTreninku/.github/workflows/member-evidence-production.yml@refs/heads/main'];
    $actor = memberEvidenceOperationActor($pdo, $operation);
    $phase = 'preview';
    $stable = memberEvidenceSnapshot($pdo);
    $pdo->exec('START TRANSACTION READ ONLY');
    $manifest = memberEvidencePreview($pdo, $config);
    $pdo->exec('ROLLBACK');
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
    $phase = 'pilot_scope_after_rename';
    $pilotPreserved = null;
    $pilotRecord = trainerPilotRecord($pdo);
    if (!empty($pilotRecord['operational_authorization'])) {
        $pilotPreserved = trainerPilotStatus($pdo, TRAINER_PILOT_ACTOR_NAME)['scope_ready'];
        if (!$pilotPreserved) {
            throw new RuntimeException('Existing synthetic pilot scope changed.');
        }
    }
    $phase = 'technical_identity_revocation';
    $revoked = memberEvidenceOperationRevoke($pdo, $operation);
    if (!$revoked || (int) $pdo->query('SELECT aktivni FROM treneri WHERE id=' . $actor)->fetchColumn() !== 0
        || (int) $pdo->query('SELECT COUNT(*) FROM staff_user_positions WHERE trainer_id=' . $actor)->fetchColumn() !== 0) {
        throw new RuntimeException('Technical identity was not fully revoked.');
    }
    echo json_encode(
        ['contract' => 'member-evidence-copy-rehearsal-v1', 'isolated_copy_only' => true,
        'source_backup_sha256' => hash_file('sha256', $backup), 'source_tables' => count($before),
        'upgraded_tables' => count($stable), 'converted_teams' => count($teams),
        'created_groups' => $result['created_groups'], 'created_edges' => $result['created_edges'],
        'group_membership_parity' => $result['group_membership_parity'], 'mapping_sha256' => $mappingHash,
        'existing_pilot_scope_preserved' => $pilotPreserved,
        'technical_actor_revoked' => $revoked,
        'opening_on' => $result['opening_on'], 'opening_count' => $result['opening_count'],
        'deleted' => $result['deleted'], 'detached_claim_count' => $result['detached_claim_count'],
        'final_sync_pending' => $result['final_sync_pending'],
        'preview_read_only' => true, 'preserved_parity' => true, 'idempotent' => true,
        'production_mapping_approved' => $mappingHash !== null, 'attachments_verified' => false],
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
