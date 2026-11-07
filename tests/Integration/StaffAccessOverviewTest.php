<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/BarFoundationFixture.php';
require_once dirname(__DIR__, 2) . '/includes/staff_access_overview.php';
require_once dirname(__DIR__, 2) . '/includes/bar/access.php';

final class StaffAccessOverviewTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function accounts(): array
    {
        $pdo = \barTestDatabase();
        $pdo->exec("UPDATE treneri SET jmeno='LOCALHOST Žaneta',email='zaneta@example.test' WHERE id=4");
        $pdo->exec("INSERT INTO treneri(id,jmeno,email,aktivni) VALUES(6,'LOCALHOST Inactive','inactive@example.test',0),(7,'LOCALHOST No role','norole@example.test',1)");
        $pdo->exec("INSERT INTO staff_user_positions(trainer_id,position_code,is_default) VALUES(4,'order_operator',0)");
        \barTestLogin(1, 'system_admin');
        \barSetConsumptionPermission($pdo, 3, true, 'LOCALHOST explicit grant');
        return \staffAccessOverview($pdo);
    }

    public function testOverviewIncludesInactiveAndUnassignedAccountsAndDefaultPosition(): void
    {
        $accounts = $this->accounts();
        self::assertCount(7, $accounts);
        $byId = array_column($accounts, null, 'id');
        self::assertSame(['coach', 'order_operator'], $byId[4]['positions']);
        self::assertSame('coach', $byId[4]['default_position']);
        self::assertTrue($byId[5]['superadmin']);
        self::assertFalse($byId[5]['own_consumption']);
        self::assertTrue($byId[3]['own_consumption']);
        self::assertSame([], $byId[7]['positions']);
        self::assertSame(6, (int)$accounts[array_key_last($accounts)]['id']);
    }

    public function testFiltersCombineUnicodeSearchStatusAndEffectiveRoles(): void
    {
        $accounts = $this->accounts();
        self::assertSame([4], array_column(\staffAccessOverviewFilter($accounts, 'ŽANETA', 'shop', 'active'), 'id'));
        self::assertSame([4], array_column(\staffAccessOverviewFilter($accounts, 'zaneta@example.test', 'coach', ''), 'id'));
        self::assertSame([3], array_column(\staffAccessOverviewFilter($accounts, '#3', 'bar', ''), 'id'));
        self::assertSame([6], array_column(\staffAccessOverviewFilter($accounts, '', '', 'inactive'), 'id'));
        self::assertSame([5], array_column(\staffAccessOverviewFilter($accounts, '', 'superadmin', ''), 'id'));
        self::assertSame([3], array_column(\staffAccessOverviewFilter($accounts, '', 'own_consumption', ''), 'id'));
        self::assertSame([7], array_column(\staffAccessOverviewFilter($accounts, '', 'unassigned', 'active'), 'id'));
        self::assertSame([5], array_column(\staffAccessOverviewFilter($accounts, '', 'catalog_manager', 'active'), 'id'));
        self::assertSame([], \staffAccessOverviewFilter($accounts, '', 'invalid-role', ''));
    }

    public function testEveryCanonicalRoleAppearsInExactlyOneDisplayGroup(): void
    {
        $codes = array_merge(...array_column(\staffAccessOverviewGroups(), 'codes'));
        self::assertCount(count(array_unique($codes)), $codes);
        $expected = \staffPositionCodes();
        sort($codes);
        sort($expected);
        self::assertSame($expected, $codes);
    }

    public function testGrantIsExplicitAndCanBeRevokedWithoutChangingBarRole(): void
    {
        $pdo = \barTestDatabase();
        \barTestLogin(1, 'system_admin');
        \barSetConsumptionPermission($pdo, 3, true, 'LOCALHOST explicit grant');
        self::assertTrue(array_column(\staffAccessOverview($pdo), null, 'id')[3]['own_consumption']);
        \barSetConsumptionPermission($pdo, 3, false, 'LOCALHOST revoke grant');
        $account = array_column(\staffAccessOverview($pdo), null, 'id')[3];
        self::assertFalse($account['own_consumption']);
        self::assertSame(['bar_admin'], $account['positions']);
        self::assertSame(2, (int)$pdo->query("SELECT COUNT(*) FROM kis_bar_events WHERE entity_type='permission'")->fetchColumn());
    }

    public function testBarAdministratorCannotGrantConsumption(): void
    {
        $pdo = \barTestDatabase();
        \barTestLogin(3, 'bar_admin');
        try {
            \barSetConsumptionPermission($pdo, 3, true, 'LOCALHOST denied grant');
            self::fail('Bar administrator granted a special permission.');
        } catch (\DomainException) {
            self::assertFalse($pdo->inTransaction());
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_permissions')->fetchColumn());
        }
    }
}
