<?php
declare(strict_types=1);
return [
    'id'=>'20261022223000_accounting_exports',
    'up'=>static function(PDO $pdo):void {
        $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        $id=$mysql?'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tail=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
        $pdo->exec('CREATE TABLE IF NOT EXISTS accounting_export_mutex (id INT PRIMARY KEY)'.$tail);
        $pdo->exec(($mysql?'INSERT IGNORE':'INSERT OR IGNORE').' INTO accounting_export_mutex (id) VALUES (1)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS accounting_export_batches (
            id '.$id.', request_key VARCHAR(100) NOT NULL UNIQUE, request_sha256 CHAR(64) NOT NULL,
            period_from DATE NOT NULL, period_to DATE NOT NULL, revision INT NOT NULL,
            predecessor_id BIGINT UNSIGNED NULL, snapshot_at DATETIME NOT NULL,
            scope_code VARCHAR(40) NOT NULL, status VARCHAR(24) NOT NULL,
            snapshot_json LONGTEXT NOT NULL, snapshot_sha256 CHAR(64) NOT NULL,
            storage_key VARCHAR(255) NOT NULL, archive_sha256 CHAR(64) NOT NULL,
            created_by INT NOT NULL, reason VARCHAR(1000) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(period_from,period_to,revision),
            FOREIGN KEY(predecessor_id) REFERENCES accounting_export_batches(id),
            FOREIGN KEY(created_by) REFERENCES treneri(id)
        )'.$tail);
        $pdo->exec('CREATE TABLE IF NOT EXISTS accounting_export_events (
            id '.$id.', batch_id BIGINT UNSIGNED NOT NULL, actor_trainer_id INT NOT NULL,
            action VARCHAR(30) NOT NULL, reason VARCHAR(1000) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(batch_id) REFERENCES accounting_export_batches(id),
            FOREIGN KEY(actor_trainer_id) REFERENCES treneri(id)
        )'.$tail);
    },
    'verify'=>static function(PDO $pdo):bool {
        try {
            $pdo->query('SELECT id FROM accounting_export_mutex LIMIT 0');
            $pdo->query('SELECT id,request_key,request_sha256,period_from,period_to,revision,predecessor_id,snapshot_at,scope_code,status,snapshot_json,snapshot_sha256,storage_key,archive_sha256,created_by,reason,created_at FROM accounting_export_batches LIMIT 0');
            $pdo->query('SELECT id,batch_id,actor_trainer_id,action,reason,created_at FROM accounting_export_events LIMIT 0');return true;
        }catch(PDOException){return false;}
    },
];
