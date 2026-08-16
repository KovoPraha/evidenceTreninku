<?php
declare(strict_types=1);

namespace Tests\Integration;

use ClubEventRegistrationException;
use ClubEventRosterTargetException;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/club_event_registration.php';

final class ClubEventRosterTargetTest extends TestCase
{
    public function testPublicEventKeepsExistingExplicitBehavior(): void
    {
        $pdo = $this->database();
        $eventId = $this->openEvent($pdo, [], 2);

        $result = \clubEventRegisterParticipant($pdo, $eventId, 10, 100, '2026.1', true);
        $row = $pdo->query('SELECT * FROM club_event_registrations')->fetch(PDO::FETCH_ASSOC);

        self::assertSame('confirmed', $result['status']);
        self::assertNull($row['eligibility_team_ids_snapshot']);
        self::assertStringContainsString('bez omezení', (string)$row['eligibility_reason_snapshot']);
    }

    public function testOneAndMultipleTargetsAcceptMembershipInAnyAndSnapshotAllMatches(): void
    {
        $pdo = $this->database();
        $eventId = $this->openEvent($pdo, [201, 202], 2);

        $result = \clubEventRegisterParticipant($pdo, $eventId, 10, 100, '2026.1', true);
        $duplicate = \clubEventRegisterParticipant($pdo, $eventId, 10, 100, '2026.1', true);
        $row = $pdo->query('SELECT * FROM club_event_registrations')->fetch(PDO::FETCH_ASSOC);

        self::assertTrue($result['created']);
        self::assertFalse($duplicate['created']);
        self::assertSame($result['id'], $duplicate['id']);
        self::assertSame('[201,202]', $row['eligibility_team_ids_snapshot']);
        self::assertStringContainsString(((int)date('Y') + 1) . '-09-01', $row['eligibility_reason_snapshot']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM club_event_registrations')->fetchColumn());
    }

    public function testInactiveExpiredAndSiblingMembershipsFailBeforeAnyRegistrationWrite(): void
    {
        $pdo = $this->database();
        $eventId = $this->openEvent($pdo, [201], 1);
        $pdo->exec("UPDATE club_roster_members SET status='removed',valid_to='2000-01-01' WHERE sportovec_id=100");

        foreach ([[10, 100], [10, 103]] as [$accountId, $sportovecId]) {
            try {
                \clubEventRegisterParticipant($pdo, $eventId, $accountId, $sportovecId, '2026.1', true);
                self::fail('Roster-ineligible participant must be rejected.');
            } catch (ClubEventRegistrationException $exception) {
                self::assertStringContainsString('cílové soupisky', $exception->getMessage());
            }
        }
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM club_event_registrations')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM club_event_registration_events')->fetchColumn());
    }

    public function testSnapshotSurvivesLaterRosterRemoval(): void
    {
        $pdo = $this->database();
        $eventId = $this->openEvent($pdo, [201], 1);
        $registration = \clubEventRegisterParticipant($pdo, $eventId, 10, 100, '2026.1', true);
        $before = $pdo->query(
            'SELECT eligibility_team_ids_snapshot,eligibility_reason_snapshot '
            . 'FROM club_event_registrations WHERE id=' . $registration['id']
        )->fetch(PDO::FETCH_ASSOC);

        $pdo->exec("UPDATE club_roster_members SET status='removed',valid_to='2000-01-01' WHERE sportovec_id=100");
        $after = $pdo->query(
            'SELECT eligibility_team_ids_snapshot,eligibility_reason_snapshot '
            . 'FROM club_event_registrations WHERE id=' . $registration['id']
        )->fetch(PDO::FETCH_ASSOC);

        self::assertSame($before, $after);
        self::assertSame('[201]', $after['eligibility_team_ids_snapshot']);
    }

    public function testWaitlistPromotionStillWorksForEligibleTargetedParticipant(): void
    {
        $pdo = $this->database();
        $eventId = $this->openEvent($pdo, [201], 1);
        $confirmed = \clubEventRegisterParticipant($pdo, $eventId, 10, 100, '2026.1', true);
        $waiting = \clubEventRegisterParticipant($pdo, $eventId, 10, 101, '2026.1', true);

        self::assertSame('waitlisted', $waiting['status']);
        $cancelled = \clubEventCancelRegistration($pdo, $confirmed['id'], 10, 'Uvolnění cílené kapacity.');
        self::assertSame($waiting['id'], $cancelled['promoted_registration_id']);
        self::assertSame('confirmed', $pdo->query(
            'SELECT status FROM club_event_registrations WHERE id=' . $waiting['id']
        )->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM club_event_notifications')->fetchColumn());
    }

    public function testTargetChangeAfterOpenFailsAndRollsBack(): void
    {
        $pdo = $this->database();
        $eventId = $this->openEvent($pdo, [201], 2);
        try {
            \clubEventRosterReplaceTargets($pdo, $eventId, [202], 7, 'Pozdní změna.', true);
            self::fail('Open target set must be immutable.');
        } catch (ClubEventRosterTargetException) {
        }
        self::assertSame([201], array_map('intval', $pdo->query(
            'SELECT team_id FROM club_event_roster_targets ORDER BY team_id'
        )->fetchAll(PDO::FETCH_COLUMN)));
        self::assertSame(1, (int)$pdo->query(
            "SELECT COUNT(*) FROM club_event_admin_events WHERE action='set_roster_targets'"
        )->fetchColumn());
    }

    private function openEvent(PDO $pdo, array $teamIds, int $capacity): int
    {
        $event = \clubEventCreateDraft($pdo, 7, [
            'code' => 'EVENT-' . bin2hex(random_bytes(4)),
            'event_type' => 'club_event',
            'name' => 'Cílená událost',
            'description_plain' => 'Test cílení na soupisky.',
            'audience_label' => 'Kluboví sportovci',
            'min_age' => '',
            'max_age' => '',
            'capacity' => $capacity,
            'pricing_policy' => 'free',
            'currency' => 'CZK',
            'registration_starts_at' => '',
            'registration_ends_at' => '',
        ]);
        $year = (int)date('Y') + 1;
        \clubEventAddSession($pdo, $event['id'], 7, "$year-09-01T16:00", "$year-09-01T17:30", 'Velodrom', null);
        \clubEventRosterReplaceTargets($pdo, $event['id'], $teamIds, 7, 'Schválené cílení.', true);
        \clubEventLinkProduct($pdo, $event['id'], 501, 7, 'Bezplatný produkt.');
        \clubEventConfigureRegistrationTerms(
            $pdo,
            $event['id'],
            7,
            '2026.1',
            'Souhlasím s účastí.',
            'Storno do uvedeného data.',
            "$year-08-30T16:00",
            true
        );
        \clubEventOpenFreeRegistration($pdo, $event['id'], 7, 'Otevření testu.', true);
        return $event['id'];
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri (id INTEGER PRIMARY KEY,jmeno TEXT NOT NULL)');
        $pdo->exec("INSERT INTO treneri VALUES(7,'Admin')");
        $pdo->exec('CREATE TABLE sportovci (id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT)');
        $pdo->exec("INSERT INTO sportovci VALUES(100,'Anna','První','2012-01-01'),(101,'Bára','Druhá','2013-01-01'),(103,'Cyril','Sourozenec','2014-01-01')");
        $pdo->exec('CREATE TABLE verejni_uzivatele (id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,email TEXT,aktivni INTEGER,email_overeno INTEGER)');
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(10,'Rodič','Test','rodic@example.test',1,1)");
        $pdo->exec('CREATE TABLE shop_products (id INTEGER PRIMARY KEY,name TEXT,offer_type TEXT,catalog_status TEXT)');
        $pdo->exec("INSERT INTO shop_products VALUES(501,'Událost','club_event','internal_only')");
        $pdo->exec('CREATE TABLE shop_variants (id INTEGER PRIMARY KEY,product_id INTEGER,price_mode TEXT,amount_minor INTEGER,currency TEXT,visible INTEGER)');
        $pdo->exec("INSERT INTO shop_variants VALUES(601,501,'free',0,'CZK',1)");

        foreach ([
            '20260802230000_account_person_roles.php',
            '20260803110000_club_events.php',
            '20260803130000_club_event_registrations.php',
            '20260803150000_club_event_terms.php',
            '20260803170000_club_event_waitlist.php',
            '20260803190000_club_event_notifications.php',
            '20260803210000_club_event_notification_admin.php',
            '20260804090000_kis_teams_rosters.php',
            '20260804150000_club_event_roster_targets.php',
            '20260816180000_registration_terms_scope.php',
        ] as $file) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $file;
            $migration['up']($pdo);
            $migration['up']($pdo);
            self::assertTrue($migration['verify']($pdo));
        }
        $year = (int)date('Y') + 1;
        $pdo->exec("INSERT INTO club_seasons(id,code,name,starts_on,ends_on,status,created_by_trainer_id) VALUES(301,'S1','Sezona','$year-01-01','$year-12-31','active',7)");
        $pdo->exec("INSERT INTO club_teams(id,season_id,code,name,discipline,age_label,status,created_by_trainer_id) VALUES(201,301,'U15','U15','silnice','U15','active',7),(202,301,'TRACK','Dráha','dráha','vše','active',7)");
        $pdo->exec("INSERT INTO club_roster_members(team_id,sportovec_id,status,source,valid_from,created_by_trainer_id) VALUES(201,100,'active','manual','$year-01-01',7),(202,100,'active','manual','$year-01-01',7),(201,101,'active','manual','$year-01-01',7)");
        \accountPersonRoleApprove($pdo, 10, 100, 'guardian', 7, 'Rodič.');
        \accountPersonRoleApprove($pdo, 10, 101, 'guardian', 7, 'Rodič.');
        \accountPersonRoleApprove($pdo, 10, 103, 'guardian', 7, 'Rodič.');
        return $pdo;
    }
}
