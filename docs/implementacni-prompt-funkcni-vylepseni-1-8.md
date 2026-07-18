# Implementacni prompt: funkcni vylepseni 1-8

Datum pripravy: 2026-06-27
Projekt: `C:\xampp\htdocs\evidencePavel`
Ucel: jednim vetsim implementacnim behem doplnit funkcni vrstvu okolo clenu, KIS synchronizace, aktivity, historie a hromadnych akci.

## Copy/paste prompt pro dalsi krok

Jsi coding agent v projektu `C:\xampp\htdocs\evidencePavel` na Windows/XAMPP/PHP 8.2. Proved implementaci funkcniho baliku "Evidence clenu a KIS provozni centrum" podle nize uvedeneho zadani. Cilem je pokryt kroky 1-8:

1. Administracni karta clena
2. KIS synchronizacni centrum
3. Chytre parovani clenu
4. Workflow aktivni/neaktivni
5. Import nanecisto
6. Historie zmen u clena
7. Hromadne akce
8. Lepsi administracni dashboard

Pracuj proti realnemu kodu a DB, ne proti domnenkam. Pred implementaci si precti minimalne:

- `CLAUDE.md`
- `docs/technicka-dokumentace.md`
- `docs/formulare-tok-dat.md`
- `docs/databazove-schema.md`
- `sync_evidence.php`
- `includes/kis_sync_lib.php`
- `sprava_sportovcu.php`
- `sportovec_detail.php`
- `includes/auto_migrace.php`
- `includes/funkce.php`
- `csrf_helper.php`

## Nejdůlezitejsi pravidla

- Veskeré SQL zmeny musi jit pres `includes/auto_migrace.php`. Nepoustet rucni SQL jako reseni produkcni zmeny.
- Zvedni `SCHEMA_VERSION` z aktualni hodnoty `2.19.2` na novou semver verzi, doporucene `2.20.0`.
- Migrace musi byt idempotentni: pouzij existujici helpery `$tableExists`, `$colExists`, `$colDef`, `$exec`.
- Zachovej stavajici parser XLSX v `includes/kis_sync_lib.php`; rozsirej ho pomocnymi knihovnami, neprepisuj cely import bez duvodu.
- Vsechny POST akce musi mit CSRF.
- Vsechny importni a hromadne zapisy musi bezet v DB transakci.
- Nepovol automatickou archivaci lidi chybejicich v importu. Chybejici osoby se maji zobrazit jako riziko/pozornost, ne tise presunout.
- Respektuj opravneni pres `canAccess()`. Nové administracni obrazovky maji byt minimalne pro `hlavni`, rizikove hromadne operace radeji `admin` nebo existujici nastavitelne opravneni.
- Pouzij Bootstrap styl a vzory existujicich stranek, zejmena `sprava_sportovcu.php`, `sync_evidence.php`, `prehled_kreditu.php`.
- Neodstranuj legacy soubory ani nesouvisejici funkcionalitu v tomto behu.

## Aktualni vychozi stav

- KIS wizard existuje v `sync_evidence.php`.
- Parser tri XLSX exportu existuje v `includes/kis_sync_lib.php`.
- `sportovci` uz maji KIS sloupce:
  - `kis_aktivni`
  - `kis_platebne_aktivni`
  - `kis_neuhrazeno`
  - `kis_posledni_uhrada`
  - `kis_posledni_sync`
  - `kis_soupisky`
- `soupiska_mapping` existuje a mapuje text soupisky na `skupina_id` a `podskupina_id`.
- `sportovec_obdobi` je po migraci `2.19.2` ve tvaru pouzivanem kreditnimi strankami: `datum_od`, `datum_do`, `sazba_kc`.
- Obecny audit log existuje jako `ucto_audit_log` a helper `zapisAuditLog()` je v `includes/funkce.php`.

## Navrzeny datovy model

Pridat migraci `2.20.0` s temito tabulkami/sloupci, pokud jeste neexistuji.

### Rozsireni `sportovci`

Doporucene sloupce:

- `stav_clenstvi ENUM('aktivni','cekajici','neaktivni','archiv') NOT NULL DEFAULT 'cekajici'`
- `stav_duvod VARCHAR(255) NULL`
- `stav_manualni TINYINT(1) NOT NULL DEFAULT 0`
- `stav_aktualizovan TIMESTAMP NULL DEFAULT NULL`
- `kis_identity_key VARCHAR(180) NULL`
- `kis_match_confidence TINYINT UNSIGNED NULL`
- `kis_last_seen_at TIMESTAMP NULL DEFAULT NULL`
- index na `stav_clenstvi`
- index na `kis_identity_key`

