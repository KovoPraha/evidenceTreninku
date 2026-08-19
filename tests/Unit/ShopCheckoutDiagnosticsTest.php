<?php
declare(strict_types=1);

namespace Tests\Unit;

use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once dirname(__DIR__,2).'/includes/shop_checkout.php';

final class ShopCheckoutDiagnosticsTest extends TestCase
{
    /**
     * Measured against a real 10.3.39 server: only a deadlock may be reported
     * to the customer as "someone else has just ordered it". Every other
     * database error must keep the generic fail-closed message.
     */
    public function testOnlyASerializationFailureIsTranslatedForTheCustomer():void
    {
        self::assertTrue(\shopCheckoutIsSerializationFailure(self::pdoException('40001',1213)));
        self::assertTrue(\shopCheckoutIsSerializationFailure(self::pdoException('HY000',1213)));
        self::assertTrue(\shopCheckoutIsSerializationFailure(self::pdoException('40001',0)));

        self::assertFalse(\shopCheckoutIsSerializationFailure(self::pdoException('HY000',1205)),'lock wait timeout');
        self::assertFalse(\shopCheckoutIsSerializationFailure(self::pdoException('42S22',1054)),'unknown column');
        self::assertFalse(\shopCheckoutIsSerializationFailure(self::pdoException('23000',1062)),'duplicate key');
        self::assertFalse(\shopCheckoutIsSerializationFailure(self::pdoException('HY000',2006)),'server gone away');
        self::assertFalse(\shopCheckoutIsSerializationFailure(new RuntimeException('40001')));
    }

    public function testDiagnosticTraceKeepsCodesAndDropsMessages():void
    {
        $cause=self::pdoException('23000',1062,"Duplicate entry 'rodic@example.test' for key 'email'");
        $trace=\shopCheckoutDiagnosticTrace(new RuntimeException('Objednávka selhala',0,$cause));

        self::assertStringContainsString('RuntimeException',$trace);
        self::assertStringContainsString('sqlstate=23000 driver=1062',$trace);
        self::assertStringContainsString(' <- ',$trace);
        self::assertStringContainsString('ShopCheckoutDiagnosticsTest.php:',$trace);
        self::assertStringNotContainsString('rodic@example.test',$trace);
        self::assertStringNotContainsString('Duplicate entry',$trace);
        self::assertStringNotContainsString('Objednávka selhala',$trace);
    }

    public function testDiagnosticTraceIsBoundedAndSurvivesAMissingDriverCode():void
    {
        $deepest=new RuntimeException('nejhlubší');
        $chain=$deepest;
        for($level=0;$level<9;$level++)$chain=new RuntimeException('úroveň '.$level,0,$chain);
        self::assertSame(5,substr_count(\shopCheckoutDiagnosticTrace($chain),' <- ')+1);

        $bare=new PDOException('bez kódu');
        self::assertStringContainsString('driver=-',\shopCheckoutDiagnosticTrace($bare));
    }

    public function testDiagnosticReferenceCarriesNoLiveToken():void
    {
        $keyHash=str_repeat('ab',32);
        self::assertSame('ref='.substr($keyHash,0,16).' order=41',\shopCheckoutDiagnosticReference($keyHash,41));
        self::assertSame('ref='.substr($keyHash,0,16).' order=-',\shopCheckoutDiagnosticReference($keyHash,0));
        self::assertSame(16,strlen(substr($keyHash,0,16)));
    }

    private static function pdoException(string $sqlstate,int $driverCode,string $message='chyba'):PDOException
    {
        return new ShopCheckoutDiagnosticsPdoDouble($sqlstate,$driverCode,$message);
    }
}

/** PDO reports SQLSTATE in the protected string code, which only a subclass can set. */
final class ShopCheckoutDiagnosticsPdoDouble extends PDOException
{
    public function __construct(string $sqlstate,int $driverCode,string $message)
    {
        parent::__construct($message);
        $this->code=$sqlstate;
        $this->errorInfo=[$sqlstate,$driverCode,$message];
    }
}
