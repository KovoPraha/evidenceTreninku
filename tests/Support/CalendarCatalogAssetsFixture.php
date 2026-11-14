<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY') {
    http_response_code(404);
    exit;
}
$output = realpath($argv[1] ?? '');
if ($output === false) {
    throw new RuntimeException('Owned output required.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
require_once dirname(__DIR__, 2) . '/includes/club_calendar.php';
[$runtime, $pdo] = fullHttpEnvironment($output);
$pdo->exec('USE `' . $runtime['schema'] . '`');
$pdo->exec('CREATE TABLE IF NOT EXISTS ucto_vozidla(id INT PRIMARY KEY,znacka_model VARCHAR(100),spz VARCHAR(30)) ENGINE=InnoDB');
$pdo->exec("INSERT IGNORE INTO ucto_vozidla(id,znacka_model,spz) VALUES(1,'LOCALHOST synthetic vehicle','LOCALHOST')");
$ids = [];
foreach (['planned', 'confirmed', 'cancelled', 'completed'] as $i => $status) {
    $day = '2026-06-' . (10 + $i);
    $ids[] = clubCalendarSaveEvent($pdo, 7, 0, [
        'name' => 'LOCALHOST NEPLATIT event ' . $status, 'activity_kind' => $i === 1 ? 'camp' : 'race',
        'planning_status' => $status, 'visibility' => 'staff', 'starts_at' => $day . 'T09:00', 'ends_at' => $day . 'T17:00',
        'location' => 'Synthetic venue', 'internal_note' => 'LOCALHOST synthetic note', 'capacity' => 20,
        'participant_fee_minor' => 0, 'fee_due_days' => 14, 'team_ids' => [1],
    ])['id'];
}
clubCalendarAddPerson($pdo, $ids[0], 7, ['trainer_id' => 7, 'visible_to_members' => 1]);
clubCalendarAddLink($pdo, $ids[0], 7, ['label' => 'Synthetic map', 'url' => 'https://example.invalid/map']);
file_put_contents($output . '/calendar-events.json', json_encode($ids, JSON_THROW_ON_ERROR));
