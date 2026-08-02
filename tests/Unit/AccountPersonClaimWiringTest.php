<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AccountPersonClaimWiringTest extends TestCase
{
    public function testPublicPageRequiresOwnSessionAndCsrfWithoutEnumeratingSportovci(): void
    {
        $root = dirname(__DIR__, 2);
        $source = (string)file_get_contents($root . '/booking/moje_osoby.php');

        self::assertStringContainsString("isset(\$_SESSION['verejny_uzivatel_id'])", $source);
        self::assertStringContainsString('csrf_verify', $source);
        self::assertStringContainsString('accountPersonClaimSubmit', $source);
        self::assertStringContainsString('accountPersonClaimCancel', $source);
        self::assertStringNotContainsString('FROM sportovci', $source);
        self::assertStringNotContainsString('kis_', strtolower($source));
    }

    public function testAdminQueueRequiresAdminAndExplicitPersonDecision(): void
    {
        $root = dirname(__DIR__, 2);
        $source = (string)file_get_contents($root . '/eshop_identity_admin.php');

        self::assertStringContainsString("roleAtLeast('admin')", $source);
        self::assertStringContainsString('approve_claim', $source);
        self::assertStringContainsString('reject_claim', $source);
        self::assertStringContainsString('sportovec_id', $source);
        self::assertStringContainsString('Podklad ověření', $source);
    }

    public function testPublicBookingNavigationLinksToOwnPeopleOnly(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertStringContainsString('moje_osoby.php', (string)file_get_contents($root . '/booking/kalendar.php'));
        self::assertStringContainsString('moje_osoby.php', (string)file_get_contents($root . '/booking/moje_rezervace.php'));
    }
}
