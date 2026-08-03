<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PersonAuditTimelineWiringTest extends TestCase
{
    public function testAdminPageIsReadOnlyAndStrictlyAdminProtected(): void
    {
        $root = dirname(__DIR__, 2);
        $page = (string)file_get_contents($root . '/person_audit_admin.php');
        self::assertStringContainsString("roleAtLeast('admin')", $page);
        self::assertStringContainsString('personAuditTimeline', $page);
        self::assertStringNotContainsString('$_POST', $page);
        self::assertStringNotContainsString('csrf_', $page);
    }

    public function testAggregatorScopesEveryEventStreamToPerson(): void
    {
        $source = (string)file_get_contents(dirname(__DIR__, 2) . '/includes/person_audit_timeline.php');
        foreach (['account_person_role_events', 'shop_order_events', 'club_program_enrollment_events',
            'club_roster_events', 'club_roster_rollover_run_items', 'club_event_registration_events',
            'public_profile_events', 'public_velodrome_reservation_events', 'child_access_events'] as $table) {
            self::assertStringContainsString($table, $source);
        }
        self::assertStringContainsString('WHERE r.sportovec_id=?', $source);
        self::assertStringContainsString('WHERE i.beneficiary_sportovec_id=?', $source);
        self::assertStringContainsString('JSON_THROW_ON_ERROR', $source);
        self::assertStringNotContainsString('INSERT INTO', $source);
        self::assertStringNotContainsString('UPDATE ', $source);
        self::assertStringNotContainsString('DELETE FROM', $source);
    }

    public function testEveryConfiguredSourceLinkTargetsAnExistingPage(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/person_audit_timeline.php';
        $root = dirname(__DIR__, 2);
        foreach (personAuditSources() as $source) {
            self::assertFileExists($root . '/' . $source['link'], $source['source']);
        }
    }
}
