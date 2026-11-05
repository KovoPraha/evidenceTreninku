<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

require_once __DIR__.'/ShopManualCatalogAdminTest.php';
require_once dirname(__DIR__, 2).'/includes/membership_setup.php';

final class MembershipSetupTest extends TestCase
{
    private PDO $pdo;
    private mixed $session;

    protected function setUp(): void
    {
        $this->session = $_SESSION ?? [];
        $base = new ShopManualCatalogAdminTest('testDeliveryModeIsStoredAndUnrelatedProductEditKeepsIt');
        $this->pdo = (new ReflectionMethod($base, 'database'))->invoke($base);
        $this->pdo->exec('ALTER TABLE treneri ADD COLUMN aktivni INTEGER DEFAULT 1;'
            . 'CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT);'
            . 'CREATE TABLE staff_user_positions(trainer_id INTEGER,position_code TEXT);'
            . 'CREATE TABLE staff_superadmins(trainer_id INTEGER);'
            . "INSERT INTO staff_user_positions VALUES(7,'finance_manager');"
            . 'CREATE TABLE shop_product_categories(id INTEGER PRIMARY KEY,product_id INTEGER,category_path TEXT,is_default INTEGER,sort_order INTEGER);');
        foreach (['20260817110000_shop_catalog_admin', '20260804090000_kis_teams_rosters', '20260804110000_kis_roster_policies',
            '20260822170000_club_roster_structure_events', '20261021090000_roster_hierarchy_temporal',
            '20261105150000_monthly_memberships', '20261105200000_membership_catalog'] as $name) {
            $migration = require dirname(__DIR__, 2).'/migrations/'.$name.'.php';
            $migration['up']($this->pdo);
        }
        $_SESSION = ['trener_id' => 7, 'staff_positions' => ['finance_manager'], 'staff_active_position' => 'finance_manager'];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->session;
    }

    public function testPreparationIsDraftIdempotentAndKeepsExistingGoods(): void
    {
        $before = $this->pdo->query('SELECT * FROM shop_products WHERE id=501')->fetch(PDO::FETCH_ASSOC);
        $first = \membershipPrepareRacingTeam($this->pdo);
        $second = \membershipPrepareRacingTeam($this->pdo);
        self::assertSame($first['product_id'], $second['product_id']);
        self::assertSame($first['team_ids'], $second['team_ids']);
        self::assertSame(3, (int)$this->pdo->query('SELECT COUNT(*) FROM club_team_edges')->fetchColumn());
        self::assertSame(4, (int)$this->pdo->query('SELECT COUNT(*) FROM club_teams')->fetchColumn());
        $product = $this->pdo->query('SELECT * FROM shop_products WHERE id='.(int)$first['product_id'])->fetch(PDO::FETCH_ASSOC);
        self::assertSame('draft', $product['catalog_status']);
        self::assertSame('Závodní tým', $product['name']);
        self::assertSame('Členství v klubu', $this->pdo->query('SELECT category_path FROM shop_product_categories')->fetchColumn());
        self::assertSame(220000, (int)$this->pdo->query('SELECT amount_minor FROM shop_variants WHERE id='.(int)$first['variant_id'])->fetchColumn());
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM monthly_membership_tariffs')->fetchColumn());
        self::assertSame($before, $this->pdo->query('SELECT * FROM shop_products WHERE id=501')->fetch(PDO::FETCH_ASSOC));
        self::assertSame([], \kisRosterEffectiveMemberIds($this->pdo, $first['team_id'], \monthlyMembershipToday()));
    }

    public function testConflictRollsBackNewRostersAndDoesNotAlterExistingProduct(): void
    {
        $this->pdo->exec("UPDATE shop_variants SET sku='KP-CLEN-ZAVODNI-MESIC' WHERE id=601");
        try {
            \membershipPrepareRacingTeam($this->pdo);
            self::fail('Conflicting SKU must not be reused.');
        } catch (\DomainException) {
            self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM club_teams')->fetchColumn());
            self::assertSame(5000, (int)$this->pdo->query('SELECT amount_minor FROM shop_variants WHERE id=601')->fetchColumn());
        }
    }

    public function testRevokedFinanceRoleCannotPrepareProduct(): void
    {
        $this->pdo->exec('DELETE FROM staff_user_positions');
        $this->expectException(\DomainException::class);
        \membershipPrepareRacingTeam($this->pdo);
    }
}
