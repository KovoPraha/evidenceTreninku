<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class KisSourceArchiveWiringTest extends TestCase
{
    public function testCliIsLocalhostDryRunByDefaultAndRequiresExplicitWrite(): void
    {
        $source = (string)file_get_contents(dirname(__DIR__, 2) . '/bin/kis-archive-source.php');
        self::assertStringContainsString("['localhost', '127.0.0.1']", $source);
        self::assertStringContainsString("JE_LOKALNE !== true", $source);
        self::assertStringContainsString("'status' => 'dry_run'", $source);
        self::assertStringContainsString("'writes' => 0", $source);
        self::assertStringContainsString("confirm-archive", $source);
        self::assertStringNotContainsString('file_get_contents((string)$args[\'input\'])', $source);
    }

    public function testArchiveServiceUsesExclusiveFileAndHashVerification(): void
    {
        $source = (string)file_get_contents(dirname(__DIR__, 2) . '/includes/kis_source_archive.php');
        foreach (['x+b', "hash_file('sha256'", 'hash_equals', 'is_link', 'KIS_SOURCE_ARCHIVE_MAX_BYTES', 'kisSourcePathWithin'] as $needle) {
            self::assertStringContainsString($needle, $source);
        }
    }
}
