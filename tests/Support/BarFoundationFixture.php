<?php

declare(strict_types=1);

function barTestDatabase(): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA foreign_keys=ON');
    $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT,role TEXT,aktivni INT,session_version INT,email TEXT,heslo TEXT)');
    $pdo->exec("INSERT INTO treneri(id,jmeno,role,aktivni,session_version) VALUES(1,'LOCALHOST System','trener',1,1),(2,'LOCALHOST Operator','trener',1,1),"
        . "(3,'LOCALHOST Bar admin','trener',1,1),(4,'LOCALHOST Coach','trener',1,1),(5,'LOCALHOST Super','admin',1,1)");
    $pdo->exec('CREATE TABLE jidlo_produkty(id INTEGER PRIMARY KEY,nazev TEXT,sklad INT)');
    $pdo->exec("INSERT INTO jidlo_produkty VALUES(91,'LOCALHOST legacy preserved',34)");
    $staff = require dirname(__DIR__, 2) . '/migrations/20260821150000_staff_workspaces.php';
    $staff['up']($pdo);
    $bar = require dirname(__DIR__, 2) . '/migrations/20261105150000_bar_foundation.php';
    $bar['up']($pdo);
    $transfer = require dirname(__DIR__, 2) . '/migrations/20261105160000_bar_transfer.php';
    $transfer['up']($pdo);
    $stock = require dirname(__DIR__, 2) . '/migrations/20261105170000_bar_stock.php';
    $stock['up']($pdo);
    $catalogManagement = require dirname(__DIR__, 2) . '/migrations/20261105180000_bar_catalog_management.php';
    $catalogManagement['up']($pdo);
    $checkout = require dirname(__DIR__, 2) . '/migrations/20261105190000_bar_checkout.php';
    $checkout['up']($pdo);
    $pdo->exec('DELETE FROM staff_user_positions');
    $pdo->exec("INSERT INTO staff_user_positions(trainer_id,position_code,is_default) VALUES"
        . "(1,'system_admin',1),(2,'bar_operator',1),(3,'bar_admin',1),(4,'coach',1)");
    return $pdo;
}

function barTestLogin(int $id, string $position): void
{
    $_SESSION = ['trener_id' => $id, 'trener_session_version' => 1, 'staff_active_position' => $position];
}
