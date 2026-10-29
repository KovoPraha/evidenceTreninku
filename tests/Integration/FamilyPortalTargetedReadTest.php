<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\QueryProfilePdo;

require_once dirname(__DIR__) . '/Support/QueryProfilePdo.php';
require_once dirname(__DIR__) . '/Support/FamilyPortalReadFixture.php';
require_once dirname(__DIR__) . '/Support/FamilyPortalReadBaseline.php';

final class FamilyPortalTargetedReadTest extends TestCase
{
    private QueryProfilePdo $pdo;
    private ?PDO $admin = null;
    private ?string $database = null;

    protected function setUp(): void
    {
        if (getenv('EVIDENCE_FAMILY_MARIADB') === '1') {
            $port = getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306';
            if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
                throw new \RuntimeException('Invalid isolated localhost port.');
            }
            $dsn = 'mysql:host=127.0.0.1;port=' . $port . ';charset=utf8mb4';
            $this->admin = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $this->database = 'w71_contract_' . bin2hex(random_bytes(8));
            $this->admin->exec('CREATE DATABASE `' . $this->database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->pdo = new QueryProfilePdo($dsn . ';dbname=' . $this->database, 'root', '');
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } else {
            $this->pdo = new QueryProfilePdo('sqlite::memory:');
        }
        \Tests\Support\familyPortalReadFixture($this->pdo);
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        if ($this->admin !== null && $this->database !== null) {
            $this->admin->exec('DROP DATABASE `' . $this->database . '`');
        }
    }

    public function testIdentityStatusRoleAndTimeRulesMatchTheFrozenReader(): void
    {
        $update = $this->pdo->prepare('UPDATE account_person_roles SET relation_role=?,status=?,valid_from=?,valid_to=? WHERE id=1');
        foreach ([
            ['guardian', 'approved', '2020-01-01', null, true],
            ['self', 'approved', '2020-01-01', null, true],
            ['payer', 'approved', '2020-01-01', null, false],
            ['coach', 'approved', '2020-01-01', null, false],
            ['guardian', 'pending', '2020-01-01', null, false],
            ['guardian', 'revoked', '2020-01-01', null, false],
            ['guardian', 'rejected', '2020-01-01', null, false],
            ['guardian', 'approved', '2099-01-01', null, false],
            ['guardian', 'approved', '2020-01-01', '2021-01-01', false],
            ['guardian', 'approved', null, null, false],
        ] as [$role, $status, $from, $to, $allowed]) {
            $update->execute([$role, $status, $from, $to]);
            $this->assertAccess(1, 1, $allowed);
            self::assertSame(\familyReadBaselineAuthorizedPeople($this->pdo, 1), \familyPortalAuthorizedPeople($this->pdo, 1));
        }
        $update->execute(['guardian', 'approved', '2020-01-01', null]);
        foreach ([[0, 1], [-1, 1], [1, 0], [1, -1], [1, 999], [2, 1], [99, 1]] as [$account, $person]) {
            $this->assertAccess($account, $person, false);
        }
        foreach (['aktivni', 'email_overeno'] as $column) {
            $this->pdo->exec('UPDATE verejni_uzivatele SET ' . $column . '=0 WHERE id=1');
            $this->assertAccess(1, 1, false);
            $this->pdo->exec('UPDATE verejni_uzivatele SET ' . $column . '=1 WHERE id=1');
            $this->assertAccess(1, 1, true);
        }
        $this->pdo->exec('UPDATE account_person_roles SET valid_from=CURRENT_TIMESTAMP WHERE id=1');
        $this->assertAccess(1, 1, true);
        $this->pdo->exec('UPDATE account_person_roles SET valid_to=CURRENT_TIMESTAMP WHERE id=1');
        $this->assertAccess(1, 1, false);
    }

    public function testDuplicatesOrphansAndDatabaseCollationMatchTheBaseline(): void
    {
        $this->pdo->exec("INSERT INTO account_person_roles VALUES(100,1,1,'self','approved','2020-01-01',NULL),"
            . "(101,1,1,'guardian','approved','2020-01-01',NULL),(102,1,999,'self','approved','2020-01-01',NULL),"
            . "(103,99,1,'self','approved','2020-01-01',NULL)");
        $this->assertAccess(1, 1, true);
        $this->assertAccess(1, 999, false);
        $this->assertAccess(99, 1, false);
        self::assertSame(\familyReadBaselineAuthorizedPeople($this->pdo, 1), \familyPortalAuthorizedPeople($this->pdo, 1));
        $this->pdo->exec("UPDATE account_person_roles SET status='APPROVED',relation_role='GUARDIAN' WHERE sportovec_id=1");
        self::assertSame(\familyReadBaselineCanViewPerson($this->pdo, 1, 1), \familyPortalCanViewPerson($this->pdo, 1, 1));
    }

    public function testRevocationAndRollbackAreVisibleOnTheSameConnection(): void
    {
        $this->assertAccess(1, 1, true);
        $this->pdo->beginTransaction();
        $this->pdo->exec("UPDATE account_person_roles SET status='revoked' WHERE id=1");
        $this->assertAccess(1, 1, false);
        $this->pdo->rollBack();
        $this->assertAccess(1, 1, true);
        $this->pdo->exec("DELETE FROM account_person_roles WHERE id=1");
        $this->assertAccess(1, 1, false);
    }

    public function testMissingRequiredTablesDenyAccess(): void
    {
        foreach (['sportovci', 'verejni_uzivatele', 'account_person_roles'] as $table) {
            $this->pdo->exec('DROP TABLE ' . $table);
            $this->assertAccess(1, 1, false);
        }
    }

    public function testSinglePersonCheckHasConstantQueriesAndNoMembershipHydration(): void
    {
        $this->pdo->profiling = true;
        foreach ([1, 999] as $person) {
            $this->pdo->profile = [];
            self::assertSame($person === 1, \familyPortalCanViewPerson($this->pdo, 1, $person));
            self::assertCount(4, $this->pdo->profile);
            self::assertLessThanOrEqual(4, array_sum(array_column($this->pdo->profile, 'fetched_rows')));
            foreach ($this->pdo->profile as $query) {
                self::assertStringNotContainsString('SELECT * FROM sportovci', $query['sql']);
                self::assertStringNotContainsString('s.narozeni', $query['sql']);
            }
        }
    }

    private function assertAccess(int $account, int $person, bool $expected): void
    {
        self::assertSame($expected, \familyReadBaselineCanViewPerson($this->pdo, $account, $person));
        self::assertSame($expected, \familyPortalCanViewPerson($this->pdo, $account, $person));
    }
}
