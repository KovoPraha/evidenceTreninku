<?php
/**
 * Auto-migrace databázového schématu — Evidence tréninků
 *
 * Spouští se automaticky z db.php při každém requestu.
 * Tato legacy migrace obsluhuje pouze pevny baseline 2.20.2. Pokud je verze
 * shodna, vyssi nebo neplatna, nic nemeni a nikdy tracker nesnizuje.
 *
 * Nove zmeny patri do cislovanych migraci v migrations/ a spousti se pres
 * bin/migrate.php. Tento soubor ani LEGACY_SCHEMA_VERSION uz nezvysovat.
 */

require_once __DIR__ . '/schema_version.php';

(function (PDO $pdo): void {

    // ── 0. Zajisti existenci tabulky nastaveni (tracker verze) ───────────────
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS nastaveni (
            klic     VARCHAR(80)  NOT NULL,
            hodnota  TEXT         NOT NULL DEFAULT '',
            upraveno TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (klic)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (PDOException $e) {
        error_log('auto_migrace: nastaveni table create failed: ' . $e->getMessage());
        return;
    }

    // ── 1. Zkontroluj aktuální verzi ─────────────────────────────────────────
    try {
        $stmt   = $pdo->query("SELECT hodnota FROM nastaveni WHERE klic = 'schema_version'");
        $verze  = $stmt ? (string)$stmt->fetchColumn() : '';
        $legacyState = evidence_legacy_schema_state($verze);
        if (!in_array($legacyState, ['missing', 'pending'], true)) {
            if ($legacyState === 'invalid') {
                error_log('auto_migrace: invalid schema_version; refusing to change database');
            }
            return; // Baseline je aktualni/vyssi, nebo tracker neni bezpecne citelny.
        }
    } catch (PDOException $e) {
        return;
    }

    // ── 1b. Advisory lock — zabrání race condition při paralelních requestech ─
    // GET_LOCK vrátí 1 = zámek získán, 0 = jiný request již migruje → konec
    try {
        $lockRow = $pdo->query("SELECT GET_LOCK('evidence_auto_migrace', 0) AS got")->fetch(PDO::FETCH_ASSOC);
        if (!$lockRow || (int)$lockRow['got'] !== 1) {
            return; // Jiný request provádí migraci
        }
    } catch (PDOException $e) {
        // Pokud GET_LOCK není dostupný (jiný engine), pokračuj bez zámku
    }

    // ── 2. Pomocné funkce ────────────────────────────────────────────────────

    /** Vrátí true pokud sloupec v tabulce existuje */
    $colExists = static function (string $table, string $col) use ($pdo): bool {
        try {
            $st = $pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
            );
            $st->execute([$table, $col]);
            return (bool) $st->fetchColumn();
        } catch (PDOException $e) { return false; }
    };

    /** Vrátí true pokud tabulka existuje */
    $tableExists = static function (string $table) use ($pdo): bool {
        try {
            $st = $pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
            );
            $st->execute([$table]);
            return (bool) $st->fetchColumn();
        } catch (PDOException $e) { return false; }
    };

    /** Vrátí definici sloupce (Type, Null, …) nebo null */
    $colDef = static function (string $table, string $col) use ($pdo): ?array {
        try {
            $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $st->execute([$col]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            return $r ?: null;
        } catch (PDOException $e) { return null; }
    };

    /** Spustí SQL; idempotentní chyby (1050/1060/1061) jsou přijatelné — neblokují verzi */
    $migrationFailed = false;
    $exec = static function (string $sql) use ($pdo, &$migrationFailed): void {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // $e->getCode() vrací SQLSTATE (string jako '42S21'), ne MySQL kód — ten je v errorInfo[1]
            $code = (int)($e->errorInfo[1] ?? $e->getCode());
            // 1050 = table already exists, 1060 = duplicate column, 1061 = duplicate key name
            if (in_array($code, [1050, 1060, 1061], true)) {
                // Idempotentní — sloupec / tabulka / index již existuje, pokračujeme
                return;
            }
            error_log('auto_migrace SQL error: ' . $e->getMessage() . ' | SQL: ' . substr($sql, 0, 200));
            $migrationFailed = true;
        }
    };

    // ════════════════════════════════════════════════════════════════════════
    //  M I G R A C E   2.7.0
    // ════════════════════════════════════════════════════════════════════════

    // ── A. segmenty.kategorie — přidat 'mtb' do ENUM ────────────────────────
    $defSegKat = $colDef('segmenty', 'kategorie');
    if ($defSegKat && strpos($defSegKat['Type'] ?? '', 'mtb') === false) {
        $exec("ALTER TABLE segmenty
               MODIFY COLUMN kategorie
               ENUM('krouzek','silnice','mtb') NOT NULL DEFAULT 'krouzek'");
    }

    // ── B. mereni_zaznamy.typ — přidat 'kolo_mtb' do ENUM ───────────────────
    $defMerTyp = $colDef('mereni_zaznamy', 'typ');
    if ($defMerTyp && strpos($defMerTyp['Type'] ?? '', 'kolo_mtb') === false) {
        $exec("ALTER TABLE mereni_zaznamy
               MODIFY COLUMN typ
               ENUM('kolo','beh','posilovna','kolo_krouzek','kolo_silnice','kolo_mtb') NOT NULL");
    }

    // ── C. zavody — nové sloupce ─────────────────────────────────────────────
    if (!$colExists('zavody', 'kategorie')) {
        $exec("ALTER TABLE zavody
               ADD COLUMN kategorie ENUM('silnice','draha','mtb') NOT NULL DEFAULT 'silnice'
               AFTER datum");
    }
    if (!$colExists('zavody', 'url_vysledky')) {
        $exec("ALTER TABLE zavody
               ADD COLUMN url_vysledky VARCHAR(500) NULL
               AFTER poznamka");
    }

    // ── D. zavod_sportovec — nullable + nové sloupce ──────────────────────────
    $defZspSid = $colDef('zavod_sportovec', 'sportovec_id');
    if ($defZspSid && strtolower($defZspSid['Null'] ?? '') !== 'yes') {
        $exec("ALTER TABLE zavod_sportovec MODIFY COLUMN sportovec_id INT NULL");
    }
    if (!$colExists('zavod_sportovec', 'jmeno_ext')) {
        $exec("ALTER TABLE zavod_sportovec ADD COLUMN jmeno_ext VARCHAR(200) NULL");
    }
    if (!$colExists('zavod_sportovec', 'klub')) {
        $exec("ALTER TABLE zavod_sportovec ADD COLUMN klub VARCHAR(200) NULL");
    }
    if (!$colExists('zavod_sportovec', 'kategorie_start')) {
        $exec("ALTER TABLE zavod_sportovec ADD COLUMN kategorie_start VARCHAR(100) NULL");
    }

    // ── E. zavod_mereni — nová junction tabulka ───────────────────────────────
    if (!$tableExists('zavod_mereni')) {
        // Zkus nejdříve s FK; pokud DB nepodporuje (chybný FK), vytvoř bez nich
        try {
            $pdo->exec("CREATE TABLE zavod_mereni (
                zavod_id  INT NOT NULL,
                mereni_id INT NOT NULL,
                poradi    INT NOT NULL DEFAULT 0,
                PRIMARY KEY (zavod_id, mereni_id),
                FOREIGN KEY (zavod_id)  REFERENCES zavody(id)         ON DELETE CASCADE,
                FOREIGN KEY (mereni_id) REFERENCES mereni_zaznamy(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            error_log('auto_migrace: zavod_mereni FK failed (' . $e->getMessage() . '), retrying without FK');
            $exec("CREATE TABLE IF NOT EXISTS zavod_mereni (
                zavod_id  INT NOT NULL,
                mereni_id INT NOT NULL,
                poradi    INT NOT NULL DEFAULT 0,
                PRIMARY KEY (zavod_id, mereni_id),
                INDEX idx_mereni_id (mereni_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    }

    // ── F. opravneni — tabulka + výchozí záznamy ─────────────────────────────
    if (!$tableExists('opravneni')) {
        $exec("CREATE TABLE opravneni (
            klic     VARCHAR(80)  NOT NULL,
            nazev    VARCHAR(160) NOT NULL DEFAULT '',
            popis    TEXT         NOT NULL DEFAULT '',
            min_role ENUM('trener','hlavni','admin') NOT NULL DEFAULT 'hlavni',
            skupina  VARCHAR(80)  NOT NULL DEFAULT '',
            poradi   INT          NOT NULL DEFAULT 0,
            PRIMARY KEY (klic)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // Výchozí záznamy oprávnění (INSERT IGNORE — nezmění ručně upravené hodnoty)
    $defaultOpravneni = [
        ['formular',          'Vložit trénink',               'Přístup k formuláři pro zadávání nových tréninků',   'trener',  'Vkládání',        10],
        ['duplikovat_trenink', 'Duplikovat trénink',           'Kopírování existujícího tréninku',                   'trener',  'Vkládání',        11],
        ['nova_cinnost',      'Vložit výkaz činností',         'Přístup k formuláři výkazu',                         'trener',  'Vkládání',        12],
        ['zatezovy_test',     'Zátěžový test',                 'Vložení zátěžového testu',                           'trener',  'Vkládání',        13],
        ['moje_treninky',     'Moje tréninky',                 'Přehled vlastních tréninků',                         'trener',  'Přehledy',        20],
        ['moje_skupiny',      'Moje skupiny',                  'Přehled skupin trenéra',                             'trener',  'Přehledy',        21],
        ['prehled_trenera',   'Přehled trenéra',               'Souhrnný přehled tréninků trenéra',                  'trener',  'Přehledy',        22],
        ['prehled_sportovcu', 'Přehled sportovců',             'Zobrazení detailů sportovců',                        'trener',  'Přehledy',        23],
        ['kalendar',          'Kalendář',                      'Zobrazení kalendáře tréninků',                       'trener',  'Přehledy',        24],
        ['vypis_vykazu',      'Výkaz činností',                'Přehled výkazů aktivit',                             'trener',  'Přehledy',        25],
        ['exporty',           'Exporty',                       'Přístup k exportním modulům',                        'trener',  'Přehledy',        26],
        ['skupiny_podskupiny','Skupiny a podskupiny',          'Přehled skupin a podskupin',                         'trener',  'Přehledy',        27],
        ['google_sheets',     'Google Sheets',                 'Export do Google Sheets',                            'trener',  'Přehledy',        28],
        ['oznameni',          'Oznámení',                      'Zobrazení oznámení',                                 'trener',  'Přehledy',        29],
        ['cviky',             'Cviky',                         'Správa cviků pro posilovnu',                         'trener',  'Přehledy',        30],
        ['odmeny',            'Odměny',                        'Přehled odměn sportovců',                            'trener',  'Přehledy',        31],
        ['segmenty',          'Správa segmentů',               'Správa segmentů na kole',                            'hlavni',  'Závodní sekce',   40],
        ['formular_zavod',    'Přidat závod',                  'Formulář pro zadání nového závodu',                  'trener',  'Závodní sekce',   45],
        ['prehled_zavodu',    'Přehled závodů',                'Veřejný seznam závodů',                              'trener',  'Závodní sekce',   46],
        ['sprava_zavodu',     'Správa závodů',                 'Admin CRUD závodů',                                  'hlavni',  'Závodní sekce',   47],
        ['zavod_detail',      'Detail závodu',                 'Zobrazení výsledků závodu',                          'trener',  'Závodní sekce',   48],
        ['sprava_sportovcu',  'Správa sportovců',              'CRUD sportovců',                                     'hlavni',  'Správa dat',      50],
        ['sprava_treninku',   'Správa tréninků',               'Přehled a editace všech tréninků',                   'hlavni',  'Správa dat',      51],
        ['sprava_skupin',     'Správa skupin',                 'CRUD skupin',                                        'hlavni',  'Správa dat',      52],
        ['sprava_podskupin',  'Správa podskupin',              'CRUD podskupin',                                     'hlavni',  'Správa dat',      53],
        ['sprava_zavodu',     'Správa závodů',                 'Admin CRUD závodů',                                  'hlavni',  'Správa dat',      54],
        ['verejny_prehled',   'Veřejný přehled',               'Veřejně dostupná stránka s přehledem',               'hlavni',  'Správa dat',      55],
        ['prehled_popisu',    'Přehled popisů tréninků',        'Zobrazení popisů tréninků dle skupiny/podskupiny',   'trener',  'Správa dat',      56],
        ['vsechny_vykazy',    'Všechny výkazy',                'Přehled výkazů všech trenérů',                       'hlavni',  'Správa dat',      57],
        ['odeslat_emaily',    'Odeslat emaily',                'Hromadné odesílání emailů',                          'hlavni',  'Správa dat',      58],
        ['prehled_kreditu',   'Přehled kreditů',               'Přehled kreditů sportovců',                          'hlavni',  'Správa dat',      59],
        ['kreditni_obdobi',   'Kreditní období',               'Správa kreditních období',                           'hlavni',  'Správa dat',      60],
        ['sync_evidence',     'Synchronizace evidence',         'Import sportovců z XLSX',                            'hlavni',  'Správa dat',      61],
        ['nastaveni_zadavani','Nastavení zadávání',             'Nastavení časového okna pro zadávání tréninků',      'admin',   'Nastavení',       70],
        ['auditlog',          'Audit log',                     'Prohlížeč audit logu',                               'admin',   'Administrace',    80],
        ['vozidla',           'Vozidla',                       'Správa vozidel',                                     'admin',   'Firemní evidence', 90],
        ['jizdy',             'Jízdy',                         'Správa jízd',                                        'admin',   'Firemní evidence', 91],
        ['servis',            'Servis',                        'Záznamy servisu',                                    'admin',   'Firemní evidence', 92],
        ['uctenky',           'Účtenky',                       'Správa účtenek',                                     'admin',   'Firemní evidence', 93],
        ['udalosti',          'Události',                      'Správa událostí',                                    'admin',   'Firemní evidence', 94],
        // 2.8.0 — Rezervační systém
        ['kalendar_sportovist',  'Kalendář sportovišť',   'Přehled obsazenosti sportovišť',                     'trener',  'Rezervace',       100],
        ['rezervace_sportovist', 'Rezervovat sportoviště','Vytvoření rezervace sportoviště',                     'trener',  'Rezervace',       101],
        ['individualni_lekce',   'Individuální lekce',    'Vypisování a správa individuálních lekcí',           'trener',  'Rezervace',       102],
        ['sprava_sportovist',    'Správa sportovišť',     'CRUD sportovišť (admin)',                             'admin',   'Administrace',     95],
        // 2.10.0 — Plánovač tréninků
        ['planovac',             'Plánovač tréninků',     'Plánování tréninků dopředu a sledování evidence',    'trener',  'Přehledy',         50],
    ];

    try {
        $stmtIns = $pdo->prepare(
            "INSERT IGNORE INTO opravneni (klic, nazev, popis, min_role, skupina, poradi)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        foreach ($defaultOpravneni as $row) {
            $stmtIns->execute($row);
        }
    } catch (PDOException $e) {
        error_log('auto_migrace opravneni insert: ' . $e->getMessage());
    }

    // ════════════════════════════════════════════════════════════════════════
    //  M I G R A C E   2.8.0  —  Rezervační systém sportovišť
    // ════════════════════════════════════════════════════════════════════════

    // ── H. sportovist — nová tabulka ─────────────────────────────────────────
    if (!$tableExists('sportovist')) {
        $exec("CREATE TABLE sportovist (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            kod          VARCHAR(40)  NOT NULL,
            nazev        VARCHAR(120) NOT NULL,
            je_verejne   TINYINT(1)   NOT NULL DEFAULT 0,
            max_kapacita INT          NOT NULL DEFAULT 5,
            poradi       INT          NOT NULL DEFAULT 0,
            aktivni      TINYINT(1)   NOT NULL DEFAULT 1,
            UNIQUE KEY uq_kod (kod)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Seed dat
        try {
            $pdo->exec("INSERT IGNORE INTO sportovist (kod, nazev, je_verejne, poradi) VALUES
                ('velodrom',           'Velodrom',             1, 1),
                ('posilovna_horni',    'Posilovna (horní)',    1, 2),
                ('telocvicna_horni',   'Horní tělocvična',    0, 3),
                ('telocvicna_spodni',  'Spodní tělocvična',   0, 4),
                ('sauna',              'Sauna',                0, 5),
                ('nahravaci_mistnost', 'Nahrávací místnost',  0, 6)
            ");
        } catch (PDOException $e) {
            error_log('auto_migrace sportovist seed: ' . $e->getMessage());
        }
    }

    // ── I. rezervace_sportovist — interní rezervace trenérů ──────────────────
    if (!$tableExists('rezervace_sportovist')) {
        $exec("CREATE TABLE rezervace_sportovist (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            sportoviste_id  INT      NOT NULL,
            trener_id       INT      NOT NULL,
            datum           DATE     NOT NULL,
            cas_od          TIME     NOT NULL,
            cas_do          TIME     NOT NULL,
            kapacita_dilu   TINYINT  NOT NULL DEFAULT 1,
            trenink_id      INT      NULL,
            lekce_id        INT      NULL,
            poznamka        TEXT     NULL,
            vytvoreno       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_datum_sportoviste (datum, sportoviste_id),
            INDEX idx_trener (trener_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // ── J. verejni_uzivatele — zákazníci s účtem ─────────────────────────────
    if (!$tableExists('verejni_uzivatele')) {
        $exec("CREATE TABLE verejni_uzivatele (
            id                  INT AUTO_INCREMENT PRIMARY KEY,
            jmeno               VARCHAR(80)  NOT NULL,
            prijmeni            VARCHAR(80)  NOT NULL,
            email               VARCHAR(160) NOT NULL,
            heslo_hash          VARCHAR(255) NOT NULL,
            telefon             VARCHAR(20)  NULL,
            verifikacni_token   VARCHAR(64)  NULL,
            verifikacni_token_expires_at DATETIME NULL,
            email_overeno       TINYINT(1)   NOT NULL DEFAULT 0,
            aktivni             TINYINT(1)   NOT NULL DEFAULT 1,
            registrovan         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_email (email),
            INDEX idx_token (verifikacni_token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // ── K. individualni_lekce — trenérem vypsané veřejné termíny ─────────────
    if (!$tableExists('individualni_lekce')) {
        $exec("CREATE TABLE individualni_lekce (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            trener_id       INT            NOT NULL,
            sportoviste_id  INT            NOT NULL,
            datum           DATE           NOT NULL,
            cas_od          TIME           NOT NULL,
            cas_do          TIME           NOT NULL,
            typ             ENUM('zelena','zluta') NOT NULL DEFAULT 'zelena',
            nazev           VARCHAR(200)   NOT NULL DEFAULT '',
            popis           TEXT           NULL,
            cena_kc         DECIMAL(8,2)   NOT NULL DEFAULT 0.00,
            max_osob        INT            NOT NULL DEFAULT 1,
            stav            ENUM('aktivni','zrusena') NOT NULL DEFAULT 'aktivni',
            vytvoreno       TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_datum (datum),
            INDEX idx_trener (trener_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // ── L. verejne_rezervace — booking zákazníků ─────────────────────────────
    if (!$tableExists('verejne_rezervace')) {
        $exec("CREATE TABLE verejne_rezervace (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            lekce_id         INT  NOT NULL,
            uzivatel_id      INT  NOT NULL,
            stav             ENUM('ceka','potvrzena','zamitnuta','zrusena') NOT NULL DEFAULT 'ceka',
            zaplaceno        TINYINT(1) NOT NULL DEFAULT 0,
            poznamka_klienta TEXT NULL,
            poznamka_trenera TEXT NULL,
            potvrzovaci_token VARCHAR(64) NULL,
            potvrzovaci_token_expires_at DATETIME NULL,
            cas_rezervace    TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
            cas_potvrzeni    TIMESTAMP   NULL,
            INDEX idx_lekce (lekce_id),
            INDEX idx_uzivatel (uzivatel_id),
            INDEX idx_token (potvrzovaci_token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // ════════════════════════════════════════════════════════════════════════
    //  M I G R A C E   2.9.0  —  Slotový systém rezervací
    // ════════════════════════════════════════════════════════════════════════

    // ── M. individualni_lekce — délka slotu ──────────────────────────────────
    if (!$colExists('individualni_lekce', 'slot_delka_min')) {
        $exec("ALTER TABLE individualni_lekce
               ADD COLUMN slot_delka_min SMALLINT NOT NULL DEFAULT 60
               AFTER cas_do");
    }

    // ── N. verejne_rezervace — konkrétní slot zákazníka ──────────────────────
    if (!$colExists('verejne_rezervace', 'slot_cas_od')) {
        $exec("ALTER TABLE verejne_rezervace
               ADD COLUMN slot_cas_od TIME NULL
               AFTER cas_potvrzeni");
    }
    if (!$colExists('verejne_rezervace', 'slot_cas_do')) {
        $exec("ALTER TABLE verejne_rezervace
               ADD COLUMN slot_cas_do TIME NULL
               AFTER slot_cas_od");
    }

    // ════════════════════════════════════════════════════════════════════════
    //  M I G R A C E   2.10.0  —  Plánovač tréninků
    // ════════════════════════════════════════════════════════════════════════

    // ── O. Tabulka planovane_treninky ────────────────────────────────────────
    if (!$tableExists('planovane_treninky')) {
        // Zkus nejdříve s FK; pokud DB nepodporuje (chybný FK), vytvoř bez nich
        try {
            $pdo->exec("CREATE TABLE planovane_treninky (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                trener_id       INT NOT NULL,
                skupina_id      INT NULL,
                podskupina_id   INT NULL,
                datum           DATE NOT NULL,
                cas_od          TIME NULL,
                cas_do          TIME NULL,
                sportoviste_id  INT NULL,
                rezervace_id    INT NULL,
                trenink_id      INT NULL,
                nazev           VARCHAR(200) NOT NULL DEFAULT '',
                kategorie       ENUM('silnice','mtb','draha','cyklokros','posilovna',
                                     'atletika','cviceni','plavani') NULL,
                popis           TEXT NULL,
                stav            ENUM('planovany','evidovany','zruseny') NOT NULL DEFAULT 'planovany',
                vytvoreno       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (trener_id)      REFERENCES treneri(id),
                FOREIGN KEY (skupina_id)     REFERENCES skupiny(id),
                FOREIGN KEY (podskupina_id)  REFERENCES podskupiny(id),
                FOREIGN KEY (sportoviste_id) REFERENCES sportovist(id),
                FOREIGN KEY (trenink_id)     REFERENCES treninky(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            error_log('auto_migrace: planovane_treninky FK failed (' . $e->getMessage() . '), retrying without FK');
            $exec("CREATE TABLE IF NOT EXISTS planovane_treninky (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                trener_id       INT NOT NULL,
                skupina_id      INT NULL,
                podskupina_id   INT NULL,
                datum           DATE NOT NULL,
                cas_od          TIME NULL,
                cas_do          TIME NULL,
                sportoviste_id  INT NULL,
                rezervace_id    INT NULL,
                trenink_id      INT NULL,
                nazev           VARCHAR(200) NOT NULL DEFAULT '',
                kategorie       ENUM('silnice','mtb','draha','cyklokros','posilovna',
                                     'atletika','cviceni','plavani') NULL,
                popis           TEXT NULL,
                stav            ENUM('planovany','evidovany','zruseny') NOT NULL DEFAULT 'planovany',
                vytvoreno       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_datum (datum),
                INDEX idx_skupina (skupina_id),
                INDEX idx_trener (trener_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    }

    // ── P. Oprávnění plánovače (pokryto i přes $defaultOpravneni) ────────────────
    try {
        $pdo->exec("INSERT IGNORE INTO opravneni (klic, nazev, popis, min_role, skupina, poradi)
                    VALUES ('planovac', 'Plánovač tréninků',
                            'Plánování tréninků dopředu a sledování evidence',
                            'trener', 'Přehledy', 50)");
    } catch (PDOException $e) {
        error_log('auto_migrace planovac opravneni: ' . $e->getMessage());
    }

    // ════════════════════════════════════════════════════════════════════════
    //  M I G R A C E   2.11.0  —  Více podskupin v plánovači
    // ════════════════════════════════════════════════════════════════════════

    // ── Q. Junction tabulka pro více podskupin u plánů ───────────────────────────
    if (!$tableExists('planovane_treninky_podskupiny')) {
        $exec("CREATE TABLE IF NOT EXISTS planovane_treninky_podskupiny (
            plan_id        INT NOT NULL,
            podskupina_id  INT NOT NULL,
            PRIMARY KEY (plan_id, podskupina_id),
            INDEX idx_podskupina (podskupina_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // ════════════════════════════════════════════════════════════════════════
    //  M I G R A C E   2.12.0  —  Výjimka 3 dní u individuálních lekcí
    // ════════════════════════════════════════════════════════════════════════

    // ── R. individualni_lekce — příznak výjimky třídenního limitu ────────────
    if (!$colExists('individualni_lekce', 'vyjimka_3_dny')) {
        $exec("ALTER TABLE individualni_lekce
               ADD COLUMN vyjimka_3_dny TINYINT(1) NOT NULL DEFAULT 0
               AFTER max_osob");
    }

    // ════════════════════════════════════════════════════════════════════════
    //  M I G R A C E   2.13.0  —  Upomínky na nezaevidované plány
    // ════════════════════════════════════════════════════════════════════════

    // ── S. planovane_treninky — sledování odeslaných upomínek ────────────────
    if (!$colExists('planovane_treninky', 'upominka_cas')) {
        $exec("ALTER TABLE planovane_treninky
               ADD COLUMN upominka_cas TIMESTAMP NULL DEFAULT NULL
               COMMENT 'Kdy byla odeslána upomínka trenérovi'");
    }

    // ════════════════════════════════════════════════════════════════════════
    //  M I G R A C E   2.14.0  —  Čekací listina pro individuální lekce
    // ════════════════════════════════════════════════════════════════════════

    // ── T. verejne_rezervace — přidat stav cekaci_listina ────────────────────
    if (!str_contains((string)($colDef('verejne_rezervace', 'stav')['Type'] ?? ''), 'cekaci_listina')) {
        $exec("ALTER TABLE verejne_rezervace
               MODIFY stav ENUM('ceka','potvrzena','zamitnuta','zrusena','cekaci_listina')
               NOT NULL DEFAULT 'ceka'");
    }

    // ════════════════════════════════════════════════════════════════════════
    //  M I G R A C E   2.15.0  —  Zajištění existence soupiska_mapping
    // ════════════════════════════════════════════════════════════════════════

    // ── U. soupiska_mapping — persistentní mapování soupisek z Excelu ────────
    if (!$tableExists('soupiska_mapping')) {
        $exec("CREATE TABLE IF NOT EXISTS soupiska_mapping (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            soupiska_text VARCHAR(255) NOT NULL,
            skupina_id    INT NULL,
            podskupina_id INT NULL,
            UNIQUE KEY uq_soupiska_text (soupiska_text)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // ════════════════════════════════════════════════════════════════════════
    //  M I G R A C E   2.16.0  —  Serie opakujících se tréninků
    // ════════════════════════════════════════════════════════════════════════

    // ── V. planovane_treninky — série opakování ───────────────────────────
    if (!$colExists('planovane_treninky', 'serie_id')) {
        $exec("ALTER TABLE planovane_treninky
               ADD COLUMN serie_id INT NULL DEFAULT NULL
               COMMENT 'ID série opakujících se tréninků (sdílené mezi instancemi)'
               AFTER upominka_cas");
    }

    // ════════════════════════════════════════════════════════════════════════
    //  M I G R A C E   2.17.0  —  Web push notifikace
    // ════════════════════════════════════════════════════════════════════════

    // ── W. push_subscriptions — Web Push odběry ───────────────────────────
    if (!$tableExists('push_subscriptions')) {
        $exec("CREATE TABLE IF NOT EXISTS push_subscriptions (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            trener_id   INT NULL,
            endpoint    TEXT NOT NULL,
            p256dh      VARCHAR(255) NOT NULL,
            auth        VARCHAR(255) NOT NULL,
            user_agent  VARCHAR(255) NULL,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_trener (trener_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // ════════════════════════════════════════════════════════════════════════
    //  M I G R A C E   2.18.0  —  Velocota SSO integrace
    // ════════════════════════════════════════════════════════════════════════

    // ── X. treneri — reference na Velocota uživatele ─────────────────────────
    if (!$colExists('treneri', 'velo_user_id')) {
        $exec("ALTER TABLE treneri
               ADD COLUMN velo_user_id INT NULL DEFAULT NULL
               COMMENT 'ID uživatele ve Velocota DB (pro SSO bridge)'
               AFTER id,
               ADD INDEX idx_velo_user (velo_user_id)");
    }

    // ════════════════════════════════════════════════════════════════════════
    //  M I G R A C E   2.18.1  —  Opravy a doplňky SSO integrace
    // ════════════════════════════════════════════════════════════════════════

    // ── Y. treneri — příznak aktivního účtu (vyžaduje sso_bridge.php) ────────
    if (!$colExists('treneri', 'aktivni')) {
        $exec("ALTER TABLE treneri
               ADD COLUMN aktivni TINYINT(1) NOT NULL DEFAULT 1
               COMMENT 'Aktivní účet (0 = deaktivován)'
               AFTER role");
    }

    // ════════════════════════════════════════════════════════════════════════
    //  M I G R A C E   2.18.2  —  sportovec_skupina (chyběla M:N tabulka)
    // ════════════════════════════════════════════════════════════════════════

    // ── Z. sportovec_skupina — M:N sportovec ↔ skupina ───────────────────────
    // Tabulka chyběla v DB schématu; všechny soubory na ni odkazují.
    // Po vytvoření se naplní z existujících vazeb sportovec_podskupina → podskupiny.
    if (!$tableExists('sportovec_skupina')) {
        $exec("CREATE TABLE sportovec_skupina (
            sportovec_id INT NOT NULL,
            skupina_id   INT NOT NULL,
            PRIMARY KEY (sportovec_id, skupina_id),
            INDEX idx_skupina (skupina_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        // Naplnění z existujících vazeb — INSERT IGNORE je bezpečný i při opakovaném běhu
        try {
            $pdo->exec("INSERT IGNORE INTO sportovec_skupina (sportovec_id, skupina_id)
                        SELECT DISTINCT sp.sportovec_id, ps.skupina_id
                        FROM sportovec_podskupina sp
                        JOIN podskupiny ps ON sp.podskupina_id = ps.id");
        } catch (PDOException $e) {
            error_log('auto_migrace: sportovec_skupina populate failed: ' . $e->getMessage());
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    //  M I G R A C E   2.18.3  —  treninky — chybějící sloupce
    // ════════════════════════════════════════════════════════════════════════

    // ── AA. treninky — kategorie ──────────────────────────────────────────────
    if (!$colExists('treninky', 'kategorie')) {
        $exec("ALTER TABLE treninky
               ADD COLUMN kategorie
                   ENUM('silnice','mtb','draha','cyklokros','posilovna','atletika','cviceni','plavani') NULL
               AFTER delka");
    }

    // ── BB. treninky — mereni_json (novější formát měření, dříve jen 'mereni') ─
    if (!$colExists('treninky', 'mereni_json')) {
        $exec("ALTER TABLE treninky
               ADD COLUMN mereni_json TEXT NULL
               AFTER mereni");
    }

    // MIGRATION 2.19.2 - sportovec_obdobi compatibility with current credit pages
    if (!$tableExists('sportovec_obdobi')) {
        $exec("CREATE TABLE sportovec_obdobi (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            sportovec_id    INT NOT NULL,
            datum_od        DATE NOT NULL,
            datum_do        DATE NULL DEFAULT NULL,
            pocet_treninku  INT NULL DEFAULT NULL,
            sazba_kc        DECIMAL(10,2) NOT NULL,
            castka_celkem   DECIMAL(10,2) NULL DEFAULT NULL,
            vyplaceno       TINYINT(1) NOT NULL DEFAULT 0,
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX fk_sportovec_obdobi_sportovec (sportovec_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } else {
        if ($colExists('sportovec_obdobi', 'obdobi_od') && !$colExists('sportovec_obdobi', 'datum_od')) {
            $exec("ALTER TABLE sportovec_obdobi
                   CHANGE COLUMN obdobi_od datum_od DATE NOT NULL");
        }
        if ($colExists('sportovec_obdobi', 'obdobi_do') && !$colExists('sportovec_obdobi', 'datum_do')) {
            $exec("ALTER TABLE sportovec_obdobi
                   CHANGE COLUMN obdobi_do datum_do DATE NULL DEFAULT NULL");
        }
        if ($colExists('sportovec_obdobi', 'sazba') && !$colExists('sportovec_obdobi', 'sazba_kc')) {
            $exec("ALTER TABLE sportovec_obdobi
                   CHANGE COLUMN sazba sazba_kc DECIMAL(10,2) NOT NULL");
        }
        if (!$colExists('sportovec_obdobi', 'datum_od')) {
            $exec("ALTER TABLE sportovec_obdobi
                   ADD COLUMN datum_od DATE NOT NULL AFTER sportovec_id");
        }
        if (!$colExists('sportovec_obdobi', 'datum_do')) {
            $exec("ALTER TABLE sportovec_obdobi
                   ADD COLUMN datum_do DATE NULL DEFAULT NULL AFTER datum_od");
        }
        if (!$colExists('sportovec_obdobi', 'sazba_kc')) {
            $exec("ALTER TABLE sportovec_obdobi
                   ADD COLUMN sazba_kc DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER datum_do");
        }
        if (!$colExists('sportovec_obdobi', 'pocet_treninku')) {
            $exec("ALTER TABLE sportovec_obdobi
                   ADD COLUMN pocet_treninku INT NULL DEFAULT NULL AFTER datum_do");
        }
        if (!$colExists('sportovec_obdobi', 'castka_celkem')) {
            $exec("ALTER TABLE sportovec_obdobi
                   ADD COLUMN castka_celkem DECIMAL(10,2) NULL DEFAULT NULL AFTER sazba_kc");
        }
        if (!$colExists('sportovec_obdobi', 'vyplaceno')) {
            $exec("ALTER TABLE sportovec_obdobi
                   ADD COLUMN vyplaceno TINYINT(1) NOT NULL DEFAULT 0 AFTER castka_celkem");
        }
        if (!$colExists('sportovec_obdobi', 'created_at')) {
            $exec("ALTER TABLE sportovec_obdobi
                   ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER vyplaceno");
        }

        if ($colExists('sportovec_obdobi', 'obdobi_od') && $colExists('sportovec_obdobi', 'datum_od')) {
            $exec("UPDATE sportovec_obdobi
                   SET datum_od = obdobi_od
                   WHERE datum_od IS NULL");
        }
        if ($colExists('sportovec_obdobi', 'obdobi_do') && $colExists('sportovec_obdobi', 'datum_do')) {
            $exec("UPDATE sportovec_obdobi
                   SET datum_do = obdobi_do
                   WHERE datum_do IS NULL");
        }
        if ($colExists('sportovec_obdobi', 'sazba') && $colExists('sportovec_obdobi', 'sazba_kc')) {
            $exec("UPDATE sportovec_obdobi
                   SET sazba_kc = sazba
                   WHERE sazba_kc = 0 OR sazba_kc IS NULL");
        }

        $defDatumDo = $colDef('sportovec_obdobi', 'datum_do');
        if ($defDatumDo && strtolower((string)($defDatumDo['Null'] ?? '')) !== 'yes') {
            $exec("ALTER TABLE sportovec_obdobi
                   MODIFY COLUMN datum_do DATE NULL DEFAULT NULL");
        }
        $defPocet = $colDef('sportovec_obdobi', 'pocet_treninku');
        if ($defPocet && strtolower((string)($defPocet['Null'] ?? '')) !== 'yes') {
            $exec("ALTER TABLE sportovec_obdobi
                   MODIFY COLUMN pocet_treninku INT NULL DEFAULT NULL");
        }
        $defCastka = $colDef('sportovec_obdobi', 'castka_celkem');
        if ($defCastka && strtolower((string)($defCastka['Null'] ?? '')) !== 'yes') {
            $exec("ALTER TABLE sportovec_obdobi
                   MODIFY COLUMN castka_celkem DECIMAL(10,2) NULL DEFAULT NULL");
        }
        $exec("ALTER TABLE sportovec_obdobi
               MODIFY COLUMN vyplaceno TINYINT(1) NOT NULL DEFAULT 0");
    }

    // ── G. Uložení verze schématu — pouze pokud vše proběhlo bez chyby ─────────
    // MIGRATION 2.20.0 - KIS provozni centrum, stavy clenu a historie
    if (!$colExists('sportovci', 'stav_clenstvi')) {
        $exec("ALTER TABLE sportovci
               ADD COLUMN stav_clenstvi ENUM('aktivni','cekajici','neaktivni','archiv') NOT NULL DEFAULT 'cekajici'
               AFTER email");
    }
    if (!$colExists('sportovci', 'stav_duvod')) {
        $exec("ALTER TABLE sportovci
               ADD COLUMN stav_duvod VARCHAR(255) NULL
               AFTER stav_clenstvi");
    }
    if (!$colExists('sportovci', 'stav_manualni')) {
        $exec("ALTER TABLE sportovci
               ADD COLUMN stav_manualni TINYINT(1) NOT NULL DEFAULT 0
               AFTER stav_duvod");
    }
    if (!$colExists('sportovci', 'stav_aktualizovan')) {
        $exec("ALTER TABLE sportovci
               ADD COLUMN stav_aktualizovan TIMESTAMP NULL DEFAULT NULL
               AFTER stav_manualni");
    }
    if (!$colExists('sportovci', 'kis_identity_key')) {
        $exec("ALTER TABLE sportovci
               ADD COLUMN kis_identity_key VARCHAR(180) NULL
               AFTER stav_aktualizovan");
    }
    if (!$colExists('sportovci', 'kis_match_confidence')) {
        $exec("ALTER TABLE sportovci
               ADD COLUMN kis_match_confidence TINYINT UNSIGNED NULL
               AFTER kis_identity_key");
    }
    if (!$colExists('sportovci', 'kis_last_seen_at')) {
        $exec("ALTER TABLE sportovci
               ADD COLUMN kis_last_seen_at TIMESTAMP NULL DEFAULT NULL
               AFTER kis_match_confidence");
    }
    // Normalizovaná jména — používá je backfill kis_identity_key (ř. níže) i sloupec
    // kis_aktivni (AFTER last_name_norm). Na čisté produkci bez těchto sloupců by jinak
    // migrace padala chybou 1054 a nikdy se nedokončila.
    if (!$colExists('sportovci', 'first_name_norm')) {
        $exec("ALTER TABLE sportovci ADD COLUMN first_name_norm VARCHAR(100) NULL");
    }
    if (!$colExists('sportovci', 'last_name_norm')) {
        $exec("ALTER TABLE sportovci ADD COLUMN last_name_norm VARCHAR(100) NULL");
    }
    // Kontaktní sloupce — vyžaduje je INSERT v executeSync; na čisté produkci by jinak
    // zakládání nových členů z KIS padlo na 1054.
    $kontakt = [
        'telefon'      => 'VARCHAR(50) NULL',
        'adresa_ulice' => 'VARCHAR(200) NULL',
        'adresa_cp'    => 'VARCHAR(20) NULL',
        'adresa_co'    => 'VARCHAR(20) NULL',
        'adresa_obec'  => 'VARCHAR(100) NULL',
        'adresa_psc'   => 'VARCHAR(10) NULL',
    ];
    foreach ($kontakt as $col => $def) {
        if (!$colExists('sportovci', $col)) {
            $exec("ALTER TABLE sportovci ADD COLUMN `$col` $def");
        }
    }
    // Tabulky poznámek — zapisují do nich sportovec_karta.php a sportovci_hromadne.php
    if (!$tableExists('sportovec_interni_poznamka')) {
        $exec("CREATE TABLE sportovec_interni_poznamka (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            sportovec_id INT NOT NULL,
            trener_id    INT NOT NULL,
            datum        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            text         TEXT NOT NULL,
            INDEX idx_sportovec (sportovec_id),
            INDEX idx_trener (trener_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    if (!$tableExists('sportovec_poznamka')) {
        $exec("CREATE TABLE sportovec_poznamka (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            sportovec_id INT NOT NULL,
            trenink_id   INT NOT NULL,
            poznamka     TEXT NOT NULL,
            INDEX idx_sportovec (sportovec_id),
            INDEX idx_trenink (trenink_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    $exec("ALTER TABLE sportovci ADD INDEX idx_stav_clenstvi (stav_clenstvi)");
    $exec("ALTER TABLE sportovci ADD INDEX idx_kis_identity_key (kis_identity_key)");
    if ($colExists('sportovci', 'kis_identity_key')) {
        $exec("UPDATE sportovci
               SET kis_identity_key = LOWER(CONCAT(COALESCE(first_name_norm, jmeno), '|', COALESCE(last_name_norm, prijmeni), '|', COALESCE(NULLIF(narozeni, '0000-00-00'), '')))
               WHERE kis_identity_key IS NULL OR kis_identity_key = ''");
    }

    if (!$tableExists('kis_import_runs')) {
        $exec("CREATE TABLE kis_import_runs (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by      INT NULL,
            status          ENUM('preview','applied','failed','cancelled') NOT NULL DEFAULT 'preview',
            source_users    VARCHAR(255) NULL,
            source_payments VARCHAR(255) NULL,
            source_rosters  VARCHAR(255) NULL,
            stats_json      LONGTEXT NULL,
            warnings_json   LONGTEXT NULL,
            applied_at      DATETIME NULL,
            note            TEXT NULL,
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists('kis_import_rows')) {
        $exec("CREATE TABLE kis_import_rows (
            id                    INT AUTO_INCREMENT PRIMARY KEY,
            run_id                INT NOT NULL,
            person_key            VARCHAR(180) NOT NULL,
            jmeno                 VARCHAR(100) NOT NULL,
            prijmeni              VARCHAR(160) NOT NULL,
            narozeni              DATE NULL,
            email                 VARCHAR(255) NULL,
            uciid                 VARCHAR(80) NULL,
            oddil                 VARCHAR(160) NULL,
            kis_aktivni           TINYINT(1) NOT NULL DEFAULT 0,
            kis_platebne_aktivni  TINYINT(1) NOT NULL DEFAULT 0,
            kis_neuhrazeno        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            kis_posledni_uhrada   DATE NULL,
            kis_soupisky          TEXT NULL,
            raw_json              LONGTEXT NULL,
            INDEX idx_run_id (run_id),
            INDEX idx_person_key (person_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists('kis_import_matches')) {
        $exec("CREATE TABLE kis_import_matches (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            run_id          INT NOT NULL,
            row_id          INT NOT NULL,
            sportovec_id    INT NULL,
            match_status    ENUM('new','matched','ambiguous','conflict','ignored') NOT NULL,
            confidence      TINYINT UNSIGNED NOT NULL DEFAULT 0,
            reason          VARCHAR(255) NULL,
            candidate_json  LONGTEXT NULL,
            resolved_by     INT NULL,
            resolved_at     DATETIME NULL,
            resolved_action ENUM('create','update','link','ignore','manual') NULL,
            INDEX idx_run_id (run_id),
            INDEX idx_row_id (row_id),
            INDEX idx_sportovec_id (sportovec_id),
            INDEX idx_match_status (match_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists('sportovec_history')) {
        $exec("CREATE TABLE sportovec_history (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            sportovec_id INT NOT NULL,
            created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by   INT NULL,
            source       ENUM('manual','kis_import','bulk_action','system') NOT NULL DEFAULT 'manual',
            event_type   VARCHAR(80) NOT NULL,
            title        VARCHAR(180) NOT NULL,
            detail       TEXT NULL,
            old_json     LONGTEXT NULL,
            new_json     LONGTEXT NULL,
            ref_table    VARCHAR(80) NULL,
            ref_id       INT NULL,
            INDEX idx_sportovec_id (sportovec_id),
            INDEX idx_created_at (created_at),
            INDEX idx_event_type (event_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // KIS synchronizace clenu a plateb
    if (!$colExists('sportovci', 'kis_aktivni')) {
        $exec("ALTER TABLE sportovci
               ADD COLUMN kis_aktivni TINYINT(1) NOT NULL DEFAULT 0
               COMMENT 'Aktivni v KIS soupisce'
               AFTER last_name_norm");
    }
    if (!$colExists('sportovci', 'kis_platebne_aktivni')) {
        $exec("ALTER TABLE sportovci
               ADD COLUMN kis_platebne_aktivni TINYINT(1) NOT NULL DEFAULT 0
               COMMENT 'Ma alespon jednu uhrazenou KIS platbu'
               AFTER kis_aktivni");
    }
    if (!$colExists('sportovci', 'kis_neuhrazeno')) {
        $exec("ALTER TABLE sportovci
               ADD COLUMN kis_neuhrazeno DECIMAL(10,2) NOT NULL DEFAULT 0.00
               COMMENT 'Souhrn otevrenych KIS plateb'
               AFTER kis_platebne_aktivni");
    }
    if (!$colExists('sportovci', 'kis_posledni_uhrada')) {
        $exec("ALTER TABLE sportovci
               ADD COLUMN kis_posledni_uhrada DATE NULL
               COMMENT 'Posledni datum uhrady z KIS exportu'
               AFTER kis_neuhrazeno");
    }
    if (!$colExists('sportovci', 'kis_posledni_sync')) {
        $exec("ALTER TABLE sportovci
               ADD COLUMN kis_posledni_sync TIMESTAMP NULL DEFAULT NULL
               COMMENT 'Kdy byl sportovec naposledy aktualizovan KIS synchronizaci'
               AFTER kis_posledni_uhrada");
    }
    if (!$colExists('sportovci', 'kis_soupisky')) {
        $exec("ALTER TABLE sportovci
               ADD COLUMN kis_soupisky TEXT NULL
               COMMENT 'Soupisky z posledniho KIS importu'
               AFTER kis_posledni_sync");
    }
    if (!$colExists('sportovci', 'category')) {
        $exec("ALTER TABLE sportovci
               ADD COLUMN category VARCHAR(100) NULL
               COMMENT 'Kategorie sportovce'
               AFTER narozeni");
    }
    if (!$colExists('sportovci', 'uciid')) {
        $exec("ALTER TABLE sportovci
               ADD COLUMN uciid VARCHAR(80) NULL
               COMMENT 'UCI ID'
               AFTER uci");
    }
    if (!$colExists('sportovci', 'oddil')) {
        $exec("ALTER TABLE sportovci
               ADD COLUMN oddil VARCHAR(160) NULL
               COMMENT 'Oddil / klub'
               AFTER email");
    }

    if (!$tableExists('oznameni')) {
        $exec("CREATE TABLE oznameni (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            nazev            VARCHAR(255) NULL,
            obsah_html       MEDIUMTEXT NOT NULL,
            datum            DATE NOT NULL,
            vlozil_trener_id INT NULL,
            created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_datum (datum),
            INDEX idx_vlozil_trener_id (vlozil_trener_id),
            CONSTRAINT fk_oznameni_trener
                FOREIGN KEY (vlozil_trener_id) REFERENCES treneri(id)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$tableExists('oznameni_targets')) {
        $exec("CREATE TABLE oznameni_targets (
            oznameni_id INT NOT NULL,
            target_type ENUM('skupina','podskupina','sportovec') NOT NULL,
            target_id   INT NOT NULL,
            PRIMARY KEY (oznameni_id, target_type, target_id),
            INDEX idx_target (target_type, target_id),
            CONSTRAINT fk_oznameni_targets_oznameni
                FOREIGN KEY (oznameni_id) REFERENCES oznameni(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$migrationFailed) {
        try {
            $pdo->prepare(
                "INSERT INTO nastaveni (klic, hodnota)
                 VALUES ('schema_version', ?)
                 ON DUPLICATE KEY UPDATE hodnota = VALUES(hodnota), upraveno = CURRENT_TIMESTAMP"
            )->execute([LEGACY_SCHEMA_VERSION]);
        } catch (PDOException $e) {
            error_log('auto_migrace version save: ' . $e->getMessage());
        }
    } else {
        error_log('auto_migrace: version NOT saved — one or more steps failed, will retry on next request');
    }

    // Uvolni advisory lock
    try { $pdo->exec("DO RELEASE_LOCK('evidence_auto_migrace')"); } catch (PDOException $e) {}

})($pdo);
