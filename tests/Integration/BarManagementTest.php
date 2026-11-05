<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/BarFoundationFixture.php';
require_once dirname(__DIR__, 2) . '/includes/bar/stock.php';

final class BarManagementTest extends TestCase
{
    private PDO $pdo;
    private int $productId;
    private array $input;

    protected function setUp(): void
    {
        $this->pdo = \barTestDatabase();
        \barTestLogin(3, 'bar_admin');
        $this->pdo->exec("INSERT INTO kis_bar_categories(id,name) VALUES(1,'LOCALHOST Drinks')");
        $this->input = ['name' => 'LOCALHOST Tea', 'description' => 'Original', 'category_id' => 1, 'price' => '35.00',
            'purchase_price' => '11.01', 'stock_mode' => 'sledovat', 'minimum_stock' => 1, 'on_terminal' => 1];
        $this->productId = \barSaveProduct($this->pdo, 0, 0, $this->input, 'LOCALHOST create product');
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function stock(string $kind, int $quantity, ?string $token = null, ?int $version = null): array
    {
        return \barStockOperation(
            $this->pdo,
            $this->productId,
            $version ?? $this->version(),
            $kind,
            $quantity,
            $token ?? bin2hex(random_bytes(32)),
            'LOCALHOST confirmed operation'
        );
    }

    private function version(): int
    {
        return (int)$this->pdo->query('SELECT lock_version FROM kis_bar_products WHERE id=' . $this->productId)->fetchColumn();
    }

    private function quantities(): array
    {
        $row = $this->pdo->query('SELECT stock_stall,stock_office FROM kis_bar_products WHERE id=' . $this->productId)->fetch(PDO::FETCH_ASSOC);
        return [(int)$row['stock_stall'], (int)$row['stock_office']];
    }

    public function testReceiveTransferAndInventoryPreserveStockAndHistoricalCosts(): void
    {
        $this->stock('receive_office', 100);
        $this->stock('to_stall', 20);
        self::assertSame([20, 80], $this->quantities());
        self::assertSame(100, (int)$this->pdo->query('SELECT SUM(quantity_delta) FROM kis_bar_stock_movements')->fetchColumn());
        \barSaveProduct($this->pdo, $this->productId, $this->version(), array_replace($this->input, ['purchase_price' => '25.00']), 'LOCALHOST new purchase cost');
        $this->stock('receive_stall', 1);
        self::assertSame([21, 80], $this->quantities());
        self::assertSame([1101, 1101, 1101, 2500], array_map('intval', $this->pdo->query('SELECT purchase_minor FROM kis_bar_stock_movements ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)));
        $this->stock('inventory_stall', 0);
        self::assertSame([0, 80], $this->quantities());
        self::assertSame(-21, (int)$this->pdo->query('SELECT quantity_delta FROM kis_bar_stock_movements ORDER BY id DESC LIMIT 1')->fetchColumn());
    }

    public function testSameTokenDoesNotDuplicateAndModifiedRequestCannotReuseIt(): void
    {
        $token = str_repeat('a', 64);
        $first = $this->stock('receive_office', 10, $token, 1);
        $repeat = $this->stock('receive_office', 10, $token, 1);
        self::assertSame($first['id'], $repeat['id']);
        self::assertTrue($repeat['repeated']);
        self::assertSame([0, 10], $this->quantities());
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_stock_operations')->fetchColumn());
        $this->expectException(\BarConflictException::class);
        $this->stock('receive_office', 11, $token, 1);
    }

    public function testStaleInventoryAndInsufficientTransferLeaveNoJournalOrAudit(): void
    {
        $this->stock('receive_office', 10);
        $events = (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_events')->fetchColumn();
        try {
            $this->stock('inventory_office', 5, null, 1);
            self::fail('A stale inventory overwrote the receipt.');
        } catch (\BarConflictException $expected) {
            self::assertSame([0, 10], $this->quantities());
        }
        try {
            $this->stock('to_stall', 11);
            self::fail('An insufficient transfer was committed.');
        } catch (\DomainException $expected) {
            self::assertSame([0, 10], $this->quantities());
            self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_stock_operations')->fetchColumn());
            self::assertSame($events, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_events')->fetchColumn());
        }
    }

    public function testAuditFailureRollsBackStockJournalAndVersion(): void
    {
        $this->pdo->exec("CREATE TRIGGER deny_stock_audit BEFORE INSERT ON kis_bar_events BEGIN SELECT RAISE(ABORT,'LOCALHOST failure'); END");
        try {
            $this->stock('receive_office', 10);
            self::fail('An unaudited receipt was committed.');
        } catch (\PDOException $expected) {
            self::assertSame([0, 0], $this->quantities());
            self::assertSame(1, $this->version());
            self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_stock_movements')->fetchColumn());
        }
    }

    public function testCatalogueCannotSetPhysicalStockOrArchiveNonzeroStock(): void
    {
        $this->stock('receive_stall', 10);
        \barSaveProduct($this->pdo, $this->productId, $this->version(), $this->input + ['stock_stall' => 999, 'stock_office' => 999], 'LOCALHOST harmless catalogue edit');
        self::assertSame([10, 0], $this->quantities());
        $this->expectException(\DomainException::class);
        \barSaveProduct($this->pdo, $this->productId, $this->version(), array_replace($this->input, ['archived' => 1]), 'LOCALHOST cannot archive stock');
    }

    public function testUnknownCostRemainsNullAndStaleCatalogueEditDoesNotOverwrite(): void
    {
        \barSaveProduct($this->pdo, $this->productId, 1, array_replace($this->input, ['purchase_price' => '']), 'LOCALHOST unknown cost');
        $this->stock('receive_office', 1);
        self::assertNull($this->pdo->query('SELECT purchase_minor FROM kis_bar_stock_movements')->fetchColumn());
        $this->expectException(\BarConflictException::class);
        \barSaveProduct($this->pdo, $this->productId, 1, $this->input, 'LOCALHOST stale cost');
    }

    public function testCzechDecimalInputIsAcceptedWithoutChangingHistoricalCost(): void
    {
        \barSaveProduct($this->pdo, $this->productId, 1, array_replace($this->input, ['price' => '35,90', 'purchase_price' => '0,01']), 'LOCALHOST Czech decimals');
        self::assertSame(3590, (int)$this->pdo->query('SELECT price_minor FROM kis_bar_products')->fetchColumn());
        self::assertSame(1, (int)$this->pdo->query('SELECT purchase_minor FROM kis_bar_products')->fetchColumn());
    }

    public function testRecipeUsesSimpleTrackedIngredientsAndHasNoOwnStock(): void
    {
        $ingredient = \barSaveProduct($this->pdo, 0, 0, array_replace($this->input, ['name' => 'LOCALHOST Ingredient']), 'LOCALHOST ingredient');
        \barSetRecipe($this->pdo, $this->productId, 1, [['id' => $ingredient, 'quantity' => 2]], 'LOCALHOST recipe');
        self::assertSame(2, (int)$this->pdo->query('SELECT quantity FROM kis_bar_recipes')->fetchColumn());
        try {
            $this->stock('receive_stall', 1);
            self::fail('A recipe received physical stock.');
        } catch (\DomainException $expected) {
            self::assertSame([0, 0], $this->quantities());
        }
        $this->expectException(\DomainException::class);
        \barSetRecipe($this->pdo, $ingredient, 1, [['id' => $this->productId, 'quantity' => 1]], 'LOCALHOST nested recipe');
    }

    public function testRevokedBarAdministratorAndOrdinaryOperatorCannotWriteStock(): void
    {
        $this->pdo->exec('DELETE FROM staff_user_positions WHERE trainer_id=3');
        try {
            $this->stock('receive_office', 1);
            self::fail('A revoked administrator wrote stock.');
        } catch (\DomainException $expected) {
            self::assertSame([0, 0], $this->quantities());
        }
        \barTestLogin(2, 'bar_operator');
        $this->expectException(\DomainException::class);
        $this->stock('receive_office', 1);
    }
}
