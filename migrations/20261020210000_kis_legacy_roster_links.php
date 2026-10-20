<?php
declare(strict_types=1);

return [
    'id' => '20261020210000_kis_legacy_roster_links',
    'up' => static function (PDO $pdo): void {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $suffix = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $id = $mysql ? 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $big = $mysql ? 'BIGINT UNSIGNED' : 'BIGINT';
        $pdo->exec('CREATE TABLE IF NOT EXISTS kis_legacy_roster_runs (
            batch_fingerprint VARCHAR(64) PRIMARY KEY,
            plan_fingerprint VARCHAR(64) NOT NULL,
            season_id '.$big.' NOT NULL,
            summary_json TEXT NOT NULL,
            actor_trainer_id INT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (batch_fingerprint) REFERENCES kis_person_batches(fingerprint),
            FOREIGN KEY (season_id) REFERENCES club_seasons(id),
            FOREIGN KEY (actor_trainer_id) REFERENCES treneri(id)
        )'.$suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS kis_legacy_roster_claims (
            id '.$id.',
            batch_fingerprint VARCHAR(64) NOT NULL,
            sportovec_id INT NOT NULL,
            team_id '.$big.' NOT NULL,
            legacy_label VARCHAR(160) NOT NULL,
            source_keys_json TEXT NOT NULL,
            reason_code VARCHAR(48) NOT NULL,
            status VARCHAR(24) NOT NULL,
            member_id '.$big.' NULL,
            decision_note VARCHAR(1000) NULL,
            decided_by_trainer_id INT NULL,
            decided_at TIMESTAMP NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (batch_fingerprint,sportovec_id,team_id),
            FOREIGN KEY (batch_fingerprint) REFERENCES kis_legacy_roster_runs(batch_fingerprint),
            FOREIGN KEY (sportovec_id) REFERENCES sportovci(id),
            FOREIGN KEY (team_id) REFERENCES club_teams(id),
            FOREIGN KEY (member_id) REFERENCES club_roster_members(id),
            FOREIGN KEY (decided_by_trainer_id) REFERENCES treneri(id)
        )'.$suffix);
    },
    'verify' => static function (PDO $pdo): bool {
        try {
            foreach (['kis_legacy_roster_runs','kis_legacy_roster_claims'] as $table) $pdo->query("SELECT * FROM $table LIMIT 0");
            return true;
        } catch (PDOException) { return false; }
    },
];
