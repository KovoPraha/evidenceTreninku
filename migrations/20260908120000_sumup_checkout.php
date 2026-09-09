<?php
declare(strict_types=1);

$sumupTableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

$sumupColumnExists = static function (PDO $pdo, string $table, string $column): bool {
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

$sumupIndexExists = static function (PDO $pdo, string $table, string $index): bool {
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
    'id' => '20260908120000_sumup_checkout',
    'up' => static function (PDO $pdo) use ($sumupTableExists, $sumupColumnExists, $sumupIndexExists): void {
        if (!$sumupTableExists($pdo, 'payments')) {
            throw new RuntimeException('Required payments table is missing.');
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$sumupColumnExists($pdo, 'payments', 'sumup_checkout_id')) {
            $pdo->exec('ALTER TABLE payments ADD COLUMN sumup_checkout_id VARCHAR(64) NULL');
        }
        if (!$sumupColumnExists($pdo, 'payments', 'sumup_checkout_reference')) {
            $pdo->exec('ALTER TABLE payments ADD COLUMN sumup_checkout_reference VARCHAR(64) NULL');
        }
        if (!$sumupColumnExists($pdo, 'payments', 'sumup_transaction_id')) {
            $pdo->exec('ALTER TABLE payments ADD COLUMN sumup_transaction_id VARCHAR(64) NULL');
        }
        if (!$sumupIndexExists($pdo, 'payments', 'uq_payment_sumup_checkout')) {
            $pdo->exec('CREATE UNIQUE INDEX uq_payment_sumup_checkout ON payments(sumup_checkout_id)');
        }
        if (!$sumupIndexExists($pdo, 'payments', 'uq_payment_sumup_reference')) {
            $pdo->exec('CREATE UNIQUE INDEX uq_payment_sumup_reference ON payments(sumup_checkout_reference)');
        }
        if (!$sumupTableExists($pdo, 'sumup_webhook_events')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE sumup_webhook_events (
                    event_key CHAR(64) NOT NULL PRIMARY KEY,
                    checkout_id VARCHAR(64) NOT NULL,
                    checkout_status VARCHAR(24) NOT NULL,
                    transaction_id VARCHAR(64) NULL,
                    payment_id BIGINT UNSIGNED NULL,
                    payload_sha256 CHAR(64) NOT NULL,
                    processing_status VARCHAR(24) NOT NULL,
                    received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    processed_at DATETIME NULL,
                    KEY idx_sumup_webhook_payment (payment_id,received_at),
                    KEY idx_sumup_webhook_checkout (checkout_id,received_at),
                    CONSTRAINT fk_sumup_webhook_payment FOREIGN KEY (payment_id)
                        REFERENCES payments(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE sumup_webhook_events (
                    event_key TEXT NOT NULL PRIMARY KEY,
                    checkout_id TEXT NOT NULL,
                    checkout_status TEXT NOT NULL,
                    transaction_id TEXT NULL,
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
    'verify' => static function (PDO $pdo) use ($sumupTableExists, $sumupColumnExists, $sumupIndexExists): bool {
        return $sumupTableExists($pdo, 'sumup_webhook_events')
            && $sumupColumnExists($pdo, 'payments', 'sumup_checkout_id')
            && $sumupColumnExists($pdo, 'payments', 'sumup_checkout_reference')
            && $sumupColumnExists($pdo, 'payments', 'sumup_transaction_id')
            && $sumupIndexExists($pdo, 'payments', 'uq_payment_sumup_checkout')
            && $sumupIndexExists($pdo, 'payments', 'uq_payment_sumup_reference');
    },
];
