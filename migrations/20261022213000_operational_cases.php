<?php
declare(strict_types=1);
return [
    'id' => '20261022213000_operational_cases',
    'up' => static function(PDO $pdo): void {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $id = $mysql ? 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tail = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $pdo->exec('CREATE TABLE IF NOT EXISTS operational_cases (
            id '.$id.', source_type VARCHAR(40) NOT NULL, source_id BIGINT UNSIGNED NOT NULL,
            owner_trainer_id INT NOT NULL, next_step VARCHAR(1000) NOT NULL,
            review_on DATE NOT NULL, status VARCHAR(16) NOT NULL DEFAULT \'open\',
            revision INT NOT NULL DEFAULT 1, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(source_type,source_id), FOREIGN KEY(owner_trainer_id) REFERENCES treneri(id)
        )'.$tail);
        $pdo->exec('CREATE TABLE IF NOT EXISTS operational_case_events (
            id '.$id.', case_id BIGINT UNSIGNED NOT NULL, actor_trainer_id INT NOT NULL,
            revision INT NOT NULL, reason VARCHAR(1000) NOT NULL,
            before_json TEXT NOT NULL, after_json TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(case_id,revision), FOREIGN KEY(case_id) REFERENCES operational_cases(id),
            FOREIGN KEY(actor_trainer_id) REFERENCES treneri(id)
        )'.$tail);
    },
    'verify'=>static function(PDO $pdo):bool {
        try {
            $pdo->query('SELECT id,source_type,source_id,owner_trainer_id,next_step,review_on,status,revision,updated_at FROM operational_cases LIMIT 0');
            $pdo->query('SELECT id,case_id,actor_trainer_id,revision,reason,before_json,after_json,created_at FROM operational_case_events LIMIT 0');
            return true;
        } catch(PDOException) { return false; }
    },
];
