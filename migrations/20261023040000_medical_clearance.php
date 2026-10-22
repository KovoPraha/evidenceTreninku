<?php
declare(strict_types=1);
return [
 'id'=>'20261023040000_medical_clearance',
 'up'=>static function(PDO $pdo):void {
  $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
  $id=$mysql?'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
  $tail=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
  $pdo->exec('CREATE TABLE IF NOT EXISTS medical_clearance_policies (id '.$id.', purpose VARCHAR(100) NOT NULL, legal_basis VARCHAR(1000) NOT NULL, retention_rule VARCHAR(1000) NOT NULL, activity_rule VARCHAR(1000) NOT NULL, reminder_days INT NOT NULL, approved_by INT NOT NULL, approved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(approved_by) REFERENCES treneri(id))'.$tail);
  $pdo->exec('CREATE TABLE IF NOT EXISTS medical_clearance_watches (id '.$id.', sportovec_id INT NOT NULL, purpose VARCHAR(100) NOT NULL, policy_id BIGINT UNSIGNED NOT NULL, next_review_on DATE NOT NULL, created_by INT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(sportovec_id,purpose), FOREIGN KEY(sportovec_id) REFERENCES sportovci(id), FOREIGN KEY(policy_id) REFERENCES medical_clearance_policies(id), FOREIGN KEY(created_by) REFERENCES treneri(id))'.$tail);
  $pdo->exec("CREATE TABLE IF NOT EXISTS medical_clearance_records (id $id, watch_id BIGINT UNSIGNED NOT NULL, issued_on DATE NOT NULL, valid_until DATE NOT NULL, original_location VARCHAR(1000) NOT NULL, storage_key VARCHAR(255) NULL, file_sha256 CHAR(64) NULL, state VARCHAR(20) NOT NULL DEFAULT 'pending', revision INT NOT NULL DEFAULT 1, recorded_by INT NOT NULL, recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, verified_by INT NULL, verified_at DATETIME NULL, replaces_id BIGINT UNSIGNED NULL UNIQUE, FOREIGN KEY(watch_id) REFERENCES medical_clearance_watches(id), FOREIGN KEY(recorded_by) REFERENCES treneri(id), FOREIGN KEY(verified_by) REFERENCES treneri(id), FOREIGN KEY(replaces_id) REFERENCES medical_clearance_records(id))".$tail);
  $pdo->exec('CREATE TABLE IF NOT EXISTS medical_clearance_events (id '.$id.', watch_id BIGINT UNSIGNED NOT NULL, record_id BIGINT UNSIGNED NULL, actor_id INT NOT NULL, action VARCHAR(30) NOT NULL, reason VARCHAR(1000) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(watch_id) REFERENCES medical_clearance_watches(id), FOREIGN KEY(record_id) REFERENCES medical_clearance_records(id), FOREIGN KEY(actor_id) REFERENCES treneri(id))'.$tail);
 },
 'verify'=>static function(PDO $pdo):bool {try {
  $pdo->query('SELECT purpose,legal_basis,retention_rule,activity_rule,reminder_days FROM medical_clearance_policies LIMIT 0');
  $pdo->query('SELECT sportovec_id,purpose,policy_id,next_review_on FROM medical_clearance_watches LIMIT 0');
  $pdo->query('SELECT watch_id,issued_on,valid_until,storage_key,file_sha256,state,revision,verified_by,verified_at,replaces_id FROM medical_clearance_records LIMIT 0');
  $pdo->query('SELECT watch_id,record_id,actor_id,action,reason FROM medical_clearance_events LIMIT 0');return true;
 }catch(PDOException){return false;}}
];
