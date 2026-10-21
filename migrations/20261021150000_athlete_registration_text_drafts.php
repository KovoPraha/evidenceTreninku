<?php
declare(strict_types=1);

return [
    'id' => '20261021150000_athlete_registration_text_drafts',
    'up' => static function (PDO $pdo): void {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $id = $mysql ? 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $pdo->exec('CREATE TABLE IF NOT EXISTS athlete_registration_text_drafts (
            id '.$id.',
            purpose VARCHAR(64) NOT NULL,
            text_plain TEXT NOT NULL,
            reason VARCHAR(1000) NOT NULL,
            base_term_id BIGINT UNSIGNED NOT NULL,
            created_by INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            published_term_id BIGINT UNSIGNED NULL UNIQUE,
            published_by INT NULL,
            published_at DATETIME NULL,
            FOREIGN KEY (base_term_id) REFERENCES club_event_term_versions(id),
            FOREIGN KEY (published_term_id) REFERENCES club_event_term_versions(id),
            FOREIGN KEY (created_by) REFERENCES treneri(id),
            FOREIGN KEY (published_by) REFERENCES treneri(id)
        )'.($mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : ''));
    },
    'verify' => static function (PDO $pdo): bool {
        try {
            $pdo->query('SELECT id,purpose,text_plain,reason,base_term_id,created_by,created_at,published_term_id,published_by,published_at FROM athlete_registration_text_drafts LIMIT 0');
            return true;
        } catch (PDOException) {
            return false;
        }
    },
];
