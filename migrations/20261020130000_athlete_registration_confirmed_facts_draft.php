<?php
declare(strict_types=1);

// Append the owner's confirmed facts to the existing *draft* notices. The
// source of authority to require a child's Czech birth number has not yet been
// confirmed, so these versions deliberately keep registration fail-closed.
return [
    'id' => '20261020130000_athlete_registration_confirmed_facts_draft',
    'up' => static function (PDO $pdo): void {
        $notices = [
            'member_data_notice' => [
                'member-data-notice-2026-10-16-vzor',
                'member-data-notice-2026-10-20-vzor-2',
                'VZOROVÝ TEXT – potvrzené údaje o správci a účelu; úplná informace o právních titulech, příjemcích a uchování po skončení členství čeká na doplnění. Správcem údajů je Tělovýchovná jednota KOVO, z.s. (TJ KOVO Praha), IČO 00541753, Nad kapličkou 3324/15, 100 00 Praha 10, kontakt info@kovopraha.cz. Údaje z žádosti klub používá pro posouzení registrace, evidenci sportovce a komunikaci s členem nebo jeho zákonným zástupcem. Klub potvrdil uchování po dobu členství; pravidla pro zamítnuté žádosti a dobu po jeho skončení dosud nejsou potvrzena.',
            ],
            'birth_number_legal_notice' => [
                'birth-number-legal-notice-2026-10-16-vzor',
                'birth-number-legal-notice-2026-10-20-vzor-2',
                'VZOROVÝ TEXT – chybí potvrzení konkrétního zákonného důvodu pro vyžadování rodného čísla a příjemce ve státní správě. Správcem údajů je Tělovýchovná jednota KOVO, z.s. (TJ KOVO Praha), IČO 00541753. Klub uvedl účel evidence sportovce a komunikace se státní správou a uchování po dobu členství. České rodné číslo se v systému ukládá šifrovaně a není určeno pro veřejné výpisy. U osoby, které české rodné číslo nebylo přiděleno, se náhradní číslo nevytváří. Samotné přečtení informace není souhlasem s jiným využitím rodného čísla.',
            ],
        ];
        $current = $pdo->prepare(
            "SELECT terms_version FROM club_event_term_versions WHERE scope_type='athlete_registration' "
            . "AND scope_key='athlete-registration-v1' AND consent_purpose=? ORDER BY created_at DESC,id DESC LIMIT 1"
        );
        $exists = $pdo->prepare(
            "SELECT 1 FROM club_event_term_versions WHERE scope_type='athlete_registration' "
            . "AND scope_key='athlete-registration-v1' AND consent_purpose=? AND terms_version=? LIMIT 1"
        );
        $insert = $pdo->prepare(
            "INSERT INTO club_event_term_versions(scope_type,scope_key,consent_purpose,event_id,terms_version,consent_text_plain,"
            . "cancellation_policy_plain,cancellation_deadline_at,actor_trainer_id,actor_type,actor_id) "
            . "VALUES('athlete_registration','athlete-registration-v1',?,NULL,?,?,NULL,NULL,NULL,'system',0)"
        );
        foreach ($notices as $purpose => [$oldVersion, $version, $text]) {
            $current->execute([$purpose]);
            if ($current->fetchColumn() !== $oldVersion) continue; // Never replace a later owner-authored notice.
            $exists->execute([$purpose, $version]);
            if (!$exists->fetchColumn()) $insert->execute([$purpose, $version, $text]);
        }
    },
    'verify' => static function (PDO $pdo): bool {
        $query = $pdo->prepare(
            "SELECT terms_version FROM club_event_term_versions WHERE scope_type='athlete_registration' "
            . "AND scope_key='athlete-registration-v1' AND consent_purpose=? ORDER BY created_at DESC,id DESC LIMIT 1"
        );
        foreach (['member_data_notice' => 'member-data-notice-2026-10-16-vzor',
                  'birth_number_legal_notice' => 'birth-number-legal-notice-2026-10-16-vzor'] as $purpose => $oldVersion) {
            $query->execute([$purpose]);
            $current = $query->fetchColumn();
            if ($current === false || $current === $oldVersion) return false;
        }
        return true;
    },
];
