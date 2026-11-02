<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/member_evidence_cutover.php';
require_once dirname(__DIR__, 2) . '/includes/member_evidence_guard.php';
require_once dirname(__DIR__, 2) . '/includes/member_evidence_final_sync.php';

final class MemberEvidenceCutoverTest extends TestCase
{
    private function groupedConfig(): array
    {
        return $this->config() + ['groups' => [['standing_code' => 'GROUP', 'name' => 'LOCALHOST skupina',
            'activity_kind' => 'competitive', 'child_ids' => [1]]]];
    }

    public function testGroupsPreserveRosterIdentityDeduplicateMembersAndKeepPermissions(): void
    {
        $pdo = $this->database();
        \kisRosterCreateTeam($pdo, 1, 7, 'SECOND', 'LOCALHOST druhý', 'kolo', 'vše', 'LOCALHOST fixture');
        \kisRosterGrantPeriod($pdo, 2, 10, 7, 'manual', '2020-01-01', null, 'LOCALHOST překryv');
        $pdo->exec('CREATE TABLE coach_scope_grants(id INTEGER PRIMARY KEY,team_id INTEGER,trainer_id INTEGER)');
        $pdo->exec('INSERT INTO coach_scope_grants VALUES(1,1,7)');
        $config = $this->groupedConfig();
        $config['cutover_on'] = '2026-11-02';
        $config['teams'][] = ['id' => 2, 'standing_code' => 'SECOND-NEW', 'name' => 'LOCALHOST druhý', 'activity_kind' => 'competitive'];
        $config['groups'][0]['child_ids'][] = 2;
        $before = \memberEvidenceSnapshot($pdo);
        $manifest = \memberEvidencePreview($pdo, $config);
        self::assertSame($before, \memberEvidenceSnapshot($pdo));
        self::assertSame(1, $manifest['groups'][0]['membership']['2026-11-02']['count']);
        $result = \memberEvidenceApply($pdo, $manifest, $manifest['fingerprint'], 7, str_repeat('a', 64), true);
        self::assertSame(1, $result['created_groups']);
        self::assertSame(2, $result['created_edges']);
        $parent = (int) $pdo->query("SELECT id FROM club_teams WHERE code='GROUP'")->fetchColumn();
        self::assertSame([], \kisRosterEffectiveMemberIds($pdo, $parent, '2026-11-01'));
        self::assertSame([10], \kisRosterEffectiveMemberIds($pdo, $parent, '2026-11-02'));
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM club_roster_members WHERE team_id=' . $parent)->fetchColumn());
        $after = \memberEvidenceSnapshot($pdo);
        self::assertSame($before['club_roster_members'], $after['club_roster_members']);
        self::assertSame($before['coach_scope_grants'], $after['coach_scope_grants']);
        self::assertTrue(\memberEvidenceApply($pdo, $manifest, $manifest['fingerprint'], 7, str_repeat('a', 64), true)['idempotent']);
        self::assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM club_team_edges')->fetchColumn());
    }

    public function testGroupMapRejectsDuplicateOrUnassignedChildrenAndCodeCollisions(): void
    {
        foreach (['duplicate', 'missing', 'collision', 'unknown'] as $case) {
            $pdo = $this->database();
            $config = $this->groupedConfig();
            if ($case === 'duplicate') {
                $config['groups'][] = array_replace($config['groups'][0], ['standing_code' => 'SECOND-GROUP']);
            } elseif ($case === 'missing') {
                $config['groups'] = [];
            } elseif ($case === 'collision') {
                $config['groups'][0]['standing_code'] = 'PERMANENT';
            } else {
                $config['groups'][0]['child_ids'] = [999];
            }
            $before = \memberEvidenceSnapshot($pdo);
            try {
                \memberEvidencePreview($pdo, $config);
                self::fail('Unsafe group map accepted: ' . $case);
            } catch (\RuntimeException $error) {
                self::assertNotSame('', $error->getMessage());
            }
            self::assertSame($before, \memberEvidenceSnapshot($pdo));
        }
    }

