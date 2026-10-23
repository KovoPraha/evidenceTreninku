<?php
declare(strict_types=1);
namespace Tests\Integration;
use PDO;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/bin/production-accounting-audit.php';

final class ProductionAccountingAuditTest extends TestCase
{
    private function db(): PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY); CREATE TABLE shop_orders(id INTEGER PRIMARY KEY,total_minor INT,currency TEXT)');
        foreach (['20261022220000_accounting_ledger','20261022221000_accounting_documents','20261022222000_accounting_adjustments','20261022223000_accounting_exports'] as $name) {
            $m=require dirname(__DIR__,2).'/migrations/'.$name.'.php'; $m['up']($pdo);
        }
        return $pdo;
    }

    public function testEmptyEvidenceIsReportedWithoutCreatingAnything(): void
    {
        $pdo=$this->db();$changes=(int)$pdo->query('SELECT total_changes()')->fetchColumn();
        $result=\productionAccountingAudit($pdo,'');
        self::assertTrue($result['ok']); self::assertTrue($result['read_only']);
        self::assertSame(0,$result['issued_pdfs_checked']); self::assertSame(0,$result['inventory']['accounting_export_batches']);
        self::assertNotEmpty($result['limitations']);self::assertSame($changes,(int)$pdo->query('SELECT total_changes()')->fetchColumn());
        self::assertFalse($pdo->inTransaction());self::assertSame(0,(int)$pdo->query('PRAGMA query_only')->fetchColumn());
    }

    public function testMissingSchemaFailsAndRestoresConnection(): void
    {
        $pdo=new PDO('sqlite::memory:');
        try { \productionAccountingAudit($pdo,''); self::fail('Missing schema must not be a green audit.'); }
        catch (\PDOException) { self::assertFalse($pdo->inTransaction());self::assertSame(0,(int)$pdo->query('PRAGMA query_only')->fetchColumn()); }
    }

    public function testCallerTransactionIsNeverRolledBack(): void
    {
        $pdo=$this->db();$pdo->beginTransaction();
        try { \productionAccountingAudit($pdo,'');self::fail('Caller transaction must be rejected.'); }
        catch (\RuntimeException $e) {self::assertStringContainsString('own read-only',$e->getMessage());self::assertTrue($pdo->inTransaction());}
        finally {$pdo->rollBack();}
    }

    public function testOrphanDocumentDetectedAndExistingReadOnlyModePreserved(): void
    {
        $pdo=$this->db();$json=json_encode(['lines'=>[['amount_minor'=>123]],'total_minor'=>123,'currency'=>'CZK'],JSON_THROW_ON_ERROR);
        $pdo->prepare("INSERT INTO accounting_documents(kind,source_type,source_id,idempotency_key,status,snapshot_json,snapshot_sha256,total_minor,currency,effective_on,created_by) VALUES('invoice','order',999,'test','draft',?,?,123,'CZK','2026-10-23',1)")->execute([$json,hash('sha256',$json)]);
        $pdo->exec('PRAGMA query_only=ON');$result=\productionAccountingAudit($pdo,'');
        self::assertFalse($result['ok']);self::assertSame(1,$result['checks']['orphan_order_documents']['count']);
        self::assertSame(0,$result['checks']['document_snapshot_integrity']['count']);
        self::assertSame(1,(int)$pdo->query('PRAGMA query_only')->fetchColumn());
    }
}
