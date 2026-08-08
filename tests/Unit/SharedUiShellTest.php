<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SharedUiShellTest extends TestCase
{
    public function testEveryFirstPartyHtmlPhpPageUsesSharedUiFoundation(): void
    {
        $root = dirname(__DIR__, 2);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        $missing = [];

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
            if (preg_match('#^(vendor|tests|migrations|scripts)/#', $relative) === 1) {
                continue;
            }
            $source = (string)file_get_contents($path);
            if (stripos($source, '<head') === false) {
                continue;
            }
            if (!str_contains($source, 'hlavicka.php') && !str_contains($source, 'appUiAssets(')) {
                $missing[] = $relative;
            }
        }

        self::assertSame([], $missing, 'HTML pages without the shared UI foundation: ' . implode(', ', $missing));
    }

    public function testEveryHlavickaIncludingPageHasOwnBootstrapCss(): void
    {
        $root = dirname(__DIR__, 2);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        $missing = [];

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
            if (preg_match('#^(vendor|tests|migrations|scripts)/#', $relative) === 1) {
                continue;
            }
            $source = (string)file_get_contents($path);
            if (!str_contains($source, 'hlavicka.php')) {
                continue;
            }
            if (!str_contains($source, 'bootstrap.min.css')) {
                $missing[] = $relative;
            }
        }

        self::assertSame([], $missing, 'Pages including hlavicka.php without their own Bootstrap CSS link: ' . implode(', ', $missing));
    }

    public function testPublicPortalEntrypointsUseOneNavigation(): void
    {
        $root = dirname(__DIR__, 2);
        $expected = [
            'booking/eshop.php' => "publicShellNav('shop')",
            'booking/produkt.php' => "publicShellNav('shop')",
            'booking/treninky.php' => "publicShellNav('training')",
            'booking/krouzky.php' => "publicShellNav('clubs')",
            'booking/velodrom.php' => "publicShellNav('velodrome')",
            'booking/prihlaseni.php' => 'publicShellNav()',
            'booking/registrace.php' => 'publicShellNav()',
        ];

        foreach ($expected as $relative => $needle) {
            self::assertStringContainsString($needle, (string)file_get_contents($root . '/' . $relative), $relative);
        }
    }

    /**
     * Snapshot of every unique .php target linked from hlavicka.php or index.php
     * before the 2026-08-08 Klub/Administrace restructuring (66+71 unique hrefs,
     * 83 in their union, verified via comm/sort -u over raw grep output — see
     * docs/navrh-informacni-architektury.md), plus auditlog/seznam.php, which the
     * restructuring was required to add since it had zero inbound links anywhere.
     * Regression guard: none of these 84 pages may lose its last entry point when
     * the navbar or dashboard changes again.
     */
    private const REQUIRED_NAVIGATION_TARGETS = [
        'admin_dashboard.php', 'auditlog/seznam.php', 'booking/eshop.php',
        'booking/kalendar.php', 'booking/krouzky.php', 'booking/moje_objednavky.php',
        'booking/moje_osoby.php', 'booking/muj_sport.php', 'booking/prihlaseni.php',
        'booking/registrace.php', 'booking/sportovec_prihlaseni.php',
        'booking/sportovni_prehled.php', 'booking/treninky.php', 'booking/velodrom.php',
        'club_programs_admin.php', 'cviky.php', 'duplikovat_trenink.php',
        'edit_trenink.php', 'eshop_admin.php', 'eshop_catalog_publication_admin.php',
        'eshop_events_admin.php', 'eshop_identity_admin.php',
        'eshop_member_prices_admin.php', 'eshop_notifications_admin.php',
        'eshop_order_expiry_admin.php', 'eshop_orders_admin.php', 'export_draha.php',
        'export_seznam.php', 'export_uci.php', 'family_weekly_summaries_admin.php',
        'formular.php', 'formular_zavod.php', 'google_sheets_linky.php',
        'hromadne_odmeny.php', 'hromadne_podskupiny.php', 'index.php',
        'individualni_lekce_form.php', 'individualni_lekce_sprava.php',
        'kalendar_sportovist.php', 'kis_child_access_admin.php', 'kis_rosters_admin.php',
        'kis_sync_center.php', 'kis_training_a07_admin.php', 'kis_transition_admin.php',
        'login.php', 'member_charge_reminders_admin.php', 'moje_skupiny.php',
        'moje_treninky.php', 'nastaveni_opravneni.php', 'nastaveni_zadavani.php',
        'nova_cinnost.php', 'odeslat_emaily.php', 'oznameni.php',
        'person_audit_admin.php', 'planovac.php', 'prehled_kreditu.php',
        'prehled_popisu.php', 'prehled_sportovcu.php', 'prehled_trenera.php',
        'prehled_treninku_skupiny_kalendar.php', 'prehled_vsech_vykazu.php',
        'prehled_zavodu.php', 'provozni_prehled_admin.php', 'rezervovat_sportoviste.php',
        'sports_data_quality_admin.php', 'sports_import_review_admin.php',
        'sprava_podskupin.php', 'sprava_segmentu.php', 'sprava_skupin.php',
        'sprava_sportovcu.php', 'sprava_sportovec_obdobi.php', 'sprava_sportovist.php',
        'sprava_treneru.php', 'sprava_vsech_treninku.php', 'sprava_zavodu.php',
        'sync_evidence.php', 'testovaci_scenare.php', 'uctenky/seznam.php',
        'udalosti/seznam.php', 'verejny_prehled.php', 'verejny_velodrom_admin.php',
        'vozidla/seznam.php', 'vypis_vykazu.php', 'zatezovy_test_form.php',
    ];

    /** @return string[] */
    private static function extractPhpHrefTargets(string $source): array
    {
        preg_match_all('/href="([a-zA-Z0-9_.\/]+\.php)(?:\?[^"]*)?"/', $source, $matches);
        return array_values(array_unique($matches[1]));
    }

    public function testNavigationCoversEveryPreviouslyLinkedPage(): void
    {
        $root = dirname(__DIR__, 2);
        $linked = array_merge(
            self::extractPhpHrefTargets((string)file_get_contents($root . '/hlavicka.php')),
            self::extractPhpHrefTargets((string)file_get_contents($root . '/index.php'))
        );

        $missing = array_values(array_diff(self::REQUIRED_NAVIGATION_TARGETS, $linked));

        self::assertSame(
            [],
            $missing,
            'Pages that lost every hlavicka.php/index.php entry point: ' . implode(', ', $missing)
        );
    }

    public function testNavigationHasNoLinksToMissingFiles(): void
    {
        $root = dirname(__DIR__, 2);
        $linked = array_merge(
            self::extractPhpHrefTargets((string)file_get_contents($root . '/hlavicka.php')),
            self::extractPhpHrefTargets((string)file_get_contents($root . '/index.php'))
        );

        $missing = [];
        foreach ($linked as $target) {
            if (!is_file($root . '/' . $target)) {
                $missing[] = $target;
            }
        }

        self::assertSame([], $missing, 'Navigation hrefs pointing at files that do not exist: ' . implode(', ', $missing));
    }

    public function testSharedInteractionsAreSafeAndReusable(): void
    {
        $root = dirname(__DIR__, 2);
        $helper = (string)file_get_contents($root . '/includes/ui_shell.php');
        $javascript = (string)file_get_contents($root . '/assets/app-ui.js');
        $header = (string)file_get_contents($root . '/hlavicka.php');

        self::assertStringContainsString('assets/app-ui.css', $helper);
        self::assertStringContainsString('assets/app-ui.js', $helper);
        self::assertStringContainsString('text.textContent = String(message)', $javascript);
        self::assertStringContainsString("form.setAttribute('aria-busy', 'true')", $javascript);
        self::assertStringContainsString("form.dataset.appSubmitting = '1'", $javascript);
        self::assertStringNotContainsString('insertAdjacentHTML', $header);
    }
}
