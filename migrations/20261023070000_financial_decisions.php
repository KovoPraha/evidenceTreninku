<?php
declare(strict_types=1);
return [
 'id'=>'20261023070000_financial_decisions',
 'up'=>static function(PDO $p):void {
  $mysql=$p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';$id=$mysql?'INT AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';$tail=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
  $p->exec('CREATE TABLE IF NOT EXISTS financial_decisions (
   id '.$id.',title VARCHAR(160) NOT NULL,kind VARCHAR(30) NOT NULL,state VARCHAR(20) NOT NULL,
   valid_from DATE NOT NULL,valid_to DATE NULL,source_reference VARCHAR(1000) NOT NULL,decision_text TEXT NOT NULL,
   original_location VARCHAR(1000) NOT NULL,storage_key VARCHAR(255) NULL,file_sha256 CHAR(64) NULL,
   replaces_id INT NULL UNIQUE,created_by INT NOT NULL,reason VARCHAR(1000) NOT NULL,
   created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
   FOREIGN KEY(replaces_id) REFERENCES financial_decisions(id),FOREIGN KEY(created_by) REFERENCES treneri(id)
  )'.$tail);
 },
 'verify'=>static function(PDO $p):bool{try{$p->query('SELECT id,title,kind,state,valid_from,valid_to,source_reference,decision_text,original_location,storage_key,file_sha256,replaces_id,created_by,reason,created_at FROM financial_decisions LIMIT 0');return true;}catch(PDOException){return false;}}
];
