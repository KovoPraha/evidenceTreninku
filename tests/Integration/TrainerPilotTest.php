<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/trainer_pilot.php';

final class TrainerPilotTest extends TestCase
{
    private function operation(): array
    {
        return ['confirmation' => 'VYTVORIT-TEST-PILOT', 'actor' => 'fixture-operator', 'run_id' => '12345',
            'workflow_ref' => 'KovoPraha/evidenceTreninku/.github/workflows/trainer-pilot-production.yml@refs/heads/main'];
    }

    public function testOperationalPilotNeverElevatesAnyAccountAndKeepsPublicAccountsUntouched(): void
    {
        $pdo = $this->database();
        $pdo->exec('DELETE FROM staff_user_positions');
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(1,NULL,'public@example.test',1,0,1)");
        $publicBefore = $pdo->query('SELECT * FROM verejni_uzivatele')->fetchAll();
        $ownerBefore = $pdo->query('SELECT * FROM treneri WHERE id=1')->fetchAll();
        self::assertFalse(\trainerPilotStatus($pdo, \TRAINER_PILOT_ACTOR_NAME)['created']);
        $result = \trainerPilotCreate($pdo, \TRAINER_PILOT_ACTOR_NAME, 'pilot@example.test', $this->operation());
        self::assertTrue($result['scope_ready']);
        self::assertTrue($result['audit_actor_disabled']);
        self::assertFalse($result['owner']['can_provision']);
        self::assertFalse($result['owner']['reset_eligible']);
        self::assertFalse($result['outside_scope_visible']);
        self::assertSame('12345', $result['operation_run_id']);
        self::assertSame($publicBefore, $pdo->query('SELECT * FROM verejni_uzivatele')->fetchAll());
        self::assertSame($ownerBefore, $pdo->query('SELECT * FROM treneri WHERE id=1')->fetchAll());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM staff_superadmins')->fetchColumn());
        self::assertSame(['coach'], $pdo->query('SELECT position_code FROM staff_user_positions')->fetchAll(PDO::FETCH_COLUMN));
        self::assertStringContainsString('fixture-operator; run 12345', $pdo->query('SELECT reason FROM coach_scope_grants')->fetchColumn());
        $again = \trainerPilotCreate($pdo, \TRAINER_PILOT_ACTOR_NAME, 'pilot@example.test', $this->operation());
        self::assertFalse($again['changed']);
        self::assertSame($result['valid_to'], $again['valid_to']);
        $pdo->exec('PRAGMA query_only=ON');
        self::assertTrue(\trainerPilotStatus($pdo, \TRAINER_PILOT_ACTOR_NAME)['scope_ready']);
    }

