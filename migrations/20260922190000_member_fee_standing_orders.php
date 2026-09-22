<?php
declare(strict_types=1);

$tableExists=static function(PDO$pdo,string$table):bool{
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'){$s=$pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");$s->execute([$table]);return(bool)$s->fetchColumn();}
    $s=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$table]);return(bool)$s->fetchColumn();
};

$variableSymbolIsUnique=static function(PDO$pdo):bool{
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){
        $rows=$pdo->query("SELECT INDEX_NAME,NON_UNIQUE,COLUMN_NAME,SEQ_IN_INDEX FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payments' ORDER BY INDEX_NAME,SEQ_IN_INDEX")->fetchAll(PDO::FETCH_ASSOC);
        $indexes=[];foreach($rows as$row)$indexes[(string)$row['INDEX_NAME']]['unique']=(int)$row['NON_UNIQUE']===0;$indexes[(string)$row['INDEX_NAME']]['columns'][]=(string)$row['COLUMN_NAME'];
        foreach($indexes as$index)if($index['unique']&&$index['columns']===['variable_symbol'])return true;
        return false;
    }
    foreach($pdo->query('PRAGMA index_list(payments)')->fetchAll(PDO::FETCH_ASSOC)as$index){
        if((int)$index['unique']!==1)continue;$name=str_replace("'","''",(string)$index['name']);$columns=$pdo->query("PRAGMA index_info('$name')")->fetchAll(PDO::FETCH_ASSOC);
        if(array_column($columns,'name')===['variable_symbol'])return true;
    }
    return false;
};

$allowRepeatedVariableSymbols=static function(PDO$pdo)use($variableSymbolIsUnique):void{
    if(!$variableSymbolIsUnique($pdo))return;
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){
        $index=$pdo->query("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payments' AND NON_UNIQUE=0 GROUP BY INDEX_NAME HAVING COUNT(*)=1 AND MAX(COLUMN_NAME)='variable_symbol' LIMIT 1")->fetchColumn();
        if(!is_string($index)||$index==='')throw new RuntimeException('Unique variable-symbol index was not found.');
        $pdo->exec('ALTER TABLE payments DROP INDEX `'.str_replace('`','``',$index).'`');
        $exists=$pdo->query("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payments' AND INDEX_NAME='idx_payment_variable_symbol' LIMIT 1")->fetchColumn();
        if(!$exists)$pdo->exec('ALTER TABLE payments ADD INDEX idx_payment_variable_symbol(variable_symbol)');
        return;
    }
    $create=$pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='payments'")->fetchColumn();
    if(!is_string($create)||$create==='')throw new RuntimeException('Payments table definition was not found.');
    $rewritten=preg_replace('/(variable_symbol\s+TEXT(?:\s+NOT\s+NULL)?)\s+UNIQUE/i','$1',$create,1,$constraintCount);
    $temporary='payments__'.'standing_order_migration';
    $rewritten=preg_replace('/^CREATE\s+TABLE\s+(?:"payments"|`payments`|\[payments\]|payments)/i','CREATE TABLE '.$temporary,$rewritten??'',1,$tableCount);
    if($constraintCount!==1||$tableCount!==1)throw new RuntimeException('SQLite payments uniqueness could not be rewritten safely.');
    $columns=array_map(static fn(array$row):string=>(string)$row['name'],$pdo->query('PRAGMA table_info(payments)')->fetchAll(PDO::FETCH_ASSOC));
    $quoted=implode(',',array_map(static fn(string$column):string=>'"'.str_replace('"','""',$column).'"',$columns));
    $indexes=array_values(array_filter($pdo->query("SELECT sql FROM sqlite_master WHERE type='index' AND tbl_name='payments' AND sql IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN),static fn(mixed$sql):bool=>is_string($sql)&&$sql!==''));
    $pdo->exec('PRAGMA foreign_keys=OFF');
    try{$pdo->beginTransaction();$pdo->exec($rewritten);$pdo->exec("INSERT INTO $temporary($quoted) SELECT $quoted FROM payments");$pdo->exec('DROP TABLE payments');$pdo->exec("ALTER TABLE $temporary RENAME TO payments");foreach($indexes as$sql)$pdo->exec($sql);$pdo->exec('CREATE INDEX IF NOT EXISTS idx_payment_variable_symbol ON payments(variable_symbol)');$pdo->commit();}
    catch(Throwable$exception){if($pdo->inTransaction())$pdo->rollBack();throw$exception;}
    finally{$pdo->exec('PRAGMA foreign_keys=ON');}
    if($pdo->query('PRAGMA foreign_key_check')->fetchColumn()!==false)throw new RuntimeException('Foreign-key check failed after rebuilding payments.');
};

return[
    'id'=>'20260922190000_member_fee_standing_orders',
    'up'=>static function(PDO$pdo)use($tableExists,$allowRepeatedVariableSymbols):void{
        foreach(['member_fee_plans','sportovci','payments']as$table)if(!$tableExists($pdo,$table))throw new RuntimeException('Required standing order table is missing: '.$table);
        $allowRepeatedVariableSymbols($pdo);
        $mysql=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        if(!$tableExists($pdo,'member_fee_standing_orders'))$pdo->exec($mysql?<<<'SQL'
            CREATE TABLE member_fee_standing_orders (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                plan_id BIGINT UNSIGNED NOT NULL,
                sportovec_id INT NOT NULL,
                variable_symbol VARCHAR(10) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_member_fee_standing_plan_person(plan_id,sportovec_id),
                UNIQUE KEY uq_member_fee_standing_vs(variable_symbol),
                CONSTRAINT fk_member_fee_standing_plan FOREIGN KEY(plan_id) REFERENCES member_fee_plans(id) ON DELETE RESTRICT,
                CONSTRAINT fk_member_fee_standing_person FOREIGN KEY(sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL:<<<'SQL'
            CREATE TABLE member_fee_standing_orders(id INTEGER PRIMARY KEY AUTOINCREMENT,plan_id INTEGER NOT NULL,sportovec_id INTEGER NOT NULL,variable_symbol TEXT NOT NULL UNIQUE,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(plan_id,sportovec_id),FOREIGN KEY(plan_id) REFERENCES member_fee_plans(id) ON DELETE RESTRICT,FOREIGN KEY(sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT)
            SQL);
    },
    'verify'=>static function(PDO$pdo)use($tableExists,$variableSymbolIsUnique):bool{return$tableExists($pdo,'member_fee_standing_orders')&&!$variableSymbolIsUnique($pdo);},
];
