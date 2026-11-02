# Evidence členů bez sezon — implementace a převod

Navazuje na [pracovní plán](MEMBER_EVIDENCE_WORK_PLAN_2026-11-02.md). Tento dokument popisuje aplikační změnu a nácvik nad syntetickými daty. Nepotvrzuje nasazení, převod produkčních dat ani dokončení E7/E8.

## Co aplikace nově používá

- Registrář pracuje v `kis_roster_hierarchy_admin.php`: vytváří trvalou skupinu nebo soupisku, nastaví zaměření, zobrazí osoby k datu, hromadně zařadí nejvýše 100 osob, ukončí ruční členství nebo provede přestup. Zařazení/ukončení/přestup mají náhled, důvod, otisk vstupů a opakovatelnou potvrzovací operaci. Změna práv se kontroluje znovu v transakci.
- `club_teams.activity_kind` obsahuje `circle`, `competitive`, `other` nebo dočasné `unclassified`. Klasifikace je explicitní na soupisce a nedědí se ze skupiny. Migrační převod původního jednoznačného `season_type` slouží pouze k zachování stávající klasifikace; nové zápisy sezonu nepotřebují. Neurčené zařazení musí správce doplnit.
- Členství používá `club_roster_members.valid_from/valid_to`, oba dny včetně. Přestup od dne D ukončí původní interval D−1. Ruční správa nesmí ukončit nárok ze zaplaceného programu; ten mění jeho zdrojová objednávka/přijetí. Budoucí RSVP, přihláška či rezervace náhrady dotčené osoby přestup nebo ukončení blokuje do vypořádání.
- `member_roster_operations` uchovává potvrzenou operaci a výsledek. Opakované odeslání stejného potvrzení nepřidává další interval. Změněný náhled je odmítnut. Hierarchické operace zamykají graf v jednotném pořadí a kontrolují cykly.
- `member_roster_age_rules` má věkové meze, rozhodný den (`on_date` / konec kalendářního roku) a případnou cílovou soupisku. `season_preparation_admin.php` zachovává URL, ale připravuje pokračování k datu. Neprovádí rollover ani automatické věkové přesuny. Neznámé narození vyžaduje kontrolu.
- Program a soupiska zůstávají trvalé. Nabídky mají vlastní data, cenu a kapacitu. Rozvrhové šablony mají `valid_from/valid_to`. Nový průvodce, opakování nabídky a katalogové přípravy nezakládají technickou sezonu.
- Náhrady mají samostatnou politiku pro nabídku (`club_makeup_offer_policies`) a výslovné směrové vazby (`club_makeup_offer_routes`). Stávající dostupnost se při první migraci zachová, nové nabídky ji automaticky nepřebírají. Zrušená vazba se opakováním migrace nevrátí. Přenesený kredit odemkne konkrétní zaplacená návazná nabídka, nikoli obecná shoda roku/programu.
- Registrace přiřazuje osobu přímo do časové soupisky. Nezapisuje druhé zařazení do starých skupin/podskupin. Členský předpis identifikuje osoba a účtované období; vyžaduje výslovného plátce, částku, splatnost a potvrzení. Překryv jiného členského předpisu se odmítne; opakování po úhradě nezaloží nový předpis.

## Dotčené stránky a návaznosti

| Oblast | Hlavní cesty | Změna |
|---|---|---|
| Registrář | `kis_roster_hierarchy_admin.php`, `sportovec_karta.php`, `eshop_identity_admin.php` | Jedna osoba, časová soupiska, náhled ručních změn; karta čte nové intervaly. |
| Programy | `club_program_wizard_admin.php`, `club_programs_admin.php`, `club_program_storefront_admin.php`, průvodce opakováním | Nabídka/rozvrh podle dat, bez volby sezony. |
| Náhrady | `club_makeup_policies_admin.php`, `training_rsvps_admin.php` | Konkrétní politika nabídky, směrové povolení náhrad a přesná návaznost. |
| Pokračování | `season_preparation_admin.php` | Členství, trenérské granty a věková kontrola k rozhodnému datu. |
| Rodina a sportovec | `booking/muj_sport.php`, `booking/sportovni_prehled.php`, rodinný kalendář a služby portálu | Jednotné členství bez vazby na aktivní sezonu a bez sezonních popisků. |
| Tréninky/akce | Plánování a filtry přes `training_roster_bridge.php`, cíle akcí přes `club_event_roster_target.php`, kalendáře | Členství/hierarchie podle data aktivity; historická skutečnost zůstává. |
| Komunikace/ceny | `roster_messages_admin.php`, `eshop_member_prices_admin.php`, služby příjemců a členské klasifikace | Aktivní soupisky a časové nároky, nikoli sezony. |
| Staré URL | `kis_rosters_admin.php`, `kis_roster_settings_admin.php`, `kis_current_rosters_admin.php`, `kis_legacy_rosters_admin.php`, `kis_rollover_a06_admin.php`, `kis_transition_admin.php`, `kis_training_a07_admin.php` | Po ověření registráře GET přesměruje do společné správy; zápisový požadavek vrátí 410. |
| Provozní nástroje | Katalogové přípravy, `trainer_pilot.php`, `bin/provision-production-uat.php` | Trvalé soupisky, odmítnutí nejednoznačného existujícího kódu; žádné slučování podle názvu. |

