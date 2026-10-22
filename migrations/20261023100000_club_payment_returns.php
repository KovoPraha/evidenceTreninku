<?php
declare(strict_types=1);
return [
 'id'=>'20261023100000_club_payment_returns',
 'up'=>static function(PDO $p):void {
  $mysql=$p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';$id=$mysql?'INT AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
  $p->exec('CREATE TABLE IF NOT EXISTS club_payment_returns(id '.$id.',domain VARCHAR(20) NOT NULL,case_id INT NOT NULL,original_allocation_id INT NOT NULL,return_allocation_id INT NOT NULL UNIQUE,amount_minor BIGINT NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)'.($mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':''));
 },
 'verify'=>static function(PDO $p):bool {try{$p->query('SELECT domain,case_id,original_allocation_id,return_allocation_id,amount_minor FROM club_payment_returns LIMIT 0');return true;}catch(PDOException){return false;}},
];
