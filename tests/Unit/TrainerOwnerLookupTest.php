<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/trainer_password_reset.php';
require_once dirname(__DIR__, 2) . '/.github/scripts/trainer-owner-lookup.php';

final class TrainerOwnerLookupTest extends TestCase
{
    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT,email TEXT,aktivni INTEGER,session_version INTEGER,heslo TEXT)');
        $pdo->exec('CREATE TABLE staff_user_positions(trainer_id INTEGER,position_code TEXT)');
        $pdo->exec('CREATE TABLE staff_superadmins(trainer_id INTEGER)');
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,trener_id INTEGER,email TEXT,aktivni INTEGER,email_overeno INTEGER,session_version INTEGER)');
        $pdo->exec("INSERT INTO treneri VALUES(1,'actual.login',' Owner@Example.test ',1,1,'private-password-hash'),(2,'Unrelated','other@example.test',1,1,'other-hash')");
        $pdo->exec("INSERT INTO staff_user_positions VALUES(1,'sports_lead')");
        return $pdo;
    }

    public function testExactNormalizedLookupWorksWithWritesDisabledAndDoesNotExposeSecrets(): void
    {
        $pdo = $this->database();
        $pdo->exec('PRAGMA query_only=ON');
        $result = \trainerOwnerLookup($pdo, ' OWNER@example.test ');
        self::assertCount(1, $result['matches']);
        self::assertSame('actual.login', $result['matches'][0]['login_name']);
        self::assertTrue($result['matches'][0]['can_provision']);
        self::assertTrue($result['matches'][0]['unique_login_name']);
        self::assertTrue($result['matches'][0]['reset_eligible']);
        self::assertFalse($result['email_available_for_separate_pilot']);
        self::assertFalse($result['domain_mutations']);
        $json = json_encode($result, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('private-password-hash', $json);
        self::assertStringNotContainsString('Owner@Example.test', $json);
        self::assertStringNotContainsString('Unrelated', $json);
    }

    public function testAmbiguityAndInactiveAccountAreReportedWithoutChoosingAnOwner(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO treneri VALUES(3,'actual.login','owner@example.test',0,1,'hash')");
        $pdo->exec('INSERT INTO staff_superadmins VALUES(3)');
        $result = \trainerOwnerLookup($pdo, 'owner@example.test');
        self::assertCount(2, $result['matches']);
        self::assertFalse($result['matches'][0]['unique_login_name']);
        self::assertFalse($result['matches'][1]['active']);
        self::assertFalse($result['matches'][1]['can_provision']);
        self::assertFalse($result['matches'][1]['reset_eligible']);
        self::assertFalse($result['email_available_for_separate_pilot']);
    }

    public function testPublicCollisionPreventsSeparatePilotEvenWithoutStaffMatch(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(1,NULL,'public@example.test',0,0,1)");
        $collision = \trainerOwnerLookup($pdo, 'public@example.test');
        self::assertSame([], $collision['matches']);
        self::assertSame(1, $collision['public_account_matches']);
        self::assertFalse($collision['email_available_for_separate_pilot']);
        self::assertNull($collision['public_accounts'][0]['explicit_staff_link']);
        self::assertTrue(\trainerOwnerLookup($pdo, 'unused@example.test')['email_available_for_separate_pilot']);
    }

    public function testOnlyExplicitPublicLinkFindsStaffWithAnotherAddress(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(1,1,'public@example.test',1,1,1),(2,999,'broken@example.test',1,1,1)");
        $pdo->exec('PRAGMA query_only=ON');
        $result = \trainerOwnerLookup($pdo, 'public@example.test');
        self::assertSame([], $result['matches']);
        $account = $result['public_accounts'][0];
        self::assertTrue($account['active']);
        self::assertTrue($account['email_verified']);
        self::assertSame('actual.login', $account['explicit_staff_link']['login_name']);
        self::assertTrue($account['explicit_staff_link']['can_provision']);
        // Explicitly linked emails differ: the existing reset contract must remain blocked.
        self::assertFalse($account['explicit_staff_link']['reset_eligible']);
        self::assertSame(['exists' => false], \trainerOwnerLookup($pdo, 'broken@example.test')['public_accounts'][0]['explicit_staff_link']);
    }

    public function testInvalidEmailIsRejectedBeforeQuerying(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        \trainerOwnerLookup($this->database(), "' OR 1=1 --");
    }
}
