<?php
declare(strict_types=1);
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Exercise page navigation with inert service/DB doubles, never the application runtime. */
final class ShopInventoryNavigationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/kis-inventory-navigation-'.bin2hex(random_bytes(8));
        mkdir($this->root.'/includes',0700,true);
        // PHP CLI does not expose response headers. Capture only header(), preserving page control flow.
        $page = (string)file_get_contents(dirname(__DIR__,2).'/eshop_inventory_admin.php');
        file_put_contents($this->root.'/eshop_inventory_admin.php',str_replace('header(', 'inventoryTestHeader(', $page));
        foreach (['csrf_helper.php','includes/shop_inventory.php','includes/ui_shell.php','hlavicka.php'] as $file) file_put_contents($this->root.'/'.$file,'<?php');
        file_put_contents($this->root.'/includes/init.php', <<<'PHP'
<?php
$_GET=json_decode($argv[1],true,flags:JSON_THROW_ON_ERROR);
$_POST=json_decode($argv[2],true,flags:JSON_THROW_ON_ERROR);
$_SERVER['REQUEST_METHOD']=$_POST===[]?'GET':'POST';
$_SESSION=['trener_id'=>326];
$GLOBALS['report']=['headers'=>[],'opening_variant'=>null,'opened_variant'=>null];
register_shutdown_function(static function():void {fwrite(STDERR,json_encode($GLOBALS['report']));});
function inventoryTestHeader(string $header,bool $replace=true,int $status=0):void {$GLOBALS['report']['headers'][]=[$header,$status];}
function staffWorkspaceRefreshSession(...$args):void {}
function staffActivePosition():string {return 'catalog_manager';}
function appUiAssets():void {}
function app_csp_nonce():string {return 'test-nonce';}
function csrf_field():string {return '<input type="hidden" name="csrf_token" value="test">';}
function csrf_verify(string $token):bool {return $token==='test';}
function shopInventoryPolicy(...$args):array {return ['warehouse_name'=>'TEST'];}
function shopInventoryBalance(...$args):?array {return null;}
function shopInventoryOpen($pdo,int $actor,int $variant,...$args):array {$GLOBALS['report']['opened_variant']=$variant;return ['id'=>987];}
class ShopInventoryException extends RuntimeException {}
$pdo=new class {
    public function query(string $sql):object {
        $rows=str_starts_with($sql,'SELECT v.id')?[
            ['id'=>1,'sku'=>'KP-OTHER','name'=>'A unrelated','stock_quantity_decimal'=>0],
            ['id'=>50,'sku'=>'KP-UAT20261023-M','name'=>'UAT Dres','stock_quantity_decimal'=>0],
            ['id'=>51,'sku'=>'KP-UAT20261023-L','name'=>'UAT Dres','stock_quantity_decimal'=>0],
        ]:[];
        return new class($rows) {public function __construct(private array $rows){} public function fetchAll(int $mode):array{return $this->rows;}};
    }
    public function prepare(string $sql):object {
        if(!str_starts_with($sql,'SELECT i.id,i.quantity,o.public_code'))throw new RuntimeException('Unexpected SQL');
        return new class {
            public function execute(array $params):void {$GLOBALS['report']['opening_variant']=$params[0];}
            public function fetchAll(int $mode):array {return [];}
        };
    }
};
PHP);
    }

    protected function tearDown(): void
    {
        foreach(['includes/init.php','includes/ui_shell.php','includes/shop_inventory.php','csrf_helper.php','hlavicka.php','eshop_inventory_admin.php'] as $file) unlink($this->root.'/'.$file);
        rmdir($this->root.'/includes');rmdir($this->root);
    }

    private function render(array $query,array $post=[]):array
    {
        $process=proc_open([PHP_BINARY,$this->root.'/eshop_inventory_admin.php',json_encode($query,JSON_THROW_ON_ERROR),json_encode($post,JSON_THROW_ON_ERROR)],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        self::assertIsResource($process);fclose($pipes[0]);
        $html=stream_get_contents($pipes[1]);$report=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        self::assertSame(0,proc_close($process),$report);
        return [$html,json_decode($report,true,flags:JSON_THROW_ON_ERROR)];
    }

    private function xpath(string $html):\DOMXPath
    {
        $document=new \DOMDocument();@$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        return new \DOMXPath($document);
    }

    public function testSuccessfulPostKeepsSubmittedVariantAndEncodedSearchInsteadOfMovementId():void
    {
        $q="UAT20261023 & M\r\nX-Test: value";
        [$html,$report]=$this->render(['variant_id'=>51,'q'=>'old'],['action'=>'open','variant_id'=>50,'q'=>$q,'csrf_token'=>'test','confirmed'=>'1']);
        self::assertSame(50,$report['opened_variant']);
        $redirect=end($report['headers']);
        self::assertSame(303,$redirect[1]);
        self::assertStringNotContainsString("\r",$redirect[0]);self::assertStringNotContainsString("\n",$redirect[0]);
        parse_str((string)parse_url(substr($redirect[0],10),PHP_URL_QUERY),$params);
        self::assertSame(['saved'=>'987','variant_id'=>'50','q'=>$q],$params);
        self::assertSame('',$html);
    }

    public function testFilteredInitialSelectionAndOpeningContextUseTheSameVariant():void
    {
        [$html,$report]=$this->render(['q'=>'UAT20261023']);$xpath=$this->xpath($html);
        self::assertSame(50,$report['opening_variant']);
        self::assertSame('50',$xpath->evaluate('string(//select[@id="stock-variant"]/option[@selected]/@value)'));
        self::assertSame('50',$xpath->evaluate('string(//form[@method="post"]/input[@name="variant_id"]/@value)'));
        self::assertSame('UAT20261023',$xpath->evaluate('string(//form[@method="post"]/input[@name="q"]/@value)'));
    }

    public function testVariantPickerPreservesFilterWithCspCompatibleChangeAndSubmitFallback():void
    {
        [$html,$report]=$this->render(['q'=>'UAT20261023','variant_id'=>51]);$xpath=$this->xpath($html);
        self::assertSame(51,$report['opening_variant']);
        self::assertSame('UAT20261023',$xpath->evaluate('string(//form[@id="stock-variant-picker"]/input[@name="q"]/@value)'));
        self::assertSame('get',$xpath->evaluate('string(//form[@id="stock-variant-picker"]/@method)'));
        self::assertSame('Vybrat variantu',$xpath->evaluate('string(//form[@id="stock-variant-picker"]/button)'));
        self::assertSame(0,$xpath->query('//*[@onchange]')->length);
        self::assertSame(1,$xpath->query('//script[@nonce="test-nonce"]')->length);
        self::assertStringContainsString("addEventListener('change'",$html);
        self::assertStringContainsString('this.form.requestSubmit()',$html);
        self::assertSame('51',$xpath->evaluate('string(//form[@method="post"]/input[@name="variant_id"]/@value)'));
    }

    public function testFilteredOutExplicitVariantDoesNotSilentlySelectAnotherTarget():void
    {
        [$html,$report]=$this->render(['q'=>'UAT20261023','variant_id'=>1]);$xpath=$this->xpath($html);
        self::assertSame(0,$report['opening_variant']);
        self::assertSame('',$xpath->evaluate('string(//select[@id="stock-variant"]/option[@selected]/@value)'));
        self::assertSame('0',$xpath->evaluate('string(//form[@method="post"]/input[@name="variant_id"]/@value)'));
    }
}
