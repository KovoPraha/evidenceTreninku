<?php
declare(strict_types=1);
return ['id'=>'20261022233000_shop_stock_documents','up'=>static function(PDO $p):void{
 $mysql=$p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
 $p->exec('CREATE TABLE IF NOT EXISTS shop_stock_documents (movement_id BIGINT UNSIGNED PRIMARY KEY,snapshot_json TEXT NOT NULL,snapshot_sha256 CHAR(64) NOT NULL,storage_key VARCHAR(255) NOT NULL,pdf_sha256 CHAR(64) NOT NULL,actor_trainer_id INT NOT NULL,reason VARCHAR(1000) NOT NULL,recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(movement_id) REFERENCES shop_stock_movements(id))'.($mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':''));
},'verify'=>static function(PDO $p):bool{try{$p->query('SELECT movement_id,snapshot_sha256,storage_key,pdf_sha256 FROM shop_stock_documents LIMIT 0');return true;}catch(PDOException){return false;}}];
