<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/member_charge_reminder.php';

final class MemberChargeReminderWiringTest extends TestCase
{
    public function testAccountPreferenceIsCsrfProtectedAndOptIn(): void
    {
        $source = $this->source('booking/sportovni_prehled.php');
        self::assertStringContainsString('member_charge_reminder_save', $source);
        self::assertStringContainsString('csrf_verify', $source);
        self::assertStringContainsString('Připomínky klubových plateb', $source);
    }

    public function testWorkerRequiresExplicitHostAndActions(): void
    {
        $source = $this->source('bin/member-charge-reminders.php');
        self::assertStringContainsString("getenv('APP_HOST')", $source);
        self::assertStringContainsString("'--generate'", $source);
        self::assertStringContainsString("'--send'", $source);
        self::assertStringContainsString('memberChargeReminderProcessOne', $source);
    }

    public function testMessageLinkHasNoPersonOrChargeIdentifier(): void
    {
        $source = $this->source('includes/member_charge_reminder.php');
        $url = \memberChargeReminderAccountUrl();
        self::assertSame('https://kis.kovopraha.cz/booking/sportovni_prehled.php', $url);
        self::assertNull(parse_url($url, PHP_URL_QUERY));
        self::assertNull(parse_url($url, PHP_URL_FRAGMENT));
        self::assertStringContainsString('sportovni_prehled.php', $source);
        self::assertStringContainsString("appUrl('booking/sportovni_prehled.php')", $source);
        self::assertStringContainsString('INTERVAL 20 HOUR', $source);
    }

    private function source(string $relative): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $relative);
        self::assertIsString($source);
        return $source;
    }
}
