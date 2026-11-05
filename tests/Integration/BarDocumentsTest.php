<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/BarFoundationFixture.php';
require_once dirname(__DIR__, 2) . '/includes/bar/invoices.php';
require_once dirname(__DIR__, 2) . '/includes/bar/accounting.php';
require_once dirname(__DIR__, 2) . '/includes/bar/menu_view.php';
require_once dirname(__DIR__, 2) . '/includes/bar/reversals.php';

final class BarDocumentsTest extends TestCase
{
    private PDO $pdo;
    private string $storage;
    private string|false $oldStorage;

    protected function setUp(): void
    {
        $this->pdo = \barTestDatabase();
        $this->pdo->exec("INSERT INTO kis_bar_categories(id,name) VALUES(1,'LOCALHOST Drinks')");
        $this->pdo->exec("INSERT INTO kis_bar_products(id,category_id,name,description,price_minor,purchase_minor,stock_mode,stock_stall,stock_office,package_name) VALUES
            (1,1,'LOCALHOST Tea','Public description',3500,1101,'sledovat',20,30,''),
            (2,1,'LOCALHOST Service','Unknown cost',999,NULL,'nesledovat',0,0,'')");
        $this->storage = sys_get_temp_dir() . '/kis-bar-documents-' . bin2hex(random_bytes(8));
        $this->oldStorage = getenv('APP_PRIVATE_STORAGE_ROOT');
        putenv('APP_PRIVATE_STORAGE_ROOT=' . $this->storage);
        \barTestLogin(3, 'bar_admin');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->storage . '/bar-invoices/*') ?: [] as $path) {
            unlink($path);
        }
        if (is_dir($this->storage . '/bar-invoices')) {
            rmdir($this->storage . '/bar-invoices');
            rmdir($this->storage);
        }
        putenv($this->oldStorage === false ? 'APP_PRIVATE_STORAGE_ROOT' : 'APP_PRIVATE_STORAGE_ROOT=' . $this->oldStorage);
        $_SESSION = [];
    }

    private function token(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function upload(): int
    {
        return \barUploadInvoice($this->pdo, dirname(__DIR__) . '/fixtures/bar-localhost-invoice.pdf', $this->token(), false)['invoice_id'];
    }

    private function draft(): array
    {
        return ['supplier' => 'LOCALHOST Supplier', 'supplier_id' => '12345678', 'number' => 'LOCAL-001', 'date' => '2020-01-01', 'currency' => 'CZK',
            'net_minor' => 10000, 'vat_minor' => 2100, 'gross_minor' => 12100, 'rounding_minor' => 0, 'payable_minor' => 12100, 'verified' => 1,
            'rows' => [['code' => 'TEA001', 'description' => 'LOCALHOST Tea', 'quantity' => 2, 'unit' => 'bal', 'net_minor' => 10000,
                'gross_minor' => 12100, 'kind' => 'stock', 'product_id' => 1, 'multiplier' => 3, 'confirmed' => 1, 'remember' => 1,
                'cost_choice' => 'apply', 'purchase_version' => 1]]];
    }

    private function receive(): array
    {
        $id = $this->upload();
        \barSaveInvoice($this->pdo, $id, 1, $this->draft(), 'LOCALHOST verified ordinary invoice', $this->token());
        return \barReceiveInvoice($this->pdo, $id, 2, '2020-01-01', true, 'LOCALHOST physically received', $this->token());
    }

    public function testUnknownPdfIsPrivateManualDraftAndContentDeduplicated(): void
    {
        $id = $this->upload();
        self::assertSame($id, $this->upload());
        $invoice = \barInvoice($this->pdo, $id);
        self::assertSame('draft', $invoice['status']);
        self::assertSame([], $invoice['draft']);
        self::assertStringContainsString('ručně', $invoice['parse_warning']);
        self::assertStringContainsString('LOCALHOST', $invoice['raw_text']);
        self::assertSame(hash_file('sha256', dirname(__DIR__) . '/fixtures/bar-localhost-invoice.pdf'), hash_file('sha256', \barInvoicePdfPath($invoice)));
        self::assertSame(30, (int)$this->pdo->query('SELECT stock_office FROM kis_bar_products WHERE id=1')->fetchColumn());
    }

    public function testReceiveReplayUsesPackageUnitsAndWeightedCostThenReversalRetainsCatalogPrice(): void
    {
        $id = $this->upload();
        \barSaveInvoice($this->pdo, $id, 1, $this->draft(), 'LOCALHOST checked invoice', $this->token());
        $token = $this->token();
        $args = [$this->pdo, $id, 2, '2020-01-01', true, 'LOCALHOST real delivery', $token];
        $result = \barReceiveInvoice(...$args);
        self::assertSame($result['receipt_id'], \barReceiveInvoice(...$args)['receipt_id']);
        self::assertTrue(\barReceiveInvoice(...$args)['repeated']);
        $product = $this->pdo->query('SELECT * FROM kis_bar_products WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(36, (int)$product['stock_office']);
        self::assertSame(2017, (int)$product['purchase_minor']);
        self::assertSame(2, (int)$product['purchase_version']);
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_supplier_mappings')->fetchColumn());
        \barReverseInvoice($this->pdo, $id, 3, 'LOCALHOST incorrect delivery', $this->token());
        self::assertSame(30, (int)$this->pdo->query('SELECT stock_office FROM kis_bar_products WHERE id=1')->fetchColumn());
        self::assertSame(2017, (int)$this->pdo->query('SELECT purchase_minor FROM kis_bar_products WHERE id=1')->fetchColumn());
        self::assertSame('draft', \barInvoice($this->pdo, $id)['status']);
        self::assertSame(0, \barInvoice($this->pdo, $id)['draft']['verified']);
        self::assertSame('reversed', $this->pdo->query('SELECT status FROM kis_bar_invoice_receipts')->fetchColumn());
    }

    public function testIncorrectTotalsBlockReceiptAndProduceNoPartialStockOrRequest(): void
    {
        $id = $this->upload();
        $draft = $this->draft();
        $draft['rows'][0]['gross_minor']--;
        \barSaveInvoice($this->pdo, $id, 1, $draft, 'LOCALHOST deliberate mismatch', $this->token());
        try {
            \barReceiveInvoice($this->pdo, $id, 2, '2020-01-01', true, 'LOCALHOST must fail', $this->token());
            self::fail('Mismatched invoice received.');
        } catch (\DomainException) {
            self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_invoice_receipts')->fetchColumn());
            self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_stock_operations')->fetchColumn());
        }
    }

    public function testCostVersionRejectsApprovalAfterAbaButStockVersionDoesNotInvalidateIt(): void
    {
        $id = $this->upload();
        \barSaveInvoice($this->pdo, $id, 1, $this->draft(), 'LOCALHOST approved cost', $this->token());
        $this->pdo->exec('UPDATE kis_bar_products SET stock_office=31,lock_version=lock_version+1 WHERE id=1');
        \barReceiveInvoice($this->pdo, $id, 2, '2020-01-01', true, 'LOCALHOST stock independent', $this->token());
        \barReverseInvoice($this->pdo, $id, 3, 'LOCALHOST correction', $this->token());
        $draft = $this->draft();
        $draft['rows'][0]['purchase_version'] = 2;
        \barSaveInvoice($this->pdo, $id, 4, $draft, 'LOCALHOST approve latest price', $this->token());
        $this->pdo->exec('UPDATE kis_bar_products SET purchase_version=purchase_version+2 WHERE id=1');
        $this->expectException(\BarConflictException::class);
        \barReceiveInvoice($this->pdo, $id, 5, '2020-01-01', true, 'LOCALHOST ABA rejected', $this->token());
    }

    public function testReversalIsAtomicWhenOneOfTheProductsHasAlreadyBeenConsumed(): void
    {
        $result = $this->receive();
        $this->pdo->exec('UPDATE kis_bar_products SET stock_office=5 WHERE id=1');
        try {
            \barReverseInvoice($this->pdo, $result['invoice_id'], 3, 'LOCALHOST insufficient stock', $this->token());
            self::fail('Insufficient stock accepted.');
        } catch (\DomainException) {
            self::assertSame('received', \barInvoice($this->pdo, $result['invoice_id'])['status']);
            self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM kis_bar_stock_operations')->fetchColumn());
        }
    }

    public function testPublicationDoesNotExposeDraftAndChangesQuoteSelectionButNotOwnConsumption(): void
    {
        $cart = [['product_id' => 1, 'quantity' => 1]];
        $quote = \barQuote($this->pdo, $cart);
        $offer = \barSaveOffer($this->pdo, 0, 0, ['title' => 'LOCALHOST <Menu>', 'subtitle' => 'Public', 'layout' => 'text', 'products' => [2]], 'LOCALHOST menu draft', $this->token());
        self::assertSame([], \barPublicMenu($this->pdo)['products']);
        self::assertSame($quote['fingerprint'], \barQuote($this->pdo, $cart)['fingerprint']);
        \barPublishOffer($this->pdo, $offer['offer_id'], 1, 'LOCALHOST publish menu', $this->token());
        $menu = \barPublicMenu($this->pdo);
        self::assertSame([2], array_map('intval', array_column($menu['products'], 'id')));
        self::assertArrayNotHasKey('purchase_minor', $menu['products'][0]);
        self::assertArrayNotHasKey('stock_office', $menu['products'][0]);
        self::assertStringContainsString('&lt;Menu&gt;', \barMenuHtml($menu));
        try {
            \barQuote($this->pdo, $cart);
            self::fail('Product outside publication accepted.');
        } catch (\DomainException) {
            \barTestLogin(1, 'system_admin');
            \barSetConsumptionPermission($this->pdo, 3, true, 'LOCALHOST explicit grant');
            \barTestLogin(3, 'bar_admin');
            self::assertSame(0, \barQuote($this->pdo, $cart, 0, 0, true)['total_minor']);
        }
    }

    public function testPublishedMenuUsesLivePriceAndOmitsArchivedProductsWithoutChangingPublication(): void
    {
        $offer = \barSaveOffer($this->pdo, 0, 0, ['title' => 'LOCALHOST Menu', 'layout' => 'photos', 'products' => [1]], 'LOCALHOST initial menu', $this->token());
        $published = \barPublishOffer($this->pdo, $offer['offer_id'], 1, 'LOCALHOST public offer', $this->token());
        $this->pdo->exec('UPDATE kis_bar_products SET price_minor=4000 WHERE id=1');
        self::assertSame(4000, (int)\barPublicMenu($this->pdo)['products'][0]['price_minor']);
        self::assertSame($published['publication_id'], \barActiveOffer($this->pdo)['publication_id']);
        $this->pdo->exec('UPDATE kis_bar_products SET archived=1 WHERE id=1');
        self::assertSame([], \barPublicMenu($this->pdo)['products']);
    }

    public function testExportFreezesSourcesOnceAndLaterReversalIsASeparateCompensation(): void
    {
        $receipt = $this->receive();
        $sources = \barAccountingSources($this->pdo);
        $selection = array_map(static fn (array $row): string => $row['sha256'], $sources);
        $token = $this->token();
        $result = \barCreateAccountingExport($this->pdo, $selection, 'LOCALHOST reviewed sources', $token);
        $frozen = \barAccountingExport($this->pdo, $result['export_id']);
        self::assertSame([], \barAccountingSources($this->pdo));
        self::assertSame($result['export_id'], \barCreateAccountingExport($this->pdo, $selection, 'LOCALHOST reviewed sources', $token)['export_id']);
        \barReverseInvoice($this->pdo, $receipt['invoice_id'], 3, 'LOCALHOST later correction', $this->token());
        $new = \barAccountingSources($this->pdo);
        self::assertCount(1, $new);
        self::assertSame('supplier_receipt_reversal', reset($new)['event']['kind']);
        self::assertSame($frozen['manifest_json'], \barAccountingExport($this->pdo, $result['export_id'])['manifest_json']);
        $this->expectException(\BarConflictException::class);
        \barCreateAccountingExport($this->pdo, $selection, 'LOCALHOST duplicate export', $this->token());
    }

    public function testCashDepositSaleCupReturnAndVoidNetToZeroWithoutCountingOpeningAsRevenue(): void
    {
        $box = \barOpenCashbox($this->pdo, 100000, 'LOCALHOST drawer opening', $this->token())['cashbox_id'];
        $this->pdo->exec('UPDATE kis_bar_settings SET cup_deposit_minor=2000 WHERE id=1');
        $cart = [['product_id' => 1, 'quantity' => 1]];
        $quote = \barQuote($this->pdo, $cart, 0, 2);
        $sale = \barSubmitSale($this->pdo, $box, $cart, 0, 2, $quote['fingerprint'], 'cash', $this->token());
        \barReturnCups($this->pdo, $sale['sale_id'], $box, 1, 'LOCALHOST cup returned', $this->token());
        \barVoidSale($this->pdo, $sale['sale_id'], $box, '', 'LOCALHOST paid sale reversed', $this->token());
        $events = array_column(array_values(\barAccountingSources($this->pdo)), 'event');
        self::assertCount(4, $events);
        self::assertSame(0, array_sum(array_column($events, 'payment_minor')));
        self::assertSame(0, array_sum(array_column($events, 'goods_including_tax_minor')));
        self::assertSame(0, array_sum(array_column($events, 'deposit_liability_minor')));
        self::assertSame(100000, array_sum(array_column($events, 'drawer_delta_minor')));
        $today = date('Y-m-d');
        $report = \barAccountingReport($this->pdo, $today, $today);
        self::assertSame(0, $report['goods']);
        self::assertSame(0, $report['deposits']);
        self::assertSame(0, $report['payments']);
        self::assertSame(0, $report['cash']);
    }

    public function testPendingQrIsNotExportedAndUnknownCostRemainsNull(): void
    {
        $box = \barOpenCashbox($this->pdo, 0, 'LOCALHOST drawer', $this->token())['cashbox_id'];
        $cart = [['product_id' => 2, 'quantity' => 1]];
        $quote = \barQuote($this->pdo, $cart);
        $sale = \barSubmitSale($this->pdo, $box, $cart, 0, 0, $quote['fingerprint'], 'qr', $this->token());
        self::assertCount(1, \barAccountingSources($this->pdo));
        \barConfirmPayment($this->pdo, $sale['sale_id'], 'LOCALHOST-bank-ref', $this->token());
        $event = \barAccountingSources($this->pdo)['bar:sale:' . $sale['sale_id']]['event'];
        self::assertNull($event['estimated_cost_minor']);
        self::assertSame('LOCALHOST-bank-ref', $event['payment_reference']);
    }

    public function testFinanceReadDoesNotGrantStockInvoiceOrPublicationWritesAndOperatorCannotReadAccounting(): void
    {
        $id = $this->upload();
        $this->pdo->exec("INSERT INTO staff_user_positions(trainer_id,position_code,is_default) VALUES(4,'finance_manager',0)");
        \barTestLogin(4, 'finance_manager');
        self::assertSame($id, (int)\barInvoice($this->pdo, $id)['id']);
        self::assertSame([], \barAccountingSources($this->pdo));
        foreach (['stock_manage', 'invoice_manage', 'offers_manage', 'own_consumption', 'sell'] as $capability) {
            try {
                \barActor($this->pdo, $capability);
                self::fail('Finance acquired ' . $capability);
            } catch (\DomainException) {
                self::assertTrue(true);
            }
        }
        \barTestLogin(2, 'bar_operator');
        $this->expectException(\DomainException::class);
        \barAccountingSources($this->pdo);
    }

    public function testCsvNeutralizesSpreadsheetFormulaReferences(): void
    {
        $csv = \barAccountingCsv([['source_key' => 'bar:sale:1', 'kind' => 'sale_settlement', 'payment_minor' => -125,
            'payment_reference' => ' =HYPERLINK("test")']]);
        self::assertStringContainsString("' =HYPERLINK", $csv);
        self::assertStringContainsString(';-125;', $csv);
    }

    public function testKnownPdfProfileExtractsExactAmountsAndNeverConfirmsMapping(): void
    {
        $text = "LE PATIO GROUP s.r.o. CZ07821921\nVariabilní symbol\n123456 123456\n"
            . "Datum vystavení dokladu: 01.01.2020\nTEA001 Test Tea 2 bal 50,000 100,00 21% 121,00 0\n"
            . "celkem 100,00 21,00 121,00\n0,00 zaokrouhlení\nčástka k úhradě 121,00";
        $draft = \barRecognizeInvoice($text);
        self::assertSame(12100, $draft['payable_minor']);
        self::assertSame(10000, $draft['rows'][0]['net_minor']);
        self::assertSame(2, $draft['rows'][0]['quantity']);
        self::assertSame(0, $draft['rows'][0]['confirmed']);
        self::assertSame(0, $draft['rows'][0]['product_id']);
        self::assertTrue(\barInvoiceTotalsMatch($draft));
        foreach (['EUR', 'dobropis', 'zálohová faktura'] as $unsupported) {
            try {
                \barRecognizeInvoice($text . "\n" . $unsupported);
                self::fail('Unsupported document recognized: ' . $unsupported);
            } catch (\DomainException) {
                self::assertTrue(true);
            }
        }
    }

    public function testCataloguePriceAbaIncrementsSeparateVersionAndRejectsOldInvoiceApproval(): void
    {
        $id = $this->upload();
        \barSaveInvoice($this->pdo, $id, 1, $this->draft(), 'LOCALHOST initial approval', $this->token());
        $input = ['name' => 'LOCALHOST Tea', 'category_id' => 1, 'price' => '35.00', 'purchase_price' => '11.02',
            'stock_mode' => 'sledovat', 'on_terminal' => 1];
        \barSaveProduct($this->pdo, 1, 1, $input, 'LOCALHOST first cost change');
        $input['purchase_price'] = '11.01';
        \barSaveProduct($this->pdo, 1, 2, $input, 'LOCALHOST restore cost');
        self::assertSame(3, (int)$this->pdo->query('SELECT purchase_version FROM kis_bar_products WHERE id=1')->fetchColumn());
        $this->expectException(\BarConflictException::class);
        \barReceiveInvoice($this->pdo, $id, 2, '2020-01-01', true, 'LOCALHOST stale approval', $this->token());
    }

    public function testDifferentPdfWithSameSupplierAndInvoiceNumberIsRejected(): void
    {
        $id = $this->upload();
        \barSaveInvoice($this->pdo, $id, 1, $this->draft(), 'LOCALHOST first document', $this->token());
        $this->pdo->prepare('INSERT INTO kis_bar_invoices(pdf_sha256,storage_key,pdf_bytes,raw_text,parse_warning,draft_json,uploaded_by) VALUES(?,?,?,?,?,?,?)')
            ->execute([str_repeat('b', 64), 'test-only', 20, '', '', '{}', 3]);
        $second = (int)$this->pdo->lastInsertId();
        $this->expectException(\DomainException::class);
        \barSaveInvoice($this->pdo, $second, 1, $this->draft(), 'LOCALHOST duplicate invoice identity', $this->token());
    }

    public function testLargeInvoiceFormKeepsAllTwoHundredLinesInOneBoundedPayload(): void
    {
        $raw = $this->draft() + ['net' => '2,00', 'vat' => '0', 'gross' => '2,00', 'rounding' => '0', 'payable' => '2,00'];
        $raw['rows'] = array_fill(0, 200, ['description' => 'LOCALHOST expense', 'code' => '', 'quantity' => '1',
            'unit' => 'ks', 'kind' => 'expense', 'net' => '0,01', 'gross' => '0,01']);
        $raw['rows'][] = ['description' => '', 'net' => '0', 'gross' => '0'];
        $draft = \barNormalizeInvoice(\barInvoiceFormData(['document_json' => json_encode($raw, JSON_THROW_ON_ERROR)]));
        self::assertCount(200, $draft['rows']);
        self::assertSame(200, $draft['gross_minor']);
        self::assertTrue(\barInvoiceTotalsMatch($draft));
    }

    public function testTruncatedInvoicePayloadCannotBeAcceptedAsCompleteForm(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        \barInvoiceFormData(['document_json' => '{"rows":']);
    }
}
