# Evidence členů bez sezon — implementace a převod

Navazuje na [pracovní plán](MEMBER_EVIDENCE_WORK_PLAN_2026-11-02.md). Tento dokument popisuje aplikační změnu a nácvik nad syntetickými daty i izolovanou kopií skutečné aplikační zálohy. Nepotvrzuje nasazení, převod živých dat ani dokončení E7/E8.

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

## Čtecí inventura před nasazením

Workflow `diagnose-member-evidence-production.yml` spouští samostatný `bin/diagnose-member-evidence.php` z privátního provozního adresáře. Nevyžaduje nové migrace ani nasazený nový runtime. Ověří cílový host a produkční konfiguraci, otevře `START TRANSACTION READ ONLY`, načte tabulky/FK, strukturu soupisek a agregace intervalů/předpisů, potom provede rollback. Nevypisuje osoby, kontakty, bankovní údaje ani jednotlivé předpisy. Výsledek neukládat do Gitu jako skutečnou mapu ID. Nástroj byl ověřen na izolovaném kompletním schématu bez nového sloupce `activity_kind`.

## Nástroj převodu a přesný rozsah

### Technický nácvik na skutečné databázové kopii

Workflow `member-evidence-copy-rehearsal.yml` stáhne poslední ověřenou zálohu do privátního adresáře izolovaného runneru, ověří SHA-256, počty všech tabulek a triggery. Skript `tests/Support/MemberEvidenceProductionCopyRehearsal.php` přijímá jen loopback a pevné jméno dočasné DB. Provede upgrade, nemutující preview, převod se zachováním ID a chráněných dat, explicitní cleanup, opakování a úplnou paritu chráněných tabulek. Potom workflow obnoví zdrojovou zálohu do stejné izolované DB a porovná otisky dat i schématu. Kopie se vždy uklízí, nezveřejňuje se jako artifact.

Kódy `REHEARSAL-ID` jsou výhradně technický vstup pro kopii, nikoli návrh produkční mapy D2. Neurčené zaměření převod zastaví. Po rozhodnutí vlastníka z 2. 11. byl nácvik rozšířen: vyžaduje přesně 435 aktivních importovaných intervalů a 560 historických, první převede na počáteční stav od 2. 11. 2026 a druhé odstraní včetně jejich importních událostí. Vypořádané převodní vazby odpojí od mazaného členství a zachová pro poslední synchronizaci. Neověřuje přílohy ani přihlášené UAT a nemůže sám uzavřít celou E6. Produkční spojení workflow používá pouze ke stažení již existující zálohy; PHP mutace běží v kontejneru runneru. Dosavadní běhy bez mazání nejsou důkazem úspěchu rozšířeného nácviku.

### Počáteční stav a poslední synchronizace

### Schválená organizační mapa

[D2 je schváleno](MEMBER_EVIDENCE_APPROVED_MAP_2026-11-02.md). Konfigurace `groups` obsahuje nové kódy/názvy, zaměření a přesná `child_ids`; každá převáděná soupiska musí být právě v jedné nové skupině. Sedm provozních skupin zahrnuje 41 soupisek, osmá odděluje tři testy. Převod vyžaduje shodu `expected_code` / `expected_name` se zdrojem. Odmítá existující rodičovské vazby a kolize kódů, nikdy nepoužije existující skupinu s jinými oprávněními. V jedné transakci vytvoří skupiny a hrany platné od počátečního dne a ověří sjednocení osob bez duplicit. Nepřidává členství ani oprávnění do nových skupin. Dosavadní trenérské rozsahy jsou v chráněné paritě. Čtecí kontrola pilotu a opakované provozní zajištění soupisky dohledají její zachované ID přes audit `standing_cutover`; neznámý nebo změněný cíl odmítnou. Jednorázové přípravy katalogu 2026/27, Kunratic, Točňáků a nejmenších jsou po přechodu zablokované, aby neobnovily staré kódy.

