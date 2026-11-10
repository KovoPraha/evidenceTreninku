<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/BarFoundationFixture.php';
require_once dirname(__DIR__, 2) . '/includes/bar/stock_transfer.php';

final class BarOpeningStockTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = \barTestDatabase();
        $this->pdo->exec("INSERT INTO kis_bar_categories(id,name) VALUES(1,'LOCALHOST')");
        $this->pdo->exec("INSERT INTO kis_bar_products(id,source_id,category_id,name,description,package_name,price_minor,stock_mode) VALUES
            (1,91,1,'LOCALHOST Tea','','',3500,'sledovat'),(2,92,1,'LOCALHOST Water','','',2500,'sledovat')");
        \barTestLogin(3, 'bar_admin');
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function plan(): array
    {
        return \barOpeningStockPlan($this->pdo, [['id' => 91, 'sklad' => 12, 'predsklad' => 24], ['id' => 92, 'sklad' => 3, 'predsklad' => 0]]);
    }

    public function testBothLocationsAuditedAndReplayPreservesSubsequentStock(): void
    {
        $plan = $this->plan();
        $token = str_repeat('a', 64);
        $result = \barApplyOpeningStocks($this->pdo, $plan, $token, 'LOCALHOST approved stock');
        self::assertSame(2, $result['products']);
        self::assertSame(15, $result['stall_total']);
        self::assertSame(24, $result['office_total']);
        self::assertSame(4, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_stock_movements')->fetchColumn());
        $this->pdo->exec('UPDATE kis_bar_products SET stock_stall=11,lock_version=lock_version+1 WHERE id=1');
        self::assertTrue(\barApplyOpeningStocks($this->pdo, $plan, $token, 'LOCALHOST approved stock')['repeated']);
        self::assertSame(11, (int)$this->pdo->query('SELECT stock_stall FROM kis_bar_products WHERE id=1')->fetchColumn());
        self::assertSame(3500, (int)$this->pdo->query('SELECT price_minor FROM kis_bar_products WHERE id=1')->fetchColumn());
    }

    public function testLateConflictRollsBackEveryProduct(): void
    {
        $plan = $this->plan();
        $this->pdo->exec('UPDATE kis_bar_products SET lock_version=2 WHERE id=2');
        try {
            \barApplyOpeningStocks($this->pdo, $plan, str_repeat('b', 64), 'LOCALHOST changed product');
            self::fail('A stale plan must fail.');
        } catch (\BarConflictException) {
            self::assertSame(0, (int)$this->pdo->query('SELECT SUM(stock_stall) FROM kis_bar_products')->fetchColumn());
            self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_stock_movements')->fetchColumn());
            self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_requests')->fetchColumn());
        }
    }

    public function testExistingStockCannotBeOverwritten(): void
    {
        $this->pdo->exec('UPDATE kis_bar_products SET stock_stall=7 WHERE id=1');
        $this->expectException(\BarConflictException::class);
        \barApplyOpeningStocks($this->pdo, $this->plan(), str_repeat('c', 64), 'LOCALHOST protect stock');
    }

    public function testOperatorCannotCopyStock(): void
    {
        $plan = $this->plan();
        \barTestLogin(2, 'bar_operator');
        $this->expectException(\DomainException::class);
        \barApplyOpeningStocks($this->pdo, $plan, str_repeat('d', 64), 'LOCALHOST forbidden stock');
    }

    public function testSignedLegacyBalancesAreCopiedWithoutRoundingOrReplacingWithZero(): void
    {
        $source = [['id' => 91, 'sklad' => -2, 'predsklad' => 0], ['id' => 92, 'sklad' => 3, 'predsklad' => -4]];
        $summary = \barOpeningStockSourceSummary($source);
        self::assertTrue($summary['valid']);
        self::assertSame(1, $summary['negative_stall']);
        self::assertSame(1, $summary['negative_office']);
        $result = \barApplyOpeningStocks($this->pdo, \barOpeningStockPlan($this->pdo, $source), str_repeat('e', 64), 'LOCALHOST exact opening balance');
        self::assertSame(1, $result['stall_total']);
        self::assertSame(-4, $result['office_total']);
        self::assertSame(-2, (int)$this->pdo->query('SELECT stock_stall FROM kis_bar_products WHERE id=1')->fetchColumn());
        self::assertSame(-4, (int)$this->pdo->query('SELECT stock_office FROM kis_bar_products WHERE id=2')->fetchColumn());
        self::assertSame(2, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_stock_movements WHERE quantity_after<0')->fetchColumn());
    }

    public function testInvalidSourceQuantitiesAreDiagnosedWithoutInventingTotals(): void
    {
        $summary = \barOpeningStockSourceSummary([['id' => 91, 'sklad' => '1.5', 'predsklad' => null],
            ['id' => 92, 'sklad' => 1000001, 'predsklad' => 0]]);
        self::assertFalse($summary['valid']);
        self::assertCount(3, $summary['invalid']);
        self::assertNull($summary['stall_total']);
        self::assertNull($summary['office_total']);
        $this->expectException(\InvalidArgumentException::class);
        \barOpeningStockQuantity('1.5');
    }
}
