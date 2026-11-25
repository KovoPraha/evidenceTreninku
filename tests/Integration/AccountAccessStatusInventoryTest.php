<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/bin/diagnose-account-access-status.php';

final class AccountAccessStatusInventoryTest extends TestCase
{
    public function testReadOnlyInventoryCountsCohortAndSharedAddressesWithoutExportingThem(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        foreach ([
            'CREATE TABLE verejni_uzivatele(email TEXT,email_overeno INT,aktivni INT,registrovan TEXT)',
            'CREATE TABLE child_access_accounts(sportovec_id INT,active INT)',
            'CREATE TABLE sportovci(id INT,email TEXT,stav_clenstvi TEXT)',
            'CREATE TABLE treneri(email TEXT)',
            'CREATE TABLE kis_person_contacts(emails_json TEXT)',
            'CREATE TABLE account_person_roles(status TEXT)',
            'CREATE TABLE club_roster_members(status TEXT,valid_from TEXT,valid_to TEXT)',
            'CREATE TABLE club_member_charges(status TEXT,due_on TEXT)',
        ] as $sql) {
            $pdo->exec($sql);
        }
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES('parent@example.test',0,0,'2026-11-20 12:00:00'),('new@example.test',0,1,'2026-11-25 12:00:00')");
        $pdo->exec("INSERT INTO sportovci VALUES(1,'same@example.test','cekajici'),(2,'SAME@example.test','aktivni'),(3,'invalid','archiv')");
        $pdo->exec('INSERT INTO child_access_accounts VALUES(1,0)');
        $pdo->exec("INSERT INTO treneri VALUES('coach@example.test')");
        $pdo->exec("INSERT INTO kis_person_contacts VALUES('[\"contact@example.test\"]')");
        $pdo->exec("INSERT INTO account_person_roles VALUES('approved'),('pending')");
        $pdo->exec("INSERT INTO club_member_charges VALUES('pending','2026-01-01')");

        $before = (int)$pdo->query('SELECT COUNT(*) FROM sportovci')->fetchColumn();
        $result = \accountAccessStatusInventory($pdo);
        self::assertSame(1, $result['public_accounts']['cohort_unverified']);
        self::assertSame(1, $result['public_accounts']['newer']);
        self::assertSame(2, $result['athletes']['missing_access']);
        self::assertSame(1, $result['athletes']['email_shared_groups']);
        self::assertSame(2, $result['athletes']['email_shared_people']);
        self::assertSame(1, $result['athletes']['invalid_email']);
        self::assertSame(1, $result['relations']['approved']);
        self::assertSame(1, $result['past_due_charges']);
        self::assertSame($before, (int)$pdo->query('SELECT COUNT(*) FROM sportovci')->fetchColumn());
        self::assertStringNotContainsString('example.test', json_encode($result, JSON_THROW_ON_ERROR));
        self::assertSame(['existing_email_trust' => false, 'athlete_email_trust' => false,
            'monthly_membership_overdue_alerts' => false], $result['new_tables_already_present']);
        self::assertTrue(\accountAccessStatusInventoryOk($result, 'before'));
        self::assertFalse(\accountAccessStatusInventoryOk($result, 'after'));

        $pdo->exec("UPDATE verejni_uzivatele SET email_overeno=1 WHERE email='parent@example.test'");
        $pdo->exec('INSERT INTO child_access_accounts VALUES(2,0),(3,0)');
        $pdo->exec('CREATE TABLE existing_email_trust(state TEXT)');
        $pdo->exec("INSERT INTO existing_email_trust VALUES('trusted')");
        $pdo->exec('CREATE TABLE athlete_email_trust(state TEXT,auto_created INT)');
        $pdo->exec("INSERT INTO athlete_email_trust VALUES('trusted',1),('trusted',1),('undeliverable',1)");
        $pdo->exec('CREATE TABLE monthly_membership_overdue_alerts(id INT)');
        $after = \accountAccessStatusInventory($pdo);
        self::assertTrue(\accountAccessStatusInventoryOk($after, 'after'));
        self::assertFalse(\accountAccessStatusInventoryOk($after, 'before'));
        self::assertSame(3, $after['converted']['athlete_auto_created']);
        self::assertStringNotContainsString('example.test', json_encode($after, JSON_THROW_ON_ERROR));
    }
}
