<?php
declare(strict_types=1);
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/staff_login_return.php';

final class StaffLoginReturnTest extends TestCase
{
    public function testOnlyKnownReadRoutesAndTypedParametersSurvive(): void
    {
        foreach (['zavod_vysledky.php?id=17', 'kis_review_admin.php?run_id=3&plan_id=4',
            'kis_review_admin.php?run_id=3&fingerprint=' . str_repeat('a', 64)] as $target) {
            self::assertSame($target, \staffLoginReturnTarget($target));
        }
    }

    public function testExternalUrlsHeaderInjectionAndActionParametersFallBack(): void
    {
        foreach ([null, [], 'https://example.org', '//example.org', '/\\example.org',
            '../logout.php', 'logout.php', "zavod_vysledky.php?id=1\r\nX-Test: yes",
            'zavod_vysledky.php?id=1%0d%0aX-Test:yes', 'zavod_vysledky.php?id[]=1',
            'zavod_vysledky.php?id=1&action=delete', 'zavod_vysledky.php?id=-1',
            'kis_review_admin.php?run_id=1&fingerprint=bad', 'zavod_vysledky.php#evil',
            'zavod_vysledky.php?id=1#evil'] as $target) {
            self::assertSame('pracovni_pozice.php', \staffLoginReturnTarget($target));
        }
    }
}
