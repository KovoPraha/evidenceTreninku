<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__.'/ProgramCapacityMigratedFixture.php';
require_once __DIR__.'/QueryProfilePdo.php';
require_once dirname(__DIR__, 2).'/includes/family_portal.php';
require_once __DIR__.'/FamilyOverviewBaseline.php';
function w72Connection(string $database = '', bool $profile = false): PDO
{
    $port = getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306';
    if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) {
        throw new RuntimeException('Invalid localhost port.');
    }
    $dsn = 'mysql:host=127.0.0.1;port='.$port.';charset=utf8mb4'.($database !== '' ? ';dbname='.$database : '');
    $pdo = $profile ? new Tests\Support\QueryProfilePdo($dsn, 'root', '') : new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    return $pdo;
}
function w72LegacyColumns(PDO $pdo, string $table, array $columns): void
{
    $present = $pdo->query('SHOW COLUMNS FROM '.$table)->fetchAll(PDO::FETCH_COLUMN);
    foreach ($columns as $name => $type) {
        if (!in_array($name, $present, true)) {
            $pdo->exec('ALTER TABLE '.$table.' ADD '.$name.' '.$type);
        }
    }
}
function w72Seed(PDO $pdo, ?callable $prepareLegacy = null): void
{
    Tests\Support\programCapacityMigratedSchema($pdo, $prepareLegacy);
    w72LegacyColumns($pdo, 'sportovci', ['stav_clenstvi' => "VARCHAR(20) DEFAULT 'aktivni'",'stav_manualni' => 'INT DEFAULT 1','stav_duvod' => "TEXT NULL",'stav_aktualizovan' => 'DATETIME NULL']);
    w72LegacyColumns(
        $pdo,
        'planovane_treninky',
        ['nazev' => "VARCHAR(100) DEFAULT 'Synthetic training'",
        'cas_od' => "TIME DEFAULT '16:00:00'",
        'cas_do' => "TIME DEFAULT '17:00:00'",
        'popis' => 'TEXT NULL',
        'trener_id' => 'INT NULL',
        'serie_id' => 'INT NULL',
        'trenink_id' => 'INT NULL']
    );
    w72LegacyColumns($pdo, 'treninky', ['datum' => 'DATE NULL','napln' => "TEXT NULL",'delka' => 'DECIMAL(5,2) NULL','kategorie' => "VARCHAR(30) DEFAULT 'draha'"]);
    w72LegacyColumns(
        $pdo,
        'mereni_zaznamy',
        ['sportovec_id' => 'INT NULL',
    'typ' => 'VARCHAR(100) NULL',
    'vzdalenost' => 'VARCHAR(100) NULL',
    'cas' => 'VARCHAR(100) NULL',
    'rpe' => 'VARCHAR(100) NULL']
    );
    w72LegacyColumns($pdo, 'sportovist', ['nazev' => 'VARCHAR(100) NULL']);
    w72LegacyColumns($pdo, 'individualni_lekce', ['sportoviste_id' => 'INT NULL','stav' => "VARCHAR(30) DEFAULT 'aktivni'"]);
    w72LegacyColumns($pdo, 'verejne_rezervace', ['uzivatel_id' => 'INT NULL']);
    foreach (['trenink_sportovec(trenink_id INT NOT NULL,sportovec_id INT NOT NULL,PRIMARY KEY(trenink_id,sportovec_id))',
        'trenink_mereni(trenink_id INT NOT NULL,mereni_id INT NOT NULL,PRIMARY KEY(trenink_id,mereni_id))'] as $definition) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS '.$definition.' ENGINE=InnoDB');
    }
    Tests\Support\programCapacityMigratedSeed($pdo);
    Tests\Support\programCapacityMigratedLoad($pdo, 20, false, false);
    $pdo->exec("UPDATE sportovci SET narozeni='2017-01-01',stav_duvod='LOCALHOST NEPLATIT'");
    $pdo->exec("INSERT INTO sportovci(id,jmeno,prijmeni,narozeni) VALUES(999,'FOREIGN_SENTINEL','PRIVATE_OTHER_FAMILY','2017-01-01')");
    $pdo->exec("INSERT INTO verejni_uzivatele(id,aktivni,email_overeno,registrovan) VALUES(11,1,1,CURRENT_TIMESTAMP)");
    $pdo->exec("INSERT INTO "
        . "account_person_roles(account_id,sportovec_id,relation_role,status,valid_from,created_by_trainer_id,decision_note) "
        . "VALUES(11,999,'guardian','approved','2020-01-01',7,'Synthetic')");
    $pdo->exec("INSERT INTO planovane_treninky(id,datum,trener_id,popis) VALUES(100,DATE_ADD(CURRENT_DATE,INTERVAL 2 DAY),7,'Synthetic plan')");
    $pdo->exec("INSERT INTO treninky(id,datum,napln,delka) VALUES(101,CURRENT_DATE,'Synthetic history',1.5)");
    $pdo->exec("INSERT INTO "
        . "training_roster_links(plan_id,team_id,target_date,team_code_snapshot,team_name_snapshot,created_by_trainer_id) "
        . "VALUES(100,1,DATE_ADD(CURRENT_DATE,INTERVAL 2 DAY),'SYN','Synthetic roster',7)");
    $member = $pdo->prepare("INSERT INTO club_roster_members(team_id,sportovec_id,status,source,valid_from,created_by_trainer_id) "
        . "VALUES(1,?,'active','manual','2020-01-01',7)");
    for ($i = 1;$i <= 20;$i++) {
        $member->execute([$i]);
        $pdo->prepare('INSERT INTO trenink_sportovec VALUES(101,?)')->execute([$i]);
    }
}
