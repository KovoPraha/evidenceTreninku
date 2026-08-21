<?php
declare(strict_types=1);

$staffTableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

return [
    'id' => '20260821150000_staff_workspaces',
    'up' => static function (PDO $pdo) use ($staffTableExists): void {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$staffTableExists($pdo, 'treneri')) throw new RuntimeException('Required treneri table is missing.');

        if (!$staffTableExists($pdo, 'staff_positions')) {
            $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE staff_positions (
    code VARCHAR(64) NOT NULL PRIMARY KEY,
    label VARCHAR(160) NOT NULL,
    sort_order INT NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE staff_positions (
    code TEXT NOT NULL PRIMARY KEY,
    label TEXT NOT NULL,
    sort_order INTEGER NOT NULL,
    active INTEGER NOT NULL DEFAULT 1
)
SQL);
        }
        if (!$staffTableExists($pdo, 'staff_user_positions')) {
            $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE staff_user_positions (
    trainer_id INT NOT NULL,
    position_code VARCHAR(64) NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    assigned_by_trainer_id INT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (trainer_id, position_code),
    INDEX idx_staff_user_positions_default (trainer_id, is_default),
    CONSTRAINT fk_staff_user_positions_trainer FOREIGN KEY (trainer_id) REFERENCES treneri(id) ON DELETE CASCADE,
    CONSTRAINT fk_staff_user_positions_position FOREIGN KEY (position_code) REFERENCES staff_positions(code) ON DELETE RESTRICT,
    CONSTRAINT fk_staff_user_positions_actor FOREIGN KEY (assigned_by_trainer_id) REFERENCES treneri(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE staff_user_positions (
    trainer_id INTEGER NOT NULL,
    position_code TEXT NOT NULL,
    is_default INTEGER NOT NULL DEFAULT 0,
    assigned_by_trainer_id INTEGER NULL,
    assigned_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (trainer_id, position_code),
    FOREIGN KEY (trainer_id) REFERENCES treneri(id) ON DELETE CASCADE,
    FOREIGN KEY (position_code) REFERENCES staff_positions(code) ON DELETE RESTRICT,
    FOREIGN KEY (assigned_by_trainer_id) REFERENCES treneri(id) ON DELETE SET NULL
)
SQL);
        }
        if (!$staffTableExists($pdo, 'staff_superadmins')) {
            $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE staff_superadmins (
    trainer_id INT NOT NULL PRIMARY KEY,
    granted_by_trainer_id INT NULL,
    granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reason VARCHAR(1000) NOT NULL,
    CONSTRAINT fk_staff_superadmins_trainer FOREIGN KEY (trainer_id) REFERENCES treneri(id) ON DELETE CASCADE,
    CONSTRAINT fk_staff_superadmins_actor FOREIGN KEY (granted_by_trainer_id) REFERENCES treneri(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE staff_superadmins (
    trainer_id INTEGER NOT NULL PRIMARY KEY,
    granted_by_trainer_id INTEGER NULL,
    granted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reason TEXT NOT NULL,
    FOREIGN KEY (trainer_id) REFERENCES treneri(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by_trainer_id) REFERENCES treneri(id) ON DELETE SET NULL
)
SQL);
        }
        if (!$staffTableExists($pdo, 'staff_position_switch_events')) {
            $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE staff_position_switch_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    trainer_id INT NOT NULL,
    from_position_code VARCHAR(64) NULL,
    to_position_code VARCHAR(64) NOT NULL,
    used_superadmin TINYINT(1) NOT NULL DEFAULT 0,
    reason VARCHAR(1000) NOT NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    user_agent VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_staff_position_switch_actor (trainer_id, created_at, id),
    CONSTRAINT fk_staff_position_switch_trainer FOREIGN KEY (trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT,
    CONSTRAINT fk_staff_position_switch_from FOREIGN KEY (from_position_code) REFERENCES staff_positions(code) ON DELETE RESTRICT,
    CONSTRAINT fk_staff_position_switch_to FOREIGN KEY (to_position_code) REFERENCES staff_positions(code) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE staff_position_switch_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    trainer_id INTEGER NOT NULL,
    from_position_code TEXT NULL,
    to_position_code TEXT NOT NULL,
    used_superadmin INTEGER NOT NULL DEFAULT 0,
    reason TEXT NOT NULL,
    ip_address TEXT NOT NULL DEFAULT '',
    user_agent TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT,
    FOREIGN KEY (from_position_code) REFERENCES staff_positions(code) ON DELETE RESTRICT,
    FOREIGN KEY (to_position_code) REFERENCES staff_positions(code) ON DELETE RESTRICT
)
SQL);
        }
        if (!$staffTableExists($pdo, 'staff_position_assignment_events')) {
            $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE staff_position_assignment_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    trainer_id INT NOT NULL,
    actor_trainer_id INT NOT NULL,
    action VARCHAR(64) NOT NULL,
    before_json LONGTEXT NULL,
    after_json LONGTEXT NOT NULL,
    reason VARCHAR(1000) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_staff_position_assignment_target (trainer_id, created_at, id),
    CONSTRAINT fk_staff_position_assignment_trainer FOREIGN KEY (trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT,
    CONSTRAINT fk_staff_position_assignment_actor FOREIGN KEY (actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE staff_position_assignment_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    trainer_id INTEGER NOT NULL,
    actor_trainer_id INTEGER NOT NULL,
    action TEXT NOT NULL,
    before_json TEXT NULL,
    after_json TEXT NOT NULL,
    reason TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT,
    FOREIGN KEY (actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
)
SQL);
        }

        $positions = [
            ['coach','Trenér',10],
            ['sports_lead','Vedoucí sportu',20],
            ['registrar','Registrář členů a KIS',30],
            ['program_coordinator','Koordinátor programů a sportovišť',40],
            ['catalog_manager','Správce katalogu e-shopu',50],
            ['order_operator','Zákaznická péče a objednávky',60],
            ['finance_manager','Hospodář a platby',70],
            ['system_admin','Správce systému',80],
        ];
        $insertPosition = $pdo->prepare($mysql
            ? 'INSERT IGNORE INTO staff_positions(code,label,sort_order,active) VALUES(?,?,?,1)'
            : 'INSERT OR IGNORE INTO staff_positions(code,label,sort_order,active) VALUES(?,?,?,1)');
        foreach ($positions as $position) $insertPosition->execute($position);

        $insertAssignment = $pdo->prepare($mysql
            ? 'INSERT IGNORE INTO staff_user_positions(trainer_id,position_code,is_default,assigned_by_trainer_id) VALUES(?,?,?,NULL)'
            : 'INSERT OR IGNORE INTO staff_user_positions(trainer_id,position_code,is_default,assigned_by_trainer_id) VALUES(?,?,?,NULL)');
        foreach ($pdo->query('SELECT id,role FROM treneri WHERE aktivni=1')->fetchAll(PDO::FETCH_ASSOC) as $trainer) {
            $role = (string)$trainer['role'];
            $codes = $role === 'admin'
                ? array_column($positions, 0)
                : ($role === 'hlavni' ? ['coach','sports_lead','registrar','program_coordinator'] : ['coach']);
            $default = $role === 'admin' ? 'system_admin' : ($role === 'hlavni' ? 'sports_lead' : 'coach');
            foreach ($codes as $code) $insertAssignment->execute([(int)$trainer['id'], $code, $code === $default ? 1 : 0]);
            if ($role === 'admin') {
                $statement = $pdo->prepare($mysql
                    ? "INSERT IGNORE INTO staff_superadmins(trainer_id,granted_by_trainer_id,reason) VALUES(?,NULL,'Převod stávající role admin při migraci')"
                    : "INSERT OR IGNORE INTO staff_superadmins(trainer_id,granted_by_trainer_id,reason) VALUES(?,NULL,'Převod stávající role admin při migraci')");
                $statement->execute([(int)$trainer['id']]);
            }
        }
    },
    'verify' => static function (PDO $pdo) use ($staffTableExists): bool {
        foreach (['staff_positions','staff_user_positions','staff_superadmins','staff_position_switch_events','staff_position_assignment_events'] as $table) {
            if (!$staffTableExists($pdo, $table)) return false;
        }
        if ((int)$pdo->query('SELECT COUNT(*) FROM staff_positions WHERE active=1')->fetchColumn() !== 8) return false;
        if ((int)$pdo->query(
            'SELECT COUNT(*) FROM treneri t WHERE t.aktivni=1 AND NOT EXISTS '
            . '(SELECT 1 FROM staff_user_positions p WHERE p.trainer_id=t.id)'
        )->fetchColumn() !== 0) return false;
        return (int)$pdo->query(
            'SELECT COUNT(*) FROM ('
            . 'SELECT t.id FROM treneri t JOIN staff_user_positions p ON p.trainer_id=t.id '
            . 'WHERE t.aktivni=1 GROUP BY t.id HAVING SUM(CASE WHEN p.is_default=1 THEN 1 ELSE 0 END)<>1'
            . ') invalid_defaults'
        )->fetchColumn() === 0;
    },
];
