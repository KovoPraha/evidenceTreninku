<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/** Minimal synthetic schema also permits malformed legacy chains for parity tests. */
final class MedicalStateFixture
{
    public static function seed(PDO $pdo, int $watches = 240): void
    {
        $pdo->exec('CREATE TABLE sportovci(id INT PRIMARY KEY,jmeno VARCHAR(100),prijmeni VARCHAR(100));
            CREATE TABLE medical_clearance_watches(id INT PRIMARY KEY,sportovec_id INT,purpose VARCHAR(100),next_review_on DATE);
            CREATE INDEX medical_state_person ON medical_clearance_watches(sportovec_id);
            CREATE TABLE medical_clearance_records(id INT PRIMARY KEY,watch_id INT,state VARCHAR(20),issued_on DATE NOT NULL,
                valid_until DATE NOT NULL,replaces_id INT,original_location VARCHAR(100));
            CREATE INDEX medical_state_owner ON medical_clearance_records(watch_id,id)');
        $person = $pdo->prepare("INSERT INTO sportovci VALUES(?,'LOCALHOST Synthetic',?)");
        $watch = $pdo->prepare('INSERT INTO medical_clearance_watches VALUES(?,?,?,?)');
        $record = $pdo->prepare("INSERT INTO medical_clearance_records VALUES(?,?,?,?,?,?,'PRIVATE MEDICAL SENTINEL')");
        $today = new \DateTimeImmutable('today');
        $day = static fn (int $n): string => $today->modify(($n < 0 ? '' : '+') . $n . ' days')->format('Y-m-d');
        $pdo->beginTransaction();
        for ($i = 1; $i <= $watches; $i++) {
            $person->execute([$i, $i % 2 === 0 ? 'Žáková 100%_!' : 'Example']);
            $watch->execute([$i, $i, 'Synthetic purpose ' . $i, $day($i % 4)]);
            $kind = $i % 12;
            $base = $i * 10;
            if ($kind === 0) {
                continue;
            }
            $record->execute([$base, $i, 'superseded', $day(-30), $day(30), null]);
            if ($kind <= 7) {
                $state = [1 => 'verified', 2 => 'verified', 3 => 'verified', 4 => 'pending',
                    5 => 'revoked', 6 => 'VERIFIED', 7 => 'verified '][$kind];
                $record->execute([$base + 1, $i, $state, $day($kind === 3 ? 1 : -1), $day($kind === 2 ? -1 : 1), null]);
            } elseif ($kind === 8) {
                $record->execute([$base + 1, $i, 'verified', $day(1), $day(30), $base]);
            } elseif ($kind === 9) {
                $record->execute([$base + 1, $i, 'verified', $day(-1), $day(1), null]);
                $record->execute([$base + 2, $i, 'pending', $day(-1), $day(1), null]);
            } elseif ($kind === 10) {
                $record->execute([$base + 1, $i, 'verified', $day(-1), $day(1), null]);
                $record->execute([$base + 2, $i, 'verified', $day(0), $day(0), null]);
                $record->execute([$base + 3, $i, 'revoked', $day(-1), $day(1), null]);
            } else {
                $record->execute([$base + 1, $i, 'rejected', $day(-1), $day(1), null]);
            }
        }
        $pdo->commit();
    }
}
