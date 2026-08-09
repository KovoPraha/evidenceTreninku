<?php
declare(strict_types=1);

$stripeTableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

$stripeColumnExists = static function (PDO $pdo, string $table, string $column): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
        $statement->execute([$table, $column]);
        return (bool)$statement->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((string)$row['name'] === $column) return true;
    }
    return false;
};

$stripeIndexExists = static function (PDO $pdo, string $table, string $index): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? LIMIT 1');
        $statement->execute([$table, $index]);
        return (bool)$statement->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA index_list(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((string)$row['name'] === $index) return true;
    }
    return false;
};

return [
    'id' => '20260809090000_stripe_checkout',
    'up' => static function (PDO $pdo) use ($stripeTableExists, $stripeColumnExists, $stripeIndexExists): void {
        if (!$stripeTableExists($pdo, 'payments')) {
            throw new RuntimeException('Required payments table is missing.');
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$stripeColumnExists($pdo, 'payments', 'payment_source')) {
            $pdo->exec("ALTER TABLE payments ADD COLUMN payment_source VARCHAR(32) NOT NULL DEFAULT 'bank_transfer'");
        }
        if (!$stripeColumnExists($pdo, 'payments', 'stripe_checkout_session_id')) {
            $pdo->exec('ALTER TABLE payments ADD COLUMN stripe_checkout_session_id VARCHAR(255) NULL');
        }
        if (!$stripeColumnExists($pdo, 'payments', 'stripe_payment_intent_id')) {
            $pdo->exec('ALTER TABLE payments ADD COLUMN stripe_payment_intent_id VARCHAR(255) NULL');
        }
        if (!$stripeIndexExists($pdo, 'payments', 'uq_payment_stripe_session')) {
            $pdo->exec('CREATE UNIQUE INDEX uq_payment_stripe_session ON payments(stripe_checkout_session_id)');
        }
        if (!$stripeTableExists($pdo, 'stripe_webhook_events')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE stripe_webhook_events (
                    event_id VARCHAR(255) NOT NULL PRIMARY KEY,
                    event_type VARCHAR(128) NOT NULL,
                    payment_id BIGINT UNSIGNED NULL,
                    payload_sha256 CHAR(64) NOT NULL,
                    processing_status VARCHAR(24) NOT NULL,
                    received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    processed_at DATETIME NULL,
                    KEY idx_stripe_webhook_payment (payment_id,received_at),
                    CONSTRAINT fk_stripe_webhook_payment FOREIGN KEY (payment_id)
                        REFERENCES payments(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE stripe_webhook_events (
                    event_id TEXT NOT NULL PRIMARY KEY,
                    event_type TEXT NOT NULL,
                    payment_id INTEGER NULL,
                    payload_sha256 TEXT NOT NULL,
                    processing_status TEXT NOT NULL,
                    received_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    processed_at TEXT NULL,
                    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE RESTRICT
                )
                SQL);
        }
    },
    'verify' => static function (PDO $pdo) use ($stripeTableExists, $stripeColumnExists, $stripeIndexExists): bool {
        return $stripeTableExists($pdo, 'stripe_webhook_events')
            && $stripeColumnExists($pdo, 'payments', 'payment_source')
            && $stripeColumnExists($pdo, 'payments', 'stripe_checkout_session_id')
            && $stripeColumnExists($pdo, 'payments', 'stripe_payment_intent_id')
            && $stripeIndexExists($pdo, 'payments', 'uq_payment_stripe_session');
    },
];
