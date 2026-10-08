<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RuntimeSchemaWriteWiringTest extends TestCase
{
    public function testWebRequestsDoNotCreateSchemaOrSeedPermissions(): void
    {
        $root = dirname(__DIR__, 2);
        $email = (string)file_get_contents($root . '/odeslat_emaily.php');
        $descriptions = (string)file_get_contents($root . '/prehled_popisu.php');
        self::assertStringNotContainsString('CREATE TABLE', $email);
        self::assertStringNotContainsString('INSERT IGNORE INTO opravneni', $descriptions);
        $migration = require $root . '/migrations/20261008100000_runtime_schema_writes.php';
        self::assertSame('20261008100000_runtime_schema_writes', $migration['id']);
    }
}
