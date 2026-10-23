<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Run the actual page with inert dependencies: no application bootstrap or database. */
final class ShopFulfillmentLandingTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/kis-fulfillment-page-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/includes', 0700, true);
        copy(dirname(__DIR__, 2) . '/eshop_fulfillment_admin.php', $this->root . '/eshop_fulfillment_admin.php');
        foreach (['csrf_helper.php', 'includes/shop_checkout.php', 'hlavicka.php'] as $file) {
            file_put_contents($this->root . '/' . $file, '<?php');
        }
        file_put_contents($this->root . '/includes/ui_shell.php', '<?php function appUiAssets(): void {}');
        file_put_contents($this->root . '/includes/init.php', <<<'PHP'
<?php
http_response_code(200);
$_GET = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
$_POST = [];
$_SESSION = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$GLOBALS['queries'] = [];
register_shutdown_function(static function (): void {
    fwrite(STDERR, json_encode(['status'=>http_response_code(), 'queries'=>$GLOBALS['queries']]));
});
function staffRequireActivePosition(string $position): void {
    if ($position !== 'order_operator' || $GLOBALS['argv'][2] !== 'order_operator') {
        http_response_code(403);
        exit('Forbidden');
    }
}
$pdo = new class {
    public function prepare(string $sql): object {
        if ($sql !== 'SELECT * FROM shop_orders WHERE id=?') throw new RuntimeException('Unexpected query');
        return new class {
            public function execute(array $parameters): void { $GLOBALS['queries'][] = $parameters; }
            public function fetch(int $mode): false { return false; }
        };
    }
};
PHP);
    }

    protected function tearDown(): void
    {
        foreach (['includes/init.php', 'includes/ui_shell.php', 'includes/shop_checkout.php', 'csrf_helper.php', 'hlavicka.php', 'eshop_fulfillment_admin.php'] as $file) {
            unlink($this->root . '/' . $file);
        }
        rmdir($this->root . '/includes');
        rmdir($this->root);
    }

    private function render(array $query, string $position = 'order_operator'): array
    {
        $process = proc_open([PHP_BINARY, $this->root . '/eshop_fulfillment_admin.php', json_encode($query, JSON_THROW_ON_ERROR), $position],
            [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $html = stream_get_contents($pipes[1]);
        $report = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $report);
        return [$html, json_decode($report, true, flags: JSON_THROW_ON_ERROR)];
    }

    public function testWorkspaceEntryOffersOrderSelectionWithoutQueryingOrWritingOrders(): void
    {
        [$html, $report] = $this->render([]);
        self::assertSame(200, $report['status']);
        self::assertSame([], $report['queries']);
        self::assertStringContainsString('<h1>Položkové výdeje</h1>', $html);
        self::assertStringContainsString('href="eshop_orders_admin.php"', $html);
        self::assertStringContainsString('Položkový výdej a doručení', $html);
        self::assertStringNotContainsString('<form', $html);
    }

    public function testExplicitMissingOrderRemainsNotFoundInsteadOfShowingLanding(): void
    {
        foreach (['0', '', 'invalid', '987654321'] as $id) {
            [$html, $report] = $this->render(['order_id'=>$id]);
            self::assertSame(404, $report['status']);
            self::assertSame([[(int)$id]], $report['queries']);
            self::assertSame('Objednávka nebyla nalezena.', $html);
        }
    }

    public function testLandingDoesNotBypassTheExistingPositionGuard(): void
    {
        [$html, $report] = $this->render([], 'coach');
        self::assertSame(403, $report['status']);
        self::assertSame([], $report['queries']);
        self::assertSame('Forbidden', $html);
    }
}
