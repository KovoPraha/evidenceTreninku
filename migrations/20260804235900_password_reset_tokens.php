<?php
declare(strict_types=1);

$passwordResetTableExists = static function (PDO $pdo, string $table): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1'
        );
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    if ($driver === 'sqlite') {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    throw new RuntimeException('Unsupported database driver for password reset migration.');
};

return [
    'id' => '20260804235900_password_reset_tokens',
    'up' => static function (PDO $pdo) use ($passwordResetTableExists): void {
        foreach (['verejni_uzivatele', 'child_access_accounts', 'account_person_roles'] as $required) {
            if (!$passwordResetTableExists($pdo, $required)) {
                throw new RuntimeException('Required password reset table is missing: ' . $required);
            }
        }
        if ($passwordResetTableExists($pdo, 'password_reset_tokens')) {
            return;
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE password_reset_tokens (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                target_type VARCHAR(16) NOT NULL,
                target_id BIGINT UNSIGNED NOT NULL,
                delivery_account_id INT NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                consumed_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_password_reset_token_hash (token_hash),
                INDEX idx_password_reset_target (target_type, target_id, consumed_at, expires_at),
                CONSTRAINT fk_password_reset_delivery_account FOREIGN KEY (delivery_account_id)
                    REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE password_reset_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                target_type TEXT NOT NULL,
                target_id INTEGER NOT NULL,
                delivery_account_id INTEGER NOT NULL,
                token_hash TEXT NOT NULL UNIQUE,
                expires_at TEXT NOT NULL,
                consumed_at TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (delivery_account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
            )
            SQL);
        if (!$mysql) {
            $pdo->exec(
                'CREATE INDEX idx_password_reset_target ON password_reset_tokens '
                . '(target_type,target_id,consumed_at,expires_at)'
            );
        }
    },
    'verify' => static fn(PDO $pdo): bool => $passwordResetTableExists($pdo, 'password_reset_tokens'),
];
