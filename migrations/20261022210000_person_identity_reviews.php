<?php
declare(strict_types=1);

return [
    'id' => '20261022210000_person_identity_reviews',
    'up' => static function (PDO $pdo): void {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $id = $mysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $suffix = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $sourceCollation = $mysql ? ' CHARACTER SET ascii COLLATE ascii_bin' : '';
        $pdo->exec('CREATE TABLE IF NOT EXISTS person_identity_reviews (
            id '.$id.',
            source_namespace VARCHAR(80)'.$sourceCollation.' NOT NULL,
            source_id VARCHAR(100)'.$sourceCollation.' NOT NULL,
            source_fingerprint VARCHAR(64) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(255) NOT NULL,
            birth_date DATE NULL,
            candidate_ids_json TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'review\',
            sportovec_id INT NULL,
            revision INT NOT NULL DEFAULT 1,
            reason TEXT NOT NULL,
            evidence_ref VARCHAR(255) NOT NULL,
            created_by_trainer_id INT NOT NULL,
            decided_by_trainer_id INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            decided_at DATETIME NULL,
            UNIQUE(source_namespace,source_id),
            FOREIGN KEY(sportovec_id) REFERENCES sportovci(id),
            FOREIGN KEY(created_by_trainer_id) REFERENCES treneri(id),
            FOREIGN KEY(decided_by_trainer_id) REFERENCES treneri(id)
        )'.$suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS person_identity_review_events (
            id '.$id.',
            review_id INT NOT NULL,
            actor_trainer_id INT NOT NULL,
            revision INT NOT NULL,
            before_json TEXT NOT NULL,
            after_json TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(review_id,revision),
            FOREIGN KEY(review_id) REFERENCES person_identity_reviews(id),
            FOREIGN KEY(actor_trainer_id) REFERENCES treneri(id)
        )'.$suffix);
    },
    'verify' => static function (PDO $pdo): bool {
        foreach (['person_identity_reviews','person_identity_review_events'] as $table) {
            try { $pdo->query('SELECT id FROM '.$table.' LIMIT 0'); } catch (PDOException) { return false; }
        }
        return true;
    },
];
