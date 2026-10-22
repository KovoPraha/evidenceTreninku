<?php
declare(strict_types=1);
namespace Tests\Integration;
use PDO;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/accounting_export.php';

final class AccountingExportTest extends TestCase
{
    private string $root;
    private string|false $oldRoot;
    protected function setUp():void {$this->oldRoot=getenv('APP_PRIVATE_STORAGE_ROOT');$this->root=sys_get_temp_dir().'/accounting-export-test-'.bin2hex(random_bytes(8));mkdir($this->root,0700,true);putenv('APP_PRIVATE_STORAGE_ROOT='.$this->root);}
    protected function tearDown():void {putenv($this->oldRoot===false?'APP_PRIVATE_STORAGE_ROOT':'APP_PRIVATE_STORAGE_ROOT='.$this->oldRoot);$it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $p){if($p->isDir())rmdir($p->getPathname());else unlink($p->getPathname());}rmdir($this->root);}
    private function db():PDO {
        $p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$p->exec('PRAGMA foreign_keys=ON');
        $p->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,aktivni INTEGER); CREATE TABLE staff_user_positions(trainer_id INTEGER,position_code TEXT);CREATE TABLE staff_superadmins(trainer_id INTEGER)');
        $p->exec("INSERT INTO treneri VALUES(1,1),(2,1),(3,0);INSERT INTO staff_user_positions VALUES(1,'finance_manager'),(2,'coach'),(3,'finance_manager')");
        foreach(['20261022220000_accounting_ledger','20261022221000_accounting_documents','20261022222000_accounting_adjustments','20261022223000_accounting_exports'] as $name){$m=require dirname(__DIR__,2).'/migrations/'.$name.'.php';$m['up']($p);self::assertTrue($m['verify']($p));}
        $p->exec("INSERT INTO accounting_accounts(id,code,name,kind,currency,opened_on,opening_minor,evidence,reason,actor_trainer_id) VALUES(1,'CASH','Test pokladna','cash','CZK','2025-01-01',10000,'LOCALHOST','LOCALHOST',1)");
        $q=$p->prepare('INSERT INTO accounting_movements(id,account_id,kind,amount_minor,currency,effective_on,source_context,source_reference,request_key,fingerprint,evidence,reason,actor_trainer_id) VALUES(?,1,?,?,\'CZK\',?,\'synthetic\',?,?,?,\'LOCALHOST\',\'LOCALHOST\',1)');
        $q->execute([1,'income',60000,'2025-09-30','0000000013','receipt1',str_repeat('a',64)]);
        $q->execute([2,'income',250000,'2025-10-31','=1+1','receipt2',str_repeat('b',64)]);
        $q->execute([3,'refund',-60000,'2025-11-02','refund','refund1',str_repeat('c',64)]);
        $p->exec('UPDATE accounting_movements SET reversal_of_id=1 WHERE id=3');
        $p->exec("INSERT INTO accounting_allocations(movement_id,case_type,case_id,amount_minor,classification_code,activity_code,effective_on,request_key,fingerprint,reason,actor_trainer_id) VALUES(2,'member_charge',10,100000,'PRISPEVKY','KLUB','2025-10-31','alloc1','test','LOCALHOST',1),(2,'member_charge',11,120000,'PRISPEVKY','KLUB','2025-10-31','alloc2','test','LOCALHOST',1)");
        return $p;
    }
    private function reject(callable $action):void {try{$action();self::fail('Expected refusal');}catch(\DomainException $e){self::assertNotSame('',$e->getMessage());}}
    public function testSnapshotHasEffectiveMoneyDatesAndNoDuplicateAllocationRevenue():void {
        $p=$this->db();$s=\accountingExportSnapshot($p,'2025-10-01','2025-10-31');
        self::assertCount(2,$s['movements']);self::assertCount(2,$s['allocations']);self::assertSame('NEÚPLNÝ',$s['status']);
        $path=$this->root.'/test.xlsx';\accountingExportWorkbook($s,$path);
        $b=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);self::assertCount(10,$b->getAllSheets());
        $money=$b->getSheetByName('Penize');self::assertSame('0000000013',$money->getCell('I2')->getValue());self::assertSame('s',$money->getCell('I2')->getDataType());self::assertSame('=1+1',$money->getCell('I3')->getValue());self::assertSame('s',$money->getCell('I3')->getDataType());self::assertSame('n',$money->getCell('F3')->getDataType());
        self::assertSame('MIMO OBRAT',$money->getCell('L2')->getValue());self::assertSame('V OBDOBÍ',$money->getCell('L3')->getValue());
        $saldo=$b->getSheetByName('Saldo');self::assertEquals(700,$saldo->getCell('D2')->getValue());self::assertEquals(2500,$saldo->getCell('E2')->getValue());self::assertEquals(3200,$saldo->getCell('F2')->getValue());
        self::assertEquals(300,$saldo->getCell('F4')->getValue(),'2500 less two real allocations1000+1200 leaves300');
        self::assertStringContainsString('NEZNÁMÉ',$b->getSheetByName('Sklad')->getCell('B2')->getValue());$b->disconnectWorksheets();
    }
    public function testArchivesAreImmutableIdempotentAndRevisionsReplaceNotAppend():void {
        $p=$this->db();$a=\accountingExportCreate($p,'2025-10-01','2025-10-31','test-request-001',1,'LOCALHOST první příprava',true);
        $file=\accountingExportDownload($p,(int)$a['id'],1);$bytes=file_get_contents($file['path']);self::assertSame($a['archive_sha256'],hash('sha256',$bytes));
        $again=\accountingExportCreate($p,'2025-10-01','2025-10-31','test-request-001',1,'LOCALHOST první příprava',true);self::assertSame($a['id'],$again['id']);
        $this->reject(fn()=>\accountingExportCreate($p,'2025-10-01','2025-10-30','test-request-001',1,'LOCALHOST první příprava',true));
        $p->exec("UPDATE accounting_accounts SET name='Current name changed'");
        $b=\accountingExportCreate($p,'2025-10-01','2025-10-31','test-request-002',1,'LOCALHOST opravená revize',true);self::assertSame(2,(int)$b['revision']);self::assertSame((int)$a['id'],(int)$b['predecessor_id']);self::assertSame($bytes,file_get_contents($file['path']));
        $zip=new \ZipArchive();self::assertTrue($zip->open($file['path']));$manifest=json_decode($zip->getFromName('manifest.json'),true,512,JSON_THROW_ON_ERROR);
        foreach($manifest['files'] as $entry){$payload=$zip->getFromName($entry['path']);self::assertIsString($payload);self::assertSame($entry['sha256'],hash('sha256',$payload));self::assertSame($entry['bytes'],strlen($payload));}$zip->close();
        file_put_contents($file['path'],'tampered');$this->reject(fn()=>\accountingExportDownload($p,(int)$a['id'],1));
    }
    public function testRolesConfirmationAndInvalidDatesNeverCreateBatch():void {
        $p=$this->db();foreach([2,3,999] as $actor)$this->reject(fn()=>\accountingExportCreate($p,'2025-10-01','2025-10-31','test-authorization', $actor,'LOCALHOST evidence',true));
        $this->reject(fn()=>\accountingExportCreate($p,'2025-10-01','2025-10-31','test-authorization',1,'LOCALHOST evidence',false));
        foreach([['2025-02-30','2025-03-01'],['2025-10-31','2025-10-01'],['2023-01-01','2025-01-01']] as [$from,$to])$this->reject(fn()=>\accountingExportSnapshot($p,$from,$to));
        self::assertSame(0,(int)$p->query('SELECT COUNT(*) FROM accounting_export_batches')->fetchColumn());
    }
    public function testFailedStorageDoesNotLeaveFinishedBatch():void {
        $p=$this->db();$block=$this->root.'/not-a-directory';file_put_contents($block,'block');putenv('APP_PRIVATE_STORAGE_ROOT='.$block);
        set_error_handler(static fn()=>true);
        try{\accountingExportCreate($p,'2025-10-01','2025-10-31','test-failed-files',1,'LOCALHOST evidence',true);self::fail('Expected storage failure');}catch(\RuntimeException $e){self::assertNotSame('',$e->getMessage());}finally{restore_error_handler();}
        self::assertFalse($p->inTransaction());self::assertSame(0,(int)$p->query('SELECT COUNT(*) FROM accounting_export_batches')->fetchColumn());
    }
    public function testRefundMonthNegativeClassificationAndMissingPdfRemainExplicit():void {
        $p=$this->db();$json=json_encode(['lines'=>[['line_key'=>'item:1','label'=>'=HYPERLINK(attack)','quantity'=>1,'amount_minor'=>60000]]],JSON_THROW_ON_ERROR);
        $p->prepare("INSERT INTO accounting_documents(kind,source_type,source_id,idempotency_key,status,number,snapshot_json,snapshot_sha256,total_minor,currency,effective_on,created_by,pdf_storage_key,pdf_sha256) VALUES('invoice','order',1,'test-invoice','issued','TEST-000013',?,?,60000,'CZK','2025-09-30',1,?,?)")->execute([$json,hash('sha256',$json),'private://accounting-documents/'.str_repeat('a',32).'.pdf',str_repeat('b',64)]);
        $parts=json_encode([['amount_minor'=>60000,'category_code'=>'ZBOZI','activity'=>'ODDIL']],JSON_THROW_ON_ERROR);
        $p->prepare("INSERT INTO accounting_classifications(movement_id,splits_json,fingerprint,request_key,actor_trainer_id,reason) VALUES(3,?,'fingerprint','classify-refund',1,'LOCALHOST')")->execute([$parts]);
        $snapshot=\accountingExportSnapshot($p,'2025-11-01','2025-11-30');self::assertCount(3,$snapshot['movements']);
        self::assertStringContainsString('Chybějící nebo poškozené PDF',implode(' ',$snapshot['exceptions']));self::assertNull($snapshot['documents'][0]['archive_path']);
        $path=$this->root.'/november.xlsx';\accountingExportWorkbook($snapshot,$path);$book=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        self::assertEquals(-600,$book->getSheetByName('Penize')->getCell('F4')->getValue());self::assertSame('n',$book->getSheetByName('Penize')->getCell('F4')->getDataType());
        self::assertEquals(-600,$book->getSheetByName('Cleneni_penez')->getCell('E2')->getValue());self::assertSame('s',$book->getSheetByName('Polozky')->getCell('C2')->getDataType());
        self::assertEquals(0,$book->getSheetByName('Saldo')->getCell('F3')->getValue(),'Original receipt survives but remaining cash reflects opposite later movement');$book->disconnectWorksheets();
    }
    public function testAccountOpenedMidPeriodDoesNotInventAnEarlierBalance():void {
        $p=$this->db();$p->exec("INSERT INTO accounting_accounts(id,code,name,kind,currency,opened_on,opening_minor,evidence,reason,actor_trainer_id) VALUES(2,'NEW','New cash','cash','CZK','2025-10-15',100000,'LOCALHOST','LOCALHOST',1)");
        $path=$this->root.'/opening.xlsx';\accountingExportWorkbook(\accountingExportSnapshot($p,'2025-10-01','2025-10-31'),$path);$book=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);$s=$book->getSheetByName('Saldo');
        self::assertSame('NEZNÁMÉ',$s->getCell('D3')->getValue());self::assertEquals(0,$s->getCell('E3')->getValue());self::assertEquals(1000,$s->getCell('F3')->getValue());self::assertStringContainsString('2025-10-15',$s->getCell('C3')->getValue());$book->disconnectWorksheets();
    }
    public function testCancellationAndReconciliationAreInRevisionChangeList():void {
        $p=$this->db();$p->exec("INSERT INTO accounting_refund_decisions(id,original_movement_id,amount_minor,currency,decided_on,reason,actor_trainer_id,request_key,input_hash) VALUES(1,2,30000,'CZK','2025-10-31','LOCALHOST',1,'decision-test','hash')");
        $a=\accountingExportCreate($p,'2025-10-01','2025-10-31','revision-cancel-before',1,'LOCALHOST před zrušením',true);
        $p->exec("INSERT INTO accounting_adjustment_events(decision_id,event_type,reason,actor_trainer_id,request_key,input_hash,recorded_at) VALUES(1,'decision_cancelled','LOCALHOST',1,'cancel-test','hash','2025-10-31 12:00:00')");
        $p->exec("INSERT INTO accounting_reconciliations(account_id,as_of,ledger_minor,observed_minor,difference_minor,is_closed,evidence,reason,actor_trainer_id) VALUES(1,'2025-10-31',320000,320000,0,1,'LOCALHOST','LOCALHOST',1)");
        $b=\accountingExportCreate($p,'2025-10-01','2025-10-31','revision-cancel-after',1,'LOCALHOST po zrušení',true);$snapshot=json_decode($b['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
        self::assertContains('adjustment_events',array_column($snapshot['changes'],'section'));self::assertContains('reconciliations',array_column($snapshot['changes'],'section'));self::assertSame((int)$a['id'],(int)$b['predecessor_id']);
    }
}
