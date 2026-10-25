<?php
declare(strict_types=1);

/** A payer can use one symbol for several charges. Payment identity remains (payable_type,payable_id). */
function paymentVariableSymbolUniqueIndexes(PDO $pdo):array {
    if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){
        $rows=$pdo->query("SELECT INDEX_NAME,NON_UNIQUE,COLUMN_NAME,SEQ_IN_INDEX FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payments' ORDER BY INDEX_NAME,SEQ_IN_INDEX")->fetchAll(PDO::FETCH_ASSOC);
        $indexes=[];
        foreach($rows as $row){$name=(string)$row['INDEX_NAME'];$indexes[$name]['unique']=(int)$row['NON_UNIQUE']===0;$indexes[$name]['columns'][]=(string)$row['COLUMN_NAME'];}
        return array_keys(array_filter($indexes,static fn(array $index):bool=>$index['unique']&&$index['columns']===['variable_symbol']));
    }
    $out=[];foreach($pdo->query('PRAGMA index_list(payments)')->fetchAll(PDO::FETCH_ASSOC) as $index){if((int)$index['unique']!==1)continue;$name=str_replace("'","''",(string)$index['name']);if(array_column($pdo->query("PRAGMA index_info('$name')")->fetchAll(PDO::FETCH_ASSOC),'name')===['variable_symbol'])$out[]=$index['name'];}return $out;
}
function paymentVariableSymbolRepairMysql(PDO $pdo):void {
    if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')throw new LogicException('MariaDB/MySQL schema repair required.');
    $indexes=paymentVariableSymbolUniqueIndexes($pdo);
    if(in_array('PRIMARY',$indexes,true))throw new RuntimeException('Unexpected payment primary key; manual schema review required.');
    // DDL only: never renumber existing payments, payer symbols, or charges.
    foreach($indexes as $name)$pdo->exec('ALTER TABLE payments DROP INDEX `'.str_replace('`','``',$name).'`');
    $exists=$pdo->query("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payments' AND COLUMN_NAME='variable_symbol' AND SEQ_IN_INDEX=1 LIMIT 1")->fetchColumn();
    if(!$exists)$pdo->exec('ALTER TABLE payments ADD INDEX idx_payment_variable_symbol(variable_symbol)');
    if(paymentVariableSymbolUniqueIndexes($pdo))throw new RuntimeException('Payment symbol uniqueness repair failed.');
}

/** Correct execution of one frozen historical definition. Its file/ledger checksum is not rewritten. */
function paymentStandingOrderMigrationCompatibility(array $definition,string $checksum):array {
    // Historical file exists with LF in Git and CRLF in older Windows checkouts.
    // Both exact byte variants are known; all other content uses normal integrity rejection.
    if($definition['id']!=='20260922190000_member_fee_standing_orders'||!in_array($checksum,['d47af0b6ace92ab8fdc316f0e8741d3735b7a70c802f26447231c9283bbf7f42','a7aab616aa0e2dfa5d587774952b98fdf24777bbc4c5c4242331c571496a8494'],true))return $definition;
    $originalUp=$definition['up'];$originalVerify=$definition['verify'];
    $definition['up']=static function(PDO $pdo)use($originalUp):void {
        if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'){$originalUp($pdo);return;}
        foreach(['member_fee_plans','sportovci','payments'] as $table){$q=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);if(!$q->fetchColumn())throw new RuntimeException('Required standing order table missing: '.$table);}
        paymentVariableSymbolRepairMysql($pdo);
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS member_fee_standing_orders (
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
SQL);
    };
    $definition['verify']=static function(PDO $pdo)use($originalVerify):bool {
        if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')return $originalVerify($pdo);
        return (bool)$pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='member_fee_standing_orders'")->fetchColumn()&&!paymentVariableSymbolUniqueIndexes($pdo);
    };
    return $definition;
}
