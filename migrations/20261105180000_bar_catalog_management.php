<?php

declare(strict_types=1);

return [
    'id' => '20261105180000_bar_catalog_management',
    'up' => static function (PDO $pdo): void {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $columns = $mysql ? $pdo->query('SHOW COLUMNS FROM kis_bar_categories')->fetchAll(PDO::FETCH_COLUMN)
            : array_column($pdo->query('PRAGMA table_info(kis_bar_categories)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!in_array('lock_version', $columns, true)) {
            $pdo->exec('ALTER TABLE kis_bar_categories ADD COLUMN lock_version INT NOT NULL DEFAULT 1');
        }
    },
    'verify' => static function (PDO $pdo): bool {
        $pdo->query('SELECT id,lock_version FROM kis_bar_categories WHERE 1=0');
        return true;
    },
];
