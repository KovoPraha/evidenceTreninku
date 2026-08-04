<?php
declare(strict_types=1);

$reminderTableExists = static function (PDO $pdo, string $table): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    if ($driver === 'sqlite') {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    throw new RuntimeException('Unsupported database driver for member charge reminder migration.');
};

return [
    'id' => '20260805020000_member_charge_reminders',
    'up' => static function (PDO $pdo) use ($reminderTableExists): void {
        foreach (['verejni_uzivatele', 'club_member_charges'] as $required) {
            if (!$reminderTableExists($pdo, $required)) {
                throw new RuntimeException('Member charge reminder prerequisite is missing: ' . $required);
            }
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$reminderTableExists($pdo, 'member_charge_reminder_preferences')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE member_charge_reminder_preferences (
                    account_id INT PRIMARY KEY,
                    enabled TINYINT(1) NOT NULL DEFAULT 0,
                    days_before TINYINT UNSIGNED NOT NULL DEFAULT 7,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    CONSTRAINT fk_charge_reminder_preference_account FOREIGN KEY(account_id)
                        REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE member_charge_reminder_preferences (
                    account_id INTEGER PRIMARY KEY,
                    enabled INTEGER NOT NULL DEFAULT 0,
                    days_before INTEGER NOT NULL DEFAULT 7,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
                )
                SQL);
        }
        if (!$reminderTableExists($pdo, 'member_charge_reminders')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE member_charge_reminders (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    charge_id BIGINT UNSIGNED NOT NULL,
                    account_id INT NOT NULL,
                    reminder_type VARCHAR(32) NOT NULL DEFAULT 'due_soon',
                    recipient_email VARCHAR(254) NOT NULL,
                    recipient_name VARCHAR(255) NOT NULL,
                    subject_plain VARCHAR(255) NOT NULL,
                    body_plain TEXT NOT NULL,
                    status ENUM('pending','processing','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
                    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    claimed_at DATETIME NULL,
                    claim_token CHAR(32) NULL,
                    sent_at DATETIME NULL,
                    cancelled_at DATETIME NULL,
                    last_error VARCHAR(500) NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_member_charge_reminder(charge_id,account_id,reminder_type),
                    KEY idx_member_charge_reminder_queue(status,available_at,id),
                    KEY idx_member_charge_reminder_frequency(account_id,sent_at,id),
                    CONSTRAINT fk_charge_reminder_charge FOREIGN KEY(charge_id)
                        REFERENCES club_member_charges(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_charge_reminder_account FOREIGN KEY(account_id)
                        REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE member_charge_reminders (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    charge_id INTEGER NOT NULL,
                    account_id INTEGER NOT NULL,
                    reminder_type TEXT NOT NULL DEFAULT 'due_soon',
                    recipient_email TEXT NOT NULL,
                    recipient_name TEXT NOT NULL,
                    subject_plain TEXT NOT NULL,
                    body_plain TEXT NOT NULL,
                    status TEXT NOT NULL DEFAULT 'pending',
                    attempts INTEGER NOT NULL DEFAULT 0,
                    available_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    claimed_at TEXT NULL,
                    claim_token TEXT NULL,
                    sent_at TEXT NULL,
                    cancelled_at TEXT NULL,
                    last_error TEXT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(charge_id,account_id,reminder_type),
                    FOREIGN KEY(charge_id) REFERENCES club_member_charges(id) ON DELETE RESTRICT,
                    FOREIGN KEY(account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
                )
                SQL);
            if (!$mysql) {
                $pdo->exec('CREATE INDEX idx_member_charge_reminder_queue ON member_charge_reminders(status,available_at,id)');
                $pdo->exec('CREATE INDEX idx_member_charge_reminder_frequency ON member_charge_reminders(account_id,sent_at,id)');
            }
        }
        if (!$reminderTableExists($pdo, 'member_charge_reminder_events')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE member_charge_reminder_events (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    reminder_id BIGINT UNSIGNED NULL,
                    account_id INT NOT NULL,
                    action VARCHAR(32) NOT NULL,
                    from_status VARCHAR(24) NULL,
                    to_status VARCHAR(24) NULL,
                    note VARCHAR(500) NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_member_charge_reminder_event(reminder_id,id),
                    KEY idx_member_charge_reminder_account_event(account_id,id),
                    CONSTRAINT fk_charge_reminder_event_reminder FOREIGN KEY(reminder_id)
                        REFERENCES member_charge_reminders(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_charge_reminder_event_account FOREIGN KEY(account_id)
                        REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE member_charge_reminder_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    reminder_id INTEGER NULL,
                    account_id INTEGER NOT NULL,
                    action TEXT NOT NULL,
                    from_status TEXT NULL,
                    to_status TEXT NULL,
                    note TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(reminder_id) REFERENCES member_charge_reminders(id) ON DELETE RESTRICT,
                    FOREIGN KEY(account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
                )
                SQL);
            if (!$mysql) {
                $pdo->exec('CREATE INDEX idx_member_charge_reminder_event ON member_charge_reminder_events(reminder_id,id)');
                $pdo->exec('CREATE INDEX idx_member_charge_reminder_account_event ON member_charge_reminder_events(account_id,id)');
            }
        }
    },
    'verify' => static fn (PDO $pdo): bool => $reminderTableExists($pdo, 'member_charge_reminder_preferences')
        && $reminderTableExists($pdo, 'member_charge_reminders')
        && $reminderTableExists($pdo, 'member_charge_reminder_events'),
];
