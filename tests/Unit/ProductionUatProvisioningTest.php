<?php
declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/bin/provision-production-uat.php';

final class ProductionUatProvisioningTest extends TestCase
{
    public function testSettingsRequireShortLivedWindowAndStrongPassword(): void
    {
        $now = new DateTimeImmutable('2026-09-22 12:00:00', new DateTimeZone('Europe/Prague'));
        $settings = \kisUatValidateSettings([
            'password' => 'Dlouhe!Uat2026-Heslo',
            'window_end' => '2026-10-06 20:00:00',
        ], $now);
        self::assertSame('2026-10-06 20:00:00', $settings['window_end']->format('Y-m-d H:i:s'));

        $this->expectException(InvalidArgumentException::class);
        \kisUatValidateSettings([
            'password' => 'Dlouhe!Uat2026-Heslo',
            'window_end' => '2027-01-01 00:00:00',
        ], $now);
    }

    public function testFixtureScheduleFitsShortApprovedWindowAndFailsBeforeAnyWritesIfTooShort(): void
    {
        $zone = new DateTimeZone('Europe/Prague');
        $now = new DateTimeImmutable('2026-10-16 12:00:00', $zone);
        $end = new DateTimeImmutable('2026-10-22 23:00:00', $zone);
        $dates = \kisUatFixtureSchedule($now, $end);
        self::assertSame('2026-10-18', $dates['training']->format('Y-m-d'));
        foreach ($dates as $day) self::assertLessThanOrEqual($end, $day->setTime(18, 0));

        $short = \kisUatFixtureSchedule($now, new DateTimeImmutable('2026-10-18 23:00:00', $zone));
        foreach ($short as $day) self::assertSame('2026-10-18', $day->format('Y-m-d'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('UAT okno je příliš krátké');
        \kisUatFixtureSchedule($now, new DateTimeImmutable('2026-10-18 17:00:00', $zone));
    }

    public function testProvisioningRejectsAnAnsweredTrainingOutsideWindowBeforeWrites(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE planovane_treninky(id INTEGER PRIMARY KEY,nazev TEXT,datum TEXT,cas_od TEXT,stav TEXT,trenink_id INTEGER)');
        $pdo->exec('CREATE TABLE training_rsvps(plan_id INTEGER)');
        $pdo->exec('CREATE TABLE club_makeup_slots(plan_id INTEGER)');
        $pdo->exec('CREATE TABLE training_roster_links(plan_id INTEGER,team_id INTEGER)');
        $pdo->exec("INSERT INTO planovane_treninky VALUES(11,'TEST - Trénink nového dítěte','2026-10-24','17:00:00','planovany',NULL)");
        $pdo->exec('INSERT INTO training_rsvps VALUES(11)');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Žádná data nebyla změněna');
        \kisUatExistingTraining($pdo,new DateTimeImmutable('2026-10-16 12:00:00',new DateTimeZone('Europe/Prague')),new DateTimeImmutable('2026-10-22 23:00:00',new DateTimeZone('Europe/Prague')));
    }

    public function testProductionScriptAndWorkflowKeepIdentitiesAndSecretAllowlisted(): void
    {
        $root = dirname(__DIR__, 2);
        $script = (string)file_get_contents($root . '/bin/provision-production-uat.php');
        $workflow = (string)file_get_contents($root . '/.github/workflows/production-drills.yml');
        $cleanup = (string)file_get_contents($root . '/bin/production-test-cleanup.php');

        foreach (['tester.karel@velocota.com','tester.petra@velocota.com','tester.ema','tester.adam','TEST -'] as $expected) {
            self::assertStringContainsString($expected, $script);
        }
        self::assertStringContainsString("KIS_UAT_PROVISION_CONFIRM')!=='VYTVORIT-UAT-DATA'", $script);
        self::assertStringContainsString('secrets.KIS_UAT_TEST_PASSWORD', $workflow);
        self::assertStringNotContainsString('Dlouhe!Uat2026-Heslo', $workflow);
        self::assertStringContainsString('pripravit-uat-ucty-a-data', $workflow);
        self::assertStringContainsString('obnovit-uat-test-hesla', $workflow);
        self::assertStringContainsString('bin/rotate-production-uat-passwords.php', $workflow);
        self::assertStringContainsString("'KP-TEST-UAT-LAHEV'", $script);
        self::assertStringContainsString("'KP-TEST-UAT-KROUZEK'", $script);
        self::assertStringContainsString("'KP-TEST-UAT-PRIMESTSKY-DEN'", $script);
        self::assertStringContainsString('clubEventOpenPaidRegistration', $script);
        self::assertStringContainsString("'uat-free-' . \$day->format('Ymd')", $script);
        self::assertStringContainsString('clubEventConfigureRegistrationTerms(', $script);
        self::assertStringContainsString('clubEventRosterReplaceTargets', $script);
        self::assertStringContainsString('kisUatFixtureSchedule($now,$windowEnd)', $script);
        self::assertStringContainsString('trainingRosterBridgeReplacePlanTeams', $script);
        self::assertStringContainsString('kisUatEnsureExclusiveFamilies', $script);
        self::assertStringContainsString('clubMakeupSyncPlanRules($pdo,$trainingId)', $script);
        self::assertStringContainsString('kisUatFreshPaidParticipant', $script);
        self::assertStringContainsString('kisUatPaidEventIdentity', $script);
        self::assertStringContainsString('shopCatalogPublicationActivate(', $script);
        self::assertLessThan(strpos($script,'clubEventOpenPaidRegistration',strpos($script,'function kisUatUpsertPaidShopEvent')),strpos($script,'shopCatalogPublicationActivate(',strpos($script,'function kisUatUpsertPaidShopEvent')));
        self::assertStringContainsString("(.products|length) == 3", $workflow);
        self::assertStringContainsString("pub.public_name LIKE 'TEST -%'", $cleanup);
        self::assertStringContainsString('KP-TEST-UAT-PRIMESTSKY-DEN', $cleanup);

        $registration = (string)file_get_contents($root . '/includes/club_event_registration.php');
        $paidList = (string)file_get_contents($root . '/includes/club_event_shop.php');
        self::assertStringContainsString("visibility='public'", $registration);
        self::assertStringContainsString("e.visibility='public'", $registration);
        self::assertStringContainsString("e.visibility='public'", $paidList);
        self::assertStringContainsString("s.ends_at>=CURRENT_TIMESTAMP", $paidList);
    }

    public function testExistingUatSeasonIsExpandedForARepeatedRun(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE club_seasons(id INTEGER PRIMARY KEY AUTOINCREMENT,code TEXT UNIQUE,name TEXT,starts_on TEXT,ends_on TEXT,status TEXT,created_by_trainer_id INTEGER,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE club_teams(id INTEGER PRIMARY KEY AUTOINCREMENT,season_id INTEGER,code TEXT,name TEXT,discipline TEXT,age_label TEXT,status TEXT,created_by_trainer_id INTEGER)');
        $pdo->exec("INSERT INTO club_seasons(code,name,starts_on,ends_on,status,created_by_trainer_id) VALUES('TEST-UAT-2026','TEST - UAT období','2026-09-22','2026-11-21','draft',7)");
        $seasonId = (int)$pdo->lastInsertId();
        $pdo->exec("INSERT INTO club_teams(season_id,code,name,discipline,age_label,status,created_by_trainer_id) VALUES($seasonId,'TEST-UAT-DETI','TEST - UAT děti','vše','8–10 let','active',7)");
        $teamId = (int)$pdo->lastInsertId();

        $result = \kisUatSeasonAndTeam($pdo, 7, '2026-10-07', '2026-12-06');

        self::assertSame(['season_id'=>$seasonId,'team_id'=>$teamId], $result);
        $season = $pdo->query("SELECT starts_on,ends_on,status FROM club_seasons WHERE code='TEST-UAT-2026'")->fetch(PDO::FETCH_ASSOC);
        self::assertSame('2026-09-22', $season['starts_on']);
        self::assertSame('2026-12-06', $season['ends_on']);
        self::assertSame('active', $season['status']);
    }

    public function testCancelledRegistrationDoesNotBlockNextUatWindow(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE club_event_registrations(event_id INTEGER NOT NULL,status TEXT NOT NULL)');
        $pdo->exec("INSERT INTO club_event_registrations(event_id,status) VALUES(2,'cancelled')");

        self::assertFalse(\kisUatHasNonCancelledRegistrations($pdo, 2));

        $pdo->exec("INSERT INTO club_event_registrations(event_id,status) VALUES(2,'payment_pending')");
        self::assertTrue(\kisUatHasNonCancelledRegistrations($pdo, 2));
    }

    public function testExclusiveUatFamiliesRevokeOnlyProvisionedCrossLinks(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY)');
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY)');
        $pdo->exec('CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY AUTOINCREMENT,account_id INTEGER,sportovec_id INTEGER,relation_role TEXT,status TEXT,source TEXT,valid_from TEXT,valid_to TEXT,created_by_trainer_id INTEGER,approved_by_trainer_id INTEGER,decision_note TEXT,updated_at TEXT)');
        $pdo->exec('CREATE TABLE account_person_role_events(relation_id INTEGER,actor_trainer_id INTEGER,action TEXT,from_status TEXT,to_status TEXT,relation_role TEXT,note TEXT)');
        $pdo->exec('INSERT INTO verejni_uzivatele VALUES(1),(2)');
        $pdo->exec('INSERT INTO sportovci VALUES(11),(12)');
        $pdo->exec("INSERT INTO account_person_roles(account_id,sportovec_id,relation_role,status,source,valid_from,decision_note) VALUES(1,12,'guardian','approved','admin',CURRENT_TIMESTAMP,'Schválená vazba výhradně pro produkční UAT.'),(2,11,'guardian','approved','admin',CURRENT_TIMESTAMP,'Schválená vazba výhradně pro produkční UAT.')");

        \kisUatEnsureExclusiveFamilies($pdo,1,2,11,12,7);
        $rows = $pdo->query('SELECT account_id,sportovec_id,status FROM account_person_roles ORDER BY account_id,sportovec_id')->fetchAll(PDO::FETCH_NUM);
        self::assertSame([[1,11,'approved'],[1,12,'revoked'],[2,11,'revoked'],[2,12,'approved']], array_map(static fn(array $row): array => [(int)$row[0],(int)$row[1],$row[2]],$rows));
        \kisUatEnsureExclusiveFamilies($pdo,1,2,11,12,7);
        self::assertSame(4,(int)$pdo->query('SELECT COUNT(*) FROM account_person_roles')->fetchColumn());
    }

    public function testExclusiveUatFamiliesRefuseToRevokeManualRelation(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY,account_id INTEGER,sportovec_id INTEGER,status TEXT,decision_note TEXT)');
        $pdo->exec("INSERT INTO account_person_roles VALUES(1,1,12,'approved','Manuální změna vazby')");
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('automatické zrušení');
        \kisUatEnsureExclusiveFamilies($pdo,1,2,11,12,7);
    }

