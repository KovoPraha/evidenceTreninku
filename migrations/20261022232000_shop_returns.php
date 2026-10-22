<?php
declare(strict_types=1);
return [
 'id'=>'20261022232000_shop_returns',
 'up'=>static function(PDO $pdo):void {
  $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';$id=$mysql?'INT AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';$tail=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
  $pdo->exec('CREATE TABLE IF NOT EXISTS shop_return_cases (
   id '.$id.', issue_movement_id BIGINT NOT NULL, order_id BIGINT NOT NULL, order_item_id BIGINT NOT NULL,
   source_exchange_case_id INT NULL, variant_id BIGINT NOT NULL, quantity INT NOT NULL, request_type VARCHAR(30) NOT NULL,
   state VARCHAR(20) NOT NULL DEFAULT \'open\', physical_state VARCHAR(30) NOT NULL DEFAULT \'requested\', disposition VARCHAR(30) NOT NULL DEFAULT \'pending\',
   owner_trainer_id INT NOT NULL, next_due_on DATE NOT NULL, contact_path VARCHAR(300) NOT NULL, customer_request TEXT NOT NULL, policy_evidence TEXT NOT NULL,
   original_snapshot_json TEXT NOT NULL, original_amount_minor BIGINT NOT NULL, currency VARCHAR(3) NOT NULL,
   return_movement_id BIGINT NULL, correction_document_id INT NULL, refund_decision_id INT NULL,
   finance_state VARCHAR(30) NOT NULL DEFAULT \'pending\', finance_evidence TEXT NULL,
   exchange_snapshot_json TEXT NULL, exchange_variant_id BIGINT NULL, exchange_amount_minor BIGINT NULL, exchange_delta_minor BIGINT NULL,
   exchange_reservation_id BIGINT NULL, exchange_issue_movement_id BIGINT NULL,
   revision INT NOT NULL DEFAULT 1, request_key VARCHAR(100) NOT NULL UNIQUE, input_hash VARCHAR(64) NOT NULL,
   created_by INT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   FOREIGN KEY(owner_trainer_id) REFERENCES treneri(id), FOREIGN KEY(created_by) REFERENCES treneri(id),
   FOREIGN KEY(correction_document_id) REFERENCES accounting_documents(id), FOREIGN KEY(refund_decision_id) REFERENCES accounting_refund_decisions(id)
  )'.$tail);
  $pdo->exec('CREATE TABLE IF NOT EXISTS shop_return_finance_links (id '.$id.',case_id INT NOT NULL,correction_document_id INT NOT NULL,line_key VARCHAR(100) NOT NULL,amount_minor BIGINT NOT NULL,refund_decision_id INT NULL,actor_id INT NOT NULL,reason VARCHAR(1000) NOT NULL,recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(case_id) REFERENCES shop_return_cases(id),FOREIGN KEY(correction_document_id) REFERENCES accounting_documents(id),FOREIGN KEY(refund_decision_id) REFERENCES accounting_refund_decisions(id),UNIQUE(case_id,correction_document_id,line_key))'.$tail);
  $pdo->exec('CREATE TABLE IF NOT EXISTS shop_return_events (
   id '.$id.',case_id INT NOT NULL,event_type VARCHAR(40) NOT NULL,actor_id INT NOT NULL,reason VARCHAR(1000) NOT NULL,
   request_key VARCHAR(100) NOT NULL UNIQUE,input_hash VARCHAR(64) NOT NULL,snapshot_json TEXT NOT NULL,
   occurred_on DATE NOT NULL,recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   FOREIGN KEY(case_id) REFERENCES shop_return_cases(id),FOREIGN KEY(actor_id) REFERENCES treneri(id)
  )'.$tail);
 },
 'verify'=>static function(PDO $pdo):bool {try {$pdo->query('SELECT id,issue_movement_id,order_item_id,physical_state,return_movement_id,finance_state,exchange_delta_minor,exchange_reservation_id,exchange_issue_movement_id,revision,request_key,input_hash FROM shop_return_cases LIMIT 0');$pdo->query('SELECT id,case_id,correction_document_id,line_key,amount_minor,refund_decision_id FROM shop_return_finance_links LIMIT 0');$pdo->query('SELECT id,case_id,event_type,actor_id,request_key,input_hash,snapshot_json,occurred_on FROM shop_return_events LIMIT 0');return true;}catch(PDOException){return false;}}
];
