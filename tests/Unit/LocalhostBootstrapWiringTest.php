<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class LocalhostBootstrapWiringTest extends TestCase
{
    public function testPortableBootstrapUsesOnlyVersionedSyntheticInputs(): void
    {
        $root = dirname(__DIR__, 2);
        $setup = (string)file_get_contents($root . '/bin/setup-localhost-testing.ps1');
        $config = (string)file_get_contents($root . '/config.example.php');

        self::assertStringContainsString('database\\local-demo-schema.sql', $setup);
        self::assertStringContainsString('tests\\fixtures\\shoptet\\products-valid.csv', $setup);
        self::assertStringContainsString('Copy-Item -LiteralPath $configExample -Destination $config', $setup);
        self::assertStringContainsString('bin\\migrate.php', $setup);
        self::assertStringContainsString('bin\\seed-local-demo.php', $setup);
        self::assertStringContainsString('EVIDENCE_LOCAL_DB_HOST', $setup . $config);
        self::assertStringContainsString('127.0.0.1;port=3308', $config);
        self::assertStringNotContainsString('DROP DATABASE', $setup);
        self::assertStringNotContainsString('kovoprahacz09.sql', $setup);
    }

    public function testVersionedSchemaContainsNoApplicationRowsOrLocalIdentity(): void
    {
        $root = dirname(__DIR__, 2);
        $schema = (string)file_get_contents($root . '/database/local-demo-schema.sql');

        self::assertStringContainsString('CREATE TABLE `sportovci`', $schema);
        self::assertStringContainsString('CREATE TABLE `evidence_schema_migrations`', $schema);
        self::assertStringNotContainsString('DEFINER=', $schema);
        self::assertDoesNotMatchRegularExpression('/AUTO_INCREMENT=(?!1\b)\d+/', $schema);
        self::assertStringNotContainsString('localhost-admin', $schema);
        self::assertStringNotContainsString('rodic@localhost.test', $schema);
        self::assertStringNotContainsString('INSERT INTO `', $schema);
    }

    public function testDatabaseBootstrapTreesAreNotPubliclyRoutable(): void
    {
        $root = dirname(__DIR__, 2);
        $htaccess = (string)file_get_contents($root . '/.htaccess');

        self::assertStringContainsString('^(?:bin|database|docs|migrations|tests|var|vendor)', $htaccess);
        self::assertStringContainsString('\\.sql$', $htaccess);
    }
}
