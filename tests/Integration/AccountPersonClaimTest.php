<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/account_person_claim.php';

final class AccountPersonClaimTest extends TestCase
{
    public function testVerifiedAccountCanSubmitAndAdminCanApproveExactlyOnce(): void
    {
        $pdo = $this->database();

        $first = \accountPersonClaimSubmit($pdo, 1, 'guardian', 'Eva', 'Dítě', '2014-01-01', 'Oddíl A');
        $duplicate = \accountPersonClaimSubmit($pdo, 1, 'guardian', ' eva ', 'DÍTĚ', '2014-01-01', 'Jiná zpráva');

        self::assertTrue($first['created']);
        self::assertFalse($duplicate['created']);
        self::assertSame($first['id'], $duplicate['id']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM account_person_claim_requests')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM account_person_claim_events')->fetchColumn());

        $approved = \accountPersonClaimApprove($pdo, $first['id'], 10, 7, 'Ověřeno proti členské přihlášce.');
        self::assertTrue($approved['changed']);
        self::assertTrue(\accountPersonCanManage($pdo, 1, 10));
        self::assertSame('approved', $pdo->query(
            'SELECT status FROM account_person_claim_requests WHERE id=' . $first['id']
        )->fetchColumn());
        self::assertNull($pdo->query(
            'SELECT active_fingerprint FROM account_person_claim_requests WHERE id=' . $first['id']
        )->fetchColumn());
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM account_person_claim_events')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM account_person_role_events')->fetchColumn());
    }

    public function testUnverifiedAccountAndCrossAccountCancellationAreDenied(): void
    {
        $pdo = $this->database();
        try {
            \accountPersonClaimSubmit($pdo, 2, 'self', 'Jana', 'Neověřená', '1990-05-01', '');
            self::fail('Unverified account must be denied.');
        } catch (\AccountPersonClaimException) {
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM account_person_claim_requests')->fetchColumn());
        }

        $claim = \accountPersonClaimSubmit($pdo, 1, 'guardian', 'Eva', 'Dítě', '2014-01-01', '');
        try {
            \accountPersonClaimCancel($pdo, $claim['id'], 2);
            self::fail('Another account must not cancel the request.');
        } catch (\AccountPersonClaimException) {
            self::assertSame('pending', $pdo->query(
                'SELECT status FROM account_person_claim_requests WHERE id=' . $claim['id']
            )->fetchColumn());
        }

        $cancelled = \accountPersonClaimCancel($pdo, $claim['id'], 1);
        $again = \accountPersonClaimCancel($pdo, $claim['id'], 1);
        self::assertTrue($cancelled['changed']);
        self::assertFalse($again['changed']);
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM account_person_claim_events')->fetchColumn());
    }

    public function testClaimApprovalAndRelationAreOneAtomicTransaction(): void
    {
        $pdo = $this->database();
        \accountPersonRoleApprove($pdo, 1, 10, 'self', 7, 'Existující vlastní profil.');
        $claim = \accountPersonClaimSubmit($pdo, 1, 'guardian', 'Eva', 'Dítě', '2014-01-01', '');

        try {
            \accountPersonClaimApprove($pdo, $claim['id'], 10, 7, 'Konfliktní vztah.');
            self::fail('Conflicting role must roll back the claim decision.');
        } catch (\AccountPersonRoleException) {
            self::assertSame('pending', $pdo->query(
                'SELECT status FROM account_person_claim_requests WHERE id=' . $claim['id']
            )->fetchColumn());
            self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM account_person_claim_events')->fetchColumn());
            self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM account_person_roles')->fetchColumn());
        }
    }

    public function testClaimApprovalCanJoinOuterCreateAndApproveTransaction(): void
    {
        $pdo = $this->database();
        $claim = \accountPersonClaimSubmit($pdo, 1, 'guardian', 'Nová', 'Osoba', '2015-02-03', '');

        $pdo->beginTransaction();
        $pdo->exec("INSERT INTO sportovci VALUES (99, 'Nová', 'Osoba', '2015-02-03', 'cekajici')");
        \accountPersonClaimApprove($pdo, $claim['id'], 99, 7, 'Založeno z údajů žádosti.');
        self::assertTrue($pdo->inTransaction(), 'Approval must not commit a transaction owned by its caller.');
        $pdo->rollBack();

        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM sportovci WHERE id=99')->fetchColumn());
        self::assertSame('pending', $pdo->query(
            'SELECT status FROM account_person_claim_requests WHERE id=' . $claim['id']
        )->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM account_person_roles')->fetchColumn());
    }

    public function testAccountCannotAccumulateMoreThanFivePendingClaims(): void
    {
        $pdo = $this->database();
        for ($day = 1; $day <= 5; $day++) {
            $result = \accountPersonClaimSubmit(
                $pdo,
                1,
                'guardian',
                'Dítě' . $day,
                'Rodina',
                sprintf('2014-01-%02d', $day),
                ''
            );
            self::assertTrue($result['created']);
        }
        $duplicate = \accountPersonClaimSubmit($pdo, 1, 'guardian', ' dítě1 ', 'RODINA', '2014-01-01', '');
        self::assertFalse($duplicate['created'], 'Duplicate stays idempotent even at the pending limit.');

        $this->expectException(\AccountPersonClaimException::class);
        try {
            \accountPersonClaimSubmit($pdo, 1, 'guardian', 'Šesté', 'Dítě', '2014-02-01', '');
        } finally {
            self::assertSame(5, (int)$pdo->query(
                "SELECT COUNT(*) FROM account_person_claim_requests WHERE status='pending'"
            )->fetchColumn());
        }
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE treneri (id INTEGER PRIMARY KEY, jmeno TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE verejni_uzivatele (id INTEGER PRIMARY KEY, jmeno TEXT NOT NULL, prijmeni TEXT NOT NULL, email TEXT NOT NULL, aktivni INTEGER NOT NULL, email_overeno INTEGER NOT NULL)');
        $pdo->exec('CREATE TABLE sportovci (id INTEGER PRIMARY KEY, jmeno TEXT NOT NULL, prijmeni TEXT NOT NULL, narozeni TEXT NULL, stav_clenstvi TEXT NULL)');
        $pdo->exec("INSERT INTO treneri VALUES (7, 'Admin')");
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES (1, 'Petr', 'Rodič', 'rodina@example.test', 1, 1), (2, 'Jana', 'Neověřená', 'jana@example.test', 1, 0)");
        $pdo->exec("INSERT INTO sportovci VALUES (10, 'Eva', 'Dítě', '2014-01-01', 'aktivni'), (11, 'Petr', 'Rodič', '1980-01-01', 'aktivni')");
        foreach (['20260802230000_account_person_roles.php', '20260802233000_account_person_claim_requests.php'] as $filename) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $filename;
            $migration['up']($pdo);
            $migration['up']($pdo);
            self::assertTrue($migration['verify']($pdo));
        }
        return $pdo;
    }
}
