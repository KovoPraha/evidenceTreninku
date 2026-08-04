<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class UnifiedHomepageWiringTest extends TestCase
{
    public function testHomepageConnectsShopFamilyAthleteEvidenceAndKisEntrypoints(): void
    {
        $root = dirname(__DIR__, 2);
        $source = (string)file_get_contents($root . '/index.php');
        $header = (string)file_get_contents($root . '/hlavicka.php');

        self::assertStringContainsString('jeden klubový portál', $source);
        self::assertStringContainsString('Nakoupit v e-shopu', $source);
        self::assertStringContainsString('booking/eshop.php', $source);
        self::assertStringContainsString('booking/krouzky.php', $source);
        self::assertStringContainsString('booking/velodrom.php', $source);
        self::assertStringContainsString('booking/moje_osoby.php', $source);
        self::assertStringContainsString('booking/muj_sport.php', $source);
        self::assertStringContainsString('booking/sportovec_prihlaseni.php', $source);
        self::assertStringContainsString('formular.php', $source);
        self::assertStringContainsString('kis_rosters_admin.php', $source);
        self::assertStringContainsString('eshop_orders_admin.php', $source);
        self::assertStringContainsString('Kovopraha – klubový portál', $source);
        self::assertStringContainsString('booking/eshop.php', $header);
        self::assertStringContainsString('Kroužky a události', $header);
        self::assertStringContainsString('Rodič / zákazník', $header);
        self::assertStringContainsString('Trenér', $header);
    }

    public function testHomepageAdaptsToEachExistingAuthenticationMode(): void
    {
        $source = (string)file_get_contents(dirname(__DIR__, 2) . '/index.php');

        self::assertStringContainsString("isset(\$_SESSION['trener_id'])", $source);
        self::assertStringContainsString("isset(\$_SESSION['verejny_uzivatel_id'])", $source);
        self::assertStringContainsString("isset(\$_SESSION['sportovec_pristup_id'])", $source);
        self::assertStringContainsString('Přihlášený rodinný účet', $source);
        self::assertStringContainsString('Přihlášený sportovec', $source);
        self::assertStringContainsString('Jeden účet používá podle oprávnění e-shop i trenérskou Evidenci.', $source);
        self::assertStringContainsString("\$shopUrl      = 'booking/eshop.php'", $source);
        self::assertStringContainsString("\$is_athlete ? 'booking/muj_sport.php'", $source);
    }
}