Nácvik s `mapping=approved` načte soukromý JSON ze secretu prostředí `production` nazvaného `KIS_MEMBER_EVIDENCE_MAP`. Mapa ID se netrackuje ani nevypisuje do logu. Runner ji uloží jen do dočasného adresáře, zkontroluje rozsah 44 soupisek / 8 skupin a přesnou identitu zdrojů; po nácviku ji odstraní spolu s kopií. Výstup obsahuje pouze otisk mapy a agregované výsledky. Režim `technical` zachovává původní nácvik s kódy `REHEARSAL-ID`.

### Počáteční stav a poslední synchronizace

`memberEvidencePrepareOpening()` sestaví kandidáta s konkrétními ID; běžný preview potom ověří každou vazbu. Konfigurace `opening.member_ids` a `opening.expected_count` musí přesně odpovídat všem aktivním importovaným intervalům k rozhodnému dni. Převzatý interval zachová ID, osobu, soupisku a případný konec platnosti; dostane počátek 2. 11. 2026, ručně spravovatelný zdroj `manual`, původ `kis-opening:ID` a audit `opening_balance`. Pole `detach_legacy_claim_ids` může obsahovat jen vypořádané vazby na výslovně mazané historické členství. Odlišný počet, jiná závislost nebo změna po náhledu převod zastaví.

`opening.final_sync_pending=true` ponechává jednu následnou synchronizaci. CLI `--sync-preview` / `--sync-apply` vyžaduje konkrétní export přes `--source` a jeho SHA-256. Přijímá pouze explicitní přidání nebo ukončení převzatého intervalu; nepřítomnost v exportu nic nemaže, neznámá osoba vyžaduje registrační postup. Odmítá období před 2. 11. 2026, překryvy, zásah do placeného nároku, změněná data a druhou odlišnou dávku. Opakované potvrzení stejné dávky vrátí původní výsledek. Audit je v `member_roster_operations`; původní ledger převodu zachovává původní rozhodnutí, dokončení synchronizace určuje její vlastní záznam. Nový export ještě není k dispozici, takže synchronizace se nespouští ani nenahrazuje prázdnou dávkou. E8 musí zachovat potřebné převodní mapy do jejího dokončení.

### Produkční manifest

`bin/member-evidence-cutover.php` je CLI. Připojení získává z `MEMBER_EVIDENCE_DSN`, `MEMBER_EVIDENCE_DB_USER`, `MEMBER_EVIDENCE_DB_PASSWORD`; tajemství nepředávat argumenty ani ukládat do Gitu. Výstupy musí být v soukromém adresáři mimo webroot.

1. `--inventory --output=/private/inventory.json`: úplný seznam tabulek, počtů, otisků dat/schématu a FK, metadata soupisek a seznam importovaných členských intervalů. Inventář nenahrazuje kontrolu aplikačních odkazů v JSON/frontách a soukromých souborech.
2. Připravit soukromý JSON: `scope: application_backup`, `cutover_on`, `reason`, `teams` (konkrétní `id`, `standing_code`, `name`, `activity_kind`) a `delete` (konkrétní `table`, `id`, `classification: imported_history`, `reason`). Zahrnout všechny aktivní soupisky s původní sezonou. Mapování zachovává ID; nástroj neprovádí slučování týmů ani osob. Rozsah používá existující ownership kontrakt zálohy, odmítá FK přes hranici a nezamyká cizí tabulky. Výchozí `whole_database` slouží pro dosavadní izolované scénáře, ne pro tento sdílený produkční provoz.
3. `--preview=/private/config.json --output=/private/manifest.json`: nemění DB. Manifest váže konfiguraci, data, schéma a efektivní členství v den převodu, další den a další rok.
4. Na izolované kopii ověřit aplikaci, obnovu DB a příloh, čas odstávky a role. Syntetický smoke sám nepotvrzuje úspěch tohoto kroku na skutečné kopii.
5. Teprve pro schválený konkrétní produkční manifest pozastavit webové zápisy, importy, generátory a workery. Vytvořit a ověřit zálohu, za pozastavených zápisů znovu vytvořit náhled. `--writes-paused` je výslovné potvrzení provozního kroku, samo procesy nezastavuje.
6. `--apply=/private/manifest.json --confirm=OTISK_MANIFESTU --actor=ID_REGISTRÁŘE --backup=/private/backup --backup-sha256=OTISK_ZÁLOHY --writes-paused`. Nástroj ověří soubor zálohy, práva správce, aktuální data/schéma a transakčnost tabulek; zamkne data a provede převod v jedné transakci. Při rozdílu nic nepotvrdí. Výsledek se uloží do `member_evidence_cutover_runs`; opakování stejného manifestu vrátí existující výsledek.
7. Ověřit parity, oprávnění a provozní průchody, až poté obnovit zápisy. Po nových finančních či provozních transakcích není přípustná slepá obnova staré zálohy.

