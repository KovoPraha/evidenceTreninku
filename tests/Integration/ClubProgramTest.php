<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2) . '/includes/club_program.php';

final class ClubProgramTest extends TestCase
{
    public function testMigrationIsRepeatableAndProgramOfferContractIsValidated(): void
    {
        $pdo=$this->database();$migration=require dirname(__DIR__,2).'/migrations/20260804140000_club_programs.php';$migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        $program=\clubProgramCreate($pdo,7,'BIKE-SCHOOL','Cyklistická škola','Stabilní kroužek.');
        $same=\clubProgramCreate($pdo,7,'BIKE-SCHOOL','Cyklistická škola','Stabilní kroužek.');self::assertSame($program['id'],$same['id']);
        $offer=\clubProgramCreateOffer($pdo,7,(int)$program['id'],1,10,501,601,'BIKE-2026-A','Podzim 2026','2026-09-01','2027-01-31',null,null,2,'active');
        self::assertSame(601,(int)$offer['variant_id']);self::assertSame('Cyklistická škola',\clubProgramOfferForVariant($pdo,601)['program_name']);self::assertTrue(\clubProgramOfferIsOnSale(\clubProgramOfferForVariant($pdo,601),new \DateTimeImmutable('2026-08-01')));
    }

    public function testPaidGuardianActivationIsExactlyOnceAndTouchesOnlyChosenChild(): void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,2,'A-2026','2026-09-01','2027-01-31');$item=$this->orderItem($pdo,10,101,601,501,10000,'paid');
        $first=\clubProgramActivateOrderItem($pdo,10,$item);$again=\clubProgramActivateOrderItem($pdo,10,$item);
        self::assertTrue($first['created']);self::assertTrue($first['roster_created']);self::assertFalse($again['created']);
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollments')->fetchColumn());
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_roster_members WHERE sportovec_id=101')->fetchColumn());
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_roster_members WHERE sportovec_id=102')->fetchColumn());
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollment_events')->fetchColumn());
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_roster_events')->fetchColumn());
    }

    public function testUnpaidAndIdorActivationFailWithoutPartialWrites(): void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,2,'A-2026','2026-09-01','2027-01-31');$item=$this->orderItem($pdo,10,101,601,501,10000,'pending');
        foreach ([[10,$item],[11,$item]] as [$account,$orderItem]) {
            try { \clubProgramActivateOrderItem($pdo,$account,$orderItem);self::fail('Unpaid or foreign order item must fail.'); } catch (\ClubProgramException) {}
        }
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollments')->fetchColumn());self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn());self::assertFalse($pdo->inTransaction());
    }

    public function testFreeItemActivatesAndCapacityIsTransactional(): void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,1,'A-2026','2026-09-01','2027-01-31');
        $free=$this->orderItem($pdo,10,101,601,501,0,'pending');\clubProgramActivateOrderItem($pdo,10,$free);
        $second=$this->orderItem($pdo,11,103,601,501,10000,'paid');
        try { \clubProgramActivateOrderItem($pdo,11,$second);self::fail('Capacity must reject second child.'); } catch (\ClubProgramException $exception) { self::assertStringContainsString('Kapacita',$exception->getMessage()); }
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollments')->fetchColumn());self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn());self::assertFalse($pdo->inTransaction());
    }

    public function testRenewalCreatesNewHistoryInsteadOfOverwritingOldEnrollment(): void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,3,'A-2026','2026-09-01','2027-01-31');$this->offer($pdo,602,502,11,3,'A-2027','2027-02-01','2027-06-30');
        \clubProgramActivateOrderItem($pdo,10,$this->orderItem($pdo,10,101,601,501,10000,'paid'));
        \clubProgramActivateOrderItem($pdo,10,$this->orderItem($pdo,10,101,602,502,10000,'paid'));
        $rows=$pdo->query('SELECT valid_from,valid_to FROM club_program_enrollments ORDER BY valid_from')->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(2,$rows);self::assertSame('2026-09-01',$rows[0]['valid_from']);self::assertSame('2027-06-30',$rows[1]['valid_to']);self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM club_roster_members WHERE sportovec_id=101')->fetchColumn());
    }

    public function testInactiveRosterConflictRollsBackEnrollmentAndAudit(): void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,2,'A-2026','2026-09-01','2027-01-31');$item=$this->orderItem($pdo,10,101,601,501,10000,'paid');
        $pdo->exec("INSERT INTO club_roster_members(team_id,sportovec_id,status,source,valid_from,valid_to,created_by_trainer_id) VALUES(10,101,'removed','manual','2026-01-01','2026-02-01',7)");
        try { \clubProgramActivateOrderItem($pdo,10,$item);self::fail('Inactive roster conflict must require admin review.'); } catch (\ClubProgramException) {}
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollments')->fetchColumn());self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollment_events')->fetchColumn());self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn());self::assertFalse($pdo->inTransaction());
    }

    private function offer(PDO $pdo,int $variantId,int $productId,int $teamId,int $capacity,string $code,string $from,string $to,?int $programId=null): int
    {
        $programId??=(int)\clubProgramCreate($pdo,7,'BIKE-SCHOOL','Cyklistická škola')['id'];
        return (int)\clubProgramCreateOffer($pdo,7,$programId,1,$teamId,$productId,$variantId,$code,'Období '.$code,$from,$to,null,null,$capacity,'active')['id'];
    }

    private function orderItem(PDO $pdo,int $accountId,int $sportovecId,int $variantId,int $productId,int $amount,string $payment): int
    {
        $status=$payment==='paid'?'processing':'placed';$pdo->prepare('INSERT INTO shop_orders(account_id,status,payment_status,created_at) VALUES(?,?,?,CURRENT_TIMESTAMP)')->execute([$accountId,$status,$payment]);$orderId=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO shop_order_items(order_id,product_id,variant_id,beneficiary_sportovec_id,product_name_snapshot,quantity,line_amount_minor) VALUES(?,?,?,?,?,?,?)')->execute([$orderId,$productId,$variantId,$sportovecId,'Kroužek',1,$amount]);return(int)$pdo->lastInsertId();
    }

    private function database(): PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT)');$pdo->exec("INSERT INTO treneri VALUES(7,'Admin')");
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,email TEXT,aktivni INTEGER,email_overeno INTEGER)');$pdo->exec("INSERT INTO verejni_uzivatele VALUES(10,'Rodič','A','a@test',1,1),(11,'Rodič','B','b@test',1,1)");
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT)');$pdo->exec("INSERT INTO sportovci VALUES(101,'Dítě','Jedna'),(102,'Dítě','Dvě'),(103,'Cizí','Dítě')");
        $pdo->exec('CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY AUTOINCREMENT,account_id INTEGER,sportovec_id INTEGER,relation_role TEXT,status TEXT,valid_from TEXT,valid_to TEXT)');$pdo->exec("INSERT INTO account_person_roles(account_id,sportovec_id,relation_role,status,valid_from) VALUES(10,101,'guardian','approved','2020-01-01'),(10,102,'guardian','approved','2020-01-01'),(11,103,'guardian','approved','2020-01-01')");
        $pdo->exec('CREATE TABLE shop_products(id INTEGER PRIMARY KEY,name TEXT,offer_type TEXT,catalog_status TEXT)');$pdo->exec("INSERT INTO shop_products VALUES(501,'Kroužek A','goods','active'),(502,'Kroužek B','goods','active')");
        $pdo->exec('CREATE TABLE shop_variants(id INTEGER PRIMARY KEY,product_id INTEGER,sku TEXT,catalog_status TEXT)');$pdo->exec("INSERT INTO shop_variants VALUES(601,501,'A','active'),(602,502,'B','active')");
        $pdo->exec('CREATE TABLE shop_orders(id INTEGER PRIMARY KEY AUTOINCREMENT,account_id INTEGER,status TEXT,payment_status TEXT,public_code TEXT DEFAULT \'TEST\',created_at TEXT)');
        $pdo->exec('CREATE TABLE shop_order_items(id INTEGER PRIMARY KEY AUTOINCREMENT,order_id INTEGER,product_id INTEGER,variant_id INTEGER,beneficiary_sportovec_id INTEGER,product_name_snapshot TEXT,quantity INTEGER,line_amount_minor INTEGER,FOREIGN KEY(order_id) REFERENCES shop_orders(id),FOREIGN KEY(product_id) REFERENCES shop_products(id),FOREIGN KEY(variant_id) REFERENCES shop_variants(id),FOREIGN KEY(beneficiary_sportovec_id) REFERENCES sportovci(id))');
        $pdo->exec('CREATE TABLE club_seasons(id INTEGER PRIMARY KEY,code TEXT,name TEXT,starts_on TEXT,ends_on TEXT,status TEXT)');$pdo->exec("INSERT INTO club_seasons VALUES(1,'SCHOOL','Školní rok','2026-09-01','2027-08-31','active')");
        $pdo->exec('CREATE TABLE club_teams(id INTEGER PRIMARY KEY,season_id INTEGER,code TEXT,name TEXT,status TEXT)');$pdo->exec("INSERT INTO club_teams VALUES(10,1,'A','Kroužek A','active'),(11,1,'B','Kroužek B','active')");
        $pdo->exec('CREATE TABLE club_roster_members(id INTEGER PRIMARY KEY AUTOINCREMENT,team_id INTEGER,sportovec_id INTEGER,status TEXT,source TEXT,valid_from TEXT,valid_to TEXT,created_by_trainer_id INTEGER,UNIQUE(team_id,sportovec_id),FOREIGN KEY(team_id) REFERENCES club_teams(id),FOREIGN KEY(sportovec_id) REFERENCES sportovci(id),FOREIGN KEY(created_by_trainer_id) REFERENCES treneri(id))');
        $pdo->exec('CREATE TABLE club_roster_events(id INTEGER PRIMARY KEY AUTOINCREMENT,team_id INTEGER,roster_member_id INTEGER,actor_trainer_id INTEGER,action TEXT,before_json TEXT,after_json TEXT,note TEXT)');
        foreach(['20260804140000_club_programs.php','20260804160000_club_program_lifecycle.php','20260804235000_club_program_repeat_enrollment.php']as$file){$migration=require dirname(__DIR__,2).'/migrations/'.$file;$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));}return$pdo;
    }
}
