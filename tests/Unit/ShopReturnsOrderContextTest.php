<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

/** Execute the actual GET page against disposable read fixtures and inert authentication. */
final class ShopReturnsOrderContextTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/kis-return-context-'.bin2hex(random_bytes(6));
        mkdir($this->root.'/includes', 0700, true);
        $app = dirname(__DIR__, 2);
        copy($app.'/shop_returns_admin.php', $this->root.'/shop_returns_admin.php');
        foreach (['staff_record_list.php', 'shop_return_admin_read.php'] as $name) {
            if (is_file($app.'/includes/'.$name)) {
                copy($app.'/includes/'.$name, $this->root.'/includes/'.$name);
            }
        }
        foreach (['includes/staff_workspaces.php', 'hlavicka.php'] as $name) {
            file_put_contents($this->root.'/'.$name, '<?php');
        }
        file_put_contents($this->root.'/csrf_helper.php', '<?php function csrf_field(): string { return ""; } function csrf_verify(string $token): bool { return $token === "fixture-valid"; }');
        file_put_contents($this->root.'/includes/ui_shell.php', '<?php function appUiAssets(): void {}');
        file_put_contents($this->root.'/includes/shop_returns.php', <<<'PHP'
<?php
function shopReturnIssueSource(PDO $pdo, int $id): array {
    $q=$pdo->prepare('SELECT * FROM shop_stock_movements WHERE id=?');$q->execute([$id]);
    return $q->fetch(PDO::FETCH_ASSOC)+['product_name_snapshot'=>'Fixture shirt','sku_snapshot'=>'LOCAL'];
}
function shopReturnOpen(mixed ...$args): array { $GLOBALS['mutation_calls']++; throw new LogicException('Unexpected domain write.'); }
function shopReturnReceive(mixed ...$args): array { $GLOBALS['mutation_calls']++; throw new LogicException('Unexpected domain write.'); }
PHP);
        file_put_contents($this->root.'/includes/init.php', <<<'PHP'
<?php
http_response_code(200);
$_GET=json_decode($argv[1],true,flags:JSON_THROW_ON_ERROR);$_POST=json_decode($argv[3],true,flags:JSON_THROW_ON_ERROR);
$_SESSION=['trener_id'=>7];$_SERVER['REQUEST_METHOD']=$_POST===[]?'GET':'POST';$GLOBALS['mutation_calls']=0;
function staffActivePosition(): string { return $GLOBALS['argv'][2]; }
$pdo=new PDO('sqlite:'.__DIR__.'/../fixture.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
register_shutdown_function(static function(): void { fwrite(STDERR,json_encode(['status'=>http_response_code(),'mutation_calls'=>$GLOBALS['mutation_calls']])); });
PHP);
        $pdo = new PDO('sqlite:'.$this->root.'/fixture.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT,aktivni INT);INSERT INTO treneri VALUES(7,'LOCAL operator',1);
            CREATE TABLE staff_user_positions(trainer_id INT,position_code TEXT);INSERT INTO staff_user_positions VALUES(7,'order_operator');
            CREATE TABLE staff_superadmins(trainer_id INT);
            CREATE TABLE shop_orders(id INTEGER PRIMARY KEY,public_code TEXT);INSERT INTO shop_orders VALUES(1,'LOCAL-ORDER-A'),(2,'LOCAL-ORDER-B'),(3,'LOCAL-EMPTY');
            CREATE TABLE shop_stock_movements(id INTEGER PRIMARY KEY,kind TEXT,order_id INT,order_item_id INT,physical_delta INT,effective_on TEXT);
            CREATE TABLE shop_stock_balances(variant_id INT,mode TEXT,physical_quantity INT,reserved_quantity INT,blocked_quantity INT);
            CREATE TABLE shop_variants(id INT,sku TEXT,product_id INT);CREATE TABLE shop_products(id INT,name TEXT);");
        $migration = require $app.'/migrations/20261022232000_shop_returns.php';
        $migration['up']($pdo);
        $pdo->beginTransaction();
        $q = $pdo->prepare('INSERT INTO shop_return_cases(id,issue_movement_id,order_id,order_item_id,variant_id,quantity,request_type,'
            .'owner_trainer_id,next_due_on,contact_path,customer_request,policy_evidence,original_snapshot_json,original_amount_minor,'
            ."currency,request_key,input_hash,created_by) VALUES(?,1,1,1,1,1,'claim',7,'2026-11-12','LOCAL contact',?,'LOCAL policy',?,5000,'CZK',?,'fixture',7)");
        for ($i = 1;$i <= 120;$i++) {
            $q->execute([$i,'LOCAL-A-'.str_pad((string)$i, 3, '0', STR_PAD_LEFT),'{"label":"Fixture shirt","sku":"LOCAL"}','case-'.$i]);
        }
        $q->execute([121,'FOREIGN_ORDER_REQUEST','{"label":"Other shirt","sku":"OTHER"}','case-121']);
        $pdo->exec('UPDATE shop_return_cases SET order_id=2 WHERE id=121');
        $q = $pdo->prepare("INSERT INTO shop_stock_movements VALUES(?,'issue',?,1,-1,'2026-11-05')");
        for ($i = 1;$i <= 202;$i++) {
            $q->execute([$i,$i === 1 ? 1 : 2]);
        }
        $pdo->commit();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root.'/includes/*') ?: [] as $file) {
            unlink($file);
        }
        foreach (glob($this->root.'/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->root.'/includes');
        rmdir($this->root);
    }

    private function render(array $query, string $position = 'order_operator', array $post = []): array
    {
        $process = proc_open(
            [PHP_BINARY,$this->root.'/shop_returns_admin.php',json_encode($query, JSON_THROW_ON_ERROR),$position,json_encode($post, JSON_THROW_ON_ERROR)],
            [0 => ['pipe','r'],1 => ['pipe','w'],2 => ['pipe','w']],
            $pipes
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $body = stream_get_contents($pipes[1]);
        $report = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $report);
        return [$body,json_decode($report, true, flags:JSON_THROW_ON_ERROR)];
    }

    public function testOrderLinkShowsOnlyItsCasesAndItsOldIssue(): void
    {
        [$body,$report] = $this->render(['order_id' => '1']);
        self::assertSame(200, $report['status']);
        self::assertFalse(str_contains($body, 'FOREIGN_ORDER_REQUEST'), 'Other order must not be listed.');
        self::assertTrue(str_contains($body, 'Objednávka LOCAL-ORDER-A'), 'Order context must be visible.');
        self::assertTrue(str_contains($body, 'eshop_fulfillment_admin.php?order_id=1'), 'Back link must retain the order.');
        $dom = $this->dom($body);
        self::assertSame(1, $dom->query('//select[@name="issue_movement_id"]/option[@value="1"]')->length);
        self::assertSame(2, $dom->query('//select[@name="issue_movement_id"]/option')->length);
        self::assertSame(25, $dom->query('//article')->length);
        self::assertSame($dom->query('//form')->length, $dom->query('//form/input[@name="order_id" and @value="1"]')->length);
    }

    public function testOldestCasesRemainReachableAndPageLinksRetainOrder(): void
    {
        [$body] = $this->render(['order_id' => '1','return_page' => '5']);
        self::assertTrue(str_contains($body, 'Nalezeno: 120 · strana 5 z 5'), 'All 120 scoped cases must be pageable.');
        self::assertTrue(str_contains($body, 'LOCAL-A-001'), 'Oldest case must remain reachable.');
        self::assertSame(20, $this->dom($body)->query('//article')->length);
        self::assertTrue(str_contains($body, 'order_id=1&amp;return_page=4'), 'Page link must retain order context.');
        [$global] = $this->render(['return_page' => '5']);
        self::assertTrue(str_contains($global, 'Nalezeno: 121 · strana 5 z 5'), 'Global list must be complete.');
        self::assertSame(21, $this->dom($global)->query('//article')->length);
    }

    public function testInvalidAndMissingOrderCannotFallBackToAllCases(): void
    {
        foreach (['0','', 'garbage',['1']] as $id) {
            [$body,$report] = $this->render(['order_id' => $id]);
            self::assertSame(422, $report['status']);
            self::assertFalse(str_contains($body, 'FOREIGN_ORDER_REQUEST'));
        }
        [$body,$report] = $this->render(['order_id' => '999999']);
        self::assertSame(404, $report['status']);
        self::assertFalse(str_contains($body, 'FOREIGN_ORDER_REQUEST'));
    }

    public function testEmptyOrderAndRoleBoundary(): void
    {
        [$body,$report] = $this->render(['order_id' => '3']);
        self::assertSame(200, $report['status']);
        self::assertTrue(str_contains($body, 'Pro tuto objednávku není evidovaný žádný případ.'), 'Empty order must have a clear empty state.');
        self::assertSame(0, $this->dom($body)->query('//article')->length);
        [$body,$report] = $this->render(['order_id' => '1'], 'coach');
        self::assertSame(403, $report['status']);
        self::assertStringNotContainsString('LOCAL-A-001', $body);
    }

    public function testValidCsrfCannotDispatchAWriteForAnotherOrder(): void
    {
        $base = ['csrf_token' => 'fixture-valid','confirmed' => '1','reason' => 'LOCAL fixture evidence',
            'request_key' => 'fixture-key-123','action' => 'receive','case_id' => 121,'revision' => 1,'ownership' => 'owned','occurred_on' => '2026-11-05'];
        foreach ([
            $base,
            ['order_id' => 2] + $base,
            ['case_id' => [121]] + $base,
            ['action' => 'open','issue_movement_id' => 202] + $base,
        ] as $post) {
            [$body, $report] = $this->render(['order_id' => 1], 'order_operator', $post);
            self::assertSame(422, $report['status']);
            self::assertSame(0, $report['mutation_calls']);
        }
    }

    private function dom(string $html): \DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return new \DOMXPath($doc);
    }
}
