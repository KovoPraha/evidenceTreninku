<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/BarFoundationFixture.php';
require_once dirname(__DIR__, 2) . '/includes/bar/checkout_read.php';
require_once dirname(__DIR__, 2) . '/includes/bar/stock.php';

final class BarCheckoutTest extends TestCase
{
    private PDO $pdo;
    private int $box;

    protected function setUp(): void
    {
        $this->pdo = \barTestDatabase();
        $this->pdo->exec("INSERT INTO kis_bar_categories(id,name) VALUES(1,'LOCALHOST Drinks')");
        $this->pdo->exec("INSERT INTO kis_bar_products(id,category_id,name,description,price_minor,purchase_minor,stock_mode,stock_stall,stock_office,package_name) VALUES
            (1,1,'LOCALHOST Tea','Tea receipt',3500,1101,'sledovat',20,30,''),
            (2,1,'LOCALHOST Double tea','Recipe receipt',9000,999,'sledovat',0,0,''),
            (3,1,'LOCALHOST Service','Unknown cost',999,NULL,'nesledovat',0,0,'')");
        $this->pdo->exec('INSERT INTO kis_bar_recipes(product_id,ingredient_id,quantity) VALUES(2,1,2)');
        \barTestLogin(2, 'bar_operator');
        $this->box = \barOpenCashbox($this->pdo, 10000, 'LOCALHOST initial cash', $this->token())['cashbox_id'];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function token(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function cart(int $id = 1, int $quantity = 1): array
    {
        return [['product_id' => $id, 'quantity' => $quantity]];
    }

    private function sale(array $cart, string $method = 'cash', int $discount = 0, int $cups = 0, ?string $token = null): array
    {
        $quote = \barQuote($this->pdo, $cart, $discount, $cups);
        return \barSubmitSale($this->pdo, $this->box, $cart, $discount, $cups, $quote['fingerprint'], $method, $token ?? $this->token());
    }

    private function version(?int $id = null): int
    {
        return (int)$this->pdo->query('SELECT lock_version FROM kis_bar_cashboxes WHERE id=' . ($id ?? $this->box))->fetchColumn();
    }

    private function stock(string $column = 'stock_stall'): int
    {
        return (int)$this->pdo->query('SELECT ' . $column . ' FROM kis_bar_products WHERE id=1')->fetchColumn();
    }

    private function grant(int $id): void
    {
        \barTestLogin(1, 'system_admin');
        \barSetConsumptionPermission($this->pdo, $id, true, 'LOCALHOST explicit consumption permission');
        \barTestLogin($id, $id === 2 ? 'bar_operator' : 'bar_admin');
    }

    public function testEachOwnerHasOwnCashboxAndCanOnlyOpenOne(): void
    {
        \barTestLogin(3, 'bar_admin');
        $other = \barOpenCashbox($this->pdo, 5000, 'LOCALHOST second owner', $this->token());
        self::assertNotSame($this->box, $other['cashbox_id']);
        self::assertSame($other['cashbox_id'], (int)\barOwnOpenCashbox($this->pdo)['id']);
        self::assertSame(2, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_cashboxes WHERE closed_at IS NULL')->fetchColumn());
        $this->expectException(\BarConflictException::class);
        \barOpenCashbox($this->pdo, 5000, 'LOCALHOST duplicate cashbox', $this->token());
    }

    public function testQuoteSeparatesRoundedDiscountAndDepositsWithoutDisclosingCosts(): void
    {
        $this->pdo->exec('UPDATE kis_bar_settings SET cup_deposit_minor=2000 WHERE id=1');
        $quote = \barQuote($this->pdo, [...$this->cart(1, 2), ...$this->cart(3)], 20, 2);
        self::assertSame(7999, $quote['goods_gross_minor']);
        self::assertSame(6000, $quote['goods_net_minor']);
        self::assertSame(1999, $quote['discount_minor']);
        self::assertSame(4000, $quote['deposit_minor']);
        self::assertSame(10000, $quote['total_minor']);
        self::assertSame(6000, array_sum(array_column($quote['lines'], 'net_minor')));
        self::assertArrayNotHasKey('products', $quote);
        self::assertArrayNotHasKey('deductions', $quote);
        self::assertArrayNotHasKey('purchase_unit_minor', $quote['lines'][0]);
        $sale = $this->sale([...$this->cart(1, 2), ...$this->cart(3)], 'cash', 20, 2);
        self::assertSame(20000, \barCashBalance($this->pdo, $this->box));
        self::assertSame(10000, (int)\barSaleReceipt($this->pdo, $sale['sale_id'])['payment']['amount_minor']);
        self::assertNotNull(\barSaleReceipt($this->pdo, $sale['sale_id'])['payment']['confirmed_at']);
    }

    public function testCashSaleAggregatesIngredientsAndVoidRestoresOriginalRecipeAndCosts(): void
    {
        $sale = $this->sale([...$this->cart(1), ...$this->cart(2, 2)]);
        self::assertSame(15, $this->stock());
        self::assertSame(31500, \barCashBalance($this->pdo, $this->box));
        self::assertSame([1101, 2202], array_map('intval', $this->pdo->query('SELECT purchase_unit_minor FROM kis_bar_sale_lines ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)));
        $this->pdo->exec('UPDATE kis_bar_recipes SET quantity=3 WHERE product_id=2');
        $this->pdo->exec('UPDATE kis_bar_products SET purchase_minor=9999,price_minor=4700 WHERE id=1');
        $token = $this->token();
        $void = \barVoidSale($this->pdo, $sale['sale_id'], $this->box, '', 'LOCALHOST returned goods', $token);
        self::assertSame(20, $this->stock());
        self::assertSame(10000, \barCashBalance($this->pdo, $this->box));
        self::assertSame(1101, (int)$this->pdo->query('SELECT purchase_minor FROM kis_bar_stock_movements ORDER BY id DESC LIMIT 1')->fetchColumn());
        self::assertSame(3500, (int)$this->pdo->query('SELECT unit_price_minor FROM kis_bar_sale_lines ORDER BY id LIMIT 1')->fetchColumn());
        self::assertTrue(\barVoidSale($this->pdo, $sale['sale_id'], $this->box, '', 'LOCALHOST returned goods', $token)['repeated']);
        self::assertSame(21500, $void['refunded_minor']);
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_voids')->fetchColumn());
    }

    public function testUnknownUntrackedCostRemainsNullAndDoesNotCreatePhysicalStock(): void
    {
        $sale = $this->sale($this->cart(3));
        self::assertNull($this->pdo->query('SELECT purchase_unit_minor FROM kis_bar_sale_lines')->fetchColumn());
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_sale_components')->fetchColumn());
        self::assertArrayNotHasKey('purchase_unit_minor', \barSaleReceipt($this->pdo, $sale['sale_id'])['lines'][0]);
    }

    public function testInsufficientAggregateStockRollsBackAllSaleFinancialAndRequestRows(): void
    {
        $this->pdo->exec('UPDATE kis_bar_products SET stock_stall=2 WHERE id=1');
        $before = (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_requests')->fetchColumn();
        try {
            $this->sale([...$this->cart(1), ...$this->cart(2)]);
            self::fail('Shared ingredient was oversold.');
        } catch (\DomainException $expected) {
            self::assertSame(2, $this->stock());
            self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_sales')->fetchColumn());
            self::assertSame($before, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_requests')->fetchColumn());
            self::assertSame(10000, \barCashBalance($this->pdo, $this->box));
        }
    }

    public function testReplayAfterClosureReturnsOriginalWithoutTouchingNewCashbox(): void
    {
        $cart = $this->cart();
        $quote = \barQuote($this->pdo, $cart);
        $token = $this->token();
        $sale = \barSubmitSale($this->pdo, $this->box, $cart, 0, 0, $quote['fingerprint'], 'cash', $token);
        \barCloseCashbox($this->pdo, $this->box, $this->version(), 13500, 'LOCALHOST exact closing', $this->token());
        $newBox = \barOpenCashbox($this->pdo, 8000, 'LOCALHOST new cashbox', $this->token())['cashbox_id'];
        $repeat = \barSubmitSale($this->pdo, $this->box, $cart, 0, 0, $quote['fingerprint'], 'cash', $token);
        self::assertTrue($repeat['repeated']);
        self::assertSame($sale['sale_id'], $repeat['sale_id']);
        self::assertSame(19, $this->stock());
        self::assertSame(8000, \barCashBalance($this->pdo, $newBox));
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_sales')->fetchColumn());
    }

    public function testTokenIsBoundToOperationActorAndCompleteRequest(): void
    {
        $token = $this->token();
        $cart = $this->cart();
        $quote = \barQuote($this->pdo, $cart);
        \barSubmitSale($this->pdo, $this->box, $cart, 0, 0, $quote['fingerprint'], 'cash', $token);
        foreach (['body', 'operation', 'actor'] as $case) {
            try {
                if ($case === 'operation') {
                    \barAdjustCash($this->pdo, $this->box, 100, 'LOCALHOST reused token', $token);
                } else {
                    if ($case === 'actor') {
                        \barTestLogin(3, 'bar_admin');
                    }
                    \barSubmitSale($this->pdo, $this->box, $cart, 0, 0, $quote['fingerprint'], $case === 'body' ? 'card' : 'cash', $token);
                }
                self::fail('Token was reused.');
            } catch (\BarConflictException $expected) {
                self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_sales')->fetchColumn());
            }
        }
    }

    public function testPendingCardReservesStockBlocksClosureAndManualConfirmationDoesNotDeductAgain(): void
    {
        $sale = $this->sale($this->cart(), 'card');
        self::assertSame('pending', $sale['payment_status']);
        self::assertSame(19, $this->stock());
        self::assertSame(10000, \barCashBalance($this->pdo, $this->box));
        try {
            \barCloseCashbox($this->pdo, $this->box, $this->version(), 10000, 'LOCALHOST premature close', $this->token());
            self::fail('Pending payment was closed.');
        } catch (\DomainException $expected) {
            self::assertNotNull(\barOwnOpenCashbox($this->pdo));
        }
        $token = $this->token();
        \barConfirmPayment($this->pdo, $sale['sale_id'], 'LOCALHOST terminal receipt 123', $token);
        self::assertTrue(\barConfirmPayment($this->pdo, $sale['sale_id'], 'LOCALHOST terminal receipt 123', $token)['repeated']);
        self::assertSame('manual', \barSaleReceipt($this->pdo, $sale['sale_id'])['payment']['verification_kind']);
        self::assertSame(19, $this->stock());
        self::assertSame(10000, \barCashBalance($this->pdo, $this->box));
        \barCloseCashbox($this->pdo, $this->box, $this->version(), 10000, 'LOCALHOST completed closing', $this->token());
        self::assertNull(\barOwnOpenCashbox($this->pdo));
    }

    public function testPendingQrCancellationRestoresStockWithoutInventingRefund(): void
    {
        $sale = $this->sale($this->cart(2), 'qr');
        self::assertSame(18, $this->stock());
        $void = \barVoidSale($this->pdo, $sale['sale_id'], null, '', 'LOCALHOST unpaid cancellation', $this->token());
        self::assertSame(0, $void['refunded_minor']);
        self::assertSame(20, $this->stock());
        self::assertSame(10000, \barCashBalance($this->pdo, $this->box));
        self::assertSame('cancelled', \barSaleReceipt($this->pdo, $sale['sale_id'])['payment']['status']);
    }

    public function testConfirmedNoncashVoidRequiresEvidenceAndNeverChangesCash(): void
    {
        $sale = $this->sale($this->cart(), 'card');
        \barConfirmPayment($this->pdo, $sale['sale_id'], 'LOCALHOST payment proof', $this->token());
        try {
            \barVoidSale($this->pdo, $sale['sale_id'], $this->box, '', 'LOCALHOST missing refund proof', $this->token());
            self::fail('Refund without proof accepted.');
        } catch (\InvalidArgumentException $expected) {
            self::assertSame(19, $this->stock());
        }
        $void = \barVoidSale($this->pdo, $sale['sale_id'], $this->box, 'LOCALHOST refund proof', 'LOCALHOST refunded outside KIS', $this->token());
        self::assertSame(3500, $void['refunded_minor']);
        self::assertSame(10000, \barCashBalance($this->pdo, $this->box));
        self::assertSame('confirmed', \barSaleReceipt($this->pdo, $sale['sale_id'])['payment']['status']);
        self::assertSame('card', \barSaleReceipt($this->pdo, $sale['sale_id'])['void']['refund_method']);
    }

    public function testClosingVersionConflictAndFrozenSnapshotSurviveRefundInLaterCashbox(): void
    {
        $oldVersion = $this->version();
        $sale = $this->sale($this->cart());
        try {
            \barCloseCashbox($this->pdo, $this->box, $oldVersion, 10000, 'LOCALHOST stale closing', $this->token());
            self::fail('Stale close succeeded.');
        } catch (\BarConflictException $expected) {
            self::assertNotNull(\barOwnOpenCashbox($this->pdo));
        }
        $closingToken = $this->token();
        $version = $this->version();
        $closed = \barCloseCashbox($this->pdo, $this->box, $version, 13400, 'LOCALHOST counted shortage 100', $closingToken);
        self::assertSame(-100, $closed['difference_minor']);
        self::assertTrue(\barCloseCashbox($this->pdo, $this->box, $version, 13400, 'LOCALHOST counted shortage 100', $closingToken)['repeated']);
        $snapshot = \barCashboxView($this->pdo, $this->box)['totals'];
        $newBox = \barOpenCashbox($this->pdo, 10000, 'LOCALHOST refund cashbox', $this->token())['cashbox_id'];
        \barVoidSale($this->pdo, $sale['sale_id'], $newBox, '', 'LOCALHOST refund after closure', $this->token());
        self::assertSame(6500, \barCashBalance($this->pdo, $newBox));
        self::assertSame($snapshot, \barCashboxView($this->pdo, $this->box)['totals']);
        self::assertSame(13500, \barCashBalance($this->pdo, $this->box));
    }

    public function testCashWithdrawalCannotMakeCashNegativeAndIsIdempotent(): void
    {
        $token = $this->token();
        $adjusted = \barAdjustCash($this->pdo, $this->box, -10000, 'LOCALHOST cash removal', $token);
        self::assertGreaterThan(0, $adjusted['movement_id']);
        self::assertTrue(\barAdjustCash($this->pdo, $this->box, -10000, 'LOCALHOST cash removal', $token)['repeated']);
        self::assertSame(0, \barCashBalance($this->pdo, $this->box));
        $this->expectException(\DomainException::class);
        \barAdjustCash($this->pdo, $this->box, -1, 'LOCALHOST excessive removal', $this->token());
    }

    public function testCupRefundUsesOriginalRateAndVoidSubtractsAlreadyReturnedDeposit(): void
    {
        $this->pdo->exec('UPDATE kis_bar_settings SET cup_deposit_minor=2000');
        $sale = $this->sale($this->cart(), 'cash', 0, 2);
        $this->pdo->exec('UPDATE kis_bar_settings SET cup_deposit_minor=5000');
        $returned = \barReturnCups($this->pdo, $sale['sale_id'], $this->box, 1, 'LOCALHOST one cup returned', $this->token());
        self::assertSame(2000, $returned['refunded_minor']);
        $void = \barVoidSale($this->pdo, $sale['sale_id'], $this->box, '', 'LOCALHOST remaining goods and cup returned', $this->token());
        self::assertSame(5500, $void['refunded_minor']);
        self::assertSame(10000, \barCashBalance($this->pdo, $this->box));
        self::assertSame(20, $this->stock());
        self::assertSame(1, (int)$this->pdo->query('SELECT cup_quantity FROM kis_bar_voids')->fetchColumn());
    }

    public function testCupRefundCanUseAnotherOperatorsOwnDrawerButCannotExceedReceipt(): void
    {
        $this->pdo->exec('UPDATE kis_bar_settings SET cup_deposit_minor=2000');
        $sale = $this->sale([], 'cash', 0, 1);
        \barTestLogin(3, 'bar_admin');
        $box = \barOpenCashbox($this->pdo, 5000, 'LOCALHOST second drawer', $this->token())['cashbox_id'];
        \barReturnCups($this->pdo, $sale['sale_id'], $box, 1, 'LOCALHOST physical cup returned', $this->token());
        self::assertSame(3000, \barCashBalance($this->pdo, $box));
        $this->expectException(\DomainException::class);
        \barReturnCups($this->pdo, $sale['sale_id'], $box, 1, 'LOCALHOST duplicate physical cup', $this->token());
    }

    public function testFreeConsumptionNeedsExplicitGrantEvenForAdminOrSuperadmin(): void
    {
        foreach ([[2, 'bar_operator'], [3, 'bar_admin'], [5, 'bar_admin']] as [$id, $position]) {
            \barTestLogin($id, $position);
            try {
                \barSubmitConsumption($this->pdo, $this->cart(), 'stall', str_repeat('a', 64), 'LOCALHOST forbidden free consumption', $this->token());
                self::fail('Consumption grant implied by position.');
            } catch (\DomainException $expected) {
                self::assertSame(20, $this->stock());
            }
        }
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_sales')->fetchColumn());
    }

    public function testConsumptionHasNoPaymentAndCanBeReversedAfterGrantIsRevoked(): void
    {
        $this->grant(2);
        $cart = $this->cart(2, 2);
        $quote = \barQuote($this->pdo, $cart, 0, 0, true);
        $token = $this->token();
        $sale = \barSubmitConsumption($this->pdo, $cart, 'stall', $quote['fingerprint'], 'LOCALHOST authorized consumption', $token);
        self::assertSame(16, $this->stock());
        self::assertSame(0, $sale['total_minor']);
        self::assertNull(\barSaleReceipt($this->pdo, $sale['sale_id'])['payment']);
        self::assertSame(10000, \barCashBalance($this->pdo, $this->box));
        $this->pdo->exec('DELETE FROM kis_bar_permissions WHERE trainer_id=2');
        self::assertTrue(\barSubmitConsumption($this->pdo, $cart, 'stall', $quote['fingerprint'], 'LOCALHOST authorized consumption', $token)['repeated']);
        \barVoidSale($this->pdo, $sale['sale_id'], null, '', 'LOCALHOST undo consumption', $this->token());
        self::assertSame(20, $this->stock());
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_sale_payments')->fetchColumn());
        self::assertSame(2202, (int)$this->pdo->query('SELECT purchase_unit_minor FROM kis_bar_sale_lines')->fetchColumn());
    }

    public function testOperatorCannotConsumeFromOfficeButGrantedAdminCanWithoutDrawer(): void
    {
        $this->grant(2);
        $quote = \barQuote($this->pdo, $this->cart(), 0, 0, true);
        try {
            \barSubmitConsumption($this->pdo, $this->cart(), 'office', $quote['fingerprint'], 'LOCALHOST forbidden office', $this->token());
            self::fail('Operator reached office.');
        } catch (\DomainException $expected) {
            self::assertSame(30, $this->stock('stock_office'));
        }
        $this->grant(3);
        $quote = \barQuote($this->pdo, $this->cart(2), 0, 0, true);
        $sale = \barSubmitConsumption($this->pdo, $this->cart(2), 'office', $quote['fingerprint'], 'LOCALHOST admin office consumption', $this->token());
        self::assertSame(28, $this->stock('stock_office'));
        self::assertSame(20, $this->stock());
        self::assertNull(\barOwnOpenCashbox($this->pdo));
        \barVoidSale($this->pdo, $sale['sale_id'], null, '', 'LOCALHOST office consumption returned', $this->token());
        self::assertSame(30, $this->stock('stock_office'));
    }

    public function testPriceAndRecipeChangesRequireNewQuoteWhileStockOnlyChangesDoNot(): void
    {
        foreach (['price', 'recipe'] as $change) {
            $quote = \barQuote($this->pdo, $this->cart(2));
            $this->pdo->exec($change === 'price' ? 'UPDATE kis_bar_products SET price_minor=price_minor+1 WHERE id=2'
                : 'UPDATE kis_bar_recipes SET quantity=quantity+1 WHERE product_id=2');
            try {
                \barSubmitSale($this->pdo, $this->box, $this->cart(2), 0, 0, $quote['fingerprint'], 'cash', $this->token());
                self::fail('Stale commercial quote accepted.');
            } catch (\BarConflictException $expected) {
                self::assertSame(20, $this->stock());
            }
        }
        $quote = \barQuote($this->pdo, $this->cart());
        $this->sale($this->cart());
        \barSubmitSale($this->pdo, $this->box, $this->cart(), 0, 0, $quote['fingerprint'], 'cash', $this->token());
        self::assertSame(18, $this->stock());
    }

    public function testAuditFailureRollsBackSaleAndRequestAndRetryCommitsExactlyOnce(): void
    {
        $quote = \barQuote($this->pdo, $this->cart());
        $token = $this->token();
        $this->pdo->exec("CREATE TRIGGER fail_checkout_audit BEFORE INSERT ON kis_bar_events BEGIN SELECT RAISE(FAIL,'LOCALHOST audit failure'); END");
        try {
            \barSubmitSale($this->pdo, $this->box, $this->cart(), 0, 0, $quote['fingerprint'], 'cash', $token);
            self::fail('Sale survived missing audit.');
        } catch (\PDOException $expected) {
            self::assertSame(20, $this->stock());
            self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_sales')->fetchColumn());
            self::assertSame(10000, \barCashBalance($this->pdo, $this->box));
        }
        $this->pdo->exec('DROP TRIGGER fail_checkout_audit');
        \barSubmitSale($this->pdo, $this->box, $this->cart(), 0, 0, $quote['fingerprint'], 'cash', $token);
        self::assertTrue(\barSubmitSale($this->pdo, $this->box, $this->cart(), 0, 0, $quote['fingerprint'], 'cash', $token)['repeated']);
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_sales')->fetchColumn());
        self::assertSame(19, $this->stock());
    }

    public function testCurrentRoleAndOwnershipProtectOtherDrawersReceiptsAndVoids(): void
    {
        $sale = $this->sale($this->cart());
        \barTestLogin(3, 'bar_admin');
        foreach (['cash', 'receipt', 'void'] as $operation) {
            try {
                match ($operation) {
                    'cash' => \barAdjustCash($this->pdo, $this->box, 100, 'LOCALHOST foreign cashbox', $this->token()),
                    'receipt' => \barSaleReceipt($this->pdo, $sale['sale_id']),
                    'void' => \barVoidSale($this->pdo, $sale['sale_id'], $this->box, '', 'LOCALHOST foreign sale void', $this->token()),
                };
                self::fail('Foreign record was accessible.');
            } catch (\DomainException $expected) {
                self::assertSame(19, $this->stock());
            }
        }
        \barTestLogin(2, 'bar_operator');
        $this->pdo->exec('DELETE FROM staff_user_positions WHERE trainer_id=2');
        $this->expectException(\DomainException::class);
        \barAdjustCash($this->pdo, $this->box, 100, 'LOCALHOST revoked actor', $this->token());
    }

    public function testInputCannotTruncateQuantitiesOrAcceptArbitraryDiscount(): void
    {
        foreach ([['product_id' => 1, 'quantity' => '1.5'], ['product_id' => 0, 'quantity' => 1], ['product_id' => 1, 'quantity' => 501]] as $row) {
            try {
                \barQuote($this->pdo, [$row]);
                self::fail('Malformed quantity accepted.');
            } catch (\InvalidArgumentException $expected) {
                self::assertSame(20, $this->stock());
            }
        }
        $this->expectException(\InvalidArgumentException::class);
        \barQuote($this->pdo, $this->cart(), 19);
    }

    public function testManualPaymentAndRefundEvidenceCannotBeReusedForDifferentReceipts(): void
    {
        $first = $this->sale($this->cart(), 'card')['sale_id'];
        $second = $this->sale($this->cart(), 'card')['sale_id'];
        \barConfirmPayment($this->pdo, $first, 'LOCALHOST unique terminal proof', $this->token());
        try {
            \barConfirmPayment($this->pdo, $second, 'LOCALHOST unique terminal proof', $this->token());
            self::fail('One proof confirmed two payments.');
        } catch (\DomainException $expected) {
            self::assertSame('pending', \barSaleReceipt($this->pdo, $second)['payment']['status']);
        }
        \barConfirmPayment($this->pdo, $second, 'LOCALHOST second terminal proof', $this->token());
        \barVoidSale($this->pdo, $first, $this->box, 'LOCALHOST unique refund proof', 'LOCALHOST actual external refund', $this->token());
        try {
            \barVoidSale($this->pdo, $second, $this->box, 'LOCALHOST unique refund proof', 'LOCALHOST reused refund evidence', $this->token());
            self::fail('One proof confirmed two refunds.');
        } catch (\DomainException $expected) {
            self::assertSame(19, $this->stock());
            self::assertNull(\barSaleReceipt($this->pdo, $second)['void']);
        }
    }

    public function testRefundAmountChangeAfterCupReturnRequiresNewConfirmation(): void
    {
        $this->pdo->exec('UPDATE kis_bar_settings SET cup_deposit_minor=2000');
        $sale = $this->sale($this->cart(), 'cash', 0, 2)['sale_id'];
        \barReturnCups($this->pdo, $sale, $this->box, 1, 'LOCALHOST intervening cup return', $this->token());
        try {
            \barVoidSale($this->pdo, $sale, $this->box, '', 'LOCALHOST stale refund quote', $this->token(), 7500);
            self::fail('Stale refund quote was accepted.');
        } catch (\BarConflictException $expected) {
            self::assertSame(19, $this->stock());
            self::assertNull(\barSaleReceipt($this->pdo, $sale)['void']);
            self::assertSame(15500, \barCashBalance($this->pdo, $this->box));
        }
    }

    public function testInsufficientRefundCashRollsBackStockAndVoidThenSameTokenCanRetry(): void
    {
        $sale = $this->sale($this->cart())['sale_id'];
        \barAdjustCash($this->pdo, $this->box, -13000, 'LOCALHOST cash removed', $this->token());
        $token = $this->token();
        try {
            \barVoidSale($this->pdo, $sale, $this->box, '', 'LOCALHOST customer return', $token);
            self::fail('Refund paid unavailable cash.');
        } catch (\DomainException $expected) {
            self::assertSame(19, $this->stock());
            self::assertNull(\barSaleReceipt($this->pdo, $sale)['void']);
            self::assertSame(500, \barCashBalance($this->pdo, $this->box));
        }
        \barAdjustCash($this->pdo, $this->box, 3000, 'LOCALHOST refund cash supplied', $this->token());
        \barVoidSale($this->pdo, $sale, $this->box, '', 'LOCALHOST customer return', $token);
        self::assertSame(20, $this->stock());
        self::assertSame(0, \barCashBalance($this->pdo, $this->box));
    }

    public function testMissingClosingAuditCannotCloseDrawerAndRetryKeepsOneClosure(): void
    {
        $version = $this->version();
        $token = $this->token();
        $this->pdo->exec("CREATE TRIGGER fail_close_audit BEFORE INSERT ON kis_bar_events BEGIN SELECT RAISE(FAIL,'LOCALHOST closing audit failure'); END");
        try {
            \barCloseCashbox($this->pdo, $this->box, $version, 10000, 'LOCALHOST verified closing', $token);
            self::fail('Closing committed without audit.');
        } catch (\PDOException $expected) {
            self::assertNotNull(\barOwnOpenCashbox($this->pdo));
            self::assertSame($version, $this->version());
        }
        $this->pdo->exec('DROP TRIGGER fail_close_audit');
        \barCloseCashbox($this->pdo, $this->box, $version, 10000, 'LOCALHOST verified closing', $token);
        self::assertTrue(\barCloseCashbox($this->pdo, $this->box, $version, 10000, 'LOCALHOST verified closing', $token)['repeated']);
        self::assertSame(1, (int)$this->pdo->query("SELECT COUNT(*) FROM kis_bar_events WHERE action='closed'")->fetchColumn());
    }

    public function testZeroTotalDoesNotInventAnExternalPaymentOrBlockClosure(): void
    {
        $quote = \barQuote($this->pdo, $this->cart(3), 20);
        self::assertSame(0, $quote['total_minor']);
        try {
            \barSubmitSale($this->pdo, $this->box, $this->cart(3), 20, 0, $quote['fingerprint'], 'card', $this->token());
            self::fail('Zero charge created a pending external payment.');
        } catch (\InvalidArgumentException $expected) {
            self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_sales')->fetchColumn());
        }
        $sale = \barSubmitSale($this->pdo, $this->box, $this->cart(3), 20, 0, $quote['fingerprint'], 'cash', $this->token());
        $payment = \barSaleReceipt($this->pdo, $sale['sale_id'])['payment'];
        self::assertSame('confirmed', $payment['status']);
        self::assertSame('zero_amount', $payment['verification_kind']);
        self::assertSame(10000, \barCashBalance($this->pdo, $this->box));
        \barCloseCashbox($this->pdo, $this->box, $this->version(), 10000, 'LOCALHOST zero charge closing', $this->token());
        self::assertNull(\barOwnOpenCashbox($this->pdo));
    }

    public function testPendingReservationCannotBeCountedAgainOrConvertedToUntrackedArchivedOrRecipeStock(): void
    {
        $this->pdo->exec("UPDATE kis_bar_products SET stock_mode='sledovat',stock_stall=1 WHERE id=3");
        $pending = $this->sale($this->cart(3), 'card')['sale_id'];
        $version = (int)$this->pdo->query('SELECT lock_version FROM kis_bar_products WHERE id=3')->fetchColumn();
        \barTestLogin(3, 'bar_admin');
        $input = ['name' => 'LOCALHOST Service', 'category_id' => 1, 'price' => '9.99', 'stock_mode' => 'sledovat'];
        foreach (['inventory', 'untracked', 'archive', 'recipe'] as $operation) {
            try {
                match ($operation) {
                    'inventory' => \barStockOperation($this->pdo, 3, $version, 'inventory_stall', 1, $this->token(), 'LOCALHOST count reserved stock'),
                    'untracked' => \barSaveProduct($this->pdo, 3, $version, array_replace($input, ['stock_mode' => 'nesledovat']), 'LOCALHOST pending mode change'),
                    'archive' => \barSaveProduct($this->pdo, 3, $version, $input + ['archived' => 1], 'LOCALHOST pending archive'),
                    'recipe' => \barSetRecipe($this->pdo, 3, $version, [['id' => 1, 'quantity' => 1]], 'LOCALHOST pending recipe conversion'),
                };
                self::fail('Reserved units were counted or converted.');
            } catch (\DomainException $expected) {
                self::assertSame(0, (int)$this->pdo->query('SELECT stock_stall FROM kis_bar_products WHERE id=3')->fetchColumn());
                self::assertSame($version, (int)$this->pdo->query('SELECT lock_version FROM kis_bar_products WHERE id=3')->fetchColumn());
            }
        }
        \barTestLogin(2, 'bar_operator');
        \barVoidSale($this->pdo, $pending, null, '', 'LOCALHOST cancelled pending reservation', $this->token());
        \barTestLogin(3, 'bar_admin');
        $version = (int)$this->pdo->query('SELECT lock_version FROM kis_bar_products WHERE id=3')->fetchColumn();
        \barStockOperation($this->pdo, 3, $version, 'inventory_stall', 3, $this->token(), 'LOCALHOST count after resolving payment');
        self::assertSame(3, (int)$this->pdo->query('SELECT stock_stall FROM kis_bar_products WHERE id=3')->fetchColumn());
    }
}
