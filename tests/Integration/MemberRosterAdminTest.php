<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/member_roster_admin.php';
require_once dirname(__DIR__, 2) . '/includes/staff_workspaces.php';

final class MemberRosterAdminTest extends TestCase
{
    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT,aktivni INTEGER)');
        $pdo->exec("INSERT INTO treneri VALUES(7,'LOCALHOST registrář',1)");
        $pdo->exec('CREATE TABLE staff_user_positions(trainer_id INTEGER,position_code TEXT)');
        $pdo->exec("INSERT INTO staff_user_positions VALUES(7,'registrar')");
        $pdo->exec('CREATE TABLE staff_superadmins(trainer_id INTEGER)');
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,stav_clenstvi TEXT)');
        $pdo->exec("INSERT INTO sportovci VALUES(10,'LOCALHOST','Anna','aktivni'),(11,'LOCALHOST','Bára','aktivni')");
        foreach (['20260804090000_kis_teams_rosters', '20260804110000_kis_roster_policies',
            '20260822170000_club_roster_structure_events', '20261021090000_roster_hierarchy_temporal',


            '20261102170000_roster_activity_kind', '20261102172000_member_roster_operations',
            '20261102173000_member_roster_age_rules'] as $name) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $name . '.php';
            $migration['up']($pdo);
            self::assertTrue($migration['verify']($pdo));
        }
        $_SESSION = ['trener_id' => 7, 'staff_active_position' => 'registrar', 'staff_positions' => ['registrar']];
        return $pdo;
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function apply(PDO $pdo, array $input): array
    {
        if (in_array($input['action'], ['grant','end_member','transfer'], true)) {
            $input['fingerprint'] = \memberRosterPreview($pdo, $input)['fingerprint'];
        }
        return \memberRosterMutate($pdo, $input);
    }

    private function team(PDO $pdo, string $code, string $kind = 'roster'): int
    {
        $result = $this->apply($pdo, ['action' => 'create', 'code' => $code, 'name' => 'LOCALHOST ' . $code,


            'kind' => $kind, 'activity_kind' => 'competitive', 'reason' => 'LOCALHOST fixture']);
        return (int) $result['id'];
    }

    public function testStandingRosterBatchMembershipAndInclusiveTransfer(): void
    {
        $pdo = $this->database();
        $source = $this->team($pdo, 'SOURCE');
        $target = $this->team($pdo, 'TARGET');
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM club_seasons')->fetchColumn());
        $grant = ['action' => 'grant', 'team_id' => $source, 'person_ids' => [10, 11],
            'valid_from' => '2026-11-01', 'reason' => 'LOCALHOST přijetí'];
        self::assertSame(2, $this->apply($pdo, $grant)['created_count']);
        self::assertSame(0, $this->apply($pdo, $grant)['created_count']);
        $memberId = (int) $pdo->query('SELECT id FROM club_roster_members WHERE sportovec_id=10')->fetchColumn();
        $this->apply($pdo, ['action' => 'transfer', 'team_id' => $source, 'target_team_id' => $target,


            'member_id' => $memberId, 'valid_from' => '2027-01-01', 'reason' => 'LOCALHOST přestup']);
        self::assertSame([10, 11], \kisRosterEffectiveMemberIds($pdo, $source, '2026-12-31'));
        self::assertSame([11], \kisRosterEffectiveMemberIds($pdo, $source, '2027-01-01'));
        self::assertSame([10], \kisRosterEffectiveMemberIds($pdo, $target, '2027-01-01'));
        self::assertCount(2, \memberRosterRead($pdo, $source, '2027-01-01')['members']);
        self::assertSame([11], \memberRosterRead($pdo, $source, '2027-01-01')['effective_ids']);
    }

    public function testFailedBatchAndFailedTransferRollBackEveryChange(): void
    {
        $pdo = $this->database();
        $source = $this->team($pdo, 'SOURCE');
        $group = $this->team($pdo, 'GROUP', 'group');
        try {
            $this->apply($pdo, ['action' => 'grant', 'team_id' => $source, 'person_ids' => [10, 999],
                'valid_from' => '2026-11-01', 'reason' => 'LOCALHOST chybná dávka']);
            self::fail('Unknown person must roll back the first grant.');
        } catch (\KisRosterException $error) {
            self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn());
        }
        $grant = \kisRosterGrantPeriod($pdo, $source, 10, 7, 'manual', '2026-11-01', null, 'LOCALHOST původní');
        try {
            $this->apply($pdo, ['action' => 'transfer', 'team_id' => $source, 'target_team_id' => $group,


                'member_id' => $grant['id'], 'valid_from' => '2026-12-01', 'reason' => 'LOCALHOST chybný cíl']);
            self::fail('Direct membership in a group must be rejected.');
        } catch (\KisRosterException $error) {
            self::assertNull($pdo->query('SELECT valid_to FROM club_roster_members')->fetchColumn());
            self::assertSame([10], \kisRosterEffectiveMemberIds($pdo, $source, '2027-01-01'));
        }
    }

    public function testTransferPreviewPeriodsMatchPersistedIntervalsDespiteUnrelatedEndField(): void
    {
        foreach (['2026-11-10', null] as $originalEnd) {
            $pdo = $this->database();
            $source = $this->team($pdo, 'SOURCE');
            $target = $this->team($pdo, 'TARGET');
            $member = \kisRosterGrantPeriod($pdo, $source, 10, 7, 'manual', '2026-11-05', $originalEnd, 'LOCALHOST členství');
            $input = ['action' => 'transfer', 'team_id' => $source, 'target_team_id' => $target,
                'member_id' => $member['id'], 'valid_from' => '2026-11-08', 'valid_to' => '2026-11-04',
                'reason' => 'LOCALHOST náhled přestupu'];
            $preview = \memberRosterPreview($pdo, $input);
            self::assertArrayHasKey('periods', $preview);
            self::assertSame([
                ['team_id' => $source, 'name' => 'LOCALHOST SOURCE', 'valid_from' => '2026-11-05', 'valid_to' => '2026-11-07'],
                ['team_id' => $target, 'name' => 'LOCALHOST TARGET', 'valid_from' => '2026-11-08', 'valid_to' => $originalEnd],
            ], $preview['periods']);
            $input['fingerprint'] = $preview['fingerprint'];
            \memberRosterMutate($pdo, $input);
            $stored = $pdo->query('SELECT team_id,valid_from,valid_to FROM club_roster_members ORDER BY team_id')->fetchAll(PDO::FETCH_ASSOC);
            $shown = array_map(static fn (array $period): array => array_diff_key($period, ['name' => true]), $preview['periods']);
            self::assertSame($stored, $shown);
        }
    }

    public function testGrantAndEndPreviewsShowTheActualMembershipPeriod(): void
    {
        $pdo = $this->database();
        $team = $this->team($pdo, 'SOURCE');
        $input = ['action' => 'grant', 'team_id' => $team, 'person_ids' => [10],
            'valid_from' => '2026-11-05', 'valid_to' => '2026-11-10', 'reason' => 'LOCALHOST náhled zařazení'];
        $preview = \memberRosterPreview($pdo, $input);
        self::assertArrayHasKey('periods', $preview);
        self::assertSame([['team_id' => $team, 'name' => 'LOCALHOST SOURCE', 'valid_from' => '2026-11-05',
            'valid_to' => '2026-11-10']], $preview['periods']);
        $input['fingerprint'] = $preview['fingerprint'];
        \memberRosterMutate($pdo, $input);
        $memberId = (int) $pdo->query('SELECT id FROM club_roster_members')->fetchColumn();
        $end = ['action' => 'end_member', 'team_id' => $team, 'member_id' => $memberId,
            'valid_from' => '2026-11-04', 'valid_to' => '2026-11-08', 'reason' => 'LOCALHOST náhled ukončení'];
        $preview = \memberRosterPreview($pdo, $end);
        self::assertSame([['team_id' => $team, 'name' => 'LOCALHOST SOURCE', 'valid_from' => '2026-11-05',
            'valid_to' => '2026-11-08']], $preview['periods']);
        $end['fingerprint'] = $preview['fingerprint'];
        \memberRosterMutate($pdo, $end);
        self::assertSame('2026-11-08', $pdo->query('SELECT valid_to FROM club_roster_members')->fetchColumn());
    }

    public function testEndingBeforeMembershipStartsPreviewsCancellationInsteadOfAnInvertedPeriod(): void
    {
        $pdo = $this->database();
        $team = $this->team($pdo, 'FUTURE');
        $member = \kisRosterGrantPeriod($pdo, $team, 10, 7, 'manual', '2099-11-05', null, 'LOCALHOST budoucí členství');
        $input = ['action' => 'end_member', 'team_id' => $team, 'member_id' => $member['id'],
            'valid_to' => '2099-11-04', 'reason' => 'LOCALHOST zrušení před začátkem'];
        $preview = \memberRosterPreview($pdo, $input);
        self::assertTrue($preview['periods'][0]['cancelled'] ?? false);
        self::assertSame('2099-11-05', $preview['periods'][0]['valid_to']);
        $input['fingerprint'] = $preview['fingerprint'];
        \memberRosterMutate($pdo, $input);
        self::assertSame('cancelled', $pdo->query('SELECT status FROM club_roster_members')->fetchColumn());
        self::assertSame([], \kisRosterEffectiveMemberIds($pdo, $team, '2099-11-05'));
    }

    public function testRevokedRegistrarCannotWriteDespiteSessionSnapshot(): void
    {
        $pdo = $this->database();
        $pdo->exec('DELETE FROM staff_user_positions');
        $this->expectException(\DomainException::class);
        $this->team($pdo, 'DENIED');
    }

    public function testPaidEntitlementCannotBeEndedThroughManualRosterAction(): void
    {
        $pdo = $this->database();
        $team = $this->team($pdo, 'PAID');
        $member = \kisRosterGrantPeriod(
            $pdo,
            $team,
            10,
            7,
            'shop',
            '2026-11-01',
            '2027-01-31',
            'LOCALHOST nákup',
            'test-item-1'
        );
        try {
            $this->apply($pdo, ['action' => 'end_member', 'team_id' => $team, 'member_id' => $member['id'],


                'valid_to' => '2026-11-30', 'reason' => 'LOCALHOST pokus']);
            self::fail('Paid entitlement requires its source workflow.');
        } catch (\KisRosterException $error) {
            self::assertSame('2027-01-31', $pdo->query('SELECT valid_to FROM club_roster_members')->fetchColumn());
        }
    }

    public function testLegacySeasonMutationIsNotAnActionOfTheNewController(): void
    {
        $pdo = $this->database();
        $this->expectException(\InvalidArgumentException::class);
        $this->apply($pdo, ['action' => 'create_season', 'reason' => 'LOCALHOST starý formulář']);
    }

    public function testConfirmedTransferRetryAndStaleGrant(): void
    {
        $pdo = $this->database();
        $source = $this->team($pdo, 'SOURCE');
        $target = $this->team($pdo, 'TARGET');
        $member = \kisRosterGrantPeriod($pdo, $source, 10, 7, 'manual', '2026-01-01', null, 'LOCALHOST členství');
        $input = ['action' => 'transfer','team_id' => $source,'target_team_id' => $target,'member_id'
    => $member['id'],
            'valid_from' => '2027-01-01','reason' => 'LOCALHOST potvrzený přestup'];
        $input['fingerprint'] = \memberRosterPreview($pdo, $input)['fingerprint'];
        $first = \memberRosterMutate($pdo, $input);
        $retry = \memberRosterMutate($pdo, $input);
        self::assertTrue($retry['idempotent']);
        self::assertSame($first['target']['id'], $retry['target']['id']);
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn());
        $grant = ['action' => 'grant','team_id' => $target,'person_ids' => [11],'valid_from' => '2027-01-01',

    'reason' => 'LOCALHOST náhled'];
        $grant['fingerprint'] = \memberRosterPreview($pdo, $grant)['fingerprint'];
        $pdo->exec("UPDATE sportovci SET stav_clenstvi='archiv' WHERE id=11");
        try {
            \memberRosterMutate($pdo, $grant);
            self::fail('Stale preview must not write.');
        } catch (\KisRosterException) {
            self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn());
        }
    }

    public function testFutureResponsePreventsSilentMembershipLoss(): void
    {
        $pdo = $this->database();
        $team = $this->team($pdo, 'SOURCE');
        $member = \kisRosterGrantPeriod($pdo, $team, 10, 7, 'manual', '2026-01-01', null, 'LOCALHOST členství');
        $pdo->exec('CREATE TABLE planovane_treninky(id INTEGER,nazev TEXT,datum TEXT,stav TEXT)');
        $pdo->exec('CREATE TABLE training_roster_links(plan_id INTEGER,team_id INTEGER)');
        $pdo->exec('CREATE TABLE training_rsvps(plan_id INTEGER,sportovec_id INTEGER)');
        $pdo->exec("INSERT INTO planovane_treninky VALUES(1,'LOCALHOST lekce','2099-06-01','planovany')");
        $pdo->prepare('INSERT INTO training_roster_links VALUES(1,?)')->execute([$team]);
        $pdo->exec('INSERT INTO training_rsvps VALUES(1,10)');
        $input = ['action' => 'end_member','team_id' => $team,'member_id' => $member['id'],'valid_to'
    => '2099-05-31','reason' => 'LOCALHOST konec'];
        $preview = \memberRosterPreview($pdo, $input);
        self::assertTrue($preview['impact']['blocked']);
        $input['fingerprint'] = $preview['fingerprint'];
        try {
            \memberRosterMutate($pdo, $input);
            self::fail('Existing response must be resolved first.');
        } catch (\KisRosterException) {
            self::assertNull($pdo->query('SELECT valid_to FROM club_roster_members')->fetchColumn());
        }
    }

    public function testLastMembershipDayIsStillEligibleAndFutureMakeupBlocksEnding(): void
    {
        $pdo = $this->database();
        $team = $this->team($pdo, 'SOURCE');
        $member = \kisRosterGrantPeriod($pdo, $team, 10, 7, 'manual', '2026-01-01', null, 'LOCALHOST členství');
        $pdo->exec('CREATE TABLE planovane_treninky(id INTEGER,nazev TEXT,datum TEXT,stav TEXT)');
        $pdo->exec('CREATE TABLE training_roster_links(plan_id INTEGER,team_id INTEGER)');
        $pdo->exec('CREATE TABLE training_rsvps(plan_id INTEGER,sportovec_id INTEGER)');
        $pdo->exec('CREATE TABLE club_makeup_reservations(id INTEGER,target_plan_id INTEGER,sportovec_id INTEGER,status TEXT)');
        $pdo->exec("INSERT INTO planovane_treninky VALUES(1,'LOCALHOST lekce','2099-06-01','planovany')");
        $pdo->prepare('INSERT INTO training_roster_links VALUES(1,?)')->execute([$team]);
        $pdo->exec("INSERT INTO club_makeup_reservations VALUES(1,1,10,'booked')");
        $input = ['action' => 'end_member','team_id' => $team,'member_id' => $member['id'],
            'valid_to' => '2099-05-31','reason' => 'LOCALHOST kontrola dne'];
        self::assertTrue(\memberRosterPreview($pdo, $input)['impact']['blocked']);
        $input['valid_to'] = '2099-06-01';
        self::assertFalse(\memberRosterPreview($pdo, $input)['impact']['blocked']);
        $this->apply($pdo, $input);
        self::assertSame([10], \kisRosterEffectiveMemberIds($pdo, $team, '2099-06-01'));
        self::assertSame([], \kisRosterEffectiveMemberIds($pdo, $team, '2099-06-02'));
    }

    public function testAgeRuleUsesExplicitReferenceDateAndNeverMovesUnknownBirthday(): void
    {
        $pdo = $this->database();
        $team = $this->team($pdo, 'YOUTH');
        $target = $this->team($pdo, 'OLDER');
        $pdo->exec('ALTER TABLE sportovci ADD COLUMN narozeni TEXT');
        $pdo->exec("UPDATE sportovci SET narozeni='2011-12-20' WHERE id=10");
        $this->apply($pdo, ['action' => 'grant','team_id' => $team,'person_ids' => [10,11],'valid_from'
    => '2026-01-01','reason' => 'LOCALHOST členství']);
        $rule = ['action' => 'age_rule','team_id' => $team,'age_min' => 12,'age_max' => 14,'reference_mode'
    => 'on_date',
            'next_team_id' => $target,'reason' => 'LOCALHOST věkové pravidlo'];
        $this->apply($pdo, $rule);
        $review = \memberRosterAgeReview($pdo, $team, '2026-09-01');
        self::assertSame(['eligible','unknown'], array_column($review['rows'], 'review'));
        $this->apply($pdo, array_replace($rule, ['reference_mode' => 'year_end']));
        $review = \memberRosterAgeReview($pdo, $team, '2026-09-01');
        self::assertSame(['too_old','unknown'], array_column($review['rows'], 'review'));
        self::assertSame($target, (int)$review['rows'][0]['suggested_team_id']);
        self::assertSame([10,11], \kisRosterEffectiveMemberIds($pdo, $team, '2027-01-01'));
        self::assertSame([], \kisRosterEffectiveMemberIds($pdo, $target, '2027-01-01'));
    }
}
