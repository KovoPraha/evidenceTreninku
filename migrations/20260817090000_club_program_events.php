<?php
declare(strict_types=1);

$clubProgramEventsTableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1'
        );
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

$clubProgramEventsColumnExists = static function (PDO $pdo, string $table, string $column): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1'
        );
        $statement->execute([$table, $column]);
        return (bool)$statement->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((string)$row['name'] === $column) return true;
    }
    return false;
};

return [
    'id' => '20260817090000_club_program_events',
    'up' => static function (PDO $pdo) use ($clubProgramEventsTableExists): void {
        foreach (['club_programs', 'club_program_offers'] as $required) {
            if (!$clubProgramEventsTableExists($pdo, $required)) {
                throw new RuntimeException('Required club program audit table is missing: ' . $required);
            }
        }
        if ($clubProgramEventsTableExists($pdo, 'club_program_events')) {
            return;
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE club_program_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                program_id BIGINT UNSIGNED NOT NULL,
                offer_id BIGINT UNSIGNED NULL,
                actor_type VARCHAR(24) NOT NULL,
                actor_id INT NOT NULL,
                action VARCHAR(48) NOT NULL,
                before_json LONGTEXT NULL,
                after_json LONGTEXT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_club_program_audit_program (program_id,id),
                KEY idx_club_program_audit_offer (offer_id,id),
                CONSTRAINT fk_club_program_audit_program FOREIGN KEY (program_id) REFERENCES club_programs(id) ON DELETE RESTRICT,
                CONSTRAINT fk_club_program_audit_offer FOREIGN KEY (offer_id) REFERENCES club_program_offers(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE club_program_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                program_id INTEGER NOT NULL,
                offer_id INTEGER NULL,
                actor_type TEXT NOT NULL,
                actor_id INTEGER NOT NULL,
                action TEXT NOT NULL,
                before_json TEXT NULL,
                after_json TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(program_id) REFERENCES club_programs(id) ON DELETE RESTRICT,
                FOREIGN KEY(offer_id) REFERENCES club_program_offers(id) ON DELETE RESTRICT
            )
            SQL);
    },
    'verify' => static function (PDO $pdo) use ($clubProgramEventsTableExists, $clubProgramEventsColumnExists): bool {
        if (!$clubProgramEventsTableExists($pdo, 'club_program_events')) return false;
        foreach (['program_id','offer_id','actor_type','actor_id','action','before_json','after_json','created_at'] as $column) {
            if (!$clubProgramEventsColumnExists($pdo, 'club_program_events', $column)) return false;
        }
        return true;
    },
];
