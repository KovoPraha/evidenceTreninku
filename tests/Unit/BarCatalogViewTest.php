<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class BarCatalogViewTest extends TestCase
{
    public function testSharedHeaderReceivesPdoAndOperatorViewEscapesProductWithoutPrivateColumns(): void
    {
        $root = dirname(__DIR__, 2);
        $runtime = sys_get_temp_dir() . '/bar-view-' . bin2hex(random_bytes(8));
        mkdir($runtime, 0700);
        mkdir($runtime . '/includes', 0700);
        mkdir($runtime . '/includes/bar', 0700);
        copy($root . '/includes/bar/web.php', $runtime . '/includes/bar/web.php');
        copy($root . '/includes/bar/editor_view.php', $runtime . '/includes/bar/editor_view.php');
        file_put_contents($runtime . '/includes/bar/catalog.php', '<?php require_once ' . var_export($root . '/includes/bar/catalog.php', true) . ';');
        file_put_contents($runtime . '/hlavicka.php', '<?php if (!isset($pdo) || !$pdo instanceof PDO) { throw new RuntimeException("Header lost PDO"); } echo "<nav>SHARED HEADER</nav>";');
        $script = '<?php require ' . var_export($runtime . '/includes/bar/web.php', true) . ';'
            . 'function appUiAssets(): void {} function appUiUrl(string $path): string { return "/".$path; }'
            . '$pdo=new PDO("sqlite::memory:"); barRenderCatalog($pdo,[["id"=>1,"name"=>"<script>Unsafe</script>","description"=>"TEST",'
            . '"category"=>"TEST","price_minor"=>3500,"stock_stall"=>2,"stock_mode"=>"sledovat","sold_out"=>0,"is_composite"=>0,"image_id"=>null,'
            . '"purchase_minor"=>1234567,"stock_office"=>987654]],false);';
        file_put_contents($runtime . '/render.php', $script);
        try {
            $process = proc_open([PHP_BINARY, $runtime . '/render.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            fclose($pipes[0]);
            $html = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), (string)$error);
            self::assertStringContainsString('SHARED HEADER', $html);
            self::assertStringContainsString('&lt;script&gt;Unsafe&lt;/script&gt;', $html);
            self::assertStringNotContainsString('<script>Unsafe', $html);
            self::assertStringNotContainsString('Nákupní cena', $html);
            self::assertStringNotContainsString('Předsklad', $html);
            self::assertStringNotContainsString('987654', $html);
        } finally {
            foreach (['render.php', 'hlavicka.php', 'includes/bar/web.php', 'includes/bar/editor_view.php', 'includes/bar/catalog.php'] as $path) {
                unlink($runtime . '/' . $path);
            }
            rmdir($runtime . '/includes/bar');
            rmdir($runtime . '/includes');
            rmdir($runtime);
        }
    }
}
