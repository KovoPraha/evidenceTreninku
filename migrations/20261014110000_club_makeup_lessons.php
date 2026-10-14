<?php
declare(strict_types=1);

$tableExists = static function (PDO $pdo, string $name): bool {
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $query = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $query->execute([$name]);
        return (bool)$query->fetchColumn();
    }
    $query = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
    $query->execute([$name]);
    return (bool)$query->fetchColumn();
};
$columnExists = static function (PDO $pdo, string $table, string $name): bool {
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $query = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $query->execute([$table, $name]);
        return (bool)$query->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $column) {
        if ($column['name'] === $name) return true;
    }
    return false;
};

return [
    'id' => '20261014110000_club_makeup_lessons',
    'up' => static function (PDO $pdo) use ($tableExists, $columnExists): void {
        foreach (['club_programs', 'club_program_offers', 'club_program_enrollments', 'club_seasons', 'club_teams', 'planovane_treninky', 'training_rsvps', 'sportovci', 'treneri'] as $required) {
            if (!$tableExists($pdo, $required)) throw new RuntimeException('Required makeup table is missing: ' . $required);
        }
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$columnExists($pdo, 'training_rsvps', 'makeup_excused_at')) {
            $pdo->exec($mysql ? 'ALTER TABLE training_rsvps ADD COLUMN makeup_excused_at DATETIME NULL' : 'ALTER TABLE training_rsvps ADD COLUMN makeup_excused_at TEXT NULL');
        }
        if (!$columnExists($pdo, 'training_rsvps', 'makeup_source_enrollment_id')) {
            $pdo->exec($mysql ? 'ALTER TABLE training_rsvps ADD COLUMN makeup_source_enrollment_id BIGINT UNSIGNED NULL' : 'ALTER TABLE training_rsvps ADD COLUMN makeup_source_enrollment_id INTEGER NULL');
        }
        if (!$columnExists($pdo, 'training_rsvps', 'makeup_cutoff_hours')) {
            $pdo->exec($mysql ? 'ALTER TABLE training_rsvps ADD COLUMN makeup_cutoff_hours SMALLINT UNSIGNED NULL' : 'ALTER TABLE training_rsvps ADD COLUMN makeup_cutoff_hours INTEGER NULL');
        }
        if (!$tableExists($pdo, 'club_makeup_policies')) $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE club_makeup_policies (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,program_id BIGINT UNSIGNED NOT NULL,season_id BIGINT UNSIGNED NOT NULL,
 team_id BIGINT UNSIGNED NOT NULL,enabled TINYINT(1) NOT NULL DEFAULT 0,excuse_hours SMALLINT UNSIGNED NOT NULL DEFAULT 12,
 updated_by_trainer_id INT NOT NULL,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_makeup_policy_scope(program_id,season_id,team_id),
 CONSTRAINT fk_makeup_policy_program FOREIGN KEY(program_id) REFERENCES club_programs(id) ON DELETE RESTRICT,
 CONSTRAINT fk_makeup_policy_season FOREIGN KEY(season_id) REFERENCES club_seasons(id) ON DELETE RESTRICT,
 CONSTRAINT fk_makeup_policy_team FOREIGN KEY(team_id) REFERENCES club_teams(id) ON DELETE RESTRICT,
 CONSTRAINT fk_makeup_policy_actor FOREIGN KEY(updated_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE club_makeup_policies(id INTEGER PRIMARY KEY AUTOINCREMENT,program_id INTEGER NOT NULL,season_id INTEGER NOT NULL,team_id INTEGER NOT NULL,enabled INTEGER NOT NULL DEFAULT 0,excuse_hours INTEGER NOT NULL DEFAULT 12,updated_by_trainer_id INTEGER NOT NULL,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(program_id,season_id,team_id),FOREIGN KEY(program_id) REFERENCES club_programs(id),FOREIGN KEY(season_id) REFERENCES club_seasons(id),FOREIGN KEY(team_id) REFERENCES club_teams(id),FOREIGN KEY(updated_by_trainer_id) REFERENCES treneri(id))
SQL);
        if (!$tableExists($pdo, 'club_makeup_offer_periods')) $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE club_makeup_offer_periods (
 offer_id BIGINT UNSIGNED PRIMARY KEY,period_kind VARCHAR(16) NOT NULL,next_offer_id BIGINT UNSIGNED NULL,
 updated_by_trainer_id INT NOT NULL,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_makeup_period_offer FOREIGN KEY(offer_id) REFERENCES club_program_offers(id) ON DELETE RESTRICT,
 CONSTRAINT fk_makeup_period_next FOREIGN KEY(next_offer_id) REFERENCES club_program_offers(id) ON DELETE RESTRICT,
 CONSTRAINT fk_makeup_period_actor FOREIGN KEY(updated_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE club_makeup_offer_periods(offer_id INTEGER PRIMARY KEY,period_kind TEXT NOT NULL,next_offer_id INTEGER NULL,updated_by_trainer_id INTEGER NOT NULL,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(offer_id) REFERENCES club_program_offers(id),FOREIGN KEY(next_offer_id) REFERENCES club_program_offers(id),FOREIGN KEY(updated_by_trainer_id) REFERENCES treneri(id))
SQL);
        if (!$tableExists($pdo, 'club_makeup_excuse_rules')) $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE club_makeup_excuse_rules (
 plan_id INT NOT NULL,policy_id BIGINT UNSIGNED NOT NULL,excuse_hours SMALLINT UNSIGNED NOT NULL,
 PRIMARY KEY(plan_id,policy_id),CONSTRAINT fk_makeup_rule_plan FOREIGN KEY(plan_id) REFERENCES planovane_treninky(id) ON DELETE CASCADE,
 CONSTRAINT fk_makeup_rule_policy FOREIGN KEY(policy_id) REFERENCES club_makeup_policies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE club_makeup_excuse_rules(plan_id INTEGER NOT NULL,policy_id INTEGER NOT NULL,excuse_hours INTEGER NOT NULL,PRIMARY KEY(plan_id,policy_id),FOREIGN KEY(plan_id) REFERENCES planovane_treninky(id) ON DELETE CASCADE,FOREIGN KEY(policy_id) REFERENCES club_makeup_policies(id))
SQL);
        if (!$tableExists($pdo, 'club_makeup_slots')) $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE club_makeup_slots (
 plan_id INT PRIMARY KEY,offer_id BIGINT UNSIGNED NOT NULL,capacity SMALLINT UNSIGNED NOT NULL,accepting TINYINT(1) NOT NULL DEFAULT 1,
 birth_year_from SMALLINT UNSIGNED NOT NULL,birth_year_to SMALLINT UNSIGNED NOT NULL,
 updated_by_trainer_id INT NOT NULL,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_makeup_slot_plan FOREIGN KEY(plan_id) REFERENCES planovane_treninky(id) ON DELETE CASCADE,
 CONSTRAINT fk_makeup_slot_offer FOREIGN KEY(offer_id) REFERENCES club_program_offers(id) ON DELETE RESTRICT,
 CONSTRAINT fk_makeup_slot_actor FOREIGN KEY(updated_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE club_makeup_slots(plan_id INTEGER PRIMARY KEY,offer_id INTEGER NOT NULL,capacity INTEGER NOT NULL,accepting INTEGER NOT NULL DEFAULT 1,birth_year_from INTEGER NOT NULL,birth_year_to INTEGER NOT NULL,updated_by_trainer_id INTEGER NOT NULL,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(plan_id) REFERENCES planovane_treninky(id) ON DELETE CASCADE,FOREIGN KEY(offer_id) REFERENCES club_program_offers(id),FOREIGN KEY(updated_by_trainer_id) REFERENCES treneri(id))
SQL);
        if (!$columnExists($pdo, 'club_makeup_slots', 'accepting')) {
            $pdo->exec($mysql ? 'ALTER TABLE club_makeup_slots ADD COLUMN accepting TINYINT(1) NOT NULL DEFAULT 1' : 'ALTER TABLE club_makeup_slots ADD COLUMN accepting INTEGER NOT NULL DEFAULT 1');
        }
        if (!$tableExists($pdo, 'club_makeup_credits')) $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE club_makeup_credits (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,source_plan_id INT NOT NULL,sportovec_id INT NOT NULL,
 source_enrollment_id BIGINT UNSIGNED NOT NULL,source_offer_id BIGINT UNSIGNED NOT NULL,carry_offer_id BIGINT UNSIGNED NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'available',expires_on DATE NOT NULL,excused_at DATETIME NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_makeup_source_person(source_plan_id,sportovec_id),KEY idx_makeup_credit_person(sportovec_id,status,expires_on),
 CONSTRAINT fk_makeup_credit_plan FOREIGN KEY(source_plan_id) REFERENCES planovane_treninky(id) ON DELETE RESTRICT,
 CONSTRAINT fk_makeup_credit_person FOREIGN KEY(sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,
 CONSTRAINT fk_makeup_credit_enrollment FOREIGN KEY(source_enrollment_id) REFERENCES club_program_enrollments(id) ON DELETE RESTRICT,
 CONSTRAINT fk_makeup_credit_source_offer FOREIGN KEY(source_offer_id) REFERENCES club_program_offers(id) ON DELETE RESTRICT,
 CONSTRAINT fk_makeup_credit_carry_offer FOREIGN KEY(carry_offer_id) REFERENCES club_program_offers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE club_makeup_credits(id INTEGER PRIMARY KEY AUTOINCREMENT,source_plan_id INTEGER NOT NULL,sportovec_id INTEGER NOT NULL,source_enrollment_id INTEGER NOT NULL,source_offer_id INTEGER NOT NULL,carry_offer_id INTEGER NULL,status TEXT NOT NULL DEFAULT 'available',expires_on TEXT NOT NULL,excused_at TEXT NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(source_plan_id,sportovec_id),FOREIGN KEY(source_plan_id) REFERENCES planovane_treninky(id),FOREIGN KEY(sportovec_id) REFERENCES sportovci(id),FOREIGN KEY(source_enrollment_id) REFERENCES club_program_enrollments(id),FOREIGN KEY(source_offer_id) REFERENCES club_program_offers(id),FOREIGN KEY(carry_offer_id) REFERENCES club_program_offers(id))
SQL);
        if (!$tableExists($pdo, 'club_makeup_reservations')) $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE club_makeup_reservations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,credit_id BIGINT UNSIGNED NOT NULL,target_plan_id INT NOT NULL,sportovec_id INT NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'booked',active_token VARCHAR(8) NULL DEFAULT 'active',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_makeup_active_credit(credit_id,active_token),UNIQUE KEY uq_makeup_active_target(target_plan_id,sportovec_id,active_token),
 KEY idx_makeup_target_status(target_plan_id,status),
 CONSTRAINT fk_makeup_reservation_credit FOREIGN KEY(credit_id) REFERENCES club_makeup_credits(id) ON DELETE RESTRICT,
 CONSTRAINT fk_makeup_reservation_plan FOREIGN KEY(target_plan_id) REFERENCES planovane_treninky(id) ON DELETE RESTRICT,
 CONSTRAINT fk_makeup_reservation_person FOREIGN KEY(sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE club_makeup_reservations(id INTEGER PRIMARY KEY AUTOINCREMENT,credit_id INTEGER NOT NULL,target_plan_id INTEGER NOT NULL,sportovec_id INTEGER NOT NULL,status TEXT NOT NULL DEFAULT 'booked',active_token TEXT NULL DEFAULT 'active',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(credit_id,active_token),UNIQUE(target_plan_id,sportovec_id,active_token),FOREIGN KEY(credit_id) REFERENCES club_makeup_credits(id),FOREIGN KEY(target_plan_id) REFERENCES planovane_treninky(id),FOREIGN KEY(sportovec_id) REFERENCES sportovci(id))
SQL);
        if (!$tableExists($pdo, 'club_makeup_events')) $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE club_makeup_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,credit_id BIGINT UNSIGNED NOT NULL,reservation_id BIGINT UNSIGNED NULL,
 action VARCHAR(32) NOT NULL,actor_type VARCHAR(20) NOT NULL,actor_id BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,KEY idx_makeup_event_credit(credit_id,id),
 CONSTRAINT fk_makeup_event_credit FOREIGN KEY(credit_id) REFERENCES club_makeup_credits(id) ON DELETE RESTRICT,
 CONSTRAINT fk_makeup_event_reservation FOREIGN KEY(reservation_id) REFERENCES club_makeup_reservations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE club_makeup_events(id INTEGER PRIMARY KEY AUTOINCREMENT,credit_id INTEGER NOT NULL,reservation_id INTEGER NULL,action TEXT NOT NULL,actor_type TEXT NOT NULL,actor_id INTEGER NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(credit_id) REFERENCES club_makeup_credits(id),FOREIGN KEY(reservation_id) REFERENCES club_makeup_reservations(id))
SQL);
        if (!$tableExists($pdo, 'club_makeup_config_events')) $pdo->exec($mysql ? <<<'SQL'
CREATE TABLE club_makeup_config_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,scope_type VARCHAR(24) NOT NULL,scope_id BIGINT UNSIGNED NOT NULL,
 actor_trainer_id INT NOT NULL,before_json LONGTEXT NULL,after_json LONGTEXT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_makeup_config_scope(scope_type,scope_id,id),
 CONSTRAINT fk_makeup_config_actor FOREIGN KEY(actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL : <<<'SQL'
CREATE TABLE club_makeup_config_events(id INTEGER PRIMARY KEY AUTOINCREMENT,scope_type TEXT NOT NULL,scope_id INTEGER NOT NULL,actor_trainer_id INTEGER NOT NULL,before_json TEXT NULL,after_json TEXT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(actor_trainer_id) REFERENCES treneri(id))
SQL);
    },
    'verify' => static fn(PDO $pdo): bool => $tableExists($pdo, 'club_makeup_events')
        && $tableExists($pdo, 'club_makeup_config_events')
        && $tableExists($pdo, 'club_makeup_credits') && $tableExists($pdo, 'club_makeup_slots')
        && $tableExists($pdo, 'club_makeup_policies') && $tableExists($pdo, 'club_makeup_offer_periods')
        && $tableExists($pdo, 'club_makeup_excuse_rules') && $tableExists($pdo, 'club_makeup_reservations')
        && $columnExists($pdo, 'training_rsvps', 'makeup_excused_at')
        && $columnExists($pdo, 'training_rsvps', 'makeup_source_enrollment_id')
        && $columnExists($pdo, 'training_rsvps', 'makeup_cutoff_hours')
        && $columnExists($pdo, 'club_makeup_slots', 'accepting'),
];
