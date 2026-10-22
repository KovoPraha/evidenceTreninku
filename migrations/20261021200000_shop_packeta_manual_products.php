<?php
declare(strict_types=1);

$hasColumn=static function(PDO $pdo):bool {
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')return(bool)$pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_products' AND COLUMN_NAME='packeta_shipping_enabled'")->fetchColumn();
    foreach($pdo->query('PRAGMA table_info(shop_products)')->fetchAll(PDO::FETCH_ASSOC)as$row)if($row['name']==='packeta_shipping_enabled')return true;
    return false;
};
return [
    'id'=>'20261021200000_shop_packeta_manual_products',
    'up'=>static function(PDO $pdo)use($hasColumn):void {
        if(!$hasColumn($pdo)){
            $definition=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?'TINYINT(1)':'INTEGER';
            $pdo->exec('ALTER TABLE shop_products ADD COLUMN packeta_shipping_enabled '.$definition.' NOT NULL DEFAULT 0');
            // Owner confirmed that existing physical goods can all be shipped.
            $pdo->exec("UPDATE shop_products SET packeta_shipping_enabled=1 WHERE offer_type='goods' AND item_type='product'");
            $pdo->exec("UPDATE shop_packeta_settings SET price_minor=15000 WHERE id=1 AND enabled=0 AND price_minor=0 AND widget_api_key=''");
        }
        // Seed once; a repeated migration must never overwrite an admin's price.
        $pdo->exec("INSERT INTO shop_packeta_settings(id,enabled,price_minor,widget_api_key) SELECT 1,0,15000,'' WHERE NOT EXISTS(SELECT 1 FROM shop_packeta_settings WHERE id=1)");
    },
    'verify'=>static function(PDO $pdo)use($hasColumn):bool {
        return $hasColumn($pdo)&&(bool)$pdo->query('SELECT 1 FROM shop_packeta_settings WHERE id=1')->fetchColumn();
    },
];