    public function testExistingHierarchyCannotBeSilentlyReparented(): void
    {
        $pdo = $this->database();
        $parent = \kisRosterCreateStandingTeam($pdo, 7, 'EXISTING', 'LOCALHOST existující', 'group', 'LOCALHOST fixture');
        $pdo->prepare('INSERT INTO club_team_edges(parent_team_id,child_team_id,valid_from,actor_trainer_id,reason) VALUES(?,1,?,7,?)')
            ->execute([$parent['id'], '2020-01-01', 'LOCALHOST fixture']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Existující organizační vazby');
        \memberEvidencePreview($pdo, $this->groupedConfig());
    }

    public function testMapChecksExactSourceIdentityBeforeRenaming(): void
    {
        $pdo = $this->database();
        $config = $this->groupedConfig();
        $config['teams'][0]['expected_code'] = 'ANOTHER';
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Původní soupiska');
        \memberEvidencePreview($pdo, $config);
    }

    private function openedDatabase(): PDO
    {
        $pdo = $this->database();
        $migration = require dirname(__DIR__, 2) . '/migrations/20261102172000_member_roster_operations.php';
        $migration['up']($pdo);
        $pdo->exec("UPDATE club_roster_members SET source='kis_shadow' WHERE id=1");
        $config = array_replace($this->config(), ['cutover_on' => '2026-11-02',
            'opening' => ['member_ids' => [1], 'expected_count' => 1, 'final_sync_pending' => true]]);
        $manifest = \memberEvidencePreview($pdo, $config);
        \memberEvidenceApply($pdo, $manifest, $manifest['fingerprint'], 7, str_repeat('a', 64), true);
        $pdo->exec("INSERT INTO sportovci VALUES(11,'aktivni')");
        return $pdo;
    }

    public function testFinalSyncKeepsOpeningDayIsIdempotentAndAllowsExactlyOneBatch(): void
    {
        $pdo = $this->openedDatabase();
        $config = ['source_sha256' => str_repeat('b', 64), 'reason' => 'LOCALHOST poslední aktualizace',
            'operations' => [['action' => 'add', 'team_id' => 1, 'person_id' => 11, 'valid_from' => '2026-11-03']]];
        $manifest = \memberEvidenceFinalSyncPreview($pdo, $config);
        $result = \memberEvidenceFinalSyncApply($pdo, $manifest, $manifest['fingerprint'], 7, str_repeat('a', 64), true);
        self::assertSame('2026-11-02', $result['opening_on']);
        self::assertFalse($result['final_sync_pending']);
        self::assertSame([10], \kisRosterEffectiveMemberIds($pdo, 1, '2026-11-02'));
        self::assertSame([10, 11], \kisRosterEffectiveMemberIds($pdo, 1, '2026-11-03'));
        self::assertTrue(\memberEvidenceFinalSyncApply($pdo, $manifest, $manifest['fingerprint'], 7, str_repeat('a', 64), true)['idempotent']);
        $next = \memberEvidenceFinalSyncPreview($pdo, ['source_sha256' => str_repeat('c', 64), 'reason' => 'LOCALHOST další', 'operations' => []]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Jedna poslední synchronizace už proběhla');
        \memberEvidenceFinalSyncApply($pdo, $next, $next['fingerprint'], 7, str_repeat('a', 64), true);
    }

    public function testFinalSyncCannotRecreatePreOpeningHistory(): void
    {
        $pdo = $this->openedDatabase();
        $this->expectException(\InvalidArgumentException::class);
        \memberEvidenceFinalSyncPreview($pdo, ['source_sha256' => str_repeat('b', 64), 'reason' => 'LOCALHOST historie',
            'operations' => [['action' => 'add', 'team_id' => 1, 'person_id' => 11, 'valid_from' => '2026-11-01']]]);
    }

    public function testFinalSyncDoesNotDuplicateExistingOpeningMembership(): void
    {
        $pdo = $this->openedDatabase();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('duplicitu');
        \memberEvidenceFinalSyncPreview($pdo, ['source_sha256' => str_repeat('b', 64), 'reason' => 'LOCALHOST opakovaný vstup',
            'operations' => [['action' => 'add', 'team_id' => 1, 'person_id' => 10, 'valid_from' => '2026-11-02']]]);
    }

    public function testFinalSyncEndsOnlySelectedOpeningPeriodWithInclusiveLastDay(): void
    {
        $pdo = $this->openedDatabase();
        $manifest = \memberEvidenceFinalSyncPreview($pdo, ['source_sha256' => str_repeat('b', 64), 'reason' => 'LOCALHOST ukončení',
            'operations' => [['action' => 'end', 'member_id' => 1, 'valid_to' => '2026-11-03']]]);
        \memberEvidenceFinalSyncApply($pdo, $manifest, $manifest['fingerprint'], 7, str_repeat('a', 64), true);
        self::assertSame([10], \kisRosterEffectiveMemberIds($pdo, 1, '2026-11-03'));
        self::assertSame([], \kisRosterEffectiveMemberIds($pdo, 1, '2026-11-04'));
        self::assertSame('2026-11-02', $pdo->query('SELECT valid_from FROM club_roster_members WHERE id=1')->fetchColumn());
    }

    public function testFinalSyncRejectsRevokedActorBeforeWriting(): void
    {
        $pdo = $this->openedDatabase();
        $manifest = \memberEvidenceFinalSyncPreview($pdo, ['source_sha256' => str_repeat('b', 64), 'reason' => 'LOCALHOST shoda', 'operations' => []]);
        $pdo->exec('DELETE FROM staff_user_positions');
        $this->expectException(\DomainException::class);
        \memberEvidenceFinalSyncApply($pdo, $manifest, $manifest['fingerprint'], 7, str_repeat('a', 64), true);
    }

    public function testFinalSyncRejectsChangedApplicationData(): void
    {
        $pdo = $this->openedDatabase();
        $manifest = \memberEvidenceFinalSyncPreview($pdo, ['source_sha256' => str_repeat('b', 64), 'reason' => 'LOCALHOST shoda', 'operations' => []]);
        $pdo->exec("UPDATE sportovci SET stav_clenstvi='archiv' WHERE id=11");
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Databáze se od náhledu synchronizace změnila');
        \memberEvidenceFinalSyncApply($pdo, $manifest, $manifest['fingerprint'], 7, str_repeat('a', 64), true);
    }

    public function testOpeningDateAndHistoricalCleanupPreserveCurrentMappingForFinalSync(): void
    {
        $pdo = $this->database();
        $pdo->exec("UPDATE club_roster_members SET source='kis_shadow' WHERE id=1");
        $pdo->exec("INSERT INTO club_roster_members(team_id,sportovec_id,status,source,valid_from,valid_to,created_by_trainer_id) "
            . "VALUES(1,10,'removed','kis_shadow','2019-01-01','2019-12-31',7)");
        $history = (int) $pdo->lastInsertId();
        \kisRosterEvent($pdo, 1, $history, 7, 'import_legacy_member', null, ['id' => $history], 'LOCALHOST historie');
        $pdo->exec('CREATE TABLE kis_legacy_roster_claims(id INTEGER PRIMARY KEY,status TEXT,member_id INTEGER REFERENCES club_roster_members(id))');
        $pdo->exec('CREATE TABLE kis_current_roster_claims(id INTEGER PRIMARY KEY,legacy_claim_id INTEGER REFERENCES kis_legacy_roster_claims(id),'
            . 'member_id INTEGER REFERENCES club_roster_members(id))');
        $pdo->exec("INSERT INTO kis_legacy_roster_claims VALUES(1,'matched',$history)");
        $pdo->exec('INSERT INTO kis_current_roster_claims VALUES(1,1,1)');
        $config = \memberEvidencePrepareOpening($pdo, array_replace($this->config(), ['cutover_on' => '2026-11-02']), 1, 1);
        $manifest = \memberEvidencePreview($pdo, $config);
        $result = \memberEvidenceApply($pdo, $manifest, $manifest['fingerprint'], 7, str_repeat('a', 64), true);
        self::assertSame(1, $result['opening_count']);
        self::assertTrue($result['final_sync_pending']);
        self::assertSame('2026-11-02', $result['opening_on']);
        self::assertSame(['club_roster_members' => 1, 'club_roster_events' => 1], $result['deleted']);
        self::assertSame([], \kisRosterEffectiveMemberIds($pdo, 1, '2026-11-01'));
        self::assertSame([10], \kisRosterEffectiveMemberIds($pdo, 1, '2026-11-02'));
        $row = $pdo->query('SELECT source,source_ref,valid_from FROM club_roster_members WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['source' => 'manual', 'source_ref' => 'kis-opening:1', 'valid_from' => '2026-11-02'], $row);
        self::assertNull($pdo->query('SELECT member_id FROM kis_legacy_roster_claims WHERE id=1')->fetchColumn());
        self::assertSame(1, (int) $pdo->query('SELECT member_id FROM kis_current_roster_claims WHERE id=1')->fetchColumn());
        self::assertTrue(\memberEvidenceApply($pdo, $manifest, $manifest['fingerprint'], 7, str_repeat('a', 64), true)['idempotent']);
    }

    public function testOpeningCannotSilentlyOmitAnActiveImportedMember(): void
    {
        $pdo = $this->database();
        $config = $this->config() + ['opening' => ['member_ids' => [1], 'expected_count' => 1]];
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Aktivní výchozí členství');
        \memberEvidencePreview($pdo, $config);
    }

    public function testApplicationScopeAllowsIndependentForeignWritesAndPreservesThem(): void
    {
        $pdo = $this->database();
        $config = $this->config() + ['scope' => 'application_backup'];
        $manifest = \memberEvidencePreview($pdo, $config);
        self::assertArrayHasKey('club_roster_members', $manifest['snapshot']);
        self::assertArrayNotHasKey('retained_finance', $manifest['snapshot']);
        $pdo->exec('UPDATE retained_finance SET amount_minor=45678 WHERE id=1');
        $result = \memberEvidenceApply($pdo, $manifest, $manifest['fingerprint'], 7, str_repeat('a', 64), true);
        self::assertTrue($result['preserved_parity']);
        self::assertSame(45678, (int) $pdo->query('SELECT amount_minor FROM retained_finance WHERE id=1')->fetchColumn());
    }

    public function testApplicationScopeRejectsNewForeignBoundaryEvenWithoutRows(): void
    {
        $pdo = $this->database();
        $config = $this->config() + ['scope' => 'application_backup'];
        $manifest = \memberEvidencePreview($pdo, $config);
        $pdo->exec('CREATE TABLE foreign_app(id INTEGER PRIMARY KEY,team_id INTEGER REFERENCES club_teams(id) ON DELETE CASCADE)');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cizí FK');
        \memberEvidenceApply($pdo, $manifest, $manifest['fingerprint'], 7, str_repeat('a', 64), true);
    }

    public function testApplicationScopeStillRejectsAChangedPayment(): void
    {
        $pdo = $this->database();
        $pdo->exec('CREATE TABLE payments(id INTEGER PRIMARY KEY,amount_minor INTEGER)');
        $pdo->exec('INSERT INTO payments VALUES(1,12345)');
        $manifest = \memberEvidencePreview($pdo, $this->config() + ['scope' => 'application_backup']);
        $pdo->exec('UPDATE payments SET amount_minor=45678 WHERE id=1');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Databáze se od náhledu změnila');
        \memberEvidenceApply($pdo, $manifest, $manifest['fingerprint'], 7, str_repeat('a', 64), true);
    }

    public function testSnapshotIncludesLegacyIdentifiersWithoutInterpretingSql(): void
    {
        $pdo = $this->database();
        $name = '2020 archive-` ; DROP TABLE retained_finance; --';
        $identifier = \memberEvidenceIdentifier($name);
        $pdo->exec('CREATE TABLE ' . $identifier . '(id INTEGER PRIMARY KEY,value TEXT)');
        $pdo->exec('INSERT INTO ' . $identifier . " VALUES(1,'LOCALHOST retained')");
        $before = \memberEvidenceSnapshot($pdo);
        self::assertSame(1, $before[$name]['count']);
        $manifest = \memberEvidencePreview($pdo, $this->config());
        \memberEvidenceApply($pdo, $manifest, $manifest['fingerprint'], 7, str_repeat('a', 64), true);
        $after = \memberEvidenceSnapshot($pdo);
        self::assertSame($before[$name], $after[$name]);
        self::assertSame($before['retained_finance'], $after['retained_finance']);
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT,aktivni INTEGER)');
        $pdo->exec("INSERT INTO treneri VALUES(7,'LOCALHOST registrář',1)");
        $pdo->exec('CREATE TABLE staff_user_positions(trainer_id INTEGER,position_code TEXT)');
        $pdo->exec("INSERT INTO staff_user_positions VALUES(7,'registrar')");
        $pdo->exec('CREATE TABLE staff_superadmins(trainer_id INTEGER)');
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,stav_clenstvi TEXT)');
        $pdo->exec("INSERT INTO sportovci VALUES(10,'aktivni')");
        foreach (['20260804090000_kis_teams_rosters','20260804110000_kis_roster_policies',
            '20260822170000_club_roster_structure_events','20261021090000_roster_hierarchy_temporal',
            '20261102170000_roster_activity_kind','20261102174000_member_evidence_cutover_runs'] as $name) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $name . '.php';
            $migration['up']($pdo);
        }
        $season = \kisRosterCreateSeason(
            $pdo,
            7,
            'OLD',
            'LOCALHOST původní',
            '2020-01-01',
            '2020-12-31',
            'calendar_year'
        );
        \kisRosterCreateTeam($pdo, $season['id'], 7, 'OLD', 'LOCALHOST původní', 'kolo', 'vše', 'LOCALHOST fixture');
        \kisRosterGrantPeriod($pdo, 1, 10, 7, 'manual', '2020-01-01', null, 'LOCALHOST živé členství');
        $pdo->exec('CREATE TABLE retained_finance(id INTEGER PRIMARY KEY,amount_minor INTEGER,currency TEXT)');
        $pdo->exec("INSERT INTO retained_finance VALUES(1,12345,'CZK')");
        return $pdo;
    }

