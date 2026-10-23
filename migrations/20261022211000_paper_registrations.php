<?php
declare(strict_types=1);

return [
    'id' => '20261022211000_paper_registrations',
    'up' => static function (PDO $pdo): void {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $id = $mysql ? 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tail = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $pdo->exec('CREATE TABLE IF NOT EXISTS club_paper_records (
            id '.$id.', sportovec_id INT NOT NULL, kind VARCHAR(20) NOT NULL,
            scope_type VARCHAR(20) NOT NULL, scope_id BIGINT UNSIGNED NULL,
            document_title VARCHAR(200) NOT NULL, term_version_id BIGINT UNSIGNED NULL,
            version_label VARCHAR(200) NOT NULL, text_snapshot TEXT NOT NULL,
            signer_name VARCHAR(200) NOT NULL, signer_relation VARCHAR(20) NOT NULL,
            signed_on DATE NOT NULL, original_location VARCHAR(1000) NOT NULL,
            storage_key VARCHAR(255) NULL, file_sha256 CHAR(64) NULL,
            recorded_by INT NOT NULL, recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            replaces_id BIGINT UNSIGNED NULL UNIQUE,
            FOREIGN KEY(sportovec_id) REFERENCES sportovci(id),
            FOREIGN KEY(recorded_by) REFERENCES treneri(id),
            FOREIGN KEY(term_version_id) REFERENCES club_event_term_versions(id),
            FOREIGN KEY(replaces_id) REFERENCES club_paper_records(id)
        )'.$tail);
        $pdo->exec('CREATE TABLE IF NOT EXISTS club_paper_record_events (
            id '.$id.', record_id BIGINT UNSIGNED NOT NULL,
            event_type VARCHAR(20) NOT NULL, actor_id INT NOT NULL,
            note VARCHAR(1000) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(record_id) REFERENCES club_paper_records(id),
            FOREIGN KEY(actor_id) REFERENCES treneri(id)
        )'.$tail);
    },
    'verify' => static function (PDO $pdo): bool {
        try {
            $pdo->query('SELECT sportovec_id,kind,scope_type,scope_id,document_title,term_version_id,version_label,text_snapshot,signer_name,signer_relation,signed_on,original_location,storage_key,file_sha256,recorded_by,recorded_at,replaces_id FROM club_paper_records LIMIT 0');
            $pdo->query('SELECT record_id,event_type,actor_id,note,created_at FROM club_paper_record_events LIMIT 0');
            return true;
        } catch (PDOException) { return false; }
    },
];
