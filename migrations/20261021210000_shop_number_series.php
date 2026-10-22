<?php
declare(strict_types=1);

return [
    'id' => '20261021210000_shop_number_series',
    'up' => static function (PDO $pdo): void {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $suffix = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $id = $mysql ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $big = $mysql ? 'BIGINT UNSIGNED' : 'INTEGER';
        $pdo->exec('CREATE TABLE IF NOT EXISTS shop_accounting_groups (
            code INT PRIMARY KEY, name VARCHAR(120) NOT NULL
        )'.$suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS shop_accounting_categories (
            id '.$id.', group_code INT NOT NULL, category_code CHAR(2) NOT NULL,
            name VARCHAR(120) NOT NULL, active INT NOT NULL DEFAULT 1,
            UNIQUE(group_code,category_code),
            FOREIGN KEY(group_code) REFERENCES shop_accounting_groups(code)
        )'.$suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS shop_accounting_mappings (
            mapping_type VARCHAR(16) NOT NULL, mapping_key VARCHAR(500) NOT NULL,
            category_id '.$big.' NOT NULL, PRIMARY KEY(mapping_type,mapping_key),
            FOREIGN KEY(category_id) REFERENCES shop_accounting_categories(id)
        )'.$suffix);
        // This one row serializes settings changes, classification and allocation.
        $pdo->exec('CREATE TABLE IF NOT EXISTS shop_number_series_settings (
            id INT PRIMARY KEY, revision INT NOT NULL DEFAULT 0
        )'.$suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS shop_number_series_counters (
            category_id '.$big.' NOT NULL, series_year INT NOT NULL, next_number INT NOT NULL,
            PRIMARY KEY(category_id,series_year),
            FOREIGN KEY(category_id) REFERENCES shop_accounting_categories(id)
        )'.$suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS shop_order_number_assignments (
            order_id '.$big.' PRIMARY KEY, category_id '.$big.' NOT NULL,
            series_year INT NOT NULL, sequence_number INT NOT NULL,
            variable_symbol VARCHAR(10) NOT NULL UNIQUE, number_snapshot VARCHAR(32) NOT NULL UNIQUE,
            group_code INT NOT NULL, category_code CHAR(2) NOT NULL,
            group_name_snapshot VARCHAR(120) NOT NULL, category_name_snapshot VARCHAR(120) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(category_id,series_year,sequence_number),
            FOREIGN KEY(order_id) REFERENCES shop_orders(id),
            FOREIGN KEY(category_id) REFERENCES shop_accounting_categories(id)
        )'.$suffix);
        // source_type distinguishes three existing order item tables.
        $pdo->exec('CREATE TABLE IF NOT EXISTS shop_order_accounting_items (
            order_id '.$big.' NOT NULL, source_type VARCHAR(16) NOT NULL, source_item_id '.$big.' NOT NULL,
            category_id '.$big.' NOT NULL, group_code INT NOT NULL, category_code CHAR(2) NOT NULL,
            group_name_snapshot VARCHAR(120) NOT NULL, category_name_snapshot VARCHAR(120) NOT NULL,
            catalog_path_snapshot VARCHAR(500) NULL,
            PRIMARY KEY(source_type,source_item_id),
            FOREIGN KEY(order_id) REFERENCES shop_orders(id),
            FOREIGN KEY(category_id) REFERENCES shop_accounting_categories(id)
        )'.$suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS shop_number_series_events (
            id '.$id.', actor_id INT NOT NULL, action VARCHAR(40) NOT NULL,
            before_json TEXT NOT NULL, after_json TEXT NOT NULL, reason VARCHAR(1000) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'.$suffix);
        foreach ([1=>'Kroužky',2=>'Oddíl',3=>'Zboží',8=>'Nezařazeno',9=>'Smíšené objednávky'] as $code=>$name) {
            $s=$pdo->prepare('SELECT 1 FROM shop_accounting_groups WHERE code=?');$s->execute([$code]);
            if (!$s->fetchColumn()) $pdo->prepare('INSERT INTO shop_accounting_groups(code,name) VALUES(?,?)')->execute([$code,$name]);
        }
        foreach ([[10,1,'00','Kroužky – bez rozlišení'],[20,2,'00','Oddíl – bez rozlišení'],[30,3,'00','Zboží – bez rozlišení'],[31,3,'01','Knihy'],[32,3,'02','Oblečení'],[80,8,'00','Nezařazeno – doplnit mapování'],[90,9,'00','Smíšené objednávky']] as $row) {
            $s=$pdo->prepare('SELECT 1 FROM shop_accounting_categories WHERE id=?');$s->execute([$row[0]]);
            if (!$s->fetchColumn()) $pdo->prepare('INSERT INTO shop_accounting_categories(id,group_code,category_code,name) VALUES(?,?,?,?)')->execute($row);
        }
        foreach (['goods'=>80,'program'=>10,'event'=>80,'velodrome'=>80] as $kind=>$categoryId) {
            $s=$pdo->prepare("SELECT 1 FROM shop_accounting_mappings WHERE mapping_type='kind' AND mapping_key=?");$s->execute([$kind]);
            if (!$s->fetchColumn()) $pdo->prepare("INSERT INTO shop_accounting_mappings(mapping_type,mapping_key,category_id) VALUES('kind',?,?)")->execute([$kind,$categoryId]);
        }
        if (!$pdo->query('SELECT 1 FROM shop_number_series_settings WHERE id=1')->fetchColumn()) $pdo->exec('INSERT INTO shop_number_series_settings(id,revision) VALUES(1,0)');
    },
    'verify' => static function (PDO $pdo): bool {
        try {
            foreach ([
                'shop_accounting_groups'=>'code,name',
                'shop_accounting_categories'=>'id,group_code,category_code,name,active',
                'shop_accounting_mappings'=>'mapping_type,mapping_key,category_id',
                'shop_number_series_settings'=>'id,revision',
                'shop_number_series_counters'=>'category_id,series_year,next_number',
                'shop_order_number_assignments'=>'order_id,category_id,series_year,sequence_number,variable_symbol,number_snapshot,group_code,category_code,group_name_snapshot,category_name_snapshot,created_at',
                'shop_order_accounting_items'=>'order_id,source_type,source_item_id,category_id,group_code,category_code,group_name_snapshot,category_name_snapshot,catalog_path_snapshot',
                'shop_number_series_events'=>'id,actor_id,action,before_json,after_json,reason,created_at',
            ] as $table=>$columns) $pdo->query('SELECT '.$columns.' FROM '.$table.' LIMIT 0');
            return (bool)$pdo->query('SELECT 1 FROM shop_number_series_settings WHERE id=1')->fetchColumn()
                && (bool)$pdo->query("SELECT 1 FROM shop_accounting_categories WHERE group_code=9 AND category_code='00' AND active=1")->fetchColumn();
        } catch (PDOException) { return false; }
    },
];
