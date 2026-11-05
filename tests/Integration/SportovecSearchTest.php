<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/sportovec_search.php';

final class SportovecSearchTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE TABLE sportovci(id INTEGER PRIMARY KEY, jmeno TEXT, prijmeni TEXT, uci TEXT, narozeni TEXT);
            INSERT INTO sportovci VALUES (1,'Anna','Novakova','100001','2010-01-02'),
                (2,'Petr','Novak','100002',NULL),(3,'Anna','Archivovana','100003',NULL),
                (4,'Anna','Novakova','100004','2010-01-02'),(5,'Test','100%_!','100005',NULL);
            CREATE TABLE skupiny(id INTEGER PRIMARY KEY, nazev TEXT);
            INSERT INTO skupiny VALUES (10,'Archiv');
            CREATE TABLE sportovec_skupina(sportovec_id INTEGER, skupina_id INTEGER);
            INSERT INTO sportovec_skupina VALUES(3,10),(NULL,10);
            CREATE TABLE treneri(id INTEGER PRIMARY KEY, aktivni INTEGER);
            INSERT INTO treneri VALUES(7,1);
            CREATE TABLE staff_user_positions(trainer_id INTEGER,position_code TEXT);
            INSERT INTO staff_user_positions VALUES(7,'coach');
            CREATE TABLE sportovec_podskupina(sportovec_id INTEGER,podskupina_id INTEGER);
            INSERT INTO sportovec_podskupina VALUES(1,20),(3,20);
            CREATE TABLE coach_scope_grants(trainer_id INTEGER,scope_kind TEXT,scope_id INTEGER,
                revoked_at TEXT,valid_from TEXT,valid_to TEXT);
            INSERT INTO coach_scope_grants VALUES(7,'subgroup',20,NULL,'2020-01-01',NULL)");
        $_SESSION = ['trener_id' => 7, 'staff_positions' => ['coach','sports_lead'], 'staff_active_position' => 'sports_lead'];
        return $pdo;
    }

    public function testSearchAcceptsBothNameOrdersAndLiteralWildcards(): void
    {
        $pdo = $this->database();
        foreach (['Anna Novakova', 'Novakova Anna', '  novakova   anna  '] as $query) {
            self::assertSame([1,4], array_column(sportovecSearch($pdo, $query), 'id'));
        }
        self::assertSame([2], array_column(sportovecSearch($pdo, '100002'), 'id'));
        self::assertSame([5], array_column(sportovecSearch($pdo, '%_!'), 'id'));
        self::assertSame([], sportovecSearch($pdo, "' OR 1=1 --"));
        $matches = sportovecSearch($pdo, 'Anna');
        self::assertNotSame($matches[0]['label'], $matches[1]['label']);
    }

    public function testInitialBrowseIsOptInAndNullArchiveRowsCannotHideUnrelatedPeople(): void
    {
        $pdo = $this->database();
        self::assertSame([], sportovecSearch($pdo, ''));
        self::assertSame([], sportovecSearch($pdo, 'A', 20, true));
        $ids = array_column(sportovecSearch($pdo, '', 20, true), 'id');
        self::assertCount(4, $ids);
        self::assertNotContains(3, $ids);
        self::assertSame([], sportovecSearch($pdo, 'Archivovana'));
        for ($i = 6; $i <= 70; $i++) {
            $pdo->exec("INSERT INTO sportovci(id,jmeno,prijmeni) VALUES($i,'Test','Limit')");
        }
        self::assertCount(20, sportovecSearch($pdo, '', 20, true));
        self::assertCount(50, sportovecSearch($pdo, '', 9999, true));
    }

    public function testSearchNeverExpandsCoachScopeEvenForSuperadmin(): void
    {
        $pdo = $this->database();
        $_SESSION['staff_active_position'] = 'coach';
        $_SESSION['staff_is_superadmin'] = true;
        self::assertSame([1], array_column(sportovecSearch($pdo, 'Anna'), 'id'));
        self::assertSame([1], array_column(sportovecSearch($pdo, '', 20, true), 'id'));
        $pdo->exec("UPDATE coach_scope_grants SET revoked_at=CURRENT_TIMESTAMP");
        self::assertSame([], sportovecSearch($pdo, '', 20, true));
        $_SESSION = [];
        self::assertSame([], sportovecSearch($pdo, 'Anna'));
    }

    public function testTrainingSearchWorksWithoutRosterGrantAndRechecksLiveEmployment(): void
    {
        $pdo = $this->database();
        $_SESSION['staff_active_position'] = 'coach';
        $pdo->exec('DELETE FROM coach_scope_grants');
        self::assertSame([], sportovecSearch($pdo, 'Anna'));
        self::assertSame([1,4], array_column(sportovecSearch($pdo, 'Anna', training: true), 'id'));
        self::assertSame([], sportovecSearch($pdo, 'Archivovana', training: true));
        $pdo->exec('DELETE FROM staff_user_positions');
        self::assertSame([], sportovecSearch($pdo, 'Anna', training: true));
        $pdo->exec("INSERT INTO staff_user_positions VALUES(7,'coach')");
        $pdo->exec('UPDATE treneri SET aktivni=0 WHERE id=7');
        self::assertSame([], sportovecSearch($pdo, 'Anna', training: true));
    }

    public function testTrainingSearchPlacesOwnPeopleBeforeAlphabeticallyEarlierSubstitutes(): void
    {
        $pdo = $this->database();
        $_SESSION['staff_active_position'] = 'coach';
        $pdo->exec("UPDATE sportovci SET prijmeni='ZZZ vlastní' WHERE id=1");
        $ids = array_column(sportovecSearch($pdo, '', 20, true, training: true), 'id');
        self::assertSame(1, $ids[0]);
        self::assertContains(2, $ids);
        self::assertContains(4, $ids);
        self::assertNotContains(3, $ids);
    }

    public function testSportsLeadCanOpenFormSaveAndLegacyRoutesAndFindMenuEntry(): void
    {
        foreach (['zatezovy_test_form.php','ulozit_zatezovy_test.php',
            'zatezove_testy/zatezovy_test_form.php','zatezove_testy/ulozit_zatezovy_test.php'] as $route) {
            self::assertSame(['coach','sports_lead'], staffRouteAllowedPositions($route));
        }
        $routes = [];
        foreach (staffPositionMenuGroups('sports_lead', false) as $group) {
            $routes = array_merge($routes, array_column($group['items'], 'route'));
        }
        self::assertContains('zatezovy_test_form.php', $routes);
        self::assertNotContains('registrar', staffRouteAllowedPositions('ulozit_zatezovy_test.php'));
    }
}
