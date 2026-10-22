<?php
declare(strict_types=1);
return [
'id'=>'20261022221000_accounting_documents',
'up'=>static function(PDO $pdo):void {
 $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
 $id=$mysql?'INT AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
 $suffix=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
 $pdo->exec('CREATE TABLE IF NOT EXISTS accounting_issuer_versions (id '.$id.',snapshot_json TEXT NOT NULL,approved_by INT NOT NULL,approval_evidence TEXT NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)'.$suffix);
 $pdo->exec('CREATE TABLE IF NOT EXISTS accounting_rules (id '.$id.',category_code VARCHAR(40) NOT NULL,activity VARCHAR(100) NOT NULL,title VARCHAR(200) NOT NULL,source_type VARCHAR(30) NOT NULL,document_kind VARCHAR(30) NOT NULL,valid_from DATE NOT NULL,approved_by INT NOT NULL,approval_evidence TEXT NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)'.$suffix);
 $pdo->exec('CREATE TABLE IF NOT EXISTS accounting_document_series (id '.$id.',kind VARCHAR(30) NOT NULL,series_year INT NOT NULL,prefix VARCHAR(20) NOT NULL,next_number INT NOT NULL,configured_by INT NOT NULL,reason TEXT NOT NULL,UNIQUE(kind,series_year),UNIQUE(prefix,series_year))'.$suffix);
 $pdo->exec('CREATE TABLE IF NOT EXISTS accounting_documents (id '.$id.',kind VARCHAR(30) NOT NULL,source_type VARCHAR(30) NOT NULL,source_id BIGINT NOT NULL,original_document_id INT NULL,idempotency_key VARCHAR(160) NOT NULL UNIQUE,source_unique_key VARCHAR(160) NULL UNIQUE,status VARCHAR(20) NOT NULL,number VARCHAR(80) NULL UNIQUE,series_id INT NULL,snapshot_json TEXT NOT NULL,snapshot_sha256 VARCHAR(64) NOT NULL,total_minor BIGINT NOT NULL,currency VARCHAR(3) NOT NULL,effective_on DATE NOT NULL,issued_at DATETIME NULL,pdf_storage_key VARCHAR(200) NULL,pdf_sha256 VARCHAR(64) NULL,created_by INT NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(original_document_id) REFERENCES accounting_documents(id),FOREIGN KEY(series_id) REFERENCES accounting_document_series(id))'.$suffix);
 $pdo->exec('CREATE TABLE IF NOT EXISTS accounting_document_events (id '.$id.',document_id INT NOT NULL,actor_id INT NOT NULL,action VARCHAR(30) NOT NULL,reason TEXT NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(document_id) REFERENCES accounting_documents(id))'.$suffix);
},
'verify'=>static function(PDO $pdo):bool {try {foreach(['accounting_issuer_versions','accounting_rules','accounting_document_series','accounting_documents','accounting_document_events'] as $t)$pdo->query('SELECT id FROM '.$t.' LIMIT 0');return true;}catch(PDOException){return false;}}
];
