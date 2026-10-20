<?php
declare(strict_types=1);

return [
    'id' => '20261020180000_kis_full_person_import',
    'up' => static function (PDO $pdo): void {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $suffix = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        if ($mysql) {
            // Unknown source dates must remain unknown, never the import date.
            $exists=$pdo->query("SHOW COLUMNS FROM sportovci LIKE 'narozeni'")->fetch(PDO::FETCH_ASSOC);
            $pdo->exec('ALTER TABLE sportovci '.($exists?'MODIFY':'ADD').' narozeni DATE NULL DEFAULT NULL');
        }
        $pdo->exec('CREATE TABLE IF NOT EXISTS kis_person_batches (
            fingerprint VARCHAR(64) PRIMARY KEY,
            source_namespace VARCHAR(80) NOT NULL UNIQUE,
            actor_trainer_id INT NOT NULL,
            payload_ciphertext LONGTEXT NOT NULL,
            key_version VARCHAR(32) NOT NULL,
            summary_json TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (actor_trainer_id) REFERENCES treneri(id)
        )'.$suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS kis_person_profiles (
            sportovec_id INT PRIMARY KEY,
            canonical_key VARCHAR(80) NOT NULL UNIQUE,
            batch_fingerprint VARCHAR(64) NOT NULL,
            contact_address TEXT NOT NULL,
            country VARCHAR(100) NOT NULL,
            source_active INT NOT NULL,
            FOREIGN KEY (sportovec_id) REFERENCES sportovci(id),
            FOREIGN KEY (batch_fingerprint) REFERENCES kis_person_batches(fingerprint)
        )'.$suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS kis_person_aliases (
            source_key VARCHAR(80) PRIMARY KEY,
            sportovec_id INT NOT NULL,
            source_row INT NOT NULL,
            legacy_username VARCHAR(190) NOT NULL,
            FOREIGN KEY (sportovec_id) REFERENCES sportovci(id)
        )'.$suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS kis_person_contacts (
            contact_key VARCHAR(80) PRIMARY KEY,
            batch_fingerprint VARCHAR(64) NOT NULL,
            display_name VARCHAR(255) NOT NULL,
            names_json TEXT NOT NULL,
            emails_json TEXT NOT NULL,
            phones_json TEXT NOT NULL,
            linked_sportovec_id INT NULL,
            account_id INT NULL,
            account_state VARCHAR(40) NOT NULL,
            FOREIGN KEY (batch_fingerprint) REFERENCES kis_person_batches(fingerprint),
            FOREIGN KEY (linked_sportovec_id) REFERENCES sportovci(id),
            FOREIGN KEY (account_id) REFERENCES verejni_uzivatele(id)
        )'.$suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS kis_person_links (
            source_key VARCHAR(100) PRIMARY KEY,
            sportovec_id INT NOT NULL,
            contact_key VARCHAR(80) NOT NULL,
            relationship_label VARCHAR(255) NOT NULL,
            source_person_key VARCHAR(80) NOT NULL,
            source_slot INT NOT NULL,
            FOREIGN KEY (sportovec_id) REFERENCES sportovci(id),
            FOREIGN KEY (contact_key) REFERENCES kis_person_contacts(contact_key)
        )'.$suffix);
    },
    'verify' => static function (PDO $pdo): bool {
        try {
            foreach (['kis_person_batches','kis_person_profiles','kis_person_aliases','kis_person_contacts','kis_person_links'] as $table) {
                $pdo->query("SELECT * FROM $table LIMIT 0");
            }
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $column = $pdo->query("SHOW COLUMNS FROM sportovci LIKE 'narozeni'")->fetch(PDO::FETCH_ASSOC);
                if (($column['Null'] ?? '') !== 'YES') return false;
            }
            return true;
        } catch (PDOException) {
            return false;
        }
    },
];
