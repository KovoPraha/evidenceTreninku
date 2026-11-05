<?php

declare(strict_types=1);

return [
    'id' => '20261105160000_bar_transfer',
    'up' => static function (PDO $pdo): void {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $columns = $mysql ? $pdo->query('SHOW COLUMNS FROM treneri')->fetchAll(PDO::FETCH_COLUMN)
            : array_column($pdo->query('PRAGMA table_info(treneri)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!in_array('technical_email', $columns, true)) {
            $pdo->exec('ALTER TABLE treneri ADD COLUMN technical_email INT NOT NULL DEFAULT 0');
        }
        $id = $mysql ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $bigint = $mysql ? 'BIGINT UNSIGNED' : 'INTEGER';
        $suffix = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_assets (
            id $id, source_name VARCHAR(255) NOT NULL UNIQUE, storage_key VARCHAR(255) NOT NULL,
            sha256_hex VARCHAR(64) NOT NULL, mime_type VARCHAR(40) NOT NULL,
            byte_size INT NOT NULL, width_px INT NOT NULL, height_px INT NOT NULL
        )$suffix");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_import_runs (
            id $id, bundle_sha256 VARCHAR(64) NOT NULL UNIQUE, trainer_id INT NOT NULL,
            summary_json TEXT NOT NULL, created_by INT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT,
            FOREIGN KEY(created_by) REFERENCES treneri(id) ON DELETE RESTRICT
        )$suffix");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_state (id INT NOT NULL PRIMARY KEY, import_run_id $bigint NULL)$suffix");
        $insert = $mysql ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
        $pdo->exec("$insert INTO kis_bar_state(id) VALUES(1)");
    },
    'verify' => static function (PDO $pdo): bool {
        $pdo->query('SELECT technical_email FROM treneri WHERE 1=0');
        $pdo->query('SELECT id,source_name,storage_key,sha256_hex,mime_type,byte_size,width_px,height_px FROM kis_bar_assets WHERE 1=0');
        $pdo->query('SELECT id,bundle_sha256,trainer_id,summary_json,created_by FROM kis_bar_import_runs WHERE 1=0');
        return (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_state WHERE id=1')->fetchColumn() === 1;
    },
];
