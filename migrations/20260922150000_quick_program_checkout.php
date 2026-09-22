<?php
declare(strict_types=1);

$columnExists=static function(PDO $pdo,string $table,string $column):bool{
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){
        $statement=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $statement->execute([$table,$column]);return(bool)$statement->fetchColumn();
    }
    foreach($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC)as$row)if((string)$row['name']===$column)return true;
    return false;
};
$indexExists=static function(PDO $pdo,string $table,string $index):bool{
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){
        $statement=$pdo->prepare('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
        $statement->execute([$table,$index]);return(bool)$statement->fetchColumn();
    }
    $statement=$pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='index' AND name=?");$statement->execute([$index]);return(bool)$statement->fetchColumn();
};
$constraintExists=static function(PDO $pdo,string $table,string $constraint):bool{
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')return false;
    $statement=$pdo->prepare('SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?');
    $statement->execute([$table,$constraint]);return(bool)$statement->fetchColumn();
};

return[
    'id'=>'20260922150000_quick_program_checkout',
    'up'=>static function(PDO $pdo)use($columnExists,$indexExists,$constraintExists):void{
        $mysql=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        if(!$columnExists($pdo,'shop_order_items','athlete_registration_request_id')){
            $pdo->exec('ALTER TABLE shop_order_items ADD COLUMN athlete_registration_request_id '.($mysql?'BIGINT UNSIGNED NULL':'INTEGER NULL REFERENCES account_person_claim_requests(id) ON DELETE RESTRICT'));
        }
        if(!$indexExists($pdo,'shop_order_items','idx_shop_order_item_athlete_request')){
            $pdo->exec('CREATE INDEX idx_shop_order_item_athlete_request ON shop_order_items(athlete_registration_request_id)');
        }
        if($mysql&&!$constraintExists($pdo,'shop_order_items','fk_shop_order_item_athlete_request')){
            $pdo->exec('ALTER TABLE shop_order_items ADD CONSTRAINT fk_shop_order_item_athlete_request FOREIGN KEY(athlete_registration_request_id) REFERENCES account_person_claim_requests(id) ON DELETE RESTRICT');
        }
    },
    'verify'=>static function(PDO $pdo)use($columnExists,$indexExists,$constraintExists):bool{
        return$columnExists($pdo,'shop_order_items','athlete_registration_request_id')
            &&$indexExists($pdo,'shop_order_items','idx_shop_order_item_athlete_request')
            &&((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'||$constraintExists($pdo,'shop_order_items','fk_shop_order_item_athlete_request'));
    },
];
