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

    public function testRemainingCustomerBookingFormsHaveHeadingsAndLabels(): void
    {
        $reservations = (string)file_get_contents($this->root . 'booking/moje_rezervace.php');
        $events = (string)file_get_contents($this->root . 'booking/krouzky.php');
        $velodrome = (string)file_get_contents($this->root . 'booking/velodrom.php');

        self::assertStringContainsString('<h1 class="h5 mb-3">', $reservations);
        self::assertStringContainsString('for="paid-person-', $events);
        self::assertStringContainsString('id="paid-person-', $events);
        self::assertStringContainsString('for="velodrome-note-', $velodrome);
        self::assertStringContainsString('id="velodrome-note-', $velodrome);
        self::assertStringContainsString('for="velodrome-cancel-note-', $velodrome);
        self::assertStringContainsString('id="velodrome-cancel-note-', $velodrome);
    }

    public function testSharedUiAddsAccessibleNamesToLegacyAdminFields(): void
    {
        $ui = (string)file_get_contents($this->root . 'assets/app-ui.js');
        $trainingSettings = (string)file_get_contents($this->root . 'nastaveni_zadavani.php');

        self::assertStringContainsString('function ensureAccessibleFieldNames()', $ui);
        self::assertStringContainsString("name.indexOf('min_role[') === 0", $ui);
        self::assertStringContainsString("field.getAttribute('placeholder')", $ui);
        self::assertStringContainsString('ensureAccessibleFieldNames();', $ui);
        self::assertStringContainsString('<h1 class="h5 fw-semibold mb-0">', $trainingSettings);
    }

    public function testLegacyAdminPagesExposeOneMainHeadingAndAuditTableStaysContained(): void
    {
        $headings = [
            'duplikovat_trenink.php' => '<h1 class="fw-semibold fs-5 mb-0">',
            'formular_zavod.php' => '<h1 class="fw-semibold fs-5 mb-0">',
            'prehled_popisu.php' => '<h1 class="fw-semibold fs-5 mb-0">',
            'hromadne_odmeny.php' => '<h1 class="fw-semibold fs-5 mb-0">',
            'planovac.php' => '<h1 class="h5 mb-0 me-2">',
            'odeslat_emaily.php' => '<h1 class="fw-semibold fs-5 mb-0">',
            'prehled_kreditu.php' => '<h1 class="fw-semibold fs-5 mb-0">',
            'sprava_sportovec_obdobi.php' => '<h1 class="fw-semibold fs-5 mb-0">',
            'edit_trenink.php' => '<h1 class="fw-semibold fs-5 mb-0">',
        ];

        foreach ($headings as $file => $heading) {
            self::assertStringContainsString($heading, (string)file_get_contents($this->root . $file), $file);
        }

        $audit = (string)file_get_contents($this->root . 'auditlog/seznam.php');
        self::assertStringContainsString('<div class="table-responsive">', $audit);
        self::assertStringContainsString('white-space: pre-wrap; overflow-wrap: anywhere;', $audit);

        $trainingUpdate = (string)file_get_contents($this->root . 'update_trenink.php');
        self::assertStringContainsString("\$_SESSION['flash_success'] = 'Trénink byl úspěšně uložen.';", $trainingUpdate);

        $shop = (string)file_get_contents($this->root . 'booking/eshop.php');
        self::assertStringContainsString('for="<?=shopPublicH($quantityId)?>">Množství</label>', $shop);
        self::assertStringContainsString('id="<?=shopPublicH($quantityId)?>"', $shop);
        self::assertStringContainsString('d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3', $shop);

        $orders = (string)file_get_contents($this->root . 'eshop_orders_admin.php');
        self::assertStringContainsString('role="search"', $orders);
        self::assertStringContainsString('id="order-search"', $orders);
        self::assertStringContainsString("#order-'.(int)\$result['order_id']", $orders);
        self::assertStringContainsString("'confirm_bank_payment'=>'Bankovní platba potvrzena'", $orders);
        self::assertStringNotContainsString('<code><?=orderAdminH($order[\'last_event_action\'])?></code>', $orders);

        $checkout = (string)file_get_contents($this->root . 'includes/shop_checkout.php');
        self::assertStringContainsString("function shopOrderAdminList(PDO \$pdo,int \$limit=200,string \$query=''):array", $checkout);
        self::assertStringContainsString('o.customer_email_snapshot LIKE ?', $checkout);

        $child = (string)file_get_contents($this->root . 'booking/muj_sport.php');
        self::assertStringContainsString("'removed' => 'Odebráno ze soupisky'", $child);

        $activity = (string)file_get_contents($this->root . 'nova_cinnost.php');
        self::assertStringContainsString('name="datum" id="datum"', $activity);
        self::assertStringContainsString("\$_SESSION['flash_success'] = 'Činnost byla uložena a je zahrnuta ve výkazu.';", $activity);
        self::assertStringContainsString("vypis_vykazu.php?mesic=", $activity);

        $report = (string)file_get_contents($this->root . 'vypis_vykazu.php');
        self::assertStringContainsString("DELETE FROM dalsi_cinnosti WHERE id = ? AND trener_id = ?", $report);
        self::assertStringContainsString('name="action" value="delete_activity"', $report);
        self::assertStringContainsString('Opravdu odstranit tuto činnost', $report);
        self::assertStringContainsString('name="confirm_action" value="1" required', $report);

        $trainingList = (string)file_get_contents($this->root . 'ajax_treninky.php');
        self::assertStringContainsString('aria-label="Upravit poznámku k tréninku', $trainingList);
        self::assertStringContainsString('aria-label="Poznámka k tréninku', $trainingList);
        self::assertStringContainsString('aria-label="Uložit poznámku k tréninku', $trainingList);
        self::assertStringContainsString('role="status" aria-live="polite"', $trainingList);
        self::assertStringContainsString('Vytvořit Story', $trainingList);

        $trainingPage = (string)file_get_contents($this->root . 'moje_treninky.php');
        self::assertStringContainsString("status.textContent = 'Poznámka byla uložena.';", $trainingPage);
        self::assertStringNotContainsString(".js-poznamka-msg[data-id=", $trainingPage);

        $velodromePage = (string)file_get_contents($this->root . 'booking/velodrom.php');
        self::assertStringContainsString("'potvrzena' => 'Potvrzená'", $velodromePage);
        self::assertStringContainsString("'zrusena' => 'Zrušená'", $velodromePage);
        self::assertStringContainsString("publicVelodromeStatusLabel((string)\$reservation['stav'])", $velodromePage);
    }

    public function testSharedUiAssetsAreCacheBustedAfterDeployment(): void
    {
        $shell = (string)file_get_contents($this->root . 'includes/ui_shell.php');

        self::assertStringContainsString("filemtime(\$root . '/assets/app-ui.css')", $shell);
        self::assertStringContainsString("filemtime(\$root . '/assets/app-ui.js')", $shell);
        self::assertStringContainsString("'?v=' . rawurlencode", $shell);
    }

    public function testKeyboardFocusIsClearlyVisibleAcrossSharedUi(): void
    {
        $css = (string)file_get_contents($this->root . 'assets/app-ui.css');

        self::assertStringContainsString(':focus-visible', $css);
        self::assertStringContainsString('outline: 3px solid #ffbf47 !important;', $css);
        self::assertStringContainsString('outline-offset: 2px !important;', $css);
    }
}
