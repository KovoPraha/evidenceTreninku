<?php

declare(strict_types=1);

require_once __DIR__ . '/QueryProfilePdo.php';
require_once __DIR__ . '/TrainingDayChoicesBaseline.php';

function trainingDayChoicesSeed(PDO $pdo, int $count): void
{
    $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,aktivni INTEGER,jmeno VARCHAR(100))');
    $pdo->exec('CREATE TABLE staff_user_positions(trainer_id INTEGER,position_code VARCHAR(40))');
    $pdo->exec('CREATE TABLE staff_superadmins(trainer_id INTEGER)');
    $pdo->exec('CREATE TABLE planovane_treninky(id INTEGER PRIMARY KEY,trener_id INTEGER,datum VARCHAR(10),cas_od VARCHAR(8),cas_do VARCHAR(8),nazev VARCHAR(200),poznamka TEXT,trenink_id INTEGER)');
    $pdo->exec("INSERT INTO treneri VALUES(1,1,'LOCALHOST coach'),(2,1,'LOCALHOST lead'),(3,1,'LOCALHOST other'),(4,1,'LOCALHOST super'),(5,0,'LOCALHOST inactive')");
    $pdo->exec("INSERT INTO staff_user_positions VALUES(1,'coach'),(2,'sports_lead'),(3,'coach'),(5,'coach')");
    $pdo->exec('INSERT INTO staff_superadmins VALUES(4)');
    $q = $pdo->prepare('INSERT INTO planovane_treninky VALUES(?,?,?,?,?,?,?,?)');
    for ($i = 1; $i <= $count; $i++) {
        $q->execute([$i, $i % 3 + 1, $i % 5 === 0 ? '2026-11-06' : '2026-11-07',
            $i % 2 === 0 ? '15:00' : '16:00', '17:00', $i % 7 === 0 ? 'Žlutý kurz_% ' . $i : 'Synthetic lesson ' . $i,
            'LOCALHOST NEPLATIT', $i % 4 === 0 ? $i : null]);
    }
}

function trainingDayChoicesSession(int $actor, string $position): void
{
    $_SESSION = ['trener_id' => $actor, 'staff_active_position' => $position,
        'staff_positions' => [$position], 'staff_is_superadmin' => $actor === 4];
}
