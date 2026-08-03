<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/family_portal.php';

final class FamilyPortalTest extends TestCase
{
    public function testGuardianSeesTwoChildrenAndSelfAccountOnlyItself(): void
    {
        $pdo = $this->database();

        self::assertEqualsCanonicalizing([10, 11], $this->personIds(\familyPortalAuthorizedPeople($pdo, 1)));
        self::assertSame([10], $this->personIds(\familyPortalAuthorizedPeople($pdo, 2)));
        self::assertTrue(\familyPortalCanViewPerson($pdo, 1, 11));
        self::assertFalse(\familyPortalCanViewPerson($pdo, 2, 11));

        $overview = \familyPortalPersonOverview($pdo, 1, 10);
        self::assertSame('U15', $overview['rosters'][0]['team_name']);
        self::assertSame(['active', 'removed'], array_column($overview['rosters'], 'status'));
        self::assertSame('Soustředění', $overview['events'][0]['event_name']);
        self::assertSame('Technika', $overview['trainings'][0]['napln']);
    }

    public function testRevokedPendingAndForeignLinksStayInaccessible(): void
    {
        $pdo = $this->database();

        self::assertSame([], \familyPortalAuthorizedPeople($pdo, 3));
        self::assertSame([], \familyPortalAuthorizedPeople($pdo, 4));
        self::assertFalse(\familyPortalCanViewPerson($pdo, 1, 12));
        $this->expectException(\FamilyPortalAccessDenied::class);
        \familyPortalPersonOverview($pdo, 2, 11);
    }

    public function testMultipleApprovedRolesForOnePersonAreCollapsedWithoutDuplicate(): void
    {
        $pdo = $this->database();
        $pdo->exec(
            "INSERT INTO account_person_roles(account_id,sportovec_id,relation_role,status,valid_from,valid_to) "
            . "VALUES(1,10,'self','approved','2020-01-01',NULL)"
        );

        $people = \familyPortalAuthorizedPeople($pdo, 1);
        self::assertCount(2, $people);
        self::assertEqualsCanonicalizing([10, 11], $this->personIds($people));
        $personTen = array_values(array_filter(
            $people,
            static fn (array $person): bool => (int)$person['sportovec_id'] === 10
        ))[0];
        self::assertSame(['guardian', 'self'], $personTen['relation_roles']);
        self::assertCount(2, \familyPortalOverview($pdo, 1));
    }

    public function testMissingOptionalTablesYieldEmptySectionsInsteadOfFailure(): void
    {
        $overview = \familyPortalPersonOverview($this->database(false), 1, 10);
        self::assertSame([], $overview['rosters']);
        self::assertSame([], $overview['events']);
        self::assertSame([], $overview['trainings']);
    }

    /** @param list<array<string,mixed>> $people @return list<int> */
    private function personIds(array $people): array
    {
        return array_map(static fn (array $person): int => (int)$person['sportovec_id'], $people);
    }

    private function database(bool $withOptionalTables = true): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY, aktivni INTEGER, email_overeno INTEGER)');
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY, jmeno TEXT, prijmeni TEXT, narozeni TEXT, stav_clenstvi TEXT)');
        // Deliberately no unique constraint: reads must deduplicate even conflicting legacy data.
        $pdo->exec('CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY, account_id INTEGER, sportovec_id INTEGER, relation_role TEXT, status TEXT, valid_from TEXT, valid_to TEXT)');
        $pdo->exec('INSERT INTO verejni_uzivatele VALUES(1,1,1),(2,1,1),(3,1,1),(4,0,1)');
        $pdo->exec("INSERT INTO sportovci VALUES(10,'Anna','První','2012-01-01','aktivni'),(11,'Bára','Druhá','2014-01-01','aktivni'),(12,'Cizí','Osoba','2010-01-01','aktivni')");
        $pdo->exec(
            "INSERT INTO account_person_roles VALUES"
            . "(1,1,10,'guardian','approved','2020-01-01',NULL),"
            . "(2,1,11,'guardian','approved','2020-01-01',NULL),"
            . "(3,2,10,'self','approved','2020-01-01',NULL),"
            . "(4,3,12,'guardian','revoked','2020-01-01','2021-01-01'),"
            . "(5,3,11,'guardian','pending','2020-01-01',NULL),"
            . "(6,4,12,'self','approved','2020-01-01',NULL)"
        );
        if (!$withOptionalTables) {
            return $pdo;
        }
        $pdo->exec('CREATE TABLE club_seasons(id INTEGER PRIMARY KEY, code TEXT, name TEXT, starts_on TEXT, ends_on TEXT)');
        $pdo->exec('CREATE TABLE club_teams(id INTEGER PRIMARY KEY, season_id INTEGER, code TEXT, name TEXT, discipline TEXT, age_label TEXT)');
        $pdo->exec('CREATE TABLE club_roster_members(id INTEGER PRIMARY KEY, team_id INTEGER, sportovec_id INTEGER, status TEXT, source TEXT, valid_from TEXT, valid_to TEXT)');
        $pdo->exec("INSERT INTO club_seasons VALUES(1,'2026','Rok 2026','2026-01-01','2026-12-31')");
        $pdo->exec("INSERT INTO club_teams VALUES(1,1,'U15','U15','silnice','U15')");
        $pdo->exec("INSERT INTO club_roster_members VALUES(1,1,10,'active','manual','2026-01-01',NULL),(2,1,10,'removed','manual','2025-01-01','2025-12-31')");
        $pdo->exec('CREATE TABLE club_events(id INTEGER PRIMARY KEY, code TEXT, name TEXT, event_type TEXT, status TEXT)');
        $pdo->exec('CREATE TABLE club_event_registrations(id INTEGER PRIMARY KEY, event_id INTEGER, sportovec_id INTEGER, status TEXT, registered_at TEXT, cancelled_at TEXT)');
        $pdo->exec("INSERT INTO club_events VALUES(1,'SOUST','Soustředění','camp','open')");
        $pdo->exec("INSERT INTO club_event_registrations VALUES(1,1,10,'confirmed','2026-08-01',NULL)");
        $pdo->exec('CREATE TABLE treninky(id INTEGER PRIMARY KEY, datum TEXT, napln TEXT, delka REAL, kategorie TEXT)');
        $pdo->exec('CREATE TABLE trenink_sportovec(trenink_id INTEGER, sportovec_id INTEGER)');
        $pdo->exec("INSERT INTO treninky VALUES(1,'2026-08-02','Technika',1.5,'dráha')");
        $pdo->exec('INSERT INTO trenink_sportovec VALUES(1,10)');
        return $pdo;
    }
}
