<?php

declare(strict_types=1);

return [
    'id' => '20261104180000_tv_programs',
    'up' => static function (PDO $pdo): void {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $id = $mysql ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $bigint = $mysql ? 'BIGINT UNSIGNED' : 'INTEGER';
        $text = $mysql ? 'LONGTEXT' : 'TEXT';
        $suffix = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $pdo->exec("CREATE TABLE IF NOT EXISTS tv_programs (
            id $id,
            name VARCHAR(120) NOT NULL,
            draft_json $text NOT NULL,
            lock_version INT NOT NULL DEFAULT 1,
            published_revision_id $bigint NULL,
            archived INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )$suffix");
        $pdo->exec("CREATE TABLE IF NOT EXISTS tv_assets (
            id $id,
            storage_key VARCHAR(200) NOT NULL UNIQUE,
            width_px INT NOT NULL,
            height_px INT NOT NULL,
            byte_size INT NOT NULL,
            sha256_hex VARCHAR(64) NOT NULL,
            created_by INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(created_by) REFERENCES treneri(id) ON DELETE RESTRICT
        )$suffix");
        $pdo->exec("CREATE TABLE IF NOT EXISTS tv_program_revisions (
            id $id,
            program_id $bigint NOT NULL,
            document_json $text NOT NULL,
            created_by INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(program_id) REFERENCES tv_programs(id) ON DELETE RESTRICT,
            FOREIGN KEY(created_by) REFERENCES treneri(id) ON DELETE RESTRICT
        )$suffix");
        $pdo->exec("CREATE TABLE IF NOT EXISTS tv_revision_assets (
            revision_id $bigint NOT NULL,
            asset_id $bigint NOT NULL,
            PRIMARY KEY(revision_id,asset_id),
            FOREIGN KEY(revision_id) REFERENCES tv_program_revisions(id) ON DELETE RESTRICT,
            FOREIGN KEY(asset_id) REFERENCES tv_assets(id) ON DELETE RESTRICT
        )$suffix");
        $pdo->exec("CREATE TABLE IF NOT EXISTS tv_program_events (
            id $id,
            program_id $bigint NOT NULL,
            actor_id INT NOT NULL,
            action VARCHAR(40) NOT NULL,
            detail_json TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(program_id) REFERENCES tv_programs(id) ON DELETE RESTRICT,
            FOREIGN KEY(actor_id) REFERENCES treneri(id) ON DELETE RESTRICT
        )$suffix");
        $insert = $mysql ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
        $pdo->exec("$insert INTO staff_positions(code,label,sort_order,active) VALUES('tv_operator','Obsluha TV',75,1)");
    },
    'verify' => static function (PDO $pdo): bool {
        $pdo->query('SELECT id,draft_json,lock_version,published_revision_id,archived FROM tv_programs WHERE 1=0');
        $pdo->query('SELECT id,storage_key,width_px,height_px,byte_size,sha256_hex,created_by FROM tv_assets WHERE 1=0');
        $pdo->query('SELECT id,program_id,document_json,created_by FROM tv_program_revisions WHERE 1=0');
        $pdo->query('SELECT revision_id,asset_id FROM tv_revision_assets WHERE 1=0');
        $pdo->query('SELECT id,program_id,actor_id,action,detail_json FROM tv_program_events WHERE 1=0');
        return (bool)$pdo->query("SELECT 1 FROM staff_positions WHERE code='tv_operator' AND active=1")->fetchColumn();
    },
];
