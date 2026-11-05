<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/bar/transfer_package.php';
require_once dirname(__DIR__, 2) . '/bin/bar-cutover-prepare.php';

final class BarLegacyImageSnapshotTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/bar-legacy-images-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/img', 0700, true);
        $image = imagecreatetruecolor(8, 4);
        imagepng($image, $this->root . '/img/Čaj 1 (malý).png');
        imagedestroy($image);
    }

    protected function tearDown(): void
    {
        foreach (['img', 'snapshot'] as $part) {
            if (is_dir($this->root . '/' . $part)) {
                foreach (new \FilesystemIterator($this->root . '/' . $part) as $file) {
                    unlink($file->getPathname());
                }
                rmdir($this->root . '/' . $part);
            }
        }
        rmdir($this->root);
        unset($GLOBALS['bar_source_image_meta']);
    }

    public static function localReferences(): array
    {
        return [['Čaj 1 (malý).png'], ['img/Čaj 1 (malý).png'], ['./img/Čaj 1 (malý).png'], ['/img/Čaj 1 (malý).png'],
            ['../img/Čaj 1 (malý).png'], ['https://bar.kovopraha.cz/img/' . rawurlencode('Čaj 1 (malý).png')]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('localReferences')]
    public function testSafeLegacyReferencesKeepExactBytesAndPrivateProvenanceWithoutChangingSource(string $reference): void
    {
        $source = ['products' => [['id' => 91, 'obrazek' => $reference], ['id' => 92, 'obrazek' => $reference]]];
        $copy = \barCutoverSnapshotImages($source, $this->root . '/img', $this->root . '/snapshot');
        self::assertSame($reference, $source['products'][0]['obrazek']);
        $name = $copy['source']['products'][0]['obrazek'];
        self::assertMatchesRegularExpression('/^source-[a-f0-9]{64}\.png$/D', $name);
        self::assertSame(file_get_contents($this->root . '/img/Čaj 1 (malý).png'), file_get_contents($this->root . '/snapshot/' . $name));
        self::assertSame($reference, $copy['map'][0]['source_reference']);
        self::assertSame($name, $copy['source']['products'][1]['obrazek']);
        self::assertCount(1, glob($this->root . '/snapshot/*'));
        self::assertSame($copy, \barCutoverSnapshotImages($source, $this->root . '/img', $this->root . '/snapshot'));
    }

    public static function rejectedReferences(): array
    {
        return [['../secret.png'], ['img/../../secret.png'], ['/tmp/secret.png'], ['https://outside.example/img/test.png'],
            ['https://user@bar.kovopraha.cz/img/test.png'], ['https://bar.kovopraha.cz/img/test.png?token=LOCALHOST-secret'],
            ['https://bar.kovopraha.cz/img/%2e%2e%2fsecret.png'], ['Čaj 1 (malý).png:stream']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rejectedReferences')]
    public function testUnknownPathsExternalUrlsAndCredentialBearingReferencesAreRejectedWithoutLeakingThem(string $reference): void
    {
        try {
            \barCutoverSnapshotImages(['products' => [['id' => 91, 'obrazek' => $reference]]], $this->root . '/img', $this->root . '/snapshot');
            self::fail('Unsafe reference accepted.');
        } catch (\InvalidArgumentException) {
            self::assertStringNotContainsString($reference, json_encode($GLOBALS['bar_source_image_meta'], JSON_THROW_ON_ERROR));
            self::assertCount(0, glob($this->root . '/snapshot/*'));
        }
    }
}
