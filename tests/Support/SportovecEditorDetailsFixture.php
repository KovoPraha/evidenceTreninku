<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/sportovec_editor.php';

function sportovecEditorDetailsFixture(PDO $pdo, bool $migrate = true): array
{
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    if (!$mysql) $pdo->exec('PRAGMA foreign_keys=ON');
    $id = $mysql ? 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $big = $mysql ? 'BIGINT UNSIGNED' : 'INTEGER';
    $blob = $mysql ? 'VARBINARY(255)' : 'BLOB';
    $nonce = $mysql ? 'BINARY(24)' : 'BLOB';
    $blind = $mysql ? 'BINARY(32)' : 'BLOB';
    $pdo->exec('CREATE TABLE treneri(id INT PRIMARY KEY,jmeno VARCHAR(100),role VARCHAR(30))');
    $pdo->exec("INSERT INTO treneri VALUES(7,'LOCALHOST Admin','admin')");
    $fields = implode(',', array_map(static fn($field) => $field . ($field === 'narozeni' ? ' DATE NULL' : ' TEXT'), array_keys(sportovecEditorFields())));
    $pdo->exec('CREATE TABLE sportovci(id INT PRIMARY KEY,' . $fields . ',rc VARCHAR(20),stav_clenstvi VARCHAR(24),stav_manualni INT DEFAULT 0)');
    $pdo->exec("INSERT INTO sportovci(id,jmeno,prijmeni,narozeni,stav_clenstvi) VALUES(10,'LOCALHOST','Jedna',NULL,'aktivni'),(11,'LOCALHOST','Dva',NULL,'aktivni'),(12,'LOCALHOST','Registrace','1985-01-01','aktivni')");
    $pdo->exec('CREATE TABLE verejni_uzivatele(id INT PRIMARY KEY,jmeno VARCHAR(100),prijmeni VARCHAR(100),email VARCHAR(254),aktivni INT,email_overeno INT)');
    $pdo->exec("INSERT INTO verejni_uzivatele VALUES(1,'LOCALHOST','Rodič','login@example.test',1,1),(2,'LOCALHOST','Nový','new@example.test',0,0)");
    $pdo->exec('CREATE TABLE account_person_claim_requests(id ' . $big . ' PRIMARY KEY)');
    $pdo->exec('INSERT INTO account_person_claim_requests VALUES(1)');
    $pdo->exec('CREATE TABLE osoba_citlive_udaje(id ' . $id . ',record_token VARCHAR(32) NOT NULL UNIQUE,request_id ' . $big . ' NOT NULL UNIQUE,sportovec_id INT NULL UNIQUE,rc_ciphertext ' . $blob . ' NOT NULL,rc_nonce ' . $nonce . ' NOT NULL,rc_key_version VARCHAR(32) NOT NULL,rc_blind_index ' . $blind . ' NOT NULL UNIQUE,contract_version VARCHAR(64) NOT NULL,status VARCHAR(24) NOT NULL,retention_reason TEXT NULL,retention_until DATETIME NULL,erased_at DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(request_id) REFERENCES account_person_claim_requests(id),FOREIGN KEY(sportovec_id) REFERENCES sportovci(id))');
    $pdo->exec('CREATE TABLE osoba_citlive_pristupy(id ' . $id . ',sensitive_record_id ' . $big . ' NULL,private_file_id ' . $big . ' NULL,sportovec_id INT NULL,request_id ' . $big . ' NULL,actor_trainer_id INT,action VARCHAR(24),reason TEXT,ip_address VARCHAR(45),FOREIGN KEY(sensitive_record_id) REFERENCES osoba_citlive_udaje(id))');
    $pdo->exec('CREATE TABLE sportovec_history(sportovec_id INT,created_by INT,source VARCHAR(24),event_type VARCHAR(80),title VARCHAR(255),detail TEXT,old_json TEXT,new_json TEXT,ref_table VARCHAR(100),ref_id INT)');
    $pdo->exec('CREATE TABLE ucto_audit_log(uzivatel_id INT,akce TEXT,tabulka TEXT,zaznam_id INT,detail TEXT,ip_adresa TEXT,user_agent TEXT)');
    foreach (['20260802230000_account_person_roles','20260804090000_kis_teams_rosters','20261020180000_kis_full_person_import'] as $name) {
        $migration = require dirname(__DIR__, 2) . '/migrations/' . $name . '.php'; $migration['up']($pdo);
    }
    $keys = ['keys' => ['test' => str_repeat('k', 32)], 'active_version' => 'test', 'index_key' => str_repeat('i', 32)];
    $oldId = personSensitiveStoreBirthNumber($pdo, 1, '8501010001', '1985-01-01', $keys);
    $pdo->prepare("UPDATE osoba_citlive_udaje SET sportovec_id=12,status='approved' WHERE id=?")->execute([$oldId]);
    $_SESSION = ['trener_id' => 7, 'role' => 'admin'];
    personSensitiveAdminReveal($pdo, $oldId, 'LOCALHOST původní registrace', $keys);
    $plain = kisPersonJson(['source_archive' => ['people' => [['id_hash' => 'source10', 'rodnecislo' => '8601010000']]]]);
    $fingerprint = hash('sha256', $plain); $archive = kisPersonEncryptArchive($plain, $fingerprint, $keys);
    $pdo->prepare('INSERT INTO kis_person_batches(fingerprint,source_namespace,actor_trainer_id,payload_ciphertext,key_version,summary_json) VALUES(?,?,?,?,?,?)')
        ->execute([$fingerprint, 'LOCALHOST', 7, $archive['ciphertext'], $archive['key_version'], '{}']);
    $pdo->prepare("INSERT INTO kis_person_profiles(sportovec_id,canonical_key,batch_fingerprint,contact_address,country,source_active) VALUES(10,'O0010',?,'LOCALHOST adresa','CZ',1)")->execute([$fingerprint]);
    $pdo->exec("INSERT INTO kis_person_aliases VALUES('source10',10,1,'localhost')");
    $pdo->prepare("INSERT INTO kis_person_contacts VALUES('parent',?,'LOCALHOST Rodič','[\"LOCALHOST Rodič\"]','[\"contact@example.test\"]','[\"123\"]',NULL,1,'existing')")->execute([$fingerprint]);
    $pdo->exec("INSERT INTO kis_person_links VALUES('link10',10,'parent','matka','source10',1),('link10b',10,'parent','matka','source10',2),('link11',11,'parent','matka','source11',1)");
    accountPersonRoleApprove($pdo, 1, 10, 'guardian', 7, 'LOCALHOST schválení rodiče');
    accountPersonRoleApprove($pdo, 1, 11, 'guardian', 7, 'LOCALHOST schválení sourozence');
    if ($migrate) { $migration = require dirname(__DIR__, 2) . '/migrations/20261020220000_athlete_editor_relations.php'; $migration['up']($pdo); }
    $pdo->exec("INSERT INTO club_seasons(id,code,name,starts_on,ends_on,status,created_by_trainer_id) VALUES(1,'CURRENT','LOCALHOST aktuální','2026-09-01','2027-08-31','active',7),(2,'OLD','LOCALHOST historie','2025-09-01','2026-08-31','archived',7),(3,'FUTURE','LOCALHOST budoucí','2098-09-01','2099-08-31','active',7)");
    $pdo->exec("INSERT INTO club_teams(id,season_id,code,name,discipline,age_label,status,created_by_trainer_id) VALUES(1,1,'CURRENT','LOCALHOST současná soupiska','kolo','děti','active',7),(2,2,'OLD','LOCALHOST historická soupiska','kolo','děti','active',7),(3,3,'FUTURE','LOCALHOST budoucí soupiska','kolo','děti','active',7)");
    return ['keys' => $keys, 'old_record_id' => $oldId];
}

