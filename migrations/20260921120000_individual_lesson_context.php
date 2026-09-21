<?php
declare(strict_types=1);

$lessonContextColumnExists=static function(PDO $pdo,string $table='individualni_lekce',string $column='booking_context'):bool{
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){
        $statement=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');$statement->execute([$table,$column]);return(bool)$statement->fetchColumn();
    }
    foreach($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC)as$row)if((string)$row['name']===$column)return true;
    return false;
};

$lessonContextIndexExists=static function(PDO $pdo):bool{
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')return(bool)$pdo->query("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='individualni_lekce' AND INDEX_NAME='idx_individualni_lekce_context' LIMIT 1")->fetchColumn();
    return(bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type='index' AND name='idx_individualni_lekce_context' LIMIT 1")->fetchColumn();
};

return[
    'id'=>'20260921120000_individual_lesson_context',
    'up'=>static function(PDO $pdo)use($lessonContextColumnExists,$lessonContextIndexExists):void{
        $mysql=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        if(!$lessonContextColumnExists($pdo))$pdo->exec($mysql
            ?"ALTER TABLE individualni_lekce ADD COLUMN booking_context VARCHAR(32) NOT NULL DEFAULT 'individual_lesson' AFTER payment_method_policy"
            :"ALTER TABLE individualni_lekce ADD COLUMN booking_context TEXT NOT NULL DEFAULT 'individual_lesson'");
        if($lessonContextColumnExists($pdo,'sportovist','kod')&&$mysql){
            $pdo->exec("UPDATE individualni_lekce il JOIN sportovist s ON s.id=il.sportoviste_id SET il.booking_context='public_velodrome' WHERE s.kod='velodrom' AND (il.nazev='Veřejná hodina velodromu' OR il.public_exclusive_booking=1 OR EXISTS(SELECT 1 FROM public_velodrome_cart_items ci WHERE ci.lesson_id=il.id) OR EXISTS(SELECT 1 FROM public_velodrome_order_items oi WHERE oi.lesson_id=il.id))");
        }elseif($lessonContextColumnExists($pdo,'sportovist','kod')){
            $pdo->exec("UPDATE individualni_lekce SET booking_context='public_velodrome' WHERE sportoviste_id IN(SELECT id FROM sportovist WHERE kod='velodrom') AND (nazev='Veřejná hodina velodromu' OR public_exclusive_booking=1 OR EXISTS(SELECT 1 FROM public_velodrome_cart_items ci WHERE ci.lesson_id=individualni_lekce.id) OR EXISTS(SELECT 1 FROM public_velodrome_order_items oi WHERE oi.lesson_id=individualni_lekce.id))");
        }
        if(!$lessonContextIndexExists($pdo)){
            $columns=['booking_context'];
            foreach(['datum','stav']as$column)if($lessonContextColumnExists($pdo,'individualni_lekce',$column))$columns[]=$column;
            $pdo->exec('CREATE INDEX idx_individualni_lekce_context ON individualni_lekce('.implode(',',$columns).')');
        }
    },
    'verify'=>static fn(PDO $pdo):bool=> $lessonContextColumnExists($pdo)&&$lessonContextIndexExists($pdo),
];
