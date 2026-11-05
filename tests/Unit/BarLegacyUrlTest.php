<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/bin/bar-legacy-url.php';

final class BarLegacyUrlTest extends TestCase
{
    private string $temporary;
    private string $root;

    protected function setUp(): void
    {
        $this->temporary = sys_get_temp_dir() . '/bar-url-' . bin2hex(random_bytes(8));
        $this->root = $this->temporary . '/bar.kovopraha.cz';
        foreach (['includes', 'admin', 'terminal', 'menu', 'public', 'img'] as $directory) {
            mkdir($this->root . '/' . $directory, 0700, true);
        }
        file_put_contents($this->root . '/includes/config.php', '<?php throw new RuntimeException("LOCALHOST bootstrap must never run");');
        file_put_contents($this->root . '/img/keep.bin', random_bytes(64));
        foreach (['terminal/api.php', 'terminal/index.php', 'terminal/uzavrit.php', 'admin/faktury_api.php',
            'admin/faktura_pdf.php', 'public/index.php', 'menu/index.php'] as $path) {
            file_put_contents($this->root . '/' . $path, '<?php throw new RuntimeException("LOCALHOST old writer must never run");');
        }
        for ($index = 0; $index < 16; $index++) {
            file_put_contents($this->root . '/admin/fixture' . $index . '.php', '<?php /* LOCALHOST unchanged source */');
        }
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->temporary, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->temporary);
    }

    public function testChangedSourceAbortsBeforeAnyEntryPointIsReplaced(): void
    {
        $plan = \barLegacyUrlInventory($this->root);
        self::assertSame('index.php', array_key_last($plan));
        self::assertNull($plan['index.php']['before']);
        file_put_contents($this->root . '/admin/fixture9.php', '<?php /* LOCALHOST concurrent edit */');
        try {
            \barLegacyUrlApply($this->root, $plan);
            self::fail('A concurrent source edit must stop the complete cutover.');
        } catch (\RuntimeException) {
            self::assertFileDoesNotExist($this->root . '/index.php');
            self::assertSame($plan['terminal/api.php']['before'], hash_file('sha256', $this->root . '/terminal/api.php'));
        }
    }

    public function testUnexpectedRootEntryPointStopsInventory(): void
    {
        file_put_contents($this->root . '/writer.php', '<?php /* LOCALHOST unknown writer */');
        $this->expectException(\RuntimeException::class);
        \barLegacyUrlInventory($this->root);
    }

    public function testTraversalCannotBecomeARedirectDestination(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        \barLegacyUrlTarget('admin/../../outside.php');
    }

    public function testRealHttpDropsOldQueriesAndRefusesEveryWriteAndLegacyApi(): void
    {
        $configHash = hash_file('sha256', $this->root . '/includes/config.php');
        $imageHash = hash_file('sha256', $this->root . '/img/keep.bin');
        \barLegacyUrlApply($this->root, \barLegacyUrlInventory($this->root));
        self::assertSame($configHash, hash_file('sha256', $this->root . '/includes/config.php'));
        self::assertSame($imageHash, hash_file('sha256', $this->root . '/img/keep.bin'));
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        self::assertIsResource($socket, $error);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $process = proc_open(
            [PHP_BINARY, '-S', $address, '-t', $this->root],
            [0 => ['pipe', 'r'], 1 => ['file', $this->temporary . '/server.log', 'a'], 2 => ['file', $this->temporary . '/server.log', 'a']],
            $pipes
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        try {
            $ready = false;
            for ($attempt = 0; $attempt < 100; $attempt++) {
                $connection = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
                if ($connection !== false) {
                    fclose($connection);
                    $ready = true;
                    break;
                }
                usleep(50000);
            }
            self::assertTrue($ready);
            foreach (['index.php' => '/bar/checkout.php', 'terminal/index.php' => '/bar/checkout.php',
                'terminal/uzavrit.php' => '/bar/cashbox.php', 'admin/faktura_pdf.php' => '/bar/invoices.php',
                'public/index.php' => '/bar/menu.php', 'menu/index.php' => '/bar/menu.php',
                'terminal/api.php' => '/bar/checkout.php', 'admin/faktury_api.php' => '/bar/invoices.php'] as $path => $target) {
                foreach (['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
                    $context = stream_context_create(['http' => ['method' => $method, 'ignore_errors' => true, 'follow_location' => 0,
                        'timeout' => 5, 'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                        'content' => $method === 'POST' ? 'action=sell&id=123&amount=50' : '']]);
                    $body = file_get_contents('http://' . $address . '/' . $path . '?id=123&token=LOCALHOST-secret&next=https://invalid.example', false, $context);
                    $headers = $http_response_header ?? [];
                    $gone = str_ends_with($path, 'api.php') || !in_array($method, ['GET', 'HEAD'], true);
                    self::assertStringContainsString($gone ? '410' : '302', $headers[0], $path . ' ' . $method);
                    self::assertContains('Cache-Control: no-store', $headers);
                    self::assertContains('Referrer-Policy: no-referrer', $headers);
                    if (!$gone) {
                        self::assertContains('Location: https://kis.kovopraha.cz' . $target, $headers);
                    } elseif ($method !== 'HEAD') {
                        self::assertSame(['error' => 'bar_moved_to_kis', 'url' => 'https://kis.kovopraha.cz' . $target], json_decode($body, true, 4, JSON_THROW_ON_ERROR));
                        self::assertFalse((bool)array_filter($headers, static fn (string $header): bool => str_starts_with(strtolower($header), 'location:')));
                    }
                    self::assertStringNotContainsString('LOCALHOST-secret', $body);
                }
            }
        } finally {
            proc_terminate($process);
            proc_close($process);
        }
    }
}