Stav clena nesmi slepe kopirovat KIS. V UI ukazuj slozene signaly:

- KIS aktivni
- platebne aktivni
- neuhrazeno
- ma skupinu/podskupinu
- posledni KIS sync
- rucni deaktivace / rucni stav
- ma treninky v poslednich X mesicich

### `kis_import_runs`

Evidence kazdeho importu a preview:

- `id INT AUTO_INCREMENT PRIMARY KEY`
- `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
- `created_by INT NULL`
- `status ENUM('preview','applied','failed','cancelled') NOT NULL DEFAULT 'preview'`
- `source_users VARCHAR(255) NULL`
- `source_payments VARCHAR(255) NULL`
- `source_rosters VARCHAR(255) NULL`
- `stats_json JSON/TEXT NULL`
- `warnings_json JSON/TEXT NULL`
- `applied_at DATETIME NULL`
- `note TEXT NULL`

Pokud lokalni MySQL/MariaDB nema pouzitelny JSON typ, pouzij `LONGTEXT` a validuj pres PHP.

### `kis_import_rows`

Normalizovane radky / osoby z importu:

- `id INT AUTO_INCREMENT PRIMARY KEY`
- `run_id INT NOT NULL`
- `person_key VARCHAR(180) NOT NULL`
- `jmeno VARCHAR(100) NOT NULL`
- `prijmeni VARCHAR(160) NOT NULL`
- `narozeni DATE NULL`
- `email VARCHAR(255) NULL`
- `uciid VARCHAR(80) NULL`
- `oddil VARCHAR(160) NULL`
- `kis_aktivni TINYINT(1) NOT NULL DEFAULT 0`
- `kis_platebne_aktivni TINYINT(1) NOT NULL DEFAULT 0`
- `kis_neuhrazeno DECIMAL(10,2) NOT NULL DEFAULT 0.00`
- `kis_posledni_uhrada DATE NULL`
- `kis_soupisky TEXT NULL`
- `raw_json LONGTEXT NULL`
- index `run_id`
- index `person_key`

### `kis_import_matches`

Vysledek parovani:

- `id INT AUTO_INCREMENT PRIMARY KEY`
- `run_id INT NOT NULL`
- `row_id INT NOT NULL`
- `sportovec_id INT NULL`
- `match_status ENUM('new','matched','ambiguous','conflict','ignored') NOT NULL`
- `confidence TINYINT UNSIGNED NOT NULL DEFAULT 0`
- `reason VARCHAR(255) NULL`
- `candidate_json LONGTEXT NULL`
- `resolved_by INT NULL`
- `resolved_at DATETIME NULL`
- `resolved_action ENUM('create','update','link','ignore','manual') NULL`
- indexy `run_id`, `row_id`, `sportovec_id`, `match_status`

### `sportovec_history`

Clenska casova osa:

- `id INT AUTO_INCREMENT PRIMARY KEY`
- `sportovec_id INT NOT NULL`
- `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
- `created_by INT NULL`
- `source ENUM('manual','kis_import','bulk_action','system') NOT NULL DEFAULT 'manual'`
- `event_type VARCHAR(80) NOT NULL`
- `title VARCHAR(180) NOT NULL`
- `detail TEXT NULL`
- `old_json LONGTEXT NULL`
- `new_json LONGTEXT NULL`
- `ref_table VARCHAR(80) NULL`
- `ref_id INT NULL`
- index `sportovec_id`
- index `created_at`

Pouzivej tuto tabulku pro detailni historii clena a zaroven zapisuj dulezite udalosti do `ucto_audit_log` pres `zapisAuditLog()`.

## Implementacni faze

### Faze 0: Baseline a bezpecnost

1. Over aktualni `SCHEMA_VERSION`.
2. Over existenci tabulek a sloupcu pres MySQL CLI.
3. Spust baseline:
   - `composer validate --no-check-publish`
   - `composer check-platform-reqs`
   - full PHP lint mimo `vendor`
4. Pokud `git status` selze kvuli poskozenemu `.git`, jen to poznamenej; neres v tomto baliku.

### Faze 1: Migrace a helper knihovny

Vytvor/rozsir:

- `includes/kis_match_lib.php`
- `includes/sportovec_status_lib.php`
- `includes/sportovec_history_lib.php`

`kis_match_lib.php` ma umet:

- normalizovat jmeno, prijmeni, email, datum narozeni, UCI ID
- najit kandidaty ve `sportovci`
- bodovat shodu:
  - jmeno+prijmeni+narozeni exact: velmi vysoka shoda
  - UCI ID exact: velmi vysoka shoda
  - email exact: vysoka shoda
  - jmeno+prijmeni bez narozeni: stredni shoda, pokud je jedina
  - podobnost jmena: pouzit jen pro navrh, ne pro automaticky zapis
