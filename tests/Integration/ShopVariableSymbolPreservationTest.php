<?php
declare(strict_types=1);
namespace Tests\Integration;
use PDO;
use PHPUnit\Framework\TestCase;

defined('SHOP_VS_PRESERVATION_LIBRARY_ONLY')||define('SHOP_VS_PRESERVATION_LIBRARY_ONLY',true);
require_once dirname(__DIR__,2).'/bin/verify-shop-variable-symbols.php';

final class ShopVariableSymbolPreservationTest extends TestCase
{
    private function database(): PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE payments(id INT PRIMARY KEY,payable_type TEXT,payable_id INT,variable_symbol TEXT)');
        $pdo->exec('CREATE TABLE member_fee_standing_orders(id INT PRIMARY KEY,plan_id INT,sportovec_id INT,variable_symbol TEXT)');
        $pdo->exec('CREATE TABLE member_payer_variable_symbols(payer_account_id INT PRIMARY KEY,variable_symbol TEXT)');
        $pdo->exec("INSERT INTO payments VALUES(1,'shop_order',13,'0000000013'),(2,'member_charge',21,'9123456789')");
        $pdo->exec("INSERT INTO member_fee_standing_orders VALUES(1,2,3,'9234567890')");
        $pdo->exec("INSERT INTO member_payer_variable_symbols VALUES(7,'9345678901')");return $pdo;
    }

    public function testSnapshotIsReadOnlyAndIgnoresNewRowsWhileDetectingOldChanges(): void
    {
        $pdo=$this->database();$before=\shopVsPreservationSnapshot($pdo);
        self::assertSame(2,$before['tables']['payments']['count']);self::assertFalse($pdo->inTransaction());
        self::assertStringNotContainsString('0000000013',json_encode($before));self::assertStringNotContainsString('9123456789',json_encode($before));
        $pdo->exec("INSERT INTO payments VALUES(3,'shop_order',14,'2630100001')");
        self::assertSame($before,\shopVsPreservationSnapshot($pdo,$before['bounds']));
        $pdo->exec("UPDATE payments SET variable_symbol='0000000099' WHERE id=1");
        self::assertNotSame($before,\shopVsPreservationSnapshot($pdo,$before['bounds']));
    }

    public function testBothStandingReservationsAndDeletedMappingsAreProtected(): void
    {
        foreach(['member_fee_standing_orders','member_payer_variable_symbols'] as $table) {
            $pdo=$this->database();$before=\shopVsPreservationSnapshot($pdo);
            $pdo->exec('DELETE FROM '.$table);
            self::assertNotSame($before,\shopVsPreservationSnapshot($pdo,$before['bounds']));
        }
    }

    public function testBoundsAreStrictAndEmptyTablesAreSupported(): void
    {
        $pdo=$this->database();$before=\shopVsPreservationSnapshot($pdo);
        try {\shopVsPreservationSnapshot($pdo,['payments'=>'1']);self::fail('Untrusted bounds must refuse.');} catch(\InvalidArgumentException) {}
        foreach(['payments','member_fee_standing_orders','member_payer_variable_symbols'] as $table)$pdo->exec('DELETE FROM '.$table);
        $empty=\shopVsPreservationSnapshot($pdo);
        self::assertSame(['payments'=>0,'member_fee_standing_orders'=>0,'member_payer_variable_symbols'=>0],$empty['bounds']);
        self::assertSame($empty,\shopVsPreservationSnapshot($pdo,$empty['bounds']));
    }
}