    public function testPaidEventGetsFreshParticipantAfterPreviousOrderHistory(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY)');
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY AUTOINCREMENT,jmeno TEXT,prijmeni TEXT,narozeni TEXT,email TEXT,telefon TEXT,hash TEXT,uci INTEGER,stav_clenstvi TEXT)');
        $pdo->exec('CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY AUTOINCREMENT,account_id INTEGER,sportovec_id INTEGER,relation_role TEXT,status TEXT,source TEXT,valid_from TEXT,valid_to TEXT,created_by_trainer_id INTEGER,approved_by_trainer_id INTEGER,decision_note TEXT,updated_at TEXT)');
        $pdo->exec('CREATE TABLE account_person_role_events(relation_id INTEGER,actor_trainer_id INTEGER,action TEXT,from_status TEXT,to_status TEXT,relation_role TEXT,note TEXT)');
        $pdo->exec('CREATE TABLE club_event_registrations(event_id INTEGER,sportovec_id INTEGER)');
        $pdo->exec('CREATE TABLE club_roster_members(id INTEGER PRIMARY KEY AUTOINCREMENT,team_id INTEGER,sportovec_id INTEGER,status TEXT,source TEXT,valid_from TEXT,valid_to TEXT,created_by_trainer_id INTEGER,updated_at TEXT)');
        $pdo->exec('INSERT INTO verejni_uzivatele VALUES(1)');
        $now = new DateTimeImmutable('2026-10-16 12:00:00',new DateTimeZone('Europe/Prague'));

        $first = \kisUatFreshPaidParticipant($pdo,9,1,3,7,'2026-10-16',$now);
        self::assertSame($first,\kisUatFreshPaidParticipant($pdo,9,1,3,7,'2026-10-16',$now));
        $pdo->prepare('INSERT INTO club_event_registrations VALUES(9,?)')->execute([$first]);
        $second = \kisUatFreshPaidParticipant($pdo,9,1,3,7,'2026-10-16',$now);
        self::assertNotSame($first,$second);
        self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn());
        $pdo->prepare("UPDATE club_roster_members SET status='removed',valid_to='2026-10-17' WHERE sportovec_id=?")->execute([$second]);
        \kisUatEnsureRosterMember($pdo,3,$second,'2026-10-18',7);
        self::assertSame(['active','2026-10-18',null],$pdo->query('SELECT status,valid_from,valid_to FROM club_roster_members WHERE sportovec_id='.$second)->fetch(PDO::FETCH_NUM));
    }

    public function testPaidEventIdentityRotatesOnlyWhenProtectedEventIsOutsideWindow(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE club_events(id INTEGER PRIMARY KEY,name TEXT,status TEXT,registration_ends_at TEXT)');
        $pdo->exec('CREATE TABLE club_event_sessions(event_id INTEGER,status TEXT,ends_at TEXT)');
        $pdo->exec('CREATE TABLE club_event_registrations(event_id INTEGER,status TEXT)');
        $now = new DateTimeImmutable('2026-10-16 12:00:00',new DateTimeZone('Europe/Prague'));
        $end = new DateTimeImmutable('2026-10-22 23:00:00',new DateTimeZone('Europe/Prague'));
        self::assertSame('KP-TEST-UAT-PRIMESTSKY-DEN',\kisUatPaidEventIdentity($pdo,$now,$end)['sku']);
        $pdo->exec("INSERT INTO club_events VALUES(1,'TEST - Příměstský den','open','2026-10-20 23:00:00')");
        $pdo->exec("INSERT INTO club_event_sessions VALUES(1,'scheduled','2026-10-21 15:00:00')");
        $pdo->exec("INSERT INTO club_event_registrations VALUES(1,'payment_pending')");
        self::assertSame('KP-TEST-UAT-PRIMESTSKY-DEN',\kisUatPaidEventIdentity($pdo,$now,$end)['sku']);
        $pdo->exec("UPDATE club_event_sessions SET ends_at='2026-10-24 15:00:00'");
        self::assertSame('KP-TEST-UAT-PRIMESTSKY-DEN-20261022',\kisUatPaidEventIdentity($pdo,$now,$end)['sku']);
    }
}
