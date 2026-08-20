<?php
declare(strict_types=1);

$catalogManagementTableExists=static function(PDO$pdo,string$table):bool{
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){
        $statement=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $statement->execute([$table]);return(bool)$statement->fetchColumn();
    }
    $statement=$pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");$statement->execute([$table]);return(bool)$statement->fetchColumn();
};
$catalogManagementColumnExists=static function(PDO$pdo,string$table,string$column):bool{
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){
        $statement=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
        $statement->execute([$table,$column]);return(bool)$statement->fetchColumn();
    }
    foreach($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC)as$row)if((string)$row['name']===$column)return true;
    return false;
};

return[
    'id'=>'20260821120000_shop_catalog_management',
    'up'=>static function(PDO$pdo)use($catalogManagementTableExists,$catalogManagementColumnExists):void{
        if(!$catalogManagementTableExists($pdo,'shop_products'))throw new RuntimeException('Required shop_products table is missing.');
        if(!$catalogManagementColumnExists($pdo,'shop_products','sort_order')){
            $pdo->exec((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'
                ?'ALTER TABLE shop_products ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER catalog_status'
                :'ALTER TABLE shop_products ADD COLUMN sort_order INTEGER NOT NULL DEFAULT 0');
        }
    },
    'verify'=>static fn(PDO$pdo):bool=>$catalogManagementTableExists($pdo,'shop_products')
        &&$catalogManagementColumnExists($pdo,'shop_products','sort_order'),
];
