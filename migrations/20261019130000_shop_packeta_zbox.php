<?php
declare(strict_types=1);

$tableExists=static function(PDO $pdo,string $table):bool{
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){$q=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);return(bool)$q->fetchColumn();}
    $q=$pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");$q->execute([$table]);return(bool)$q->fetchColumn();
};
$columnExists=static function(PDO $pdo,string $table,string $column):bool{
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){$q=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$q->execute([$table,$column]);return(bool)$q->fetchColumn();}
    foreach($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC)as$row)if($row['name']===$column)return true;return false;
};
return [
    'id'=>'20261019130000_shop_packeta_zbox',
    'up'=>static function(PDO $pdo)use($tableExists,$columnExists):void{
        if(!$tableExists($pdo,'shop_orders'))throw new RuntimeException('Chybí tabulka objednávek.');
        $mysql=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        if(!$tableExists($pdo,'shop_packeta_settings'))$pdo->exec($mysql?<<<'SQL'
CREATE TABLE shop_packeta_settings (
 id TINYINT UNSIGNED PRIMARY KEY,
 enabled TINYINT(1) NOT NULL DEFAULT 0,
 price_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
 widget_api_key VARCHAR(64) NOT NULL DEFAULT '',
 updated_by_trainer_id INT NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL:<<<'SQL'
CREATE TABLE shop_packeta_settings (
 id INTEGER PRIMARY KEY,
 enabled INTEGER NOT NULL DEFAULT 0,
 price_minor INTEGER NOT NULL DEFAULT 0,
 widget_api_key TEXT NOT NULL DEFAULT '',
 updated_by_trainer_id INTEGER NULL,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);
        if(!$tableExists($pdo,'shop_packeta_settings_events'))$pdo->exec($mysql?<<<'SQL'
CREATE TABLE shop_packeta_settings_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 actor_trainer_id INT NOT NULL,
 before_json LONGTEXT NOT NULL,
 after_json LONGTEXT NOT NULL,
 reason VARCHAR(1000) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL:<<<'SQL'
CREATE TABLE shop_packeta_settings_events (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 actor_trainer_id INTEGER NOT NULL,
 before_json TEXT NOT NULL,
 after_json TEXT NOT NULL,
 reason TEXT NOT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);
        $columns=[
            'packeta_point_id'=>$mysql?'VARCHAR(32) NULL':'TEXT NULL',
            'packeta_point_name'=>$mysql?'VARCHAR(255) NULL':'TEXT NULL',
            'packeta_point_address'=>$mysql?'VARCHAR(500) NULL':'TEXT NULL',
            'packeta_tracking_number'=>$mysql?'VARCHAR(64) NULL':'TEXT NULL',
            'packeta_consignment_code'=>$mysql?'CHAR(9) NULL':'TEXT NULL',
            'packeta_deposited_at'=>$mysql?'DATETIME NULL':'TEXT NULL',
        ];
        foreach($columns as$name=>$definition)if(!$columnExists($pdo,'shop_orders',$name))$pdo->exec('ALTER TABLE shop_orders ADD COLUMN '.$name.' '.$definition);
    },
    'verify'=>static function(PDO $pdo)use($tableExists,$columnExists):bool{
        if(!$tableExists($pdo,'shop_packeta_settings')||!$tableExists($pdo,'shop_packeta_settings_events'))return false;
        foreach(['packeta_point_id','packeta_point_name','packeta_point_address','packeta_tracking_number','packeta_consignment_code','packeta_deposited_at']as$column)if(!$columnExists($pdo,'shop_orders',$column))return false;
        return true;
    },
];
