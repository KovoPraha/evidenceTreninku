<?php

declare(strict_types=1);

require_once __DIR__ . '/FamilyOverviewPageFixture.php';
require_once __DIR__ . '/ClubCalendarReadBaseline.php';

$schema = 'w84_calendar_' . bin2hex(random_bytes(8));
$admin = w72Connection();
$admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try {
    $pdo = w72Connection($schema, true);
    // Legacy vehicle table must exist before the catalog so its optional FK is installed.
    $pdo->exec('CREATE TABLE ucto_vozidla(id INT PRIMARY KEY,znacka_model VARCHAR(100),spz VARCHAR(30)) ENGINE=InnoDB');
    w72Seed($pdo);
    $pdo->exec("INSERT INTO account_person_roles(account_id,sportovec_id,relation_role,status,valid_from,created_by_trainer_id,decision_note)"
        . " VALUES(10,1,'guardian','approved','2020-01-01',7,'Synthetic')");
    $pdo->exec("INSERT INTO ucto_vozidla VALUES(1,'Synthetic vehicle','TEST')");
    $inserted = 0;
    $results = [];
    foreach ([0, 1, 12, 52] as $weeks) {
        for ($week = $inserted; $week < $weeks; $week++) {
            $date = (new DateTimeImmutable('2026-01-01'))->modify('+' . ($week * 7) . ' days')->format('Y-m-d');
            foreach (['public', 'members', 'staff', 'rosters'] as $visibility) {
                $id = clubCalendarSaveEvent($pdo, 7, 0, ['name' => 'Synthetic', 'activity_kind' => 'race',
                    'planning_status' => 'planned', 'visibility' => $visibility, 'starts_at' => $date . 'T09:00',
                    'ends_at' => $date . 'T17:00', 'location' => 'Test', 'internal_note' => 'PRIVATE_SENTINEL', 'capacity' => 20,
                    'participant_fee_minor' => 0, 'fee_due_days' => 14, 'team_ids' => [1]])['id'];
                $pdo->prepare("INSERT INTO club_event_sessions(event_id,starts_at,ends_at,location,capacity_override,status) VALUES(?,?,?,'Test',7,'scheduled')")
                    ->execute([$id, $date . ' 18:00:00', $date . ' 19:00:00']);
                clubCalendarAddPerson($pdo, $id, 7, ['trainer_id' => 7, 'visible_to_members' => 1, 'external_contact' => 'PRIVATE_SENTINEL', 'note' => 'PRIVATE_SENTINEL']);
                clubCalendarAddPerson($pdo, $id, 7, ['external_name' => 'PRIVATE_SENTINEL']);
                clubCalendarAddLink($pdo, $id, 7, ['label' => 'Map', 'url' => 'https://example.test/map']);
                clubCalendarReserveVehicle($pdo, $id, 7, ['vehicle_id' => 1, 'starts_at' => $date . 'T09:00',
                    'ends_at' => $date . 'T17:00', 'conflict_acknowledged' => 1, 'conflict_note' => 'Synthetic']);
            }
        }
        $inserted = $weeks;
        foreach (['staff' => [null, true], 'public' => [null, false], 'family' => [10, false]] as $role => [$account, $staff]) {
            $counts = [];
            $hash = null;
            foreach (['baseline', 'current'] as $mode) {
                $pdo->profile = [];
                $pdo->profiling = true;
                $value = $mode === 'baseline' ? w84BaselineClubCalendarEvents($pdo, '2026-01-01', '2026-12-31', $account, $staff)
                    : clubCalendarEvents($pdo, '2026-01-01', '2026-12-31', $account, $staff);
                $pdo->profiling = false;
                $serialized = json_encode($value, JSON_THROW_ON_ERROR);
                $hash ??= hash('sha256', $serialized);
                if ($hash !== hash('sha256', $serialized) || (!$staff && str_contains($serialized, 'PRIVATE_SENTINEL'))) {
                    throw new RuntimeException('Calendar changed or leaked private fields.');
                }
                $counts[$mode] = ['sql' => count($pdo->profile), 'rows' => array_sum(array_column($pdo->profile, 'fetched_rows')),
                    'link_sql' => count(array_filter($pdo->profile, static fn (array $q): bool => str_contains($q['sql'], 'FROM club_event_links')))];
            }
            if ($counts['current']['sql'] > $counts['baseline']['sql'] || $counts['current']['link_sql'] > 1) {
                throw new RuntimeException('Calendar query budget failed.');
            }
            $returned = count($value);
            unset($value, $serialized);
            $pdo->profile = [];
            $samples = ['baseline' => [], 'current' => []];
            $plain = w72Connection($schema);
            for ($iteration = -1; $iteration < 5; $iteration++) {
                foreach ($iteration % 2 === 0 ? ['baseline', 'current'] : ['current', 'baseline'] as $mode) {
                    memory_reset_peak_usage();
                    $memory = memory_get_usage();
                    $start = hrtime(true);
                    $value = $mode === 'baseline' ? w84BaselineClubCalendarEvents($plain, '2026-01-01', '2026-12-31', $account, $staff)
                        : clubCalendarEvents($plain, '2026-01-01', '2026-12-31', $account, $staff);
                    $elapsed = (hrtime(true) - $start) / 1e6;
                    $peak = memory_get_peak_usage() - $memory;
                    if ($hash !== hash('sha256', json_encode($value, JSON_THROW_ON_ERROR))) {
                        throw new RuntimeException('Timed calendar changed.');
                    }
                    unset($value);
                    if ($iteration >= 0) {
                        $samples[$mode][] = ['ms' => $elapsed, 'incremental_peak_bytes' => $peak];
                    }
                }
            }
            $median = static function (array $values): float {
                sort($values);
                return $values[intdiv(count($values), 2)];
            };
            $peaks = array_map(static fn (array $values): float => $median(array_column($values, 'incremental_peak_bytes')), $samples);
            if ($peaks['current'] > $peaks['baseline'] + 65536) {
                throw new RuntimeException('Calendar memory regressed: ' . json_encode([$weeks, $role, $peaks]));
            }
            $results[] = ['weeks' => $weeks, 'role' => $role, 'same_data' => true, 'returned' => $returned, 'counts' => $counts,
                'sha256' => $hash, 'samples' => $samples, 'median_peak_bytes' => $peaks,
                'median_ms' => array_map(static fn (array $values): float => $median(array_column($values, 'ms')), $samples)];
        }
    }
    $pdo->exec("INSERT INTO club_event_planned_participants(event_id,sportovec_id,status,created_by_trainer_id) "
        . "SELECT MIN(id),1,'CONFIRMED',7 FROM club_events");
    $pdo->exec("UPDATE club_event_vehicle_reservations SET status='cancelled' ORDER BY id LIMIT 1");
    $pdo->exec("UPDATE club_event_vehicle_reservations SET starts_at=ends_at ORDER BY id DESC LIMIT 1");
    $expected = w84BaselineClubCalendarEvents($pdo, '2026-01-01', '2026-12-31', null, true);
    $actual = clubCalendarEvents($pdo, '2026-01-01', '2026-12-31', null, true);
    if ($expected !== $actual || $actual[0]['registration_count'] !== 1) {
        throw new RuntimeException('Native collation or changed vehicle contract failed.');
    }
    echo json_encode(['database_version' => $pdo->query('SELECT VERSION()')->fetchColumn(), 'migrated_schema' => true,
        'foreign_keys' => (int) $pdo->query('SELECT @@foreign_key_checks')->fetchColumn(), 'production_mutations' => false,
        'results' => $results, 'native_changed_state_contract' => true], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $admin->exec('DROP DATABASE `' . $schema . '`');
}
