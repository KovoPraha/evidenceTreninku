<?php
declare(strict_types=1);

$memberPriceTableExists = static function (PDO $pdo, string $table): bool {
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
    'id' => '20260805010000_shop_member_pricing',
    'up' => static function (PDO $pdo) use ($memberPriceTableExists): void {
        foreach (['club_teams','shop_products','treneri'] as $required) {
            if (!$memberPriceTableExists($pdo, $required)) throw new RuntimeException('Missing member pricing dependency: '.$required);
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$memberPriceTableExists($pdo, 'shop_member_category_rules')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE shop_member_category_rules (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    team_id BIGINT UNSIGNED NOT NULL,
                    category_path VARCHAR(500) NOT NULL,
                    discount_type VARCHAR(24) NOT NULL,
                    value_minor_or_basis_points BIGINT NOT NULL,
                    currency CHAR(3) NULL,
                    active TINYINT(1) NOT NULL DEFAULT 1,
                    created_by_trainer_id INT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_member_category_team_path (team_id,category_path),
                    KEY idx_member_category_active (active,category_path,team_id),
                    CONSTRAINT fk_member_category_team FOREIGN KEY(team_id) REFERENCES club_teams(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_member_category_actor FOREIGN KEY(created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE shop_member_category_rules (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,team_id INTEGER NOT NULL,category_path TEXT NOT NULL,
                    discount_type TEXT NOT NULL,value_minor_or_basis_points INTEGER NOT NULL,currency TEXT NULL,
                    active INTEGER NOT NULL DEFAULT 1,created_by_trainer_id INTEGER NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(team_id,category_path),FOREIGN KEY(team_id) REFERENCES club_teams(id) ON DELETE RESTRICT,
                    FOREIGN KEY(created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT)
                SQL);
        }
        if (!$memberPriceTableExists($pdo, 'shop_member_product_prices')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE shop_member_product_prices (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    team_id BIGINT UNSIGNED NOT NULL,product_id BIGINT UNSIGNED NOT NULL,
                    amount_minor BIGINT NOT NULL,currency CHAR(3) NOT NULL,active TINYINT(1) NOT NULL DEFAULT 1,
                    created_by_trainer_id INT NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_member_product_team_product (team_id,product_id),
                    KEY idx_member_product_active (active,product_id,team_id),
                    CONSTRAINT fk_member_product_team FOREIGN KEY(team_id) REFERENCES club_teams(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_member_product_product FOREIGN KEY(product_id) REFERENCES shop_products(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_member_product_actor FOREIGN KEY(created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE shop_member_product_prices (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,team_id INTEGER NOT NULL,product_id INTEGER NOT NULL,
                    amount_minor INTEGER NOT NULL,currency TEXT NOT NULL,active INTEGER NOT NULL DEFAULT 1,
                    created_by_trainer_id INTEGER NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(team_id,product_id),
                    FOREIGN KEY(team_id) REFERENCES club_teams(id) ON DELETE RESTRICT,
                    FOREIGN KEY(product_id) REFERENCES shop_products(id) ON DELETE RESTRICT,
                    FOREIGN KEY(created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT)
                SQL);
        }
        if (!$memberPriceTableExists($pdo, 'shop_member_price_events')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE shop_member_price_events (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,rule_type VARCHAR(24) NOT NULL,rule_id BIGINT UNSIGNED NOT NULL,
                    team_id BIGINT UNSIGNED NOT NULL,product_id BIGINT UNSIGNED NULL,category_path VARCHAR(500) NULL,
                    actor_trainer_id INT NOT NULL,action VARCHAR(24) NOT NULL,before_json LONGTEXT NULL,after_json LONGTEXT NULL,
                    note VARCHAR(1000) NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_member_price_event_team (team_id,id),
                    CONSTRAINT fk_member_price_event_team FOREIGN KEY(team_id) REFERENCES club_teams(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_member_price_event_actor FOREIGN KEY(actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE shop_member_price_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,rule_type TEXT NOT NULL,rule_id INTEGER NOT NULL,team_id INTEGER NOT NULL,
                    product_id INTEGER NULL,category_path TEXT NULL,actor_trainer_id INTEGER NOT NULL,action TEXT NOT NULL,
                    before_json TEXT NULL,after_json TEXT NULL,note TEXT NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(team_id) REFERENCES club_teams(id) ON DELETE RESTRICT,
                    FOREIGN KEY(actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT)
                SQL);
        }
    },
    'verify' => static fn(PDO $pdo): bool => $memberPriceTableExists($pdo, 'shop_member_category_rules')
        && $memberPriceTableExists($pdo, 'shop_member_product_prices')
        && $memberPriceTableExists($pdo, 'shop_member_price_events'),
];
