<?php
declare(strict_types=1);
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Render the real route using inert dependencies; never load the runtime or access a DB. */
final class AccountingLedgerAllocationViewTest extends TestCase
{
    private string $root;

    protected function setUp():void
    {
        $this->root=sys_get_temp_dir().'/kis-allocation-view-'.bin2hex(random_bytes(8));
        mkdir($this->root.'/includes',0700,true);
        copy(dirname(__DIR__,2).'/accounting_ledger_admin.php',$this->root.'/accounting_ledger_admin.php');
        foreach(['csrf_helper.php','includes/accounting_ledger.php','includes/ui_shell.php','hlavicka.php'] as $file)file_put_contents($this->root.'/'.$file,'<?php');
        file_put_contents($this->root.'/includes/init.php',<<<'PHP'
<?php
http_response_code(200);
$_GET=json_decode($argv[1],true,flags:JSON_THROW_ON_ERROR);$_POST=json_decode($argv[3],true,flags:JSON_THROW_ON_ERROR);$_SESSION=['trener_id'=>326];$_SERVER['REQUEST_METHOD']=$_POST===[]?'GET':'POST';
$GLOBALS['queries']=[];
register_shutdown_function(static function():void{fwrite(STDERR,json_encode(['status'=>http_response_code(),'queries'=>$GLOBALS['queries']]));});
function staffRequireActivePosition(string $position):void {if($position!=='finance_manager'||$GLOBALS['argv'][2]!=='finance_manager'){http_response_code(403);exit('Forbidden');}}
function appUiAssets():void {}
function csrf_token():string {return 'test-token';}
function csrf_verify(string $token):bool {return $token==='test-token';}
class AccountingLedgerException extends RuntimeException {}
function accountingLedgerAllocate(...$args):never {throw new AccountingLedgerException('Simulace odmítnutí uzavřeného období.');}
function accountingLedgerPendingSources(...$args):array {return [];}
function accountingLedgerTable(...$args):bool {return false;}
$pdo=new class {
    public function query(string $sql):object {
        if(!str_starts_with($sql,'SELECT '))throw new RuntimeException('Write forbidden');
        $GLOBALS['queries'][]=[$sql,[]];
        return new class {public function fetchAll(int $mode):array{return [];}};
    }
    public function prepare(string $sql):object {
        if(!str_starts_with($sql,'SELECT a.*')||!str_contains($sql,'r.reverses_id=a.id'))throw new RuntimeException('Unexpected SQL');
        return new class($sql) {
            private array $params=[];
            public function __construct(private string $sql){}
            public function execute(array $params):void {$this->params=$params;$GLOBALS['queries'][]=[$this->sql,$params];}
            public function fetchAll(int $mode):array {
                $common=['movement_id'=>7,'case_type'=>'shop_order','case_id'=>42,'effective_on'=>'2026-10-23','classification_code'=>'3/98','activity_code'=>'<UAT>','account_name'=>'TEST cash','source_reference'=>'<script>unsafe</script>','reverses_id'=>null];
                $rows=[
                    ['id'=>11,'amount_minor'=>100000,'released_minor'=>-50000]+$common,
                    ['id'=>12,'amount_minor'=>-50000,'released_minor'=>0,'reverses_id'=>11]+$common,
                    ['id'=>13,'amount_minor'=>50000,'released_minor'=>-50000]+$common,
                    ['id'=>14,'amount_minor'=>50000,'released_minor'=>0,'case_type'=>'expense_claim']+$common,
                ];
                if(str_contains($this->sql,' WHERE a.id=?'))return array_values(array_filter($rows,fn($r)=>$r['id']===$this->params[0]));
                if(str_contains($this->sql,' WHERE a.movement_id=?'))return array_values(array_filter($rows,fn($r)=>$r['movement_id']===$this->params[0]));
                return $rows;
            }
            public function fetch(int $mode):array|false {return $this->fetchAll($mode)[0]??false;}
        };
    }
};
PHP);
    }

    protected function tearDown():void
    {
        foreach(['includes/init.php','includes/ui_shell.php','includes/accounting_ledger.php','csrf_helper.php','hlavicka.php','accounting_ledger_admin.php'] as $file)unlink($this->root.'/'.$file);
        rmdir($this->root.'/includes');rmdir($this->root);
    }

