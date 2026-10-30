<?php

declare(strict_types=1);

namespace Tests\Integration;

use FamilySchemaReadScope;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\QueryProfilePdo;

require_once dirname(__DIR__, 2).'/includes/family_schema_read_scope.php';
require_once dirname(__DIR__).'/Support/FamilyPortalReadFixture.php';
require_once dirname(__DIR__).'/Support/QueryProfilePdo.php';
require_once dirname(__DIR__, 2).'/includes/club_finance.php';

final class FamilySchemaReadScopeTest extends TestCase
{
    public function testOptionalColumnBudgetAndRefreshAfterSchemaChange(): void
    {
        $pdo = new QueryProfilePdo('sqlite::memory:');
        $pdo->exec('CREATE TABLE optional(id INT)');
        $pdo->profiling = true;
        $scope = FamilySchemaReadScope::begin($pdo);
        try {
            for ($i = 0; $i < 3; $i++) {
                self::assertTrue(\clubFinanceColumnExists($pdo, 'optional', 'id'));
                self::assertFalse(\clubFinanceColumnExists($pdo, 'optional', 'added'));
            }
        } finally {
            $scope->close();
        }
        self::assertCount(2, $pdo->profile);
        $pdo->exec('ALTER TABLE optional ADD added INT');
        $scope = FamilySchemaReadScope::begin($pdo);
        try {
            self::assertTrue(\clubFinanceColumnExists($pdo, 'optional', 'added'));
        } finally {
            $scope->close();
        }
    }

    public function testNestedScopeRestoresOuterAndReleasesConnection(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $weak = \WeakReference::create($pdo);
        $calls = 0;
        $read = static function () use (&$calls): bool {
            $calls++;
            return true;
        };
        $outer = FamilySchemaReadScope::begin($pdo);
        try {
            FamilySchemaReadScope::remember($pdo, 'test', $read);
            $inner = FamilySchemaReadScope::begin($pdo);
            try {
                FamilySchemaReadScope::remember($pdo, 'test', $read);
            } finally {
                $inner->close();
            }
            FamilySchemaReadScope::remember($pdo, 'test', $read);
            self::assertSame(2, $calls);
        } finally {
            $outer->close();
        }
        $outer->close();
        FamilySchemaReadScope::remember($pdo, 'test', $read);
        self::assertSame(3, $calls);
        unset($pdo);
        self::assertNull($weak->get());
    }

    public function testRevocationDuringOverviewStillDeniesNextProfile(): void
    {
        $pdo = new FamilySchemaRevocationPdo('sqlite::memory:');
        \Tests\Support\familyPortalReadFixture($pdo, 2);
        $pdo->exec('CREATE TABLE club_seasons(id INT,code TEXT,name TEXT,starts_on TEXT,ends_on TEXT)');
        $pdo->exec('CREATE TABLE club_teams(id INT,season_id INT,code TEXT,name TEXT,discipline TEXT,age_label TEXT)');
        $pdo->exec('CREATE TABLE club_roster_members(id INT,team_id INT,sportovec_id INT,status TEXT,source TEXT,valid_from TEXT,valid_to TEXT)');
        $pdo->revoke = true;
        $scope = FamilySchemaReadScope::begin($pdo);
        try {
            $this->expectException(\FamilyPortalAccessDenied::class);
            \familyPortalOverview($pdo, 1);
        } finally {
            $scope->close();
            self::assertFalse($pdo->revoke);
            self::assertFalse(\familyPortalCanViewPerson($pdo, 1, 2));
        }
    }

    public function testRepeatedPresentAndMissingTablesAreReadOncePerScope(): void
    {
        $pdo = new QueryProfilePdo('sqlite::memory:');
        $pdo->exec('CREATE TABLE present(id INT)');
        $pdo->profiling = true;
        $scope = FamilySchemaReadScope::begin($pdo);
        try {
            for ($i = 0; $i < 4; $i++) {
                self::assertTrue(\familyPortalTableExists($pdo, 'present'));
                self::assertFalse(\familyPortalTableExists($pdo, 'missing'));
            }
        } finally {
            $scope->close();
        }
        self::assertCount(2, $pdo->profile);
    }

    public function testConnectionsAndNewScopesDoNotReuseOldMetadata(): void
    {
        $a = new PDO('sqlite::memory:');
        $b = new PDO('sqlite::memory:');
        $a->exec('CREATE TABLE thing(id INT)');
        $scope = FamilySchemaReadScope::begin($a);
        try {
            self::assertTrue(\familyPortalTableExists($a, 'thing'));
            self::assertFalse(\familyPortalTableExists($b, 'thing'));
        } finally {
            $scope->close();
        }
        $a->exec('DROP TABLE thing');
        self::assertFalse(\familyPortalTableExists($a, 'thing'));
        $scope = FamilySchemaReadScope::begin($a);
        try {
            self::assertFalse(\familyPortalTableExists($a, 'thing'));
        } finally {
            $scope->close();
        }
        $a->exec('CREATE TABLE thing(id INT)');
        self::assertTrue(\familyPortalTableExists($a, 'thing'));
    }

    public function testOptionalColumnsAndFreshDataWithinScope(): void
    {
        $pdo = new QueryProfilePdo('sqlite::memory:');
        \Tests\Support\familyPortalReadFixture($pdo, 2);
        $baseline = \familyPortalOverview($pdo, 1);
        $scope = FamilySchemaReadScope::begin($pdo);
        try {
            self::assertSame($baseline, \familyPortalOverview($pdo, 1));
            self::assertTrue(\clubFinanceColumnExists($pdo, 'sportovci', 'jmeno'));
            self::assertFalse(\clubFinanceColumnExists($pdo, 'sportovci', 'optional_missing'));
            $pdo->exec("UPDATE sportovci SET stav_duvod='LOCALHOST changed' WHERE id=1");
            self::assertSame('LOCALHOST changed', \familyPortalPersonOverview($pdo, 1, 1)['person']['stav_duvod']);
            $pdo->exec("UPDATE account_person_roles SET status='revoked' WHERE sportovec_id=2");
            self::assertFalse(\familyPortalCanViewPerson($pdo, 1, 2));
            $this->expectException(\FamilyPortalAccessDenied::class);
            \familyPortalPersonOverview($pdo, 1, 2);
        } finally {
            $scope->close();
        }
    }

    public function testErrorsAreRetriedAndFinallyRestoresFreshReads(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $calls = 0;
        $read = static function () use (&$calls): bool {
            $calls++;
            if ($calls === 1) {
                throw new \RuntimeException('Transient metadata error');
            }
            return true;
        };
        $scope = FamilySchemaReadScope::begin($pdo);
        try {
            try {
                FamilySchemaReadScope::remember($pdo, 'test', $read);
                self::fail('Expected error');
            } catch (\RuntimeException $e) {
                self::assertSame('Transient metadata error', $e->getMessage());
            }
            self::assertTrue(FamilySchemaReadScope::remember($pdo, 'test', $read));
            self::assertTrue(FamilySchemaReadScope::remember($pdo, 'test', $read));
            self::assertSame(2, $calls);
        } finally {
            $scope->close();
        }
        self::assertTrue(FamilySchemaReadScope::remember($pdo, 'test', $read));
        self::assertSame(3, $calls);
    }
}

final class FamilySchemaRevocationPdo extends PDO
{
    public bool $revoke = false;
    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        if ($this->revoke && str_contains($query, 'FROM club_roster_members m')) {
            $this->revoke = false;
            $this->exec("UPDATE account_person_roles SET status='revoked' WHERE sportovec_id=2");
        }
        return parent::prepare($query, $options);
    }
}
