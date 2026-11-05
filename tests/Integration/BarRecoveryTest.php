<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/BarFoundationFixture.php';
require_once dirname(__DIR__, 2) . '/includes/bar/recovery.php';
require_once dirname(__DIR__, 2) . '/includes/bar/accounting.php';
require_once dirname(__DIR__, 2) . '/includes/bar/request_status.php';
require_once dirname(__DIR__, 2) . '/includes/bar/stock.php';

final class BarRecoveryTest extends TestCase
{
    private PDO $pdo;
    private int $box;

    protected function setUp(): void
    {
        $this->pdo = \barTestDatabase();
        $this->pdo->exec("INSERT OR IGNORE INTO staff_positions(code,label,sort_order,active) VALUES('tv_operator','LOCALHOST TV',75,1)");
        $this->pdo->exec("INSERT INTO kis_bar_categories(id,name) VALUES(1,'LOCALHOST Drinks')");
        $this->pdo->exec("INSERT INTO kis_bar_products(id,category_id,name,description,price_minor,purchase_minor,stock_mode,stock_stall,stock_office,package_name) VALUES
            (1,1,'LOCALHOST Tea','',3500,1101,'sledovat',20,5,''),(2,1,'LOCALHOST Double tea','',7000,999,'sledovat',0,0,'')");
        $this->pdo->exec('INSERT INTO kis_bar_recipes(product_id,ingredient_id,quantity) VALUES(2,1,2)');
        \barTestLogin(2, 'bar_operator');
        $this->box = \barOpenCashbox($this->pdo, 100000, 'LOCALHOST opening', $this->token())['cashbox_id'];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function token(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function sale(string $method = 'qr', int $product = 1): int
    {
        $cart = [['product_id' => $product, 'quantity' => 1]];
        $quote = \barQuote($this->pdo, $cart);
        return \barSubmitSale($this->pdo, $this->box, $cart, 0, 0, $quote['fingerprint'], $method, $this->token())['sale_id'];
    }

    private function version(): int
    {
        return (int)$this->pdo->query('SELECT lock_version FROM kis_bar_cashboxes WHERE id=' . $this->box)->fetchColumn();
    }

    private function abandon(bool $disable = true): void
    {
        if ($disable) {
            $this->pdo->exec('UPDATE treneri SET aktivni=0 WHERE id=2');
        } else {
            $this->pdo->exec("DELETE FROM staff_user_positions WHERE trainer_id=2 AND position_code='bar_operator'");
        }
        \barTestLogin(3, 'bar_admin');
    }

    private function recover(string $action, int $sale = 0, string $reference = '', int $counted = 100000, ?int $version = null): array
    {
        return \barRecoverCashbox(
            $this->pdo,
            $this->box,
            $version ?? $this->version(),
            $action,
            $sale,
            $counted,
            $reference,
            true,
            'LOCALHOST reviewed recovery',
            $this->token()
        );
    }

    public function testActiveOwnerCannotBeReplacedByAdministrator(): void
    {
        \barTestLogin(3, 'bar_admin');
        $this->expectException(\DomainException::class);
        $this->recover('close');
    }

    public function testCloseUnavailableOwnerPreservesOwnershipRecordsCounterAndReplaysAfterReactivation(): void
    {
        $this->abandon();
        $args = [$this->pdo, $this->box, $this->version(), 'close', 0, 99900, '', true, 'LOCALHOST counted and taken over', $this->token()];
        $result = \barRecoverCashbox(...$args);
        self::assertSame(-100, $result['difference_minor']);
        $box = \barRecoveryCashbox($this->pdo, $this->box);
        self::assertSame(2, (int)$box['owner_id']);
        self::assertSame(3, (int)$box['closed_by']);
        self::assertNull($box['active_owner_id']);
        self::assertSame('recovery', $box['closure_kind']);
        self::assertSame(100000, \barCashBalance($this->pdo, $this->box));
        $this->pdo->exec('UPDATE treneri SET aktivni=1 WHERE id=2');
        self::assertTrue(\barRecoverCashbox(...$args)['repeated']);
        self::assertSame(1, (int)$this->pdo->query("SELECT COUNT(*) FROM kis_bar_events WHERE action='recovery_closed'")->fetchColumn());
        self::assertSame($box['closing_snapshot_json'], \barRecoveryCashbox($this->pdo, $this->box)['closing_snapshot_json']);
    }

    public function testRevokedBarRoleQualifiesButAnotherBarRoleStillProtectsOwner(): void
    {
        $this->abandon(false);
        $this->pdo->exec("INSERT INTO staff_user_positions(trainer_id,position_code,is_default) VALUES(2,'bar_admin',1)");
        self::assertTrue(\barRecoveryCashbox($this->pdo, $this->box)['owner_available']);
        $this->pdo->exec("DELETE FROM staff_user_positions WHERE trainer_id=2 AND position_code='bar_admin'");
        self::assertFalse(\barRecoveryCashbox($this->pdo, $this->box)['owner_available']);
        $this->recover('close');
        self::assertSame('recovery', \barRecoveryCashbox($this->pdo, $this->box)['closure_kind']);
    }

    public function testPendingPaymentBlocksClosureWithoutPartialMutation(): void
    {
        $this->sale();
        $this->abandon();
        try {
            $this->recover('close');
            self::fail('Unresolved payment allowed closure.');
        } catch (\DomainException) {
            self::assertNull(\barRecoveryCashbox($this->pdo, $this->box)['closed_at']);
            self::assertSame(0, (int)$this->pdo->query("SELECT COUNT(*) FROM kis_bar_events WHERE action='recovery_closed'")->fetchColumn());
        }
    }

    public function testRecoveryConfirmsActualPaymentWithAdminIdentityWithoutSecondStockWithdrawal(): void
    {
        $sale = $this->sale();
        $this->abandon();
        $stock = (int)$this->pdo->query('SELECT stock_stall FROM kis_bar_products WHERE id=1')->fetchColumn();
        $oldVersion = $this->version();
        $this->recover('confirm', $sale, 'LOCALHOST verified bank proof');
        $payment = \barSalePayment($this->pdo, $sale);
        self::assertSame('confirmed', $payment['status']);
        self::assertSame('manual', $payment['verification_kind']);
        self::assertSame(3, (int)$payment['confirmed_by']);
        self::assertSame($stock, (int)$this->pdo->query('SELECT stock_stall FROM kis_bar_products WHERE id=1')->fetchColumn());
        self::assertSame(2, (int)$this->pdo->query('SELECT actor_id FROM kis_bar_sales WHERE id=' . $sale)->fetchColumn());
        self::assertSame(100000, \barCashBalance($this->pdo, $this->box));
        try {
            $this->recover('close', 0, '', 100000, $oldVersion);
            self::fail('Stale closing count accepted.');
        } catch (\BarConflictException) {
            $this->recover('close');
            self::assertNotNull(\barRecoveryCashbox($this->pdo, $this->box)['closed_at']);
        }
    }

    public function testCancellationRestoresOriginalRecipeOnlyAndCannotCancelConfirmedPayment(): void
    {
        $pending = $this->sale('card', 2);
        $this->abandon();
        \barSetRecipe($this->pdo, 2, 1, [['id' => 1, 'quantity' => 3]], 'LOCALHOST recipe changed after reservation');
        $this->recover('cancel', $pending);
        self::assertSame(20, (int)$this->pdo->query('SELECT stock_stall FROM kis_bar_products WHERE id=1')->fetchColumn());
        self::assertSame('cancelled', \barSalePayment($this->pdo, $pending)['status']);
        self::assertSame(0, (int)$this->pdo->query('SELECT refunded_minor FROM kis_bar_voids WHERE sale_id=' . $pending)->fetchColumn());
        self::assertSame(100000, \barCashBalance($this->pdo, $this->box));
        self::assertArrayNotHasKey('bar:sale:' . $pending, \barAccountingSources($this->pdo));
        $this->expectException(\DomainException::class);
        $this->recover('confirm', $pending, 'LOCALHOST invalid late proof');
    }

    public function testMissingExplicitConfirmationCannotTouchAbandonedCashbox(): void
    {
        $this->abandon();
        $this->expectException(\InvalidArgumentException::class);
        \barRecoverCashbox($this->pdo, $this->box, $this->version(), 'close', 0, 100000, '', false, 'LOCALHOST no confirmation', $this->token());
    }

    public static function positions(): array
    {
        return array_map(static fn (string $position): array => [$position], \staffPositionCodes());
    }

    #[DataProvider('positions')]
    public function testAllWorkingPositionsHaveExplicitBarBoundaries(string $position): void
    {
        $this->pdo->exec('DELETE FROM staff_user_positions WHERE trainer_id=4');
        $this->pdo->prepare('INSERT INTO staff_user_positions(trainer_id,position_code,is_default) VALUES(4,?,1)')->execute([$position]);
        \barTestLogin(4, $position);
        $caps = ['sell' => ['bar_operator', 'bar_admin'], 'catalog_manage' => ['bar_admin'], 'invoice_manage' => ['bar_admin'],
            'invoice_read' => ['bar_admin', 'finance_manager'], 'accounting' => ['bar_admin', 'finance_manager'],
            'cash_recovery' => ['bar_admin'], 'transfer' => ['system_admin'], 'own_consumption' => []];
        $caps['request_status'] = ['bar_operator', 'bar_admin', 'finance_manager'];
        foreach ($caps as $capability => $allowed) {
            try {
                \barActor($this->pdo, $capability);
                self::assertContains($position, $allowed, $capability);
            } catch (\DomainException) {
                self::assertNotContains($position, $allowed, $capability);
            }
        }
    }

    public function testUtcPeriodsAndDisplaysDistinguishBothAutumnHours(): void
    {
        self::assertSame(['2026-03-28 23:00:00', '2026-03-29 22:00:00'], \barUtcPeriod('2026-03-29', '2026-03-29'));
        self::assertSame(['2026-10-24 22:00:00', '2026-10-25 23:00:00'], \barUtcPeriod('2026-10-25', '2026-10-25'));
        self::assertSame('25. 10. 2026 02:30:00 CEST', \barDisplayTime('2026-10-25 00:30:00'));
        self::assertSame('25. 10. 2026 02:30:00 CET', \barDisplayTime('2026-10-25 01:30:00'));
    }

    public function testReportUsesPragueMidnightsAndKeepsAlreadyExportedSales(): void
    {
        $times = ['2026-03-28 22:59:59', '2026-03-28 23:00:00', '2026-03-29 21:59:59', '2026-03-29 22:00:00'];
        foreach ($times as $time) {
            $sale = $this->sale('cash');
            $this->pdo->prepare('UPDATE kis_bar_sale_payments SET confirmed_at=? WHERE sale_id=?')->execute([$time, $sale]);
        }
        \barTestLogin(3, 'bar_admin');
        $report = \barAccountingReport($this->pdo, '2026-03-29', '2026-03-29');
        self::assertSame(7000, $report['goods']);
        self::assertSame(7000, $report['cash']);
        $selection = array_map(static fn (array $row): string => $row['sha256'], \barAccountingSources($this->pdo));
        $export = \barCreateAccountingExport($this->pdo, $selection, 'LOCALHOST checked period', $this->token());
        $manifest = json_decode(\barAccountingExport($this->pdo, $export['export_id'])['manifest_json'], true, 64, JSON_THROW_ON_ERROR);
        self::assertSame('UTC', $manifest['source_timezone']);
        self::assertSame($report, \barAccountingReport($this->pdo, '2026-03-29', '2026-03-29'));
    }

    public function testMigrationDoesNotSilentlyRelabelExistingFinancialHistory(): void
    {
        $this->pdo->exec('UPDATE kis_bar_state SET clock_contract=NULL WHERE id=1');
        $migration = require dirname(__DIR__, 2) . '/migrations/20261105210000_bar_recovery.php';
        $migration['up']($this->pdo);
        $migration['up']($this->pdo);
        self::assertSame('legacy-local', $this->pdo->query('SELECT clock_contract FROM kis_bar_state WHERE id=1')->fetchColumn());
        self::assertTrue($migration['verify']($this->pdo));
        $this->expectException(\DomainException::class);
        \barAdjustCash($this->pdo, $this->box, 100, 'LOCALHOST mixed clocks blocked', $this->token());
    }

    public function testRequestStatusRevealsOnlyOwnResultLinkAndNeverAnotherActorsPayload(): void
    {
        $token = (string)$this->pdo->query("SELECT token FROM kis_bar_requests WHERE actor_id=2 AND operation='cashbox_open'")->fetchColumn();
        self::assertSame(['state' => 'completed', 'result_route' => 'bar/cashbox.php?id=' . $this->box], \barRequestStatus($this->pdo, $token));
        \barTestLogin(3, 'bar_admin');
        self::assertSame(['state' => 'unknown', 'result_route' => null], \barRequestStatus($this->pdo, $token));
        $stockToken = $this->token();
        \barStockOperation($this->pdo, 1, 1, 'receive_office', 1, $stockToken, 'LOCALHOST status for stock receipt');
        self::assertSame('bar/stock.php?product_id=1', \barRequestStatus($this->pdo, $stockToken)['result_route']);
        $this->pdo->exec("DELETE FROM staff_user_positions WHERE trainer_id=3 AND position_code='bar_admin'");
        $this->expectException(\DomainException::class);
        \barRequestStatus($this->pdo, $stockToken);
    }
}
