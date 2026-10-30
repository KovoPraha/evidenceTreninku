<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tests\Support\QueryProfilePdo;

require_once dirname(__DIR__, 2) . '/includes/accounting_ledger.php';
require_once dirname(__DIR__) . '/Support/AccountingLedgerReadBaseline.php';

final class AccountingLedgerReadTest extends TestCase
{
    private function database(): QueryProfilePdo
    {
        $pdo = new QueryProfilePdo('sqlite::memory:');
        $pdo->exec('CREATE TABLE accounting_movement_sources(id INT,source_type TEXT,source_id INT,movement_id INT)');
        $pdo->exec('CREATE TABLE accounting_movements(id INT,amount_minor INT,currency TEXT,effective_on TEXT)');
        $pdo->exec('CREATE TABLE accounting_allocations(request_key TEXT,amount_minor INT)');
        $pdo->exec('CREATE TABLE fio_account_movements(id INT,fio_movement_id TEXT,amount_minor INT,currency TEXT,booked_on TEXT)');
        $pdo->exec('CREATE TABLE payments(id INT,payable_type TEXT,payable_id INT,status TEXT,amount_minor INT,currency TEXT,bank_movement_id TEXT)');
        $pdo->exec('CREATE TABLE club_finance_receipts(id INT,reference TEXT,amount_minor INT,currency TEXT,paid_on TEXT)');
        $pdo->exec('CREATE TABLE member_payer_receipts(id INT,reference TEXT,amount_minor INT,currency TEXT,paid_on TEXT)');
        $pdo->exec('CREATE TABLE club_finance_allocations(id INT,receipt_id INT,charge_id INT,order_id INT,amount_minor INT,created_at TEXT)');
        for ($id = 1; $id <= 20; $id++) {
            $pdo->exec("INSERT INTO club_finance_receipts VALUES($id,'SYN-$id',100,'CZK','2026-01-01')");
            $pdo->exec("INSERT INTO club_finance_allocations VALUES($id,$id,$id,NULL,50,'2026-01-02')");
        }
        return $pdo;
    }

    public function testPendingProjectionMatchesFrozenBaselineWithBoundedMetadata(): void
    {
        $pdo = $this->database();
        $baseline = \w104PendingSourcesBaseline($pdo, 20);
        $pdo->profiling = true;
        $current = \accountingLedgerPendingSources($pdo, 20);
        $pdo->profiling = false;
        self::assertSame($baseline, $current);
        self::assertCount(20, $current);
        $metadata = array_filter($pdo->profile, static fn (array $query): bool => str_starts_with($query['sql'], 'SELECT 1 FROM '));
        self::assertLessThanOrEqual(7, count($metadata));
    }

    public function testNextOperationAndPublicSourceSeeChangedRowsAndNewSchema(): void
    {
        $pdo = $this->database();
        $first = \accountingLedgerPendingSources($pdo, 20);
        $pdo->exec("UPDATE club_finance_receipts SET amount_minor=101 WHERE id=1");
        $second = \accountingLedgerPendingSources($pdo, 20);
        self::assertSame(100, $first[0]['amount_minor']);
        self::assertSame(101, $second[0]['amount_minor']);
        self::assertSame(\w104PendingSourcesBaseline($pdo, 20), $second);
        $pdo->exec('DROP TABLE member_payer_receipts');
        $scope = new \AccountingLedgerReadContext($pdo);
        self::assertFalse($scope->tableExists($pdo, 'member_payer_receipts'));
        $pdo->exec('CREATE TABLE member_payer_receipts(id INT,reference TEXT,amount_minor INT,currency TEXT,paid_on TEXT)');
        $pdo->exec("INSERT INTO member_payer_receipts VALUES(77,'SYN-1',999,'CZK','2026-01-01')");
        // The standalone source API used by write paths never consumes a read context.
        try {
            \accountingLedgerSource($pdo, 'club_finance_receipts', 1);
            self::fail('Fresh source conflict must be refused.');
        } catch (\AccountingLedgerException $error) {
            self::assertNotSame('', $error->getMessage());
        }
        $third = \accountingLedgerPendingSources($pdo, 20);
        self::assertSame(['source_conflict'], $third[0]['flags']);
        self::assertSame(\w104PendingSourcesBaseline($pdo, 20), $third);
        $pdo->exec('DELETE FROM member_payer_receipts');
        self::assertSame($second, \accountingLedgerPendingSources($pdo, 20));
    }

    public function testReadContextCannotCrossConnections(): void
    {
        $pdo = $this->database();
        $scope = new \AccountingLedgerReadContext($pdo);
        $this->expectException(\LogicException::class);
        $scope->tableExists(new QueryProfilePdo('sqlite::memory:'), 'payments');
    }
}
