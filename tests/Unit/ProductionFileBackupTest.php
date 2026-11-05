<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/bin/production-file-backup.php';

final class ProductionFileBackupTest extends TestCase
{
    public function testPrivateArchiveIncludesConfigurationAndNestedAttachmentsAndRefusesOverwrite(): void
    {
        $root = sys_get_temp_dir() . '/kis-file-backup-' . bin2hex(random_bytes(8));
        mkdir($root . '/app', 0700, true);
        mkdir($root . '/private/photos', 0700, true);
        file_put_contents($root . '/app/config.php', '<?php /* LOCALHOST secret fixture */');
        file_put_contents($root . '/private/photos/test.bin', random_bytes(1234));
        try {
            $result = \productionFileBackup(['app' => $root . '/app', 'private' => $root . '/private'], $root . '/backup.zip');
            self::assertSame(2, $result['files']);
            self::assertSame(hash_file('sha256', $root . '/backup.zip'), $result['sha256']);
            $zip = new \ZipArchive();
            self::assertTrue($zip->open($root . '/backup.zip'));
            $manifest = json_decode($zip->getFromName('manifest.json'), true, 16, JSON_THROW_ON_ERROR);
            foreach ($manifest as $name => $file) {
                $bytes = $zip->getFromName($name);
                self::assertSame($file['bytes'], strlen($bytes));
                self::assertSame($file['sha256'], hash('sha256', $bytes));
            }
            $zip->close();
            try {
                \productionFileBackup(['app' => $root . '/app'], $root . '/app/recursive.zip');
                self::fail('Backup inside its source must be rejected.');
            } catch (\RuntimeException) {
                self::assertFileDoesNotExist($root . '/app/recursive.zip');
            }
            $this->expectException(\RuntimeException::class);
            \productionFileBackup(['app' => $root . '/app'], $root . '/backup.zip');
        } finally {
            foreach (['/backup.zip', '/app/config.php', '/private/photos/test.bin'] as $file) {
                if (is_file($root . $file)) {
                    unlink($root . $file);
                }
            }
            rmdir($root . '/private/photos');
            rmdir($root . '/private');
            rmdir($root . '/app');
            rmdir($root);
        }
    }
}
