<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

final class SportsActivityOverviewFixture
{
    public static function seed(PDO $pdo, int $measurements = 100): void
    {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $pdo->exec('CREATE TABLE treneri(id INT PRIMARY KEY,aktivni INT);
            CREATE TABLE sportovci(id INT PRIMARY KEY);
            CREATE TABLE club_events(id ' . ($mysql ? 'BIGINT UNSIGNED' : 'INTEGER') . ' PRIMARY KEY);
            CREATE TABLE treninky(id INT PRIMARY KEY,datum DATE);
            CREATE TABLE trenink_sportovec(trenink_id INT,sportovec_id INT);
            CREATE TABLE trenink_mereni(trenink_id INT,mereni_id INT);
            CREATE TABLE mereni_zaznamy(id INT PRIMARY KEY,sportovec_id INT,typ VARCHAR(50),vzdalenost VARCHAR(50),
                distance_unit VARCHAR(10),distance_meters DECIMAL(12,3),cas VARCHAR(50),duration_ms INT,
                rpe VARCHAR(30),rpe_value DECIMAL(4,1),contract_version VARCHAR(50))');
        $pdo->exec('INSERT INTO treneri VALUES(7,1); INSERT INTO sportovci VALUES(1),(2)');
        foreach (['20261023020000_training_closure', '20261023030000_sports_activity'] as $name) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $name . '.php';
            $migration['up']($pdo);
        }
        $training = $pdo->prepare('INSERT INTO treninky VALUES(?,?)');
        $revision = $pdo->prepare('INSERT INTO training_closure_revisions
            (id,training_id,version,status,actual_date,actual_started_at,actual_ended_at,duration_minutes,
            snapshot_json,reason,closed_by) VALUES(?,?,?, ?,?,?,?,0,?, ?,7)');
        $closure = $pdo->prepare('INSERT INTO training_closures(training_id,current_revision_id,version,status) VALUES(?,?,2,?)');
        $event = $pdo->prepare('INSERT INTO club_events VALUES(?)');
        $participation = $pdo->prepare('INSERT INTO sports_event_participation(id,event_id,sportovec_id,current_revision_id) VALUES(?,?,?,?)');
        $eventRevision = $pdo->prepare('INSERT INTO sports_event_revisions
            (id,participation_id,version,stage,actual_date,snapshot_json,reason,source_reference,actor_trainer_id,request_key,request_hash)
            VALUES(?,?,?, ?,?,?, ?,?,7,?,?)');
        $pdo->beginTransaction();
        for ($i = 1; $i <= 48; $i++) {
            $date = ['2025-12-31', '2026-01-01', '2026-06-15', '2026-12-31', '2027-01-01'][$i % 5];
            $training->execute([$i, $date]);
            foreach ([1, 2] as $version) {
                $snapshot = ['training_id' => $i, 'actual_date' => $date,
                    'duration_minutes' => [null, 0, 60, '45.9', -1][$i % 5],
                    'participant_ids' => $version === 1 || $i % 3 !== 0 ? [1] : [2], 'coach_ids' => [7]];
                $revision->execute([$i * 10 + $version, $i, $version, 'closed', $date, $date . ' 12:00:00',
                    $date . ' 13:00:00', json_encode($snapshot, JSON_THROW_ON_ERROR), 'LOCALHOST synthetic closure']);
            }
            $closure->execute([$i, $i * 10 + 2, ['closed', 'closed', 'draft', 'void'][$i % 4]]);
            $event->execute([$i]);
            $participation->execute([$i, $i, $i % 4 === 0 ? 2 : 1, $i * 10 + 2]);
            foreach ([1, 2] as $version) {
                $stage = $version === 1 ? 'started' : ['nominated', 'accepted', 'registered', 'attended', 'started', 'cancelled'][$i % 6];
                $eventRevision->execute([$i * 10 + $version, $i, $version, $stage, $date,
                    json_encode(['result_status' => $i % 2 ? 'DNF' : 'FINISHED', 'result_value' => null, 'result_unit' => null], JSON_THROW_ON_ERROR),
                    'LOCALHOST synthetic event', 'LOCALHOST source', 'overview-' . $i . '-' . $version, str_repeat('a', 64)]);
            }
        }
        // Raw attendance and measurements do not create verified activities.
        $pdo->exec("INSERT INTO treninky VALUES(99,'2026-01-01'); INSERT INTO trenink_sportovec VALUES(99,1)");
        $measurement = $pdo->prepare('INSERT INTO mereni_zaznamy VALUES(?,1, ?,?,?,NULL,?,NULL,?,NULL,NULL)');
        $link = $pdo->prepare('INSERT INTO trenink_mereni VALUES(99,?)');
        for ($i = 1; $i <= $measurements; $i++) {
            $measurement->execute([$i, 'kolo', '2 okruhy', null, 'rychle', 'pohodově']);
            $link->execute([$i]);
        }
        $pdo->commit();
    }
}
