<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/includes/kis_person_import.php';
require_once __DIR__.'/KisPersonImportFixture.php';
$port=getenv('EVIDENCE_MARIADB_SMOKE_PORT')?:'3306';
if(!ctype_digit($port))throw new RuntimeException('Invalid test port.');
$server=new PDO('mysql:host=127.0.0.1;port='.$port.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$database='evidence_kis_person_import_smoke_test';
$server->exec("DROP DATABASE IF EXISTS `$database`");
$server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$check=static function(bool $value,string $label):void {if(!$value)throw new RuntimeException($label);};
try {
    $pdo=new PDO('mysql:host=127.0.0.1;port='.$port.';dbname='.$database.';charset=utf8mb4','root','',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec("CREATE TABLE treneri(id INT PRIMARY KEY,role VARCHAR(30)) ENGINE=InnoDB");
    $pdo->exec("INSERT INTO treneri VALUES(1,'admin')");
    $pdo->exec("CREATE TABLE verejni_uzivatele(id INT AUTO_INCREMENT PRIMARY KEY,jmeno VARCHAR(80),prijmeni VARCHAR(80),email VARCHAR(160) UNIQUE,heslo_hash VARCHAR(255),aktivni INT,email_overeno INT) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE sportovci(id INT AUTO_INCREMENT PRIMARY KEY,jmeno VARCHAR(100),prijmeni TEXT,narozeni DATE NOT NULL,email TEXT,telefon VARCHAR(50),hash VARCHAR(64),adresa_ulice VARCHAR(200),adresa_cp VARCHAR(20),adresa_co VARCHAR(20),adresa_obec VARCHAR(100),adresa_psc VARCHAR(10),stav_clenstvi VARCHAR(20),stav_manualni INT,kis_aktivni INT) ENGINE=InnoDB");
    $pdo->exec("INSERT INTO sportovci(jmeno,narozeni) VALUES('LEGACY','2000-01-01')");
    $pdo->exec('CREATE TABLE test_history(id INT PRIMARY KEY,sportovec_id INT NOT NULL,FOREIGN KEY(sportovec_id) REFERENCES sportovci(id)) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO test_history VALUES(1,1)');
    $pdo->exec('CREATE TABLE test_history_event(id INT PRIMARY KEY,history_id INT NOT NULL,FOREIGN KEY(history_id) REFERENCES test_history(id)) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO test_history_event VALUES(1,1)');
    $pdo->exec('CREATE TABLE treninky(id INT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO treninky VALUES(10)');
    $pdo->exec('CREATE TABLE legacy_training_note(id INT PRIMARY KEY,trenink_id INT) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO legacy_training_note VALUES(1,10)');
    $pdo->exec('CREATE TABLE shop_orders(id INT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO shop_orders VALUES(20),(21)');
    $pdo->exec('CREATE TABLE shop_order_items(id INT PRIMARY KEY,order_id INT,sportovec_id INT,FOREIGN KEY(order_id) REFERENCES shop_orders(id)) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO shop_order_items VALUES(1,20,1)');
    $pdo->exec('CREATE TABLE club_member_charges(id INT PRIMARY KEY,sportovec_id INT) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO club_member_charges VALUES(30,1)');
    $pdo->exec('CREATE TABLE payments(id INT PRIMARY KEY,payable_type VARCHAR(32),payable_id INT) ENGINE=InnoDB');
    $pdo->exec("INSERT INTO payments VALUES(1,'shop_order',20),(2,'member_charge',30),(3,'shop_order',21),(4,'other',20)");
    $pdo->exec('CREATE TABLE payment_events(id INT PRIMARY KEY,payment_id INT,FOREIGN KEY(payment_id) REFERENCES payments(id)) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO payment_events VALUES(1,1),(2,2),(3,3),(4,4)');
    foreach(['20260802230000_account_person_roles','20261020180000_kis_full_person_import'] as $id) {
        $m=require dirname(__DIR__,2).'/migrations/'.$id.'.php';$m['up']($pdo);$check($m['verify']($pdo),'migration verification');
    }
    $payload=kisPersonReadPayload(kisPersonJson(kisPersonImportFixture()));
    $config=['keys'=>['v1'=>str_repeat('k',32)],'active_version'=>'v1','index_key'=>str_repeat('i',32)];
    $plan=kisPersonCutoverPlan($pdo);
    $pdo->exec("UPDATE sportovci SET jmeno='CHANGED' WHERE id=1");
    try {kisPersonImportApply($pdo,$payload,1,$plan['fingerprint'],$config);throw new LogicException('stale plan accepted');}
    catch(RuntimeException $e) {$check(str_contains($e->getMessage(),'změnila'),'stale plan rejection');}
    $plan=kisPersonCutoverPlan($pdo);
    $pdo->exec("CREATE TRIGGER injected_failure BEFORE INSERT ON kis_person_aliases FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='test rollback'");
    try {kisPersonImportApply($pdo,$payload,1,$plan['fingerprint'],$config);throw new LogicException('failure not injected');}
    catch(PDOException) {
        $check((int)$pdo->query('SELECT COUNT(*) FROM test_history_event')->fetchColumn()===1,'rollback history');
        $check($pdo->query('SELECT jmeno FROM sportovci')->fetchColumn()==='CHANGED','rollback person');
        $check((int)$pdo->query('SELECT COUNT(*) FROM kis_person_batches')->fetchColumn()===0,'rollback archive');
        $check((int)$pdo->query('SELECT COUNT(*) FROM treninky')->fetchColumn()===1,'rollback training history');
        $check((int)$pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn()===4,'rollback payments');
        $check((int)$pdo->query('SELECT COUNT(*) FROM payment_events')->fetchColumn()===4,'rollback payment events');
    }
    $pdo->exec('DROP TRIGGER injected_failure');
    $result=kisPersonImportApply($pdo,$payload,1,$plan['fingerprint'],$config);
    $check($result['people']===1 && $result['links']===2 && $result['aliases']===2,'source cardinality');
    $check($pdo->query('SELECT narozeni FROM sportovci')->fetchColumn()===null,'unknown date');
    $check((int)$pdo->query('SELECT COUNT(*) FROM test_history_event')->fetchColumn()===0,'remove child history');
    $check((int)$pdo->query('SELECT COUNT(*) FROM legacy_training_note')->fetchColumn()===0,'remove implicit history');
    $check($pdo->query('SELECT GROUP_CONCAT(id ORDER BY id) FROM payments')->fetchColumn()==='3,4','remove only typed payments of deleted history');
    $check($pdo->query('SELECT GROUP_CONCAT(id ORDER BY id) FROM payment_events')->fetchColumn()==='3,4','remove dependent payment events');
    $check((int)$pdo->query('SELECT id FROM shop_orders')->fetchColumn()===21,'preserve unrelated order');
    $check((int)$pdo->query('SELECT aktivni FROM verejni_uzivatele')->fetchColumn()===0,'disabled account');
    $check($pdo->query('SELECT status FROM account_person_roles')->fetchColumn()==='pending','no permission grant');
    $batch=$pdo->query('SELECT * FROM kis_person_batches')->fetch();
    $check(kisPersonDecryptArchive($batch,$config)===$payload['bytes'],'archive round trip');
    $again=kisPersonImportApply($pdo,$payload,1,$plan['fingerprint'],$config);
    $check($again['already_applied']===true,'idempotency');
    echo "KIS full person import: rollback, stale preview, encrypted archive, unknown dates, relationships and idempotency OK\n";
} finally {
    $server->exec("DROP DATABASE IF EXISTS `$database`");
}
