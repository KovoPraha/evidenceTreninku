<?php
declare(strict_types=1);

$titleColumnExists = static function (PDO $pdo, string $table): bool {
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $query = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME='title_plain'");
        $query->execute([$table]);
        return (bool)$query->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row['name'] === 'title_plain') return true;
    }
    return false;
};

return [
    'id' => '20261021170000_registration_text_titles',
    'up' => static function (PDO $pdo) use ($titleColumnExists): void {
        foreach (['club_event_term_versions', 'athlete_registration_text_drafts'] as $table) {
            if (!$titleColumnExists($pdo, $table)) $pdo->exec('ALTER TABLE '.$table.' ADD COLUMN title_plain VARCHAR(200) NULL');
        }
        // Freeze the names used before this editor existed; never rewrite a
        // title already saved by a registrar or another run of this migration.
        $defaults = [
            'member_data_notice' => 'Informace o evidenci člena',
            'birth_number_legal_notice' => 'Informace k rodnému číslu',
            'photo_internal' => 'Souhlas s interním použitím fotografie',
            'photo_public' => 'Souhlas se zveřejněním fotografie',
            'csc_birth_number_sharing' => 'Volitelné předání rodného čísla ČSC',
        ];
        $terms = $pdo->prepare("UPDATE club_event_term_versions SET title_plain=? WHERE scope_type='athlete_registration' AND scope_key='athlete-registration-v1' AND consent_purpose=? AND title_plain IS NULL");
        $drafts = $pdo->prepare('UPDATE athlete_registration_text_drafts SET title_plain=? WHERE purpose=? AND title_plain IS NULL');
        foreach ($defaults as $purpose => $title) {
            $terms->execute([$title, $purpose]);
            $drafts->execute([$title, $purpose]);
        }
    },
    'verify' => static function (PDO $pdo) use ($titleColumnExists): bool {
        return $titleColumnExists($pdo, 'club_event_term_versions') && $titleColumnExists($pdo, 'athlete_registration_text_drafts');
    },
];
