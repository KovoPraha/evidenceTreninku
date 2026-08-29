<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/private_storage.php';

final class PrivateStorageTest extends TestCase
{
    private string $root;
    private string|false $previous;

    protected function setUp(): void
    {
        $this->previous = getenv('APP_PRIVATE_STORAGE_ROOT');
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'evidence-private-' . bin2hex(random_bytes(6));
        putenv('APP_PRIVATE_STORAGE_ROOT=' . $this->root);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($this->root);
        }
        $this->previous === false
            ? putenv('APP_PRIVATE_STORAGE_ROOT')
            : putenv('APP_PRIVATE_STORAGE_ROOT=' . $this->previous);
    }

    public function testStoresAllowedImageOutsideWebrootUnderOpaqueKey(): void
    {
        $source = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'evidence-source-' . bin2hex(random_bytes(6)) . '.png';
        file_put_contents($source, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        ));

        $key = \privateStorageStore($source, PRIVATE_STORAGE_RECEIPTS, false);

        self::assertMatchesRegularExpression('~^private://receipts/[a-f0-9]{32}\.png$~', $key);
        self::assertFileExists((string)\privateStorageResolve($key));
        self::assertFileDoesNotExist($source);
    }

    public function testRejectsExecutableContent(): void
    {
        $source = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'evidence-source-' . bin2hex(random_bytes(6)) . '.php';
        file_put_contents($source, '<?php echo "unsafe";');
        try {
            $this->expectException(\RuntimeException::class);
            \privateStorageStore($source, PRIVATE_STORAGE_STRESS_TESTS, false);
        } finally {
            if (is_file($source)) {
                unlink($source);
            }
        }
    }

    public function testAthletePhotoIsReencodedWithoutOriginalMetadata(): void
    {
        $source = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'evidence-athlete-' . bin2hex(random_bytes(6)) . '.png';
        file_put_contents($source, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        ));

        $stored = \privateStorageStoreAthletePhoto($source, false);

        self::assertMatchesRegularExpression('~^private://athlete-photos/[a-f0-9]{32}\.jpg$~', $stored['storage_key']);
        self::assertSame('image/jpeg', $stored['mime_type']);
        self::assertSame(1, $stored['width_px']);
        self::assertSame(1, $stored['height_px']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $stored['sha256_hex']);
        self::assertFileExists((string)\privateStorageResolve($stored['storage_key']));
        self::assertFileDoesNotExist($source);
    }

    public function testUciTemplateUsesDedicatedPrivateXlsxCategory(): void
    {
        $source = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'evidence-uci-' . bin2hex(random_bytes(6)) . '.xlsx';
        $archive = new \ZipArchive();
        self::assertTrue($archive->open($source, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $archive->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $archive->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>');
        $archive->close();

        $key = \privateStorageStoreUciTemp($source, 'UCI-template.xlsx', false);

        self::assertMatchesRegularExpression('~^private://uci-temp/[a-f0-9]{32}\.xlsx$~', $key);
        self::assertFileExists((string)\privateStorageResolve($key));
        self::assertFileDoesNotExist($source);
        \privateStorageDeleteUciTemp($key);
        self::assertNull(\privateStorageResolve($key));
    }
}
