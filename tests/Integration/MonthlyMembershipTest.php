<?php

declare(strict_types=1);

namespace Tests\Integration;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\MonthlyMembershipFixture as Fixture;

require_once dirname(__DIR__).'/Support/MonthlyMembershipFixture.php';

final class MonthlyMembershipTest extends TestCase
{
    private PDO $pdo;
    private mixed $session;
    private string|false $storage;
    private string $private;
    protected function setUp(): void
    {
        $this->session = $_SESSION ?? [];
        $this->storage = getenv('APP_PRIVATE_STORAGE_ROOT');
        $this->private = sys_get_temp_dir().'/monthly-membership-'.bin2hex(random_bytes(10));
        mkdir($this->private);
        putenv('APP_PRIVATE_STORAGE_ROOT='.$this->private);
        $this->pdo = Fixture::database();
    }
    protected function tearDown(): void
    {
        $_SESSION = $this->session;
        $this->storage === false ? putenv('APP_PRIVATE_STORAGE_ROOT') : putenv('APP_PRIVATE_STORAGE_ROOT='.$this
    ->storage);
        // Only this test's randomly named private directory is removed.
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->private, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->private);
    }
    public function testCheckoutFirstMonthAndReplayAreAtomicAndKeepOneStandingSymbol(): void
    {
        $t = Fixture::tariff($this->pdo);
        $id = Fixture::subscribe($this->pdo, $t);
        self::assertSame($id, Fixture::subscribe($this->pdo, $t));
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM club_member_charges')->fetchColumn());
        self::assertSame(1, (int)$this->pdo->query("SELECT COUNT(*) FROM club_roster_members WHERE source='shop'")
    ->fetchColumn());
        self::assertSame(1, \monthlyMembershipTransaction($this->pdo, fn () => \monthlyMembershipGenerateLocked($this
    ->pdo, $id, '2026-10-01')));
        self::assertSame(0, \monthlyMembershipTransaction($this->pdo, fn () => \monthlyMembershipGenerateLocked($this
    ->pdo, $id, '2026-10-01')));
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(DISTINCT variable_symbol) FROM payments')->fetchColumn());
        self::assertSame(['2026-09','2026-10'], $this->pdo->query('SELECT period_key FROM monthly_membership_periods ORDER BY id')
    ->fetchAll(PDO::FETCH_COLUMN));
    }
    public function testChangedVariantCannotCreateMonthlyDebtThroughStaleCheckout(): void
    {
        $tariff = Fixture::tariff($this->pdo);
        $this->pdo->exec("UPDATE shop_variants SET price_mode='free' WHERE id=1");
        try {
            Fixture::subscribe($this->pdo, $tariff);
            self::fail('Changed price mode must invalidate the submitted tariff.');
        } catch (\DomainException) {
            self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM monthly_memberships')->fetchColumn());
            self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM club_member_charges')->fetchColumn());
        }
    }

    public function testPartialPaymentOverpaymentAndNextMonthSettleWithoutDuplicateBankMoney(): void
    {
        $id = Fixture::subscribe($this->pdo, Fixture::tariff($this->pdo));
        $a = Fixture::movement($this->pdo, 'LOCALHOST-PART', '500');
        self::assertTrue(\monthlyMembershipImportMovement($this->pdo, $a, 'CZ6508000000192000145399'));
        self::assertSame(70000, \monthlyMembershipRemaining($this->pdo, 1));
        self::assertFalse(\monthlyMembershipImportMovement($this->pdo, $a, 'CZ6508000000192000145399'));
        $b = Fixture::movement($this->pdo, 'LOCALHOST-OVER', '1900');
        self::assertTrue(\monthlyMembershipImportMovement($this->pdo, $b, 'CZ6508000000192000145399'));
        self::assertSame('paid', $this->pdo->query('SELECT status FROM club_member_charges WHERE id=1')->fetchColumn());
        self::assertSame(120000, \monthlyMembershipCredit($this->pdo, 20));
        \monthlyMembershipTransaction($this->pdo, fn () => \monthlyMembershipGenerateLocked($this->pdo, $id, '2026-10-01'));
        self::assertSame(0, \monthlyMembershipCredit($this->pdo, 20));
        self::assertSame(2, (int)$this->pdo->query("SELECT COUNT(*) FROM club_member_charges WHERE status='paid'")
    ->fetchColumn());
        self::assertSame(240000, (int)$this->pdo->query('SELECT SUM(amount_minor) FROM monthly_membership_allocations')
    ->fetchColumn());
        $this->expectException(\ClubFinanceException::class);
        \clubFinanceAssertBankReferenceUnused($this->pdo, 'LOCALHOST-PART', 50000, '2026-09-16');
    }
    public function testOnePayerCanPayTwoChildrenAndOldestChargesFirst(): void
    {
        $t = Fixture::tariff($this->pdo);
        Fixture::subscribe($this->pdo, $t, 1);
        Fixture::subscribe($this->pdo, $t, 2);
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(DISTINCT variable_symbol) FROM payments')->fetchColumn());
        $m = Fixture::movement($this->pdo, 'LOCALHOST-FAMILY', '1800');
        self::assertTrue(\monthlyMembershipImportMovement($this->pdo, $m, 'CZ6508000000192000145399'));
        self::assertSame(0, \monthlyMembershipRemaining($this->pdo, 1));
        self::assertSame(60000, \monthlyMembershipRemaining($this->pdo, 2));
        self::assertSame(60000, \clubFinanceBalanceForAthlete($this->pdo, 2, new DateTimeImmutable('2026-10-01'))['open_minor']);
    }
    public function testWrongVsAccountCurrencyAndPreContractMovementsCannotPay(): void
    {
        Fixture::subscribe($this->pdo, Fixture::tariff($this->pdo));
        $wrong = Fixture::movement($this->pdo, 'LOCALHOST-WRONG', '1200', '2026-09-16', '0000000001');
        self::assertFalse(\monthlyMembershipImportMovement($this->pdo, $wrong, 'CZ6508000000192000145399'));
        $old = Fixture::movement($this->pdo, 'LOCALHOST-OLD', '1200', '2026-08-31');
        self::assertFalse(\monthlyMembershipImportMovement($this->pdo, $old, 'CZ6508000000192000145399'));
        $valid = Fixture::movement($this->pdo, 'LOCALHOST-VALID', '1200');
        self::assertFalse(\monthlyMembershipImportMovement($this->pdo, $valid, 'CZ0000000000000000000000'));
        $this->pdo->exec("UPDATE fio_account_movements SET currency='EUR' WHERE id=".$valid);
        self::assertFalse(\monthlyMembershipImportMovement($this->pdo, $valid, 'CZ6508000000192000145399'));
        self::assertSame(120000, \monthlyMembershipRemaining($this->pdo, 1));
    }
    public function testLegacyOutstandingFeeMakesStandingPaymentReviewOnly(): void
    {
        Fixture::subscribe($this->pdo, Fixture::tariff($this->pdo));
        $this->pdo->exec(('INSERT INTO ' .
    'club_member_charges(sportovec_id,payer_account_id,public_code,charge_type,title_snapshot,amount_minor,currency,status,source_'
    .
    'system,source_external_id) ' .
    'VALUES(1,20,\'OLD\',\'membership\',\'Legacy\',120000,\'CZK\',\'pending\',\'manual\',\'old\')'));
        $m = Fixture::movement($this->pdo, 'LOCALHOST-MIXED', '1200');
        self::assertFalse(\monthlyMembershipImportMovement($this->pdo, $m, 'CZ6508000000192000145399'));
        self::assertSame('review_payer_split', $this->pdo->query('SELECT match_status FROM fio_account_movements')
    ->fetchColumn());
    }
    public function testCancellationEndsOnlyItsOwnRosterGrantAndStopsFutureBilling(): void
    {
        $id = Fixture::subscribe($this->pdo, Fixture::tariff($this->pdo));
        $this->pdo->exec("INSERT INTO club_roster_members(team_id,sportovec_id,status,source,valid_from) VALUES(10,1,'active','manual','2026-01-01')");
        self::assertSame('2026-09-30', \monthlyMembershipCancel($this->pdo, 20, $id, true, new DateTimeImmutable('2026-09-20')));
        self::assertSame('2026-09-30', \monthlyMembershipCancel($this->pdo, 20, $id, true, new DateTimeImmutable('2026-10-20')));
        self::assertSame(0, \monthlyMembershipTransaction($this->pdo, fn () => \monthlyMembershipGenerateLocked($this
    ->pdo, $id, '2026-11-01')));
        self::assertSame(1, (int)$this->pdo->query("SELECT COUNT(*) FROM club_roster_members WHERE source='manual' AND status='active'")
    ->fetchColumn());
        self::assertSame(120000, \monthlyMembershipRemaining($this->pdo, 1));
    }
    public function testOverdueStagesAreIdempotentUseActualRemainderAndStopAfterPayment(): void
    {
        Fixture::subscribe($this->pdo, Fixture::tariff($this->pdo));
        self::assertSame(0, \monthlyMembershipQueueOverdue($this->pdo, new DateTimeImmutable('2026-09-17')));
        self::assertSame(1, \monthlyMembershipQueueOverdue($this->pdo, new DateTimeImmutable('2026-09-18')));
        self::assertSame(0, \monthlyMembershipQueueOverdue($this->pdo, new DateTimeImmutable('2026-09-18')));
        $m = Fixture::movement($this->pdo, 'LOCALHOST-REMINDER-PART', '500');
        \monthlyMembershipImportMovement($this->pdo, $m, 'CZ6508000000192000145399');
        $message = \monthlyMembershipMessage($this->pdo, 1, new DateTimeImmutable('2026-09-18'));
        self::assertStringContainsString('700,00', $message['body']);
        $sent = [];
        $sender = static function ($email, $subject, $body) use (&$sent): bool {
            $sent[] = [$email,$subject,$body];
            return true;
        };
        self::assertTrue(\monthlyMembershipSendOne($this->pdo, $sender, new DateTimeImmutable('2026-09-18')));
        self::assertCount(1, $sent);
        self::assertNull(\monthlyMembershipSendOne($this->pdo, $sender, new DateTimeImmutable('2026-09-18')));
        self::assertSame(1, \monthlyMembershipQueueOverdue($this->pdo, new DateTimeImmutable('2026-09-22')));
        $m = Fixture::movement($this->pdo, 'LOCALHOST-REMINDER-REST', '700', '2026-09-22');
        \monthlyMembershipImportMovement($this->pdo, $m, 'CZ6508000000192000145399');
        self::assertNull(\monthlyMembershipSendOne($this->pdo, $sender, new DateTimeImmutable('2026-09-22')));
        self::assertSame(0, \monthlyMembershipQueueOverdue($this->pdo, new DateTimeImmutable('2026-10-15')));
        self::assertCount(1, $sent);
    }
    public function testIssuedPdfIsAvailableOnlyToTheAuthorizedPayerAndReplayKeepsNumber(): void
    {
        Fixture::subscribe($this->pdo, Fixture::tariff($this->pdo));
        $_SESSION = []; // Worker mandate must work without impersonating a logged-in employee.
        $doc = \monthlyMembershipIssueDocument($this->pdo, 1);
        self::assertSame([], $_SESSION);
        self::assertSame($doc, \monthlyMembershipIssueDocument($this->pdo, 1));
        self::assertSame(1, (int)$this->pdo->query("SELECT COUNT(*) FROM accounting_documents WHERE status='issued'")
    ->fetchColumn());
        $path = \monthlyMembershipDocumentForAccount($this->pdo, 20, $doc);
        self::assertSame('%PDF', file_get_contents($path, false, null, 0, 4));
        $this->expectException(\DomainException::class);
        \monthlyMembershipDocumentForAccount($this->pdo, 30, $doc);
    }
    public function testMissingNextYearSeriesDoesNotDuplicateChargesAndCanRecover(): void
    {
        $id = Fixture::subscribe($this->pdo, Fixture::tariff($this->pdo), 1, '2026-12-01');
        $this->pdo->exec("DELETE FROM accounting_document_series WHERE series_year=2027");
        \monthlyMembershipTransaction($this->pdo, fn () => \monthlyMembershipGenerateLocked($this->pdo, $id, '2027-01-01'));
        try {
            \monthlyMembershipIssueDocument($this->pdo, 2);
            self::fail('Missing approved series must block issue.');
        } catch (\DomainException) {
        }
        self::assertSame(2, (int)$this->pdo->query('SELECT COUNT(*) FROM club_member_charges')->fetchColumn());
        $_SESSION = ['trener_id' => 7,'staff_positions' => ['finance_manager'],'staff_active_position' => 'finance_manager'];
        \accountingDocumentConfigureSeries($this->pdo, ['kind' => 'contribution_confirmation','series_year' => 2027
    ,'prefix' => 'CLEN','next_number'
    => 1,'confirmed' => '1','reason' => 'LOCALHOST nová řada']);
        self::assertGreaterThan(0, \monthlyMembershipIssueDocument($this->pdo, 2));
        self::assertSame(2, (int)$this->pdo->query('SELECT COUNT(*) FROM club_member_charges')->fetchColumn());
    }
    public function testMonthlyVariantCannotBeSoldThroughOneTimeCheckout(): void
    {
        Fixture::tariff($this->pdo);
        $variant = ['id' => 1,'variant_id' => 1,'offer_type' => 'program','product_status' => 'active','publication_status'
    => 'active','catalog_status'
    => 'active','visible' => 1,'price_mode' => 'fixed','amount_minor' => 120000,'currency' => 'CZK'];
        self::assertFalse(\shopCheckoutVariantIsSaleable($variant, $this->pdo));
        $this->pdo->exec("UPDATE monthly_membership_tariffs SET status='inactive'");
        self::assertFalse(\shopCheckoutVariantIsSaleable($variant, $this->pdo));
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM monthly_memberships')->fetchColumn());
    }
    public function testWorkerIssuesAndNotifiesOnceAndRechecksPaymentBeforeDelivery(): void
    {
        Fixture::subscribe($this->pdo, Fixture::tariff($this->pdo));
        $sent = [];
        $sender = static function ($email, $subject, $body) use (&$sent): bool {
            $sent[] = [$email,$subject,$body];
            return true;
        };
        $result = \monthlyMembershipRun($this->pdo, true, true, $sender, 100, new DateTimeImmutable('2026-09-18'));
        self::assertSame([], $result['errors']);
        self::assertSame(1, $result['documents']);
        self::assertSame(2, $result['sent']);
        $repeat = \monthlyMembershipRun($this->pdo, true, true, $sender, 100, new DateTimeImmutable('2026-09-18'));
        self::assertSame(0, $repeat['charges']);
        self::assertSame(0, $repeat['documents']);
        self::assertSame(0, $repeat['sent']);
        self::assertCount(2, $sent);
    }

    public function testFioAutomaticWorkerUsesTheStandingPaymentAllocator(): void
    {
        if (!defined('MONTHLY_MEMBERSHIP_AUTOMATION_ENABLED')) {
            define('MONTHLY_MEMBERSHIP_AUTOMATION_ENABLED', true);
        }
        Fixture::subscribe($this->pdo, Fixture::tariff($this->pdo));
        Fixture::movement($this->pdo, 'LOCALHOST-AUTO', '1200');
        self::assertSame(['confirmed' => 1,'review' => 0], \fioAutoConfirmPending($this->pdo, 'CZ6508000000192000145399'));
        self::assertSame(['confirmed' => 0,'review' => 0], \fioAutoConfirmPending($this->pdo, 'CZ6508000000192000145399'));
        self::assertSame(0, \monthlyMembershipRemaining($this->pdo, 1));
    }

    public function testMonthlyProductRemainsVisibleInCatalogAndCannotBecomeAnOrdinaryPurchase(): void
    {
        require_once dirname(__DIR__, 2).'/includes/shop_catalog_cards.php';
        Fixture::tariff($this->pdo);
        $cards = \shopPublicCatalogCards($this->pdo);
        self::assertCount(1, $cards);
        self::assertSame(1, $cards[0]['variants'][0]['monthly_tariff_id']);
        $this->pdo->exec("UPDATE monthly_membership_tariffs SET status='inactive'");
        self::assertSame([], \shopPublicCatalogCards($this->pdo));
    }

    public function testRevokedFinanceMandateDoesNotIssueDocumentsOrImpersonateSession(): void
    {
        Fixture::subscribe($this->pdo, Fixture::tariff($this->pdo));
        $this->pdo->exec('DELETE FROM staff_user_positions');
        $_SESSION = [];
        try {
            \monthlyMembershipIssueDocument($this->pdo, 1);
            self::fail('Revoked mandate');
        } catch (\DomainException) {
        }
        self::assertSame([], $_SESSION);
        self::assertNull(\AccountingDocumentWorker::actor());
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM accounting_documents')->fetchColumn());
    }

    public function testChangedTermsAndDuplicateMembershipDoNotCreateAnotherDebt(): void
    {
        $t = Fixture::tariff($this->pdo);
        Fixture::subscribe($this->pdo, $t);
        try {
            Fixture::subscribe($this->pdo, $t, 1, '2026-09-02');
            self::fail('Duplicate membership');
        } catch (\DomainException) {
        }
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM monthly_memberships')->fetchColumn());
        self::assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM club_member_charges')->fetchColumn());
    }

    public function testAccountingReadsBankReceiptOnceAndTreatsPaidChargesAsAllocationsNotNewMoney(): void
    {
        Fixture::subscribe($this->pdo, Fixture::tariff($this->pdo));
        $movement = Fixture::movement($this->pdo, 'LOCALHOST-ACCOUNTING', '1200');
        \monthlyMembershipImportMovement($this->pdo, $movement, 'CZ6508000000192000145399');
        $payment = $this->pdo->query('SELECT * FROM payments WHERE payable_id=1')->fetch(PDO::FETCH_ASSOC);
        self::assertTrue(\accountingLedgerPaymentDerived($this->pdo, $payment));
        $source = \accountingLedgerSource($this->pdo, 'fio_account_movements', $movement);
        self::assertSame(120000, $source['amount_minor']);
        self::assertContains('monthly_membership_receipts', array_column($source['aliases'], 'source_type'));
        $allocations = \accountingLedgerReadSourceAllocations($this->pdo, $source);
        self::assertCount(1, $allocations);
        self::assertSame('member_charge', $allocations[0]['case_type']);
        self::assertSame(120000, $allocations[0]['amount_minor']);
        $this->expectException(\AccountingLedgerException::class);
        \accountingLedgerSource($this->pdo, 'payments', (int)$payment['id']);
    }

    public function testRevokedPayerCannotSubscribeAndNoPartialGrantOrChargeRemains(): void
    {
        $t = Fixture::tariff($this->pdo);
        $this->pdo->exec("UPDATE account_person_roles SET status='revoked'");
        try {
            Fixture::subscribe($this->pdo, $t);
            self::fail('Revoked payer');
        } catch (\DomainException) {
        }
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM monthly_memberships')->fetchColumn());
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn());
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn());
    }
}