    private function config(): array
    {
        return ['cutover_on' => date('Y-m-d'),'reason' => 'LOCALHOST řízený přechod',
            'teams' => [['id' => 1,'standing_code' => 'PERMANENT','name' => 'LOCALHOST tým','activity_kind'
    => 'competitive']], 'delete' => []];
    }

    public function testPreviewApplyRetryPreserveIdentityMembershipFinanceAndStopLegacyWrites(): void
    {
        $pdo = $this->database();
        $before = \memberEvidenceSnapshot($pdo);
        $manifest = \memberEvidencePreview($pdo, $this->config());
        self::assertSame($before, \memberEvidenceSnapshot($pdo), 'Preview must not write.');
        $result = \memberEvidenceApply(
            $pdo,
            $manifest,
            $manifest['fingerprint'],
            7,
            str_repeat('a', 64),
            true
        );
        self::assertTrue($result['preserved_parity']);
        self::assertNull($pdo->query('SELECT season_id FROM club_teams')->fetchColumn());
        self::assertSame([10], \kisRosterEffectiveMemberIds($pdo, 1, date('Y-m-d')));
        self::assertSame($before['retained_finance'], \memberEvidenceSnapshot($pdo)['retained_finance']);
        self::assertTrue(\memberEvidenceApply($pdo, $manifest, $manifest['fingerprint'], 7, str_repeat(
            'a',
            64
        ), true)['idempotent']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM member_evidence_cutover_runs')->fetchColumn());
        $this->expectException(\RuntimeException::class);
        \kisRosterCreateSeason(
            $pdo,
            7,
            'REFILL',
            'LOCALHOST zakázaný návrat',
            '2020-01-01',
            '2020-12-31',
            'calendar_year'
        );
    }

    public function testSchemaChangeWithUnchangedRowsInvalidatesPreview(): void
    {
        $pdo = $this->database();
        $pdo->exec('CREATE TABLE empty_dependency(id INTEGER PRIMARY KEY)');
        $manifest = \memberEvidencePreview($pdo, $this->config());
        $pdo->exec('ALTER TABLE empty_dependency ADD COLUMN reference_id INTEGER');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Databáze se od náhledu změnila');
        \memberEvidenceApply($pdo, $manifest, $manifest['fingerprint'], 7, str_repeat('a', 64), true);
    }

    public function testStaleSnapshotAndTamperedManifestNeverPartiallyConvert(): void
    {
        $pdo = $this->database();
        $manifest = \memberEvidencePreview($pdo, $this->config());
        $pdo->exec('UPDATE retained_finance SET amount_minor=999 WHERE id=1');
        try {
            \memberEvidenceApply($pdo, $manifest, $manifest['fingerprint'], 7, str_repeat('a', 64), true);
            self::fail('Stale manifest was accepted.');
        } catch (\RuntimeException) {
            self::assertSame(1, (int)$pdo->query('SELECT season_id FROM club_teams')->fetchColumn());
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM member_evidence_cutover_runs')->fetchColumn());
        }
        $manifest['config']['teams'][0]['name'] = 'ALTERED';
        $this->expectException(\RuntimeException::class);
        \memberEvidenceApply($pdo, $manifest, $manifest['fingerprint'], 7, str_repeat('a', 64), true);
    }

    public function testOnlyExpiredImportedMembershipMayBePurgedAndSetNullDependencyBlocks(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO club_roster_members(team_id,sportovec_id,status,source,valid_from,valid_to,created_by_trainer_id) "
            . "VALUES(1,10,'removed','kis_shadow','2020-01-01','2020-12-31',7)");
        $id = (int)$pdo->lastInsertId();
        $config = $this->config();
        $config['delete'] = [['table' => 'club_roster_members','id' => $id,'classification' => 'imported_history',

    'reason' => 'LOCALHOST starý import']];
        $pdo->exec('CREATE TABLE retained_attendance(id INTEGER PRIMARY KEY,member_id INTEGER REFERENCES club_roster_members(id) ON DELETE SET NULL)');
        $pdo->prepare('INSERT INTO retained_attendance VALUES(1,?)')->execute([$id]);
        try {
            \memberEvidencePreview($pdo, $config);
            self::fail('SET NULL must not silently alter retained attendance.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('retained_attendance', $error->getMessage());
        }
        $pdo->exec('DELETE FROM retained_attendance');
        $manifest = \memberEvidencePreview($pdo, $config);
        $result = \memberEvidenceApply(
            $pdo,
            $manifest,
            $manifest['fingerprint'],
            7,
            str_repeat('a', 64),
            true
        );
        self::assertSame(['club_roster_members' => 1], $result['deleted']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM sportovci')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn());
    }

    public function testManualMemberCannotBeDeletedAsOldImport(): void
    {
        $pdo = $this->database();
        $config = $this->config();
        $config['delete'] = [['table' => 'club_roster_members','id' => 1,'classification' => 'imported_history',

    'reason' => 'LOCALHOST chybná klasifikace']];
        $this->expectException(\RuntimeException::class);
        \memberEvidencePreview($pdo, $config);
    }
}
