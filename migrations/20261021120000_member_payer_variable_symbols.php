<?php
declare(strict_types=1);

return [
    'id' => '20261021120000_member_payer_variable_symbols',
    'up' => static function (PDO $pdo): void {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if ($mysql) {
            $exists = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='member_payer_variable_symbols'")->fetchColumn();
            if (!$exists) $pdo->exec(<<<'SQL'
CREATE TABLE member_payer_variable_symbols (
    payer_account_id INT NOT NULL PRIMARY KEY,
    variable_symbol VARCHAR(10) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_member_payer_vs (variable_symbol),
    CONSTRAINT fk_member_payer_vs_account FOREIGN KEY (payer_account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        } else {
            $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS member_payer_variable_symbols (
    payer_account_id INTEGER PRIMARY KEY,
    variable_symbol TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payer_account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
)
SQL);
        }
    },
    'verify' => static function (PDO $pdo): bool {
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            return (bool)$pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='member_payer_variable_symbols'")->fetchColumn();
        }
        return (bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='member_payer_variable_symbols'")->fetchColumn();
    },
];