    public function testOperationalAuditFailureRollsBackTechnicalIdentityToo(): void
    {
        $pdo = $this->database();
        $pdo->exec("CREATE TRIGGER reject_operational_audit BEFORE INSERT ON coach_scope_events BEGIN SELECT RAISE(ABORT,'fixture-audit-failure'); END");
        try {
            \trainerPilotCreate($pdo, \TRAINER_PILOT_ACTOR_NAME, 'pilot@example.test', $this->operation());
            self::fail('Audit failure must roll back');
        } catch (\PDOException) {
            self::assertFalse($pdo->inTransaction());
        }
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM treneri')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM nastaveni')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM club_teams')->fetchColumn());
    }

    public function testOperationalModeRequiresExactMainWorkflowAuthority(): void
    {
        $pdo = $this->database();
        $operation = $this->operation();
        $operation['workflow_ref'] = 'KovoPraha/evidenceTreninku/.github/workflows/trainer-pilot-production.yml@refs/heads/unreviewed';
        $this->expectExceptionMessage('pilot_operation_authorization_invalid');
        \trainerPilotCreate($pdo, \TRAINER_PILOT_ACTOR_NAME, 'pilot@example.test', $operation);
    }

    public function testAuditActorCannotBeUsedAsNormalOwnerOrActivatedSilently(): void
    {
        $pdo = $this->database();
        \trainerPilotCreate($pdo, \TRAINER_PILOT_ACTOR_NAME, 'pilot@example.test', $this->operation());
        try {
            \trainerPilotCreate($pdo, \TRAINER_PILOT_ACTOR_NAME, 'pilot@example.test');
            self::fail('Inactive actor is not an authorized staff owner');
        } catch (\RuntimeException $error) {
            self::assertSame('pilot_owner_not_unique_active', $error->getMessage());
        }
        $pdo->prepare('UPDATE treneri SET aktivni=1 WHERE jmeno=?')->execute([\TRAINER_PILOT_ACTOR_NAME]);
        $this->expectExceptionMessage('pilot_owner_not_unique_active');
        \trainerPilotCreate($pdo, \TRAINER_PILOT_ACTOR_NAME, 'pilot@example.test', $this->operation());
    }

    public function testOperationalEmailCollisionRollsBackAuditActorAndDoesNotLinkPublicAccount(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(1,NULL,'pilot@example.test',1,1,1)");
        try {
            \trainerPilotCreate($pdo, \TRAINER_PILOT_ACTOR_NAME, 'pilot@example.test', $this->operation());
            self::fail('Address collision must be refused');
        } catch (\RuntimeException $error) {
            self::assertSame('pilot_identity_collision', $error->getMessage());
        }
        self::assertNull($pdo->query('SELECT trener_id FROM verejni_uzivatele')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM treneri')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM nastaveni')->fetchColumn());
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY AUTOINCREMENT,jmeno TEXT,email TEXT,heslo TEXT,role TEXT,aktivni INTEGER,session_version INTEGER)');
        $pdo->exec("INSERT INTO treneri VALUES(1,'Fixture Owner','owner@example.test','unchanged-owner-hash','hlavni',1,1)");
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,trener_id INTEGER,email TEXT,aktivni INTEGER,email_overeno INTEGER,session_version INTEGER)');
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY AUTOINCREMENT,jmeno TEXT,prijmeni TEXT,narozeni TEXT,email TEXT,telefon TEXT,hash TEXT,uci INTEGER,uciid TEXT,stav_clenstvi TEXT)');
        $pdo->exec("INSERT INTO sportovci(id,jmeno,prijmeni,stav_clenstvi) VALUES(1,'Existing','Outside fixture','aktivni')");
        $pdo->exec('CREATE TABLE nastaveni(klic TEXT PRIMARY KEY,hodnota TEXT NOT NULL)');
        foreach (['20260804090000_kis_teams_rosters', '20260804110000_kis_roster_policies',
            '20260821150000_staff_workspaces', '20261021090000_roster_hierarchy_temporal', '20261022214000_coach_scope'] as $id) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $id . '.php';
            $migration['up']($pdo);
        }
        $pdo->exec('DELETE FROM staff_user_positions WHERE trainer_id=1');
        $pdo->exec("INSERT INTO staff_user_positions(trainer_id,position_code,is_default) VALUES(1,'sports_lead',1)");
        return $pdo;
    }

    public function testCreatesOnlyCoachAndSyntheticScopeWithAudit(): void
    {
        $pdo = $this->database();
        $result = \trainerPilotCreate($pdo, 'Fixture Owner', 'pilot@example.test');
        self::assertTrue($result['changed']);
        self::assertTrue($result['scope_ready']);
        self::assertTrue($result['reset_eligible']);
        self::assertSame(2, $result['visible_test_people']);
        $zone = new \DateTimeZone('UTC');
        self::assertSame(14 * 86400, (new \DateTimeImmutable($result['valid_to'], $zone))->getTimestamp()
            - (new \DateTimeImmutable($result['valid_from'], $zone))->getTimestamp());
        self::assertFalse($result['outside_scope_visible']);
        $json = json_encode($result, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('owner@example.test', $json);
        self::assertStringNotContainsString('unchanged-owner-hash', $json);
        self::assertSame('unchanged-owner-hash', $pdo->query('SELECT heslo FROM treneri WHERE id=1')->fetchColumn());
        self::assertSame(['coach'], $pdo->query('SELECT position_code FROM staff_user_positions WHERE trainer_id=' . $result['trainer_id'])->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM coach_scope_events')->fetchColumn());
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM staff_position_assignment_events WHERE action='provision_test_coach'")->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM verejni_uzivatele')->fetchColumn());
        $visible = $pdo->query('SELECT s.id FROM sportovci s WHERE ' . \coachScopePersonPredicateForTrainer($pdo, $result['trainer_id']) . ' ORDER BY s.id')->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame($result['member_ids'], array_map('intval', $visible));
        self::assertNotContains(1, $visible);
        self::assertNotContains($result['outside_test_person_id'], $visible);
    }

    public function testRepeatedProvisionDoesNotResetPasswordOrExtendGrant(): void
    {
        $pdo = $this->database();
        $first = \trainerPilotCreate($pdo, 'Fixture Owner', 'pilot@example.test');
        $before = $pdo->query('SELECT * FROM treneri ORDER BY id')->fetchAll();
        $again = \trainerPilotCreate($pdo, 'Fixture Owner', 'pilot@example.test');
        self::assertFalse($again['changed']);
        self::assertSame($first['valid_to'], $again['valid_to']);
        self::assertSame($before, $pdo->query('SELECT * FROM treneri ORDER BY id')->fetchAll());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM coach_scope_grants')->fetchColumn());
        $pdo->exec('PRAGMA query_only=ON');
        self::assertTrue(\trainerPilotStatus($pdo, 'Fixture Owner')['scope_ready']);
    }

    public function testExistingOwnerAddressCannotBecomePilot(): void
    {
        $pdo = $this->database();
        try {
            \trainerPilotCreate($pdo, 'Fixture Owner', ' OWNER@example.test ');
            self::fail('Must refuse collision');
        } catch (\RuntimeException $error) {
            self::assertSame('pilot_identity_collision', $error->getMessage());
        }
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM treneri')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM nastaveni')->fetchColumn());
    }

    public function testPublicAddressCollisionDoesNotLinkOrModifyExistingAccount(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(1,NULL,'pilot@example.test',1,1,1)");
        $this->expectExceptionMessage('pilot_identity_collision');
        \trainerPilotCreate($pdo, 'Fixture Owner', 'pilot@example.test');
    }

    public function testMissingOwnerAuthorityIsNeverElevated(): void
    {
        $pdo = $this->database();
        $pdo->exec('DELETE FROM staff_user_positions');
        $pdo->exec('DELETE FROM staff_superadmins');
        $this->expectExceptionMessage('pilot_owner_permission_required');
        \trainerPilotCreate($pdo, 'Fixture Owner', 'pilot@example.test');
    }

    public function testAuditFailureRollsBackEveryFixtureWrite(): void
    {
        $pdo = $this->database();
        $pdo->exec("CREATE TRIGGER reject_pilot_audit BEFORE INSERT ON coach_scope_events BEGIN SELECT RAISE(ABORT,'fixture-audit-failure'); END");
        try {
            \trainerPilotCreate($pdo, 'Fixture Owner', 'pilot@example.test');
            self::fail('Audit failure must roll back');
        } catch (\PDOException) {
            self::assertFalse($pdo->inTransaction());
        }
        foreach (['club_seasons', 'club_teams', 'club_roster_members', 'coach_scope_grants', 'nastaveni'] as $table) {
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn());
        }
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM sportovci')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM treneri')->fetchColumn());
    }

    public function testHumanScopeChangeIsReportedInsteadOfRepaired(): void
    {
        $pdo = $this->database();
        $result = \trainerPilotCreate($pdo, 'Fixture Owner', 'pilot@example.test');
        $pdo->exec("UPDATE coach_scope_grants SET valid_to='2099-01-01 00:00:00'");
        try {
            \trainerPilotCreate($pdo, 'Fixture Owner', 'pilot@example.test');
            self::fail('Human change must be refused');
        } catch (\RuntimeException $error) {
            self::assertSame('pilot_grant_drift', $error->getMessage());
        }
        self::assertSame('2099-01-01 00:00:00', $pdo->query('SELECT valid_to FROM coach_scope_grants')->fetchColumn());
        self::assertCount(2, $result['member_ids']);
    }
}