    private function render(array $query=[],string $position='finance_manager',array $post=[]):array
    {
        $process=proc_open([PHP_BINARY,$this->root.'/accounting_ledger_admin.php',json_encode($query,JSON_THROW_ON_ERROR),$position,json_encode($post,JSON_THROW_ON_ERROR)],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        self::assertIsResource($process);fclose($pipes[0]);$html=stream_get_contents($pipes[1]);$report=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        self::assertSame(0,proc_close($process),$report);
        $document=new \DOMDocument();@$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        return [$html,new \DOMXPath($document),json_decode($report,true,flags:JSON_THROW_ON_ERROR)];
    }

    public function testReadOnlyListShowsIdsTargetsPartialAndFullReversalsWithoutExecutableUserContent():void
    {
        [$html,$xpath,$report]=$this->render();
        self::assertSame(200,$report['status']);
        $row=$xpath->evaluate('string(//tr[@id="allocation-11"])');
        self::assertStringContainsString('#11',$row);self::assertStringContainsString('Pohyb #7',$row);self::assertStringContainsString('Objednávka #42',$row);
        self::assertStringContainsString('Uvolněno 500,00 Kč',$row);self::assertStringContainsString('Zbývá 500,00 Kč',$row);
        self::assertStringContainsString('Uvolnění přídělu #11',$xpath->evaluate('string(//tr[@id="allocation-12"])'));
        self::assertStringContainsString('Plně uvolněno',$xpath->evaluate('string(//tr[@id="allocation-13"])'));
        self::assertSame(1,$xpath->query('//a[contains(@href,"release_allocation_id=")]')->length);
        self::assertStringNotContainsString('<script>unsafe</script>',$html);
        foreach($report['queries'] as [$sql])self::assertStringStartsWith('SELECT ',$sql);
        self::assertStringContainsString('ORDER BY a.id DESC LIMIT 200',$report['queries'][2][0]);
    }

    public function testMovementFilterIsBoundAndReturnsEmptyStateWithoutGuessingAnotherAllocation():void
    {
        [$html,$xpath,$report]=$this->render(['allocation_movement_id'=>999]);
        self::assertSame([999],$report['queries'][2][1]);
        self::assertStringContainsString('WHERE a.movement_id=?',$report['queries'][2][0]);
        self::assertStringContainsString('Pro tento filtr nebyl nalezen žádný příděl.',$html);
        self::assertSame(0,$xpath->query('//a[contains(@href,"release_allocation_id=")]')->length);
    }

    public function testGetSelectionPrefillsExistingAuditedFormWithRemainingAmountAndOriginalClassification():void
    {
        [$html,$xpath,$report]=$this->render(['allocation_movement_id'=>7,'release_allocation_id'=>11]);
        self::assertSame(1,$xpath->query('//details[@id="allocation-form"][@open]')->length);
        foreach(['action'=>'allocate','movement_id'=>'7','case_id'=>'42','reverses_id'=>'11','amount'=>'-500.00','classification_code'=>'3/98','activity_code'=>'<UAT>','csrf_token'=>'test-token'] as $name=>$value){
            self::assertSame($value,$xpath->evaluate('string(//details[@id="allocation-form"]//input[@name="'.$name.'"]/@value)'),$name);
        }
        self::assertSame('shop_order',$xpath->evaluate('string(//details[@id="allocation-form"]//option[@selected]/@value)'));
        self::assertSame('',$xpath->evaluate('string(//details[@id="allocation-form"]//input[@name="effective_on"]/@value)'));
        self::assertSame(1,$xpath->query('//details[@id="allocation-form"]//input[@name="confirmed"][@required]')->length);
        self::assertSame(0,$xpath->query('//details[@id="allocation-form"]//input[@name="confirmed"][@checked]')->length);
        self::assertSame([11],$report['queries'][3][1]);
    }

    public function testReleasedNegativeUnsupportedAndMissingSelectionsCannotPrefillRelease():void
    {
        foreach([12,13,14,999] as $id){
            [$html,$xpath]=$this->render(['release_allocation_id'=>$id]);
            self::assertStringContainsString('Vybraný příděl není dostupný pro uvolnění',$html);
            self::assertSame(0,$xpath->query('//details[@id="allocation-form"][@open]')->length);
            self::assertSame('',$xpath->evaluate('string(//details[@id="allocation-form"]//input[@name="reverses_id"]/@value)'));
        }
    }

    public function testNonFinancePositionStopsBeforeAnyRead():void
    {
        [$html,,$report]=$this->render(['release_allocation_id'=>11],'coach');
        self::assertSame('Forbidden',$html);self::assertSame(403,$report['status']);self::assertSame([],$report['queries']);
    }

    public function testRejectedPartialReleasePreservesSubmittedAmountTargetDateAndIdempotencyKey():void
    {
        $post=['action'=>'allocate','csrf_token'=>'test-token','movement_id'=>'8','case_type'=>'shop_return','case_id'=>'43','amount'=>'-250,00','reverses_id'=>'21','classification_code'=>'NEW-CODE','activity_code'=>'<LOCAL>','effective_on'=>'2026-10-22','request_key'=>'same-partial-release-request','reason'=>'SIMULACE oprava podkladu','confirmed'=>'1'];
        [$html,$xpath,$report]=$this->render(['release_allocation_id'=>11],'finance_manager',$post);
        self::assertSame(422,$report['status']);self::assertStringContainsString('Simulace odmítnutí uzavřeného období.',$html);
        self::assertSame(1,$xpath->query('//details[@id="allocation-form"][@open]')->length);
        foreach($post as $field=>$value){
            if($field==='case_type')self::assertSame($value,$xpath->evaluate('string(//details[@id="allocation-form"]//option[@selected]/@value)'));
            else self::assertSame($value,$xpath->evaluate('string(//details[@id="allocation-form"]//input[@name="'.$field.'"]/@value)'),$field);
        }
        self::assertSame(1,$xpath->query('//details[@id="allocation-form"]//input[@name="confirmed"][@checked]')->length);
        self::assertStringNotContainsString('value="-500.00"',$html);
    }
}
