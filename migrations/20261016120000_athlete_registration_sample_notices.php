<?php
declare(strict_types=1);

// This is deliberately a sample, not a legal approval. Replace it with the
// club's reviewed Article 13 notice before treating registration as cleared.
return [
    'id' => '20261016120000_athlete_registration_sample_notices',
    'up' => static function (PDO $pdo): void {
        $samples = [
            'member_data_notice' => [
                'member-data-notice-2026-10-16-vzor',
                'VZOROVÝ TEXT – čeká na doplnění a schválení klubem. Správcem údajů je Cyklistický klub KOVO Praha; kontaktní údaje správce, právní tituly jednotlivých účelů a konkrétní lhůty uchování je nutné doplnit. Údaje z přihlášky používáme k posouzení žádosti, vedení členské a tréninkové evidence a komunikaci s členem nebo zákonným zástupcem. Přístup mají pověřené osoby klubu a smluvní poskytovatelé systému v nezbytném rozsahu. Uchování trvá po dobu vyřízení žádosti a členství a poté jen po nezbytnou dobu podle příslušných právních povinností a případných nároků; přesné lhůty musí klub stanovit. Máte právo požádat o přístup, opravu či omezení zpracování a obrátit se na Úřad pro ochranu osobních údajů. Toto znění nenahrazuje úplnou informační povinnost podle čl. 13 GDPR.',
                'member-data-notice-2026-08-16-v1',
            ],
            'birth_number_legal_notice' => [
                'birth-number-legal-notice-2026-10-16-vzor',
                'VZOROVÝ TEXT – čeká na potvrzení konkrétního právního důvodu a lhůty uchování. České rodné číslo se má vyžadovat jen tehdy, je-li pro daný účel nezbytné a klub doloží oprávnění k jeho využití; právní předpis nebo jiný konkrétní titul musí být v konečném znění uveden. Údaj se v systému ukládá šifrovaně a není určen do veřejných výpisů. U osoby, které české rodné číslo nebylo přiděleno, se náhradní číslo nevytváří. Přístup a uchování se mají omezit na nezbytný rozsah a dobu; konkrétní lhůtu musí klub doplnit. Samotné potvrzení přečtení této informace není souhlasem s libovolným využitím rodného čísla.',
                'birth-number-legal-notice-2026-08-16-v1',
            ],
        ];
        $current = $pdo->prepare("SELECT terms_version FROM club_event_term_versions WHERE scope_type='athlete_registration' AND scope_key='athlete-registration-v1' AND consent_purpose=? ORDER BY created_at DESC,id DESC LIMIT 1");
        $exists = $pdo->prepare("SELECT 1 FROM club_event_term_versions WHERE scope_type='athlete_registration' AND scope_key='athlete-registration-v1' AND consent_purpose=? AND terms_version=? LIMIT 1");
        $insert = $pdo->prepare("INSERT INTO club_event_term_versions(scope_type,scope_key,consent_purpose,event_id,terms_version,consent_text_plain,cancellation_policy_plain,cancellation_deadline_at,actor_trainer_id,actor_type,actor_id) VALUES('athlete_registration','athlete-registration-v1',?,NULL,?,?,NULL,NULL,NULL,'system',0)");
        foreach ($samples as $purpose => [$version,$text,$oldVersion]) {
            $current->execute([$purpose]);
            if ($current->fetchColumn() !== $oldVersion) continue; // Preserve a newer, human-approved notice.
            $exists->execute([$purpose,$version]);
            if (!$exists->fetchColumn()) $insert->execute([$purpose,$version,$text]);
        }
    },
    'verify' => static function (PDO $pdo): bool {
        $query = $pdo->prepare("SELECT terms_version FROM club_event_term_versions WHERE scope_type='athlete_registration' AND scope_key='athlete-registration-v1' AND consent_purpose=? ORDER BY created_at DESC,id DESC LIMIT 1");
        foreach (['member_data_notice' => 'member-data-notice-2026-08-16-v1', 'birth_number_legal_notice' => 'birth-number-legal-notice-2026-08-16-v1'] as $purpose => $oldVersion) {
            $query->execute([$purpose]);
            $current = $query->fetchColumn();
            if ($current === false || $current === $oldVersion) return false;
        }
        return true;
    },
];
