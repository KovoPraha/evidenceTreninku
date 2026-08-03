<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/child_access.php';
require_once dirname(__DIR__, 2) . '/includes/auth_session.php';

final class ChildAccessTest extends TestCase
{
    public function testBindingTrainerOrPublicIdentityClearsRestrictedAthleteMode(): void
    {
        $_SESSION = [];
        \auth_session_bind_child(9, 4);
        \auth_session_bind_public_user(8, 3);
        self::assertArrayNotHasKey('sportovec_pristup_id', $_SESSION);
        self::assertSame(8, $_SESSION['verejny_uzivatel_id']);

        \auth_session_bind_child(9, 4);
        \auth_session_bind_trainer(7, 2);
        self::assertArrayNotHasKey('sportovec_pristup_id', $_SESSION);
        self::assertSame(7, $_SESSION['trener_id']);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testChildBindingDropsTrainerAndFamilyIdentity(): void
    {
        \auth_session_bind_trainer(7, 2);
        \auth_session_bind_public_user(8, 3);
        $_SESSION['role'] = 'admin';
        $_SESSION['opravneni'] = ['sync_evidence' => 'admin'];

        \auth_session_bind_child(9, 4);

        self::assertSame(9, $_SESSION['sportovec_pristup_id']);
        self::assertSame(4, $_SESSION[AUTH_SESSION_CHILD_VERSION_KEY]);
        self::assertArrayNotHasKey('trener_id', $_SESSION);
        self::assertArrayNotHasKey('verejny_uzivatel_id', $_SESSION);
        self::assertArrayNotHasKey('role', $_SESSION);
        self::assertArrayNotHasKey('opravneni', $_SESSION);
    }

    public function testMigrationAndCredentialLifecycleAreRevocableAndAudited(): void
    {
        $pdo = $this->baseDatabase();
        $migration = require dirname(__DIR__, 2) . '/migrations/20260804190000_child_access_accounts.php';
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));

        $created = \childAccessCreate($pdo, 10, ' Anna.U15 ', 'BezpecneHeslo123!', 1, 'Schváleno rodičem.');
        self::assertSame(1, $created['access_account_id']);
        self::assertNotNull(\childAccessAuthenticate($pdo, 'anna.u15', 'BezpecneHeslo123!'));
        self::assertNull(\childAccessAuthenticate($pdo, 'anna.u15', 'spatne-heslo'));

        \childAccessResetPassword($pdo, 1, 'NoveBezpecneHeslo456!', 1, 'Zapomenuté heslo.');
        self::assertNull(\childAccessAuthenticate($pdo, 'anna.u15', 'BezpecneHeslo123!'));
        $account = \childAccessAuthenticate($pdo, 'anna.u15', 'NoveBezpecneHeslo456!');
        self::assertSame(2, (int)$account['session_version']);

        \childAccessSetActive($pdo, 1, false, 1, 'Ukončení přístupu.');
        self::assertNull(\childAccessAuthenticate($pdo, 'anna.u15', 'NoveBezpecneHeslo456!'));
        self::assertNull(\auth_session_active_version($pdo, 'child', 1));
        self::assertSame(
            ['create', 'password_reset', 'deactivate'],
            $pdo->query('SELECT action FROM child_access_events ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    public function testEveryOverviewSectionIsScopedByAccessAccountInsteadOfCallerPersonId(): void
    {
        $pdo = $this->fullDatabase();

        $anna = \childAccessOverview($pdo, 1);
        self::assertSame(10, (int)$anna['person']['sportovec_id']);
        self::assertSame(['Anna trénink'], array_column($anna['trainings'], 'napln'));
        self::assertSame(['Anna U15'], array_column($anna['rosters'], 'team_name'));
        self::assertSame(['Anna závod'], array_column($anna['events'], 'event_name'));
        self::assertSame(['Anna kroužek'], array_column($anna['payments'], 'product_name_snapshot'));

        $bara = \childAccessOverview($pdo, 2);
        self::assertSame(11, (int)$bara['person']['sportovec_id']);
        self::assertSame(['Bára trénink'], array_column($bara['trainings'], 'napln'));
        self::assertSame(['Bára U13'], array_column($bara['rosters'], 'team_name'));
        self::assertSame(['Bára soustředění'], array_column($bara['events'], 'event_name'));
        self::assertSame(['Bára kurz'], array_column($bara['payments'], 'product_name_snapshot'));

        foreach (['rosters', 'events', 'trainings', 'payments'] as $section) {
            self::assertCount(1, $anna[$section], 'Anna must never receive sibling rows in ' . $section);
            self::assertCount(1, $bara[$section], 'Bára must never receive sibling rows in ' . $section);
        }
    }

    public function testInactiveOrUnknownAccessAccountCannotReadAnyPerson(): void
    {
        $pdo = $this->fullDatabase();
        $pdo->exec('UPDATE child_access_accounts SET active=0 WHERE id=1');

        foreach ([1, 999] as $accessId) {
            try {
                \childAccessOverview($pdo, $accessId);
                self::fail('Inactive or unknown account unexpectedly opened a profile.');
            } catch (\ChildAccessException $exception) {
                self::assertSame('Přístup sportovce není aktivní.', $exception->getMessage());
            }
        }
    }

    public function testDuplicateSportovecAndLoginAreRejectedByDatabaseConstraints(): void
    {
        $pdo = $this->baseDatabase();
        $migration = require dirname(__DIR__, 2) . '/migrations/20260804190000_child_access_accounts.php';
        $migration['up']($pdo);
        \childAccessCreate($pdo, 10, 'anna', 'BezpecneHeslo123!', 1, 'První účet.');

        $this->expectException(\ChildAccessException::class);
        \childAccessCreate($pdo, 10, 'anna2', 'JineBezpecneHeslo123!', 1, 'Duplicitní osoba.');
    }

    private function baseDatabase(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT,stav_clenstvi TEXT)');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT,aktivni INTEGER)');
        $pdo->exec("INSERT INTO sportovci VALUES(10,'Anna','První','2012-01-01','aktivni'),(11,'Bára','Druhá','2014-01-01','aktivni')");
        $pdo->exec("INSERT INTO treneri VALUES(1,'Admin',1)");
        return $pdo;
    }

