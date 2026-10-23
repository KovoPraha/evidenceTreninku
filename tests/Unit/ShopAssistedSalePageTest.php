<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopAssistedSalePageTest extends TestCase
{
    /** Execute the real POST controller and field markup; isolate auth and order-writing boundaries. */
    private function exercise(string $mode, array $get = [], array $post = []): array
    {
        $root = dirname(__DIR__, 2);
        $source = (string)file_get_contents($root . '/eshop_assisted_sale_admin.php');
        $controller = strstr($source, '$variants=$pdo->query', true);
        self::assertIsString($controller);
        $controller = preg_replace("~require_once __DIR__\\.[^;]+;~", '', $controller);
        $controller = str_replace("staffRequireActivePosition('order_operator');", '', $controller);
        $controller = str_replace("csrf_verify((string)(\$_POST['csrf_token']??''))", 'true', $controller);
        $controller = str_replace('header(', 'testCaptureHeader(', $controller);
        preg_match('~<label>Množství(.*?)</label>~s', $source, $quantity);
        preg_match('~<label>Sportovec jako příjemce.*?(<input.*?</label>)~s', $source, $beneficiary);
        self::assertArrayHasKey(1, $quantity);
        self::assertArrayHasKey(1, $beneficiary);
        $post = array_replace(['request_key'=>'request-original','variant_id'=>'50','quantity'=>'2','beneficiary_id'=>'123',
            'first_name'=>'UAT','last_name'=>'Synthetic','payer_name'=>'UAT payer','recipient_name'=>'UAT recipient','reason'=>'Synthetic retry'], $post);
        $code = 'require ' . var_export($root . '/includes/shop_bank_settings.php', true) . ';' . <<<'PHP'
define('SHOP_BANK_IBAN','CZ7599999999999999999999');
define('SHOP_BANK_BIC','');define('SHOP_BANK_ACCOUNT_LABEL','SYNTHETIC CONFIG FALLBACK');define('SHOP_BANK_DUE_DAYS',7);
function testCaptureHeader(string $value, bool $replace=true, int $status=0):void {$GLOBALS['redirect']=$value;$GLOBALS['redirect_status']=$status;}
function shopAssistedSalePlace(...$args):array {$GLOBALS['attempt']=['bank'=>$args[8],'quantity'=>$args[2],'beneficiary'=>$args[10]];throw new RuntimeException('Injected order validation failure.');}
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE shop_bank_settings(id INTEGER PRIMARY KEY,iban TEXT,bic TEXT,account_label TEXT,due_days INTEGER,updated_at TEXT,updated_by_trainer_id INTEGER)');
$GLOBALS['attempt']=null;$GLOBALS['redirect']=null;$GLOBALS['rendered']='';
$_SESSION=['trener_id'=>326,'assisted_request'=>['key'=>'request-original','access'=>'access-original'],'assisted_last_order'=>['id'=>42,'code'=>'SYNTHETIC-OLD']];
register_shutdown_function(static function():void {echo json_encode(['attempt'=>$GLOBALS['attempt'],'redirect'=>$GLOBALS['redirect'],'redirect_status'=>$GLOBALS['redirect_status']??null,'request'=>$_SESSION['assisted_request'],'last'=>$_SESSION['assisted_last_order']??null,'error'=>$GLOBALS['error']??'','rendered'=>$GLOBALS['rendered']],JSON_THROW_ON_ERROR);});
PHP;
        $code .= '$mode=' . var_export($mode, true) . ';';
        $code .= <<<'PHP'
$pdo->prepare('INSERT INTO shop_bank_settings VALUES(1,?,?,?,14,?,326)')->execute([$mode==='invalid_bank'?'BAD':'CZ2799990000000011111111','','SYNTHETIC DATABASE ACCOUNT','2026-10-23']);
$_SERVER['REQUEST_METHOD']=$mode==='new'?'GET':($mode==='initial'?'GET':'POST');
PHP;
        $code .= '$_GET=' . var_export($get, true) . ';$_POST=' . var_export($mode === 'initial' ? [] : $post, true) . ';';
        $code .= 'eval(' . var_export(substr($controller, 5), true) . ');';
        $code .= 'ob_start();eval(' . var_export('?>' . $quantity[1] . $beneficiary[1], true) . ');$GLOBALS["rendered"]=ob_get_clean();';
        $process = proc_open([PHP_BINARY, '-r', $code], [['pipe','r'],['pipe','w'],['pipe','w']], $pipes);
        self::assertIsResource($process);fclose($pipes[0]);
        $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        self::assertSame(0,proc_close($process),$stderr);self::assertSame('',$stderr);
        return json_decode($stdout,true,512,JSON_THROW_ON_ERROR);
    }

    public function testDatabaseBankWinsAndFailedPostPreservesFieldsAndRequest():void
    {
        $result=$this->exercise('valid_bank',['new'=>'1']);
        self::assertSame('SYNTHETIC DATABASE ACCOUNT',$result['attempt']['bank']['account_label']);
        self::assertSame(14,$result['attempt']['bank']['due_days']);
        self::assertSame(2,$result['attempt']['quantity']);self::assertSame(123,$result['attempt']['beneficiary']);
        self::assertSame('request-original',$result['request']['key']);self::assertSame(42,$result['last']['id']);
        self::assertNull($result['redirect']);
        self::assertMatchesRegularExpression('/name="quantity"[^>]*value="2"/',$result['rendered']);
        self::assertMatchesRegularExpression('/name="beneficiary_id"[^>]*value="123"/',$result['rendered']);
    }

    public function testInvalidDatabaseBankCannotFallBackToValidConfigOrReachOrderWriter():void
    {
        $result=$this->exercise('invalid_bank');
        self::assertNull($result['attempt']);self::assertStringContainsString('Uložený bankovní účet',$result['error']);
        self::assertSame('request-original',$result['request']['key']);
        self::assertMatchesRegularExpression('/name="quantity"[^>]*value="2"/',$result['rendered']);
    }

    public function testNewGetRotatesOnlyOnceAndKeepsValidatedSelection():void
    {
        $result=$this->exercise('new',['new'=>'1','variant_id'=>'50','account_id'=>'12','unrelated'=>'ignored']);
        self::assertSame('Location: eshop_assisted_sale_admin.php?variant_id=50&account_id=12',$result['redirect']);
        self::assertSame(303,$result['redirect_status']);self::assertNull($result['last']);self::assertNull($result['attempt']);
        self::assertNotSame('request-original',$result['request']['key']);
        $invalid=$this->exercise('new',['new'=>'1','variant_id'=>'50evil','account_id'=>'-1']);
        self::assertSame('Location: eshop_assisted_sale_admin.php',$invalid['redirect']);
    }

    public function testInitialQuantityAndEscapingOfPreservedInvalidValues():void
    {
        $initial=$this->exercise('initial');self::assertMatchesRegularExpression('/name="quantity"[^>]*value="1"/',$initial['rendered']);
        $error=$this->exercise('invalid_bank',[],['quantity'=>'2" autofocus onfocus="alert(1)','beneficiary_id'=>'123" onclick="alert(1)']);
        self::assertStringContainsString('value="2&quot; autofocus onfocus=&quot;alert(1)"',$error['rendered']);
        self::assertStringNotContainsString(' onfocus="',$error['rendered']);self::assertStringNotContainsString(' onclick="',$error['rendered']);
    }
}