Původní sezonní služby a nullable sloupce jsou přechodová kompatibilita pro upgrade a historické fixture. Jejich fyzické odstranění je E8 po provozním přechodu, nikoli hotová část tohoto vydání. Úplný seznam zbylých referencí a jejich dispozic je v [inventáři](verification/member-evidence-20261102/season-dependency-inventory.json).

## Migrace a zálohy

Nové migrace `20261102170000` až `20261102174000` přidávají klasifikaci, datované nabídky/rozvrhy, rozsah náhrad, ledger ručních operací, věková pravidla a ledger převodu. Již aplikované migrace se nemění. `bin/db-backup.php` má ownership `2026-11-02.1`, nové tabulky a sloupcové kontrakty. Ověření zahrnuje SQLite fixture, MariaDB migrace a izolovanou zálohu/obnovu. CI spouští nový `tests/Support/MemberEvidenceMariaDbSmoke.php` v existující databázové matici.

## Nástroj převodu a přesný rozsah

`bin/member-evidence-cutover.php` je CLI. Připojení získává z `MEMBER_EVIDENCE_DSN`, `MEMBER_EVIDENCE_DB_USER`, `MEMBER_EVIDENCE_DB_PASSWORD`; tajemství nepředávat argumenty ani ukládat do Gitu. Výstupy musí být v soukromém adresáři mimo webroot.

1. `--inventory --output=/private/inventory.json`: úplný seznam tabulek, počtů, otisků dat/schématu a FK, metadata soupisek a seznam importovaných členských intervalů. Inventář nenahrazuje kontrolu aplikačních odkazů v JSON/frontách a soukromých souborech.
2. Připravit soukromý JSON: `cutover_on`, `reason`, `teams` (konkrétní `id`, `standing_code`, `name`, `activity_kind`) a `delete` (konkrétní `table`, `id`, `classification: imported_history`, `reason`). Zahrnout všechny aktivní soupisky s původní sezonou. Mapování zachovává ID; nástroj neprovádí slučování týmů ani osob.
3. `--preview=/private/config.json --output=/private/manifest.json`: nemění DB. Manifest váže konfiguraci, data, schéma a efektivní členství v den převodu, další den a další rok.
4. Na izolované kopii ověřit aplikaci, obnovu DB a příloh, čas odstávky a role. Syntetický smoke sám nepotvrzuje úspěch tohoto kroku na skutečné kopii.
5. Teprve pro schválený konkrétní produkční manifest pozastavit webové zápisy, importy, generátory a workery. Vytvořit a ověřit zálohu, za pozastavených zápisů znovu vytvořit náhled. `--writes-paused` je výslovné potvrzení provozního kroku, samo procesy nezastavuje.
6. `--apply=/private/manifest.json --confirm=OTISK_MANIFESTU --actor=ID_REGISTRÁŘE --backup=/private/backup --backup-sha256=OTISK_ZÁLOHY --writes-paused`. Nástroj ověří soubor zálohy, práva správce, aktuální data/schéma a transakčnost tabulek; zamkne data a provede převod v jedné transakci. Při rozdílu nic nepotvrdí. Výsledek se uloží do `member_evidence_cutover_runs`; opakování stejného manifestu vrátí existující výsledek.
7. Ověřit parity, oprávnění a provozní průchody, až poté obnovit zápisy. Po nových finančních či provozních transakcích není přípustná slepá obnova staré zálohy.

Současný cleanup podporuje pouze explicitně vybrané ukončené intervaly `source=kis_shadow` před datem převodu, jejich importní události a vypořádané `kis_current_roster_claims` / `kis_legacy_roster_claims`. Každá další FK včetně `SET NULL` nebo `CASCADE` odstranění blokuje. Neodstraňuje osoby, finanční doklady, provozní audit, tabulky sezon, importní soubory ani nejednoznačné záznamy. Pro další historické struktury je nutné konkrétní vypořádání a navazující E8; jejich automatické smazání není implementováno.

Po zapsání převodu jsou na úrovni služeb zablokovány nové sezony, rollover, původní přestupový průvodce, import osob/soupisek, vznik dalších starých importních běhů a propagace starých finančních importů. Obnovovací/čtecí cesty zůstávají pro návrat a audit.

## Stav před provozním přechodem

Technicky jsou implementovány aplikační části E1–E5 a nástroj E6. E0 má zdrojový inventář, chybí ověřená aktuální produkční inventura. E6 má syntetický nácvik a samostatnou obnovu, chybí nácvik konkrétního produkčního manifestu včetně souborů a přihlášené UAT všech rolí. E7/E8 nebyly provedeny.

Před E7 uzavřít D1–D3 nad skutečnými daty. D4/D5/D8 zůstávají výslovnými rozhodnutími v příslušných agendách: systém neodhaduje poměrné ceny, výjimky z plateb ani rušení členství pro dluh. D6 spravuje matice náhrad; D7 explicitní věkové pravidlo. Žádné uvedené syntetické ověření nepotvrzuje účetní/lidskou přejímku nebo oprávnění zapnout skutečné importy a rozesílky.
