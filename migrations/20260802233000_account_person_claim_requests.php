<?php
declare(strict_types=1);

$claimTableExists = static function (PDO $pdo, string $table): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1'
        );
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    if ($driver === 'sqlite') {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    throw new RuntimeException('Unsupported database driver for account claim migration.');
};

return [
    'id' => '20260802233000_account_person_claim_requests',
    'up' => static function (PDO $pdo) use ($claimTableExists): void {
        foreach (['verejni_uzivatele', 'sportovci', 'treneri', 'account_person_roles'] as $required) {
            if (!$claimTableExists($pdo, $required)) {
                throw new RuntimeException('Required identity table is missing: ' . $required);
            }
        }
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!$claimTableExists($pdo, 'account_person_claim_requests')) {
            $pdo->exec($driver === 'mysql' ? <<<'SQL'
                CREATE TABLE account_person_claim_requests (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    account_id INT NOT NULL,
                    requested_role VARCHAR(24) NOT NULL,
                    claimed_jmeno VARCHAR(100) NOT NULL,
                    claimed_prijmeni VARCHAR(100) NOT NULL,
                    claimed_narozeni DATE NOT NULL,
                    requester_message TEXT NOT NULL,
                    status VARCHAR(24) NOT NULL DEFAULT 'pending',
                    active_fingerprint CHAR(64) NULL,
                    matched_sportovec_id INT NULL,
                    decided_by_trainer_id INT NULL,
                    decision_note TEXT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    decided_at DATETIME NULL,
                    UNIQUE KEY uq_account_claim_pending (account_id, active_fingerprint),
                    INDEX idx_account_claim_status (status, created_at),
                    INDEX idx_account_claim_account (account_id, created_at),
                    CONSTRAINT fk_account_claim_account FOREIGN KEY (account_id)
                        REFERENCES verejni_uzivatele (id) ON DELETE RESTRICT,
                    CONSTRAINT fk_account_claim_person FOREIGN KEY (matched_sportovec_id)
                        REFERENCES sportovci (id) ON DELETE RESTRICT,
                    CONSTRAINT fk_account_claim_decider FOREIGN KEY (decided_by_trainer_id)
                        REFERENCES treneri (id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE account_person_claim_requests (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    account_id INTEGER NOT NULL,
                    requested_role TEXT NOT NULL,
                    claimed_jmeno TEXT NOT NULL,
                    claimed_prijmeni TEXT NOT NULL,
                    claimed_narozeni TEXT NOT NULL,
                    requester_message TEXT NOT NULL,
                    status TEXT NOT NULL DEFAULT 'pending',
                    active_fingerprint TEXT NULL,
                    matched_sportovec_id INTEGER NULL,
                    decided_by_trainer_id INTEGER NULL,
                    decision_note TEXT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    decided_at TEXT NULL,
                    UNIQUE (account_id, active_fingerprint),
                    FOREIGN KEY (account_id) REFERENCES verejni_uzivatele (id) ON DELETE RESTRICT,
                    FOREIGN KEY (matched_sportovec_id) REFERENCES sportovci (id) ON DELETE RESTRICT,
                    FOREIGN KEY (decided_by_trainer_id) REFERENCES treneri (id) ON DELETE RESTRICT
                )
                SQL);
        }
        if (!$claimTableExists($pdo, 'account_person_claim_events')) {
            $pdo->exec($driver === 'mysql' ? <<<'SQL'
                CREATE TABLE account_person_claim_events (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    request_id BIGINT UNSIGNED NOT NULL,
                    actor_type VARCHAR(24) NOT NULL,
                    actor_id INT NOT NULL,
                    action VARCHAR(24) NOT NULL,
                    from_status VARCHAR(24) NULL,
                    to_status VARCHAR(24) NOT NULL,
                    note TEXT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_account_claim_event (request_id, created_at),
                    CONSTRAINT fk_account_claim_event_request FOREIGN KEY (request_id)
                        REFERENCES account_person_claim_requests (id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE account_person_claim_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    request_id INTEGER NOT NULL,
                    actor_type TEXT NOT NULL,
                    actor_id INTEGER NOT NULL,
                    action TEXT NOT NULL,
                    from_status TEXT NULL,
                    to_status TEXT NOT NULL,
                    note TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (request_id) REFERENCES account_person_claim_requests (id) ON DELETE RESTRICT
                )
                SQL);
        }
    },
    'verify' => static function (PDO $pdo) use ($claimTableExists): bool {
        return $claimTableExists($pdo, 'account_person_claim_requests')
            && $claimTableExists($pdo, 'account_person_claim_events');
    },
];
