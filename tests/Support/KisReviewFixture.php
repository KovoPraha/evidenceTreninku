<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/includes/kis_review_handoff.php';
/** Minimal transactional schema shared by SQLite regression and MariaDB smoke. */
function kisReviewFixture(PDO $p,string $archive):void{
 $mysql=$p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';$auto=$mysql?'INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';$tail=$mysql?' ENGINE=InnoDB':'';
 $definitions=[
  'sportovci'=>"id $auto,jmeno VARCHAR(100),prijmeni VARCHAR(100),narozeni VARCHAR(20),kis_external_id VARCHAR(80),hash VARCHAR(100),uci INTEGER,uciid VARCHAR(100),email TEXT NOT NULL,first_name_norm VARCHAR(100),last_name_norm VARCHAR(100),stav_clenstvi VARCHAR(20),stav_duvod VARCHAR(500)",
  'club_teams'=>"id $auto,name VARCHAR(100),status VARCHAR(20),team_kind VARCHAR(20)",
  'club_roster_members'=>"id $auto,team_id INTEGER,sportovec_id INTEGER,status VARCHAR(24),source VARCHAR(24),source_ref VARCHAR(120),valid_from VARCHAR(10),valid_to VARCHAR(10),created_by_trainer_id INTEGER,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
  'club_roster_events'=>"id $auto,team_id INTEGER,roster_member_id INTEGER,actor_trainer_id INTEGER,action VARCHAR(40),before_json TEXT,after_json TEXT,note VARCHAR(1000),created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
  'sportovec_history'=>"id $auto,sportovec_id INTEGER,created_by INTEGER,source VARCHAR(40),event_type VARCHAR(80),title VARCHAR(180),detail TEXT,old_json TEXT,new_json TEXT,ref_table VARCHAR(80),ref_id INTEGER",
  'kis_import_runs'=>"id $auto,status VARCHAR(20),source_manifest_json TEXT",
  'kis_import_rows'=>"id $auto,run_id INTEGER,jmeno VARCHAR(100),prijmeni VARCHAR(100),narozeni VARCHAR(20),kis_external_id VARCHAR(80),kis_soupisky VARCHAR(200)",
  'kis_import_payment_rows'=>"id $auto,run_id INTEGER,amount_minor INTEGER",
  'kis_import_source_artifacts'=>"id $auto,source_kind VARCHAR(20),contract_version VARCHAR(80),sha256 VARCHAR(64),byte_size INTEGER,storage_key VARCHAR(200)",
  'payments'=>"id $auto,status VARCHAR(20),variable_symbol VARCHAR(20)",
  'ucto_audit_log'=>"id $auto,uzivatel_id INTEGER,akce VARCHAR(200),tabulka VARCHAR(100),zaznam_id INTEGER,detail TEXT,ip_adresa VARCHAR(80),user_agent VARCHAR(300)",
  'shop_order_items'=>"id $auto,sportovec_id INTEGER",
 ];
 foreach($definitions as $table=>$definition)$p->exec("CREATE TABLE $table($definition)$tail");
 $m=require dirname(__DIR__,2).'/migrations/20261025231000_kis_review_handoff.php';$m['up']($p);
 $p->exec("INSERT INTO sportovci(id,jmeno,prijmeni,narozeni,kis_external_id,email,stav_clenstvi) VALUES(5,'Původní','Osoba','2012-01-01','KIS-5','','aktivni');INSERT INTO club_teams(id,name,status,team_kind) VALUES(1,'Testovací soupiska','active','roster');INSERT INTO payments(status,variable_symbol) VALUES('paid','1234567890')");
 $ids=[];foreach(kisSourceKinds() as $kind){$body="Synthetic $kind";file_put_contents($archive.'/'.$kind.'.raw',$body);$p->prepare('INSERT INTO kis_import_source_artifacts(source_kind,contract_version,sha256,byte_size,storage_key) VALUES(?,?,?,?,?)')->execute([$kind,'fixture-v1',hash('sha256',$body),strlen($body),$kind.'.raw']);$ids[$kind]=(int)$p->lastInsertId();}
 $p->prepare("INSERT INTO kis_import_runs(id,status,source_manifest_json) VALUES(1,'preview',?)")->execute([kisImportJson(kisSourceManifest($p,$ids))]);
 $p->exec("INSERT INTO kis_import_rows(id,run_id,jmeno,prijmeni,narozeni,kis_external_id,kis_soupisky) VALUES(1,1,'Opravená','Osoba','2012-01-01','KIS-5','Testovací soupiska'),(2,1,'Nová','Osoba','2013-02-01','KIS-NEW','Testovací soupiska');INSERT INTO kis_import_payment_rows(run_id,amount_minor) VALUES(1,10000)");
}
