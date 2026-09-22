<?php
declare(strict_types=1);

$tableExists = static function(PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    } else {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
    }
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

return [
    'id' => '20260922120000_member_fee_plans',
    'up' => static function(PDO $pdo) use ($tableExists): void {
        foreach (['club_teams','sportovci','treneri','club_member_charges'] as $required) {
            if (!$tableExists($pdo, $required)) throw new RuntimeException('Required member fee plan table is missing: ' . $required);
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$tableExists($pdo, 'member_fee_plans')) $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE member_fee_plans (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                team_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(160) NOT NULL,
                charge_title VARCHAR(255) NOT NULL,
                amount_minor BIGINT UNSIGNED NOT NULL,
                currency CHAR(3) NOT NULL DEFAULT 'CZK',
                due_day TINYINT UNSIGNED NOT NULL,
                starts_on DATE NOT NULL,
                ends_on DATE NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'active',
                created_by_trainer_id INT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_member_fee_plan_status_dates(status,starts_on,ends_on),
                KEY idx_member_fee_plan_team(team_id,status),
                CONSTRAINT fk_member_fee_plan_team FOREIGN KEY(team_id) REFERENCES club_teams(id) ON DELETE RESTRICT,
                CONSTRAINT fk_member_fee_plan_creator FOREIGN KEY(created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE member_fee_plans(id INTEGER PRIMARY KEY AUTOINCREMENT,team_id INTEGER NOT NULL,name TEXT NOT NULL,charge_title TEXT NOT NULL,amount_minor INTEGER NOT NULL,currency TEXT NOT NULL DEFAULT 'CZK',due_day INTEGER NOT NULL,starts_on TEXT NOT NULL,ends_on TEXT NULL,status TEXT NOT NULL DEFAULT 'active',created_by_trainer_id INTEGER NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(team_id) REFERENCES club_teams(id) ON DELETE RESTRICT,FOREIGN KEY(created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT)
            SQL);
        if (!$tableExists($pdo, 'member_fee_plan_exclusions')) $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE member_fee_plan_exclusions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                plan_id BIGINT UNSIGNED NOT NULL,
                sportovec_id INT NOT NULL,
                valid_from DATE NOT NULL,
                valid_to DATE NULL,
                reason VARCHAR(1000) NOT NULL,
                created_by_trainer_id INT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_member_fee_plan_exclusion(plan_id,sportovec_id,valid_from),
                CONSTRAINT fk_member_fee_exclusion_plan FOREIGN KEY(plan_id) REFERENCES member_fee_plans(id) ON DELETE RESTRICT,
                CONSTRAINT fk_member_fee_exclusion_person FOREIGN KEY(sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,
                CONSTRAINT fk_member_fee_exclusion_creator FOREIGN KEY(created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE member_fee_plan_exclusions(id INTEGER PRIMARY KEY AUTOINCREMENT,plan_id INTEGER NOT NULL,sportovec_id INTEGER NOT NULL,valid_from TEXT NOT NULL,valid_to TEXT NULL,reason TEXT NOT NULL,created_by_trainer_id INTEGER NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(plan_id,sportovec_id,valid_from),FOREIGN KEY(plan_id) REFERENCES member_fee_plans(id) ON DELETE RESTRICT,FOREIGN KEY(sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,FOREIGN KEY(created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT)
            SQL);
        if (!$tableExists($pdo, 'member_fee_plan_runs')) $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE member_fee_plan_runs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                plan_id BIGINT UNSIGNED NOT NULL,
                period_key CHAR(7) NOT NULL,
                preview_fingerprint CHAR(64) NOT NULL,
                generated_count INT UNSIGNED NOT NULL,
                skipped_count INT UNSIGNED NOT NULL,
                actor_trainer_id INT NOT NULL,
                reason VARCHAR(1000) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_member_fee_plan_run(plan_id,period_key),
                CONSTRAINT fk_member_fee_run_plan FOREIGN KEY(plan_id) REFERENCES member_fee_plans(id) ON DELETE RESTRICT,
                CONSTRAINT fk_member_fee_run_actor FOREIGN KEY(actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE member_fee_plan_runs(id INTEGER PRIMARY KEY AUTOINCREMENT,plan_id INTEGER NOT NULL,period_key TEXT NOT NULL,preview_fingerprint TEXT NOT NULL,generated_count INTEGER NOT NULL,skipped_count INTEGER NOT NULL,actor_trainer_id INTEGER NOT NULL,reason TEXT NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(plan_id,period_key),FOREIGN KEY(plan_id) REFERENCES member_fee_plans(id) ON DELETE RESTRICT,FOREIGN KEY(actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT)
            SQL);
        if (!$tableExists($pdo, 'member_fee_plan_run_items')) $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE member_fee_plan_run_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                run_id BIGINT UNSIGNED NOT NULL,
                sportovec_id INT NOT NULL,
                payer_account_id INT NULL,
                charge_id BIGINT UNSIGNED NULL,
                result VARCHAR(32) NOT NULL,
                detail VARCHAR(1000) NOT NULL,
                UNIQUE KEY uq_member_fee_run_item(run_id,sportovec_id),
                CONSTRAINT fk_member_fee_item_run FOREIGN KEY(run_id) REFERENCES member_fee_plan_runs(id) ON DELETE RESTRICT,
                CONSTRAINT fk_member_fee_item_person FOREIGN KEY(sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,
                CONSTRAINT fk_member_fee_item_charge FOREIGN KEY(charge_id) REFERENCES club_member_charges(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE member_fee_plan_run_items(id INTEGER PRIMARY KEY AUTOINCREMENT,run_id INTEGER NOT NULL,sportovec_id INTEGER NOT NULL,payer_account_id INTEGER NULL,charge_id INTEGER NULL,result TEXT NOT NULL,detail TEXT NOT NULL,UNIQUE(run_id,sportovec_id),FOREIGN KEY(run_id) REFERENCES member_fee_plan_runs(id) ON DELETE RESTRICT,FOREIGN KEY(sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,FOREIGN KEY(charge_id) REFERENCES club_member_charges(id) ON DELETE RESTRICT)
            SQL);
    },
    'verify' => static function(PDO $pdo) use ($tableExists): bool {
        foreach (['member_fee_plans','member_fee_plan_exclusions','member_fee_plan_runs','member_fee_plan_run_items'] as $table) if (!$tableExists($pdo, $table)) return false;
        return true;
    },
];
