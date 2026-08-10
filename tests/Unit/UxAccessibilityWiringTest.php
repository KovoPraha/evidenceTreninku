<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class UxAccessibilityWiringTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2) . '/';
    }

    public function testWideAdminNavigationCollapsesBeforeItOverflowsNotebookViewport(): void
    {
        $header = (string)file_get_contents($this->root . 'hlavicka.php');

        self::assertStringContainsString('navbar-expand-xxl', $header);
        self::assertStringNotContainsString('navbar-expand-lg navbar-dark', $header);
        self::assertStringContainsString('min-width: 1400px', $header);
        self::assertStringContainsString('#globalSearchInput { min-width: 170px; width: 170px; }', $header);
    }

    public function testCustomerCalendarAndAccountFormsHaveProgrammaticLabels(): void
    {
        $calendar = (string)file_get_contents($this->root . 'booking/kalendar.php');
        $claims = (string)file_get_contents($this->root . 'booking/moje_osoby.php');
        $athleteLogin = (string)file_get_contents($this->root . 'booking/sportovec_prihlaseni.php');

        self::assertStringContainsString('for="calendar-sportoviste"', $calendar);
        self::assertStringContainsString('aria-label="Předchozí měsíc"', $calendar);
        self::assertStringContainsString('aria-label="Následující měsíc"', $calendar);
        foreach (['claim-role', 'claim-first-name', 'claim-last-name', 'claim-birth-date', 'claim-message'] as $id) {
            self::assertStringContainsString('for="' . $id . '"', $claims);
            self::assertStringContainsString('id="' . $id . '"', $claims);
        }
        self::assertStringContainsString('for="athlete-login"', $athleteLogin);
        self::assertStringContainsString('for="athlete-password"', $athleteLogin);
    }

    public function testIconOnlyAdminActionsHaveAccessibleNames(): void
    {
        $groups = (string)file_get_contents($this->root . 'sprava_skupin.php');
        $calendar = (string)file_get_contents($this->root . 'kalendar_sportovist.php');
        $lessons = (string)file_get_contents($this->root . 'individualni_lekce_sprava.php');

        self::assertStringContainsString('aria-label="Upravit skupinu', $groups);
        self::assertStringContainsString('aria-label="Smazat skupinu', $groups);
        self::assertStringContainsString('aria-label="Předchozí týden"', $calendar);
        self::assertStringContainsString('aria-label="Následující týden"', $calendar);
        self::assertStringContainsString('aria-label="Správa sportovišť"', $calendar);
        self::assertStringContainsString('aria-label="Zrušit lekci', $lessons);
        self::assertStringContainsString('aria-label="Zamítnout rezervaci', $lessons);
    }

    public function testDenseAdminFormsExposeFieldPurpose(): void
    {
        $members = (string)file_get_contents($this->root . 'sprava_sportovcu.php');
        $training = (string)file_get_contents($this->root . 'formular.php');
        $catalog = (string)file_get_contents($this->root . 'eshop_admin.php');
        $orders = (string)file_get_contents($this->root . 'eshop_orders_admin.php');

        self::assertStringContainsString('aria-label="Hledat sportovce"', $members);
        self::assertStringContainsString('aria-label="Vybrat <?= h(', $members);
        self::assertStringContainsString('for="training-images"', $training);
        self::assertStringContainsString('aria-label="Odebrat měření"', $training);
        self::assertStringContainsString('for="catalog-search"', $catalog);
        self::assertStringContainsString('aria-label="Výsledný typ produktu', $catalog);
        self::assertStringContainsString('aria-label="Poznámka k produktu', $catalog);
        self::assertStringContainsString('aria-label="Důvod storna', $orders);
        self::assertStringContainsString('aria-label="Reference vratky', $orders);
    }
}
