<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/AnonymousRouteInventory.php';
require_once dirname(__DIR__) . '/Support/AnonymousHttpPolicy.php';

final class AnonymousSweepPolicyTest extends TestCase
{
    public function testMissingResourceAndMethodRejectionDoNotProveAuthentication(): void
    {
        foreach ([200, 400, 404, 405, 500] as $status) {
            self::assertFalse(anonymousResponseProvesDenial('new_detail.php', $status, ''));
        }
        self::assertTrue(anonymousResponseProvesDenial('new_detail.php', 403, ''));
        self::assertTrue(anonymousResponseProvesDenial('new_detail.php', 302, "Location: login.php\r\n"));
        self::assertTrue(anonymousResponseProvesDenial('booking/detail.php', 303, "Location: prihlaseni.php?next=x\r\n"));
        self::assertFalse(anonymousResponseProvesDenial('new_detail.php', 302, "Location: https://evil.test/login.php\r\n"));
        self::assertFalse(anonymousResponseProvesDenial('new_detail.php', 302, "Location: fake-login.php\r\n"));
        self::assertFalse(anonymousResponseProvesDenial('new_detail.php', 302, "Location: //evil.test/login.php\r\n"));
        self::assertTrue(anonymousResponseProvesDenial('cron_upominky.php', 404, ''));
        self::assertTrue(anonymousResponseProvesDenial('diagnostika_site_admin.php', 404, ''));
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
