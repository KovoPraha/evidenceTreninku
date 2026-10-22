<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/financial_event_guard.php';
require_once dirname(__DIR__, 2) . '/includes/file_mutation_transaction.php';
require_once dirname(__DIR__, 2) . '/includes/financial_receipt_files.php';

final class FinancialEventGuardTest extends TestCase
{
    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE ucto_udalosti (id INTEGER PRIMARY KEY, stav TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE ucto_uctenky (id INTEGER PRIMARY KEY, udalost_id INTEGER, castka INTEGER, obrazek_path TEXT)');
        $pdo->exec("INSERT INTO ucto_udalosti VALUES (1, 'otevrena'), (2, 'uzavrena'), (3, 'otevrena'), (4, 'unknown')");
        $pdo->exec("INSERT INTO ucto_uctenky VALUES (1, 1, 100, 'original'), (2, 2, 200, 'closed'), (3, NULL, 300, '')");
        return $pdo;
    }

    public function testGuardsRefuseAutocommitEvenWithoutEvent(): void
    {
        $this->expectException(\LogicException::class);
        \financialReceiptAssertMutable($this->database(), null, null);
    }

    public function testOpenReceiptCanMoveBetweenOpenEventsAndBecomeUnassigned(): void
    {
        $pdo = $this->database();
        foreach ([3, null] as $target) {
            $pdo->beginTransaction();
            $receipt = \financialReceiptLock($pdo, 1);
            \financialReceiptAssertMutable($pdo, $receipt, $target);
            $pdo->prepare('UPDATE ucto_uctenky SET udalost_id=? WHERE id=1')->execute([$target]);
            $pdo->commit();
            self::assertEquals($target, $pdo->query('SELECT udalost_id FROM ucto_uctenky WHERE id=1')->fetchColumn());
        }
    }

    public function testClosedSourceOrTargetBlocksEditDeleteDetachAndInsertion(): void
    {
        $pdo = $this->database();
        $before = $pdo->query('SELECT * FROM ucto_uctenky ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        foreach ([[2, 1], [2, null], [1, 2], [null, 2], [3, 2]] as [$receiptId, $target]) {
            $pdo->beginTransaction();
            try {
                $receipt = $receiptId === null ? null : \financialReceiptLock($pdo, $receiptId);
                \financialReceiptAssertMutable($pdo, $receipt, $target);
                self::fail('Closed financial evidence must block every mutation.');
            } catch (\FinancialEventConflict $e) {
                self::assertStringContainsString('#2', $e->getMessage());
                self::assertStringContainsString('hospodáři', $e->getMessage());
            } finally {
                $pdo->rollBack();
            }
        }
        self::assertSame($before, $pdo->query('SELECT * FROM ucto_uctenky ORDER BY id')->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testMissingOrUnknownEventFailsClosed(): void
    {
        $pdo = $this->database();
        foreach ([4, 999, -1] as $id) {
            $pdo->beginTransaction();
            try {
                \financialReceiptAssertMutable($pdo, null, $id);
                self::fail('Unknown financial state must not accept a receipt.');
            } catch (\FinancialEventConflict $e) {
                self::assertNotSame('', $e->getMessage());
            } finally {
                $pdo->rollBack();
            }
        }
    }

    public function testRepeatedClosureCannotReopenOrChangeExistingState(): void
    {
        $pdo = $this->database();
        $pdo->beginTransaction();
        \financialEventAssertOpen(\financialEventLock($pdo, 1));
        $pdo->exec("UPDATE ucto_udalosti SET stav='uzavrena' WHERE id=1");
        $pdo->commit();
        $pdo->beginTransaction();
        try {
            \financialEventAssertOpen(\financialEventLock($pdo, 1));
            self::fail('Second close must not mutate a closed event.');
        } catch (\FinancialEventConflict $e) {
            self::assertStringContainsString('#1', $e->getMessage());
        } finally {
            $pdo->rollBack();
        }
        self::assertSame('uzavrena', $pdo->query('SELECT stav FROM ucto_udalosti WHERE id=1')->fetchColumn());
    }

    public function testFailureAfterReplacementRestoresFileAndReceipt(): void
    {
        $pdo = $this->database();
        $original = tempnam(sys_get_temp_dir(), 'b1-original-');
        $replacement = tempnam(sys_get_temp_dir(), 'b1-new-');
        file_put_contents($original, 'original-proof');
        file_put_contents($replacement, 'replacement-proof');
        $files = \fileMutationBegin();
        try {
            $pdo->beginTransaction();
            $receipt = \financialReceiptLock($pdo, 1);
            \financialReceiptAssertMutable($pdo, $receipt, 1);
            $files['finalized'][] = $replacement;
            \fileMutationRetire($files, $original);
            $pdo->exec("UPDATE ucto_uctenky SET obrazek_path='replacement' WHERE id=1");
            try {
                $pdo->exec('INSERT INTO missing_audit_table VALUES (1)');
                self::fail('Fixture must fail before commit.');
            } catch (\PDOException) {
                $pdo->rollBack();
                \fileMutationRollback($files);
            }
            self::assertSame('original-proof', file_get_contents($original));
            self::assertFileDoesNotExist($replacement);
            self::assertSame('original', $pdo->query('SELECT obrazek_path FROM ucto_uctenky WHERE id=1')->fetchColumn());
        } finally {
            if ($pdo->inTransaction()) $pdo->rollBack();
            \fileMutationRollback($files);
            if (is_file($original)) unlink($original);
            if (is_file($replacement)) unlink($replacement);
        }
    }

    public function testCommittedDeletionKeepsOriginalBytesInHiddenArchive(): void
    {
        $pdo = $this->database();
        $directory = sys_get_temp_dir() . '/b1-receipt-archive-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $original = $directory . '/original.pdf';
        $bytes = "%PDF-1.4\nLOCALHOST NEPLATIT original proof\0\xff";
        file_put_contents($original, $bytes);
        $files = \fileMutationBegin();
        try {
            $pdo->beginTransaction();
            \financialReceiptAssertMutable($pdo, \financialReceiptLock($pdo, 1), null);
            \fileMutationRetire($files, $original);
            $pdo->exec('DELETE FROM ucto_uctenky WHERE id=1');
            $pdo->commit();
            \financialReceiptFilesCommitted($files);

            self::assertFileDoesNotExist($original);
            $archives = glob($directory . '/.deleted-*');
            self::assertCount(1, $archives);
            self::assertSame($bytes, file_get_contents($archives[0]));
            self::assertSame([], glob($directory . '/.retired-*'));
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM ucto_uctenky WHERE id=1')->fetchColumn());
            self::assertSame(\fileMutationBegin(), $files);
        } finally {
            if ($pdo->inTransaction()) $pdo->rollBack();
            \fileMutationRollback($files);
            foreach (array_merge(glob($directory . '/*'), glob($directory . '/.*')) as $path) {
                if (is_file($path)) unlink($path);
            }
            rmdir($directory);
        }
    }
}
