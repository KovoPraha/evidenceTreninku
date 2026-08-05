<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class MemberChargeReminderAdminWiringTest extends TestCase
{
    public function testAdminPageRequiresAdminCsrfReasonAndQueuesWithoutSending(): void
    {
        $root = dirname(__DIR__, 2);
        $page = (string)file_get_contents($root . '/member_charge_reminders_admin.php');
        $service = (string)file_get_contents($root . '/includes/member_charge_reminder.php');
        $header = (string)file_get_contents($root . '/hlavicka.php');
        $cli = (string)file_get_contents($root . '/bin/member-charge-reminders.php');
        $demo = (string)file_get_contents($root . '/includes/member_charge_reminder_demo.php');
        self::assertStringContainsString("roleAtLeast('admin')", $page);
        self::assertStringContainsString('csrf_verify', $page);
        self::assertStringContainsString("(\$_POST['confirm_retry'] ?? '') === '1'", $page);
        self::assertStringContainsString('memberChargeReminderAdminRetry', $page);
        self::assertStringNotContainsString('memberChargeReminderMailSender', $page);
        self::assertStringContainsString("c.status AS charge_status", $service);
        self::assertStringContainsString("p.enabled", $service);
        self::assertStringContainsString("'manual_retry'", $service);
        self::assertStringContainsString("'trainer', \$actorTrainerId", $service);
        self::assertStringContainsString('member_charge_reminders_admin.php', $header);
        self::assertStringContainsString("header('Cache-Control: no-store, private')", $page);
        self::assertStringContainsString('memberChargeReminderAdminPreview', $page);
        self::assertStringContainsString('--transport=local-outbox', $cli);
        self::assertStringContainsString('memberChargeReminderLocalOutboxSender($appHost)', $cli);
        self::assertStringContainsString("Require all denied", (string)file_get_contents($root . '/var/.htaccess'));
        self::assertStringContainsString("(\$_POST['action'] ?? '') === 'seed_demo'", $page);
        self::assertStringContainsString("defined('JE_LOKALNE')", $page);
        self::assertStringContainsString('memberChargeReminderSeedLocalDemo', $page);
        self::assertStringContainsString("rodic@localhost.test", $demo);
        self::assertStringContainsString("'localhost_demo_reset'", $demo);
        self::assertStringContainsString("(\$_POST['action'] ?? '') === 'deliver_demo'", $page);
        self::assertStringContainsString("(\$_POST['confirm_delivery'] ?? '') === '1'", $page);
        self::assertStringContainsString('memberChargeReminderDeliverLocalDemo', $page);
        self::assertStringContainsString("memberChargeReminderLocalOutboxSender('localhost'", $demo);
        self::assertStringNotContainsString('memberChargeReminderMailSender', $demo);
    }
}
