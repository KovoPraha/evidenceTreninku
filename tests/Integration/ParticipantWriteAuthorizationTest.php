<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/FamilyPortalReadFixture.php';
require_once dirname(__DIR__, 2) . '/includes/participant_write_authorization.php';

final class ParticipantWriteAuthorizationTest extends TestCase
{
    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        \Tests\Support\familyPortalReadFixture($pdo, 2);
        $pdo->exec('CREATE TABLE child_access_accounts(id INT PRIMARY KEY,sportovec_id INT,active INT)');
        $pdo->exec('INSERT INTO child_access_accounts VALUES(50,1,1),(51,2,0)');
        return $pdo;
    }

    public function testRequiresTransactionAndLeavesReadOnlyGuardUsable(): void
    {
        $pdo = $this->database();
        self::assertTrue(\familyPortalCanViewPerson($pdo, 1, 1));
        self::assertFalse($pdo->inTransaction());
        $this->expectException(\LogicException::class);
        \participantWriteCanActFor($pdo, 'account', 1, 1);
    }

    public function testIdentityNamespacesAndMembershipStayIndependent(): void
    {
        $pdo = $this->database();
        $pdo->beginTransaction();
        self::assertTrue(\participantWriteCanActFor($pdo, 'account', 1, 1));
        self::assertTrue(\participantWriteCanActFor($pdo, 'athlete', 50, 1));
        foreach ([['account', 50, 1], ['athlete', 1, 1], ['athlete', 50, 2], ['athlete', 51, 2],
            ['trainer', 1, 1], ['account', 0, 1], ['account', 1, 0], ['account', 2, 1]] as [$type, $actor, $person]) {
            self::assertFalse(\participantWriteCanActFor($pdo, $type, $actor, $person));
        }
        $pdo->exec("UPDATE account_person_roles SET status='revoked'");
        self::assertFalse(\participantWriteCanActFor($pdo, 'account', 1, 1));
        self::assertTrue(\participantWriteCanActFor($pdo, 'athlete', 50, 1));
        self::assertTrue($pdo->inTransaction());
        $pdo->rollBack();
        self::assertTrue(\familyPortalCanViewPerson($pdo, 1, 1));
    }

    public function testFamilyPredicatesMatchReadGuardIncludingTemporalBoundaries(): void
    {
        $pdo = $this->database();
        foreach (["relation_role='self'", "relation_role='guardian'", "relation_role='payer'", "status='pending'",
            "valid_from=CURRENT_TIMESTAMP", "valid_from='2999-01-01'", "valid_to=CURRENT_TIMESTAMP", "valid_to='2999-01-01'"] as $change) {
            $pdo->beginTransaction();
            $pdo->exec('UPDATE account_person_roles SET ' . $change . ' WHERE id=1');
            self::assertSame(\familyPortalCanViewPerson($pdo, 1, 1), \participantWriteCanActFor($pdo, 'account', 1, 1), $change);
            $pdo->rollBack();
        }
        foreach (['aktivni=0', 'email_overeno=0'] as $change) {
            $pdo->beginTransaction();
            $pdo->exec('UPDATE verejni_uzivatele SET ' . $change . ' WHERE id=1');
            self::assertFalse(\participantWriteCanActFor($pdo, 'account', 1, 1));
            $pdo->rollBack();
        }
    }

    public function testMissingTablesFailClosed(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->beginTransaction();
        self::assertFalse(\participantWriteCanActFor($pdo, 'account', 1, 1));
        self::assertFalse(\participantWriteCanActFor($pdo, 'athlete', 1, 1));
        $pdo->rollBack();
    }
}
