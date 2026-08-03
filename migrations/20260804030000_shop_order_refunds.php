<?php
declare(strict_types=1);

$paymentColumnExists = static function (PDO $pdo, string $column): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() "
            . "AND TABLE_NAME='payments' AND COLUMN_NAME=? LIMIT 1"
        );
        $statement->execute([$column]);
        return (bool)$statement->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info(payments)')->fetchAll(PDO::FETCH_ASSOC) as $definition) {
        if (($definition['name'] ?? null) === $column) return true;
    }
    return false;
};

return [
    'id' => '20260804030000_shop_order_refunds',
    'up' => static function (PDO $pdo) use ($paymentColumnExists): void {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $columns = [
            'refund_sent_at' => $mysql ? 'DATETIME NULL' : 'TEXT NULL',
            'refund_reference' => $mysql ? 'VARCHAR(255) NULL' : 'TEXT NULL',
            'refund_confirmed_by_trainer_id' => $mysql ? 'INT NULL' : 'INTEGER NULL',
            'refund_confirmation_note' => $mysql ? 'VARCHAR(1000) NULL' : 'TEXT NULL',
        ];
        foreach ($columns as $column => $definition) {
            if (!$paymentColumnExists($pdo, $column)) {
                $pdo->exec('ALTER TABLE payments ADD COLUMN '.$column.' '.$definition);
            }
        }
    },
    'verify' => static function (PDO $pdo) use ($paymentColumnExists): bool {
        foreach (['refund_sent_at','refund_reference','refund_confirmed_by_trainer_id','refund_confirmation_note'] as $column) {
            if (!$paymentColumnExists($pdo, $column)) return false;
        }
        return true;
    },
];
