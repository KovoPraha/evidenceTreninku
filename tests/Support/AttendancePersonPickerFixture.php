<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

require_once __DIR__ . '/AttendanceReadFixture.php';

final class AttendancePersonPickerFixture
{
    public static function seed(PDO $pdo): void
    {
        AttendanceReadFixture::seed($pdo);
        $pdo->exec("ALTER TABLE sportovci ADD jmeno VARCHAR(100); ALTER TABLE sportovci ADD prijmeni VARCHAR(100);
            CREATE TABLE coach_scope_grants(trainer_id INT,scope_kind VARCHAR(20),scope_id INT,
                revoked_at DATETIME,valid_from DATETIME,valid_to DATETIME);
            INSERT INTO coach_scope_grants VALUES(7,'subgroup',1,NULL,'2020-01-01',NULL);
            CREATE TABLE sportovec_podskupina(sportovec_id INT,podskupina_id INT);
            INSERT INTO sportovec_podskupina VALUES(1150,1)");
        $q = $pdo->prepare('UPDATE sportovci SET jmeno=?,prijmeni=? WHERE id=?');
        foreach (range(1, 1200) as $id) {
            $q->execute(['Anna', sprintf('Osoba%04d', $id), $id]);
        }
    }
}
