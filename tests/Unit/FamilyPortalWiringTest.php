<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class FamilyPortalWiringTest extends TestCase
{
    public function testPublicPageUsesSessionScopeAndHasNoPersonIdInput(): void
    {
        $root = dirname(__DIR__, 2);
        $page = (string)file_get_contents($root . '/booking/sportovni_prehled.php');
        $people = (string)file_get_contents($root . '/booking/moje_osoby.php');

        self::assertStringContainsString("\$_SESSION['verejny_uzivatel_id']", $page);
        self::assertStringContainsString('familyPortalOverview', $page);
        self::assertStringNotContainsString("\$_GET['sportovec_id']", $page);
        self::assertStringNotContainsString("\$_POST['sportovec_id']", $page);
        self::assertStringContainsString('sportovni_prehled.php', $people);
    }
}