function sportovecEditorPaymentFixture(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE club_member_charges(id INT PRIMARY KEY,sportovec_id INT,public_code VARCHAR(32),title_snapshot TEXT,amount_minor INT,currency CHAR(3),status VARCHAR(24),period_from DATE,period_to DATE,due_on DATE,source_system VARCHAR(32),created_at DATETIME)');
    $pdo->exec("INSERT INTO club_member_charges VALUES(1,10,'PAID','LOCALHOST stará úhrada',1000,'CZK','paid','2020-01-01','2020-12-31','2020-01-01','manual','2020-01-01'),(2,10,'CANCEL','LOCALHOST zrušeno',2000,'CZK','cancelled',NULL,NULL,NULL,'manual','2026-01-01'),(3,11,'FOREIGN','Sourozenec',3000,'CZK','paid',NULL,NULL,NULL,'manual','2026-01-01')");
    $pdo->exec('CREATE TABLE payments(id INT PRIMARY KEY,payable_type VARCHAR(32),payable_id INT,status VARCHAR(24),method VARCHAR(32),variable_symbol VARCHAR(10),paid_at DATETIME)');
    $pdo->exec("INSERT INTO payments VALUES(1,'member_charge',1,'paid','bank_transfer','111','2020-01-02'),(2,'shop_order',1,'paid','bank_transfer','222','2026-01-02'),(3,'member_charge',3,'paid','bank_transfer','333','2026-01-03')");
    $pdo->exec('CREATE TABLE shop_orders(id INT PRIMARY KEY,account_id INT,finance_sportovec_id INT,public_code VARCHAR(32),total_minor INT,currency CHAR(3),status VARCHAR(24),payment_status VARCHAR(24),placed_at DATETIME)');
    $pdo->exec("INSERT INTO shop_orders VALUES(1,1,10,'OWN',10000,'CZK','completed','paid','2026-01-01'),(2,1,11,'SIBLING',50000,'CZK','placed','pending','2026-01-02'),(3,1,11,'MIXED',8000,'CZK','placed','refunded','2026-01-03')");
    $pdo->exec('CREATE TABLE shop_order_items(id INT PRIMARY KEY,order_id INT,beneficiary_sportovec_id INT,line_amount_minor INT)');
    $pdo->exec('INSERT INTO shop_order_items VALUES(1,1,10,10000),(2,2,11,50000),(3,3,10,2000),(4,3,11,5000),(5,3,10,1000)');
    $pdo->exec('CREATE TABLE club_finance_receipts(id INT PRIMARY KEY,sportovec_id INT,kind VARCHAR(24),reference VARCHAR(100),amount_minor INT,currency CHAR(3),paid_on DATE)');
    $pdo->exec("INSERT INTO club_finance_receipts VALUES(1,10,'manual_bank','LOCALHOST-1',5000,'CZK','2026-01-02'),(2,11,'manual_bank','FOREIGN',9000,'CZK','2026-01-02')");
    $pdo->exec('CREATE TABLE club_finance_allocations(id INT PRIMARY KEY,receipt_id INT,charge_id INT,order_id INT,amount_minor INT)');
    $pdo->exec('INSERT INTO club_finance_allocations VALUES(1,1,1,NULL,1000),(2,2,3,NULL,3000)');
}
