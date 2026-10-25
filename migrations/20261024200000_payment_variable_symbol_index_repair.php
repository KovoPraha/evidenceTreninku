<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/includes/payment_variable_symbol_schema.php';
return [
    'id'=>'20261024200000_payment_variable_symbol_index_repair',
    'up'=>static function(PDO $pdo):void {
        if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){paymentVariableSymbolRepairMysql($pdo);return;}
        // The old SQLite path was correct and also supports an inline UNIQUE constraint.
        if(paymentVariableSymbolUniqueIndexes($pdo)){$legacy=require __DIR__.'/20260922190000_member_fee_standing_orders.php';$legacy['up']($pdo);}
    },
    'verify'=>static fn(PDO $pdo):bool=>paymentVariableSymbolUniqueIndexes($pdo)===[],
];