    private function fullDatabase(): PDO
    {
        $pdo = $this->baseDatabase();
        $migration = require dirname(__DIR__, 2) . '/migrations/20260804190000_child_access_accounts.php';
        $migration['up']($pdo);
        $hash = password_hash('BezpecneHeslo123!', PASSWORD_DEFAULT);
        $insert = $pdo->prepare("INSERT INTO child_access_accounts(sportovec_id,login_name,login_key,password_hash,active,session_version,password_changed_at,created_by_trainer_id) VALUES(?,?,?,?,1,1,CURRENT_TIMESTAMP,1)");
        $insert->execute([10, 'anna', 'anna', $hash]);
        $insert->execute([11, 'bara', 'bara', $hash]);

        $pdo->exec('CREATE TABLE club_seasons(id INTEGER PRIMARY KEY,name TEXT,starts_on TEXT)');
        $pdo->exec('CREATE TABLE club_teams(id INTEGER PRIMARY KEY,season_id INTEGER,name TEXT,discipline TEXT,age_label TEXT)');
        $pdo->exec('CREATE TABLE club_roster_members(id INTEGER PRIMARY KEY,team_id INTEGER,sportovec_id INTEGER,status TEXT,valid_from TEXT,valid_to TEXT)');
        $pdo->exec("INSERT INTO club_seasons VALUES(1,'2026','2026-01-01')");
        $pdo->exec("INSERT INTO club_teams VALUES(1,1,'Anna U15','dráha','U15'),(2,1,'Bára U13','silnice','U13')");
        $pdo->exec("INSERT INTO club_roster_members VALUES(1,1,10,'active','2026-01-01',NULL),(2,2,11,'active','2026-01-01',NULL)");

        $pdo->exec('CREATE TABLE club_events(id INTEGER PRIMARY KEY,name TEXT,event_type TEXT,status TEXT)');
        $pdo->exec('CREATE TABLE club_event_registrations(id INTEGER PRIMARY KEY,event_id INTEGER,sportovec_id INTEGER,status TEXT,registered_at TEXT,cancelled_at TEXT)');
        $pdo->exec("INSERT INTO club_events VALUES(1,'Anna závod','race','open'),(2,'Bára soustředění','camp','open')");
        $pdo->exec("INSERT INTO club_event_registrations VALUES(1,1,10,'confirmed','2026-08-01',NULL),(2,2,11,'confirmed','2026-08-02',NULL)");

        $pdo->exec('CREATE TABLE treninky(id INTEGER PRIMARY KEY,datum TEXT,napln TEXT,delka REAL,kategorie TEXT)');
        $pdo->exec('CREATE TABLE trenink_sportovec(trenink_id INTEGER,sportovec_id INTEGER)');
        $pdo->exec("INSERT INTO treninky VALUES(1,'2026-08-01','Anna trénink',1.5,'dráha'),(2,'2026-08-02','Bára trénink',1.0,'silnice')");
        $pdo->exec('INSERT INTO trenink_sportovec VALUES(1,10),(2,11)');

        $pdo->exec('CREATE TABLE shop_orders(id INTEGER PRIMARY KEY,public_code TEXT,status TEXT,payment_status TEXT,placed_at TEXT)');
        $pdo->exec('CREATE TABLE shop_order_items(id INTEGER PRIMARY KEY,order_id INTEGER,beneficiary_sportovec_id INTEGER,product_name_snapshot TEXT,quantity INTEGER,line_amount_minor INTEGER,currency TEXT)');
        $pdo->exec("INSERT INTO shop_orders VALUES(1,'A01','paid','paid','2026-08-01'),(2,'B01','paid','paid','2026-08-02')");
        $pdo->exec("INSERT INTO shop_order_items VALUES(1,1,10,'Anna kroužek',1,10000,'CZK'),(2,2,11,'Bára kurz',1,20000,'CZK')");
        return $pdo;
    }
}
