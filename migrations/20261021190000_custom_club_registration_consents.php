<?php
declare(strict_types=1);

$consentColumnExists = static function (PDO $pdo, string $table, string $column): bool {
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $q=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $q->execute([$table,$column]); return (bool)$q->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC) as $row) if ($row['name']===$column) return true;
    return false;
};
return [
    'id'=>'20261021190000_custom_club_registration_consents',
    'up'=>static function(PDO $pdo) use($consentColumnExists):void {
        $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        $id=$mysql?'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
        $suffix=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
        $pdo->exec('CREATE TABLE IF NOT EXISTS club_registration_consents (
            id '.$id.', purpose VARCHAR(64) NOT NULL UNIQUE,
            current_term_id BIGINT UNSIGNED NULL, created_by INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(current_term_id) REFERENCES club_event_term_versions(id),
            FOREIGN KEY(created_by) REFERENCES treneri(id)
        )'.$suffix);
        foreach (['club_event_term_versions'=>['custom_required'=>'TINYINT NULL','custom_visible'=>'TINYINT NULL'],
            'athlete_registration_text_drafts'=>['custom_consent_id'=>$mysql?'BIGINT UNSIGNED NULL':'INTEGER NULL REFERENCES club_registration_consents(id)','custom_required'=>'TINYINT NULL','custom_visible'=>'TINYINT NULL']] as $table=>$columns) {
            foreach($columns as $column=>$type) if(!$consentColumnExists($pdo,$table,$column)) $pdo->exec('ALTER TABLE '.$table.' ADD COLUMN '.$column.' '.$type);
        }
        if($mysql){
            $fk=$pdo->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='athlete_registration_text_drafts' AND CONSTRAINT_NAME='fk_registration_draft_custom'");
            if(!$fk->fetchColumn())$pdo->exec('ALTER TABLE athlete_registration_text_drafts ADD CONSTRAINT fk_registration_draft_custom FOREIGN KEY(custom_consent_id) REFERENCES club_registration_consents(id)');
        }
        $pdo->exec('CREATE TABLE IF NOT EXISTS club_registration_consent_acceptances (
            id '.$id.', order_item_id BIGINT UNSIGNED NOT NULL,
            account_id INT NOT NULL, term_version_id BIGINT UNSIGNED NOT NULL,
            title_snapshot VARCHAR(200) NOT NULL, text_snapshot TEXT NOT NULL,
            required_snapshot TINYINT NOT NULL, accepted TINYINT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(order_item_id,term_version_id),
            FOREIGN KEY(order_item_id) REFERENCES shop_order_items(id),
            FOREIGN KEY(account_id) REFERENCES verejni_uzivatele(id),
            FOREIGN KEY(term_version_id) REFERENCES club_event_term_versions(id)
        )'.$suffix);
    },
    'verify'=>static function(PDO $pdo) use($consentColumnExists):bool {
        try {
            $pdo->query('SELECT id,purpose,current_term_id,created_by FROM club_registration_consents LIMIT 0');
            $pdo->query('SELECT order_item_id,account_id,term_version_id,title_snapshot,text_snapshot,required_snapshot,accepted FROM club_registration_consent_acceptances LIMIT 0');
            foreach(['club_event_term_versions'=>['custom_required','custom_visible'],'athlete_registration_text_drafts'=>['custom_consent_id','custom_required','custom_visible']] as $table=>$columns)
                foreach($columns as $column) if(!$consentColumnExists($pdo,$table,$column)) return false;
            return true;
        } catch(PDOException) { return false; }
    },
];
