<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/tools/build-ui-asset-versions.php';

final class UiAssetManifestTest extends TestCase
{
    public function testCommittedVersionsBindEveryPayloadByte(): void
    {
        $root = dirname(__DIR__, 2);
        $expected = \uiAssetVersionManifest($root);
        self::assertSame($expected, str_replace("\r\n", "\n", (string) file_get_contents($root . '/includes/ui_asset_versions.php')));
        $versions = require $root . '/includes/ui_asset_versions.php';
        self::assertCount(5, $versions);
        foreach (\uiAssetVersionPaths() as $path) {
            self::assertSame(hash_file('sha256', $root . '/' . $path), $versions[$path]);
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $versions[$path]);
        }
    }

    public function testMetadataOnlyReleaseKeepsVersionsButChangedContentInvalidatesOnlyItsAsset(): void
    {
        $root = sys_get_temp_dir() . '/kis-ui-manifest-test-' . bin2hex(random_bytes(8));
        mkdir($root);
        mkdir($root . '/includes');
        try {
            foreach (\uiAssetVersionPaths() as $path) {
                $directory = dirname($root . '/' . $path);
                if (!is_dir($directory)) {
                    mkdir($directory, 0700, true);
                }
                file_put_contents($root . '/' . $path, '/* owned fixture ' . $path . ' */');
                touch($root . '/' . $path, 1794302581);
            }
            $before = \uiAssetVersionManifest($root);
            foreach (\uiAssetVersionPaths() as $path) {
                touch($root . '/' . $path, 1794311532);
            }
            clearstatcache();
            self::assertSame($before, \uiAssetVersionManifest($root));
            file_put_contents($root . '/includes/ui_asset_versions.php', $before);
            $old = require $root . '/includes/ui_asset_versions.php';
            file_put_contents($root . '/assets/app-ui.css', "\n/* actual new content */", FILE_APPEND);
            $after = \uiAssetVersionManifest($root);
            self::assertNotSame($before, $after);
            file_put_contents($root . '/includes/ui_asset_versions.php', $after);
            $new = require $root . '/includes/ui_asset_versions.php';
            self::assertSame(['assets/app-ui.css'], array_keys(array_diff_assoc($new, $old)));
        } finally {
            foreach (\uiAssetVersionPaths() as $path) {
                if (is_file($root . '/' . $path)) {
                    unlink($root . '/' . $path);
                }
            }
            if (is_file($root . '/includes/ui_asset_versions.php')) {
                unlink($root . '/includes/ui_asset_versions.php');
            }
            rmdir($root . '/includes');
            // All directories below belong to the one freshly created test root.
            foreach (['assets/vendor/bootstrap-icons/font', 'assets/vendor/bootstrap-icons', 'assets/vendor/bootstrap',
                'assets/vendor', 'assets'] as $path) {
                rmdir($root . '/' . $path);
            }
            rmdir($root);
        }
    }

    public function testShellEmitsEachManifestUrlOnceAcrossRepeatedCalls(): void
    {
        $code = <<<'PHP'
        define('APP_BASE_URL','http://localhost');
        require 'includes/ui_shell.php';
        ob_start();appUiAssets();appUiAssets();$html=ob_get_clean();
        preg_match_all('~(?:href|src)="([^\"]+)"~',$html,$m);
        echo json_encode($m[1],JSON_THROW_ON_ERROR);
        PHP;
        $process = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => STDERR], $pipes, dirname(__DIR__, 2));
        self::assertIsResource($process);
        $urls = json_decode((string) stream_get_contents($pipes[1]), true, 512, JSON_THROW_ON_ERROR);
        fclose($pipes[1]);
        self::assertSame(0, proc_close($process));
        self::assertCount(5, $urls);
        $versions = require dirname(__DIR__, 2) . '/includes/ui_asset_versions.php';
        $actual = [];
        foreach ($urls as $url) {
            $actual[ltrim((string) parse_url($url, PHP_URL_PATH), '/')] = substr((string) parse_url($url, PHP_URL_QUERY), 2);
        }
        ksort($actual);
        ksort($versions);
        self::assertSame($versions, $actual);
    }
}
