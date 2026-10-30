<?php

declare(strict_types=1);

require_once __DIR__ . '/FamilyOverviewPageFixture.php';
require_once dirname(__DIR__, 2) . '/includes/training_closure.php';
require_once dirname(__DIR__, 2) . '/includes/medical_clearance.php';

$schema = 'w85_assessment_' . bin2hex(random_bytes(8));
$admin = w72Connection();
$admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try {
    $pdo = w72Connection($schema, true);
    w72Seed($pdo);
    $pdo->exec("INSERT IGNORE INTO staff_user_positions(trainer_id,position_code) VALUES(7,'sports_lead'),(7,'registrar')");
    $pdo->exec('CREATE TABLE IF NOT EXISTS trenink_trener(trenink_id INT NOT NULL,trener_id INT NOT NULL,PRIMARY KEY(trenink_id,trener_id)) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE sportovec_obdobi(id INT PRIMARY KEY,sportovec_id INT NOT NULL,datum_od VARCHAR(10),datum_do VARCHAR(10),'
        . 'vyplaceno INT,pocet_treninku INT,castka_celkem DECIMAL(10,2),INDEX(sportovec_id,datum_od),'
        . 'FOREIGN KEY(sportovec_id) REFERENCES sportovci(id)) ENGINE=InnoDB');
    $person = $pdo->prepare("INSERT IGNORE INTO sportovci(id,jmeno,prijmeni,narozeni) VALUES(?,'Synthetic','Assessment','2010-01-01')");
    $reward = $pdo->prepare("INSERT INTO sportovec_obdobi VALUES(?,?,'2026-01-01','2026-01-31',1,8,1200)");
    $pdo->beginTransaction();
    for ($id = 1; $id <= 1000; $id++) {
        $person->execute([$id]);
        if ($id <= 200) {
            $reward->execute([$id, $id]);
        }
    }
    $pdo->commit();
    $_SESSION = ['trener_id' => 7, 'staff_positions' => ['sports_lead'], 'staff_active_position' => 'sports_lead'];
    $closures = [];
    foreach ([10, 50, 200] as $size) {
        $id = 10000 + $size;
        $pdo->prepare("INSERT INTO treninky(id,datum,napln,delka) VALUES(?,'2026-01-02','Synthetic',1)")->execute([$id]);
        $input = ['actual_started_at' => '2026-01-02 15:00:00', 'actual_ended_at' => '2026-01-02 16:00:00',
            'participant_ids' => range(1, $size), 'coach_ids' => [7], 'reason' => 'Synthetic measured closure',
            'confirmed' => true, 'record_source' => 'paper'];
        $pdo->profile = [];
        $pdo->profiling = true;
        $result = trainingClosureClose($pdo, $id, 0, $input, 7);
        $pdo->profiling = false;
        $reads = array_filter($pdo->profile, static fn (array $q): bool => str_starts_with(ltrim($q['sql']), 'SELECT'));
        $writes = count($pdo->profile) - count($reads);
        if (count($reads) > 32 + 3 * $size || $writes !== 6 + 2 * $size) {
            throw new RuntimeException('Closure query growth changed.');
        }
        $pdo->profile = [];
        $plain = w72Connection($schema);
        $samples = [];
        for ($iteration = 0; $iteration < 6; $iteration++) {
            memory_reset_peak_usage();
            $memory = memory_get_usage();
            $start = hrtime(true);
            $next = trainingClosureClose($plain, $id, $iteration + 1, $input, 7);
            $sample = ['ms' => (hrtime(true) - $start) / 1e6, 'incremental_peak_bytes' => memory_get_peak_usage() - $memory];
            if ($next['participant_ids'] !== range(1, $size) || !$next['review_required'] || $next['version'] !== $iteration + 2) {
                throw new RuntimeException('Closure snapshot changed.');
            }
            if ($iteration > 0) {
                $samples[] = $sample;
            }
        }
        $first = $pdo->prepare('SELECT snapshot_json FROM training_closure_revisions WHERE id=?');
        $first->execute([$result['revision_id']]);
        if (json_decode($first->fetchColumn(), true, 512, JSON_THROW_ON_ERROR)['participant_ids'] !== range(1, $size)
            || (int) $pdo->query('SELECT COUNT(*) FROM sportovec_obdobi WHERE pocet_treninku<>8 OR castka_celkem<>1200')->fetchColumn() !== 0) {
            throw new RuntimeException('Historical snapshot/reward mutated.');
        }
        $closures[] = ['participants' => $size, 'read_sql' => count($reads), 'write_sql' => $writes,
            'reward_locks' => count(array_filter($reads, static fn (array $q): bool => str_contains($q['sql'], 'FROM sportovec_obdobi'))),
            'samples' => $samples, 'immutable_snapshot_and_rewards' => true];
    }
    $_SESSION = ['trener_id' => 7, 'staff_positions' => ['registrar'], 'staff_active_position' => 'registrar'];
    $policy = medicalClearancePolicyCreate($pdo, ['purpose' => 'Synthetic sport', 'legal_basis' => 'Synthetic only',
        'retention_rule' => 'Synthetic test lifetime', 'activity_rule' => 'Human review', 'reminder_days' => 30, 'confirmed' => '1']);
    $watch = $pdo->prepare("INSERT INTO medical_clearance_watches(id,sportovec_id,purpose,policy_id,next_review_on,created_by) VALUES(?,?,'Synthetic sport',?,'2026-12-31',7)");
    $record = $pdo->prepare("INSERT INTO medical_clearance_records(watch_id,issued_on,valid_until,original_location,storage_key,state,recorded_by) "
        . "VALUES(?,'2020-01-01','2099-12-31','PRIVATE_SENTINEL','PRIVATE_SENTINEL','verified',7)");
    $medical = [];
    $inserted = 0;
    foreach ([0, 100, 1000] as $size) {
        $pdo->beginTransaction();
        for ($id = $inserted + 1; $id <= $size; $id++) {
            $watch->execute([$id, $id, $policy]);
            $record->execute([$id]);
        }
        $pdo->commit();
        $inserted = $size;
        $pdo->profile = [];
        $pdo->profiling = true;
        $value = medicalClearanceList($pdo, []);
        $pdo->profiling = false;
        $sql = count($pdo->profile);
        $fetched = array_sum(array_column($pdo->profile, 'fetched_rows'));
        $pdo->profile = [];
        $plain = w72Connection($schema);
        memory_reset_peak_usage();
        $memory = memory_get_usage();
        $start = hrtime(true);
        $again = medicalClearanceList($plain, []);
        $elapsed = (hrtime(true) - $start) / 1e6;
        $peak = memory_get_peak_usage() - $memory;
        if ($sql !== 2 || $value !== $again || $again['total'] !== $size || count($again['rows']) !== min(25, $size)
            || str_contains(json_encode($again, JSON_THROW_ON_ERROR), 'PRIVATE_SENTINEL') || $peak > 16 * 1024 * 1024) {
            throw new RuntimeException('Medical list budget/privacy failed.');
        }
        $medical[] = ['watches' => $size, 'sql' => $sql, 'fetched_rows' => $fetched, 'returned' => count($again['rows']),
            'ms' => $elapsed, 'incremental_peak_bytes' => $peak, 'private_fields_absent' => true];
    }
    $pdo->exec("UPDATE medical_clearance_records SET state='superseded' WHERE watch_id=1");
    $pdo->beginTransaction();
    for ($i = 0; $i < 1000; $i++) {
        $record->execute([1]);
    }
    $pdo->commit();
    $pdo->exec("UPDATE medical_clearance_records SET state='superseded' WHERE watch_id=1");
    $pdo->exec("UPDATE medical_clearance_records SET state='verified' WHERE watch_id=1 ORDER BY id DESC LIMIT 1");
    memory_reset_peak_usage();
    $memory = memory_get_usage();
    $start = hrtime(true);
    $history = medicalClearanceList($pdo, ['person_id' => 1]);
    $historyProfile = ['ms' => (hrtime(true) - $start) / 1e6, 'incremental_peak_bytes' => memory_get_peak_usage() - $memory];
    if ($history['total'] !== 1 || $history['rows'][0]['evaluated_status']['status'] !== 'valid'
        || $historyProfile['incremental_peak_bytes'] > 4 * 1024 * 1024) {
        throw new RuntimeException('Medical history failed.');
    }
    $pdo->exec("UPDATE medical_clearance_records SET state='revoked' WHERE watch_id=1");
    if (medicalClearanceList($pdo, ['person_id' => 1])['rows'][0]['evaluated_status']['status'] !== 'revoked') {
        throw new RuntimeException('Medical revocation stale.');
    }
    echo json_encode(['database_version' => $pdo->query('SELECT VERSION()')->fetchColumn(), 'migrated_schema' => true,
        'foreign_keys' => (int) $pdo->query('SELECT @@foreign_key_checks')->fetchColumn(), 'production_mutations' => false,
        'closures' => $closures, 'medical' => $medical, 'history_1000_and_fresh_revocation' => true,
        'history_profile' => $historyProfile], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    $_SESSION = [];
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $admin->exec('DROP DATABASE `' . $schema . '`');
}
