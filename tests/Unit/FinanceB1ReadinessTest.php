<?php
declare(strict_types=1);
namespace Tests\Unit;
use PDO;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__, 2) . '/bin/finance-b1-readiness.php';
final class FinanceB1ReadinessTest extends TestCase
{
    public function testBrokenPresentObjectPreventsReadyEvenWithSuccessfulConnection(): void
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE valid_table(id INTEGER);CREATE VIEW broken_table AS SELECT * FROM missing_storage');
        $result=\financeB1TableReadiness($pdo,['valid_table','broken_table']);
        self::assertSame('FAIL',$result['status']);self::assertSame(1,$result['readable_owned_tables']);self::assertSame('broken_table',$result['unreadable_tables'][0]['table']);
    }
    public function testReadableEmptyTablePassesAndUnknownNamesCannotBeSql(): void
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$pdo->exec('CREATE TABLE valid_table(id INTEGER)');
        self::assertSame('PASS',\financeB1TableReadiness($pdo,['valid_table'])['status']);
        $this->expectException(\InvalidArgumentException::class);\financeB1TableReadiness($pdo,['valid_table; DROP TABLE valid_table']);
    }
    public function testMissingRequiredTableFailsAndOnlyExplicitOptionalTablesAreAllowed(): void
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$pdo->exec('CREATE TABLE valid_table(id INTEGER)');
        $result=\financeB1TableReadiness($pdo,['valid_table','accounting_movements']);
        self::assertSame('FAIL',$result['status']);self::assertSame(['accounting_movements'],$result['missing_required_tables']);
        self::assertSame('PASS',\financeB1TableReadiness($pdo,['valid_table','ucto_gs_linky'],['ucto_gs_linky'])['status']);
    }
}
