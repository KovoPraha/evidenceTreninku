<?php
declare(strict_types=1);

$fioReconciliationTableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

$fioReconciliationColumnExists = static function (PDO $pdo, string $table, string $column): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
        $statement->execute([$table,$column]);
        return (bool)$statement->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((string)$row['name'] === $column) return true;
    }
    return false;
};

return [
    'id' => '20260909170000_fio_bank_reconciliation',
    'up' => static function (PDO $pdo) use ($fioReconciliationTableExists,$fioReconciliationColumnExists): void {
        foreach (['payments','fio_account_movements'] as $required) {
            if (!$fioReconciliationTableExists($pdo,$required)) throw new RuntimeException('Required Fio reconciliation table is missing: '.$required);
        }
        $mysql=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        $paymentColumns=[
            'bank_movement_id'=>$mysql?'VARCHAR(80) NULL':'TEXT NULL',
            'bank_booked_on'=>$mysql?'DATE NULL':'TEXT NULL',
        ];
        foreach ($paymentColumns as $column=>$definition) {
            if (!$fioReconciliationColumnExists($pdo,'payments',$column)) $pdo->exec('ALTER TABLE payments ADD COLUMN '.$column.' '.$definition);
        }
        if ($mysql) {
            $index=$pdo->query("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payments' AND INDEX_NAME='uq_payments_bank_movement' LIMIT 1")->fetchColumn();
            if (!$index) $pdo->exec('ALTER TABLE payments ADD UNIQUE KEY uq_payments_bank_movement (bank_movement_id)');
        } else {
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_payments_bank_movement ON payments(bank_movement_id)');
        }
        $movementColumns=[
            'confirmed_at'=>$mysql?'DATETIME NULL':'TEXT NULL',
            'confirmed_by_trainer_id'=>$mysql?'INT NULL':'INTEGER NULL',
            'confirmation_note'=>$mysql?'VARCHAR(1000) NULL':'TEXT NULL',
        ];
        foreach ($movementColumns as $column=>$definition) {
            if (!$fioReconciliationColumnExists($pdo,'fio_account_movements',$column)) $pdo->exec('ALTER TABLE fio_account_movements ADD COLUMN '.$column.' '.$definition);
        }
    },
    'verify' => static function (PDO $pdo) use ($fioReconciliationTableExists,$fioReconciliationColumnExists): bool {
        if (!$fioReconciliationTableExists($pdo,'payments')||!$fioReconciliationTableExists($pdo,'fio_account_movements')) return false;
        foreach (['bank_movement_id','bank_booked_on'] as $column) if (!$fioReconciliationColumnExists($pdo,'payments',$column)) return false;
        foreach (['confirmed_at','confirmed_by_trainer_id','confirmation_note'] as $column) if (!$fioReconciliationColumnExists($pdo,'fio_account_movements',$column)) return false;
        return true;
    },
];
