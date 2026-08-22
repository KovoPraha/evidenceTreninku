<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/staff_workspaces.php';

final class StaffWorkspaceRegistryTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testEightNonOverlappingPositionsOwnEveryMenuDestination(): void
    {
        $definitions = \staffPositionDefinitions();
        self::assertCount(8, $definitions);
        self::assertSame([
            'coach','sports_lead','registrar','program_coordinator',
            'catalog_manager','order_operator','finance_manager','system_admin',
        ], \staffPositionCodes());
        $seen = [];
        foreach ($definitions as $code => $definition) {
            self::assertNotSame([], $definition['groups'], $code);
            foreach ($definition['groups'] as $group) {
                self::assertNotSame([], $group['items'], $code . ':' . $group['label']);
                foreach ($group['items'] as $item) {
                    $route = $item['route'];
                    self::assertArrayNotHasKey($route, $seen, 'Menu destination is duplicated: ' . $route);
                    $seen[$route] = $code;
                    self::assertSame($code, \staffRouteOwner($route));
                }
            }
        }
    }

    public function testEveryProtectedRouteHasExactlyOneKnownOwner(): void
    {
        $valid = array_fill_keys(\staffPositionCodes(), true);
        foreach (\staffRouteOwners() as $route => $owner) {
            self::assertMatchesRegularExpression('/^[a-z0-9_\/-]+[.]php$/', $route);
            self::assertArrayHasKey($owner, $valid, $route);
            self::assertFileExists(dirname(__DIR__, 2) . '/' . $route, $route);
            self::assertNotContains($route, \staffSharedRoutes(), $route);
        }
    }

    public function testSupportRouteDelegationIsExplicitAndDoesNotMergeMenus(): void
    {
        $definitions = \staffPositionDefinitions();
        foreach (\staffRouteDelegates() as $route => $delegates) {
            self::assertNotNull(\staffRouteOwner($route), $route);
            self::assertFileExists(dirname(__DIR__, 2) . '/' . $route, $route);
            self::assertNotSame([], $delegates, $route);
            self::assertNotContains(\staffRouteOwner($route), $delegates, $route);
            foreach ($delegates as $delegate) self::assertArrayHasKey($delegate, $definitions, $route);
        }
        self::assertSame(['coach', 'sports_lead'], \staffRouteAllowedPositions('edit_trenink.php'));
        self::assertSame(['sports_lead', 'coach'], \staffRouteAllowedPositions('update_trenink.php'));
        self::assertSame(['coach', 'program_coordinator'], \staffRouteAllowedPositions('kalendar_sportovist.php'));
    }

    public function testEnvironmentSpecificItemsAreHiddenFromProductionMenus(): void
    {
        $registrarProduction = \staffPositionMenuGroups('registrar', false);
        $systemProduction = \staffPositionMenuGroups('system_admin', false);
        $registrarLocal = \staffPositionMenuGroups('registrar', true);
        $systemLocal = \staffPositionMenuGroups('system_admin', true);
        $routes = static function (array $groups): array {
            $result = [];
            foreach ($groups as $group) foreach ($group['items'] as $item) $result[] = $item['route'];
            return $result;
        };
        self::assertNotContains('kis_rollover_a06_admin.php', $routes($registrarProduction));
        self::assertNotContains('testovaci_scenare.php', $routes($systemProduction));
        self::assertContains('kis_rollover_a06_admin.php', $routes($registrarLocal));
        self::assertContains('testovaci_scenare.php', $routes($systemLocal));
    }

    public function testPublicGroupProgramIsSharedAndMoneyRatesBelongToFinance(): void
    {
        self::assertContains('program_skupiny.php', \staffSharedRoutes());
        self::assertNull(\staffRouteOwner('program_skupiny.php'));
        self::assertSame('finance_manager', \staffRouteOwner('hromadne_odmeny.php'));
    }

    public function testStaffEntryPointInventoryHasNoOrphanRoute(): void
    {
        $root = dirname(__DIR__, 2);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        $excludedDirectories = ['vendor/','tests/','migrations/','includes/','bin/','booking/','pdf/','auth/','cron/'];
        $excludedFiles = [
            'config.php','config.example.php','csrf_helper.php','db.php','hlavicka.php',
            'cron_report_tyden.php','cron_upominky.php','report_tyden_lib.php','pub.php','list.php',
            'sportovec_treninky.php','test.php','analyze_excel.php','analyze_uci_form.php',
        ];
        $orphans = [];
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
            $relative = \staffNormalizeRoute(substr(str_replace('\\', '/', $file->getPathname()), strlen(str_replace('\\', '/', $root))));
            if (array_filter($excludedDirectories, static fn(string $prefix): bool => str_starts_with($relative, $prefix)) !== []) continue;
            if (in_array($relative, $excludedFiles, true) || in_array($relative, \staffSharedRoutes(), true)) continue;
            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            if (!str_contains($source, 'trener_id') && !str_contains($source, 'roleAtLeast(') && !str_contains($source, 'canAccess(')) continue;
            if (\staffRouteOwner($relative) === null) $orphans[] = $relative;
        }
        sort($orphans);
        self::assertSame([], $orphans, 'Staff entry points without one owner: ' . implode(', ', $orphans));
    }

    public function testStaffHeaderAndLandingPageAreGeneratedFromActivePositionOnly(): void
    {
        $root = dirname(__DIR__, 2);
        $header = (string)file_get_contents($root . '/hlavicka.php');
        $landing = (string)file_get_contents($root . '/pracovni_pozice.php');
        self::assertStringContainsString('staffPositionMenuGroups(', $header);
        self::assertStringContainsString('foreach ($staff_menu_groups', $header);
        self::assertStringContainsString('staffAvailablePositions()', $header);
        self::assertStringContainsString('staffIsSuperadmin()', $header);
        self::assertStringContainsString('staffPositionMenuGroups(', $landing);
        self::assertStringContainsString('foreach ($activeGroups', $landing);
        self::assertStringNotContainsString('roleAtLeast(', $landing);
    }

    public function testActivePositionControlsLegacyCompatibilityWithoutMergingMenus(): void
    {
        $_SESSION = ['trener_id'=>7,'role'=>'admin','staff_positions'=>['coach','finance_manager'],'staff_active_position'=>'coach','staff_is_superadmin'=>false];
        self::assertSame('coach', \staffActivePosition());
        self::assertSame('trener', \staffEffectiveLegacyRole('admin'));
        self::assertSame(['coach','finance_manager'], \staffAvailablePositions());
        $_SESSION['staff_active_position'] = 'finance_manager';
        self::assertSame('admin', \staffEffectiveLegacyRole('trener'));
        self::assertSame('finance_manager', \staffRouteOwner('eshop_payments_admin.php'));
        self::assertSame('order_operator', \staffRouteOwner('eshop_orders_admin.php'));
    }

    public function testSuperadminMayActivateAllPositionsButOnlyOneAtATime(): void
    {
        $_SESSION = ['trener_id'=>7,'role'=>'admin','staff_positions'=>['system_admin'],'staff_active_position'=>'system_admin','staff_is_superadmin'=>true];
        self::assertSame(\staffPositionCodes(), \staffAvailablePositions());
        self::assertSame('system_admin', \staffActivePosition());
        $_SESSION['staff_active_position'] = 'catalog_manager';
        self::assertSame('catalog_manager', \staffActivePosition());
        self::assertFalse(\staffActivePositionIs('system_admin'));
    }

    public function testMoneyActionsAreSeparatedFromOrderOperations(): void
    {
        $root = dirname(__DIR__, 2);
        $orders = (string)file_get_contents($root . '/eshop_orders_admin.php');
        $payments = (string)file_get_contents($root . '/eshop_payments_admin.php');
        self::assertStringNotContainsString("action==='confirm_payment'", $orders);
        self::assertStringNotContainsString("action==='confirm_refund'", $orders);
        self::assertStringContainsString("\$action === 'confirm_payment'", $payments);
        self::assertStringContainsString("\$action === 'confirm_refund'", $payments);
        self::assertStringContainsString("staffRequireActivePosition('finance_manager')", $payments);
        self::assertStringContainsString("action === 'confirm_velodrome_payment'", $payments);
        $lessons = (string)file_get_contents($root . '/individualni_lekce_sprava.php');
        $velodrome = (string)file_get_contents($root . '/verejny_velodrom_admin.php');
        self::assertStringNotContainsString("\$action === 'zaplatit'", $lessons);
        self::assertStringNotContainsString("manual_confirm", $velodrome);
        $switch = (string)file_get_contents($root . '/prepnout_pracovni_pozici.php');
        self::assertStringContainsString("staffRouteOwner(\$next) === \$targetPosition", $switch);
    }
}
