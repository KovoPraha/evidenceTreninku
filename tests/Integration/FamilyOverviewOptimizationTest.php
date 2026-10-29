<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Tests\Support\QueryProfilePdo;

require_once dirname(__DIR__).'/Support/QueryProfilePdo.php';
require_once dirname(__DIR__).'/Support/FamilyPortalReadFixture.php';
require_once dirname(__DIR__).'/Support/FamilyOverviewBaseline.php';

final class FamilyOverviewOptimizationTest extends TestCase
{
    private function database(int $size = 20): QueryProfilePdo
    {
        $pdo = new QueryProfilePdo('sqlite::memory:');
        \Tests\Support\familyPortalReadFixture($pdo, $size);
        return $pdo;
    }
    public function testCompleteOverviewMatchesFrozenBaselineForEveryFamilySize(): void
    {
        foreach ([0,1,5,20] as $size) {
            $pdo = $this->database($size);
            self::assertSame(\w72BaselineFamilyPortalOverview($pdo, 1), \familyPortalOverview($pdo, 1));
        }
    }
    public function testDuplicateRolesMembershipAndFreshRevocationStayUnchanged(): void
    {
        $pdo = $this->database(2);
        $pdo->exec("INSERT INTO account_person_roles VALUES(3,1,1,'self','approved','2020-01-01',NULL)");
        self::assertSame(\w72BaselineFamilyPortalOverview($pdo, 1), \familyPortalOverview($pdo, 1));
        $pdo->exec("UPDATE account_person_roles SET status='revoked' WHERE sportovec_id=2");
        self::assertCount(1, \familyPortalOverview($pdo, 1));
        $this->expectException(\FamilyPortalAccessDenied::class);
        \familyPortalPersonOverview($pdo, 1, 2);
    }
    public function testForeignPersonAndAccountNeverReturnAnOverview(): void
    {
        $pdo = $this->database(2);
        self::assertSame([], \familyPortalOverview($pdo, 2));
        self::assertSame([], \familyPortalOverview($pdo, 3));
        $this->expectException(\FamilyPortalAccessDenied::class);
        \familyPortalPersonOverview($pdo, 2, 1);
    }
    public function testTargetFilterPreservesRolesAndRejectsInvalidTargets(): void
    {
        $pdo = $this->database(2);
        $pdo->exec("INSERT INTO account_person_roles VALUES(3,1,1,'self','approved','2020-01-01',NULL)");
        $all = \familyPortalAuthorizedPeople($pdo, 1);
        self::assertSame([$all[0]], \familyPortalAuthorizedPeople($pdo, 1, 1));
        self::assertSame(['guardian', 'self'], $all[0]['relation_roles']);
        self::assertSame([], \familyPortalAuthorizedPeople($pdo, 1, 0));
        self::assertSame([], \familyPortalAuthorizedPeople($pdo, 1, -1));
        self::assertSame([], \familyPortalAuthorizedPeople($pdo, 1, 999));
        self::assertSame([], \familyPortalAuthorizedPeople($pdo, 2, 1));
        $pdo->exec("UPDATE sportovci SET stav_manualni=0,stav_duvod='Changed' WHERE id=1");
        self::assertSame(\w72BaselineFamilyPortalPersonOverview($pdo, 1, 1), \familyPortalPersonOverview($pdo, 1, 1));
    }

    public function testRevocationBetweenProfilesIsStillChecked(): void
    {
        foreach (['w72BaselineFamilyPortalOverview', 'familyPortalOverview'] as $read) {
            $pdo = new FamilyOverviewRevocationPdo('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            \Tests\Support\familyPortalReadFixture($pdo, 2);
            $pdo->exec('CREATE TABLE club_seasons(id INT,code TEXT,name TEXT,starts_on TEXT,ends_on TEXT)');
            $pdo->exec('CREATE TABLE club_teams(id INT,season_id INT,code TEXT,name TEXT,discipline TEXT,age_label TEXT)');
            $pdo->exec('CREATE TABLE club_roster_members(id INT,team_id INT,sportovec_id INT,status TEXT,source TEXT,valid_from TEXT,valid_to TEXT)');
            $pdo->revoke = true;
            try {
                $read($pdo, 1);
                self::fail('Mid-overview revocation was ignored.');
            } catch (\FamilyPortalAccessDenied) {
                self::assertFalse($pdo->revoke);
                self::assertFalse(\familyPortalCanViewPerson($pdo, 1, 2));
            }
        }
    }

    public function testMembershipHydrationGrowsLinearlyAcrossCompleteOverview(): void
    {
        $pdo = $this->database(20);
        $pdo->profiling = true;
        self::assertCount(20, \familyPortalOverview($pdo, 1));
        $membership = array_filter($pdo->profile, static fn (array $q): bool => $q['sql'] === 'SELECT * FROM sportovci WHERE id=?');
        self::assertCount(40, $membership, 'Only initial family plus current target, not every family for every profile.');
    }
}

final class FamilyOverviewRevocationPdo extends PDO
{
    public bool $revoke = false;
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if ($this->revoke && str_contains($query, 'FROM club_roster_members m')) {
            $this->revoke = false;
            $this->exec("UPDATE account_person_roles SET status='revoked' WHERE sportovec_id=2");
        }
        return parent::prepare($query, $options);
    }
}
