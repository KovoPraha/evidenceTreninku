<?php
declare(strict_types=1);

// Owner-confirmed controller, state-support/NSA duty and retention policy.
// ČSC receives a birth number only after its own separately recorded consent.
return [
    'id' => '20261020160000_athlete_registration_final_notices',
    'up' => static function (PDO $pdo): void {
        $notices = [
            'member_data_notice' => [
                'member-data-notice-2026-10-20-vzor-2',
                'member-data-notice-2026-10-20-v1',
                'Správcem osobních údajů je Tělovýchovná jednota KOVO, z.s. (TJ KOVO Praha), IČO 00541753, Nad kapličkou 3324/15, 100 00 Praha 10, info@kovopraha.cz. Údaje z přihlášky používáme k posouzení žádosti, vedení členské a sportovní evidence, organizaci účasti a komunikaci s členem či zákonným zástupcem. Právním základem těchto činností jsou kroky k uzavření a plnění členského a účastnického vztahu podle čl. 6 odst. 1 písm. b) GDPR; pro zápis evidovaných sportovců do Rejstříku sportu plníme právní povinnost podle čl. 6 odst. 1 písm. c) GDPR a § 3e odst. 2 zákona č. 115/2001 Sb. Nezbytné údaje mohou získat Národní sportovní agentura, Český svaz cyklistiky pro sportovní evidenci a smluvní technický provozovatel systému. Rodné číslo předáme ČSC jen při samostatně potvrzeném oprávnění popsaném níže. Údaje evidovaného sportovce uchováváme po dobu členství; při zamítnutí či zrušení žádosti se citlivé údaje zařadí k řízenému výmazu po 30 dnech. Po skončení členství se údaje vymažou, pokud konkrétní zákonná povinnost nebo uplatnění právního nároku nevyžaduje další uchování; v tom případě se rozsah a doba omezí na daný účel. Poskytnutí údajů potřebných k identifikaci a vedení sportovce je podmínkou vyřízení žádosti. Máte právo požádat o přístup, opravu, výmaz nebo omezení zpracování, v příslušných případech vznést námitku a získat údaje pro přenos. Stížnost lze podat Úřadu pro ochranu osobních údajů. O přijetí sportovce rozhoduje administrátor, nikoli automatizované rozhodování.',
            ],
            'birth_number_legal_notice' => [
                'birth-number-legal-notice-2026-10-20-vzor-2',
                'birth-number-legal-notice-2026-10-20-v1',
                'TJ KOVO Praha žádá o podporu ze státního rozpočtu a evidované sportovce zapisuje do Rejstříku sportu Národní sportovní agentury. Podle § 3e odst. 2 písm. c) zákona č. 115/2001 Sb. do něj zapisuje jméno, příjmení a rodné číslo sportovce; jde o plnění právní povinnosti podle čl. 6 odst. 1 písm. c) GDPR a o využití rodného čísla podle § 13c odst. 1 písm. b) zákona č. 133/2000 Sb. České rodné číslo je proto u sportovce, kterému bylo přiděleno, pro tuto evidenci potřebné. Rodné číslo uchováváme v šifrované podobě po dobu členství a nezveřejňujeme je. Při zamítnutí či zrušení žádosti je systém označí k řízenému výmazu s termínem za 30 dní; po skončení členství podléhá řízenému výmazu, pokud konkrétní zákonná povinnost nevyžaduje delší uchování. Českému svazu cyklistiky předáme rodné číslo pro jeho sportovní evidenci pouze na základě samostatného výslovného souhlasu nositele nebo jeho zákonného zástupce podle § 13c odst. 1 písm. d) zákona č. 133/2000 Sb.; tento volitelný souhlas není podmínkou klubové registrace a lze jej odvolat pro budoucí předání. U osoby, které české rodné číslo nebylo přiděleno, se nevytváří náhradní číslo.',
            ],
            'csc_birth_number_sharing' => [
                null,
                'csc-birth-number-sharing-2026-10-20-v1',
                'Souhlasím s tím, aby Tělovýchovná jednota KOVO, z.s. předala rodné číslo vybraného sportovce Českému svazu cyklistiky pro jeho sportovní evidenci. Jsem nositelem rodného čísla nebo zákonným zástupcem dítěte. Tento souhlas je volitelný a není podmínkou členství ani účasti v kroužku. Lze jej odvolat vůči klubu na info@kovopraha.cz; odvolání se vztahuje na budoucí předání a nemění již zákonně provedené zpracování.',
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
        foreach ($notices as $purpose => [$previous, $version, $text]) {
            $current->execute([$purpose]);
            $active = $current->fetchColumn();
            if ($active === false) $active = null;
            if ($active === $version) continue;
            if ($active !== $previous) {
                throw new RuntimeException('Nelze přepsat odlišnou schválenou verzi informace: ' . $purpose);
            }
            $exists->execute([$purpose, $version]);
            if (!$exists->fetchColumn()) $insert->execute([$purpose, $version, $text]);
        }
    },
    'verify' => static function (PDO $pdo): bool {
        $query = $pdo->prepare(
            "SELECT terms_version FROM club_event_term_versions WHERE scope_type='athlete_registration' "
            . "AND scope_key='athlete-registration-v1' AND consent_purpose=? ORDER BY created_at DESC,id DESC LIMIT 1"
        );
        foreach (['member_data_notice' => 'member-data-notice-2026-10-20-v1',
                  'birth_number_legal_notice' => 'birth-number-legal-notice-2026-10-20-v1',
                  'csc_birth_number_sharing' => 'csc-birth-number-sharing-2026-10-20-v1'] as $purpose => $version) {
            $query->execute([$purpose]);
            $current = $query->fetchColumn();
            if ($current !== $version) return false;
        }
        return true;
    },
];
