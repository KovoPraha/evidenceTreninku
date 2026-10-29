<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

require_once dirname(__DIR__, 2) . '/includes/family_portal.php';

/** Reduced legacy schema intentionally permits duplicate and orphaned relation rows. */
function familyPortalReadFixture(PDO $pdo, int $size = 20): void
{
    $pdo->exec('CREATE TABLE verejni_uzivatele(id INT PRIMARY KEY,aktivni INT,email_overeno INT)');
    $pdo->exec('CREATE TABLE sportovci(id INT PRIMARY KEY,jmeno VARCHAR(100),prijmeni VARCHAR(100),narozeni DATE,'
        . 'stav_clenstvi VARCHAR(20),stav_manualni INT,stav_duvod VARCHAR(255),stav_aktualizovan DATETIME)');
    $pdo->exec('CREATE TABLE account_person_roles(id INT PRIMARY KEY,account_id INT,sportovec_id INT,'
        . 'relation_role VARCHAR(24),status VARCHAR(24),valid_from DATETIME,valid_to DATETIME)');
    $pdo->exec('INSERT INTO verejni_uzivatele VALUES(1,1,1),(2,1,1),(3,0,1),(4,1,0)');
    $person = $pdo->prepare("INSERT INTO sportovci VALUES(?,'Synthetic',?,'2010-01-01','archiv',1,'LOCALHOST membership','2026-01-01')");
    $relation = $pdo->prepare("INSERT INTO account_person_roles VALUES(?,1,?,'guardian','approved','2020-01-01',NULL)");
    for ($i = 1; $i <= $size; $i++) {
        $person->execute([$i, sprintf('Person %03d', $i)]);
        $relation->execute([$i, $i]);
    }
}