Současný cleanup podporuje pouze explicitně vybrané ukončené intervaly `source=kis_shadow` před datem převodu, jejich importní události a vypořádané `kis_current_roster_claims` / `kis_legacy_roster_claims`. Každá další FK včetně `SET NULL` nebo `CASCADE` odstranění blokuje. Neodstraňuje osoby, finanční doklady, provozní audit, tabulky sezon, importní soubory ani nejednoznačné záznamy. Pro další historické struktury je nutné konkrétní vypořádání a navazující E8; jejich automatické smazání není implementováno.

Po zapsání převodu jsou na úrovni služeb zablokovány nové sezony, rollover, původní přestupový průvodce, import osob/soupisek, vznik dalších starých importních běhů a propagace starých finančních importů. Obnovovací/čtecí cesty zůstávají pro návrat a audit.

## Stav před provozním přechodem

Technicky jsou implementovány a sloučeny aplikační části E1–E5 a nástroj E6. E0 má zdrojový inventář a úspěšnou čtecí produkční inventuru; konkrétní dispozice a manifest ještě nejsou uzavřené. E6 má syntetický nácvik a úspěšný technický převod 44 soupisek na skutečné izolované aplikační záloze včetně obnovy všech 337 původních tabulek. Chybí nácvik konkrétního produkčního manifestu úklidu včetně souborů a přihlášené UAT všech rolí. E7/E8 nebyly provedeny. Přesné důkazy obsahuje [ověřovací protokol](verification/member-evidence-20261102/RESULTS.md); aktuální čísla a rozhodnutí obsahují [podklady převodu](MEMBER_EVIDENCE_PRODUCTION_TRANSITION_2026-11-02.md).

D1 a výchozí rozsah D3 jsou potvrzené: 2. 11. 2026, převzetí 435 aktivních a odstranění 560 historických zařazení. Mapa D2 je také schválená. Nácvik přesné organizační mapy prošel v běhu 41532405191. Před E7 zbývá aktuální produkční manifest včetně provozních podmínek, ověření příloh a přihlášené přejímky. D4/D5/D8 zůstávají výslovnými rozhodnutími v příslušných agendách: systém neodhaduje poměrné ceny, výjimky z plateb ani rušení členství pro dluh. D6 spravuje matice náhrad; D7 explicitní věkové pravidlo. Žádné uvedené syntetické ověření nepotvrzuje účetní/lidskou přejímku nebo oprávnění zapnout skutečné importy a rozesílky.

Referenční čtečky výkonových profilů W7.2/W7.3 přebírají pouze záměrnou změnu projekce soupisek (nulové sezonní aliasy a řazení podle začátku členství). Šablona W7.2 používá aktuální HTML bez sezonního sloupce při zachování původního controlleru. Původní způsob načítání osob a rozpočty dotazů zůstávají, aby měření stále odhalilo návrat N+1.
