<?php
declare(strict_types=1);

namespace Tests\Integration;

use FioImportException;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/fio_readonly_import.php';

final class FioReadonlyImportTest extends TestCase
{
    public function testImportCreatesShadowProposalAndNeverMutatesPaymentOrOrder(): void
    {
        $pdo=$this->database();
        $payload=$this->payload([
            $this->movement('1001','1250.00','CZK','1'),
            $this->movement('1002','1200.00','CZK','1'),
            $this->movement('1003','1250.00','EUR','1'),
            $this->movement('1004','-200.00','CZK','1'),
            $this->movement('1005','1250.00','CZK',null),
            $this->movement('1006','1250.00','CZK','999'),
        ]);
        $result=fioImportJson($pdo,$payload,'2026-08-01','2026-08-03','CZ6508000000192000145399');
        self::assertSame(6,$result['inserted']);self::assertSame(1,$result['proposed']);self::assertSame(4,$result['review']);self::assertSame(1,$result['ignored']);
        self::assertSame(['proposed_exact','review_amount','review_currency','ignored_non_credit','review_missing_vs','review_unknown_vs'],$pdo->query('SELECT match_status FROM fio_account_movements ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame('pending',$pdo->query('SELECT status FROM payments WHERE id=1')->fetchColumn());
        self::assertSame('placed',$pdo->query('SELECT status FROM shop_orders WHERE id=1')->fetchColumn());
        self::assertNull($pdo->query('SELECT paid_at FROM payments WHERE id=1')->fetchColumn());

        $second=fioImportJson($pdo,$payload,'2026-08-01','2026-08-03','CZ6508000000192000145399');
        self::assertSame(0,$second['inserted']);self::assertSame(6,$second['duplicates']);
        self::assertSame(6,(int)$pdo->query('SELECT COUNT(*) FROM fio_account_movements')->fetchColumn());
    }

    public function testChangedMovementIdFailsAtomicallyWithoutOverwritingOriginal(): void
    {
        $pdo=$this->database();$original=$this->payload([$this->movement('2001','1250.00','CZK','1')]);
        fioImportJson($pdo,$original,'2026-08-03','2026-08-03','CZ6508000000192000145399');
        try{fioImportJson($pdo,$this->payload([$this->movement('2001','1251.00','CZK','1')]),'2026-08-03','2026-08-03','CZ6508000000192000145399');self::fail('Expected conflict.');}
        catch(FioImportException $exception){self::assertSame('fio_movement_changed',$exception->getMessage());}
        self::assertSame(125000,(int)$pdo->query("SELECT amount_minor FROM fio_account_movements WHERE fio_movement_id='2001'")->fetchColumn());
        self::assertSame('failed',$pdo->query('SELECT status FROM fio_import_runs ORDER BY id DESC LIMIT 1')->fetchColumn());
    }

    public function testUnexpectedAccountIsRejectedAndNoMovementIsStored(): void
    {
        $pdo=$this->database();
        $this->expectException(FioImportException::class);$this->expectExceptionMessage('fio_unexpected_account');
        try{fioImportJson($pdo,$this->payload([$this->movement('3001','1250.00','CZK','1')]),'2026-08-03','2026-08-03','CZ0000000000000000000000');}
        finally{self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM fio_account_movements')->fetchColumn());}
    }

    public function testMoneyAndVariableSymbolNormalizationAreExact(): void
    {
        self::assertSame(12345,\fioAmountToMinor('123.4500'));self::assertSame(-1,\fioAmountToMinor('-0.01'));self::assertSame('0000000123',\fioNormalizeVariableSymbol('123'));self::assertNull(\fioNormalizeVariableSymbol('12A'));
        $this->expectException(FioImportException::class);\fioAmountToMinor('1.001');
    }

    public function testOfficialFioBookingDateFormatAndLegacyEpochAreAcceptedStrictly(): void
    {
        self::assertSame('2012-06-30',\fioBookedOn('2012-06-30+02:00'));
        self::assertSame('2012-06-30',\fioBookedOn('2012-06-30'));
        self::assertSame('2026-08-03',\fioBookedOn(1785715200000));
        self::assertSame('2026-08-03',\fioBookedOn(1785715200));

        foreach (['2012-02-30+02:00','2012-06-30+15:00','not-a-date'] as $invalid) {
            try {
                \fioBookedOn($invalid);
                self::fail('Invalid Fio booking date must be rejected: '.$invalid);
            } catch (FioImportException $exception) {
                self::assertSame('fio_invalid_booking_date',$exception->getMessage());
            }
        }
    }

    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);$pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE shop_orders(id INTEGER PRIMARY KEY,public_code TEXT,status TEXT,payment_status TEXT)');
        $pdo->exec('CREATE TABLE payments(id INTEGER PRIMARY KEY,payable_type TEXT,payable_id INTEGER,method TEXT,status TEXT,amount_minor INTEGER,currency TEXT,variable_symbol TEXT,paid_at TEXT)');
        $pdo->exec("INSERT INTO shop_orders VALUES(1,'OBJ-1','placed','pending')");$pdo->exec("INSERT INTO payments VALUES(1,'shop_order',1,'bank_transfer','pending',125000,'CZK','0000000001',NULL)");
        $migration=require dirname(__DIR__,2).'/migrations/20260804070000_fio_readonly_import.php';$migration['up']($pdo);$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));return $pdo;
    }

    /** @param list<array<string,mixed>> $movements */
    private function payload(array $movements):string
    {
        return json_encode(['accountStatement'=>['info'=>['iban'=>'CZ65 0800 0000 1920 0014 5399'],'transactionList'=>['transaction'=>$movements]]],JSON_THROW_ON_ERROR);
    }

    /** @return array<string,array{value:mixed}> */
    private function movement(string $id,string $amount,string $currency,?string $vs):array
    {
        return ['column0'=>['value'=>1785715200000],'column1'=>['value'=>$amount],'column5'=>['value'=>$vs],'column8'=>['value'=>'Vklad prevodem'],'column14'=>['value'=>$currency],'column22'=>['value'=>$id]];
    }
}
