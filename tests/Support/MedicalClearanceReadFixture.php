<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use DateTimeImmutable;

/** LOCALHOST-only mixed-state fixture shared by SQLite regression and owned MariaDB verification. */
final class MedicalClearanceReadFixture
{
    public static function seed(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT,aktivni INTEGER);
            CREATE TABLE staff_user_positions(trainer_id INTEGER,position_code TEXT);
            CREATE TABLE staff_superadmins(trainer_id INTEGER);
            CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT);
            INSERT INTO treneri VALUES(1,\'LOCALHOST Registrar\',1);
            INSERT INTO staff_user_positions VALUES(1,\'registrar\');');
        $m = require dirname(__DIR__, 2) . '/migrations/20261023040000_medical_clearance.php';
        $m['up']($pdo);
        $person = $pdo->prepare('INSERT INTO sportovci VALUES(?,?,?)');
        for ($i = 1; $i <= 57; $i++) {
            $person->execute([$i, 'LOCALHOST ' . $i, $i === 57 ? 'Žáková 100%_!' : 'Osoba']);
        }
        $pdo->exec("INSERT INTO medical_clearance_policies VALUES(1,'LOCALHOST','LOCALHOST','LOCALHOST','LOCALHOST',30,1,CURRENT_TIMESTAMP)");
        $watch = $pdo->prepare('INSERT INTO medical_clearance_watches(id,sportovec_id,purpose,policy_id,next_review_on,created_by) VALUES(?,?,?,1,?,1)');
        $today = new DateTimeImmutable('today');
        $dates = array_map(static fn (string $d): string => $today->modify($d)->format('Y-m-d'), ['-1 day', '+0 days', '+1 day', '+2 days']);
        for ($i = 1; $i <= 431; $i++) {
            $watch->execute([$i, ($i - 1) % 57 + 1, 'LOCALHOST účel ' . $i, $dates[$i % 4]]);
            $kind = $i % 14;
            if ($kind === 0) {
                continue;
            }
            $record = function (string $state, string $issued, string $until, ?int $previous = null) use ($pdo, $i): int {
                $q = $pdo->prepare('INSERT INTO medical_clearance_records(watch_id,issued_on,valid_until,original_location,state,recorded_by,replaces_id)
                    VALUES(?,?,?,\'PRIVATE SECRET CABINET\',?,1,?)');
                $q->execute([$i, $issued, $until, $state, $previous]);
                return (int)$pdo->lastInsertId();
            };
            if ($kind <= 6) {
                $state = [1 => 'pending', 2 => 'verified', 3 => 'verified', 4 => 'verified', 5 => 'revoked', 6 => 'rejected'][$kind];
                $record($state, $kind === 4 ? $dates[2] : $dates[0], $kind === 3 ? $dates[0] : $dates[2]);
            } elseif ($kind <= 9) {
                $previous = $record($kind === 9 ? 'revoked' : 'superseded', $dates[0], $kind === 8 ? $dates[0] : $dates[2]);
                $record('verified', $dates[2], $dates[3], $previous);
            } elseif ($kind === 10) {
                $record('verified', $dates[1], $dates[1]);
                $record('pending', $dates[1], $dates[3]);
            } elseif ($kind === 11) {
                $previous = $record('superseded', $dates[0], $dates[2]);
                $record('revoked', $dates[1], $dates[3], $previous);
            } elseif ($kind === 12) {
                $a = $record('superseded', $dates[0], $dates[2]);
                $b = $record('superseded', $dates[2], $dates[3], $a);
                $record('verified', $dates[3], $dates[3], $b);
            } else {
                $record('superseded', $dates[0], $dates[2]);
                $record('rejected', $dates[1], $dates[3]);
            }
        }
        $_SESSION = ['trener_id' => 1, 'staff_positions' => ['registrar'], 'staff_active_position' => 'registrar'];
    }

    public static function inputs(): array
    {
        $inputs = [[], ['medical_page' => '2'], ['medical_page' => '999999'], ['person_id' => '1'],
            ['medical_q' => 'LOCALHOST účel 23'], ['medical_q' => 'Žáková 100%_!'],
            ['medical_q' => "%' OR 1=1 --"], ['person_id' => '999'],
            ['medical_from' => date('Y-m-d'), 'medical_to' => date('Y-m-d')],
            ['medical_from' => '2026-02-31'], ['medical_state' => 'unknown'],
            ['medical_from' => '2030-01-01', 'medical_to' => '2020-01-01']];
        foreach (['missing', 'pending', 'valid', 'expired', 'not_yet_valid', 'revoked'] as $state) {
            foreach (['1', '2', '999999'] as $page) {
                $inputs[] = ['medical_state' => $state, 'medical_page' => $page];
            }
            $inputs[] = ['medical_state' => $state, 'person_id' => '1', 'medical_q' => 'LOCALHOST'];
        }
        return $inputs;
    }
}
