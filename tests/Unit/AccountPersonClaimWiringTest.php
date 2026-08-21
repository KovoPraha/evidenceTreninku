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
        self::assertStringContainsString('Žádost byla odeslána ke kontrole administrátorovi.', $source);
        self::assertStringNotContainsString('FROM sportovci', $source);
        self::assertStringNotContainsString('personMatchV1', $source);
        self::assertStringNotContainsString('kis_', strtolower($source));
    }

    public function testAdminQueueRequiresAdminAndExplicitPersonDecision(): void
    {
        $root = dirname(__DIR__, 2);
        $source = (string)file_get_contents($root . '/eshop_identity_admin.php');

        self::assertStringContainsString("staffActivePositionIs('registrar')", $source);
        self::assertStringContainsString('approve_claim', $source);
        self::assertStringContainsString('reject_claim', $source);
        self::assertStringContainsString('sportovec_id', $source);
        self::assertStringContainsString('Podklad ověření', $source);
        self::assertStringContainsString('create_claim_person', $source);
        self::assertStringContainsString('personMatchV1', $source);
        self::assertStringContainsString('personMatchV1CreateManual', $source);
        self::assertStringContainsString('Založit osobu z údajů žádosti a schválit', $source);
        self::assertStringNotContainsString('LIMIT 1000', $source);
    }

    public function testPublicBookingNavigationLinksToOwnPeopleOnly(): void
    {
        $root = dirname(__DIR__, 2);
        $shell = (string)file_get_contents($root . '/includes/ui_shell.php');
        self::assertStringContainsString('publicShellNav', (string)file_get_contents($root . '/booking/kalendar.php'));
        self::assertStringContainsString('publicShellNav', (string)file_get_contents($root . '/booking/moje_rezervace.php'));
        self::assertStringContainsString('moje_osoby.php', $shell);
    }
}
