<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ClubEventRegistrationWiringTest extends TestCase
{
    public function testPublicPageRequiresSessionCsrfAndServerSideK2Service(): void
    {
        $root = dirname(__DIR__, 2);
        $page = (string)file_get_contents($root . '/booking/krouzky.php');
        self::assertStringContainsString("isset(\$_SESSION['verejny_uzivatel_id'])", $page);
        self::assertStringContainsString('csrf_verify', $page);
        self::assertStringContainsString('clubEventRegisterParticipant', $page);
        self::assertStringContainsString('accountPersonEligibleParticipants', $page);
        self::assertStringContainsString('name="consented"', $page);
        self::assertStringContainsString('consent_version', $page);
        self::assertStringNotContainsString('shop_orders', $page);
        self::assertStringNotContainsString('payments', $page);
        self::assertStringNotContainsString('soupisk', $page);
    }

    public function testRegistrationServiceContainsMariaDbCapacityLockAndUniqueMigration(): void
    {
        $root = dirname(__DIR__, 2);
        $service = (string)file_get_contents($root . '/includes/club_event_registration.php');
        $migration = (string)file_get_contents($root . '/migrations/20260803130000_club_event_registrations.php');
        $termsMigration = (string)file_get_contents($root . '/migrations/20260803150000_club_event_terms.php');
        self::assertStringContainsString('clubEventLock($pdo, $eventId)', $service);
        self::assertStringContainsString("=== 'mysql'", $service);
        self::assertStringContainsString('FOR UPDATE', $service);
        self::assertStringContainsString('UNIQUE KEY uq_club_registration_person', $migration);
        self::assertStringContainsString('UNIQUE (event_id, sportovec_id)', $migration);
        self::assertStringContainsString('cancellation_deadline_snapshot', $service);
        self::assertStringContainsString('club_event_term_versions', $termsMigration);
        self::assertStringContainsString('uq_club_event_terms_version', $termsMigration);
    }

    public function testAdminOpeningIsCsrfProtectedAndExplicitlyConfirmed(): void
    {
        $source = (string)file_get_contents(dirname(__DIR__, 2) . '/eshop_events_admin.php');
        self::assertStringContainsString("roleAtLeast('admin')", $source);
        self::assertStringContainsString('csrf_verify', $source);
        self::assertStringContainsString("(\$_POST['confirm_open']??'')==='1'", $source);
        self::assertStringContainsString('clubEventOpenFreeRegistration', $source);
        self::assertStringContainsString('clubEventConfigureRegistrationTerms', $source);
        self::assertStringContainsString("(\$_POST['confirm_terms']??'')==='1'", $source);
    }
}
