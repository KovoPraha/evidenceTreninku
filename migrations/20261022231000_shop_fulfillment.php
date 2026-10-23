<?php
declare(strict_types=1);
return [
 'id'=>'20261022231000_shop_fulfillment',
 'up'=>static function(PDO $pdo):void {
  $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
  $id=$mysql?'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
  $tail=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
  $pdo->exec("CREATE TABLE IF NOT EXISTS shop_fulfillments (id $id, order_id BIGINT UNSIGNED NOT NULL, request_key VARCHAR(100) NOT NULL UNIQUE, fingerprint CHAR(64) NOT NULL, channel VARCHAR(20) NOT NULL, recipient VARCHAR(200) NOT NULL, occurred_at DATETIME NOT NULL, actor_trainer_id INT NOT NULL, reason VARCHAR(1000) NOT NULL, recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(order_id) REFERENCES shop_orders(id), FOREIGN KEY(actor_trainer_id) REFERENCES treneri(id))$tail");
  $pdo->exec("CREATE TABLE IF NOT EXISTS shop_fulfillment_items (id $id, fulfillment_id BIGINT UNSIGNED NOT NULL, order_item_id BIGINT UNSIGNED NOT NULL, variant_id BIGINT UNSIGNED NOT NULL, quantity INT NOT NULL, inventory_movement_id BIGINT UNSIGNED NOT NULL UNIQUE, cost_minor BIGINT NULL, UNIQUE(fulfillment_id,order_item_id), FOREIGN KEY(fulfillment_id) REFERENCES shop_fulfillments(id), FOREIGN KEY(order_item_id) REFERENCES shop_order_items(id))$tail");
  $pdo->exec("CREATE TABLE IF NOT EXISTS shop_delivery_events (id $id, order_id BIGINT UNSIGNED NOT NULL, request_key VARCHAR(100) NOT NULL UNIQUE, fingerprint CHAR(64) NOT NULL, status VARCHAR(30) NOT NULL, occurred_at DATETIME NOT NULL, actor_trainer_id INT NOT NULL, recipient VARCHAR(200) NOT NULL, reason VARCHAR(1000) NOT NULL, recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(order_id) REFERENCES shop_orders(id), FOREIGN KEY(actor_trainer_id) REFERENCES treneri(id))$tail");
  $pdo->exec("CREATE TABLE IF NOT EXISTS shop_assisted_sales (id $id, order_id BIGINT UNSIGNED NOT NULL UNIQUE, actor_trainer_id INT NOT NULL, account_id INT NULL, beneficiary_sportovec_id INT NULL, payer_name VARCHAR(200) NOT NULL, recipient_name VARCHAR(200) NOT NULL, price_snapshot_json TEXT NOT NULL, request_fingerprint CHAR(64) NOT NULL, reason VARCHAR(1000) NOT NULL, recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(order_id) REFERENCES shop_orders(id), FOREIGN KEY(actor_trainer_id) REFERENCES treneri(id))$tail");
 },
 'verify'=>static function(PDO $pdo):bool {try{foreach(['shop_fulfillments','shop_fulfillment_items','shop_delivery_events','shop_assisted_sales'] as $table)$pdo->query("SELECT * FROM $table LIMIT 0");return true;}catch(PDOException){return false;}}
];