- vratit `matched`, `ambiguous`, `new`, `conflict`

`sportovec_status_lib.php` ma umet:

- spocitat provozni stav clena z KIS signalu, plateb, skupin, manualniho stavu a posledniho syncu
- vratit badge label + CSS tridu + vysvetleni
- aktualizovat `stav_clenstvi` jen podle jasnych pravidel a bez mazani dat

`sportovec_history_lib.php` ma umet:

- `sportovecLogEvent(PDO $pdo, int $sportovecId, string $eventType, string $title, array $old = [], array $new = [], string $source = 'manual', ?int $userId = null): void`
- ulozit detail do `sportovec_history`
- volitelne zapsat i `ucto_audit_log`

### Faze 2: Administracni karta clena

Vytvor `sportovec_karta.php?sportovec_id=ID`.

Karta ma mit:

- hlavicku: jmeno, prijmeni, vek/narozeni, UCI ID, email, telefon, oddil
- status strip: stav clena, KIS aktivita, platby, dluh, skupiny, posledni sync
- rychle akce: upravit, otevrit verejnou kartu, zobrazit treninky, pridat poznamku, zmenit stav
- taby nebo sekce:
  - Prehled
  - KIS a platby
  - Skupiny a soupisky
  - Treninky
  - Zavody
  - Zatezove testy
  - Kredity/odmeny
  - Poznamky
  - Historie

Napoj odkazy:

- ze `sprava_sportovcu.php`
- z `prehled_sportovcu.php`, pokud je to rozumne
- ze sync preview na detail clena

### Faze 3: KIS synchronizacni centrum

Bud rozsir `sync_evidence.php`, nebo vytvor `kis_sync_center.php` a nech `sync_evidence.php` jako kompatibilni vstup. Preferuj nebourat existujici wizard, ale doplnit mu evidenci importu.

Centrum ma zobrazit:

- posledni importy
- stav importu: preview/applied/failed/cancelled
- statistiky: nove osoby, aktualizace, shody, konflikty, mimo import, dluhy, aktivni/neaktivni
- tlacitko novy import
- detail importu s radky, kandidaty a rozdily

Pri uploadu:

1. parsuj tri XLSX pres `kis_build_import()`
2. uloz `kis_import_runs`
3. uloz `kis_import_rows`
4. vytvor `kis_import_matches`
5. zobraz preview bez zapisu do `sportovci`

### Faze 4: Chytre parovani a konflikty

Preview musi radky rozdelit:

- automaticky sparovano
- nova osoba
- konflikt / vice kandidatu
- chybejici nebo slabe udaje
- DB osoba mimo posledni import

Konflikty nesmi byt automaticky zapsany. UI musi umoznit:

- vybrat existujiciho sportovce
- zalozit noveho
- ignorovat radek
- vratit se k mapovani soupisek

U kazdeho match zobraz duvod: napr. "jmeno+prijmeni+narozeni", "email", "UCI ID", "jedina shoda podle jmena".

### Faze 5: Workflow aktivni/neaktivni

Pridat do UI filtr a akce:

- aktivni
- cekajici
- neaktivni
- archiv
- KIS aktivni
- dluh > 0
- bez skupiny
- mimo posledni import
- rucne deaktivovani

Manualni stav musi mit prednost pred automatickym dopoctem. Pokud je `stav_manualni=1`, import nesmi stav prepsat bez explicitniho potvrzeni.

Do `sprava_sportovcu.php` dopln:

- filtry podle stavu
- sloupce KIS/platby/skupiny/posledni sync
- odkazy na kartu clena

### Faze 6: Import nanecisto

Stavajici krok Preview rozsir na skutecny dry-run:

- zadne zapisy do `sportovci`, skupin nebo podskupin pred potvrzenim
- ukaz diff pred/po pro kazdeho clena
- ukaz hromadne statistiky
- ukaz rizikove zmeny zvlast:
  - zmena jmena
  - zmena data narozeni
  - ztrata skupiny
  - novy dluh
  - KIS aktivni -> neaktivni
  - rucne deaktivovany clen v importu

Potvrzeni importu:

- musi byt samostatny POST s CSRF
- musi zapsat `kis_import_runs.status='applied'`
- musi zapsat `sportovec_history` pro kazdeho zmeneneho clena
- musi aktualizovat `kis_last_seen_at`

### Faze 7: Historie zmen u clena

Na `sportovec_karta.php` tab Historie zobraz:

- manualni upravy
- KIS import zmeny
- hromadne akce
- zmeny stavu
- zmeny skupin/podskupin
- kreditni obdobi / odmeny, pokud je jednoduche napojit

