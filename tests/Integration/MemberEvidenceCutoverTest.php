<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/member_evidence_cutover.php';
require_once dirname(__DIR__, 2) . '/includes/member_evidence_guard.php';

final class MemberEvidenceCutoverTest extends TestCase
{
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
