<?php
declare(strict_types=1);

$tableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

return [
    'id' => '20261007120000_training_rsvps',
    'up' => static function (PDO $pdo) use ($tableExists): void {
        foreach (['planovane_treninky', 'sportovci', 'verejni_uzivatele', 'child_access_accounts'] as $required) {
            if (!$tableExists($pdo, $required)) throw new RuntimeException('Required training RSVP table is missing: ' . $required);
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$tableExists($pdo, 'training_rsvps')) {
            $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE training_rsvps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    sportovec_id INT NOT NULL,
    account_id INT NULL,
    access_account_id BIGINT UNSIGNED NULL,
    response VARCHAR(24) NOT NULL,
    responded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_training_rsvp_person (plan_id,sportovec_id),
    KEY idx_training_rsvp_plan_response (plan_id,response,id),
    KEY idx_training_rsvp_account (account_id,id),
    CONSTRAINT fk_training_rsvp_plan FOREIGN KEY(plan_id) REFERENCES planovane_treninky(id) ON DELETE CASCADE,
    CONSTRAINT fk_training_rsvp_person FOREIGN KEY(sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,
    CONSTRAINT fk_training_rsvp_account FOREIGN KEY(account_id) REFERENCES verejni_uzivatele(id) ON DELETE SET NULL,
    CONSTRAINT fk_training_rsvp_child FOREIGN KEY(access_account_id) REFERENCES child_access_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE training_rsvps (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    plan_id INTEGER NOT NULL,
    sportovec_id INTEGER NOT NULL,
    account_id INTEGER NULL,
    access_account_id INTEGER NULL,
    response TEXT NOT NULL,
    responded_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(plan_id,sportovec_id),
    FOREIGN KEY(plan_id) REFERENCES planovane_treninky(id) ON DELETE CASCADE,
    FOREIGN KEY(sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,
    FOREIGN KEY(account_id) REFERENCES verejni_uzivatele(id) ON DELETE SET NULL,
    FOREIGN KEY(access_account_id) REFERENCES child_access_accounts(id) ON DELETE SET NULL
)
SQL);
        }
        if (!$tableExists($pdo, 'training_rsvp_events')) {
            $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE training_rsvp_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rsvp_id BIGINT UNSIGNED NOT NULL,
    actor_type VARCHAR(24) NOT NULL,
    actor_id BIGINT UNSIGNED NOT NULL,
    from_response VARCHAR(24) NULL,
    to_response VARCHAR(24) NOT NULL,
    note VARCHAR(1000) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_training_rsvp_event (rsvp_id,id),
    CONSTRAINT fk_training_rsvp_event FOREIGN KEY(rsvp_id) REFERENCES training_rsvps(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE training_rsvp_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    rsvp_id INTEGER NOT NULL,
    actor_type TEXT NOT NULL,
    actor_id INTEGER NOT NULL,
    from_response TEXT NULL,
    to_response TEXT NOT NULL,
    note TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(rsvp_id) REFERENCES training_rsvps(id) ON DELETE RESTRICT
)
SQL);
        }
        if (!$mysql) {
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_training_rsvp_plan_response ON training_rsvps(plan_id,response,id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_training_rsvp_account ON training_rsvps(account_id,id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_training_rsvp_event ON training_rsvp_events(rsvp_id,id)');
        }
    },
    'verify' => static fn(PDO $pdo): bool => $tableExists($pdo, 'training_rsvps') && $tableExists($pdo, 'training_rsvp_events'),
];
