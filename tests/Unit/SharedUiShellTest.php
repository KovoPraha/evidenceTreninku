<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SharedUiShellTest extends TestCase
{
    public function testEveryFirstPartyHtmlPhpPageUsesSharedUiFoundation(): void
    {
        $root = dirname(__DIR__, 2);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        $missing = [];

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
            if (preg_match('#^(vendor|tests|migrations|scripts)/#', $relative) === 1) {
                continue;
            }
            $source = (string)file_get_contents($path);
            if (stripos($source, '<head') === false) {
                continue;
            }
            if (!str_contains($source, 'hlavicka.php') && !str_contains($source, 'appUiAssets(')) {
                $missing[] = $relative;
            }
        }

        self::assertSame([], $missing, 'HTML pages without the shared UI foundation: ' . implode(', ', $missing));
    }

    public function testPublicPortalEntrypointsUseOneNavigation(): void
    {
        $root = dirname(__DIR__, 2);
        $expected = [
            'booking/eshop.php' => "publicShellNav('shop')",
            'booking/produkt.php' => "publicShellNav('shop')",
            'booking/treninky.php' => "publicShellNav('training')",
            'booking/krouzky.php' => "publicShellNav('clubs')",
            'booking/velodrom.php' => "publicShellNav('velodrome')",
            'booking/prihlaseni.php' => 'publicShellNav()',
            'booking/registrace.php' => 'publicShellNav()',
        ];

        foreach ($expected as $relative => $needle) {
            self::assertStringContainsString($needle, (string)file_get_contents($root . '/' . $relative), $relative);
        }
    }

    public function testSharedInteractionsAreSafeAndReusable(): void
    {
        $root = dirname(__DIR__, 2);
        $helper = (string)file_get_contents($root . '/includes/ui_shell.php');
        $javascript = (string)file_get_contents($root . '/assets/app-ui.js');
        $header = (string)file_get_contents($root . '/hlavicka.php');

        self::assertStringContainsString('assets/app-ui.css', $helper);
        self::assertStringContainsString('assets/app-ui.js', $helper);
        self::assertStringContainsString('text.textContent = String(message)', $javascript);
        self::assertStringContainsString("form.setAttribute('aria-busy', 'true')", $javascript);
        self::assertStringContainsString("form.dataset.appSubmitting = '1'", $javascript);
        self::assertStringNotContainsString('insertAdjacentHTML', $header);
    }
}
