<?php
declare(strict_types=1);

$database = getenv('EVIDENCE_TRANSITION_SMOKE_DB') ?: 'evidence_m19_transition_test';
if (preg_match('/\Aevidence_m19_transition_test_[a-z0-9_]+\z/', $database) !== 1
    && $database !== 'evidence_m19_transition_test'
) {
    throw new RuntimeException('Refusing to use a non-test MariaDB database name.');
}

$server = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$quotedDatabase = '`' . str_replace('`', '``', $database) . '`';
$server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase);
$server->exec('CREATE DATABASE ' . $quotedDatabase . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=' . $database . ';charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('CREATE TABLE treneri(id INT NOT NULL PRIMARY KEY,jmeno VARCHAR(100)) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE sportovci(id INT NOT NULL PRIMARY KEY,jmeno VARCHAR(100),prijmeni VARCHAR(100),narozeni DATE,uciid VARCHAR(64),stav_clenstvi VARCHAR(30)) ENGINE=InnoDB');
    $pdo->exec("INSERT INTO treneri VALUES(7,'Admin')");
    $pdo->exec("INSERT INTO sportovci VALUES(10,'Anna','Test','2012-05-01','UCI-10','aktivni')");
    foreach (['20260804090000_kis_teams_rosters.php','20260804110000_kis_roster_policies.php','20260804170000_kis_roster_rollover_execution.php'] as $file) {
        $migration = require dirname(__DIR__, 2) . '/migrations/' . $file;
        $migration['up']($pdo);
        if (!$migration['verify']($pdo)) throw new RuntimeException('MariaDB migration verification failed: ' . $file);
    }
    require_once dirname(__DIR__, 2) . '/includes/kis_hobby_transition.php';
    $school = kisRosterCreateSeason($pdo,7,'SCHOOL-MDB','Školní rok','2026-09-01','2027-08-31','school_year');
    $raceSeason = kisRosterCreateSeason($pdo,7,'RACE-MDB','Závodní 2027','2027-01-01','2027-12-31','calendar_year');
    $hobbySeries = kisRosterCreateSeries($pdo,7,'HOBBY-MDB','Kroužek','hobby','school_year','renewal_required');
    $raceSeries = kisRosterCreateSeries($pdo,7,'U15-MDB','U15','age','calendar_year','manual',null,15,15);
    $hobby = kisRosterCreateSeriesTeam($pdo,(int)$hobbySeries['id'],(int)$school['id'],7,'HOBBY-MDB-T','Kroužek','Obecná','Mix','Smoke.');
    $race = kisRosterCreateSeriesTeam($pdo,(int)$raceSeries['id'],(int)$raceSeason['id'],7,'U15-MDB-T','U15 závodní','Silnice','U15','Smoke.');
    $member = kisRosterAddMember($pdo,(int)$hobby['id'],10,7,'manual','2026-09-01','Smoke.');
    $preview = kisHobbyTransitionPreview($pdo,(int)$member['id'],(int)$race['id'],'2027-01-15',true);
    $result = kisHobbyTransitionExecute($pdo,(int)$member['id'],(int)$race['id'],'2027-01-15',true,7,'MariaDB smoke.',true,$preview['fingerprint']);
    $again = kisHobbyTransitionExecute($pdo,(int)$member['id'],(int)$race['id'],'2027-01-15',true,7,'MariaDB smoke repeat.',true,$preview['fingerprint']);
    if ($result['idempotent'] || !$again['idempotent']) throw new RuntimeException('MariaDB idempotency smoke failed.');
    if ((int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn() !== 2) throw new RuntimeException('MariaDB duplicate protection smoke failed.');
    echo "MariaDB hobby transition smoke OK\n";
} finally {
    $server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase);
}
