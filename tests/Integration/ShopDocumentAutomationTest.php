<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/ShopCheckoutTest.php';
require_once dirname(__DIR__, 2).'/includes/staff_workspaces.php';
require_once dirname(__DIR__, 2).'/includes/shop_document_automation.php';
require_once dirname(__DIR__, 2).'/includes/club_event_notification.php';

final class ShopDocumentAutomationTest extends TestCase
{
    private PDO $pdo;
    private array $session;
    private string|false $oldRoot;
    private string $private;
    private const BILLING = ['billing_street' => 'LOCALHOST 12','billing_city' => 'Praha','billing_postcode' => '19000'];
    private const BANK = ['iban' => 'CZ6508000000192000145399','bic' => 'GIBACZPX','account_label' => 'LOCALHOST NEPLATIT'
    ,'due_days' => 7];

    protected function setUp(): void
    {
        $this->session = $_SESSION ?? [];
        $_SESSION = ['trener_id' => 7,'staff_positions' => ['finance_manager'],'staff_active_position' => 'finance_manager'
    ];
        $this->oldRoot = getenv('APP_PRIVATE_STORAGE_ROOT');
        $this->private = sys_get_temp_dir().'/shop-doc-test-'.bin2hex(random_bytes(8));
        mkdir($this->private);
        putenv('APP_PRIVATE_STORAGE_ROOT='.$this->private);
        $base = new ShopCheckoutTest('testCheckoutUsesCurrentServerPriceThenKeepsImmutableSnapshotAndIsIdempotent');
        $p = $this->pdo = (new \ReflectionMethod($base, 'database'))->invoke($base);
        // SQLite's UTC clock otherwise predates today's Prague mandate after midnight.
        // Match the business timezone explicitly in this isolated synthetic database.
        $p->sqliteCreateFunction('current_timestamp', static fn (): string =>
            (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Prague')))->format('Y-m-d H:i:s'), 0);
        $p->exec(("ALTER TABLE treneri ADD COLUMN aktivni INT DEFAULT 1;
            CREATE TABLE staff_user_positions(trainer_id INT,position_code " .
    "TEXT);
            CREATE TABLE staff_superadmins(trainer_id INT);
            INSERT INTO staff_user_positions " .
    "VALUES(7,'finance_manager');
            CREATE TABLE club_event_order_items(id INT,order_id INT,beneficiary_sportovec_id " .
    "INT,event_name_snapshot TEXT,quantity INT,line_amount_minor INT);
            CREATE TABLE public_velodrome_order_items(id " .
    ("INT,order_id INT,beneficiary_sportovec_id INT,lesson_name_snapshot TEXT,lesson_date_snapshot " .
    "TEXT,starts_at_snapshot ") .
    "TEXT,quantity INT,line_amount_minor INT);"));
        foreach (['20261021170000_shop_system_email_templates','20261022221000_accounting_documents','20261107180000_shop_document_automation'
    ] as $name) {
            $m = require dirname(__DIR__, 2).'/migrations/'.$name.'.php';
            $m['up']($p);
            $m['up']($p);
            self::assertTrue($m['verify']($p));
        }
        $issuer = \accountingDocumentConfigureIssuer($p, ['name' => 'LOCALHOST Klub','address' => 'LOCALHOST 1, Praha','registration_id'
     => 'LOCALHOST','confirmed' => '1','approval_evidence' => 'Syntetická metodika.']);
        \shopDocumentConfigure($p, $issuer, 'LOCALHOST schválení automatického vystavení.');
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->session;
        $this->oldRoot === false ? putenv('APP_PRIVATE_STORAGE_ROOT') : putenv(
            'APP_PRIVATE_STORAGE_ROOT='.$this->oldRoot
        );
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $this->private,
            \FilesystemIterator::SKIP_DOTS
        ), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->private);
    }

    private function place(array $billing = self::BILLING): array
    {
        \shopCartSetQuantity($this->pdo, 10, 601, 1);
        return \shopCheckoutPlace($this->pdo, 10, bin2hex(random_bytes(16)), self::BANK, \shopCartDetail($this->pdo, 10)[
    'fingerprint'], customConsentInput:$billing);
    }

    private function pay(array $order): void
    {
        \shopOrderAdminConfirmBankPayment($this->pdo, (int)$order['payment_id'], 7, 'LOCALHOST syntetická platba.', true)
        ;
    }

    public function testProtectedActivationUsesExistingIssuerMandateWithoutCreatingSessionOrDuplicatePolicy(): void
    {
        \shopDocumentConfigure($this->pdo, 1, 'LOCALHOST vypnutí před provozní aktivací.', false);
        $_SESSION = [];
        $id = \shopDocumentEnableApprovedIssuer($this->pdo, 1, 'LOCALHOST', 'LOCALHOST chráněné zadání.');
        self::assertSame($id, \shopDocumentEnableApprovedIssuer($this->pdo, 1, 'LOCALHOST', 'LOCALHOST opakování.'));
        self::assertSame([], $_SESSION);
        self::assertNull(\AccountingDocumentWorker::actor());
        self::assertTrue(\shopDocumentStatus($this->pdo)['enabled']);
        self::assertSame(1, \shopDocumentStatus($this->pdo)['available_issuer']['id']);
        try {
            \shopDocumentEnableApprovedIssuer($this->pdo, 1, 'OTHER', 'LOCALHOST chybný vystavovatel.');
            self::fail('Issuer mismatch must be refused.');
        } catch (\DomainException $e) {
            self::assertStringContainsString('IČ', $e->getMessage());
        }
        $this->pdo->exec('DELETE FROM staff_user_positions WHERE trainer_id=7');
        $this->expectException(\DomainException::class);
        \shopDocumentEnableApprovedIssuer($this->pdo, 1, 'LOCALHOST', 'LOCALHOST odňaté pověření.');
    }

    public function testFullOrderPaymentDocumentCancelCorrectionRefundAndReplay(): void
    {
        $p = $this->pdo;
        $order = $this->place();
        $id = (int)$order['id'];
        self::assertSame(['shop_order_placed'], $p->query('SELECT notification_type FROM club_event_notifications')->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame(0, (int)$p->query('SELECT COUNT(*) FROM accounting_documents')->fetchColumn());
        $this->pay($order);
        $this->pay($order);
        $messages = [];
        $sender = static function ($to, $subject, $body, $files = []) use (&$messages): bool {
            $messages[] = compact('to', 'subject', 'body', 'files');
            return true;
        };
        self::assertSame(['documents' => 1,'sent' => 3,'failed' => 0,'attention' => 0], \shopDocumentDrain($p, $sender));
        $invoice = \accountingDocumentGet($p, 1);
        self::assertSame('issued', $invoice['status']);
        self::assertSame(12500, (int)$invoice['total_minor']);
        self::assertCount(1, $messages[2]['files']);
        self::assertStringStartsWith('%PDF-', $messages[2]['files'][0]['bytes']);
        self::assertSame($invoice['pdf_sha256'], hash('sha256', $messages[2]['files'][0]['bytes']));
        self::assertSame(['documents' => 0,'sent' => 0,'failed' => 0,'attention' => 0], \shopDocumentDrain($p, $sender));
        \shopOrderAdminCancel($p, $id, 7, 'LOCALHOST storno.', true);
        self::assertSame('refund_required', $p->query('SELECT payment_status FROM shop_orders')->fetchColumn());
        self::assertSame(['documents' => 1,'sent' => 2,'failed' => 0,'attention' => 0], \shopDocumentDrain($p, $sender));
        self::assertSame(-12500, (int)\accountingDocumentGet($p, 2)['total_minor']);
        self::assertSame($invoice['pdf_sha256'], \accountingDocumentGet($p, 1)['pdf_sha256']);
        self::assertStringContainsString('musí klub ještě provést', $messages[3]['body']);
        \shopOrderAdminConfirmRefund($p, $id, 7, 'LOCALHOST-REFUND', 'LOCALHOST skutečná syntetická vratka.', true);
        \shopOrderAdminConfirmRefund($p, $id, 7, 'LOCALHOST-REFUND', 'LOCALHOST opakování.', true);
        self::assertSame(['documents' => 0,'sent' => 1,'failed' => 0,'attention' => 0], \shopDocumentDrain($p, $sender));
        self::assertSame('refunded', $p->query('SELECT payment_status FROM shop_orders')->fetchColumn());
        self::assertSame(6, (int)$p->query('SELECT COUNT(*) FROM club_event_notifications')->fetchColumn());
        self::assertSame(2, (int)$p->query('SELECT COUNT(*) FROM accounting_documents')->fetchColumn());
        self::assertNull(\AccountingDocumentWorker::actor());
    }

    public function testCancellationBeforeWorkerStillIssuesImmutableInvoiceAndCorrection(): void
    {
        $order = $this->place();
        $this->pay($order);
        \shopOrderAdminCancel($this->pdo, (int)$order['id'], 7, 'LOCALHOST okamžité storno.', true);
        self::assertSame(0, \shopDocumentDrain($this->pdo, static fn () => true)['failed']);
        self::assertSame([12500,-12500], array_map('intval', $this->pdo->query(
            'SELECT total_minor FROM accounting_documents ORDER BY id'
        )->fetchAll(PDO::FETCH_COLUMN)));
        $this->expectException(\DomainException::class);
        \accountingDocumentSource($this->pdo, 'order', (int)$order['id']);
    }

    public function testMissingBillingRollsBackOrderAndUnpaidCancellationHasNoInvoice(): void
    {
        try {
            $this->place([]);
            self::fail('Billing is required.');
        } catch (\InvalidArgumentException) {
        }
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn());
        $order = $this->place();
        \shopOrderAdminCancel($this->pdo, (int)$order['id'], 7, 'LOCALHOST bez platby.', true);
        self::assertSame(0, \shopDocumentDrain($this->pdo, static fn () => true)['documents']);
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM accounting_documents')->fetchColumn());
    }

    public function testPdfFailureDoesNotUndoPaymentAndRetryKeepsNumber(): void
    {
        $order = $this->place();
        $this->pay($order);
        putenv('APP_PRIVATE_STORAGE_ROOT='.dirname(__DIR__, 2));
        self::assertSame(1, \shopDocumentDrain($this->pdo, static fn () => true)['failed']);
        self::assertSame('paid', $this->pdo->query('SELECT payment_status FROM shop_orders')->fetchColumn());
        $before = \accountingDocumentGet($this->pdo, 1);
        self::assertSame('failed', $before['status']);
        putenv('APP_PRIVATE_STORAGE_ROOT='.$this->private);
        $this->pdo->exec('UPDATE shop_order_documents SET retry_at=CURRENT_TIMESTAMP');
        self::assertSame(0, \shopDocumentDrain($this->pdo, static fn () => true)['failed']);
        self::assertSame($before['number'], \accountingDocumentGet($this->pdo, 1)['number']);
    }

    public function testGuestHasOnePlacementEmailAndForeignDocumentIsRefused(): void
    {
        $token = bin2hex(random_bytes(32));
        $order = \shopGuestCheckoutPlace($this->pdo, 601, 1, self::BILLING + ['first_name' => 'LOCALHOST','last_name' =>
    'Host','email' => 'guest@example.test','confirmed' => '1'], bin2hex(random_bytes(16)), $token, self::BANK);
        self::assertSame(['shop_guest_order_placed'], $this->pdo->query(
            'SELECT notification_type FROM club_event_notifications'
        )->fetchAll(PDO::FETCH_COLUMN));
        $this->pay($order);
        \shopDocumentDrain($this->pdo, static fn () => true);
        self::assertSame($order['id'], \shopGuestOrderByCode($this->pdo, $order['public_code'], $token)['id']);
        self::assertFileExists(\shopDocumentFile($this->pdo, (int)$order['id'], 1)['path']);
        $this->expectException(\DomainException::class);
        \shopDocumentFile($this->pdo, 999, 1);
    }

    public function testTransportRejectionRetriesButAmbiguousFailureIsHeld(): void
    {
        $order = $this->place();
        $this->pay($order);
        \shopDocumentProcessOrder($this->pdo, (int)$order['id']);
        self::assertFalse(\clubEventNotificationProcessOne($this->pdo, static fn () => false, false, true));
        self::assertSame('pending', $this->pdo->query('SELECT status FROM club_event_notifications WHERE id=1')->fetchColumn());
        self::assertFalse(\clubEventNotificationProcessOne($this->pdo, static function (): bool {
            throw new \RuntimeException('uncertain');
        }, false, true));
        self::assertSame('failed', $this->pdo->query('SELECT status FROM club_event_notifications WHERE id=2')->fetchColumn());
        self::assertStringStartsWith('delivery_unknown:', $this->pdo->query(
            'SELECT last_error FROM club_event_notifications WHERE id=2'
        )->fetchColumn());
    }

    public function testMandateRevocationHoldsDocumentWithoutChangingPayment(): void
    {
        $order = $this->place();
        $this->pay($order);
        $this->pdo->exec('DELETE FROM staff_user_positions');
        self::assertSame(1, \shopDocumentDrain($this->pdo, static fn () => true)['failed']);
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM accounting_documents')->fetchColumn());
        self::assertSame('paid', $this->pdo->query('SELECT payment_status FROM shop_orders')->fetchColumn());
        self::assertNull(\AccountingDocumentWorker::actor());
    }

    public function testMimeHasPdfAndRejectsInjectedFilename(): void
    {
        $mime = \clubEventNotificationMime('Žluťoučký', [['name' => 'doklad-1.pdf','bytes' => '%PDF-test']]);
        self::assertStringContainsString('multipart/mixed', $mime['headers']);
        self::assertStringContainsString(base64_encode('%PDF-test'), $mime['body']);
        $this->expectException(\InvalidArgumentException::class);
        \clubEventNotificationMime('test', [['name' => "doklad-1.pdf\r\nBcc: other@example.test",'bytes' => '%PDF-test']])
        ;
    }

    public function testEnablementNeverBackfillsAnOlderOrder(): void
    {
        \shopDocumentConfigure($this->pdo, 1, 'LOCALHOST pozastavení nových objednávek.', false);
        $older = $this->place([]);
        \shopDocumentConfigure($this->pdo, 1, 'LOCALHOST opětovné povolení.');
        $this->place();
        $this->pay($older);
        $messages = [];
        \shopDocumentDrain($this->pdo, static function ($to, $subject) use (&$messages): bool {
            $messages[] = $subject;
            return true;
        });
        self::assertCount(1, $messages);
        self::assertStringNotContainsString($older['public_code'], $messages[0]);
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM accounting_documents')->fetchColumn());
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM shop_order_documents')->fetchColumn());
    }

    public function testIndividualRefundNotificationsAreDeduplicatedPerPayout(): void
    {
        $order = $this->place();
        $this->pay($order);
        $original = ['case_type' => 'shop_order', 'case_id' => $order['id']];
        $payment = ['id' => 555, 'amount_minor' => -5000, 'currency' => 'CZK'];
        $this->pdo->beginTransaction();
        \shopDocumentRefundPaid($this->pdo, $original, $payment, []);
        \shopDocumentRefundPaid($this->pdo, $original, $payment, []);
        \shopDocumentRefundPaid($this->pdo, $original, array_replace($payment, ['id' => 556]), []);
        $this->pdo->commit();
        $messages = $this->pdo->query("SELECT body_plain FROM club_event_notifications "
            . "WHERE notification_type LIKE 'shop_refund_paid:%'")->fetchAll(PDO::FETCH_COLUMN);
        self::assertCount(2, $messages);
        self::assertStringContainsString('50,00 CZK', $messages[0]);
        self::assertStringContainsString('Nejde o potvrzení celé vratky', $messages[0]);
    }

    public function testCorruptedPdfIsHeldBeforeCallingTransport(): void
    {
        $order = $this->place();
        $this->pay($order);
        \shopDocumentProcessOrder($this->pdo, (int)$order['id']);
        for ($i = 0; $i < 2; $i++) {
            self::assertTrue(\clubEventNotificationProcessOne($this->pdo, static fn () => true, false, true));
        }
        $file = \shopDocumentFile($this->pdo, (int)$order['id'], 1);
        file_put_contents($file['path'], '%PDF-tampered');
        $calls = 0;
        self::assertFalse(\clubEventNotificationProcessOne($this->pdo, static function () use (&$calls): bool {
            $calls++;
            return true;
        }, false, true));
        self::assertSame(0, $calls);
        self::assertSame('failed', $this->pdo->query('SELECT status FROM club_event_notifications WHERE id=3')->fetchColumn());
    }
}
