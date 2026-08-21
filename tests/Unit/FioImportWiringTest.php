<?php
declare(strict_types=1);
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;

final class FioImportWiringTest extends TestCase
{
    public function testFioImportIsCliOnlyReadOnlyAndVisibleToAdmin():void
    {
        $root=dirname(__DIR__,2);$cli=(string)file_get_contents($root.'/bin/fio-import.php');$service=(string)file_get_contents($root.'/includes/fio_readonly_import.php');
        self::assertStringContainsString("PHP_SAPI !== 'cli'",$cli);self::assertStringContainsString('/v1/rest/periods/',$service);self::assertStringNotContainsString('/v1/rest/last/',$service);
        self::assertStringNotContainsString('UPDATE payments',$service);self::assertStringNotContainsString('UPDATE shop_orders',$service);self::assertStringContainsString('eshop_fio_admin.php',(string)file_get_contents($root.'/includes/staff_workspaces.php'));
        self::assertStringContainsString('FIO_API_TOKEN',(string)file_get_contents($root.'/config.example.php'));
    }
}
