<?php
declare(strict_types=1);
return [
 'id'=>'20261023010000_member_admissions',
 'up'=>static function(PDO $pdo):void {
  $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';$id=$mysql?'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';$tail=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
  $pdo->exec('CREATE TABLE IF NOT EXISTS member_fee_agreements(id '.$id.',plan_id BIGINT UNSIGNED NOT NULL,sportovec_id INT NOT NULL,payer_account_id INT NOT NULL,valid_from DATE NOT NULL,valid_to DATE NOT NULL,reason VARCHAR(1000) NOT NULL,actor_id INT NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(plan_id) REFERENCES member_fee_plans(id))'.$tail);
  $pdo->exec('CREATE TABLE IF NOT EXISTS member_fee_period_decisions(id '.$id.',plan_id BIGINT UNSIGNED NOT NULL,sportovec_id INT NOT NULL,period_key CHAR(7) NOT NULL,amount_minor INT NOT NULL,allow_overlap INT NOT NULL DEFAULT 0,reason VARCHAR(1000) NOT NULL,actor_id INT NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(plan_id) REFERENCES member_fee_plans(id))'.$tail);
  $pdo->exec('CREATE TABLE IF NOT EXISTS club_program_admissions(id '.$id.',order_id BIGINT UNSIGNED NOT NULL UNIQUE,order_item_id BIGINT UNSIGNED NOT NULL UNIQUE,offer_id BIGINT UNSIGNED NOT NULL,sportovec_id INT NOT NULL,payer_account_id INT NOT NULL,starts_on DATE NOT NULL,paper_snapshot_json TEXT NOT NULL,request_key VARCHAR(100) NOT NULL UNIQUE,request_fingerprint CHAR(64) NOT NULL,reason VARCHAR(1000) NOT NULL,actor_id INT NOT NULL,source_enrollment_id BIGINT UNSIGNED NULL,financial_resolution VARCHAR(1000) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(order_id) REFERENCES shop_orders(id),FOREIGN KEY(order_item_id) REFERENCES shop_order_items(id),FOREIGN KEY(offer_id) REFERENCES club_program_offers(id))'.$tail);
 },
 'verify'=>static function(PDO $pdo):bool {try{foreach(['member_fee_agreements','member_fee_period_decisions','club_program_admissions']as$t)$pdo->query('SELECT * FROM '.$t.' LIMIT 0');return true;}catch(PDOException){return false;}}
];
