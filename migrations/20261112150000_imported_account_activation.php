<?php
declare(strict_types=1);

return [
    'id' => '20261112150000_imported_account_activation',
    'up' => static function (PDO $pdo): void {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if ($mysql) {
            $column = $pdo->query("SHOW COLUMNS FROM password_reset_tokens LIKE 'delivery_email'")->fetch(PDO::FETCH_ASSOC);
            if (!$column) {
                $pdo->exec('ALTER TABLE password_reset_tokens ADD COLUMN delivery_email VARCHAR(160) NULL');
            }
            $pdo->exec(<<<'SQL'
                CREATE TABLE IF NOT EXISTS public_account_activation_events (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    account_id INT NOT NULL,
                    actor_trainer_id INT NULL,
                    action VARCHAR(32) NOT NULL,
                    reason VARCHAR(1000) NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_activation_account (account_id,id),
                    CONSTRAINT fk_activation_account FOREIGN KEY (account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_activation_actor FOREIGN KEY (actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL);
        } else {
            $columns = $pdo->query('PRAGMA table_info(password_reset_tokens)')->fetchAll(PDO::FETCH_ASSOC);
            if (!in_array('delivery_email', array_column($columns, 'name'), true)) {
                $pdo->exec('ALTER TABLE password_reset_tokens ADD COLUMN delivery_email TEXT NULL');
            }
            $pdo->exec(<<<'SQL'
                CREATE TABLE IF NOT EXISTS public_account_activation_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    account_id INTEGER NOT NULL,
                    actor_trainer_id INTEGER NULL,
                    action TEXT NOT NULL,
                    reason TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT,
                    FOREIGN KEY (actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
                )
                SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_activation_account ON public_account_activation_events(account_id,id)');
        }
    },
    'verify' => static function (PDO $pdo): bool {
        try {
            $pdo->query('SELECT delivery_email FROM password_reset_tokens LIMIT 0');
            $pdo->query('SELECT id FROM public_account_activation_events LIMIT 0');
            return true;
        } catch (PDOException) {
            return false;
        }
    },
];
