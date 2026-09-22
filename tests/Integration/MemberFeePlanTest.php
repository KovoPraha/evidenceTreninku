<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/member_fee_plan.php';

final class MemberFeePlanTest extends TestCase
{
    public function testPreviewSkipsMissingPayerAndGenerationIsIdempotent():void
    {
        $pdo=$this->database();$migration=require dirname(__DIR__,2).'/migrations/20260922120000_member_fee_plans.php';$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));
        $plan=\memberFeePlanCreate($pdo,7,['team_id'=>10,'name'=>'Závodní měsíční','charge_title'=>'Příspěvek září','amount_minor'=>120000,'due_day'=>15,'starts_on'=>'2026-01-01','ends_on'=>'2026-12-31'],'Zavedení měsíčních příspěvků.',true);
        $preview=\memberFeePlanPreview($pdo,$plan['id'],'2026-09');self::assertSame(1,$preview['ready_count']);self::assertSame(1,$preview['skipped_count']);self::assertContains('missing_payer',array_column($preview['rows'],'result'));
        $run=\memberFeePlanGenerate($pdo,$plan['id'],'2026-09',7,'Potvrzený měsíční běh.',$preview['fingerprint'],true);self::assertFalse($run['idempotent']);self::assertSame(1,$run['generated_count']);self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_member_charges')->fetchColumn());self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn());
        $again=\memberFeePlanGenerate($pdo,$plan['id'],'2026-09',7,'Opakování stejného běhu.',$preview['fingerprint'],true);self::assertTrue($again['idempotent']);self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_member_charges')->fetchColumn());
    }

    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
        $pdo->exec("CREATE TABLE treneri(id INTEGER PRIMARY KEY);CREATE TABLE club_teams(id INTEGER PRIMARY KEY,name TEXT,status TEXT);CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT);CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,email TEXT,aktivni INTEGER,email_overeno INTEGER);CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY,account_id INTEGER,sportovec_id INTEGER,relation_role TEXT,status TEXT,valid_from TEXT,valid_to TEXT);CREATE TABLE club_roster_members(team_id INTEGER,sportovec_id INTEGER,status TEXT,valid_from TEXT,valid_to TEXT);CREATE TABLE club_member_charges(id INTEGER PRIMARY KEY AUTOINCREMENT,sportovec_id INTEGER,payer_account_id INTEGER,public_code TEXT UNIQUE,charge_type TEXT,title_snapshot TEXT,period_from TEXT,period_to TEXT,amount_minor INTEGER,currency TEXT,due_on TEXT,status TEXT,source_system TEXT,source_external_id TEXT,source_import_run_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(source_system,source_external_id));CREATE TABLE club_member_charge_events(id INTEGER PRIMARY KEY AUTOINCREMENT,charge_id INTEGER,action TEXT,from_status TEXT,to_status TEXT,actor_type TEXT,actor_id INTEGER,reason TEXT,snapshot_json TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP);CREATE TABLE payments(id INTEGER PRIMARY KEY AUTOINCREMENT,payable_type TEXT,payable_id INTEGER,method TEXT,status TEXT,amount_minor INTEGER,currency TEXT,variable_symbol TEXT UNIQUE,iban_snapshot TEXT,bic_snapshot TEXT,account_label_snapshot TEXT,spd_payload TEXT,due_at TEXT,paid_at TEXT,confirmed_by_trainer_id INTEGER,confirmation_note TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(payable_type,payable_id));CREATE TABLE shop_bank_settings(id INTEGER PRIMARY KEY,iban TEXT,bic TEXT,account_label TEXT,due_days INTEGER,updated_by_trainer_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("INSERT INTO treneri VALUES(7);INSERT INTO club_teams VALUES(10,'Závodní U17','active');INSERT INTO sportovci VALUES(1,'Anna','První'),(2,'Borek','Druhý');INSERT INTO club_roster_members VALUES(10,1,'active','2026-01-01',NULL),(10,2,'active','2026-01-01',NULL);INSERT INTO verejni_uzivatele VALUES(20,'Rodič','První','parent@example.test',1,1);INSERT INTO account_person_roles VALUES(1,20,1,'guardian','approved','2026-01-01',NULL);INSERT INTO shop_bank_settings(id,iban,bic,account_label,due_days,updated_by_trainer_id) VALUES(1,'CZ6508000000192000145399','GIBACZPX','KIS Kovo Praha',14,7)");return$pdo;
    }
}
