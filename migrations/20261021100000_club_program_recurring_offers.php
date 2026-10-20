<?php
declare(strict_types=1);

$columnExists=static function(PDO $pdo,string $table,string $column):bool{
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){
        $q=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $q->execute([$table,$column]);return(bool)$q->fetchColumn();
    }
    foreach($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC)as$row)if((string)$row['name']===$column)return true;
    return false;
};
$tableExists=static function(PDO $pdo,string $table):bool{
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){
        $q=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);return(bool)$q->fetchColumn();
    }
    $q=$pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");$q->execute([$table]);return(bool)$q->fetchColumn();
};

return [
    'id'=>'20261021100000_club_program_recurring_offers',
    'up'=>static function(PDO $pdo)use($columnExists,$tableExists):void{
        foreach(['club_program_offers','shop_order_items','shop_variants']as$table)if(!$tableExists($pdo,$table))throw new RuntimeException('Missing table: '.$table);
        $mysql=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        if(!$columnExists($pdo,'shop_order_items','club_program_offer_id')){
            $pdo->exec($mysql
                ? 'ALTER TABLE shop_order_items ADD COLUMN club_program_offer_id BIGINT UNSIGNED NULL AFTER variant_id, ADD INDEX idx_shop_order_program_offer (club_program_offer_id), ADD CONSTRAINT fk_shop_order_program_offer FOREIGN KEY (club_program_offer_id) REFERENCES club_program_offers(id) ON DELETE RESTRICT'
                : 'ALTER TABLE shop_order_items ADD COLUMN club_program_offer_id INTEGER NULL');
        }
        $pdo->exec('UPDATE shop_order_items SET club_program_offer_id=(SELECT o.id FROM club_program_offers o WHERE o.variant_id=shop_order_items.variant_id AND o.product_id=shop_order_items.product_id) WHERE club_program_offer_id IS NULL AND EXISTS(SELECT 1 FROM club_program_offers o WHERE o.variant_id=shop_order_items.variant_id AND o.product_id=shop_order_items.product_id)');
        if(!$mysql)$pdo->exec('CREATE INDEX IF NOT EXISTS idx_shop_order_program_offer ON shop_order_items(club_program_offer_id)');
        if(!$columnExists($pdo,'club_program_offers','recurrence_source_offer_id')){
            $pdo->exec($mysql
                ? 'ALTER TABLE club_program_offers ADD COLUMN recurrence_source_offer_id BIGINT UNSIGNED NULL AFTER variant_id, ADD INDEX idx_club_program_recurrence_source (recurrence_source_offer_id), ADD CONSTRAINT fk_club_program_recurrence_source FOREIGN KEY (recurrence_source_offer_id) REFERENCES club_program_offers(id) ON DELETE RESTRICT'
                : 'ALTER TABLE club_program_offers ADD COLUMN recurrence_source_offer_id INTEGER NULL');
        }
        if(!$mysql)$pdo->exec('CREATE INDEX IF NOT EXISTS idx_club_program_recurrence_source ON club_program_offers(recurrence_source_offer_id)');
        if(!$tableExists($pdo,'club_program_recurrence_rules'))$pdo->exec($mysql?<<<'SQL'
            CREATE TABLE club_program_recurrence_rules (
                source_offer_id BIGINT UNSIGNED PRIMARY KEY,
                enabled TINYINT(1) NOT NULL DEFAULT 0,
                horizon_years TINYINT UNSIGNED NOT NULL DEFAULT 1,
                updated_by_trainer_id INT NOT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_club_program_rule_source FOREIGN KEY (source_offer_id) REFERENCES club_program_offers(id) ON DELETE RESTRICT,
                CONSTRAINT fk_club_program_rule_actor FOREIGN KEY (updated_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL:<<<'SQL'
            CREATE TABLE club_program_recurrence_rules(source_offer_id INTEGER PRIMARY KEY,enabled INTEGER NOT NULL DEFAULT 0,horizon_years INTEGER NOT NULL DEFAULT 1,updated_by_trainer_id INTEGER NOT NULL,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(source_offer_id) REFERENCES club_program_offers(id) ON DELETE RESTRICT,FOREIGN KEY(updated_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT)
            SQL);
    },
    'verify'=>static function(PDO $pdo)use($columnExists,$tableExists):bool{
        if(!$columnExists($pdo,'shop_order_items','club_program_offer_id')||!$columnExists($pdo,'club_program_offers','recurrence_source_offer_id')||!$tableExists($pdo,'club_program_recurrence_rules'))return false;
        $q=$pdo->query('SELECT COUNT(*) FROM shop_order_items oi WHERE oi.club_program_offer_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM club_program_offers o WHERE o.id=oi.club_program_offer_id AND o.variant_id=oi.variant_id AND o.product_id=oi.product_id)');
        return(int)$q->fetchColumn()===0;
    },
];
