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
        self::assertStringContainsString("foreach (\$staff_active['groups']", $header);
        self::assertStringContainsString('staffAvailablePositions()', $header);
        self::assertStringContainsString('staffIsSuperadmin()', $header);
        self::assertStringContainsString("foreach (\$active['groups']", $landing);
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
        $switch = (string)file_get_contents($root . '/prepnout_pracovni_pozici.php');
        self::assertStringContainsString("staffRouteOwner(\$next) === \$targetPosition", $switch);
    }
}