Kazda udalost:

- datum/cas
- kdo
- zdroj
- titulek
- detail
- pred/po, pokud je dostupne

### Faze 8: Hromadne akce

Do `sprava_sportovcu.php` nebo nove `sportovci_hromadne.php` pridej vyber vice sportovcu a akce:

- nastavit stav
- priradit skupinu/podskupinu
- odebrat ze skupiny/podskupiny
- nastavit sazbu kreditniho obdobi nebo poslat do existujiciho `hromadne_odmeny.php`
- oznacit jako rucne neaktivni
- vycistit rucni stav
- pridat interni poznamku

Kazda hromadna akce:

- ma preview poctu ovlivnenych osob
- ma potvrzeni
- bezi v transakci
- zapisuje `sportovec_history`
- zapisuje `ucto_audit_log`

### Faze 9: Administracni dashboard

Rozsir `index.php` nebo vytvor `admin_dashboard.php` pro spravce.

Dashboard ma zobrazit bloky "Vyžaduje pozornost":

- KIS import nebyl dlouho proveden
- konflikty z posledniho importu
- osoby bez skupiny
- KIS aktivni, ale bez platby
- dluh > 0
- evidence aktivni, ale mimo posledni KIS import
- rucne deaktivovani, kteri se znovu objevili v KIS
- otevrena kreditni obdobi
- prazdne/neprirazene `soupiska_mapping`

Pridat odkazy:

- `index.php`
- `hlavicka.php`
- pripadne `sprava_sportovcu.php`

## UX poznamky

- Nepridavej marketingovou landing page. Prvni obrazovky maji byt pracovni administracni rozhrani.
- Pouzij kompaktni tabulky, filtry, badge, detailni panely a jasne akce.
- Pro rizikove akce pouzij potvrzeni a vysvetleni dopadu.
- Nedavej dlouhe napovedy primo do UI, pokud staci tooltip nebo kratky popisek.
- Dulezite stavy musi byt videt na prvni pohled: barva badge + text.

## Validace a smoke testy

Po implementaci spust:

```powershell
composer validate --no-check-publish
composer check-platform-reqs
$files = Get-ChildItem -Recurse -Include *.php,*.php3 -File | Where-Object { $_.FullName -notmatch '\\vendor\\' }
foreach ($f in $files) { php -l $f.FullName }
C:\xampp\mysql\bin\mysql.exe -uroot evidence -e "SELECT hodnota FROM nastaveni WHERE klic='schema_version';"
```

Otestuj minimalne tyto stranky pres prime include nebo lokalni Apache:

- `index.php`
- `sprava_sportovcu.php`
- `sportovec_karta.php?sportovec_id=1`
- `sync_evidence.php`
- nove KIS centrum / detail importu
- konfliktni preview importu, pokud lze pripravit test data
- `prehled_kreditu.php`
- `hromadne_odmeny.php`

Pokud je dostupny Apache:

- `http://localhost/evidencePavel/index.php`
- `http://localhost/evidencePavel/sprava_sportovcu.php`
- `http://localhost/evidencePavel/sync_evidence.php`

## Akceptacni kriteria

Hotovo znamena:

- DB zmeny jsou v `includes/auto_migrace.php`, schema version je zvednuta a lokálně ulozena v `nastaveni`.
- Existuje administracni karta clena dostupna z administrace; verejna karta sportovce zustava oddelena pres `sportovec_treninky.php?hash=...`.
- KIS import umi ulozit run/preview a ukazat shody/konflikty pred zapisem.
- Import nanecisto nedela zadne zmeny ve `sportovci`, dokud neni potvrzen.
- Konfliktni parovani se resi rucne.
- Aktivni/neaktivni stav je viditelny a filtrovany, manualni stav ma prednost.
- Historie clena ukazuje manualni i importni udalosti.
- Hromadne akce maji preview, CSRF, transakci a logovani.
- Dashboard ukazuje provozni problemy, ktere spravce musi resit.
- Dokumentace je aktualizovana:
  - `docs/technicka-dokumentace.md`
  - `docs/formulare-tok-dat.md`
  - `docs/databazove-schema.md`
  - `docs/uzivatelska-prirucka.md`
  - `CLAUDE.md`
- Full PHP lint projde.
- V zaverecne zprave jsou konkretni soubory, schema version, testy a pripadne zbyla rizika.

## Mimo rozsah tohoto promptu

- Oprava poskozeneho `.git` adresare.
- Mazani starych `.sql`, `.zip`, `.log`, backup a test souboru z webrootu.
- Prepis cele aplikace na framework.
- Automaticka archivace clenu bez lidskeho potvrzeni.
- Produkcni deploy.
