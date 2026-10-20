<?php
declare(strict_types=1);

$tableExists = static function (PDO $pdo, string $table): bool {
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $query = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $query->execute([$table]);
        return (bool)$query->fetchColumn();
    }
    $query = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
    $query->execute([$table]);
    return (bool)$query->fetchColumn();
};

return [
    'id' => '20261021130000_member_payer_receipts',
    'up' => static function (PDO $pdo) use ($tableExists): void {
        foreach (['verejni_uzivatele', 'treneri', 'club_member_charges', 'payments', 'fio_account_movements', 'club_finance_receipts', 'club_finance_settings', 'member_payer_variable_symbols'] as $required) {
            if (!$tableExists($pdo, $required)) throw new RuntimeException('Chybí tabulka pro rodinné platby: '.$required);
        }
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$tableExists($pdo, 'member_payer_receipts')) $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE member_payer_receipts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payer_account_id INT NOT NULL,
    reference VARCHAR(100) NOT NULL,
    fio_movement_id BIGINT UNSIGNED NULL,
    amount_minor BIGINT UNSIGNED NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'CZK',
    paid_on DATE NOT NULL,
    confirmed_by_trainer_id INT NOT NULL,
    reason VARCHAR(1000) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_member_payer_receipt_reference(reference),
    UNIQUE KEY uq_member_payer_receipt_fio(fio_movement_id),
    KEY idx_member_payer_receipt_account(payer_account_id,id),
    CONSTRAINT fk_member_payer_receipt_account FOREIGN KEY (payer_account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT,
    CONSTRAINT fk_member_payer_receipt_fio FOREIGN KEY (fio_movement_id) REFERENCES fio_account_movements(id) ON DELETE RESTRICT,
    CONSTRAINT fk_member_payer_receipt_actor FOREIGN KEY (confirmed_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE member_payer_receipts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    payer_account_id INTEGER NOT NULL,
    reference TEXT NOT NULL UNIQUE,
    fio_movement_id INTEGER NULL UNIQUE,
    amount_minor INTEGER NOT NULL CHECK(amount_minor > 0),
    currency TEXT NOT NULL DEFAULT 'CZK',
    paid_on TEXT NOT NULL,
    confirmed_by_trainer_id INTEGER NOT NULL,
    reason TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payer_account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT,
    FOREIGN KEY (fio_movement_id) REFERENCES fio_account_movements(id) ON DELETE RESTRICT,
    FOREIGN KEY (confirmed_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
)
SQL);
        if (!$tableExists($pdo, 'member_payer_receipt_allocations')) $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE member_payer_receipt_allocations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    receipt_id BIGINT UNSIGNED NOT NULL,
    charge_id BIGINT UNSIGNED NOT NULL,
    amount_minor BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_member_payer_allocation_receipt(receipt_id,id),
    KEY idx_member_payer_allocation_charge(charge_id),
    CONSTRAINT fk_member_payer_allocation_receipt FOREIGN KEY (receipt_id) REFERENCES member_payer_receipts(id) ON DELETE RESTRICT,
    CONSTRAINT fk_member_payer_allocation_charge FOREIGN KEY (charge_id) REFERENCES club_member_charges(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE member_payer_receipt_allocations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    receipt_id INTEGER NOT NULL,
    charge_id INTEGER NOT NULL,
    amount_minor INTEGER NOT NULL CHECK(amount_minor > 0),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (receipt_id) REFERENCES member_payer_receipts(id) ON DELETE RESTRICT,
    FOREIGN KEY (charge_id) REFERENCES club_member_charges(id) ON DELETE RESTRICT
)
SQL);
        if (!$tableExists($pdo, 'member_payer_receipt_events')) $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE member_payer_receipt_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    receipt_id BIGINT UNSIGNED NOT NULL,
    actor_trainer_id INT NOT NULL,
    action VARCHAR(40) NOT NULL,
    request_key CHAR(64) NULL,
    reason VARCHAR(1000) NOT NULL,
    snapshot_json LONGTEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_member_payer_event_receipt(receipt_id,id),
    UNIQUE KEY uq_member_payer_event_request(receipt_id,request_key),
    CONSTRAINT fk_member_payer_event_receipt FOREIGN KEY (receipt_id) REFERENCES member_payer_receipts(id) ON DELETE RESTRICT,
    CONSTRAINT fk_member_payer_event_actor FOREIGN KEY (actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE member_payer_receipt_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    receipt_id INTEGER NOT NULL,
    actor_trainer_id INTEGER NOT NULL,
    action TEXT NOT NULL,
    request_key TEXT NULL,
    reason TEXT NOT NULL,
    snapshot_json TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (receipt_id) REFERENCES member_payer_receipts(id) ON DELETE RESTRICT,
    FOREIGN KEY (actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
)
SQL);
        if (!$mysql) $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_member_payer_event_request ON member_payer_receipt_events(receipt_id,request_key)');
    },
    'verify' => static function (PDO $pdo) use ($tableExists): bool {
        foreach (['member_payer_receipts', 'member_payer_receipt_allocations', 'member_payer_receipt_events'] as $table) if (!$tableExists($pdo, $table)) return false;
        return true;
    },
];
