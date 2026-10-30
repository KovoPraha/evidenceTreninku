<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\QueryProfilePdo;

require_once dirname(__DIR__) . '/Support/QueryProfilePdo.php';
require_once dirname(__DIR__) . '/Support/ClubCalendarReadBaseline.php';
require_once dirname(__DIR__) . '/Support/ClubCalendarReadFixture.php';

final class ClubCalendarReadOptimizationTest extends TestCase
{
    public function testWholeCalendarPreservesVisibilityAndHasBoundedAttachmentQueries(): void
    {
        $pdo = $this->database();
        for ($i = 0; $i < 12; $i++) {
            $id = $this->event($pdo, ['visibility' => ['public', 'members', 'staff', 'rosters'][$i % 4], 'team_ids' => [10]]);
            $pdo->prepare("INSERT INTO club_event_sessions(event_id,starts_at,ends_at,capacity_override,status) VALUES(?,? ,?,7,'scheduled')")
                ->execute([$id, '2026-09-11 10:00:00', '2026-09-11 11:00:00']);
            \clubCalendarAddPerson($pdo, $id, 1, ['trainer_id' => 1, 'visible_to_members' => 1, 'external_contact' => 'PRIVATE', 'note' => 'PRIVATE']);
            \clubCalendarAddPerson($pdo, $id, 1, ['external_name' => 'HIDDEN', 'note' => 'PRIVATE']);
            \clubCalendarAddLink($pdo, $id, 1, ['label' => 'Map', 'url' => 'https://example.test/map']);
        }
        foreach ([[null, true], [null, false], [100, false], [101, false]] as [$account, $staff]) {
            $expected = \w84BaselineClubCalendarEvents($pdo, '2026-01-01', '2026-12-31', $account, $staff);
            $pdo->profile = [];
            $pdo->profiling = true;
            $actual = \clubCalendarEvents($pdo, '2026-01-01', '2026-12-31', $account, $staff);
            $pdo->profiling = false;
            self::assertSame($expected, $actual);
            if (!$staff) {
                self::assertStringNotContainsString('PRIVATE', json_encode($actual, JSON_THROW_ON_ERROR));
                self::assertStringNotContainsString('HIDDEN', json_encode($actual, JSON_THROW_ON_ERROR));
            }
            $links = array_filter($pdo->profile, static fn (array $q): bool => str_contains($q['sql'], 'FROM club_event_links'));
            self::assertLessThanOrEqual(1, count($links));
        }
        $pdo->exec("UPDATE account_person_roles SET status='revoked' WHERE account_id=100");
        self::assertSame(\w84BaselineClubCalendarEvents($pdo, '2026-01-01', '2026-12-31', 100), \clubCalendarEvents($pdo, '2026-01-01', '2026-12-31', 100));
    }

    public function testVehicleOverlapBoundariesCancelledSourceAndFreshChanges(): void
    {
        $pdo = $this->database();
        $ids = [$this->event($pdo), $this->event($pdo), $this->event($pdo)];
        foreach ([['09:00', '10:00'], ['10:00', '11:00'], ['09:30', '10:30']] as $i => [$start, $end]) {
            \clubCalendarReserveVehicle($pdo, $ids[$i], 1, ['vehicle_id' => 1, 'starts_at' => '2026-09-10T' . $start,
                'ends_at' => '2026-09-10T' . $end, 'conflict_acknowledged' => 1, 'conflict_note' => 'Synthetic']);
        }
        foreach ($ids as $id) {
            self::assertSame(\w84BaselineClubCalendarVehicles($pdo, $id), \clubCalendarVehicles($pdo, $id));
            self::assertSame(\w84BaselineClubCalendarDetail($pdo, $id), \clubCalendarDetail($pdo, $id));
        }
        $pdo->exec("UPDATE club_event_vehicle_reservations SET status='cancelled' WHERE id=1");
        self::assertSame(\w84BaselineClubCalendarEvents($pdo, '2026-09-01', '2026-09-30', null, true), \clubCalendarEvents($pdo, '2026-09-01', '2026-09-30', null, true));
        $pdo->exec("UPDATE club_event_vehicle_reservations SET ends_at='2026-09-10 09:00:00' WHERE id=3");
        self::assertSame(\w84BaselineClubCalendarVehicles($pdo, $ids[0]), \clubCalendarVehicles($pdo, $ids[0]));
        self::assertNull(\clubCalendarDetail($pdo, 9999));
        self::assertSame([], \clubCalendarEvents($pdo, '2025-01-01', '2025-01-02', null, true));
    }

    public function testChunkBoundaryCountsDeduplicationAndNullableLegacyRegistration(): void
    {
        $pdo = $this->database();
        $event = $pdo->prepare("INSERT INTO club_events(id,name,capacity,status,visibility) VALUES(?,'Synthetic',20,'open','public')");
        $session = $pdo->prepare("INSERT INTO club_event_sessions(event_id,starts_at,ends_at,status) VALUES(?,'2026-09-10 09:00:00','2026-09-10 10:00:00','scheduled')");
        for ($id = 1; $id <= 401; $id++) {
            $event->execute([$id]);
            $session->execute([$id]);
        }
        $pdo->exec("INSERT INTO club_event_planned_participants(event_id,sportovec_id,status) VALUES(1,200,'confirmed')");
        $pdo->exec("INSERT INTO club_event_registrations(event_id,sportovec_id,status) VALUES(1,200,'payment_pending'),(1,201,'confirmed'),(1,NULL,'confirmed'),(1,NULL,'confirmed')");
        $expected = \w84BaselineClubCalendarEvents($pdo, '2026-09-01', '2026-09-30', null, true);
        self::assertSame(3, $expected[0]['registration_count']);
        $pdo->profile = [];
        $pdo->profiling = true;
        $actual = \clubCalendarEvents($pdo, '2026-09-01', '2026-09-30', null, true);
        $pdo->profiling = false;
        self::assertSame($expected, $actual);
        self::assertCount(13, $pdo->profile);
        $pdo->exec("UPDATE club_event_registrations SET status='cancelled' WHERE sportovec_id=201");
        self::assertSame(2, \clubCalendarEvents($pdo, '2026-09-01', '2026-09-30')[0]['registration_count']);
    }

    private function database(): QueryProfilePdo
    {
        $pdo = new QueryProfilePdo('sqlite::memory:');
        \w84SeedSqlite($pdo);
        return $pdo;
    }

    private function event(PDO $pdo, array $override = []): int
    {
        return \clubCalendarSaveEvent($pdo, 1, 0, array_replace(['name' => 'Synthetic', 'activity_kind' => 'race',
            'planning_status' => 'planned', 'visibility' => 'staff', 'starts_at' => '2026-09-10T09:00',
            'ends_at' => '2026-09-10T17:00', 'location' => 'Test', 'internal_note' => 'PRIVATE', 'capacity' => 20,
            'participant_fee_minor' => 0, 'fee_due_days' => 14, 'team_ids' => []], $override))['id'];
    }
}
