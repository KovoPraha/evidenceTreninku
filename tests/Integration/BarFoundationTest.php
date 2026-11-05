<?php

declare(strict_types=1);

namespace Tests\Integration;

use DomainException;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/BarFoundationFixture.php';
require_once dirname(__DIR__, 2) . '/includes/bar/catalog.php';

final class BarFoundationTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testMigrationIsRepeatableAndNeverRewritesLegacyProductsOrAssignsBarRoles(): void
    {
        $pdo = \barTestDatabase();
        $pdo->exec("DELETE FROM staff_user_positions WHERE position_code IN ('bar_operator','bar_admin')");
        $migration = require dirname(__DIR__, 2) . '/migrations/20261105150000_bar_foundation.php';
        $migration['up']($pdo);
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        self::assertSame(0, (int)$pdo->query("SELECT COUNT(*) FROM staff_user_positions WHERE position_code LIKE 'bar_%'")->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_permissions')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_products')->fetchColumn());
        self::assertSame(34, (int)$pdo->query('SELECT sklad FROM jidlo_produkty WHERE id=91')->fetchColumn());
    }

    public function testBarRolesDoNotAcquireCoachingAndRegistryDelegatesBarWorkflows(): void
    {
        $pdo = \barTestDatabase();
        \barTestLogin(2, 'bar_operator');
        \staffWorkspaceRefreshSession($pdo, 2);
        self::assertSame(['bar_operator'], \staffAvailablePositions());
        self::assertNotContains('bar_operator', \staffRouteAllowedPositions('formular.php'));
        self::assertSame(['bar_operator', 'bar_admin'], \staffRouteAllowedPositions('bar/index.php'));
        foreach (['bar/checkout.php', 'bar/cashbox.php', 'bar/consumption.php', 'bar/receipt.php'] as $route) {
            self::assertSame(['bar_operator', 'bar_admin'], \staffRouteAllowedPositions($route));
        }
        self::assertSame(['bar_admin'], \staffRouteAllowedPositions('bar/settings.php'));
        self::assertSame(['bar_admin'], \staffRouteAllowedPositions('bar/catalog.php'));
        self::assertSame(['system_admin'], \staffRouteAllowedPositions('bar/permissions.php'));
        self::assertSame('bar_operator', \barActor($pdo, 'catalog_read')['position']);
    }

    public static function deniedActors(): array
    {
        return [
            'anonymous' => [0, 'bar_operator', 'catalog_read', null],
            'operator cannot manage' => [2, 'bar_operator', 'catalog_manage', null],
            'coach cannot forge context' => [4, 'bar_operator', 'sell', null],
            'bar admin cannot grant' => [3, 'bar_admin', 'manage_permissions', null],
            'system position is not cashier' => [1, 'system_admin', 'sell', null],
            'deactivated account' => [2, 'bar_operator', 'sell', 'UPDATE treneri SET aktivni=0 WHERE id=2'],
            'session revoked' => [2, 'bar_operator', 'sell', 'UPDATE treneri SET session_version=2 WHERE id=2'],
            'membership revoked' => [2, 'bar_operator', 'sell', 'DELETE FROM staff_user_positions WHERE trainer_id=2'],
            'position disabled' => [2, 'bar_operator', 'sell', "UPDATE staff_positions SET active=0 WHERE code='bar_operator'"],
            'bar admin has no free consumption' => [3, 'bar_admin', 'own_consumption', null],
            'superadmin has no free consumption' => [5, 'bar_admin', 'own_consumption', null],
            'superadmin cannot select disabled role' => [5, 'bar_admin', 'sell', "UPDATE staff_positions SET active=0 WHERE code='bar_admin'"],
            'unknown capability' => [2, 'bar_operator', 'unknown', null],
        ];
    }

    #[DataProvider('deniedActors')]
    public function testActorFailsClosed(int $id, string $position, string $capability, ?string $mutation): void
    {
        $pdo = \barTestDatabase();
        \barTestLogin($id, $position);
        $_SESSION['staff_is_superadmin'] = true;
        if ($mutation !== null) {
            $pdo->exec($mutation);
        }
        $this->expectException(DomainException::class);
        \barActor($pdo, $capability);
    }

    public function testGrantAndRevocationAreIdempotentAuditedAndObservedWithoutNewLogin(): void
    {
        $pdo = \barTestDatabase();
        \barTestLogin(1, 'system_admin');
        self::assertTrue(\barSetConsumptionPermission($pdo, 2, true, 'LOCALHOST explicit grant'));
        self::assertFalse(\barSetConsumptionPermission($pdo, 2, true, 'LOCALHOST duplicate grant'));
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_events')->fetchColumn());
        \barTestLogin(2, 'bar_operator');
        self::assertSame(2, \barActor($pdo, 'own_consumption')['id']);
        \barTestLogin(1, 'system_admin');
        self::assertTrue(\barSetConsumptionPermission($pdo, 2, false, 'LOCALHOST revoke grant'));
        self::assertFalse(\barSetConsumptionPermission($pdo, 2, false, 'LOCALHOST duplicate revoke'));
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_events')->fetchColumn());
        \barTestLogin(2, 'bar_operator');
        $this->expectException(DomainException::class);
        \barActor($pdo, 'own_consumption');
    }

    public function testRoleRevocationInsideTransactionCannotBeBypassedByEarlierActorSnapshot(): void
    {
        $pdo = \barTestDatabase();
        \barTestLogin(1, 'system_admin');
        \barActor($pdo, 'manage_permissions');
        $pdo->exec('DELETE FROM staff_user_positions WHERE trainer_id=1');
        try {
            \barSetConsumptionPermission($pdo, 2, true, 'LOCALHOST stale authority');
            self::fail('A stale actor authorized a grant.');
        } catch (DomainException $expected) {
            self::assertFalse($pdo->inTransaction());
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_permissions')->fetchColumn());
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_events')->fetchColumn());
        }
    }

    public function testAuditFailureRollsBackTheGrant(): void
    {
        $pdo = \barTestDatabase();
        $pdo->exec('DROP TABLE kis_bar_events');
        \barTestLogin(1, 'system_admin');
        try {
            \barSetConsumptionPermission($pdo, 2, true, 'LOCALHOST audit failure');
            self::fail('An unaudited grant was committed.');
        } catch (\PDOException $expected) {
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_permissions')->fetchColumn());
            self::assertFalse($pdo->inTransaction());
        }
    }

    public function testDeactivatedTargetMayLosePermissionButCannotGainIt(): void
    {
        $pdo = \barTestDatabase();
        \barTestLogin(1, 'system_admin');
        \barSetConsumptionPermission($pdo, 2, true, 'LOCALHOST initial grant');
        $pdo->exec('UPDATE treneri SET aktivni=0 WHERE id=2');
        self::assertTrue(\barSetConsumptionPermission($pdo, 2, false, 'LOCALHOST revoke inactive'));
        $this->expectException(DomainException::class);
        \barSetConsumptionPermission($pdo, 2, true, 'LOCALHOST inactive grant');
    }

    public function testOperatorResponseCannotExposePurchasePriceOfficeStockOrArchivedItems(): void
    {
        $pdo = \barTestDatabase();
        $this->products($pdo);
        \barTestLogin(2, 'bar_operator');
        $rows = \barCatalog($pdo);
        self::assertCount(1, $rows);
        self::assertArrayNotHasKey('purchase_minor', $rows[0]);
        self::assertArrayNotHasKey('stock_office', $rows[0]);
        self::assertSame(3500, (int)$rows[0]['price_minor']);
        \barTestLogin(3, 'bar_admin');
        $admin = \barCatalog($pdo, true);
        self::assertCount(3, $admin);
        self::assertSame(1100, (int)$admin[0]['purchase_minor']);
        self::assertSame(17, (int)$admin[0]['stock_office']);
    }

    private function products(PDO $pdo): void
    {
        $pdo->exec("INSERT INTO kis_bar_categories(id,name) VALUES(1,'LOCALHOST Drinks')");
        $query = $pdo->prepare('INSERT INTO kis_bar_products(id,category_id,name,description,price_minor,purchase_minor,stock_mode,stock_stall,stock_office,'
            . 'package_name,archived,on_terminal) VALUES(?,1,?,?,3500,1100,\'sledovat\',4,17,\'\',?,?)');
        $query->execute([1, 'LOCALHOST Tea', 'LOCALHOST Description', 0, 1]);
        $query->execute([2, 'LOCALHOST Archived', '', 1, 1]);
        $query->execute([3, 'LOCALHOST Hidden', '', 0, 0]);
    }
}
