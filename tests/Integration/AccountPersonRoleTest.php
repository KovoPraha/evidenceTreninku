<?php
declare(strict_types=1);

namespace Tests\Integration;

use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/account_person_role.php';

final class AccountPersonRoleTest extends TestCase
{
    public function testOnlyExplicitApprovedRelationMakesPersonEligible(): void
    {
        $pdo = $this->database();

        self::assertFalse(\accountPersonCanManage($pdo, 1, 10));
        self::assertSame([], \accountPersonEligibleParticipants($pdo, 1));

        $first = \accountPersonRoleApprove($pdo, 1, 10, 'guardian', 7, 'Ověřeno podle přihlášky.');
        $same = \accountPersonRoleApprove($pdo, 1, 10, 'guardian', 7, 'Stejný podklad.');

        self::assertTrue($first['created']);
        self::assertFalse($same['created']);
        self::assertSame($first['relation_id'], $same['relation_id']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM account_person_role_events')->fetchColumn());
        self::assertFalse(\accountPersonCanManage($pdo, 1, 10), 'Unverified account must stay ineligible.');

        try {
            \accountPersonRoleApprove($pdo, 1, 10, 'self', 7, 'Konfliktní role.');
            self::fail('One account-person pair must not have two active roles.');
        } catch (\AccountPersonRoleException) {
            self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM account_person_roles')->fetchColumn());
        }

        $pdo->exec('UPDATE verejni_uzivatele SET email_overeno=1 WHERE id=1');
        self::assertTrue(\accountPersonCanManage($pdo, 1, 10));
        self::assertFalse(\accountPersonCanManage($pdo, 1, 11), 'A matching email must never infer another relation.');
        self::assertSame([10], array_map(
            static fn (array $participant): int => (int)$participant['sportovec_id'],
            \accountPersonEligibleParticipants($pdo, 1)
        ));

        $revoked = \accountPersonRoleRevoke($pdo, $first['relation_id'], 7, 'Odvoláno na žádost rodiče.');
        $sameRevocation = \accountPersonRoleRevoke($pdo, $first['relation_id'], 7, 'Opakovaný požadavek.');
        self::assertTrue($revoked['changed']);
        self::assertFalse($sameRevocation['changed']);
        self::assertFalse(\accountPersonCanManage($pdo, 1, 10));
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM account_person_role_events')->fetchColumn());

        $reactivated = \accountPersonRoleApprove($pdo, 1, 10, 'guardian', 7, 'Znovu ověřeno administrátorem.');
        self::assertFalse($reactivated['created']);
        self::assertTrue(\accountPersonCanManage($pdo, 1, 10));
        self::assertSame(3, (int)$pdo->query('SELECT COUNT(*) FROM account_person_role_events')->fetchColumn());
    }

    public function testDecisionRequiresRoleAndAuditNoteBeforeWriting(): void
    {
        $pdo = $this->database();

        foreach ([
            [1, 10, 'guardian', 7, ''],
            [1, 10, 'parent', 7, 'Podklad'],
        ] as $arguments) {
            try {
                \accountPersonRoleApprove($pdo, ...$arguments);
                self::fail('Invalid decision must be rejected.');
            } catch (InvalidArgumentException) {
                // Expected: validation happens before the transaction and all writes.
            }
        }

        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM account_person_roles')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM account_person_role_events')->fetchColumn());
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE treneri (id INTEGER PRIMARY KEY, jmeno TEXT NOT NULL)');
        $pdo->exec(
            'CREATE TABLE verejni_uzivatele ('
            . 'id INTEGER PRIMARY KEY, jmeno TEXT NOT NULL, prijmeni TEXT NOT NULL, '
            . 'email TEXT NOT NULL, aktivni INTEGER NOT NULL, email_overeno INTEGER NOT NULL)'
        );
        $pdo->exec(
            'CREATE TABLE sportovci ('
            . 'id INTEGER PRIMARY KEY, jmeno TEXT NOT NULL, prijmeni TEXT NOT NULL, '
            . 'narozeni TEXT NULL, stav_clenstvi TEXT NULL)'
        );
        $pdo->exec("INSERT INTO treneri (id, jmeno) VALUES (7, 'Admin')");
        $pdo->exec(
            "INSERT INTO verejni_uzivatele (id, jmeno, prijmeni, email, aktivni, email_overeno) "
            . "VALUES (1, 'Petr', 'Rodič', 'rodina@example.test', 1, 0)"
        );
        $pdo->exec(
            "INSERT INTO sportovci (id, jmeno, prijmeni, narozeni, stav_clenstvi) VALUES "
            . "(10, 'Eva', 'Dítě', '2014-01-01', 'aktivni'), "
            . "(11, 'Petr', 'Rodič', '1980-01-01', 'aktivni')"
        );

        $migration = require dirname(__DIR__, 2) . '/migrations/20260802230000_account_person_roles.php';
        $migration['up']($pdo);
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));

        return $pdo;
    }
}
