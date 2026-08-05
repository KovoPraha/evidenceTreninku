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
        self::assertStringContainsString("roleAtLeast('admin')", $page);
        self::assertStringContainsString('csrf_verify', $page);
        self::assertStringContainsString("(\$_POST['confirm_retry'] ?? '') === '1'", $page);
        self::assertStringContainsString('memberChargeReminderAdminRetry', $page);
        self::assertStringNotContainsString('memberChargeReminderProcessOne', $page);
        self::assertStringNotContainsString('memberChargeReminderMailSender', $page);
        self::assertStringContainsString("c.status AS charge_status", $service);
        self::assertStringContainsString("p.enabled", $service);
        self::assertStringContainsString("'manual_retry'", $service);
        self::assertStringContainsString("'trainer', \$actorTrainerId", $service);
        self::assertStringContainsString('member_charge_reminders_admin.php', $header);
    }
}
