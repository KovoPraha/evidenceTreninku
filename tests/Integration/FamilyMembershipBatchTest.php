<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\QueryProfilePdo;

require_once dirname(__DIR__) . '/Support/QueryProfilePdo.php';
require_once dirname(__DIR__) . '/Support/FamilyPortalReadFixture.php';
require_once dirname(__DIR__) . '/Support/FamilyPortalReadBaseline.php';

final class FamilyMembershipBatchTest extends TestCase
{
    /** @var list<string> */
    private array $schemas = [];
    private ?PDO $admin = null;

    private function database(int $size): QueryProfilePdo
    {
        if (getenv('EVIDENCE_FAMILY_MARIADB') === '1') {
            $port = getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306';
            if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
                throw new \RuntimeException('Invalid isolated localhost port.');
            }
            $dsn = 'mysql:host=127.0.0.1;port=' . $port . ';charset=utf8mb4';
            $this->admin ??= new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $schema = 'w74_contract_' . bin2hex(random_bytes(8));
            $this->admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->schemas[] = $schema;
            $pdo = new QueryProfilePdo($dsn . ';dbname=' . $schema, 'root', '');
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } else {
            $pdo = new QueryProfilePdo('sqlite::memory:');
        }
        \Tests\Support\familyPortalReadFixture($pdo, $size);
        return $pdo;
    }

    protected function tearDown(): void
    {
        foreach ($this->schemas as $schema) {
            $this->admin?->exec('DROP DATABASE `' . $schema . '`');
        }
    }

    public function testBoundedBatchesPreserveAllRowsAndOrdering(): void
    {
        foreach ([0, 1, 5, 20, 399, 400, 401, 1000] as $size) {
            $pdo = $this->database($size);
            $expected = \familyReadBaselineAuthorizedPeople($pdo, 1);
            $pdo->profiling = true;
            self::assertSame($expected, \familyPortalAuthorizedPeople($pdo, 1));
            $queries = array_filter($pdo->profile, static fn (array $q): bool =>
                str_starts_with($q['sql'], 'SELECT * FROM sportovci WHERE id'));
            self::assertCount((int) ceil($size / 400), $queries, 'Family size ' . $size);
            self::assertSame($size, array_sum(array_column($queries, 'fetched_rows')));
            foreach ($queries as $query) {
                self::assertLessThanOrEqual(400, count($query['parameters']));
                self::assertSame(count($query['parameters']), count(array_unique($query['parameters'])));
            }
        }
    }

    public function testDuplicatesFreshMembershipAndRevocationDoNotLeakPrivateFields(): void
    {
        $pdo = $this->database(2);
        $pdo->exec('ALTER TABLE sportovci ADD private_note TEXT');
        $pdo->exec("UPDATE sportovci SET private_note='PRIVATE_SENTINEL'");
        $pdo->exec("INSERT INTO account_person_roles VALUES(3,1,1,'self','approved','2020-01-01',NULL)");
        $pdo->profiling = true;
        $people = \familyPortalAuthorizedPeople($pdo, 1);
        self::assertSame(['guardian', 'self'], $people[0]['relation_roles']);
        self::assertArrayNotHasKey('private_note', $people[0]);
        self::assertSame(\familyReadBaselineAuthorizedPeople($pdo, 1), $people);
        $pdo->exec("UPDATE sportovci SET stav_manualni=0,stav_duvod='Changed' WHERE id=1");
        self::assertSame('Changed', \familyPortalAuthorizedPeople($pdo, 1)[0]['stav_duvod']);
        $pdo->exec("UPDATE account_person_roles SET status='revoked' WHERE sportovec_id=2");
        self::assertCount(1, \familyPortalAuthorizedPeople($pdo, 1));
        self::assertSame([], \familyPortalAuthorizedPeople($pdo, 2));
        self::assertSame([], \familyPortalAuthorizedPeople($pdo, 1, 2));
    }

    public function testMissingOptionalColumnsRetainOriginalArrayShape(): void
    {
        $pdo = $this->database(5);
        foreach (['stav_manualni', 'stav_duvod', 'stav_aktualizovan'] as $column) {
            $pdo->exec('ALTER TABLE sportovci DROP COLUMN ' . $column);
            self::assertSame(\familyReadBaselineAuthorizedPeople($pdo, 1), \familyPortalAuthorizedPeople($pdo, 1));
        }
    }
}
