<?php
declare(strict_types=1);

$familyCartColumn = static function(PDO $pdo,string $table='shop_cart_items'): bool {
    try { $pdo->query('SELECT participant_key FROM '.$table.' LIMIT 0'); return true; }
    catch (PDOException) { return false; }
};
return [
    'id'=>'20261023210000_family_course_cart',
    'up'=>static function(PDO $pdo) use ($familyCartColumn): void {
        $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        if (!$familyCartColumn($pdo)) {
            if ($mysql) {
                $pdo->exec('ALTER TABLE shop_cart_items ADD COLUMN participant_key INT NOT NULL DEFAULT 0');
            } else {
                // SQLite cannot drop the original inline UNIQUE constraint.
                $pdo->exec('CREATE TABLE shop_cart_items_family_next (
                    id INTEGER PRIMARY KEY AUTOINCREMENT, cart_id INTEGER NOT NULL REFERENCES shop_carts(id) ON DELETE CASCADE,
                    variant_id INTEGER NOT NULL REFERENCES shop_variants(id) ON DELETE RESTRICT, quantity INTEGER NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    beneficiary_sportovec_id INTEGER NULL REFERENCES sportovci(id) ON DELETE RESTRICT,
                    participant_key INTEGER NOT NULL DEFAULT 0)');
                $pdo->exec('INSERT INTO shop_cart_items_family_next(id,cart_id,variant_id,quantity,created_at,updated_at,beneficiary_sportovec_id)
                    SELECT id,cart_id,variant_id,quantity,created_at,updated_at,beneficiary_sportovec_id FROM shop_cart_items');
                $pdo->exec('DROP TABLE shop_cart_items');
                $pdo->exec('ALTER TABLE shop_cart_items_family_next RENAME TO shop_cart_items');
                $pdo->exec('CREATE INDEX idx_shop_cart_item_beneficiary ON shop_cart_items(beneficiary_sportovec_id)');
            }
        }
        // Safe to repeat after an interrupted MySQL DDL; no historical order is changed.
        $pdo->exec("UPDATE shop_cart_items SET participant_key=COALESCE(beneficiary_sportovec_id,0)
            WHERE variant_id IN (SELECT v.id FROM shop_variants v JOIN shop_products p ON p.id=v.product_id WHERE p.offer_type='program')");
        if ($mysql) {
            $indexes=$pdo->query('SHOW INDEX FROM shop_cart_items')->fetchAll(PDO::FETCH_ASSOC);
            $names=array_column($indexes,'Key_name');
            if (!in_array('uq_shop_cart_participant',$names,true)) $pdo->exec('ALTER TABLE shop_cart_items ADD UNIQUE KEY uq_shop_cart_participant(cart_id,variant_id,participant_key)');
            if (in_array('uq_shop_cart_variant',$names,true)) $pdo->exec('ALTER TABLE shop_cart_items DROP INDEX uq_shop_cart_variant');
        } else $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_shop_cart_participant ON shop_cart_items(cart_id,variant_id,participant_key)');
        if (!$familyCartColumn($pdo,'shop_order_items')) $pdo->exec('ALTER TABLE shop_order_items ADD COLUMN participant_key INT NOT NULL DEFAULT 0');
        $pdo->exec("UPDATE shop_order_items SET participant_key=COALESCE(beneficiary_sportovec_id,0)
            WHERE variant_id IN (SELECT v.id FROM shop_variants v JOIN shop_products p ON p.id=v.product_id WHERE p.offer_type='program')");
        if ($mysql) {
            $names=array_column($pdo->query('SHOW INDEX FROM shop_order_items')->fetchAll(PDO::FETCH_ASSOC),'Key_name');
            if (!in_array('uq_shop_order_participant',$names,true)) $pdo->exec('ALTER TABLE shop_order_items ADD UNIQUE KEY uq_shop_order_participant(order_id,variant_id,participant_key)');
            if (in_array('uq_shop_order_variant',$names,true)) $pdo->exec('ALTER TABLE shop_order_items DROP INDEX uq_shop_order_variant');
        } else {
            $sql=(string)$pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='shop_order_items'")->fetchColumn();
            if (str_contains($sql,'UNIQUE(order_id,variant_id)')) {
                if ($pdo->inTransaction()) throw new RuntimeException('SQLite family migration must run outside a transaction.');
                $foreignKeys=(int)$pdo->query('PRAGMA foreign_keys')->fetchColumn();
                $objects=$pdo->query("SELECT sql FROM sqlite_master WHERE tbl_name='shop_order_items' AND type IN ('index','trigger') AND sql IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
                $pdo->exec('PRAGMA foreign_keys=OFF');
                try {
                    $pdo->beginTransaction();
                    $sql=str_replace(['CREATE TABLE shop_order_items','UNIQUE(order_id,variant_id)'],['CREATE TABLE shop_order_items_family_next','UNIQUE(order_id,variant_id,participant_key)'],$sql);
                    $pdo->exec($sql);
                    $pdo->exec('INSERT INTO shop_order_items_family_next SELECT * FROM shop_order_items');
                    $pdo->exec('DROP TABLE shop_order_items');
                    $pdo->exec('ALTER TABLE shop_order_items_family_next RENAME TO shop_order_items');
                    foreach ($objects as $object) $pdo->exec($object);
                    if ($pdo->query('PRAGMA foreign_key_check')->fetch()) throw new RuntimeException('SQLite family migration failed foreign key validation.');
                    $pdo->commit();
                } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
                finally { $pdo->exec('PRAGMA foreign_keys='.$foreignKeys); }
            }
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_shop_order_participant ON shop_order_items(order_id,variant_id,participant_key)');
        }
        $orderId=$mysql?'BIGINT UNSIGNED':'INTEGER';
        $pdo->exec('CREATE TABLE IF NOT EXISTS shop_order_finance_shares (
            order_id '.$orderId.' NOT NULL, sportovec_id INT NOT NULL, amount_minor BIGINT NOT NULL,
            PRIMARY KEY(order_id,sportovec_id),
            FOREIGN KEY(order_id) REFERENCES shop_orders(id) ON DELETE RESTRICT,
            FOREIGN KEY(sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT
        )'.($mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':''));
    },
    'verify'=>static function(PDO $pdo) use ($familyCartColumn): bool {
        if (!$familyCartColumn($pdo)||!$familyCartColumn($pdo,'shop_order_items')) return false;
        try {
            $pdo->query('SELECT order_id,sportovec_id,amount_minor FROM shop_order_finance_shares LIMIT 0');
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') {
                $names=array_column($pdo->query('SHOW INDEX FROM shop_cart_items')->fetchAll(PDO::FETCH_ASSOC),'Key_name');
                $orderNames=array_column($pdo->query('SHOW INDEX FROM shop_order_items')->fetchAll(PDO::FETCH_ASSOC),'Key_name');
                return in_array('uq_shop_cart_participant',$names,true)&&!in_array('uq_shop_cart_variant',$names,true)&&in_array('uq_shop_order_participant',$orderNames,true)&&!in_array('uq_shop_order_variant',$orderNames,true);
            }
            return (bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type='index' AND name='uq_shop_cart_participant'")->fetchColumn();
        } catch (PDOException) { return false; }
    },
];
