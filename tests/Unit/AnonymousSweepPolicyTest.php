<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/AnonymousRouteInventory.php';
require_once dirname(__DIR__) . '/Support/AnonymousHttpPolicy.php';

final class AnonymousSweepPolicyTest extends TestCase
{
    public function testRedirectBodiesAreRejectedBeforeFollowingAnyHop(): void
    {
        $root = sys_get_temp_dir() . '/evidence-redirect-' . bin2hex(random_bytes(8));
        mkdir($root, 0700);
        $server = null;
        try {
            file_put_contents($root . '/router.php', <<<'PHP'
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$status = (int) ($_GET['status'] ?? 302);
$leak = $_GET['leak'] ?? '';
if ($path === '/alias.php') {
    header('Location: protected.php?leak=' . ($leak === 'second' ? 'yes' : ''), true, $status);
    if ($leak === 'first') {
        echo 'LEAKED PRIVATE DATA';
    }
    exit;
}
if ($path === '/protected.php') {
    header('Location: login.php', true, $status);
    if ($leak !== '') {
        echo 'LEAKED PRIVATE DATA';
    }
    exit;
}
echo 'PUBLIC LOGIN';
PHP);
            $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
            self::assertIsResource($socket, $error);
            $address = stream_socket_get_name($socket, false);
            fclose($socket);
            $server = proc_open(
                [PHP_BINARY, '-S', $address, $root . '/router.php'],
                [0 => ['pipe', 'r'], 1 => ['file', $root . '/server.log', 'a'], 2 => ['file', $root . '/server.log', 'a']],
                $pipes,
                $root
            );
            self::assertIsResource($server);
            fclose($pipes[0]);
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
            self::assertTrue($ready, 'Synthetic HTTP fixture did not start.');
            foreach (['GET', 'POST'] as $method) {
                foreach ([302, 303] as $status) {
                    $path = 'protected.php?status=' . $status;
                    self::assertTrue(anonymousProbeResponse('http://' . $address, $path, $method)['denied']);
                    $result = anonymousProbeResponse('http://' . $address, $path . '&leak=yes', $method);
                    self::assertFalse($result['denied']);
                    self::assertTrue($result['redirectBody']);
                }
                foreach ([301, 302, 303, 307, 308] as $status) {
                    $path = 'alias.php?status=' . $status;
                    self::assertTrue(anonymousProbeResponse('http://' . $address, $path, $method)['denied']);
                    foreach (['first', 'second'] as $hop) {
                        $result = anonymousProbeResponse('http://' . $address, $path . '&leak=' . $hop, $method);
                        self::assertFalse($result['denied'], $method . ' alias ' . $status . ' / ' . $hop);
                        self::assertTrue($result['redirectBody']);
                    }
                }
            }
        } finally {
            if (is_resource($server)) {
                proc_terminate($server);
                proc_close($server);
            }
            foreach (['router.php', 'server.log'] as $file) {
                if (is_file($root . '/' . $file)) {
                    unlink($root . '/' . $file);
                }
            }
            rmdir($root);
        }
    }

    public function testMissingResourceAndMethodRejectionDoNotProveAuthentication(): void
    {
        foreach ([200, 400, 404, 405, 500] as $status) {
            self::assertFalse(anonymousResponseProvesDenial('new_detail.php', $status, '', ''));
        }
        self::assertTrue(anonymousResponseProvesDenial('new_detail.php', 403, '', ''));
        self::assertTrue(anonymousResponseProvesDenial('new_detail.php', 302, "Location: login.php\r\n", ''));
        self::assertTrue(anonymousResponseProvesDenial('booking/detail.php', 303, "Location: prihlaseni.php?next=x\r\n", ''));
        self::assertFalse(anonymousResponseProvesDenial('new_detail.php', 302, "Location: https://evil.test/login.php\r\n", ''));
        self::assertFalse(anonymousResponseProvesDenial('new_detail.php', 302, "Location: fake-login.php\r\n", ''));
        self::assertFalse(anonymousResponseProvesDenial('new_detail.php', 302, "Location: //evil.test/login.php\r\n", ''));
        self::assertTrue(anonymousResponseProvesDenial('cron_upominky.php', 404, '', ''));
        self::assertTrue(anonymousResponseProvesDenial('diagnostika_site_admin.php', 404, '', ''));
        self::assertSame('export_csv.php?id=1', anonymousLocalRedirect('export.php', "Location: export_csv.php?id=1\r\n"));
        self::assertSame(['', '?id=1'], anonymousRouteQueries('new_detail.php'));
        self::assertCount(4, anonymousRouteQueries('private_download.php'));
    }

    public function testInventoryUsesTrackedFilesAndSkipsAllDotComponents(): void
    {
        $root = sys_get_temp_dir() . '/evidence-inventory-' . bin2hex(random_bytes(8));
        mkdir($root, 0700);
        try {
            foreach (['new.php', '.php-cs-fixer.dist.php', 'assets/.hidden/probe.php', 'booking/free_event_registration_form.php'] as $path) {
                if (!is_dir(dirname($root . '/' . $path))) {
                    mkdir(dirname($root . '/' . $path), 0700, true);
                }
                file_put_contents($root . '/' . $path, '<?php');
            }
            $this->git($root, ['init', '--quiet']);
            $this->git($root, ['add', '--', 'new.php', '.php-cs-fixer.dist.php', 'assets/.hidden/probe.php', 'booking/free_event_registration_form.php']);
            file_put_contents($root . '/untracked-cache.php', '<?php');
            self::assertSame(['new.php'], anonymousEntryPoints($root));
        } finally {
            $children = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($children as $child) {
                if ($child->isFile()) {
                    chmod($child->getPathname(), 0600);
                }
                $child->isDir() ? rmdir($child->getPathname()) : unlink($child->getPathname());
            }
            rmdir($root);
        }
    }

    private function git(string $root, array $arguments): void
    {
        $process = proc_open(array_merge(['git', '-C', $root], $arguments), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $error);
    }
}
