<?php
declare(strict_types=1);

$attributeTableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement=$pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

$attributeColumnExists = static function (PDO $pdo, string $table, string $column): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
        $statement->execute([$table,$column]);
        return (bool)$statement->fetchColumn();
    }
    foreach($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if((string)$row['name']===$column)return true;
    }
    return false;
};

return [
    'id'=>'20260821090000_shop_attribute_definitions',
    'up'=>static function(PDO $pdo)use($attributeTableExists):void{
        if(!$attributeTableExists($pdo,'shop_variants')||!$attributeTableExists($pdo,'treneri')){
            throw new RuntimeException('Required attribute dictionary tables are missing.');
        }
        $mysql=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        if(!$attributeTableExists($pdo,'shop_attribute_definitions')){
            $pdo->exec($mysql?<<<'SQL'
                CREATE TABLE shop_attribute_definitions (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    attribute_key VARCHAR(191) NOT NULL UNIQUE,
                    display_name VARCHAR(255) NOT NULL,
                    value_type VARCHAR(16) NOT NULL,
                    unit VARCHAR(32) NULL,
                    sort_order INT NOT NULL DEFAULT 0,
                    show_in_listing TINYINT(1) NOT NULL DEFAULT 0,
                    show_in_detail TINYINT(1) NOT NULL DEFAULT 1,
                    active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY idx_shop_attribute_display (active,sort_order,display_name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL:<<<'SQL'
                CREATE TABLE shop_attribute_definitions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    attribute_key TEXT NOT NULL UNIQUE,
                    display_name TEXT NOT NULL,
                    value_type TEXT NOT NULL,
                    unit TEXT NULL,
                    sort_order INTEGER NOT NULL DEFAULT 0,
                    show_in_listing INTEGER NOT NULL DEFAULT 0,
                    show_in_detail INTEGER NOT NULL DEFAULT 1,
                    active INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
                SQL);
        }
        if(!$attributeTableExists($pdo,'shop_attribute_choices')){
            $pdo->exec($mysql?<<<'SQL'
                CREATE TABLE shop_attribute_choices (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    attribute_id BIGINT UNSIGNED NOT NULL,
                    value VARCHAR(255) NOT NULL,
                    sort_order INT NOT NULL DEFAULT 0,
                    active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_shop_attribute_choice (attribute_id,value),
                    KEY idx_shop_attribute_choice_order (attribute_id,active,sort_order,id),
                    CONSTRAINT fk_shop_attribute_choice_definition FOREIGN KEY(attribute_id) REFERENCES shop_attribute_definitions(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL:<<<'SQL'
                CREATE TABLE shop_attribute_choices (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    attribute_id INTEGER NOT NULL,
                    value TEXT NOT NULL,
                    sort_order INTEGER NOT NULL DEFAULT 0,
                    active INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(attribute_id,value),
                    FOREIGN KEY(attribute_id) REFERENCES shop_attribute_definitions(id) ON DELETE RESTRICT
                )
                SQL);
        }
        if(!$attributeTableExists($pdo,'shop_attribute_definition_events')){
            $pdo->exec($mysql?<<<'SQL'
                CREATE TABLE shop_attribute_definition_events (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    attribute_id BIGINT UNSIGNED NOT NULL,
                    attribute_key VARCHAR(191) NOT NULL,
                    actor_type VARCHAR(24) NOT NULL,
                    actor_id BIGINT NOT NULL,
                    action VARCHAR(48) NOT NULL,
                    before_json LONGTEXT NULL,
                    after_json LONGTEXT NOT NULL,
                    reason TEXT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_shop_attribute_event_definition (attribute_id,id),
                    KEY idx_shop_attribute_event_created (created_at,id),
                    CONSTRAINT fk_shop_attribute_event_definition FOREIGN KEY(attribute_id) REFERENCES shop_attribute_definitions(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL:<<<'SQL'
                CREATE TABLE shop_attribute_definition_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    attribute_id INTEGER NOT NULL,
                    attribute_key TEXT NOT NULL,
                    actor_type TEXT NOT NULL,
                    actor_id INTEGER NOT NULL,
                    action TEXT NOT NULL,
                    before_json TEXT NULL,
                    after_json TEXT NOT NULL,
                    reason TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(attribute_id) REFERENCES shop_attribute_definitions(id) ON DELETE RESTRICT
                )
                SQL);
        }
    },
    'verify'=>static function(PDO $pdo)use($attributeTableExists,$attributeColumnExists):bool{
        foreach(['shop_attribute_definitions','shop_attribute_choices','shop_attribute_definition_events']as$table)if(!$attributeTableExists($pdo,$table))return false;
        foreach(['attribute_key','display_name','value_type','unit','sort_order','show_in_listing','show_in_detail','active','created_at','updated_at']as$column)if(!$attributeColumnExists($pdo,'shop_attribute_definitions',$column))return false;
        foreach(['attribute_id','value','sort_order','active','created_at','updated_at']as$column)if(!$attributeColumnExists($pdo,'shop_attribute_choices',$column))return false;
        foreach(['attribute_id','attribute_key','actor_type','actor_id','action','before_json','after_json','reason','created_at']as$column)if(!$attributeColumnExists($pdo,'shop_attribute_definition_events',$column))return false;
        return true;
    },
];
