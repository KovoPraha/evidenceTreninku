# Session handoff

## Aktualizace 20. 8. 2026 — oprava deploy preflightu proti předchozímu release (`04fe558`)

### Dokončený výsledek

- `bin/deploy-preflight.php` už nepředpokládá, že aktuálně nasazený docroot
  obsahuje soubor `includes/shop_bank_settings.php`, který přináší teprve
  nasazované vydání. Všechny volitelné aplikační soubory načítá přes jedinou
  kontrolovanou hranici s `is_file()`.
- Pokud starý docroot nový resolver obsahuje, preflight připojí databázi a použije
  `shopBankSettingsEffective($pdo)`. Pokud soubor ještě neexistuje, ověří
  dosavadní konstanty přes `shopBankSettingsFromConfig()`. Chyba bankovní
  diagnostiky se dál vrací jen ve `warnings[]`; nepřítomnost budoucího souboru
  už nezpůsobí fatální pád před zálohou.
- Kontrola celého preflightu nenašla jiný předpoklad souboru z nového release.
  `config.php` je povinná produkční konfigurace a již nasazený
  `includes/shop_checkout.php` se kontroluje před načtením.

### Regrese a brány

- Nový obecný test
  `DeployWorkflowContractTest::testPreflightDoesNotRequireFilesFromTheIncomingReleaseDirectly`
  nejprve na původním kódu prokazatelně selhal na přímém načtení checkoutu
  a bankovního resolveru. Po opravě povoluje přímé načtení jen povinného
  `config.php`; každý aplikační `require`/`include` navíc test odmítne, pokud
  neprojde společnou kontrolou existence. Existující bankovní wiring test byl
  zpřesněn pro dvojí režim preflightu, ostatní čtenáři nadále nesmějí obcházet
  databázový resolver.
- Zaměřené testy: `13 tests / 290 assertions`. Plná sada: `671 tests / 6075
  assertions`, jedna existující PHPUnit deprecation. Lint prošel na všech 534
  sledovaných first-party PHP souborech. `composer validate --strict`,
  `composer audit --locked`, `composer check-platform-reqs` a `git diff --check`
  jsou zelené.
- Migrační brána proběhla na izolované `10.3.39-MariaDB` z ověřené lokální
  zálohy: `check` našel očekávané pending migrace, `apply` a následný `check`
  vrátily `current: true`, katalog `61`, `pending: []`, legacy baseline
  `2.20.2`. Testovací schéma bylo odstraněno a kontrola vrátila nulu.
- Všech pět MariaDB smoke scénářů prošlo na `10.3.39-MariaDB` i
  `11.4.0-MariaDB`: child access, hobby transition, záloha a obnova 114 tabulek,
  ruční katalog a souběžný checkout/platba/poslední místo kroužku. Po obou
  bězích zůstalo nula testovacích schémat a servery byly vráceny na porty 33103
  a 33114.
- Lokální XAMPP MariaDB na portu 3306 se nespustila kvůli dřívějšímu poškození
  InnoDB (`Missing MLOG_CHECKPOINT`). Její data nebyla otevřena, opravována ani
  měněna; všechny databázové brány proto běžely na izolovaném serveru 10.3.
  Řez nemění rozhraní, takže browserová brána nebyla relevantní.

### Provozní hranice a další krok

- Implementační commit je `04fe558`. Produkce, `origin/main`, push i deploy
  zůstaly beze změny; produkce je stále na `612c793` a bankovní administrace
  stále čeká na nasazení.
- Následuje samostatná dokumentační oprava nepravdivého tvrzení o produkčních
  `SHOP_BANK_*` v `docs/CURRENT_STATE.md`. Až poté se vlastníkovi předloží přesný
  výčet commitů a bez jeho pokynu se nic nepushuje ani nenasazuje.

## Aktualizace 19. 8. 2026 — bankovní účet e-shopu v administraci (`27b2d8e`, `ff20427`)

### Dokončený výsledek

- Účet, na který chodí platby klubu, šel dosud změnit jedině spuštěním GitHub
  Actions workflow a jeho hodnotu nešlo vůbec přečíst — jediná kontrola byla
  vytvořit objednávku a podívat se na QR. Nová obrazovka
  `eshop_bank_admin.php` ukazuje platný IBAN, BIC, název účtu i splatnost,
  dovolí je změnit bez GitHubu a bez vývojáře a je dostupná z navigace
  i z `eshop_admin.php`.
- Úložištěm je `shop_bank_settings` s pevným primárním klíčem `1`, takže druhý
  konkurenční řádek nemůže vzniknout ani při souběžném uložení. Každá změna je
  transakční a zapisuje se do `shop_bank_settings_events` s typem a ID aktéra,
  předchozí i novou hodnotou a povinným důvodem. Uložení beze změny je
  bezpečný no-op a nezakládá prázdný auditní řádek.
- **Přednost zdrojů: databáze vyhrává, konstanty z `config.php` jsou záloha,
  dokud v databázi žádný záznam není.** Produkce tím zůstane funkční mezi
  nasazením a prvním uložením a localhostové demo konstanty fungují beze změny.
  Obrazovka vždy ukazuje zdroj právě platné hodnoty a při rozdílu mezi zdroji
  vypíše výslovné upozornění včetně toho, co je v `config.php`. Uložený, ale
  neplatný záznam se nikdy tiše neobejde konstantami — checkout je do opravy
  fail-closed, protože jinak by peníze chodily na starý účet.
- `shopBankValidateSettings()` zůstává jediným validátorem; administrace
  nezakládá druhou kopii pravidel. Rozsah názvu účtu byl sjednocen na 3–120
  znaků bez řídicích znaků, aby přijímal přesně totéž co produkční nástroj.
  Všichni čtenáři jdou přes resolver: storefront, administrace e-shopu, deploy
  preflight i Fio import, který se musí párovat proti skutečně platnému účtu.
- Kontrolní QR se vykreslí z uloženého nastavení bez vzniku objednávky:
  `shopBankSampleQr()` nedostává PDO, takže ze své podstaty nemůže nic zapsat.
  Ukázková částka je 1 Kč, variabilní symbol `9999999999` je nejvyšší možný
  desetimístný, a tedy mimo řadu odvozenou z ID objednávky, a zprávu
  `UKAZKA NEPLATIT` nese i samotný QR kód.
- U splatnosti je přímo u pole napsáno, že řídí i dobu, po kterou nezaplacená
  objednávka drží místo v kroužku, a že 30 dní zablokuje kapacitu na měsíc.
- Oprávnění je konfigurovatelné klíčem `eshop_bank_settings` stejným vzorem
  jako `sync_evidence`. **Poznámka k zadání:** v tomto projektu je `admin` (3)
  vyšší role než `hlavni` (2), takže zadání „nejvyšší úroveň, tedy hlavni, ne
  admin“ si odporovalo. Migrace proto seeduje `min_role='admin'` podle
  uvedeného důvodu, tedy nejpřísněji; vlastník ji smí kdykoli snížit na
  `hlavni` v `nastaveni_opravneni.php` bez zásahu do kódu. Chybí-li klíč
  v relaci, `canAccess()` spadne zpět na `hlavni`, takže obrazovka není nikdy
  otevřená trenérovi.
- Workflow `configure-production-bank.yml` zůstalo nedotčené jako nouzová cesta.
  Runbook nově popisuje, že po prvním uložení v administraci už jeho spuštění
  nic nezmění, protože přednost má databáze.

### Neměnnost existujících objednávek — ověřeno, nález nevznikl

Bankovní údaje se snapshotují do `payments` v okamžiku vzniku objednávky
(`iban_snapshot`, `bic_snapshot`, `account_label_snapshot`, `spd_payload`,
`due_at`) a `booking/objednavka.php` je odtud jen vykresluje. Změna účtu proto
nemůže přepsat platební příkaz, který zákazník už má. Regrese to nyní hlídá:
po změně IBANu drží starší nezaplacená objednávka původní účet, název, VS
i SPD payload, zatímco nová objednávka použije nový účet.

### Kontrakt zálohy a manifest

- `EVIDENCE_OWNED_COLUMN_CONTRACT` doplněn o sloupce R7:
  `club_event_term_versions.status` / `archived_at` / `archived_by_trainer_id`,
  `shop_order_items.program_terms_*` a `club_program_enrollments.terms_*`.
  Sloupce R6 tam podle uzavřeného rozhodnutí nepatří. Dvě nové tabulky
  bankovního nastavení jsou v `EVIDENCE_TABLES`.
  `EVIDENCE_OWNERSHIP_CONTRACT_VERSION` je `2026-08-19.2`.
- Manifest nese **obě čísla**: `owned_column_contract` jako očekávání kódu
  a nové `owned_columns_present` jako seznam sloupců skutečně přítomných ve
  snímku. Záměrně nejde o varování, které by bylo při každém vydání s novým
  sloupcem červené a po třetím vydání by ho nikdo nečetl.
- Ověřeno prakticky na `10.3.39-MariaDB` proti schématu před vydáním
  (katalog 55 migrací): manifest uvedl úplný kontrakt a vedle něj jen
  `shop_products: source_candidate_id, source_run_id` a
  `shop_variants: source_candidate_id`, tedy realitu snímku. Obnova 110 tabulek
  přitom prošla. Nad úplným katalogem se obě čísla shodují a obnova 114 tabulek
  prošla na 10.3.39 i 11.4.0. Restore drill čte jen `sha256`, `application`,
  `sql_file`, `format_version`, `tables` a `triggers`, takže nový klíč se ho
  nedotkne.
- Regrese `DatabaseBackupOwnershipContractTest` čte kontrakt bez spuštění
  zálohy přes strážní konstantu `EVIDENCE_BACKUP_LIBRARY_ONLY`, kterou deploy
  nikdy nedefinuje; soubor zůstává samostatně spustitelný.

### Regrese, živý localhostový průchod a úklid

- Nové regrese pokrývají přednost zdrojů, konflikt obou zdrojů, odmítnutí
  neplatného IBANu, krátkého názvu, splatnosti mimo 1–30 a chybného BIC,
  fail-closed checkout bez platného nastavení i nad poškozeným záznamem,
  neměnnost existující objednávky, ukázkový QR bez zápisu, audit s aktérem
  i předchozí hodnotou, jeden řádek pravdy, opakovatelnost migrace, strážení
  obrazovky před jakoukoli prací s databází a kontrakt zálohy.
- V prohlížeči administrátor otevřel obrazovku se zdrojem „z config.php“,
  neplatný IBAN neprošel a v databázi nevznikl žádný řádek ani auditní zápis.
  Uložení platného účtu zadaného s mezerami je normalizovalo, přepnulo zdroj na
  „z administrace“, vypsalo rozdíl proti `config.php` a doplnilo, kdo a kdy
  změnu provedl. Ukázkový QR se vykreslil s VS `9999999999` a částkou 1 Kč,
  aniž přibyl jediný záznam. Rodič poté vytvořil objednávku, která dostala účet
  z administrace a pětidenní splatnost; po změně IBANu ukázala tatáž objednávka
  v prohlížeči dál původní účet, VS i QR.
- Testovací objednávka, položka, platba, skladový pohyb, košík, obě nastavení
  i oba auditní záznamy byly odstraněny v jedné transakci a sklad se vrátil na
  původní hodnotu. Kontrolní SELECT vrátil ve všech šesti skupinách nulu.
  Instrumentovaná sonda manifestu je v `var/_to_delete/prompt-e-r8-checkpoint/`.

### Brány a provozní hranice

- Plná sada je `670 tests / 6066 assertions` s jednou existující PHPUnit
  deprecation. Lint prošel na 534 first-party PHP souborech. `composer validate
  --strict`, `composer audit --locked`, `composer check-platform-reqs`
  a `git diff --check` jsou zelené.
- Lokální migrace prošla `check (jedna čekající) → apply → check (current)`;
  katalog má 61 migrací. Zálohovací i checkoutový smoke prošly na
  `10.3.39-MariaDB` i `11.4.0-MariaDB`; oba dočasné servery byly ukončeny
  a testovací databáze odstraněny.
- Produkce, `origin/main`, Prompt G, push i deploy zůstaly beze změny.
  IBAN, název účtu ani obsah secrets se nedostaly do Gitu, logu ani testů;
  všechny použité účty mají neexistující bankovní kód 9999.

### Další krok

- Navrhnout vlastníkovi nasazení tohoto řezu jako samostatné malé vydání.
  Teprve po něm nastaví účet v administraci a teprve pak má smysl bod 9
  poinstalačního ověření, tedy zkušební nákup běžného zboží.
- Poté zahájit R9 a předložit čtyři body zapsané v předchozí sekci; body 3 a 4
  jsou tímto řezem hotové, zbývá návrh `parent_path` a rozhodnutí o produktu
  bez kategorie.

## Aktualizace 19. 8. 2026 — Prompt E R1–R8 nasazen na produkci (`612c793`)

### Výsledek nasazení

- Vlastník spustil běh `32245326447`. Všech osmnáct kroků je zelených včetně
  plné testovací sady, kontroly syntaxe, předletové kontroly serveru, zálohy,
  migrací, aktivace release a HTTP smoke testu. Doba běhu 1 min 23 s. Produkce
  přešla z `0e43a8b` na `612c793`, tedy dvacet commitů a pět nových migrací.
- Záloha vznikla **před** migracemi:
  `evidence_2026-08-19_110000_18457cc3.sql.gz`, SHA-256
  `1366ab5d9d0ab53f1c492787818edafdef981d1516c0a95b2d950f8d9fa0a65d`,
  159 vlastněných tabulek, 2 triggery, 1 256 661 bajtů, uloženo v
  `/home/www/kovopraha.cz/www/data/.kis-backups` mimo webroot.
- Migrace doběhly a byly ověřeny dvakrát: `current: true`, `reason: current`,
  `catalog_count: 60`, `legacy_state: current` s cílem `2.20.2`. Produkční PHP
  se aktivovalo teprve po tomto ověření.
- Předpovězené chování zálohy se na produkci potvrdilo. Záloha běží před
  migracemi, takže manifest tohoto běhu uvádí sloupcový kontrakt s `origin`
  a `created_by_trainer_id`, které ve schématu v tom okamžiku ještě
  neexistovaly. Manifest je deklarativní, proti schématu se neověřuje a záloha
  proto neselhala ani nevarovala. Náprava je zařazená na začátek R9.

### Poinstalační ověření

- **1 · Záloha** — doloženo výstupem kroku „Vytvořit a ověřit produkční zálohu“
  (viz hodnoty výše) a hláškou, že se aplikace smí měnit teprve poté.
- **2 · Migrace** — `current`, katalog 60/60, ověřeno před aktivací i po ní.
- **3 · Storefront odhlášeně** — `booking/eshop.php` se načetl bez relace,
  zobrazil dosavadní aktivní nabídku a novou řádku kategorií s chipy `Vše` a
  `Doplňky (1)`. Filtr `?kategorie=Doplňky` vrátil nadpis „Kategorie: Doplňky“
  a zvýrazněný chip, takže kategoriové menu z R8 je v provozu.
- **6 · Kroužky** — `booking/krouzky.php` vykreslil všechny tři sekce:
  „Placené kroužky“ s prázdným stavem, „Bezplatné kroužky“ a „Placené klubové
  události“, obě rovněž s prázdným stavem. Žádná regrese v živé funkci;
  produkce zatím nemá publikovanou klubovou událost.
- **8 · Databázové invarianty** — read-only drill `overit-databazove-invarianty`
  (běh `32246124827`) vrátil `ok: true`, `checked: 16`, všech šestnáct kontrol
  `ok` s nulovým počtem a prázdné pole `violations`.
- **4, 5, 7 · Administrační obrazovky** — neověřeno tímto vláknem. Vyžadují
  přihlášení do produkční administrace; přihlašovací údaje vlákno nepoužívá ani
  nepřijímá. Předáno vlastníkovi jako tři kliknutí.
- **9 · Zkušební nákup** — blokováno, dokud nejsou nastavené `SHOP_BANK_*`.
  Provede se až po nastavení banky a s výslovným souhlasem, protože vytvoří
  skutečnou objednávku.

### Nastavení bankovního účtu

- Banka se nastavuje výhradně workflow **„Nastavit produkční bankovní účet
  KIS“** (`configure-production-bank.yml`) s jediným vstupem
  `potvrzeni = NASTAVIT-BANKU`. Údaje se berou ze secrets prostředí
  `production` `KIS_BANK_IBAN` a `KIS_BANK_ACCOUNT_LABEL`, které už existují.
  Ruční nahrávání `config.php` není potřeba a je zakázané.
- Skript `bin/configure-production-bank.php` vloží spravovaný blok mezi
  `// BEGIN KIS MANAGED BANK ACCOUNT` a `// END KIS MANAGED BANK ACCOUNT` hned
  za úvodní `<?php` produkčního `config.php`, zálohuje původní soubor do
  `data/.kis-backups/config/`, zapisuje atomicky přes `rename()` a nastaví
  práva `0600`. Opakované spuštění je idempotentní a blok nezduplikuje.
- Deploy `config.php` nepřepisuje. Rsync ho vylučuje při přípravě release
  i při aktivaci a stávající produkční soubor se do release jen kopíruje.
  Nastavení tedy proběhne jednou a další nasazení ho zachovají.
- Dvě omezení workflow, která nelze změnit vstupem: BIC je pevně prázdný
  řetězec a `SHOP_BANK_DUE_DAYS` je pevně `7`. Prázdný BIC je pro tuzemský
  převod v pořádku. Sedm dní je zároveň doba, po kterou nezaplacená objednávka
  drží místo v kroužku; ponechat ji doporučujeme. Změna kterékoli z obou hodnot
  vyžaduje úpravu kódu, ne jen jiný secret.

### Brány a provozní hranice

- Nasazený kód je totožný s lokálně ověřeným stavem: `655 tests / 5879
  assertions`, lint 528 first-party souborů, `composer validate --strict`,
  `composer audit --locked` a `composer check-platform-reqs` zelené.
  Testovací sada proběhla znovu i uvnitř deploy workflow.
- Ověření bylo čtecí. Produkční databáze se tímto vláknem nemutovala, žádná
  produkční objednávka nevznikla a do výstupů ani do dokumentace se nedostaly
  osobní údaje.
- Ownership kontrakt zálohy zůstává `2026-08-17.2`.

### Další krok

- Vlastník spustí nastavení banky, proklikne body 4, 5 a 7 a vyžádá si bod 9.
- Poté zahájit R9. Na začátku R9 předložit: návrh `parent_path` a hierarchie
  kategorií, rozhodnutí o produktu bez kategorie, doplnění sloupců R7 do
  `EVIDENCE_OWNED_COLUMN_CONTRACT` s bumpnutím verze a úpravu manifestu tak,
  aby vedle kontraktu jako očekávání kódu nesl i seznam sloupců skutečně
  přítomných ve snímku.

## Aktualizace 18. 8. 2026 — Prompt E, kontrolní bod R8: diagnostika checkoutu a značka vzoru (`4df4a47`, `f96915b`)

### Dokončený výsledek

- Skutečná chyba checkoutu se nově zaloguje. Obecná větev
  `shopCheckoutPlace()` sice původní `PDOException` zabalovala jako `previous`,
  ale `booking/eshop.php` chytá `ShopCheckoutException` bez logování, takže
  databázová porucha končila u zákazníka obecnou hláškou a na serveru beze
  stopy. Nejde o regresi z R3–R8; nasadit ale nelze bez diagnostiky, protože
  checkout tímto vydáním výrazně nabývá na složitosti.
- Log obsahuje pouze třídu výjimky, `SQLSTATE`, driver kód a původ celého
  řetězu `previous`, plus prefix hashe klíče, který se joinuje na
  `shop_orders.idempotency_key_hash`. Zprávy ovladače se záměrně nelogují,
  protože mohou citovat hodnoty řádku, například e-mail. Soubor a řádek přitom
  jednoznačně určují, který příkaz selhal. Hláška pro zákazníka se nezměnila.
- Podmínka překladu deadlocku je vytažená do
  `shopCheckoutIsSerializationFailure()`, takže ji poprvé kryje test. Měření
  proti skutečné 10.3.39 potvrdilo, že `1205` (lock wait timeout), `1054`
  (neznámý sloupec), `1062` (duplicitní klíč) ani `2006` se nepřekládají a
  drží obecné fail-closed odmítnutí; překládá se jen `SQLSTATE 40001` nebo
  chyba `1213`. Deadlock se loguje samostatným prefixem, aby šlo odlišit
  provozní souběh od skutečné poruchy.
- `CLUB_PROGRAM_TERM_DEFAULTS` začíná značkou `VZOR — před publikováním
  upravte.` přímo v textu dokumentu, ne jen v rozhraní. Doplňující rozhodnutí
  to vyžadovalo a v R7 to vypadlo. Důvod je právní: kdyby administrátor
  publikoval připravené znění beze změny, klub by se opíral o storno podmínky,
  které nikdo nenapsal, a značka se propíše i do rodičova snímku souhlasu.

### Chování zálohy vůči sloupcům, které ve schématu ještě nejsou

Ověřeno prakticky proti `10.3.39-MariaDB` nad **produkčním schématem**, tedy
katalogem 55 migrací bez pěti nových. `bin/db-backup.php` v takovém stavu
**projde, exit kód 0**, a úplná obnova 110 tabulek souhlasí v počtech řádků
i triggerech.

`EVIDENCE_OWNED_COLUMN_CONTRACT` se do manifestu zapisuje **deklarativně** —
konstanta se serializuje beze změny a proti schématu se nijak neověřuje. Ani
neselže, ani nevaruje, ani sloupec nepřeskočí. Záloha pořízená před migracemi
proto uvádí `origin` a `created_by_trainer_id`, které v tom okamžiku ve
schématu ještě neexistují (`source_candidate_id` a `source_run_id` jsou navíc
stále `NOT NULL`). Manifest tedy popisuje očekávání kódu, ne obsah snímku.

Není to blokátor nasazení a data neohrožuje — dump je úplný a sám sebe
popisuje. Je to ale vlastnost, na kterou narazí každé další vydání, které do
kontraktu něco přidá, protože záloha běží v deploy workflow **před** migracemi.
Nabízí se doplnit do `bin/db-backup.php` varování se seznamem sloupců
z kontraktu, které ve schématu chybí; patří to do stejné změny jako doplnění
R7 sloupců, tedy na začátek R9.

### Kontrakt zálohy — rozhodnutí a načasování

Vlastník přijal rozdělení: R6 do kontraktu nepatří, protože
`birth_year_from/to` je volitelný obchodní atribut, který nemění, kdo smí
zapisovat. R7 tam patří, protože `status`/`archived_at`/`archived_by_trainer_id`
ruší invariant „každý řádek je platný“ a `program_terms_*` v
`shop_order_items` a `club_program_enrollments` jsou přímo zápisový kontrakt
i právně relevantní důkaz. Doplnění se odkládá **na začátek R9**, protože bumpne
`EVIDENCE_OWNERSHIP_CONTRACT_VERSION` a mění skript, který na produkci běží
jako první krok deploye.

### Rozšířený rozsah R11

K původnímu zadání R11 přibývají čtyři nálezy z kontrolního bodu:

1. **Editace a uzavření existující nabídky — povinná součást, ne volitelná.**
   `club_programs_admin.php` umí nabídku jen zakládat a `clubProgramEvent()`
   zná pouze akce `create_program` a `create_offer`. Bez toho nelze kroužek
   provozovat přes rok.
2. **Důvod neprodejnosti v administraci.** `clubProgramVariantSaleState()`
   vrací srozumitelný `reason`, ale čte ho jen storefront a checkout;
   rozhodnutí 7 výslovně žádá, aby administrátor viděl, proč se produkt
   neprodává.
3. **Hláška poraženému v souběhu** neřekne, že je kroužek plný.
4. **Transliterace SKU** nesmí zlomit slovo uvnitř (`KP-RAJC-ATKA-…`); pokud to
   nejde spolehlivě, zkrátit na hranici slova. Nejnižší priorita.

### Brány a provozní hranice

- Plná sada je `655 tests / 5879 assertions`, s jednou existující PHPUnit
  deprecation; stav samotného `4df4a47` byl zvlášť ověřen na `653 / 5869`.
  Lint prošel na 528 first-party PHP souborech. `composer validate --strict`,
  `composer audit --locked`, `composer check-platform-reqs` a
  `git diff --check` jsou zelené. Migrační katalog je current 60/60.
- Nové regrese: `ShopCheckoutDiagnosticsTest` kryje překlad deadlocku, omezení
  hloubky řetězu a nepřítomnost osobních údajů ve stopě;
  `ShopCheckoutTest::testDatabaseFailureDuringCheckoutIsLoggedWithoutPersonalData`
  ověřuje, že databázová chyba při checkoutu zapíše log a zákazník dostane
  obecnou hlášku, přičemž log neobsahuje e-mail, jméno, obsah košíku ani
  zprávu ovladače; `ClubProgramTermDefaultsTest` hlídá značku vzoru.
- Obě nové změny nepřidávají migraci ani tabulku, ownership kontrakt zálohy
  zůstává `2026-08-17.2`. Dočasný server 10.3.39 byl po ověření ukončen,
  testovací databáze odstraněny a instrumentované sondy přesunuty do
  `var/_to_delete/prompt-e-r8-checkpoint/`.
- Produkce, `origin/main`, Prompt G, push i deploy zůstaly beze změny.

### Další krok

- Předložit vlastníkovi aktualizovaný výčet 19 commitů a počkat na pokyn
  k pushi; vlastník mezitím nastaví produkční `SHOP_BANK_*`.
- Po nasazení projít devět bodů poinstalačního ověření (bod 9 až po nastavení
  banky), aktualizovat `docs/CURRENT_STATE.md`, rebasovat na nový `origin/main`
  a otevřít R9. R9 musí vědomě vyřešit dvě věci z R8: filtr kategorií dnes
  dělá přesnou shodu řetězce a nezahrnuje podkategorie, a produkt bez
  kategorie se objeví jen pod „Vše“.

## Aktualizace 18. 8. 2026 — Prompt E R8: transakční průvodce „Vypsat kroužek“ (`c520b35`)

### Dokončený výsledek

- R8 přidává jednu obrazovku `club_program_wizard_admin.php`, dostupnou z navigace
  i z `eshop_admin.php` a `club_programs_admin.php`. Devět kroků průvodce (název a
  popis, cena a DPH, obrázek, kategorie a parametry, období a prodejní okno s
  kapacitou, volitelné ročníky, storno podmínky, souhlas, cílová soupiska, souhrn)
  vede k jedinému POST. Souhrn se v prohlížeči přepočítává živě, takže administrátor
  před odesláním vidí, co vznikne.
- `clubProgramWizardCreate()` provede celý zápis v jedné transakci: produkt a
  variantu s původem `manual` a SKU `KP-`, výchozí kategorii, obrázek, sezónu a
  soupisku, program, nabídku, obě verze podmínek a teprve nakonec publikaci.
  Aktivace je záměrně poslední krok, takže poloviční kroužek nemůže být zákazníkovi
  viditelný. Jednorázový klíč v session brání dvojímu odeslání a při selhání se
  už uložený obrázek přesune do karantény.
- Aby průvodce nemusel zakládat paralelní model, dostaly tři existující cesty
  transakční jádro se stejnou konvencí jako `shopOrderConfirmPaymentInTransaction()`:
  `shopCatalogPublicationActivateInTransaction()`,
  `shopProductImageAddStoredInTransaction()`, `kisRosterCreateSeasonInTransaction()`
  a `kisRosterCreateTeamInTransaction()`. Veřejné funkce zůstaly beze změny chování
  a nadále otevírají vlastní transakci; jádra na otevřenou transakci trvají.
  Jádro přidání obrázku navíc znovu ověří bezpečnostní záznam připraveného souboru,
  takže se do katalogu nedostane cesta mimo `uploads/shop-products/`.
- `shopStorefrontCatalog()` nově vrací i kategorie produktu (jeden dávkový dotaz nad
  `shop_product_categories`, výchozí kategorie první). Na tom stojí základní
  kategoriové menu v `booking/eshop.php`: chipy „Vše“ a jednotlivé cesty s počty,
  filtr v URL `?kategorie=…` a nadpis podle výběru. Menu se odvozuje výhradně z
  právě prodejných produktů, žádná metadata kategorií zatím neexistují — to je
  úkol R9.
- `booking/krouzky.php` dostal úzkou sekci „Placené kroužky“ nad dosavadním
  bezplatným výpisem. Zobrazuje jen varianty, které projdou
  `clubProgramVariantSaleState()`, a obrázky omezuje na lokální soubory.
  Rozhodnutí vlastníka číslo 2 je tím splněné na obou místech.

### Regrese, živý localhostový průchod a úklid

- Regrese `ClubProgramWizardTest` ověřují úplné vytvoření kroužku jedním voláním,
  nezveřejnění při selhání kteréhokoli kroku a nulový zůstatek po rollbacku;
  `ShopStorefrontTest` a obě wiring sady kryjí kategorie ve storefrontu.
- Kompletní živý průchod prošel v prohlížeči na localhostu. Administrátor založil
  průvodcem kroužek `Rajčátka` (produkt 251, varianta `KP-RAJC-ATKA-FBE16F`,
  klíč `manual:…`, cena 1 500 Kč, kategorie `Kroužky`, kapacita 2, ročník 2025,
  nová sezóna i soupiska `LOCALHOST Rajčátka`, obě verze podmínek v1). Kroužek se
  objevil na `/booking/krouzky.php` i v kategoriovém menu e-shopu pod chipem
  `Kroužky` s popiskem „pro ročník 2025“.
- Osoba narozená 1985 byla odmítnuta hláškou o věkovém omezení a nevznikla ani
  prázdná položka košíku; dítě narozené 2025 prošlo. Objednávka bez zaškrtnutého
  souhlasu fail-closed skončila hláškou a žádná objednávka nevznikla. Po potvrzení
  souhlasu vznikla objednávka `KP2608188826B944AD` s QR kódem, variabilním symbolem
  `0000000012` a splatností; administrace ukázala rozpad kapacity `0 / 1 / 1`.
- Po ručním potvrzení syntetické platby přešel rozpad na `1 / 0 / 1`, vznikla
  aktivní účast se snapshotem obou verzí podmínek a členství `active / shop` na
  soupisce od 1. 9. 2026. Storno objednávky účast i členství korektně ukončilo
  (`cancelled`, `removed`, platba `refund_required`) se zápisem do
  `club_roster_events` i `club_program_enrollment_events`.
- Po posunu konce prodejního okna do minulosti kroužek zmizel ze storefrontu i z
  kategoriového menu, `booking/krouzky.php` zobrazil prázdný stav a detail produktu
  přestal být veřejně dostupný, zatímco `catalog_status` zůstal `active`.
  Souběžně ověřený nákup běžného zboží zůstal beze změny: formulář bez příjemce,
  bez věkové kontroly a bez souhlasu, objednávka `KP260818F70CFC33F4` se
  snapshotem bez `beneficiary_sportovec_id` i bez podmínek.
- Syntetický produkt, varianta, kategorie, obrázek, publikace, program, nabídka,
  obě verze podmínek, sezóna, soupiska, členství, účast, obě objednávky, položky,
  platby, skladové pohyby, notifikace i košíky byly odstraněny v jedné transakci.
  Následná kontrola patnácti skupin vrátila celkový zůstatek 0 a sklad testované
  varianty se vrátil na původní hodnotu. Nahraný obrázek a dočasná sonda souběhu
  jsou v `var/_to_delete/prompt-e-r8-checkpoint/`.

### Brány a provozní hranice

- Plná sada je `648 tests / 5828 assertions`, s jednou existující PHPUnit
  deprecation. Lint prošel na 526 first-party PHP souborech. `composer validate
  --strict`, `composer audit --locked`, `composer check-platform-reqs` a
  `git diff --check` jsou zelené. Migrační katalog je current 60/60.
- Souběžný checkout posledního místa byl znovu ověřen na `10.3.39-MariaDB`
  i `11.4.0-MariaDB`. V obou verzích uspěje právě jeden rodič; druhý dostane
  fail-closed odmítnutí „Některá položka už není dostupná. Obnovte košík.“ a
  jeho košík zůstane `active` i s položkou, takže může objednat něco jiného.
  Rozhodnutí padne na kapacitní kontrole se zamykacím čtením, ne na deadlocku.
  Oba dočasné servery byly po testu ukončeny a testovací databáze odstraněny.
- Překlad deadlocku z R7 se chytá pouze na `SQLSTATE 40001` nebo chybu `1213`.
  Změřeno na 10.3.39: `1205` (lock wait timeout), `1054` i `1062` se nepřekládají
  a padají do obecné hlášky. Otevřený nález: obecná větev v `shop_checkout.php`
  zabalí původní `PDOException` jako `previous`, ale `booking/eshop.php:59`
  `ShopCheckoutException` neloguje, takže skutečná databázová chyba při checkoutu
  nezanechá v logu stopu.
- Další otevřené nálezy k R9–R11: administrace nemá cestu k editaci ani uzavření
  existující nabídky (`clubProgramEvent()` zná jen `create_program` a
  `create_offer`); důvod z `clubProgramVariantSaleState()` se nikde v administraci
  nezobrazuje, takže rozhodnutí 7 „musí být vidět, proč se neprodává“ zatím není
  splněné; předpřipravený text podmínek neobsahuje označení
  „VZOR — před publikováním upravte“; produkt bez kategorie se objeví jen pod
  „Vše“; překlad deadlocku nemá regresi.
- R8 nepřidává trvalou tabulku, takže ownership kontrakt zálohy zůstává
  `2026-08-17.2`. Implementace je commit
  `c520b35961d5acdcabf00d9a26bc7e59f03723cb`. Produkce, `origin/main`, Prompt G,
  push i deploy zůstaly beze změny.

### Další krok

- Kontrolní bod po R8: nechat vlastníka potvrdit rozsah nasazení R3–R8, doprovodit
  nasazení na produkci, rebasovat na nový `origin/main` a teprve pak zahájit R9
  (metadata kategorií nad `category_path`, administrace, veřejné menu a filtrování).

## Aktualizace 17. 8. 2026 — Prompt E R7: verzované podmínky kroužku (`cddad4b`)

### Dokončený výsledek

- R7 používá existující registr `club_event_term_versions`; nevznikl druhý
  číselník ani nová trvalá tabulka. Každý program má dvě oddělené verze:
  storno podmínky a souhlas s účastí. Výchozí text patří programu a konkrétní
  nabídka jej může samostatně přepsat. Tato hierarchie zachová opakovaně
  použitelný program a dovolí odlišnost jedné sezóny bez kopírování obou
  dokumentů.
- Uložení vytvoří automaticky další verzi, dosavadní aktivní verzi archivuje s
  časem a ID trenéra a historický text už nepřepisuje. Nabídka bez obou platných
  efektivních dokumentů je fail-closed: program nelze publikovat a již aktivní
  položka není prodejná.
- Checkout zobrazuje oba dokumenty a vyžaduje výslovné potvrzení pro každý
  program v košíku. Přesný snapshot ID verze, označení verze, textu a SHA-256
  obou dokumentů se atomicky uloží k položce objednávky spolu s časem a účtem
  rodiče. Po platbě se tentýž snapshot beze změny přenese k účasti. Otisk
  košíku obsahuje podmínky, takže změna verze bezpečně zneplatní starou stránku.
- Kompatibilita starších izolovaných testovacích schémat je záměrně vázaná na
  přítomnost R7 sloupců: po aplikaci migrace je kontrola vždy přísná, zatímco
  historické fixture bez migračního registru nadále ověřují jen starší vrstvy.
  MariaDB deadlock z přesně synchronizovaného závodu se mapuje na stejné
  srozumitelné fail-closed odmítnutí dostupnosti; žádný částečný zápis
  nevznikne.

### Regrese, živý localhostový průchod a úklid

- Regrese ověřují blokaci publikace bez podmínek, povinný souhlas na serveru,
  programový výchozí text i override nabídky, archivaci v1 po vzniku v2,
  neměnnost již přijatého snapshotu a jeho přesný přenos do účasti po platbě.
- V prohlížeči vznikl koncept `R7 BROWSER SOUHLAS` / `KP-R7-BROWSER`, program a
  nabídka. Administrátor přes UI uložil obě v1, produkt publikoval a rodič na
  storefrontu viděl oba dokumenty. Bez zaškrtnutí objednávka nevznikla; po
  potvrzení vznikla objednávka `KP260817D350AFCAEB` a DB doložila snapshot
  v1/v1 s účtem rodiče. Následná editace souhlasu vytvořila v2, archivovala v1
  s trenérem 43 a původní objednávka si ponechala přesný text v1.
- Syntetický produkt, varianta, publikace a audity, program, nabídka, termíny,
  objednávka, položka, platba, košík i dočasná vazba osoby byly odstraněny v
  kontrolované transakci. Souhrnný SELECT potvrdil ve všech skupinách nulový
  zůstatek.

### Brány a provozní hranice

- Plná sada je `644 tests / 5785 assertions`, s jednou existující PHPUnit
  deprecation. Lint prošel na 523 first-party PHP souborech. `composer validate
  --strict`, `composer audit --locked`, `composer check-platform-reqs` a
  `git diff --check` jsou zelené.
- Lokální migrace prošla `check → apply → check` a katalog je current 60/60.
  Úplný backup/restore smoke všech 112 vlastněných tabulek i souběžný checkout
  posledního místa prošly na `10.3.39-MariaDB` i `11.4.0-MariaDB`; dočasné
  servery byly ukončeny. Protože R7 nepřidává tabulku, ownership kontrakt
  zálohy se nemění.
- Implementace je commit `cddad4b062088fb1e414e965e397fb6df6de5db8`.
  Produkce, `origin/main`, Prompt G, push i deploy zůstaly beze změny.

### Další krok

- Zahájit čistý R8: transakční průvodce „Vypsat kroužek“, který spojí katalog,
  nabídku, podmínky a cílovou soupisku a zakončí se povinným kompletním živým
  průchodem před kontrolním bodem.

## Aktualizace 17. 8. 2026 — Prompt E R6: ročníky a skutečná kapacita (`df0f4e1`)

### Dokončený výsledek

- Nabídka programu má aditivní nullable omezení `birth_year_from` a
  `birth_year_to`. Rozsah je validovaný mezi 1900 a aktuálním rokem, může být
  jednostranný a počáteční ročník nesmí převýšit koncový. Prázdná dvojice
  znamená bez omezení; veřejné i administrační UI používá společný čitelný
  popisek, včetně přirozeného „pro ročník 2025“ pro jediný ročník.
- Dítě se u programu vybírá už na detailu produktu a přidání do košíku proběhne
  atomicky s ověřením aktivní schválené vazby a data narození. Chybějící nebo
  nepovolený ročník nevytvoří ani prázdnou položku. Totéž se znovu fail-closed
  ověří při změně příjemce a uvnitř transakce checkoutu; běžné zboží zachovalo
  volitelnou vazbu příjemce i původní chování.
- Jediný výpočet kapacity nyní sčítá aktivní účasti a množství položek platných
  nezaplacených objednávek. Držení končí přesně v
  `shop_orders.payment_expires_at`; vypršení se vyhodnocuje líně při čtení a
  nepotřebuje worker ani změnu `status`. Storno držení uvolní a zaplacená
  objednávka se po vzniku aktivní účasti nepočítá dvakrát.
- Checkout zamyká katalogovou variantu a nabídku, potom čte aktivní účasti i
  držící objednávkové položky zamykacím aktuálním čtením. První verze smoke
  testu odhalila, že samotný zámek nabídky nestačí: druhý proces po čekání stále
  používal starý transakční snapshot. Přechod na `FOR UPDATE` nad konkrétními
  řádky uzavřel závod; ze dvou současných checkoutů posledního místa nyní
  přesně jeden uspěje a druhý dostane srozumitelné odmítnutí bez částečného
  zápisu.
- Administrace nabídky zobrazuje odděleně „Aktivní / zaplacené“, „Drží
  nezaplacené“ a „Skutečně volné“. Trvalou tabulku R6 nepřidává; nová migrace
  pouze idempotentně rozšiřuje `club_program_offers`, takže ownership kontrakt
  zálohy se nemění.

### Regrese, živý localhostový průchod a úklid

- Regrese pokrývají odmítnutí ročníku už při vložení i po podvržení košíku,
  chybějící narození, neomezenou nabídku, lazy uvolnění po lhůtě, storno,
  přechod držení na aktivní účast bez dvojího započtení a opakovatelnost
  migrace. MariaDB dvouprocesový smoke navíc připraví dvě rodiny a nabídku s
  kapacitou jedna, obě pustí k zamčenému závodu a ověří právě jednu čekající
  objednávku a jedno odmítnutí.
- V prohlížeči administrátor založil koncept `R6 BROWSER KROUŽEK` se SKU
  `KP-R6-BROWSER`, přímo v administračním výběru jej navázal na aktivní období
  s kapacitou 1 a ročníkem 2025 a poté produkt publikoval. Storefront i detail
  zobrazily „pro ročník 2025“.
- Dočasná osoba narozená 1985 byla odmítnuta textem o věkovém omezení a DB
  potvrdila nula položek; osoba narozená 2025 prošla. Objednávka zobrazila QR,
  variabilní symbol a splatnost a administrativa přešla z `0 / 0 / 1` na
  `0 / 1 / 0`; storefront plnou nabídku skryl. Po auditovaném posunu testovací
  lhůty do minulosti zůstal stav objednávky `placed/pending`, rozpad se vrátil
  na `0 / 0 / 1` a nabídka se bez workeru znovu ukázala.
- Syntetický produkt, varianta, publikace, program, nabídka, objednávka,
  položka, platba, košíky a dvě dočasné vazby osob byly po ověření odstraněny v
  kontrolované transakci. Následný souhrnný SELECT potvrdil pro všech osm
  skupin nulový zůstatek.

### Brány a provozní hranice

- Plná sada je `643 tests / 5743 assertions`, s jednou existující PHPUnit
  deprecation. Lint prošel na 521 first-party PHP souborech; všech 12 souborů
  vlastněných R6 bylo po poslední úpravě znovu ověřeno. `composer validate
  --strict`, `composer audit --locked`, `composer check-platform-reqs` a
  `git diff --check` jsou zelené.
- Lokální migrace prošla `check (jedna čekající R6) → apply → check (current)`
  a finální `check → apply → check`; katalog má 59/59 migrací. Úplný migrační
  a backup/restore smoke se 112 tabulkami i nový souběžný checkout posledního
  místa prošly na `10.3.39-MariaDB` i `11.4.0-MariaDB`; oba dočasné servery byly
  po testu ukončeny a izolované testovací databáze odstraněny.
- Implementace je commit `df0f4e1c8b8871b06262edce148d43d78fe04833`.
  Produkce, `origin/main`, Prompt G, push i deploy zůstaly beze změny.

### Další krok

- Zahájit čistý R7: verzované souhlasy programu a nabídky, povinné potvrzení v
  checkoutu a neměnný auditní snapshot souhlasu u objednávky/účasti.

## Aktualizace 17. 8. 2026 — Prompt E R5: bezpečné obrázky produktu (`3746174`)

### Dokončený výsledek

- Správa produktu nyní umožňuje administrátorovi nahrát JPG nebo PNG do 5 MB
  a 6000 × 6000 px, určit jeho pořadí, pořadí později změnit a obrázek odebrat.
  Každá operace vyžaduje CSRF, důvod a výslovné potvrzení.
- Server nevěří příponě ani MIME z prohlížeče: obsah ověří přes `finfo`, dekóduje
  přes GD a vždy znovu zakóduje jako JPEG na bílé plátno. Tím zahodí EXIF i
  ostatní metadata. Veřejný soubor má pouze kryptograficky náhodný název;
  původní jméno se neukládá do cesty ani do databáze.
- Databáze používá existující `shop_product_images`. Přidání, změna pořadí a
  odebrání zapisují ve stejné databázové transakci snapshot do existujícího
  `shop_catalog_admin_events` s aktérem `trainer`, jeho ID a důvodem. Zámky
  drží konzistentní pořadí produkt → obrázek.
- Soubor se ukládá jako kanonická relativní cesta
  `uploads/shop-products/<32 hex>.jpg`. Storefront pustí jen přesný vlastní
  vzor a z něj sestaví adresu přes důvěryhodné `APP_BASE_URL`; nadále odmítá
  cizí HTTP, jiné protokoly, řídicí znaky i URL s přihlašovacími údaji.
  Kroužek ukáže pouze takový vlastní ručně schválený obrázek, nikoli starý
  importní obrázek původního produktu.
- Odebrání fyzický soubor nemaže. V rámci kompenzovaného transakčního toku ho
  přesune do `var/_to_delete/shop-product-images`; při databázovém rollbacku
  se pokusí soubor vrátit. Veřejný kořen už globálně zakazuje indexování a
  spuštění skriptových přípon v `uploads`, takže nový adresář nepotřeboval
  další trvalý konfigurační soubor.

### Regrese, živý localhostový průchod a úklid

- Nový integrační test odmítne textový soubor vydávající se za JPG bez řádku v
  databázi a bez veřejného souboru. Druhý test vloží do platného JPEG vlastní
  EXIF marker, projde přidání → změnu pořadí → odebrání a ověří, že uložený
  JPEG marker neobsahuje, všechny tři akce jsou auditované a odebraný soubor
  leží v karanténě. URL regrese navíc ověřuje povolenou vlastní cestu, traversal,
  ne-náhodný název a cizí doménu se stejně vypadající cestou.
- V prohlížeči administrátor založil `R5 BROWSER Obrázek programu` se SKU
  `KP-R5-BROWSER-IMG`, nahrál skutečné PNG, změnil pořadí z 10 na -5 a viděl
  audit `add_image` a `reorder_image`. DB a soubor potvrdily náhodnou cestu,
  MIME `image/jpeg`, SHA-256 a nulový výskyt EXIF markeru.
- Pro kontrolu obou veřejných rozhraní byl syntetický produkt dočasně změněn
  na `goods` a auditovaně publikován. Obrázek se zobrazil na kartě v
  `/booking/eshop.php` i v `/booking/produkt.php?id=247`, vždy z přesné lokální
  adresy. Poté ho administrátor odebral; UI potvrdilo prázdnou galerii a soubor
  byl přesunut do karantény.
- Před úklidem bylo ověřeno, že produkt ani varianta nemají nabídku, košík,
  objednávku, členskou cenu, kategorii, event vazbu ani skladový pohyb. Produkt,
  varianta, publikace a oba auditní deníky byly následně odstraněny v jedné
  kontrolované transakci. DB i nově načtený storefront a katalog potvrdily nulu
  pro `R5 BROWSER` a `KP-R5-BROWSER-`; syntetický JPEG zůstává pouze v určené
  karanténě, jak vyžaduje pravidlo bez fyzického mazání.

### Brány a provozní hranice

- Plná sada je `638 tests / 5604 assertions`, s jednou existující PHPUnit
  deprecation. Lint prošel na 520 first-party PHP souborech;
  `composer validate --strict`, `composer audit --locked`,
  `composer check-platform-reqs` a `git diff --check` jsou zelené.
- R5 nepřidává migraci ani trvalou tabulku. Povinný lokální průchod
  `check → apply → check` skončil třikrát `current`; stav zůstává 58/58 a
  ownership kontrakt zálohy se neměnil.
- Implementace je commit `3746174`. Produkce, `origin/main`, Prompt G, push i
  deploy zůstaly beze změny.

### Další krok

- Zahájit čistý R6: kapacita nabídky včetně neuhrazených objednávek držených do
  `shop_orders.payment_expires_at`, lazy expirace, společný transakční výpočet a
  administrační rozpad aktivních účastí versus dosud platných rezervací.

## Aktualizace 17. 8. 2026 — Prompt E R4: ruční správa produktu (`049503c`)

### Dokončený výsledek

- Nová stránka `eshop_produkt_admin.php` je natvrdo jen pro administrátora,
  používá CSRF a PRG a je dostupná ze správy e-shopu. Z prohlížeče založí ruční
  produkt s první variantou, upraví produkt i variantu, přidá další sezonní
  variantu a produkt deaktivuje/archivuje bez fyzického mazání.
- Ruční produkt vzniká v jedné transakci jako `origin='manual'`, bez importních
  vazeb, s náhodným klíčem `manual:<32 hex>` a v `draft`. SKU se ověřuje proti
  rezervovanému prefixu `KP-` i globální unikátnosti; souběžná kolize skončí
  stejnou srozumitelnou hláškou. První varianta vznikne vždy v konceptu.
- Nová varianta ručního produktu převezme aktivní stav už publikovaného
  produktu. U programu je to bezpečné: bez navázané právě prodejné nabídky ji
  R3 ve storefrontu i checkoutu stejně odmítne. Tím je zachováno rozhodnutí, že
  nová sezonní nabídka vrátí produkt do prodeje bez opakované publikace.
- Produkt upravuje interní název, prostý popis, `goods`/`program`,
  `product`/`service` a povolenou viditelnost. Varianta upravuje cenu, původní
  cenu, JSON parametry, viditelnost, DPH snapshot, sklad, jednotku a EAN. Cena
  používá sdílený `couponAdminMoneyInput()` přesunutý beze změny do
  `includes/shop_coupon.php`; UI výslovně říká, že změna platí jen pro nové
  objednávky. SKU importované varianty je neměnné kvůli budoucím importům.
- Každý writer vyžaduje administrátora, důvod a potvrzení, drží pořadí zámků
  produkt → varianta a zapisuje snapshot do nové tabulky
  `shop_catalog_admin_events` ve stejné transakci. Deaktivace synchronně
  deaktivuje produkt, varianty i případnou publikaci a zachová také dosavadní
  publikační audit.
- Bezpečná editace skladu vyžadovala předsunout úzkou část R11: existující
  `shop_inventory_movements` nyní dovoluje `NULL` u objednávkové vazby a má
  nullable auditní sloupce `actor_type`, `actor_id`, `reason` pro staré řádky.
  Ruční změna skladu nikdy nepřepisuje číslo bez pohybu; zapisuje
  `manual_adjustment`, přesný rozdíl, výsledný stav, aktéra a důvod. Ne vznikl
  druhý skladový registr.

### Regrese a živý localhostový průchod

- Integrační test vytvoří ruční program a dvě sezonní varianty, ověří jejich
  původ, stavy a snapshotové audity. Nad importovanou variantou s historickou
  objednávkou změní cenu z 50 na 75 Kč a sklad z 5 na 7; objednávkový snapshot
  zůstane 50 Kč a vznikne jediný pohyb `manual_adjustment +2.000000` s aktérem
  `trainer`. Změna importního SKU, chybějící potvrzení i neplatné ruční SKU
  selžou bez částečného zápisu.
- V prohlížeči administrátor založil `R4 BROWSER Test produktu` se SKU
  `KP-R4-BROWSER-2026`, cenou 1 234,56 Kč, JSON parametrem, DPH snapshotem a
  skladem 5. Následně změnil název, cenu na 1 300 Kč, parametry i sklad na 7,
  přidal `KP-R4-BROWSER-2027` za 1 400 Kč a produkt archivoval. UI po každém
  POSTu zobrazilo PRG úspěch a audit `create_product`, `update_variant`,
  `add_variant`, `update_product`, `archive_product`.
- DB potvrdila ruční původ, obě neaktivní varianty po archivaci, pět auditních
  událostí, jeden skladový pohyb a nula objednávek. Poté byly produkt, obě
  varianty, pohyb i testovací audity kontrolovaně odstraněny; DB i nové načtení
  obrazovky potvrdily nulový zůstatek prefixů `R4 BROWSER` a
  `KP-R4-BROWSER-`.

### Brány a provozní hranice

- Plná sada je `636 tests / 5578 assertions`, s jednou existující PHPUnit
  deprecation. Lint prošel na 518 first-party PHP souborech; Composer validate,
  audit i platform requirements jsou zelené a `git diff --check` je čistý.
- Lokální migrace prošla `check (jedna čekající R4) → apply → check (current)`;
  katalog má 58/58 migrací. Úplný migrační a backup/restore smoke se 112
  tabulkami prošel na `10.3.39-MariaDB` i `11.4.0-MariaDB`. Ownership kontrakt
  `2026-08-17.2` obsahuje novou trvalou auditní tabulku.
- Kategorie jsou v R4 záměrně odložené na R9 a dvojice parametrů s našeptáváním
  na R10 podle původního pořadí; volný JSON už lze bezpečně editovat. Implementace
  je commit `049503c`. Produkce, `origin/main`, Prompt G, push i deploy zůstaly
  beze změny.

### Další krok

- Zahájit čistý R5 nad `049503c`: bezpečný upload JPG/PNG, re-encoding bez EXIF,
  lokální veřejná URL, pořadí a odstranění obrázku s auditem.

## Aktualizace 17. 8. 2026 — Prompt E R3: prodejní životní cyklus programu (`79c84bb`)

### Dokončený výsledek

- Katalogový typ `program` je povolený pro ručně založený kroužek, aniž by se
  změnila klasifikace importu: kategorie Shoptetu „Kroužky“ zůstává
  `club_event`. Publikace programu je fail-closed a vyžaduje existující vazbu
  na nabídku; `goods` si zachovává dosavadní pravidla a ostatní typy zůstávají
  blokované.
- Kruhová závislost z rozhodnutí 8 je rozseknutá společným serverovým výběrem
  variant: koncept produktu i varianty lze nabídnout pouze pro typ `program`,
  zatímco `goods` musí mít produkt i variantu nadále aktivní. Stejnou podmínku
  znovu ověřuje transakční writer nabídky, takže úprava HTML ji nemůže obejít.
  Přirozené pořadí je produkt v konceptu → nabídka → aktivace; samotná vazba
  nic nezveřejní.
- Storefront i checkout používají společnou fail-closed prodejnost programu.
  Nabídka musí být aktivní, její program musí být aktivní, aktuální čas musí
  ležet v prodejním okně, nesmí být po konci dne `ends_on` a počet aktivních
  účastí musí být pod kapacitou. Časy se vyhodnocují výslovně v
  `Europe/Prague`; neexistující lokální čas při přechodu na letní čas je
  odmítnut. Běžné zboží se chová jako před R3.
- Po skončení okna se `catalog_status` produktu nemění. Program zmizí ze
  storefrontu, checkout jej znovu odmítne pod transakčním zámkem a administrace
  zobrazí „Aktivní, ale bez platné nabídky.“ i konkrétní důvod. Nová aktivní
  sezonní varianta s novou nabídkou vrátí tentýž produkt do prodeje bez další
  publikace; stará varianta zůstane skrytá.
- `clubProgramCreate()` a `clubProgramCreateOffer()` mají transakčně
  skládatelná jádra `...InTransaction`. Nová aditivní a idempotentní tabulka
  `club_program_events` zapisuje ve stejné transakci aktéra `trainer`, jeho ID,
  akci a snapshot. Ownership kontrakt zálohy je `2026-08-17.1` a novou tabulku
  vlastní ve stejném commitu.

### Potvrzené volající a hranice navazujících řezů

- `clubProgramOfferIsOnSale()` volají `booking/eshop.php` při přidání do
  košíku, před vytvořením objednávky a při sestavení nabídky účastníků a
  `booking/produkt.php` při filtrování variant i při POSTu přidání do košíku.
  Všechny tyto cesty nyní sdílejí kontrolu `ends_on` a aktivní kapacity.
- Závazné držení kapacity neuhrazenou objednávkou do
  `shop_orders.payment_expires_at` patří do R6. Musí být lazy, transakčně
  zamčené, zahrnuté do společného výpočtu dostupnosti a v administraci rozdělit
  aktivní účasti od dosud platných rezervací. R3 záměrně počítá jen aktivní
  účasti; tento známý mezistav nesmí být zaměněn za hotovou R6 kapacitu.
- Nová sezona používá novou variantu stejného produktu/programu a zachovává
  `UNIQUE(variant_id)`. Předvídatelné SKU `KP-<slug>-<rok><sezona>`, administrační
  historie a automatické skrytí starých sezonních variant dokončí navazující
  řezy; R3 už ověřuje, že stará neprodejná varianta se ve storefrontu neukáže.

### Živý localhostový průchod a úklid

- Administrátor nejprve viděl koncept `R3 BROWSER Rajčátka / program /
  draft→draft` ve výběru nabídky, zatímco žádné konceptové `goods` ve výběru
  nebylo. Samostatný program bez nabídky měl v publikaci přesný blokátor
  „Program nemá navázanou žádnou nabídku.“ a neměl tlačítko Aktivovat.
- Po založení stabilního programu a aktivní nabídky nad konceptovou variantou
  šel produkt aktivovat. Objevil se ve storefrontu, detail zobrazil období,
  SKU, cenu a tlačítko Přidat do košíku; vložení do košíku prošlo.
- Po auditovaném posunutí konce prodeje do minulosti zmizela karta produktu ze
  storefrontu. Položka zůstala v košíku pouze pro bezpečný recheck a pokus o
  vytvoření objednávky skončil hláškou, že období už není v prodeji. DB
  potvrdila nula objednávkových položek a produkt zůstal `active`; publikace
  zobrazila „Aktivní, ale bez platné nabídky. Prodejní okno už skončilo.“
- Nová aktivní varianta a nová nabídka vrátily produkt do storefrontu bez
  opakované publikace. Detail ukázal jen novou variantu a staré SKU skryl.
- Po ověření byly v jedné kontrolované transakci odstraněny 2 syntetické
  produkty, 3 varianty, 1 program, 2 nabídky, testovací košíková položka a
  související testovací audity/publikace. Nevznikla objednávka, platba, účast
  ani členství. Následný DB dotaz i nové načtení storefrontu a administrace
  potvrdily nulový zůstatek všech prefixů `R3-BROWSER`.

### Brány a provozní hranice

- Plná sada je `633 tests / 5541 assertions`, s jednou existující PHPUnit
  deprecation. Lint prošel na 514 first-party PHP souborech;
  `composer validate --strict`, `composer audit --locked` a
  `composer check-platform-reqs` jsou zelené a `git diff --check` je čistý.
- Lokální migrace prošla `check (jedna čekající R3) → apply → check (current)`
  a katalog má 57/57 migrací. Úplný migrační a skutečný backup/restore smoke
  se 111 tabulkami prošel na izolovaných přenosných serverech
  `10.3.39-MariaDB` i `11.4.0-MariaDB`; obě testovací databáze odstranil
  `finally`.
- Implementace je samostatný commit `79c84bb`. Produkce a `origin/main`
  zůstávají na `0e43a8b`; větev nebyla pushnuta, nic nebylo nasazeno a worktree
  Promptu G na `e28abd6` zůstal nedotčený.

### Další krok

- Zahájit čistý R4 nad `79c84bb`. Kapacitní rezervace neuhrazených objednávek
  zůstává závaznou součástí R6; po každém řezu zachovat plné brány, samostatný
  implementační commit a samostatný handoff commit.

## Aktualizace 16. 8. 2026 — Prompt E R2: kolizní preflight katalogu (`24cc675`)

### Dokončený výsledek

- Ruční produkt dostává náhodný klíč `manual:<32 hex>` a jeho SKU musí začínat
  konfigurovatelnou konstantou `SHOP_MANUAL_SKU_PREFIX` s výchozí hodnotou
  `KP-`. Validátory odmítají klíče `shoptet:` i ruční SKU mimo rezervovaný
  prefix.
- `shopCatalogPromote()` po všech read-only kontrolách běhu, ale ještě před
  prvním `INSERT` promotion, sestaví úplný seznam chystaných externích klíčů a
  SKU. Porovná je s kanonickým katalogem, odhalí duplicity uvnitř stejného běhu
  a odmítne produkt, jehož klíč není v importním namespace `shoptet:`. Ruční
  produkt proto promotion nemůže zpracovat.
- Kolize selže s deterministicky seřazeným výčtem konkrétních SKU a případných
  externích klíčů. Text administrátorovi výslovně říká, že má přejmenovat ručně
  založené SKU a import zopakovat; u kolize s dřívějším importem má zkontrolovat
  duplicitní běh. Současně vysvětluje, že importní soubor není rozbitý.

### Ověření nad skutečným Shoptet exportem

- Ověřený soubor `C:\productsComplete.xml` měl SHA-256
  `f924ec583781ac6a18aa92c1574070d8a220ee49fc13baf4700ff85527046bdf` a
  normalizoval se bez blokátoru na přesně 241 produktů a 807 variant.
- V první jednorázové MariaDB databázi existoval ruční produkt se SKU
  `KP-R2-REAL-UNIKAT`. Staging a promotion stejného skutečného XML prošly a
  vytvořily všech 241 importních produktů a 807 variant vedle ruční položky.
- Ve druhé jednorázové MariaDB databázi byl záměrně nasimulován starší ruční
  produkt se skutečným exportním SKU `157/MOD`. Promotion skončila zprávou
  `Kolidující SKU: 157/MOD` a návodem k přejmenování. Počty před i po pokusu
  zůstaly přesně `0 promotions / 1 product / 1 variant`, takže preflight
  proběhl před prvním promotion insertem.
- Obě testovací databáze byly v `finally` odstraněny. Následná kontrola
  `information_schema` nenašla žádnou databázi `evidence_prompt_e_r2_real_%`;
  samotný XML soubor nebyl kopírován ani měněn.

### Regrese a provozní hranice

- Syntetické testy pokrývají průchod unikátního `KP-` SKU, kolizi SKU s ručním
  produktem, kolizi SKU i externího klíče s dřívějším importem, duplicity uvnitř
  běhu, stabilní úplný výpis a odmítnutí `manual:` produktu ve stagingu ještě
  před zápisem.
- Plná sada je `626 tests / 5457 assertions`, s jednou existující PHPUnit
  deprecation. Lint prošel na 513 first-party PHP souborech;
  `composer validate --strict`, `composer audit --locked` a
  `composer check-platform-reqs` jsou zelené. Migrační brána
  `check → apply → check` zůstala `current` na 56 migracích.
- R2 nemění rozhraní, proto podle pravidla Promptu E nemá živou browserovou
  bránu. Implementace je samostatný commit `24cc675`. Produkce a `origin/main`
  zůstávají na `0e43a8b`; nic nebylo pushnuto ani nasazeno.

### Další krok

- Zahájit R3 nad commitem `24cc675`: přidat nabídku `program` a zachovat
  fail-closed podmínky publikace, checkoutu a storefrontu. R4 ani další řezy
  neslučovat do R3 commitu.

## Aktualizace 16. 8. 2026 — Prompt E R1: původ ručního katalogu (`a0c0d73`)

### Dokončený výsledek

- Nová aditivní a idempotentní migrace
  `20260816200000_shop_manual_catalog_origin` povoluje `NULL` ve zdrojových
  sloupcích produktů a variant, zachovává jejich původní cizí klíče a přidává
  do obou tabulek `origin VARCHAR(16) NOT NULL DEFAULT 'import'` a nullable
  `created_by_trainer_id` s vazbou na `treneri`. Neprovádí datový backfill;
  dosavadní řádky získají deklarovaný výchozí původ `import`.
- Společný aplikační kontrakt odmítá importovaný produkt bez zdrojového
  kandidáta nebo běhu, importovanou variantu bez kandidáta, ruční řádek se
  zdrojovou vazbou, ruční řádek bez administrátora a variantu s jiným původem
  než její produkt. `shopCatalogPromote()` zapisuje `import` výslovně a před
  každým produktem i variantou tento kontrakt ověří.
- Ownership kontrakt zálohy je `2026-08-16.2`. Manifest nyní vedle vlastnictví
  celých tabulek výslovně uvádí změněné sloupce `shop_products` a
  `shop_variants`, aby byly při restore review viditelné.

### Ověření a provozní hranice

- Izolované SQLite testy ověřují opakované spuštění migrace, zachování
  existujících importních dat i navazujících cizích klíčů, vložení ručního
  produktu/varianty a fail-closed vazbu na trenéra. Nové unit testy pokrývají
  všechny symetrické kombinace původu.
- Jednorázová MariaDB databáze prošla migrací dvakrát na lokální verzi 10.4.32,
  zachovala importní řádek a přijala ruční řádek s nulovými zdroji. CI spustí
  stejný nový smoke také v existující matici MariaDB 10.3 a 11.4; vzdálené CI
  nebylo bez pushnutí spuštěno.
- Skutečný izolovaný backup/restore smoke prošel se 110 tabulkami a novým
  sloupcovým ownership manifestem. Plná sada je `614 tests / 5407 assertions`,
  s jednou existující PHPUnit deprecation. Lint je čistý na 511 first-party PHP
  souborech; `composer validate --strict`, `composer audit --locked` a
  `composer check-platform-reqs` jsou zelené.
- Lokální MariaDB prošla `check (jedna čekající R1) → apply → check (current)`;
  katalog má 56 migrací. Následné dotazy potvrdily nulu pro importní produkty
  bez zdrojů, neplatné ruční produkty, rozdílný původ varianty a produktu,
  importní varianty bez zdroje i neplatné ruční varianty. R1 nemění UI, proto
  nemá browserovou bránu.
- Implementace je samostatný commit `a0c0d73`. Produkce a `origin/main`
  zůstávají na `0e43a8b`; nic z Promptu E nebylo pushnuto ani nasazeno.

### Závazné hranice pro R2–R8

- R2 zastaví importní kolizi před prvním `INSERT`, vypíše všechna konkrétní
  kolidující SKU a poradí administrátorovi přejmenovat ručně založené SKU.
- Při pozdějším rozdělení `kisRosterCreateTeam()` a
  `shopCatalogPublicationActivate()` na transakčně skládatelné jádro vzniknou
  regresní testy všech současných volajících, nejen průvodce.
- Ruční cena je konečná zákaznická cena včetně DPH; příznak a sazba jsou pouze
  auditní snapshot. Budoucí dopočet DPH a dokladový tok pro klub-plátce přes
  modul `uctenky/` zůstává známým omezením mimo Prompt E.
- Testy i finální browserový průchod používají jednorázovou izolovanou DB. V R8
  se program musí zobrazit také na `/booking/krouzky.php`. Po R8 se práce
  zastaví; R9–R11 bez nové vlastnické kontroly nezačínají.

### Další krok

- Zahájit R2 nad commitem `a0c0d73`; před jeho implementací znovu ověřit čistý
  sledovaný worktree a zachovat cizí nesledované podklady beze změny.

## Aktualizace 16. 8. 2026 — Prompt F: kompatibilita scoped podmínek (`ff02c40`)

### Dokončený výsledek

- `clubEventConfigureRegistrationTerms()` po migraci
  `20260816180000_registration_terms_scope` zapisuje úplný eventový kontrakt:
  `scope_type='club_event'`, `scope_key='event:<id>'`,
  `consent_purpose='club_event_registration'`, `actor_type='trainer'` a ID
  stejného administrátora v `actor_id`. Dosavadní neměnná verze, oba texty,
  deadline, snapshoty a pravidla otevření klubové akce se nezměnily.
- Všechna aplikační čtení a zápisy `club_event_term_versions` byla znovu
  prověřena. Registrace sportovce dál vybírá své čtyři účely výhradně přes
  `scope_type`, `scope_key` a `consent_purpose`; seed scoped migrace už úplný
  kontrakt zapisoval. Jiný nekompatibilní aplikační writer ani obdobný dopad
  dalších migrací vlákna B nalezen nebyl.
- Integrační fixture registrací a cílení na soupisky nyní aplikují původní
  eventovou migraci i následnou scoped migraci. Nový regresní test založí
  klubovou akci nad post-migračním schématem, uloží podmínky a ověří přesný
  scope i auditora, zatímco čtyři registrační texty sportovce zůstanou
  oddělené.

### Ověření a provozní hranice

- Zaměřená sada: `26 tests / 616 assertions`; plná sada: `600 tests / 5358
  assertions`, jedna existující PHPUnit deprecation. Lint je čistý na `507`
  first-party PHP souborech. `composer validate --strict`,
  `composer audit --locked` (0 advisories) a `composer check-platform-reqs`
  jsou zelené.
- Lokální MariaDB prošla `check → apply → check` jako `current`; katalog má 55
  migrací. Živý localhostový admin průchod založil draft akci
  `PROMPT-F-TERMS-155420` (ID 4), přidal termín a uložil `prompt-f-v1`.
  UI zobrazilo úspěch a audit `configure_terms`, konzole neměla chyby a DB
  potvrdila `club_event / event:4 / club_event_registration / trainer`.
  Testovací akce zůstala záměrně v draftu; nic se nemazalo.
- Implementace je samostatný commit
  `ff02c4072cdf31a4619a4fdd7e1bef3239531a8f` nad osmi lokálními commity vlákna
  B. Nic nebylo pushnuto ani nasazeno a produkční databáze se nezměnila.

### Potvrzené hranice navazujícího Promptu E

- Doporučení E1–E12 jsou vlastníkem potvrzená, ale R1 nesmí začít před
  nasazením Promptu F+B, novým fetch/rebase a vytvořením izolované větve.
- Budoucí transakčně skládatelná jádra `kisRosterCreateTeam()` a
  `shopCatalogPublicationActivate()` musí mít regresní testy všech současných
  volajících, nejen průvodce. Program se v R8 musí objevit také na
  `/booking/krouzky.php`.
- Ruční cena bude konečná zákaznická cena včetně DPH; příznak a sazba jsou nyní
  pouze auditní snapshot. Pokud je klub plátcem DPH a používá modul `uctenky/`,
  budoucí výpočet DPH a dokladový tok zůstávají známým omezením mimo Prompt E.
- Importní kolize se zastaví před prvním INSERTem, vypíše konkrétní SKU a
  administrátorovi výslovně poradí přejmenovat ručně založené SKU. Testy a
  kompletní browserový scénář Promptu E použijí jednorázovou izolovanou DB.
- `docs/CURRENT_STATE.md` se aktualizuje až po produkčním nasazení, samostatným
  dokumentačním commitem podle skutečně nasazeného stavu.

### Další krok

- Připravit přesný výčet `2f11612..HEAD` a počkat na výslovné potvrzení
  vlastníka. Do té doby nic nepushovat, nespouštět produkční workflow a
  nezačínat Prompt E R1.

## Aktualizace 16. 8. 2026 — Prompt B R6: členský předpis po zařazení (`f352fbc`)

### Dokončený výsledek

- Detail schválené registrační žádosti nabízí samostatné prověření výslovně
  zvolené aktuální sezony. Zobrazuje čtyři nezávislé podmínky: schválenou
  žádost s osobou, alespoň jednu skupinu, podskupinu patřící k některé z jejích
  skupin a aktivní členství v aktivním týmu dané právě probíhající sezony.
  Tlačítko „Vystavit členský předpis“ se zobrazí pouze při splnění všech čtyř.
- Administrátor ručně zadává název, obě hranice období, splatnost, kladnou
  celočíselnou částku v haléřích a ISO formát měny. Plátce lze vybrat jen z
  aktivních schválených vazeb `self`/`guardian` na danou osobu s aktivním a
  ověřeným účtem. POST má CSRF, povinný potvrzovací checkbox a důvod alespoň 10
  znaků.
- Writer znovu ověří a na MariaDB zamkne rozhodné řádky v jedné transakci.
  Používá existující `member-charge-v1`, `club_member_charges` a
  `club_member_charge_events`: typ i zdroj jsou `membership`, stav `pending` a
  audit nese aktéra `trainer`, jeho ID, důvod a snapshot. Nevznikla nová
  finanční tabulka ani skutečná platba.
- Stabilní reference obsahuje registrační request, sezonu a typ předpisu.
  Přesné opakování je idempotentní no-op se stejným ID; pokus pod stejnou
  referencí změnit titul, částku či jiné hodnoty selže. Schválení ani zařazení
  předpis nikdy nevystavují automaticky.

### Ověření a hranice

- Plná sada: `599 tests`, `5314 assertions`; lint `507` first-party PHP
  souborů. Composer validate/audit/platform je zelený a lokální katalog 55
  migrací je aktuální.
- Izolovaný regresní test postupně ověřuje selhání každé ze čtyř readiness
  podmínek bez zápisu, cizího plátce, zápornou i desetinnou částku, neplatnou
  měnu, chybějící potvrzení, přesnou idempotenci i konfliktní duplicitu.
- Produkční databáze, deploy, vzdálený repozitář ani platební služby nebyly
  změněny. R7 zůstává uzavřený. Konkrétní právní zdroj a konečné retenční lhůty
  jsou nadále povinnou branou před produkční aktivací.

### Další krok

- Lokálně jsou autorizované řezy R1–R6 dokončené. R7 se nesmí otevřít bez
  nového rozhodnutí vlastníka; stejně tak se bez výslovného pokynu nesmí nic
  nasadit ani měnit v produkční databázi.

## Aktualizace 16. 8. 2026 — Prompt B R5: ruční zařazení sportovce (`68498a1`)

### Dokončený výsledek

- Ve stejné administrátorské frontě lze po schválení registrace explicitně
  otevřít zařazení sportovce. Formulář nabízí existující skupiny, pouze jejich
  podskupiny a týmy z aktivních, dosud neskončených sezon; vyžaduje důvod
  alespoň 10 znaků. Výběr podskupin se v prohlížeči filtruje podle skupiny,
  server však vztah vždy znovu ověřuje.
- `athleteRegistrationAdminAssign()` znovu zamkne schválenou registrační
  žádost a v jedné transakci zapíše legacy vazby `sportovec_skupina` a
  `sportovec_podskupina` i kanonické aktivní členství v
  `club_roster_members`. Zdroj soupisky je `manual`; změna má kanonickou
  roster událost i samostatný audit s kontraktem a důvodem.
- Opakování stejného požadavku je bezpečný no-op. Neplatná kombinace
  skupiny/podskupiny, neaktivní tým či sezona a neschválená žádost zastaví
  celou transakci bez částečného zařazení. R5 nevystavuje členský předpis a
  žádný další krok nespouští automaticky.

### Ověření a hranice

- Plná sada: `598 tests`, `5256 assertions`; lint `507` first-party PHP
  souborů. Composer validate/audit/platform je zelený a lokální katalog 55
  migrací je aktuální.
- Regresní test ověřuje rollback cizí podskupiny a neaktivní sezony, jediný
  zápis každé vazby, jedinou roster událost a audit a idempotentní opakování.
- Produkční databáze, deploy ani vzdálený repozitář nebyly změněny. R7 zůstává
  uzavřený.

### Následující řez

- R6 přidá pouze explicitní administrátorské vystavení členského předpisu nad
  existujícími `club_member_charges` a `club_member_charge_events`. Před zápisem
  musí samostatně ověřit schválenou registraci, skupinu, odpovídající
  podskupinu a aktivní soupisku ve výslovně zvolené aktivní sezoně; předpis se
  po R4 ani R5 nikdy nevystaví automaticky.

## Aktualizace 16. 8. 2026 — Prompt B R4: administrátorské schválení registrace (`d5fa74b`)

### Dokončený výsledek

- `eshop_identity_admin.php` zůstává jedinou frontou pro běžné žádosti o
  propojení i nové registrace sportovce. Detail registrační žádosti vyžaduje
  natvrdo session roli `admin`, používá CSRF/PRG a odpověď `no-store` bez
  referreru. Běžný trenér byl v živém localhost průchodu správně odmítnut.
- Detail zobrazuje kontaktní a adresní podklady, občanství, všechny neměnné
  snapshoty informačních textů a oddělené fotografické volby. RČ se při
  kontrole načte pouze maskovaně přes `person-sensitive-v1` a každé takové
  čtení se audituje; celé RČ má samostatný POST + CSRF + důvod. Interní
  fotografii doručí pouze existující admin-only privátní endpoint a zobrazení
  se audituje.
- Jedinou autoritou shody je sdílená `personMatchV1()`. R4 ji znovu
  implementovat nezačal: zobrazuje všechny SHODA/P1–P4 kandidáty a jejich
  pravidla, KIS příznak a samostatný bezpečný signál shody nebo konfliktu blind
  indexu RČ. SHODA blokuje běžné založení; výjimka vyžaduje potvrzení a důvod
  alespoň 10 znaků a zapisuje kandidátní ID do auditu.
- `includes/athlete_registration_admin.php` provádí oba schvalovací směry v
  jedné transakci. Připojení existující osoby i založení nové přes již hotovou
  F7 funkci `personMatchV1CreateManual()` znovu zamknou žádost, ověří aktivní
  účet, aplikují kontakt/adresu, přiřadí chráněný záznam a fotografii, schválí
  self/guardian vazbu a zapíší person-match audit. Nová osoba má
  `kis_external_id=NULL`.
- Jiný chráněný záznam RČ u vybrané osoby, neočekávané RČ ve větvi cizince,
  chybějící povinný citlivý řádek nebo stale stav blokují celou transakci bez
  částečného přepisu. Zamítnutí nastaví RČ a fotografii do předběžné 30denní
  řízené retence. R4 nezařazuje skupinu/soupisku a nevystavuje předpis.
- Typ soukromé fotografie byl sjednocen na kanonický `profile_photo`, který
  používá i `private_download.php`. Veřejná stránka nyní převádí vnitřní
  crypto/unikátní konflikt na obecnou chybu a neprozrazuje existenci RČ.

### Ověření a hranice

- Plná sada po implementaci: `597 tests`, `5227 assertions`; lint `507`
  first-party PHP souborů. Composer validate/audit/platform je zelený a lokální
  katalog 55 migrací je aktuální.
- Nové integrační testy ověřují maskované auditované čtení, všechny kandidáty
  sdíleného matcheru, připojení existující osoby, blokovanou SHODA bez override,
  F7 založení s override auditem, `kis_external_id=NULL`, adresu, vazbu účtu,
  přiřazení RČ, rollback konfliktu a reject retenci. Povinné T1–T12 zůstávají v
  jediné stávající sadě `PersonMatchV1Test`.
- Produkční databáze, deploy, produkční klíče a aktivace nebyly změněny. R7
  zůstává uzavřený; právní zdroj a konečná retence jsou stále brána až před
  produkční aktivací.

### Následující řez

- R5 na stejném detailu přidá výhradně explicitní zařazení schválené osoby do
  jedné skupiny, její podskupiny a aktivní sezonní soupisky. Musí validovat
  vztahy i aktuálnost, zapsat legacy a kanonické vazby v jedné transakci,
  auditovat a zůstat idempotentní. R6 ani R7 se v R5 automaticky nespouští.

## Aktualizace 16. 8. 2026 — Prompt B R3: samoobslužná registrace sportovce (`3918dc2`)

### Dokončený výsledek

- Vlastník potvrdil bod 9. Migrace
  `20260816180000_registration_terms_scope` zobecňuje jediný K3 registr
  `club_event_term_versions` o `scope_type`, `scope_key` a
  `consent_purpose`, zachovává staré eventové řádky a jejich identity a ukládá
  čtyři neměnné verze registračních textů. Nevznikla syntetická klubová událost,
  paralelní registr ani falešné ID trenéra; registrační texty mají aktéra
  `system`.
- `includes/athlete_registration.php` zapisuje Tok A do jediné existující
  fronty `account_person_claim_requests` s druhem `athlete_registration`.
  Vyžaduje aktivní účet s ověřeným e-mailem, vztah guardian pro nezletilého a
  self pro dospělého, povinný kontakt a úplnou adresu, přesný snapshot všech
  textů, explicitní větev cizince bez přiděleného českého RČ a stabilní
  idempotenci. Veřejná větev nikdy nevolá matcher ani nehledá kandidáty.
- RČ se ve stejné transakci ukládá výhradně přes `person-sensitive-v1`.
  Cizinec s `has_czech_birth_number=false` nemá žádný citlivý řádek ani
  náhradní číslo. Volitelná interní fotografie se ukládá mimo webroot přes
  existující privátní storage, má samostatný souhlas a při rollbacku se
  bezpečně odstraní. Veřejný fotografický souhlas je oddělená explicitní
  volba ano/ne.
- `booking/registrace_sportovce.php` poskytuje CSRF, PRG, `no-store`,
  `no-referrer`, neutrální úspěšnou odpověď a správu vlastních čekajících
  žádostí. Citlivé pole se po chybě nikdy nepředvyplní. Odkaz vede z
  `booking/moje_osoby.php`, které nyní používá i společný footer.
- Zrušení čekající registrační žádosti je transakční, idempotentní a nastaví
  předběžnou 30denní retenční lhůtu citlivému záznamu a stav řízené retence
  soukromé fotografie.

### Ověření a hranice

- Plná sada po implementačním commitu: `592 tests`, `5163 assertions`; lint
  `504` first-party PHP souborů. Composer validate/audit/platform je zelený.
- Lokální MariaDB migrace prošla `check → apply → check`; katalog obsahuje 55
  migrací a je aktuální. Izolované testy opakují migrace i submit, ověřují
  transakční atomicitu, cizince bez RČ, šifrovaný český záznam, soukromou
  fotografii, snapshoty, stale podmínky, neověřený účet, věkovou roli,
  idempotenci a storno s retencí.
- Živý localhost průchod v přihlášené relaci ověřil společnou navigaci,
  vykreslení všech čtyř verzovaných textů, explicitní volby RČ/fotografií a
  prázdnou historii. Formulář nebyl v uživatelské lokální DB odeslán; zápis
  pokrývají izolované testy.
- Produkční databáze, deploy, produkční klíče a aktivace nebyly změněny. R7
  zůstává uzavřený. Konkrétní právní zdroj a konečné retenční lhůty zůstávají
  povinnou branou až před produkční aktivací.

### Následující řez

- R4 rozšíří jedinou frontu `eshop_identity_admin.php`. Musí použít výhradně
  sdílenou `personMatchV1()`, zobrazit všechny SHODA/P1–P4 kandidáty a bezpečný
  signál blind indexu a v jedné transakci buď připojit existující osobu, nebo
  založit novou přes již hotovou F7 cestu. R5/R6 a R7 se v R4 neotvírají.

## Aktualizace 16. 8. 2026 — Prompt D: oznámení zákazníkovi po přijetí platby

### Dokončený výsledek

- Kanonický přechod platby `shopOrderConfirmPaymentInTransaction()` nyní ve
  stejné transakci zařadí událost `shop_payment_received` do existujícího
  `club_event_notifications`. Bankovní potvrzení i podepsaný Stripe webhook
  používají tutéž cestu; opakované potvrzení a duplicitní webhook dohledají
  tentýž záznam.
- Aditivní migrace `20260816170000_shop_payment_received_notification` pouze
  rozšiřuje společný outbox o nullable `order_id` a unikátní klíč
  `(order_id, notification_type)`. Nevznikla nová tabulka, worker ani správní
  fronta, proto se ownership kontrakt zálohy neměnil. SQL je kompatibilní s
  MariaDB 10.3 a SQLite migrace zachovává původní K3 data i indexy.
- Uložený plain-text obsah má veřejný kód objednávky, datum přijetí, částku a
  měnu, položky a další krok pro osobní odběr, kroužek nebo rezervaci. Neobsahuje
  platební instrukce, variabilní symbol, bearer token ani osobní/order ID v URL;
  odkaz vede pouze na přihlášení s interním návratem do `Moje objednávky`.
- Stávající worker dál claimuje a odesílá až po ukončení DB transakce, opakuje
  dočasná selhání a po pěti pokusech nechá položku `failed`. Přibyl pouze
  explicitní `local-outbox` transport pro bezpečný localhostový průchod;
  odmítnutí nebo výjimka transportu stav platby nemění.
- Stávající `eshop_notifications_admin.php` je společná fronta K3 i plateb:
  admin vidí typ, veřejný kód, stav, pokusy a chybu, escapovaný `no-store`
  náhled přesně uloženého textu a původní POST+CSRF auditované ruční opakování.

### Ověření

- Povinný vstupní audit začal na `9e4ae69`; během práce byly cizím vlastníkem
  dokončeny a integrovány řezy Prompt B a rate-limitů. Finální nedotčený základ
  je `44dd1c19181b8ec6b428c19ebf2351210f4f4c02`. Při finální kontrole byl
  lokální `main` `3 ahead / 0 behind` proti `origin/main`
  `df4154faa6fe05c6941cd986149059939c604cfe`.
  Prompt D se v původním handoffu nevyskytoval a krátký `CURRENT_STATE.md` je
  proti ledgeru zastaralý. Cizí dokumenty, build artefakty a jiné untracked
  soubory zůstaly nedotčené.
- Lokální migrace: `check (1 pending) → apply → check (current)`, katalog
  `54`, legacy schema `2.20.2`. Dvouprocesový MariaDB smoke potvrdil výsledky
  `[changed=true, changed=false]`, jednu placenou objednávku a právě jednu
  notifikaci.
- Plná sada: `587 tests`, `5105 assertions`, jedna existující PHPUnit
  deprecation; zaměřená sada `63/1162`. `php -l` je čisté na `498`
  first-party PHP souborech. Composer validate, audit (0 advisories) a platform
  requirements jsou zelené.
- Živý localhostový průchod použil pouze syntetický účet a bankovní objednávku
  `KP2608164CC1B68600`: vytvoření přes zákaznické UI → potvrzení v admin UI →
  jediná položka fronty → přesný náhled → tentýž worker s
  `local-outbox`. Výsledek workeru `processed=1, sent=1, failed=0`; DB stav
  objednávky zůstal `paid`, oznámení je `sent`, pokusy `1`. Důkaz je pouze
  lokálně ve `var/_to_delete/prompt-d-live-20260816-162342/`; žádný Stripe ani
  skutečný e-mail nebyl použit.

### Produkční hranice a povinná akce vlastníka

- Produkce nebyla nasazena a produkční DB se nezměnila. Poslední viditelné
  deploy workflow běhy jsou zelené, ale nedokládají odchozí e-mail. Repozitář
  obsahuje adaptér přes PHP `mail()`, nikoli důkaz přijetí poštovním serverem,
  skutečného doručení ani nastaveného CRONu. Produkční doručování proto zůstává
  **neověřené a vypnuté**; nové zprávy se po budoucím deployi bezpečně hromadí
  ve frontě, dokud vlastník worker nezapne.
- Po výslovně autorizovaném deployi musí vlastník mimo webroot vytvořit soukromý
  putenv bootstrap v `data/.kis-deploy/`, který nastaví `APP_HOST`,
  `CLUB_EVENT_NOTIFICATION_TRANSPORT=mail` a limit a načte
  `kis.kovopraha.cz/bin/club-event-notifications.php`. Hostingový CRON smí
  spouštět pouze holé `php data/.kis-deploy/<bootstrap>.php`; argumenty ani
  externí env hosting podle runbooku nepřenese.
- Teprve řízená objednávka na určenou testovací schránku, stav `sent` ve frontě
  a skutečně přijatý e-mail dovolí označit doručování za funkční. Návrat
  `mail()=true` sám o sobě potvrzuje jen převzetí lokálním transportem. Do té
  doby CRON nezapínat naslepo a netvrdit, že zákazníci e-mail dostávají.

## Aktualizace 16. 8. 2026 — Prompt B R2: citlivé údaje a fotografie (`dadc478`)

### Dokončený výsledek

- `includes/person_sensitive.php` zavádí jedinou fail-closed vrstvu
  `person-sensitive-v1`: validaci 9/10 číslic, datum, offsety `+20/+50/+70`,
  dělitelnost 11, XChaCha20-Poly1305, oddělený HMAC blind index, verzovaný
  keyring, rotaci a kryptografický výmaz. Šifrovací a indexový klíč nesmějí být
  stejné.
- Maskované i plné čtení vyžaduje natvrdo session roli `admin`; nepoužívá
  `canAccess()`. Odhalení je POST + CSRF + důvod, odpověď má `no-store` a každé
  úspěšné maskování, odhalení, změna, výmaz, rotace i zobrazení fotografie jde
  do `osoba_citlive_pristupy` bez plaintextu, ciphertextu nebo storage key.
- KIS synchronizace už RČ nemapuje do session payloadu, nečte je přes
  `SELECT *` a nezapisuje je do `sportovci.rc`. Historie sportovce rekurzivně
  rediguje legacy i šifrované citlivé klíče. Automatické guardy zakazují import
  decrypt vrstvy do exportů, kalendářů, story, auditů a KIS preview/parity.
- Interní fotografie se MIME/rozměrově ověří, dekóduje a znovu uloží jako JPEG
  bez EXIF do `private://athlete-photos/` mimo webroot. Plný soubor doručí jen
  admin větev `private_download.php` a zobrazení audituje.
- `ext-sodium` je povinná Composer platforma. V lokálním XAMPP byla dostupná
  knihovna pouze zakomentovaná, proto byla v `C:\xampp\php\php.ini` bezpečně
  aktivována. Produkční klíče nebyly vytvořeny, čteny ani nastaveny.
- Bezpečnostní a provozní kontrakt je v `docs/rodne-cislo-bezpecnost.md`;
  permanentní read-only kontrola je `bin/athlete-registration-preflight.php`.

### Ověření a hranice

- Plná sada po commitu: `587 tests`, `5103 assertions`; lint `498` first-party
  PHP souborů. Composer validate/audit/platform včetně Sodium je zelený a
  migrace hlásí `AKTUALNI: current`.
- Testy pokrývají délku, lomítko, kontrolní součet, přesné datum, syntetické
  `+20/+50/+70`, cizince bez RČ, chybějící/stejný klíč, duplicitní blind index,
  poškozený ciphertext, admin/hlavní roli, masku, reveal, audit, rotaci, výmaz,
  re-encoding fotografie a statické exportní/KIS guardy.
- Produkčních 1 241 legacy hodnot nebylo čteno, přeneseno ani změněno. R7,
  produkční migrace, klíče, aktivace a deploy zůstávají uzavřené.

### Další rozhodnutí

- **Brána před R3 — bod 9:** potvrdit, zda se má existující
  `club_event_term_versions` v R3 doporučeně zobecnit o scope/purpose pro
  registraci sportovce. Bez potvrzení nevznikne paralelní registr ani
  syntetická klubová událost a R3 nezačne.

## Aktualizace 16. 8. 2026 — Prompt B R1: základ registrace sportovce (`f2985b8`)

### Dokončený výsledek

- Aditivní migrace `20260816143000_athlete_registration_foundation` rozšířila
  jedinou frontu `account_person_claim_requests` o druh žádosti a verzi
  kontraktu. Přidala oddělený detail registrace, neměnné snapshoty informačních
  textů, metadata soukromých fotografií, šifrované citlivé údaje a append-only
  audit přístupů. Historická data se nebackfillovala.
- Cizinec bez přiděleného českého RČ má explicitní
  `has_czech_birth_number=false`; náhradní číslo se nevytváří. Nové schéma
  neukládá RČ, ciphertext ani cestu fotografie do `sportovci`.
- Všech pět nových trvalých tabulek je v `EVIDENCE_TABLES`; ownership kontrakt
  zálohy je `2026-08-16.1`. SQLite migrační test i generický test úplnosti
  ownership katalogu zůstávají zelené.
- Návrh byl aktualizován podle rozhodnutí vlastníka: R1–R6 jsou lokálně
  otevřené, R7 a produkce zavřené, lhůty 30/90 dní jsou předběžné a konkrétní
  právní zdroj je povinný až před produkční aktivací. Bod 9 se řeší až u R3.

### Produkční preflight před první migrací

- Read-only workflow run `31951238628` vrátil bez výpisu hodnot **1 241**
  neprázdných `sportovci.rc`. `sync_evidence` používá prahovou roli `hlavni`,
  takže ji mají 1 aktivní hlavní trenér a 3 aktivní administrátoři, celkem 4;
  individuální výjimky model nemá. Všech 16 invariantů bylo zelených.
- Preflight používal pouze `SELECT`. Pomocná vzdálená větev byla po doběhnutí
  odstraněna. Produkční databáze, aplikace ani konfigurace se nezměnily.

### Ověření a další akce

- Lokální migrace prošla `check (pending) → apply → check (current)`, katalog
  má 53 migrací. Plná sada po commitu: `567 tests`, `4862 assertions`; lint
  `488` first-party PHP souborů, Composer validate/audit/platform zelené.
- **Jediná další konkrétní akce:** implementovat R2 — fail-closed šifrovací a
  validační vrstvu, tvrdé admin-only auditované čtení, privátní fotografii a
  automatické exportní guardy. Legacy produkční hodnoty se v R2 automaticky
  nepřevádějí ani nemažou.

## Aktualizace 16. 8. 2026 — Prompt B Fáze 1: návrh registrace sportovce

### Dokončený výsledek

- Vznikl povinný návrh `docs/navrh-registrace-sportovce.md`. Návrh zachovává
  jedinou frontu `account_person_claim_requests`, odděluje citlivé údaje od
  profilu sportovce, požaduje šifrování RČ s odděleným slepým indexem, audit
  každého zobrazení a soukromé uložení fotografie mimo webroot.
- Doporučená varianta je B2: jedna objednávka může obsahovat jeden programový
  produkt pro čekajícího sportovce, s časově omezenou rezervací kapacity.
  Aktivace programu, skupiny, soupisky i členského předpisu nastane až po
  samostatném schválení administrátorem. Implementace B2 je až poslední řez R7
  a bez schválení vlastníka nezačne.
- Párování ve schvalovací transakci musí přímo volat sdílenou funkci
  `personMatchV1()` z `includes/person_match.php`; nevznikne druhá implementace
  pravidel shody osob.
- Audit potvrdil drift: `sportovci.rc` už existuje v plaintextu a používají jej
  KIS synchronizace; lokálně je neprázdných hodnot 0, produkce nebyla čtena.
  `club_event_term_versions` je pouze registr podmínek akcí a
  `member_charges_admin.php` je read-only. Návrh proto požaduje výslovné
  rozhodnutí před zobecněním registru a nový auditovaný writer předpisů.
- Nebylo změněno PHP, databázové schéma ani produkce. Nevznikla migrace, commit,
  push ani deploy.

### Ověření a pracovní stav

- Výchozí lokální HEAD je `9e4ae69c674d83c713d7a1392f2e85a767a4d1e6`;
  po čerstvém fetchi je lokální `main` 14 commitů před `origin/main` a 0 za
  ním. Před vlastními dokumentačními změnami byly sledované soubory čisté;
  cizí nesledovaný WIP zůstal nedotčený.
- Lokální DB `evidence` běží na MariaDB 10.4.32, obsahuje 150 tabulek a
  `APP_HOST=localhost php bin/migrate.php --check` hlásí `AKTUALNI: current`.
  Produkční DB, produkční tajemství ani produkční soubory nebyly čteny.
- Právní část byla ověřena proti GDPR, aktuálnímu zákonu č. 133/2000 Sb. a
  metodice ÚOOÚ. Návrh není právní stanovisko: konkrétní právní titul pro RČ,
  účely a retenční lhůty musí před implementací schválit vlastník.

### Rozhodnutí a další akce

- Vlastník musí schválit nebo změnit rozhodnutí v části 12 návrhu, zejména B1
  versus B2, povinná pole a cizince, režim dospělý/zákonný zástupce, právní
  titul a retenci RČ, oddělení souhlasů k interní a veřejné fotografii a
  zobecnění K3 registru verzí.
- **Jediná další konkrétní akce:** vlastník odpoví na body 1–8 v části 12
  `docs/navrh-registrace-sportovce.md`; do té doby nezačne žádná implementace.

## Aktualizace 16. 8. 2026 — P1 F8 kalendáře a rezervace (`e0daaa8d84d434c2b9bd849a5ee407a4ddf20826`)

### Dokončený výsledek

- Oba kalendáře sportovišť volají společný `venueCalendarUnreservedPlans()`.
  Dotaz zahrnuje plány ve stavu `planovany` i `evidovany`, ale samostatný plán
  vynechá při přímé vazbě `rezervace_id` i při historické vazbě přes shodné
  `rezervace_sportovist.trenink_id = planovane_treninky.trenink_id`.
- Evidovaný plán se vykreslí zeleně se štítkem `Zaevidováno`; odkaz
  `Otevřít trénink` používá výhradně `planovane_treninky.trenink_id`. Tabulka
  `treninky` se pro místo ani čas nepoužívá.
- `venueCalendarCreateTrainingReservation()` provede kontrolu kapacity,
  rezervaci a zápis `planovane_treninky.rezervace_id` uvnitř stejné transakce
  jako evidence tréninku. Plná kapacita už není tichý fail: vrací konkrétní
  hlášku s místem, časem a obsazeností a celý zápis bezpečně vrátí zpět.
  Zrušení rezervace zároveň uvolní přímou vazbu plánu.
- Formulář otevřený z plánu předvyplní `sportoviste_id`, `cas_od` i `cas_do` a
  rezervační panel rovnou rozbalí. Při chybě se vrací na stejný `plan_id`.
- Kalendář tréninků nyní při vybrané skupině a prázdné podskupině filtruje přes
  `trenink_skupina`; podskupina je volitelná. Kopie týdne zachovává
  `je_verejny`. Současně byl z kopírovacího INSERT odstraněn neexistující
  sloupec `misto`, takže větev odpovídá kanonickému schématu.

### Živé ověření a brány

- Lokální formulář z plánu se otevřel s rozbaleným panelem, sportovištěm `1`
  a časy `19:00–19:30`. Jediné odeslání vytvořilo trénink, rezervaci a stav
  plánu `evidovany`; plán měl `trenink_id` i `rezervace_id`. Sdílený kalendářní
  dotaz vrátil `0` samostatných plánů, vedle jedné rezervace tedy kontrolní
  `render_count=1`.
- Samostatný evidovaný plán se v týdenním i denním kalendáři zobrazil jako
  `Zaevidováno` s funkčním odkazem na skutečný trénink. Skupinový kalendář bez
  podskupiny vykreslil měsíc bez chyby. Kopie týdne vytvořila cílový plán a
  kontrolní SELECT potvrdil u zdroje i kopie `je_verejny=1`.
- Regrese navíc pokrývá historickou deduplikaci a jasný kapacitní konflikt bez
  částečné vazby. Plná sada: `567 tests`, `4849 assertions`; PHP lint `487`
  first-party souborů, `0` chyb. Composer `validate --strict`, `audit --locked`
  a `check-platform-reqs` jsou zelené. Migrace `check → apply → check` třikrát
  `AKTUALNI: current`; žádná migrace nebyla přidána.
- Všechny dočasné lokální plány, tréninky, vazby a rezervace vytvořené živými
  scénáři byly po kontrole přesně odstraněny. Produkce ani původní poškozený
  lokální InnoDB nebyly změněny. Cizí nesledovaný WIP zůstal nedotčený a nic
  nebylo pushnuto ani nasazeno.

### Další akce

- **Jediná další konkrétní akce:** vlastník může zkontrolovat souhrn Promptu A;
  push nebo nasazení provést až na jeho výslovný pokyn.

## Aktualizace 16. 8. 2026 — P1 F7 přísné zakládání osob (`80bb5231ed6caef5b8a237546de45a830615774d`)

### Dokončený výsledek

- Nový `includes/person_match.php` je jediná sdílená implementace závazného
  kontraktu `person-match-v1`. Je oddělený od skórování KIS importu, protože
  vyhodnocuje přesně SHODU a pravidla P1–P4; vlákno B má volat přímo
  `personMatchV1()` a nesmí vytvořit vlastní variantu pravidel.
- Normalizace sjednocuje mezery a velikost písmen, odstraňuje diakritiku,
  spojovníky a apostrofy. Vrací všechny přesné i podobné kandidáty včetně
  pravidel. Přesná shoda blokuje vytvoření, nabízí existující osoby a dovolí
  auditovanou výjimku „Přesto založit jako novou osobu“ jen s důvodem alespoň
  10 znaků.
- `sprava_sportovcu.php` má admin-only, CSRF chráněnou akci `create`. Ruční
  osoba dostane kryptografický veřejný token, `stav_clenstvi='cekajici'`,
  `stav_manualni=1` a žádné `kis_external_id`; každý výsledek založení i
  výjimka se zapisuje do existujícího obecného auditu bez nové migrace.
- `eshop_identity_admin.php` umí nad žádostí zobrazit všechny kandidáty,
  připojit vybranou existující osobu nebo založit osobu z údajů žádosti a
  schválit ji v jedné transakci. `accountPersonClaimApprove()` nově korektně
  vstoupí do transakce volajícího. Pevný `LIMIT 1000` nahradilo serverové
  hledání s limitem 100 výsledků, takže funguje i nad přibližně 10 000 osobami.
- Veřejná žádost kontrakt nevolá, nedotazuje `sportovci` a dál vrací stejnou
  neutrální odpověď; kontrola shod zůstává výhradně administrátorská.

### Živé ověření a brány

- V izolované lokální databázi byla vytvořena čekající žádost se stejným
  jménem a datem jako existující osoba. Pokus o založení se zastavil na přesné
  shodě, vykreslil kandidáta, „Připojit k této osobě“ a důvod výjimky. Nová
  osoba nevznikla; přesně vymezená testovací žádost, její event a auditní
  discovery záznam byly po ověření odstraněny.
- Povinné případy T1–T12 jsou pokryté: SHODA/normalizace, P1–P4, žádná shoda,
  více přesných kandidátů, audit override a zákaz veřejné enumerace. Regrese
  navíc ověřuje, že schválení žádosti nepřevezme ani předčasně necommitne vnější
  transakci založení osoby.
- Plná sada: `560 tests`, `4821 assertions`; PHP lint `484` first-party
  souborů, `0` chyb. Composer `validate --strict`, `audit --locked` a
  `check-platform-reqs` jsou zelené. Migrace `check → apply → check` třikrát
  `AKTUALNI: current`; žádná migrace nebyla přidána.
- Produkce ani původní poškozený lokální InnoDB nebyly změněny. Cizí
  nesledovaný WIP zůstal nedotčený a nic nebylo pushnuto ani nasazeno.

### Další akce

- **Jediná další konkrétní akce:** implementovat schválený řez F8 včetně
  vazby plánu na rezervaci, historické deduplikace a regrese „plán → evidence +
  rezervace v jednom odeslání“ právě jednou.

## Aktualizace 16. 8. 2026 — P1 F9 politika hesla (`0aacc178dd01c1025078bbc1f98c614ac992206c`)

### Dokončený výsledek

- Jediná funkce `passwordPolicyValidate()` v `includes/password_security.php`
  vynucuje 12–200 Unicode znaků přes `mb_strlen`, takže vícebajtové znaky se
  už nepočítají jako několik znaků hesla.
- Sdílenou politiku volá registrace zákazníka, založení i změna sportovního
  účtu, samoobslužný reset, administrace trenérů, localhost acceptance reset,
  lokální seed a provisioning produkčního test-admina. Provisioning si navíc
  zachoval svou přísnější kontrolu malého/velkého písmene, čísla a symbolu.
- Registrační formulář, oba resetovací vstupy, administrace sportovních účtů a
  správa trenérů používají shodné `minlength=12`, `maxlength=200` a texty.
  Prázdné heslo při úpravě trenéra dál znamená beze změny; každé nové heslo ale
  projde společnou serverovou politikou.
- Onboarding trenéra jednorázovým odkazem nebyl přidán: mění provozní proces
  doručení a aktivace účtu a vyžaduje samostatné produktové rozhodnutí. Současná
  cesta je teď alespoň bezpečně ohraničená společnou politikou.

### Živé ověření a brány

- V prohlížeči správa trenérů i veřejná registrace zobrazily 12–200 znaků a
  oba inputy měly stejné min/max atributy. Odeslání krátkého hesla v registraci
  i při založení trenéra skončilo zprávou `Heslo musí mít 12–200 znaků.`;
  kontrolní SELECT potvrdil `0` řádků krátkého testovacího trenéra.
- Regresní test výslovně odmítá krátké ASCII heslo, šest vícebajtových znaků a
  201 znaků; přijímá 12 ASCII, 12 vícebajtových i 200 znaků. Plošně hlídá všech
  sedm serverových zakládacích/resetovacích cest a oba hlavní formuláře.
- Plná sada: `547 tests`, `4780 assertions`; PHP lint `482` first-party
  souborů, `0` chyb. Composer `validate --strict`, `audit --locked` a
  `check-platform-reqs` jsou zelené. Migrace `check → apply → check`: legacy
  `2.20.2`, katalog `52`, `pending=[]`.
- Produkce ani původní poškozený lokální InnoDB nebyly změněny. Cizí
  nesledovaný WIP zůstal nedotčený a nic nebylo pushnuto ani nasazeno.

### Další akce

- **Jediná další konkrétní akce:** vyžádat produktové rozhodnutí pro F7
  (pravidlo duplicity při ručním založení osoby z žádosti) a F8(a) (zda
  zaevidovaný trénink zobrazovat přímo v kalendáři sportovišť), teprve potom
  implementovat poslední dva řezy.

## Aktualizace 16. 8. 2026 — P1 F4–F6 navigace (`77a3a3f006cf9859ea1a41c4356d91bf3b4f564d`)

### Dokončený výsledek

- `appUiAssets()` nyní načítá jediný centrální Bootstrap 5.3.3 bundle. Ruční
  kopie byly odstraněny ze všech 82 first-party stránek, které je měly; nový
  plošný test zakazuje další ruční kopie.
- Breakpointy horní lišty jsou sjednocené na XXL: mobilní pravidla platí do
  1399,98 px, odsazení i zarovnání pravého bloku se přepínají ve stejném bodě
  jako `navbar-expand-xxl`. Všechny odkazy mají průhledný spodní rámeček, takže
  aktivní Plánovač už nemění výšku řádku.
- Sedm nahlášených samostatných booking stránek má společnou horní i dolní
  navigaci. `program_skupiny.php` používá administrátorskou hlavičku. Ruční
  `<details>` nabídku „Můj účet“ nahradil standardní Bootstrap dropdown, který
  se zavírá kliknutím mimo a nepřetéká vpravo.
- Regresní test hlídá centrální Bootstrap JS, zákaz duplicit, XXL kontrakt,
  navigaci všech osmi nahlášených slepých uliček a standardní účtový dropdown.

### Živé ověření a brány

- Na šířce 1280 px se hamburger na dříve rozbité
  `kis_rosters_admin.php` rozbalil; levý i pravý blok měly shodný levý okraj,
  stránka načetla právě jeden Bootstrap bundle a nevznikl vodorovný přesah.
- `booking/verejny_profil.php` vykreslil horní i dolní navigaci, účtový dropdown
  se otevřel, zůstal uvnitř viewportu a stránka neměla horizontální overflow.
  Veřejný `program_skupiny.php` vykreslil hlavičku, obsah i jediný bundle bez
  přesahu. Vizuální screenshoty všech tří stavů byly zkontrolované.
- Plná sada: `545 tests`, `4763 assertions`; PHP lint `482` first-party
  souborů, `0` chyb. Composer `validate --strict`, `audit --locked` a
  `check-platform-reqs` jsou zelené. Migrace `check → apply → check`: legacy
  `2.20.2`, katalog `52`, `pending=[]`.
- Produkce ani původní poškozený lokální InnoDB nebyly změněny. Cizí
  nesledovaný WIP zůstal nedotčený a nic nebylo pushnuto ani nasazeno.

### Samostatný nález a další akce

- Mimo osm výslovně vyjmenovaných F6 stránek existuje devět starších
  standalone stránek bez sdílené navigační komponenty (`kis_child_access_admin.php`,
  `login.php`, `prehled_skupiny.php`, `prehled_skupina.php`, `pub.php`,
  `sportovec_treninky.php`, `verejny_prehled.php`, `testovaci_scenare.php`,
  `tydenni_report.php`). Rozsah F6 nebyl o tyto stránky rozšířen.
- **Jediná další konkrétní akce:** sjednotit F9 na jednu sdílenou politiku
  hesla o minimálně 12 znacích a ověřit všechny zakládací i resetovací cesty.

## Aktualizace 16. 8. 2026 — P0 F3 přihlášení a relace (`75226aa1d31dc225f27feacd9f086abea1c1fb79`)

### Potvrzená diagnóza a oprava

- Běžný cyklus přihlásit → odhlásit → přihlásit prošel před opravou všemi
  třemi cestami (`logout.php`, `booking/odhlaseni.php`,
  `booking/sportovec_odhlaseni.php`). Návrat na trenérský formulář načetl nový
  CSRF token. Neúplné odhlášení ani zastaralý CSRF formulář se proto při
  reprodukci nepotvrdily.
- Potvrdil se sdílený IP limit: pět chybných pokusů pro pět různých účtů
  zablokovalo šesté správné přihlášení a rozhraní zobrazilo jen zprávu o
  nesprávném hesle. Dočasné kategorické diagnostické logy byly před commitem
  úplně odstraněny.
- Limit účtu zůstává 5 pokusů za 15 minut, samostatný limit sdílené IP je nově
  20 pokusů za 15 minut. Vyšší IP práh chrání běžné domácí, klubové a školní
  sítě, aniž by oslabil ochranu konkrétního účtu. Blokace má nyní vlastní
  srozumitelnou zprávu v obou hlavních přihlašovacích formulářích.
- Pět citlivých přihlašovacích/resetovacích stránek posílá `Cache-Control:
  no-store` a `Pragma: no-cache`. Všechny tři odhlašovací cesty ničí celou
  relaci. Neplatná verzovaná relace přesměruje na přihlášení se zprávou místo
  holého textu 401.

### Živé ověření a brány

- Po opravě pět chybných pokusů různých účtů ze stejné IP neblokovalo správné
  přihlášení šestého účtu. Pět chybných pokusů stejného účtu naopak zachovalo
  přísnou blokaci a zobrazilo novou zprávu.
- Všechny tři odhlašovací cesty znovu prošly v prohlížeči včetně následného
  přihlášení. Uměle revokovaná lokální sportovní relace skončila na
  `booking/prihlaseni.php?session=expired` s vysvětlující zprávou. Všech pět
  stránek mělo přes HTTP obě no-store hlavičky.
- Plná sada: `541 tests`, `4737 assertions`; PHP lint `482` first-party
  souborů, `0` chyb. Composer `validate --strict`, `audit --locked` a
  `check-platform-reqs` jsou zelené. Migrace `check → apply → check`: legacy
  `2.20.2`, katalog `52`, `pending=[]`.
- Testy dál běží proti izolované lokální obnově popsané níže; původní poškozený
  InnoDB adresář ani produkce nebyly změněny. Cizí nesledovaný WIP zůstal
  nedotčený a nic nebylo pushnuto ani nasazeno.

### Další akce

- **Jediná další konkrétní akce:** implementovat P1 F4–F6 jako jeden úzký UX
  řez, znovu projít plné brány a vizuálně ověřit dotčené stránky.

## Aktualizace 16. 8. 2026 — P0 F2 tmavý režim (`cdf16620d8d1ae8025f93567e95318e957786555`)

### Dokončený výsledek

- Téma se už nikdy nezapíná automaticky podle `prefers-color-scheme`; bez
  výslovné uložené volby uživatele se načte světlé rozhraní.
- Vědomě zapnutý tmavý režim přepisuje `body.bg-light` na sdílené
  `--app-page-bg` a `.bg-white` na Bootstrap `--bs-secondary-bg`.
- Regresní test prochází first-party PHP stránky, zakazuje automatickou volbu
  tématu podle OS a hlídá obě CSS bezpečnostní sítě.
- Živý browser na čistém originu `127.0.0.1` potvrdil výchozí `light`, pozadí
  `#f3f6fa` a tmavý text. Po ručním přepnutí potvrdil `dark`, pozadí `#111827`
  a světlý text na dashboardu i `sprava_sportovcu.php`. Známé inline světlé
  panely na jednotlivých stránkách zůstávají vědomě P2 mimo tento řez.

### Brány a pracovní stav

- Plná sada: `539 tests`, `4702 assertions`, vše zelené.
- PHP lint: `482` first-party souborů, `0` chyb.
- Composer `validate --strict`, `audit --locked`, `check-platform-reqs` zelené;
  migrace `check → apply → check`, legacy `2.20.2`, katalog `52`, `pending=[]`.
- V implementačním commitu byly vlastněné pouze `hlavicka.php`,
  `assets/app-ui.css` a `tests/Unit/SharedUiShellTest.php`; po commitu nezůstal
  žádný vlastněný sledovaný dirty soubor. Cizí nesledovaný WIP zůstal
  nedotčený. Produkce nebyla ověřena, změněna ani nasazena.

### Otevřené riziko a další akce

- Platí databázový drift popsaný v aktualizaci F1: původní lokální InnoDB je
  poškozená a testy běží proti oddělené dočasné obnově; původní data nebyla
  měněna.
- **Jediná další konkrétní akce:** provést cílenou diagnostiku P0 F3 přes cyklus
  přihlásit → odhlásit → přihlásit všemi třemi odhlašovacími cestami, zaznamenat
  odmítající větev bez osobních údajů a teprve potom implementovat tři potvrzené
  opravy.

## Aktualizace 16. 8. 2026 — P0 F1 plánovač (`a2833fcdc981ab6931559077140bcca125d4bd08`)

### Dokončený výsledek

- Náhled skupinového oznámení v `planovac.php` už nevolá neexistující
  `mb_strtok()`. První řádek je zkrácený na 160 znaků a escapovaný přes `h()`,
  takže oznámení plánovač neshodí ani nevloží aktivní HTML/JavaScript.
- Nový plošný test prochází všech 482 first-party PHP souborů a ověřuje, že
  každá staticky volaná globální funkce existuje v PHP nebo je deklarovaná
  aplikací. Rozlišuje funkce od metod, konstruktorů a PHP atributů.
- Živé ověření proběhlo na localhostu se syntetickým administrátorem,
  skupinovým oznámením obsahujícím `<script>` a plánovaným tréninkem. Vykreslila
  se nástěnka, sedmidenní mřížka, plán, tlačítko „Zadat evidenci" i
  `bootstrap.bundle.min.js`; hamburger a dropdown se skutečně rozbalily a
  vložený skript se nespustil.

### Brány a pracovní stav

- Plná sada: `538 tests`, `4696 assertions`, vše zelené.
- PHP lint: `482` first-party souborů, `0` chyb.
- Composer: `validate --strict`, `audit --locked` a `check-platform-reqs`
  zelené; žádné známé bezpečnostní advisory.
- Migrace na izolované lokální obnově: `check → apply → check`, legacy
  `2.20.2`, katalog `52`, `pending=[]`.
- V implementačním commitu byly vlastněné pouze `planovac.php` a
  `tests/Unit/GlobalFunctionAvailabilityTest.php`; po commitu nezůstal žádný
  vlastněný sledovaný dirty soubor. Dřívější nesledované dokumenty, nástroje,
  `.agents/` a `var/` jsou cizí WIP a zůstaly nedotčené.
- Produkce a aktuální produkční stav nebyly v této aktualizaci ověřeny ani
  změněny; starší produkční údaje níže jsou historické.

### Otevřené riziko a další akce

- Původní lokální MariaDB datový adresář je poškozený: InnoDB odmítá start
  hláškou `Missing MLOG_CHECKPOINT`, a to i s dosavadním
  `innodb_force_recovery=1`. Nebyl opravován ani přepisován. Pro ověření vznikla
  oddělená dočasná instance z lokální zálohy s ověřeným SHA-256. Druhý drift
  mimo rozsah této vlny: migrace neumějí postavit legacy základ 2.20.2 z úplně
  prázdné databáze.
- **Jediná další konkrétní akce:** implementovat P0 F2 — vypnout automatické
  zapnutí tmavého režimu podle nastavení operačního systému a doplnit CSS
  bezpečnostní síť i regresní test.

## Aktualizace 10. 8. 2026 — závěrečná automatizovaná vlna a provozní drilly (`6993693c7cd133ede8a5c2e666468a2b3cc6c8b1`)

### Implementované opravy

- `/index.php` nyní přijímá pouze `GET` a `HEAD`; ostatní metody vracejí 405 a `Allow: GET, HEAD`.
- Přibyl ručně potvrzovaný produkční workflow pro izolovanou obnovu poslední zálohy a úzce omezenou deaktivaci jednorázových E2E účtů.
- Restore drill ověřuje SHA-256 a skutečný manifest, čeká na autentizovaný SQL dotaz, obnovuje do jednorázové MariaDB a porovnává všechny tabulky i triggery. První run odhalil slabý readiness probe; oprava je krytá regresním testem.
- Cleanup je CLI-only, vyžaduje dvojí explicitní potvrzení a může deaktivovat pouze aktivní účty `kis-e2e-<číslo>@velocota.com`; nemaže účty ani jiná data.

### Ověření a produkce

- Lokální finální gate: `518 tests`, `4559 assertions`; CI run `31432002869` zelený včetně MariaDB 10.3 a 11.4.
- Produkční restore run `31432074594`: checksum v pořádku, **154 tabulek a 2 triggery** obnoveny a přesně porovnány v izolaci.
- Produkční cleanup run `31432168737`: `matched=1`, `deactivated=1`, `tokens_consumed=1`.
- E-mailové cesty: registrace, ověření adresy, přihlášení, resetovací zpráva a platný resetovací formulář ověřeny přes catch-all `@velocota.com`.
- Bezpečnost: `56/56`; `/index.php` po nasazení dává 200 pro GET/HEAD a 405 pro POST/PUT/PATCH/DELETE/TRACE.
- Prohlížeče: Chromium/Firefox/WebKit `12/12`. Failure/retry `71 testů / 1012 kontrol`. Plné aplikační a finanční E2E `8/8`.
- Zátěž: localhost `600/600`, 0 chyb, p95 401 ms; produkce `300/300`, 0 chyb, p95 1062 ms. Autentizovaný produkční souběh `48/48` prošel při concurrency 4; jednorázová přehnaná concurrency 48 odhalila jeden síťový timeout hostingu bez aplikační 5xx.
- Produkční deploy runy `31432225018` a `31432462031` oba uspěly pro stejné SHA, včetně zálohy, migrací a HTTP smoke. Opakovatelnost nasazení je potvrzena.

### Otevřené architektonické body

1. Automatizovaný návrat aplikace na starší kompatibilní release nebyl aktivován; databázové migrace jsou forward-only. Restore do izolace a opakované nasazení jsou ověřené.
2. Automatická Stripe refund/reconciliation synchronizace pro lokální `refund_required` zůstává samostatný slice. Produkční Stripe je vypnutý.
3. Bankovní checkout zůstává fail-closed do dodání platných `SHOP_BANK_*` údajů.

## Aktualizace 10. 8. 2026 — dokončený UX/CRUD průchod a produkční nasazení (`34c185b64643e531ced4ac53004b23aa11593cbb`)

### Výsledek a opravy

- Rozšířený testovací plán doběhl bez nové kritické nebo datově nekonzistentní chyby. Implementační commit `34c185b` (`Improve KIS accessibility and responsive admin UX`) je na `main` a byl úspěšně nasazen.
- Opravena administrátorská navigace přetékající na notebookových šířkách: skládá se do hamburgeru do breakpointu XXL, globální vyhledávání má bezpečnou šířku v pásmu 1400–1439 px.
- Doplněny skutečné H1 na dashboardu, tréninkovém formuláři, správě sportovců a zákaznickém kalendáři.
- Doplněny programatické popisky zákaznického kalendáře, přihlášení sportovce a žádosti o propojení osoby.
- Doplněny čitelné názvy ikonových akcí pro navigaci kalendářů, skupiny, sportoviště, lekce a rezervace.
- Doplněny názvy hustých řádkových polí ve správě sportovců, katalogu, objednávkách a měřeních tréninku; mobilní akce e-shop administrace se zalamují.
- Přidán regresní `tests/Unit/UxAccessibilityWiringTest.php` (4 testy, 35 kontrol).

### Ověření

- PHPUnit bezprostředně před commitem: `515 tests`, `4533 assertions`, vše zelené.
- Playwright acceptance: `8/8`; katalog `A01–A10 + B01–B30 = 40/40`; široký smoke `161` endpointů bez 5xx/runtime chyby.
- UX/responzivita: `49` kombinací stránek a šířek, 0 chybějících H1, 0 nepopsaných kontrol, 0 vodorovných přesahů.
- Mutační CRUD: `4/4`; trenér, skupina, trénink/vazby, sportoviště, rezervace, individuální lekce bez opakování, žádost o osobu, týdenní souhrn a privátní kalendář. SQL kontrola a automatický úklid vlastněných dat prošly.
- DB invarianty po scénářích: všech 8 kontrol má hodnotu 0 (osiřelé/duplicitní objednávky a platby, záporné součty, neplatné rezervace, duplicitní účastníci, aktivní expirace).
- `composer validate --strict`, `composer audit --locked`, `composer check-platform-reqs`, lint 473 first-party PHP souborů a idempotentní migration check/apply byly zelené.

### Produkce

- GitHub Actions run `31409725853`, job `93524592344`, závěr `success`; nasazené SHA `34c185b64643e531ced4ac53004b23aa11593cbb`.
- Ověřená záloha před aktivací: `evidence_2026-08-10_163614_62fd5e47.sql.gz`, 154 tabulek, 2 triggery, SHA-256 `08f7f32aa94847f5e77ee78f09f19913bdfe93a9127396d66cba503abf6e51c2`.
- Přihlášený produkční smoke ověřil admin dashboard, katalog, objednávky, zákaznický kalendář a osoby HTTP 200 včetně nového HTML; webhook GET vrací očekávané 405 + `Allow: POST`.

### Otevřené body

1. Produkční bankovní checkout zůstává fail-closed, dokud vlastník nedodá skutečné `SHOP_BANK_IBAN`, `SHOP_BANK_BIC`, `SHOP_BANK_ACCOUNT_LABEL` a splatnost.
2. Stripe sandbox refund proběhl skutečně u Stripe, lokální objednávka ale zůstává `refund_required`; automatická Stripe refund/reconciliation synchronizace je odložený systémový slice.
3. Aktuálně otevřená karta phpMyAdmin míří do `velocotacom` (202 tabulek), ne do KIS databáze `evidence` (154 tabulek potvrzených deploy zálohou). Nebyl proveden zápis do nesprávné DB. Po otevření `evidence` ručně deaktivovat testovací účty `verejni_uzivatele.id=5`, `treneri.id=47`, zvýšit `session_version` a prověřit případnou lekci `UXTEST postdeploy jediný termín 20260810`.
4. Historické duplicitní trenérské identity a velké seznamy (cca 10 tisíc sportovců / 1463 tréninkových formulářů) zůstávají samostatné datové a architektonické úkoly; nejsou blokátorem dokončené testovací vlny.

## Aktualizace 10. 8. 2026 — autonomní stabilizace, plné E2E a produkční deploy (`e4a5dc07f28570a0a56a9040ce7acc5578276a5a`)

### Výsledek

- Produkční kód byl stabilizován, plně otestován, commitnut, pushnut a nasazen z `main` přes GitHub Actions.
- Výchozí SHA bylo `4289785ca21c5d12bf4514714428156d5473adec`; nasazené SHA je `e4a5dc07f28570a0a56a9040ce7acc5578276a5a` (`Stabilize checkout, acceptance, and deploy readiness`).
- Cizí rozpracované soubory zůstaly nedotčené. Chybně pojmenovaný workflow `.github/workflows/deploy-productio.yml` byl pouze přesunut do karantény `var/_to_delete/deploy-productio.yml`.
- `vendor/` byl odebrán z Git indexu a přidán do `.gitignore`; fyzický lokální adresář a `vendor/autoload.php` zůstaly zachované. Produkční release instaluje závislosti z `composer.lock`.

### Opravené priority

- **P0 — Stripe:** Checkout už nenutí pouze kartu (`payment_method_types` odstraněno). Webhook nejprve ověří vazbu session, payment intent, metadata, částku a měnu. Legitimní neplacený/incomplete event uloží jako ignorovaný a vrátí HTTP 200; nepropojené nebo nesouhlasící eventy dál odmítá a transakci vrací zpět.
- **P0 — deploy:** preflight vrací strukturované `warnings[]` pro chybějící/neplatné/ne-HTTPS `APP_BASE_URL`; workflow je zvýrazní jako GitHub warning. Na produkci je ale nyní `APP_BASE_URL=https://kis.kovopraha.cz` správně nastavené, takže vlastník nemusí nic doplňovat.
- **P1 — acceptance účet:** administrátor může na localhostu přes CSRF chráněný formulář idempotentně vytvořit/resetovat `rodic@localhost.test`; operace je fail-closed mimo loopback, heslo se neloguje ani nezobrazuje, účet je aktivní/ověřený a staré relace jsou zneplatněné.
- **P2 — pozdní platba:** placený Stripe event pro zrušenou/expirovanou objednávku se k objednávce nepřipojí; kanonický přechod selže a celá lokální transakce se vrátí zpět. Regresní test pokrývá, že se nevytvoří falešná lokální platba.

### Dodatečný nalezený a opravený blokér

- Široký HTTP smoke odhalil skutečné HTTP 500 na `cviky.php` a `google_sheets_linky.php`: migrační katalog neuměl z čisté databáze vytvořit legacy tabulky `cviky`, `gs_kategorie`, `gs_linky` a `gs_link_targets`.
- Přidána idempotentní migrace `20260810003000_legacy_training_support_tables.php` pro MySQL i SQLite a rozšířen test úplnosti migračního katalogu. Po nasazení je produkční stav `current=true`, `catalog_count=52`, `pending=[]`.

### Vědomě neimplementováno

- **Notifikace po přijetí platby:** současná architektura správně používá transakční outbox a worker; nebyl přidán přímý e-mail do HTTP requestu. Navržený další slice: generický paid-order outbox s unikátním klíčem objednávka+událost/akce, enqueue uvnitř `shopOrderConfirmPaymentInTransaction` pro banku i Stripe, claim/send/retry ve workeru mimo DB transakci a stav v administraci. Chyba notifikace nesmí vrátit potvrzení platby zpět.
- **Refund/reconciliation automatika:** pozdní platba je bezpečně nepřipojená, ale externí refundace a automatické párování jsou samostatný budoucí slice. Lokální Stripe testovací objednávky proto po skutečném sandbox refundu zůstávají ve stavu `refund_required`.

### Ověření

- PHP lint: `472` first-party souborů bez chyby.
- PHPUnit: `506 tests`, `4457 assertions`, vše zelené; test spuštěn i bezprostředně před implementačním commitem.
- `composer validate --strict`, `composer audit --locked` a `composer check-platform-reqs`: vše úspěšné, žádné známé advisory.
- Migrace lokálně: pending → apply → current; opakovaný apply i check zůstaly current.
- Playwright mimo repozitář: `8/8` scénářů prošlo. Pokryty zákazník/admin, bankovní QR/IBAN/VS/splatnost/částka, kupon a členská cena, reálný Stripe sandbox Checkout kartou `4242`, dynamické metody (card/link/klarna), aktivace programu, podepsaný duplicate replay, akce klubu/velodromu, A01–A10, B01–B30, storno/expirace, skrytá platba, rate limit a úklid fixtures.
- Široký smoke: `161` kontrol, `0` selhání a `0` odpovědí 5xx/runtime warningů; guardované action/parameter endpointy vracely očekávané 400/403/404/405.
- Podepsaný replay úspěšného Stripe eventu vrátil `duplicate` HTTP 200 a nezměnil počty eventů ani auditů (`1 → 1`). Sandbox placené pokusy byly na Stripe refundovány a lokální fixtures kanonicky zrušeny; bankovní fixtures mají potvrzené lokální refundy.

### Produkční nasazení a kontrola

- GitHub Actions run `31340114330`, job `93312387822`, závěr `success`, nasazené SHA přesně `e4a5dc07f28570a0a56a9040ce7acc5578276a5a`.
- Před migracemi vznikla ověřená záloha mimo webroot: `evidence_2026-08-09_224450_2662bec0.sql.gz` + manifest, `154` tabulek, `2` triggery, `1 241 996` komprimovaných bajtů, ověřen SHA checksum.
- Aktivace release použila rsync bez `--delete`. Runner měl tři síťové timeouty, workflow proto poprvé živě použilo SSH/server fallback a ten uspěl: `{"ok":true,"http":200,"via":"curl"}`.
- Přímá produkční kontrola: `/index.php`, `/booking/prihlaseni.php`, `/booking/eshop.php` a `/booking/krouzky.php` HTTP 200; chráněné `cviky.php` a `google_sheets_linky.php` korektně 302 na login; Stripe webhook na GET korektně 405 s `Allow: POST`.

### Co zbývá vlastníkovi

1. Před live Stripe provozem dokončit KYC, vložit live klíče a založit/ověřit live webhook secret; testovací klíče se nesmí kopírovat do produkce.
2. Samostatně schválit a implementovat paid-order notification outbox slice popsaný výše.
3. Samostatně navrhnout refund/reconciliation slice pro pozdní Stripe platby a stav `refund_required`.
4. Po kontrole rozhodnout, zda trvale smazat karanténní `var/_to_delete/deploy-productio.yml`; tato stabilizace ji záměrně nemaže.

## Aktualizace 9. 8. 2026 — Stripe slice 1 NASAZENA a OVĚŘENA v test mode (commity `cb2c356`, `080f66a`, `0b1109f`, `f190498`)

- Slice 1 (`feat(payments): add disabled Stripe Checkout foundation` +
  handoff) byla po revizi pushnuta a **nasazena na produkci**. GitHub push
  protection nejprve push zablokoval kvůli ukázkovému test klíči ve
  `vendor/stripe/stripe-php/README.md:62` — vyhodnoceno jako false positive
  (klíč z oficiální dokumentace SDK) a odblokováno přes secret-scanning URL.
- **Produkce běží s vypnutým Stripe.** Ověřeno:
  `GET https://kis.kovopraha.cz/booking/stripe_webhook.php` → `405`,
  `Allow: POST`, `Cache-Control: no-store`. Kontrola metody je ve skriptu
  první, takže 405 potvrzuje jen nasazení souboru, nikoli zapnutí Stripe.
- **OPRAVA DEPLOY WORKFLOW** (`0b1109f` + `f190498`): závěrečný „HTTP smoke
  test" selhal `curl: (28) Connection timed out` — hosting Český hosting
  blokuje IP rozsahy GitHub runnerů. Deploy sám (SSH) proběhl celý; červený
  byl jen poslední krok PO aktivaci release. Smoke test má nyní
  `--retry 2 --retry-all-errors` a při selhání na SÍŤOVÉ úrovni fallback
  přes SSH na nový `bin/deploy-smoke.php` (stejný putenv bootstrap vzor jako
  `bin/deploy-preflight.php`). Skutečná HTTP chyba (např. 500) shodí deploy
  okamžitě, bez fallbacku. `DeployWorkflowContractTest` rozšířen o asserce
  na nový skript, bootstrap i retry.
  - POZOR na past: mezikrok `0b1109f` omylem obsahoval STAROU verzi workflow
    (chybná kopie z Downloads) s `php -r`, který omezený shell hostingu
    zakazuje; kontraktní test to zachytil, `f190498` to opravil. HEAD je
    v pořádku, ale `0b1109f` v historii je nepoužitelný.
- **Lokální test mode kompletně ověřen (end-to-end, sandbox
  `acct_1U2Uf22fNtqyPMYB` „Kovo Praha sandbox")**:
  - `stripe listen --forward-to http://localhost/evidencePavel/booking/stripe_webhook.php`
  - Objednávka `id=8` / `KP260809948037CDBF`, 2 630,00 CZK, karta
    `4242 4242 4242 4242`.
  - Výsledek: objednávka `status=processing`, `payment_status=paid`;
    platba `id=8` `status=paid`, `payment_source=stripe`,
    `stripe_checkout_session_id=cs_test_a1uFt0dQ…`,
    `stripe_payment_intent_id=pi_3U2dmH2fNtqyPMYB0Rw3YTY6`.
  - `checkout.session.completed` → `processed` s `payment_id=8`; ostatní
    doručené eventy (`charge.succeeded`, `payment_intent.succeeded`,
    `payment_intent.created`, `charge.updated`) → `ignored`, všechny s
    HTTP 200. Neznámé typy tedy webhook nerozbíjejí ani neopakují.
- **Lokální `config.php`**: doplněno chybějící `APP_BASE_URL` (bez něj
  `stripeIsEnabled()` vrací false, protože z něj vznikají success/cancel URL)
  a localhost-only větev se sandbox test klíči + `whsec_`. Klíče nejsou
  a nesmí být v Gitu.
- Dočasné pomocné skripty (`stripe-check.php`, `stripe-test-ucet.php`) byly
  po testu smazány. Registrace na localhostu nejde dokončit (bez SMTP se
  neodešle ověřovací e-mail) — testovací zákazník se zakládá přímo v DB
  s `email_overeno=1`.

### PŘEDPOKLADY PRO ZAPNUTÍ TEST MODE NA PRODUKCI (nezačínat bez nich)

1. **Produkční `config.php` nemá `APP_BASE_URL`.** Je to samostatná kopie na
   serveru mimo Git a bez této konstanty zůstane Stripe vypnutý i s platnými
   klíči. Doplnit `https://kis.kovopraha.cz`.
2. Klíče a webhook secret dodat mimo Git (prostředí / ignorovaný
   `config.php`), sandbox klíče NEPOUŽÍVAT pro ostré platby.
3. Webhook endpoint ve Stripe dashboardu:
   `https://kis.kovopraha.cz/booking/stripe_webhook.php`, event
   `checkout.session.completed`.
4. Klubový Stripe účet + KYC, výplatní účet, role a 2FA — stále OTEVŘENÉ,
   sandbox na ostré platby nestačí.

### DROBNOST DO NÁSLEDUJÍCÍ SLICE (revize kódu)

- `checkout.session.completed` s `payment_status != 'paid'` dnes skončí
  výjimkou → HTTP 500 → Stripe event opakuje až do vyčerpání retry okna.
  U card-only plateb prakticky nenastane, ale s asynchronními metodami
  patří takový event uložit jako `ignored`, ne shodit.
- Refund/reconciliation slice (nad existujícím `refund_required`, zejména
  pozdní platba po expiraci) zůstává předpokladem live režimu.

## Aktualizace 9. 8. 2026 — rozšířená akceptační sada B01–B30 (commit `d33ec55`)

- Na žádost vlastníka („KA1–A10 mi přidej další kompletní sadu scénářů…
  prostě vše co systém má umět") vznikla druhá, regresní sada scénářů
  `localhostAcceptanceScenariosB()` v `includes/localhost_acceptance_hub.php`:
  30 scénářů B01–B30 v osmi oblastech — Tréninky (B01–B03), Závody (B04),
  Plánování a rezervace (B05–B07), E-shop (B08–B13 včetně importu, kupónů,
  klubových cen, storna/refundace), Kroužky a programy (B14–B17), Rodina a
  účty (B18–B22), KIS a členství (B23–B27), Administrace a bezpečnost
  (B28–B30). Stejný formát i `is_file` kontrola cest jako sada A; všech
  30 odkazovaných cest existuje (žádný scénář „unavailable").
- `testovaci_scenare.php` zobrazuje sloučené A+B se společným ukládáním
  výsledků (PASS/PARTIAL/FAIL/BLOCKED), společným Markdown exportem a
  vizuálním oddělovačem před B01. **Závěrečná brána M2 zůstává vázaná
  VÝHRADNĚ na vlastnickou sadu A01–A10** — `m2FinalizationStatus()` dostává
  jen `$scenariosA`; sada B bránu neblokuje ani nenafukuje čitatele.
- Testy: `php -l` čisté, akceptační testy (LocalhostAcceptance* +
  M2Finalization) 13/114 zelené, plná sada v cloudové kopii zelená.
  `LocalhostAcceptanceHubWiringTest` nevyžadoval změnu (asserce dál platí).
- Pozn.: soubor hubu měl 252 řádků CRLF — před commitem normalizováno na
  LF, takže commit je minimální (+337/−2).
- Otevřené: `git push origin main` a případné NASADIT provádí vlastník;
  výsledky prohlídky se zapisují na localhostu (stránka je mimo loopback
  fail-closed 404), testovat lze proti produkčnímu pilotu.

## Aktualizace 8. 8. 2026 — implementace informační architektury (2 commity)

- Vlastník schválil `docs/navrh-informacni-architektury.md` a řez ho
  implementoval ve dvou commitech nad base `6806766`.
- **Commit `2f0fc98`** — 5 rychlých výher: trvalý odkaz „Veřejný portál"
  v navbaru pro každého přihlášeného (`hlavicka.php`, `booking/eshop.php`,
  `target="_blank"`); doplnění `member_charges_admin.php`,
  `kis_rollover_a06_admin.php` a `auditlog/seznam.php` (dosud 0 odkazů
  kdekoli) do Správy; podnadpisy uvnitř tehdy ještě jediné „Správy"
  (Provoz/Členové a KIS/Administrace — E-shop/Členové a KIS/Provoz/
  Nastavení a firemní evidence); oprava zavádějícího
  `_dropActive('edit_zavod_form.php')` u položky s `href="prehled_zavodu.php"`;
  sloučení 5 tlačítek zákaznického účtu do jedné nabídky „Můj účet"
  (`includes/ui_shell.php` + `assets/app-ui.css`).
- **Commit `3db00f2`** — cílová struktura: rozdělení „Správy" na **Klub**
  (správce) a **Administrace** (admin, samostatné top-level menu, ne
  vnořené) → trenér/správce/admin nyní vidí 5/6/7 položek první úrovně,
  nikdy víc než 7. `index.php` card-wall (~40 odkazů) nahrazen kompaktní
  sekcí „Rychlý přístup" (widget kopírovacího odkazu pro zákazníky +
  zkratky „Otevřít Klub"/„Otevřít Administraci"); 13 položek, které tím
  na nástěnce ztrácely jediný vstup (`cviky.php`, `duplikovat_trenink.php`,
  4× export, `google_sheets_linky.php`, `hromadne_odmeny.php`,
  `hromadne_podskupiny.php`, `oznameni.php`, `sprava_zavodu.php`,
  `formular_zavod.php` ve Vložit), přesunuto do `Vložit`/`Přehledy` se
  **stejným** efektivním oprávněním, jaké mělo dnes (ne do správcovského
  Klubu — to by byla tichá regrese pro řadové trenéry). Pouze
  `odeslat_emaily.php`, `prehled_kreditu.php`, `sprava_sportovec_obdobi.php`
  byly už dřív správcovské, ty šly do Klubu.
- **Testy:** `tests/Unit/SharedUiShellTest.php` má 2 nové regresní testy —
  natvrdo zapsaná množina 84 stránek (83 z `hlavicka.php`/`index.php` před
  řezem + `auditlog/seznam.php`) musí zůstat pokrytá součtem obou souborů
  po řezu (ověřeno: 86 unikátních cílů, žádná z 84 nechybí, žádný odkaz
  nesměřuje na neexistující soubor). Tři starší „wiring" testy
  (`ClubEventWiringTest`, `ShopCatalogPublicationWiringTest`,
  `ShopIdentityAdminWiringTest`) natvrdo kontrolovaly přítomnost stránky
  v `index.php` — upraveny na `hlavicka.php` (+ `eshop_admin.php`/
  `admin_dashboard.php`), protože nástěnka záměrně přestala být druhou
  navigací. Plná sada 495/4305 (base 493/4306: +2 nové testy, −3 asercí ze
  tří upravených wiring testů, +2 z nových), 462 first-party PHP souborů
  bez chyby syntaxe.
- **Browser:** ověřeno jako `localhost-admin` (7 položek, Klub i
  Administrace naplněné, `member_charges_admin.php` i
  `kis_rollover_a06_admin.php` se vykreslí bez chyby) a jako dokumentovaný
  `trener1`/`heslo456` (`docs/vyvojarsky-pruvodce.md`) — 5 položek, Klub
  i Administrace správně chybí, `cviky.php`/`hromadne_odmeny.php` dostupné
  z Přehledů, `sprava_segmentu.php`/`hromadne_podskupiny.php` skryté (jejich
  `canAccess()` je pro tento účet false — potvrzuje, že podmínky reagují na
  živé oprávnění, ne natvrdo). „Můj účet" ověřeno na `booking/eshop.php`
  (stránka bez `bootstrap.bundle.min.js`) — živý klik otevřel/zavřel menu
  správně po opravě CSS (viz níže). Konzole 0, žádné neočekávané requesty
  ve všech třech relacích.
- **Nález za běhu, opraveno v rámci commitu `3db00f2`:** `<details>` menu
  „Můj účet" bylo viditelné i zavřené — `position: absolute` na `<ul>`
  potlačilo nativní skrývání zavřeného `<details>` v testovaném enginu.
  Opraveno explicitním `display: none` / `.acct-menu[open] > ul { display: block; }`
  místo spoléhání na nativní chování.
- **Nález za běhu, MIMO ROZSAH tohoto řezu, nahlášen samostatně:**
  `auditlog/seznam.php` má prapůvodní (nesouvisející) chybu neshody
  sloupců — dotazuje `l.trener_id`/`l.cas`/`l.entita`/`l.data`, skutečné
  sloupce `ucto_audit_log` jsou `uzivatel_id`/`datum`/`tabulka`/
  `zaznam_id`/`detail` — PDOException na každém načtení. Stránka byla do
  teď zcela neodkazovaná, takže na to nikdo nenarazil; nový odkaz v
  Administraci to odhalil. Nahlášeno jako samostatný task, nesahá na to
  tento řez.
- Stav .git: nalezen `.git/HEAD.lock` (0 B, neaktivní proces ověřen přes
  `Get-Process`/`ps aux`), přesunut do `var/_to_delete/HEAD.lock.<ts>`
  podle zavedené konvence adresáře. `vendor/*` a další již dříve
  nekomitované soubory (`.github/workflows/deploy-productio.yml`,
  `kis-shoptet-import.ps1`, `var/_to_delete/`) zůstaly nedotčené a
  necomitnuté.
- Žádné oprávnění nebylo změněno. Žádný soubor nebyl přejmenován ani
  přesunut. Produkce ani vzdálený Git se nezměnily (`git push` neproveden).

## Aktualizace 8. 8. 2026 — návrh informační architektury (read-only)

- Nový dokument [`docs/navrh-informacni-architektury.md`](../navrh-informacni-architektury.md):
  mapa všech 83 stránek odkazovaných z `hlavicka.php`/`index.php` (66+71
  unikátních cílů, ověřeno množinově přes `comm`/`sort -u`, ne ručním
  počítáním) + 64 dalších nalezených funkčních stránek mimo navigaci (mj.
  `auditlog/seznam.php` zcela bez odkazu odkudkoli, `member_charges_admin.php`
  a `kis_rollover_a06_admin.php` jen kontextově) a přehled podezřelých
  duplicitních/mrtvých souborů (`index-backup.php`, `index3.php`, 6×
  `vypis_vykazu (N) - kopie.php` aj.).
- Navrhuje cílovou navigaci (max 7 top-level položek pro trenéra/správce/
  admina, rozdělení dnešní 39položkové „Správy" bez druhého patra na
  Klub/Administraci se 4 podskupinami), zeštíhlení nástěnky `index.php`
  a trvalý viditelný odkaz na veřejný portál z navbaru — dnes chybí
  jakýkoli stálý most mezi přihlášeným trenérem a veřejnou částí.
- Čistě analytický řez: žádný PHP soubor nezměněn, nic nepřejmenováno ani
  nepřesunuto na disku. Base `5c99509`. Produkce se nezměnila.

## Aktualizace 6. 8. 2026 — PRVNÍ PRODUKČNÍ NASAZENÍ kis.kovopraha.cz

- Workflow „Nasadit produkci“ doběhl celý: záloha DB, release mimo webroot,
  migrace (kopie legacy schématu 2.20.2 povýšena celým katalogem 50 migrací)
  a aktivace. https://kis.kovopraha.cz živě odpovídá — homepage portálu bez
  chyb, ověřeno nezávislým fetch z Cowork cloudu.
- Data: kovoprahacz10 = kopie kovoprahacz09 pořízená přes phpMyAdmin
  (mysql/mysqldump CLI hosting nepovoluje). Kopie zároveň splnila roli
  cutover rehearsal — migrace nad reálnými produkčními daty prošla.
- DŮLEŽITÝ NÁLEZ: v kovoprahacz09 žije vedle evidence i samostatná aplikace
  miniresult (16 tabulek vč. miniresult_athletes/registrations s FK na
  sportovci). Z kopie kovoprahacz10 byly tyto tabulky smazány (fail-closed
  ownership guard zálohy je odhalil). Pro budoucí ostrý cutover evidence
  platí: stará DB musí zůstat živá, dokud se miniresult nepřepojí — vazba
  cizí aplikace na sportovci je otevřený bod cutover plánu.
- DB_HOST na tomto hostingu je 127.0.0.1; heslo DB uživatele bylo změněno
  vlastníkem (prošlo chatem → po pilotu zrotovat spolu s FTP heslem).
- Stará evidence data.kovopraha.cz zůstává nedotčená a AUTORITATIVNÍ.
  kis.kovopraha.cz je pilot nad kopií dat; ostrý cutover je samostatně
  schvalovaný krok. Vlastníkova prohlídka A01–A10 zůstává otevřená — nyní
  ji lze provést i nad produkčním pilotem.
- Kanonické poznatky o hostingu: docs/thinline-deploy-runbook.md.

## Aktualizace 6. 8. 2026 — příprava produkce kis.kovopraha.cz

- Vlastník autorizoval nasazení na novou subdoménu kis.kovopraha.cz přes
  GitHub Actions. Lokální `main` je pushnutá na `origin/main`; deploy workflow
  je parametrizovaný přes Variables KIS_* a běží v environment `production`
  s ručním potvrzením NASADIT.
- Server má tři živě ověřené zvláštnosti: chroot bez funkčního $HOME
  s nezapisovatelným kořenem, omezený shell blokující argumenty PHP skriptů
  I externí env proměnné (jediný kanál je soubor → putenv bootstrap vzor) a
  MariaDB se strict mode, který XAMPP nemá. Kanonický popis a runbook je
  v docs/thinline-deploy-runbook.md — deploy vlákna ho čtou před změnou workflow.
- CI odhalilo a opravilo reálnou fixture chybu (treneri bez AUTO_INCREMENT ve
  strict mode). Preflight na serveru živě vrací {"ok":true,"php":"8.2.32"};
  config.php s pepperem a novou DB kovoprahacz10 je nahraný mimo git.
- Zbývá před prvním nasazením: kopie kovoprahacz09 → kovoprahacz10 přímo na
  serveru (slouží zároveň jako cutover rehearsal; deploy odmítne zálohu prázdné
  DB) a ruční spuštění workflow s NASADIT. Stará evidence zůstává nedotčená a
  autoritativní až do samostatně schváleného ostrého cutoveru.
- Otevřené bezpečnostní resty vlastníka: změna FTP hesla, rotace DB hesla po
  zprovoznění (obojí prošlo chatem), výhledově EOL MariaDB 10.3/Debian 10.

## Aktualizace 6. 8. 2026 — statická kontrola kompatibility s produkční MariaDB 10.3

- Vlastník 2026-08-05 v phpMyAdmin ověřil produkční DB server: MariaDB
  `10.3.39` na Debian 10 (`replikant3544`), klientské připojení bez SSL v
  privátní síti `10.5.x.x`. Je to dnes nejstarší a jediné dosud netestované
  prostředí projektu (localhost `10.4.32`, CI `11.4`) — zapsáno do řádku
  „produkční runtime" v poslední známém důkazním snapshotu níže spolu s
  krátkou poznámkou o otevřeném vlastnickém rozhodnutí ohledně EOL Debian 10
  a nepatchované MariaDB 10.3 od roku 2023; žádná akce vůči produkci ani
  produkční DB se neprovedla (ani jen připojení).
- Statická kontrola `migrations/*.php` (49 souborů), `includes/auto_migrace.php`,
  `includes/migration_runner.php`, `db.php` a zbytku first-party PHP (mimo
  `vendor/`, přes 470 souborů) na konstrukce nedostupné v MariaDB 10.3
  (`INSERT...RETURNING` 10.5.0, `JSON_TABLE`/`JSON_ARRAYAGG`/`JSON_OBJECTAGG`
  10.5–10.6, `SKIP LOCKED`/`NOWAIT`/`LATERAL` 10.6.0, CTE/window funkce/
  `INTERSECT`/`EXCEPT`/sekvence/temporální tabulky/`INVISIBLE` sloupce — ty
  všechny jsou už pod podlahou 10.3 — a nekonstantní `DEFAULT (výraz)` od
  10.2.1) — **bez nálezu**. Jediná reálná `CHECK (...)` constraint
  v produkčním MySQL/MariaDB kódu (`training_roster_bridge.php:49`) je
  kompatibilní, protože MariaDB CHECK vynucuje už od 10.2.1 (druhý nalezený
  `CHECK` v `club_event_notifications.php:78` je jen v SQLite testovací větvi
  a proti MariaDB se nikdy neprovede). utf8mb4 kolace jsou v celém repozitáři
  vždy explicitní (`utf8mb4_unicode_ci`/`utf8mb4_general_ci`) a beze změny
  napříč 10.3/10.4/11.4. Nic se preventivně nepřepisovalo; plný rozbor včetně
  zdrojů je v [`docs/mariadb-10.3-compatibility-2026-08-06.md`](../mariadb-10.3-compatibility-2026-08-06.md).
- `.github/workflows/tests.yml`, job `mariadb-smoke`, dostal `strategy.matrix`
  se dvěma verzemi (`10.3` produkční podlaha, `11.4` dosavadní CI);
  `services.mariadb.image` nově interpoluje `${{ matrix.mariadb-version }}`
  místo pevné `mariadb:11.4`. Workflow nebyl spuštěn (zakázáno zadáním).
  Žádný ze čtyř smoke kroků není z principu blokovaný na 10.3 — všechny
  používají jen SQL ověřené jako kompatibilní výše.
  `tests/Unit/DeployWorkflowContractTest.php` je jediná kódová změna tohoto
  řezu — kontrakt testoval natvrdo `image: mariadb:11.4`, aktualizován na
  novou maticovou strukturu.
- Lokálně (10.3 na XAMPP není k dispozici, poctivě přiznáno — skutečný běh na
  10.3 proběhne až v CI matici po autorizovaném spuštění) proběhly všechny
  čtyři MariaDB smoke skripty proti izolované MariaDB `10.4.32`: dětský
  přístup OK, KIS hobby přechod OK, záloha 100 tabulek OK, sportovní import
  review OK (5 měření/1 v1, 3 výsledky/1 v1, 7 legacy textových řádků/2
  rozpoznatelné, 8 inventurních záznamů, 8 nálezů). Po běhu `SHOW DATABASES`
  potvrdilo úklid všech izolovaných testovacích databází a nedotčenou
  `evidence`.
- Ověření: `php -l` na 471 first-party PHP souborů bez chyby (beze změny
  počtu — žádná PHP kompatibilní oprava nebyla potřeba). Celá sada je
  493/4278 PHPUnit, 0 selhání (base 493/4277 + 1 nová assertion z opraveného
  workflow kontraktu). Migrace beze změny (50/50). Produkce, produkční DB a
  vzdálený Git se v tomto řezu vůbec nepřipojily ani nezměnily.
- Base před tímto řezem: `9e8d9d0` (MariaDB smoke pro M3.5d). Řez commituje
  jen `docs/mariadb-10.3-compatibility-2026-08-06.md`,
  `.github/workflows/tests.yml`, `tests/Unit/DeployWorkflowContractTest.php`
  a tento soubor.

## Aktualizace 5. 8. 2026 — MariaDB smoke pro M3.5d sportovní import review

- M3.5d živě odhalil dvě reálné vady, které SQLite testy nezachytily: legacy
  `zavod_sportovec` v produkčním schématu nemá surrogate PK a dotaz na `zs.id`
  spadl až nad skutečným MySQL/MariaDB (opraveno v `cfc3c93`). Chyběla vrstva,
  která by tento typ driftu odhalila automaticky a opakovaně, ne až živou
  prohlídkou.
- Nový `bin/sports-review-smoke.php` (localhost/CI-only, po vzoru
  `tests/Support/*MariaDbSmoke.php`) na izolované testovací MariaDB databázi
  vytvoří minimální ale reálné schéma: `mereni_zaznamy` s PK, `zavod_sportovec`
  výslovně BEZ surrogate PK, `zavody`, `treninky`, `trenink_mereni`,
  `zavod_mereni` a `mereni`. Legacy řádky se vloží před spuštěním skutečné
  migrace `20260805050000_sports_measurement_contract`; teprve po jejím
  `up()`/`verify()` přibudou dva řádky v kontraktu v1 — stejně jako v realitě,
  migrace nic zpětně nepřevádí ani neodhaduje.
- Syntetická data jsou označená `LOCALHOST` a neobsahují žádnou konkrétní
  osobu (`sportovec_id` zůstává v celé fixtuře `NULL`). Skript spustí
  `sportsImportReview()` i `sportsDataQualityInventory()`, ověří klíčové počty
  (měření, výsledky závodů, legacy textová tabulka, inventura zdrojů a nálezů)
  a explicitně zkontroluje, že zakódovaný JSON výstup neobsahuje
  `sportovec_id`, `jmeno` ani `prijmeni`.
- Cílová databáze je omezená regexem na `evidence_sports_review_smoke_test`
  (volitelně s `_[a-z0-9_]+` příponou přes env `EVIDENCE_SPORTS_REVIEW_SMOKE_DB`)
  a host je napevno `127.0.0.1` — skript se nemůže připojit k databázi
  `evidence` ani k žádnému vzdálenému serveru. Testovací databáze se vždy
  smaže ve `finally`, i při selhání.
- Zapojeno do `.github/workflows/tests.yml`, job `mariadb-smoke`, jako čtvrtý
  krok vedle stávajících tří smoke skriptů (workflow nebyl spuštěn, jen
  upraven).
- `includes/sports_import_review.php` a admin stránky zůstaly beze změny —
  úkolem tohoto řezu bylo doplnit chybějící testovací vrstvu, ne opravovat kód;
  živý běh navíc žádnou další vadu neodhalil.
- Ověření: lokální běh proti izolované MariaDB 10.4.32 na XAMPP vypsal
  `MariaDB sports review smoke OK — 5 measurements (1 v1), 3 race results
  (1 v1), 7 legacy text rows (2 recognized/5 ambiguous), 8 inventory records,
  8 findings`; testovací databáze byla po běhu smazána, `evidence` zůstala
  nedotčená (ověřeno `SHOW DATABASES`). `php -l` na nový skript čistý a 470
  first-party PHP souborů (vše mimo `vendor/`, včetně nového skriptu) bez
  chyby syntaxe. Celá sada je beze změny 493/4277 PHPUnit, 0 selhání — nový
  skript není součástí `composer test`, spouští se samostatně jako ostatní
  MariaDB smoke skripty. Migrace beze změny (50/50). Produkce, vzdálený Git a
  databáze `evidence` se nezměnily.
- Base před tímto řezem: `6312d04` — doc-only integrační záznam Cowork control
  nad `a90de13` (oprava Bootstrap hlavičky), objevený jako drift až v průběhu
  tohoto řezu (viz Metadata níže). Netýká se žádného kódu, který tento smoke
  ověřuje. Řez commituje jen `bin/sports-review-smoke.php`,
  `.github/workflows/tests.yml` a tento soubor.

## Aktualizace 5. 8. 2026 — doplnění chybějící Bootstrap hlavičky na 4 stránkách

- `cfc3c93` (M3.5d) opravil chybějící vlastní `<head>` s Bootstrap 5.3.3 CSS
  na `sports_data_quality_admin.php` a `sports_import_review_admin.php`.
  Systematický grep porovnal všechny stránky s `hlavicka.php` proti těm,
  které mají vlastní odkaz na `bootstrap.min.css`, a našel čtyři další
  postižené stránky: `provozni_prehled_admin.php`,
  `family_weekly_summaries_admin.php`, `member_charge_reminders_admin.php`
  a `member_charges_admin.php`. Bez vlastní hlavičky se vykreslovaly zcela
  bez stylů, protože `appUiAssets()` volaný z `hlavicka.php` načítá jen
  bootstrap-icons a `assets/app-ui.css`, nikdy samotný Bootstrap.
- Všechny čtyři dostaly stejnou standardní hlavičku podle šablony v
  `CLAUDE.md` (`<!DOCTYPE html>`, `<head>` s Bootstrap 5.3.3 CSS,
  `bootstrap.bundle.min.js` před `</body>`); obsah stránek se nezměnil.
- `tests/Unit/SharedUiShellTest.php` má nový regresní test
  `testEveryHlavickaIncludingPageHasOwnBootstrapCss`: nad celým stromem
  (mimo `vendor/`, `tests/`, `migrations/`, `scripts/`) hlídá, že každá
  stránka s `hlavicka.php` má i vlastní `bootstrap.min.css`.
- Browser přes `localhost-admin` na všech čtyřech stránkách potvrdil
  skutečně načtený Bootstrap stylesheet (`document.styleSheets` s
  pravidly, ne jen přítomný `<link>`), konzoli 0 a žádné odeslání dat.
- Tento řez dobíhal souběžně se sdílenou pracovní kopií, do které za běhu
  přibylo M3.5e (`188407c`) — měnilo `includes/sports_import_review.php`,
  `sports_import_review_admin.php`,
  `tests/Integration/SportsImportReviewTest.php` a
  `docs/sports-data-quality.md`. Tento řez se jich nedotkl a commituje jen
  svých 5 vyjmenovaných souborů nad již přijatým `188407c`.
- Ověření: 493/4277 PHPUnit (bezprostředně po M3.5e 492/4276; tento řez
  přidává +1 test/+1 assertion, 0 selhání), 471 first-party PHP souborů
  bez chyby syntaxe, migrace beze změny (50/50). Produkce ani vzdálený Git
  se nezměnily.

## Aktualizace 5. 8. 2026 — M3.5e rozpoznávání historické tabulky měření

- Historická volnotextová tabulka `mereni` (7 řádků na localhostu), kterou
  M3.5a i M3.5d záměrně vynechaly, má nový deterministický rozpoznávací
  kontrakt v `includes/sports_import_review.php`. Uznané vzory jsou výhradně
  „<číslo> km“, „<číslo> m“, striktní čas `MM:SS(.mmm)`/`HH:MM:SS(.mmm)` a
  „<číslo> min“ (mezi číslem a jednotkou smí, ale nemusí být jedna mezera,
  např. „200m“ i „200 m“); cokoli jiné — rozsahy, „cca“, více hodnot nebo
  prázdná hodnota — je nejednoznačné. Kontrakt nic nepřevádí, nic neodhaduje
  a neukládá žádnou normalizovanou hodnotu, jen klasifikuje pro pozdější
  ruční rozhodnutí. Úplný popis je v `docs/sports-data-quality.md`.
- `sports_import_review_admin.php` nahradil dosavadní jednořádkovou
  souhrnnou kartu plnohodnotnou sekcí: řádků celkem, rozpoznatelné,
  nejednoznačné a tabulka všech řádků s badge verdiktem a důvodem u obou
  polí (vzdálenost i čas). Vazba na trénink se uvádí jen jako
  „trénink <datum>“; `sportovec_id` ani jméno se nikam nevrací. Stránka
  zůstává no-store a sama o sobě nemá formulář ani vstup — jediný form/input
  na vykreslené stránce patří sdílené navigaci `hlavicka.php` (odhlášení a
  globální hledání), stejně jako na všech ostatních obrazovkách aplikace.
- Živý localhost snapshot: všech 7 historických řádků je klasifikováno jako
  nejednoznačných (0 rozpoznatelných) — např. vzdálenost „200m“ je sama o
  sobě rozpoznaná, ale čas u stejného řádku („30s“) ani u jiných řádků
  („14.52“ bez dvojtečky, „por“) striktnímu formátu neodpovídá, takže se
  žádný z řádků nepřevádí ani neodhaduje.
- Testováno na izolované sqlite fixture se 7 syntetickými řádky (2 plně
  rozpoznatelné, 5 nejednoznačných — rozsah, „cca“ prefix, více hodnot,
  obě pole prázdná, desetinný čas bez dvojtečky) a samostatným maticovým
  testem přímo nad rozpoznávacími funkcemi včetně negativních případů
  (jednotka velkými písmeny, dvě mezery, jiná jednotka, prázdný/mezerový
  vstup). JSON výstup je ověřen bez `sportovec_id`, `jmeno` nebo konkrétního
  jména.
- Ověření (lokálně, PHP 8.2.12/PHPUnit 11.5.56, izolovaně jen s tímto řezem
  nad `cfc3c93`): 492/4276 PHPUnit (base 491/4217 → +1 test/+59 assertions,
  0 selhání), 470 first-party PHP souborů beze změny počtu a bez chyby,
  žádná nová migrace ani závislost. Browser přes `localhost-admin` potvrdil
  novou sekci se 7 řádky a verdikty, 0 rozpoznatelných/7 nejednoznačných a
  0 konzolových chyb.
- Base před M3.5e je `cfc3c93` (M3.5d). Vzdálený repozitář ani produkce se
  nemění.

## Aktualizace 5. 8. 2026 — M3.5d read-only příprava importu sportovních dat

- Nová admin-only stránka `sports_import_review_admin.php` je no-store, bez
  formuláře i vstupu a nic neimportuje. Ukazuje pokrytí kontraktu v1 v měřeních
  a výsledcích závodů a konkrétní seznam nejednoznačných legacy řádků k ručnímu
  rozhodnutí před budoucím jednorázovým importem.
- Klasifikace v `includes/sports_import_review.php` je deterministická a
  fail-closed: vzdálenost bez výslovné jednotky, čas mimo striktní formát, RPE
  mimo 1,0–10,0 a chybějící výslovný stav výsledku závodu jsou nejednoznačné;
  striktně parsovatelný čas/RPE bez vzdálenosti se pouze počítá jako
  deterministicky převoditelný. Nic se nepřevádí a stav se neodhaduje.
- Výstup neobsahuje jména ani identifikátory sportovců; technické hodnoty měření
  jsou zobrazené záměrně kvůli ručnímu rozhodnutí. Delší seznam se zkracuje
  hlasitě s uvedeným celkovým počtem. Historická volnotextová tabulka `mereni`
  zůstává mimo seznam do schválení formátu a jednotek.
- Živý browser průchod odhalil a řez opravil dvě reálné vady: legacy tabulka
  `zavod_sportovec` nemá surrogate PK (řádky se nyní odkazují přes `zavod_id`
  + deterministické pořadí v závodě) a stránky M3.5a i M3.5d neměly vlastní
  `<head>` s Bootstrap CSS, takže se vykreslovaly bez stylů. Obě sportovní
  admin stránky teď mají standardní hlavičku dle šablony projektu.
- Browser nad živým localhostem ověřil: přihlášení `localhost-admin`, odkaz v
  menu Správa, stylované vykreslení, měření v1 0/0, dvě legacy závodní účasti
  (#8 a #9, důvod „chybí výslovný stav“), historická tabulka 7 řádků,
  0 formulářů, 0 vstupů a 0 konzolových chyb. Dočasný diagnostický skript byl
  z webrootu odstraněn.
- Session běžela v Cowork cloudu: testy proběhly na identické kopii repozitáře
  (PHP 8.4/PHPUnit 11.5.56), lokální DB se nemutovala. Ověření: 491/4217
  PHPUnit (base 487/4161), 4 nové testy, 470 first-party syntaxí bez chyby,
  žádná nová migrace ani změna závislostí (poslední audit 0 je historický;
  sandbox nemá přístup k advisories).
- Base před M3.5d je `e07fc25`. Vzdálený repozitář ani produkce se nemění.

## Aktualizace 5. 8. 2026 — M3.5c normalizovaný zápis sportovních dat

- Všechny čtyři toky vytvoření a editace tréninku/závodu používají jediný
  `includes/sports_measurement_input.php`. Lokální duplicitní parsery byly odstraněné.
- Formuláře vyžadují výslovnou jednotku vzdálenosti `m`/`km`, čas ve striktním
  formátu a číselné RPE 1,0–10,0. Neplatný vstup selže před DB transakcí.
- Nové řádky ukládají původní čitelná pole i normalizované metry, milisekundy,
  RPE a `sports-measurement-v1`. Ve výstupech se zobrazuje uložená jednotka;
  legacy vzdálenost bez jednotky zůstává čitelná v dosavadních kilometrech.
- Testovaný `sportsRaceResultInput()` je pouze fail-closed příprava budoucího
  jednorázového importu. M3.5c žádný ostrý KIS/e-shop import, převod historie ani
  zápis do produkce nespouští.
- Browser ověřil nový formulář tréninku: volbu jednotky, nápovědu striktního času,
  nulovou konzolovou chybu a žádné odeslání dat. Ověření: 487/4161 PHPUnit,
  469 first-party syntaxí, migrace 50/50, backup smoke 100 tabulek a audit 0.
- Base před M3.5c je `7211dfd`. Vzdálený repozitář ani produkce se nemění.

## Aktualizace 5. 8. 2026 — M3.5b sportovní datový kontrakt

- Kontrakt `sports-measurement-v1` nově vyžaduje výslovnou jednotku `m`/`km`,
  normalizuje vzdálenost na metry, striktní čas na milisekundy a RPE na číslo
  1,0–10,0. Závod rozlišuje `entered`, `finished`, `dns`, `dnf` a `dsq`.
- Migrace `20260805050000_sports_measurement_contract` je čistě aditivní: přidává
  nullable sloupce do `mereni_zaznamy` a `zavod_sportovec`, bez jediného backfillu.
  Localhost je 50/50; produkce se nezměnila.
- Read-only inventura M3.5a ukazuje počet záznamů v kontraktu v1 a staré řádky
  označuje jako legacy. Nadále nevrací osoby ani naměřené hodnoty.
- KIS a e-shop budou později jednorázově importované. Produkční tréninky se
  převezmou ze stávající Evidence; nejednoznačné historické hodnoty se neodhadují.
- Další řez M3.5c napojí společný validátor na nový zápis a kontrolované importy.
  Legacy formuláře v tomto řezu zůstaly beze změny.
- Ověření: 477/4107 PHPUnit, 466 first-party PHP syntaxí, migrace 50/50,
  izolovaný MariaDB backup smoke 100 tabulek a Composer audit bez nálezu. Browser
  potvrdil metriku v1, dvě starší závodní účasti, 0 formulářů/vstupů a konzoli 0.

## Aktualizace 5. 8. 2026 — M3.5a kvalita sportovních dat

- `sports_data_quality_admin.php` je admin-only, no-store a výhradně read-only.
  Nemá formulář ani výběr osoby a nevrací jména, ID, poznámky nebo naměřené hodnoty.
- Inventura agreguje pět existujících zdrojů: tréninky a docházku, strukturovaná
  měření, starší textová měření, výsledky závodů a zátěžové testy. Nedostupný
  zdroj se nikdy nevydává za nulu.
- Live localhost snapshot: 456 tréninků, 422 vazeb docházky, 239 sportovců s
  docházkou, 0 strukturovaných měření, 7 historických měření, 2 závodní účasti a
  6 zátěžových testů. Pět z pěti zdrojů je dostupných.
- Browser ověřil pět zdrojových karet, nulový počet formulářů a vstupů a nulové
  chyby konzole. Plná sada je 466/4075; migrace zůstávají 49/49.
- Dokumentace výslovně potvrzuje, že Evidence, e-shop a KIS jsou jedna aplikace;
  modulové názvy jsou historické a funkční členění, nikoli hranice nasazení.
- Další řez M3.5b musí nejprve definovat jednotky, formát času, stupnici RPE,
  výsledkové stavy DNS/DNF a ochranu zátěžových testů. Historii nelze převádět
  odhadem. Produkce se nezměnila.

## Aktualizace 5. 8. 2026 — dokončení M3.2

- Týdenní rodinný souhrn má výchozí opt-out, dobrovolné zapnutí a odhlášení
  jedním krokem v `booking/sportovni_prehled.php`.
- Migrace `20260805040000_family_weekly_summaries` přidává preference,
  idempotentní snapshot frontu a audit. Unikátní účet + počátek týdne brání
  duplicitám; vypnutí ruší všechny neodeslané zprávy.
- `family_weekly_summaries_admin.php` umí pouze na localhostu připravit frontu a
  uložit jednu zprávu do chráněného souborového outboxu. Produkční transport ani
  CRON nejsou implementované.
- Lokální outbox je sdílený s připomínkami členských plateb přes
  `includes/local_message_outbox.php`.
- Browser ověřil zapnutí → jednu zprávu → lokální uložení → vypnutí, bez zbylé
  fronty a bez konzolové chyby. Plná sada je 462/4026, syntaxe 457 PHP souborů,
  migrace 49/49, MariaDB backup smoke 98 tabulek a Composer audit bez nálezu.
  Produkce se nezměnila.
- Další implementovatelný řez je M3.5a: read-only inventura kvality sportovních
  dat, jednotek, úplnosti a oprávnění bez zdravotních predikcí.

## Aktualizace 5. 8. 2026 — sjednocení uživatelského rozhraní

- Evidence, KIS, e-shop a rezervace používají společný základ
  `includes/ui_shell.php` + `assets/app-ui.css` + `assets/app-ui.js`.
- Všech 127 aktivních PHP HTML stránek používá buď `hlavicka.php`, nebo
  `appUiAssets()`; regresi hlídá `tests/Unit/SharedUiShellTest.php`.
- Hlavní veřejné vstupy používají jednu navigaci a přizpůsobují volby rodiči,
  sportovci i trenérovi. POST formuláře mají společný stav načítání a ochranu
  proti dvojímu odeslání.
- `prehled_skupiny.php` byl bez změny funkčnosti převeden z historického CP1250
  do UTF-8, aby mohl sdílený základ bezpečně používat.
- Produkce nebyla změněna. Podrobný kontrakt je v `docs/shared-ui-foundation.md`.

Tento soubor je stručný obnovitelný stav řídicího tasku. Architekturu ani
roadmapu neduplikuje; odkazuje na jejich kanonické dokumenty. Všechny provozní
hodnoty jsou historické, dokud je nový řídicí task živě neověří.

## Metadata

- Aktualizováno: 2026-08-06, Europe/Prague
- Poslední přijatý implementační HEAD před statickou kontrolou MariaDB 10.3:
  `9e8d9d0` (MariaDB smoke pro M3.5d). Řez je čistě dokumentační + CI matice;
  nesahá na sports/admin stránky ani `includes/sports_import_review.php`.
- Poslední přijatý implementační HEAD před M3.5e: `cfc3c93` (M3.5d).
- Poslední přijatý implementační HEAD před opravou Bootstrap hlavičky: `188407c` (M3.5e).
- Integrační kontrola řídícího vlákna (2026-08-05): HEAD `a90de13`, strom čistý,
  oba paralelní commity přijaty; plná sada 493/4277 nezávisle potvrzena nad
  sloučeným stromem v Cowork cloudu. Duplicitní číslo 105 v integrační frontě
  opraveno na 105 (M3.5e) + 106 (Bootstrap hlavičky); zaznamenáno v `6312d04`.
- Poslední přijatý implementační HEAD před MariaDB smoke test řezem: `6312d04`.
  Tento commit (Cowork control) přistál mezi počátečním resume auditem tohoto
  řezu (base `a90de13`) a jeho commitem; mění výhradně tento soubor, žádný
  kód ani schéma, takže neruší nic, co smoke skript ověřuje. Zaznamenáno zde
  místo tichého přepsání, jak vyžaduje protokol driftu.
- Větev `main`, upstream `origin/main`, lokálně ahead 52 / behind 0 proti
  poslednímu známému `origin/main` (`aead0be`, bez živého fetch). Vzdálený
  repozitář ani produkce se v tomto řezu nezměnily; `git push` neproveden.
- Localhost DB je 50/50 (historická hodnota; M3.5e je čistě čtecí klasifikace
  volného textu a nepřidává žádnou migraci). Produkční workflow je ruční a
  produkce se nemění.
- Repozitář: `C:\xampp\htdocs\evidencePavel`
- Programová brána: F0 – červená
- Aktivní integrační větev: `main`; technická část M1 je dokončená a M2.1
  zahájilo provozní localhost pilot. Vlastníkova prohlídka A01–A10 zůstává
  otevřenou produktovou branou.
- Auth kódový tip před tímto handoff commitem: `9977b4dfc3f2f6aab775825d0bdf9b629e61e217`;
  auth přírůstek tvoří
  `a3c2239` (revokace + limiter), `10c2cf9` (atomická rezervace + SSO abort) a
  `9977b4d` (HMAC pepper + sjednocené pořadí zámků)
- Původní base: `58ec8ec985d447dfe901481ac8bb24b944b03d08`
- Produkční deploy bez výslovného souhlasu: zakázán
- Produkční DB změny bez výslovného souhlasu: zakázány
- Aktuální produktová autorita pro localhost:
  [10 – Milník M2](10-milnik-m2-provozni-pilot.md); cílem je provozní pilot nad
  integrovanou Evidencí, e-shopem a členskou evidencí. Fio auto-confirm, Stripe,
  wallet a ostrý import zůstávají blokované.
- Navazující technické řezy řídí [12 – Milník M3](12-milnik-m3-clenska-hodnota.md);
  jejich produkční brána se neotevře před vypořádáním A01–A10.
- Poslední dokončená funkční akce je řez M3.5e. Historická volnotextová
  tabulka `mereni` (7 řádků) má deterministický rozpoznávací kontrakt: uznává
  jen „<číslo> km/m/min“ a striktní čas, cokoli jiné včetně prázdné hodnoty
  je nejednoznačné a nic se nepřevádí ani neodhaduje.
  `sports_import_review_admin.php` nahradil souhrnnou kartu plnohodnotnou
  sekcí se všemi 7 řádky, verdiktem a důvodem u obou polí, bez `sportovec_id`
  a bez jmen. Ověření izolovaně nad `cfc3c93` je 492/4276 PHPUnit (base
  491/4217, +1 test/+59 assertions, 0 selhání) a 470 first-party syntaxí beze
  změny počtu; browser živě potvrdil 7 řádků s verdikty (0 rozpoznatelných/7
  nejednoznačných) a konzoli 0.
  Předchozí dokončená funkční akce je řez M3.5d. Administrátor má read-only
  přípravu jednorázového importu: pokrytí v1 a konkrétní seznam nejednoznačných
  legacy řádků s důvody, bez jediného převodu, odhadu, formuláře nebo osobního
  údaje. Ověření 491/4217 PHPUnit a 470 syntaxí proběhlo na identické kopii
  repozitáře v Cowork cloudu; browser nad živým localhostem potvrdil obě
  sportovní admin stránky, dvě legacy účasti k rozhodnutí a konzoli 0.
  Předchozí dokončená funkční akce je řez M3.5c. Všechny čtyři toky vytvoření a
  editace tréninku/závodu používají společný fail-closed parser a dual-write
  původních + normalizovaných hodnot `sports-measurement-v1`. Historická data,
  produkce a vzdálený repozitář zůstaly beze změny.

  Předchozí dokončená funkční akce je řez M3.5b. Databáze a společný validátor
  získaly aditivní `sports-measurement-v1`; historická data zůstala beze změny a
  read-only přehled ukazuje pokrytí v1.

  Předchozí dokončená funkční akce je řez M3.5a. Administrátor má agregovaný
  read-only přehled pěti sportovních zdrojů bez osobních a naměřených hodnot.
  Browser ověřil pět karet, žádný formulář ani vstup a nulovou konzolovou chybu;
  plná sada je 466/4075 a databáze zůstává 49/49. Produkce se nezměnila.

  Předchozí dokončená funkční akce je řez M3.2. Uživatel si týdenní souhrn
  dobrovolně zapne nebo jedním krokem vypne; idempotentní fronta a audit jsou
  napojené na kanonickou rodinnou agendu. Administrátor má pouze localhostový
  souborový outbox a produkční e-mail zůstává nepřítomný. Browser ověřil celý tok,
  plná sada je 462/4026, syntaxe 457 souborů, migrace 49/49, backup smoke 98
  tabulek a audit 0.

  Předchozí dokončená funkční akce: `12c2300` dokončuje technický řez M3.4.
  Administrátor má read-only provozní přehled peněz, kapacit, přihlášek a výjimek,
  který pouze odkazuje do existujících auditovaných obrazovek. Browser ověřil
  přihlášení lokálním syntetickým administrátorem, čtyři sekce, stav 0 signálů a
  nulovou konzolovou chybu. Bezpečnostní commit `6655a39` předtím uzavřel dva nové
  HIGH nálezy: přílohy jsou mimo webroot a bezpečnostní odkazy ignorují Host
  hlavičku. Plná sada je 452/3951, syntaxe 450 souborů, migrace 48/48 a audit 0.
  Produkce se nezměnila.

  Předchozí dokončená funkční akce: `63c8ec1` přidává první část M3.3. Přihlášený
  účet vidí za validovaný rok skutečně uhrazené členské předpisy a e-shopové
  položky všech právě schválených profilů. Zdroje a měny se nesčítají dohromady,
  čekající, zrušené, vratkové a cizí řádky se vynechávají a stránka výslovně
  nevystupuje jako účetní ani daňový doklad. Browser ověřil rok 2026 s e-shopovou
  úhradou 1 530 CZK a vynechaným čekajícím předpisem, prázdný rok 2025 a odmítnutý
  rok 2027. Plná sada je 438/3897, syntaxe 438 souborů, migrace 48/48, backup
  smoke 95 a audit 0. Produkce se nezměnila.

  Předchozí dokončená funkční akce: `82d41ac` přidává první část M3.2. Přihlášený
  rodič vidí prostý text týdenního souhrnu a může listovat po striktně validovaných
  sedmidenních intervalech v omezeném rozsahu. Náhled používá kanonickou rodinnou
  agendu, nepřijímá ID osoby, nic nezapisuje a výslovně nemá odběr ani transport.
  Browser ověřil prázdný aktuální týden a 12.–18. 8. s jednou akcí a jednou
  splatností, bez cizí osoby a konzolové chyby. Plná sada je 434/3872, syntaxe
  434 souborů, migrace 48/48, backup smoke 95 a audit 0. Produkce se nezměnila.

  Předchozí dokončená funkční akce: `1510c20` zahajuje M3.1 rodinným programem.
  Přihlášený rodič vidí nejbližších 30 dní tréninků, přihlášených akcí,
  rezervací a splatností. Funkce je read-only a volá kanonický rodinný
  kalendářový model, takže nepřijímá ID osoby a zachovává živé oprávnění vazeb.
  Browser nad `rodic@localhost.test` zobrazil dvě oprávněné položky, žádnou cizí
  osobu a nula konzolových chyb. Plná sada je 431/3847, syntaxe 433 souborů,
  migrace 48/48, backup smoke 95 a audit 0. Produkce se nezměnila.

  Předchozí dokončená funkční akce: `9a04c3c` přidává do A01–A10 závěrečnou
  bránu M2. Automaticky a read-only ověřuje dostupnost všech cest, checksumy
  migračního katalogu a úplnost základních demo identit/nabídky; výsledek
  odděluje od vlastníkova PASS 10/10 a blokujících připomínek. Browser živě
  potvrdil techniku 3/3, cesty 10/10, migrace 48/48, demo data OK, vlastníkovu
  akceptaci 0/10 a nulové blokátory bez konzolové chyby. Plná sada je 429/3833,
  syntaxe 433 souborů, backup smoke 95 tabulek a audit 0. Produkce se nezměnila.

  Předchozí dokončená funkční akce: `6d290cc` přidává druhou localhost-only,
  admin+CSRF a výslovně potvrzenou akci. Jednu čekající připomínku uloží výhradně
  do chráněného souborového outboxu, přepne ji na `sent` a u `claim` i `sent`
  audituje konkrétního trenéra. Browser ověřil Čeká 1 → Odesláno 1, náhled a
  následnou obnovu na Čeká 1 pro vlastníkovu zkoušku. Plná sada je 427/3819,
  syntaxe 431 souborů, migrace 48/48, backup smoke 95 tabulek a audit 0.
  Produkce ani skutečný e-mail se nepoužily.

  Předchozí dokončená funkční akce: `5843f70` přidává localhost-only admin+CSRF
  tlačítko s výslovným potvrzením. Opakovatelně vytvoří nebo obnoví syntetický
  předpis `LOCAL-REMINDER-001`, opt-in účtu `rodic@localhost.test` a právě jednu
  čekající připomínku, přičemž audit ukládá administrátora u předpisu, opt-in i
  zprávy. Browser ověřil přechod 0→1, náhled předmětu a stav Čeká; žádný
  transport se nespustil. Ukázka zůstává na localhostu připravená k prohlídce.
  Plná sada je 425/3799, syntaxe 431 souborů, migrace 48/48, backup smoke 95
  tabulek a audit 0. Produkce se nezměnila.

  Předchozí dokončená funkční akce: `68e1199` přidává no-store administrátorský
  náhled přesně uloženého textu připomínky a explicitní localhost-only
  `--transport=local-outbox`. Testovací transport uloží JSON do ignorovaného
  `var/member-charge-reminder-outbox`, skutečný mail nevolá a produkční host
  odmítne. Celý `var/` je nově přes Apache nedostupný (ověřeno HTTP 403).
  Autentizovaný browser ověřil administrační stránku; CLI nad prázdnou frontou
  zpracovalo 0 zpráv a nic neodeslalo. Plná sada je 423/3781, syntaxe 429
  souborů, migrace 48/48, backup smoke 95 tabulek a audit 0. Produkce ani CRON
  se nezměnily.

  Předchozí dokončená funkční akce: `66b4241` přidává administrátorský přehled
  fronty připomínek plateb se stavy čeká/zpracovává se/selhalo/odesláno/zrušeno.
  Ruční opakování je admin-only, POST+CSRF, vyžaduje důvod a potvrzení, zprávu
  pouze vrací do fronty a audit ukládá typ i ID aktéra. Znovuzařazení nesmí
  obejít odhlášení uživatele, uhrazený předpis ani rozpracovanou, odeslanou či
  zrušenou zprávu. Browser ověřil autentizovanou prázdnou frontu na localhostu.
  Plná sada je 421/3761, syntaxe 429 souborů, migrace 48/48, backup smoke 95
  tabulek s ownership `2026-08-05.2` a audit 0. Produkce se nezměnila a
  produkční transport ani CRON stále nejsou zapnuté.

  Předchozí dokončená funkční akce: `29e3d5d` přidává dobrovolné
  připomínky blížící se splatnosti členských předpisů. Uživatel volí
  vypnuto nebo 3/7/14 dní. Fronta je unikátní pro účet a předpis, auditovaná,
  kontroluje aktuální vazbu i stav `pending`, omezuje účet na jednu zprávu za
  20 hodin, blokuje dva souběžné workery a po pěti neúspěšných pokusech končí
  jako `failed`. Odkaz v e-mailu nemá ID osoby, předpisu ani bearer token. CLI
  odděluje `--generate` a `--send`; produkční CRON není nastaven. Browser ověřil
  zapnutí a vypnutí, localhost zůstal vypnutý a nebyl odeslán skutečný e-mail.
  Plná sada je 418/3728, syntaxe 428 souborů, migrace 47/47, backup smoke 95
  tabulek s ownership `2026-08-05.2` a audit 0. Produkce se nezměnila.

  Předchozí dokončená funkční akce: `004e4a6` přidává soukromý rodinný
  ICS feed do sportovního přehledu rodiče. Odkaz používá 256bitový náhodný
  token, v databázi je pouze jeho SHA-256 otisk a kontrolní konec; celý odkaz se
  v účtu ukáže jen bezprostředně po vytvoření nebo rotaci. Vytvoření, rotace a
  revokace jsou auditované. Každé načtení znovu vyhodnotí aktivní ověřený
  účet a aktuální schválené vazby na osoby; URL nepřijímá ID osoby. Feed
  obsahuje cílené tréninky aktivních soupisek, přihlášené termíny akcí,
  rezervace a splatnosti členských předpisů. Browser ověřil vytvoření i
  jednorázové zobrazení; HTTP smoke platné ICS 200 a tentýž odkaz po revokaci
  404. Plná sada je 410/3662, syntaxe 423 souborů, migrace 46/46, backup smoke
  92 tabulek s ownership kontraktem `2026-08-05.1` a Composer audit 0. Produkce
  se nezměnila.

  Předchozí dokončená funkční akce: `3aa39f8` přidává anonymní veřejný ICS feed
  nad zveřejněnými plánovanými tréninky, otevřenými termíny klubových akcí a
  aktivními veřejnými hodinami velodromu. Feed používá stabilní UID, UTC převod
  z Europe/Prague, CRLF, escapování a skládání řádků; nečte osoby, docházku,
  rezervace ani interní popisy. Odkaz je veřejně dostupný ze tří souvisejících
  stránek. HTTP ověřil 200 + `text/calendar` + `.ics` attachment a 400 pro
  neplatný rozsah; browser ověřil všechny tři odkazy. Plná sada je 403/3617,
  syntaxe 416 souborů, migrace 45/45 a Composer audit 0. Funkční audit je
  vytříděný v dokumentu 11; personalizovaný kalendář byl v tomto historickém
  kroku ještě otevřený a dokončil jej až `004e4a6`. Wallet, externí integrace a
  prediktivní funkce zůstávají za samostatnými branami. Produkce se nezměnila.

  Předchozí dokončená funkční akce: `5829171` zpřístupňuje členské předpisy v
  read-only pohledu rodiče, omezeného sportovního účtu a administrátora.
  Rodičovský pohled je omezen aktivní schválenou vazbou, sportovní pohled
  odvozuje osobu pouze z revokovatelného přístupového účtu a administrace nemá
  žádnou mutující akci. Browser ověřil všechny tři obrazovky a odkaz z KIS
  centra; plná sada je 398/3510, syntaxe 412 souborů, migrace 45/45 a Composer
  audit 0. Auditní snapshoty a produktové příležitosti jsou indexované v
  `docs/AUDITY.md`. Produkce se nezměnila.

  Předchozí dokončená akce: `281fcd0` uzavírá HIGH nález druhého kontrolního auditu.
  Ownership kontrakt zálohy `.9` zahrnuje všech 12 chybějících trvalých tabulek
  M2.3/M2.3g a klubových cen. Generický test porovnává kontrakt se všemi trvalými
  `CREATE TABLE` v migračním katalogu a MariaDB CI skutečně vytvoří komprimovanou
  zálohu a ověří manifest. Izolovaný smoke prošel s 90 tabulkami. Současně byl
  odstraněn float převod starého platebního signálu. Plná sada je 393/3496,
  syntaxe 409 souborů, migrace 45/45 a audit 0. Produkce se nezměnila.

  Předchozí dokončená akce: `7c8b444` dokončuje M2.3g auditovaný localhost přenos
  členských předpisů. Přenos vyžaduje přesnou shodu osoby, čerstvý paritní fingerprint,
  admina, důvod a potvrzení; běží transakčně a je idempotentní. Uhrazený předpis
  vytváří samostatnou historickou platbu. Rollback před odstraněním pouze vlastních
  cílů kontroluje předpis, platbu i auditní historii a při driftu fail-closed zastaví.
  Browser run #13 ověřil 2/2 předpisy + 1 platbu + nulové blokátory a návrat na
  0/2 + 0 plateb se dvěma auditními událostmi. Plná sada je 391/3430, syntaxe 408
  souborů, migrace 45/45 a audit 0. Další akce: zdrojová akceptace reprezentativního
  anonymizovaného exportu a celý cutover rehearsal nad testovací kopií.

  Předchozí dokončená akce: `d69ee4f` dokončuje M2.3f cílový model a bezpečný staging
  členských předpisů. `member-charge-v1` odděluje předpis od skutečné platby,
  cílové tabulky jsou idempotentní a auditovatelné a import vyžaduje stabilní ID
  předpisu i částku. Run #12 měl 2 stagingové předpisy a 2 čekající na přenos.

  Předchozí dokončená akce: `95693a2` dokončuje M2.3e uložený cutover paritní
  report. Každý nový běh atomicky porovnává osoby, aktivní členství, snapshot
  soupisek a agregované platební signály s cílovou Evidencí. Report používá jen
  pořadové reference, pevné kategorie, počty a fingerprinty; neobsahuje jména,
  KIS ID ani peněžní hodnoty. Run #9 pravdivě ukazuje tři blokátory: dvě nové
  demo osoby a chybějící cílový kontrakt jednotlivých členských platebních
  předpisů. Browser ověřil report a sandbox 2/2 → 0/2. Plná sada je 379/3332,
  syntaxe 401 souborů, migrace 43/43 a audit 0. Další akce: definovat cílový model
  členských předpisů a potvrdit hlavičky na reprezentativním anonymizovaném exportu.

  Předchozí dokončená akce: `2bcb346` dokončuje M2.3d stabilní field/external-ID
  kontrakt. `kis-import-field-v1` vyžaduje interní KIS ID ve všech třech exportech,
  drží jej odděleně od UCI licence, umí podle něj spojit i bezejmennou platbu a
  fail-closed blokuje chybějící, neplatné, duplicitní nebo rozporné identity.
  Non-PII report neobsahuje jména ani hodnoty KIS ID. Upload nyní archivuje všechny
  tři zdroje mimo webroot a kanonický execute vyžaduje připravený field kontrakt.
  Browser nad run #8 potvrdil 2/2, sandbox 2/2 a rollback 0/2; run #7 bez kontraktu
  nelze znovu aplikovat. Plná sada je 377/3308, syntaxe 398 souborů, migrace 42/42
  a audit 0. Další akce: potvrdit aliasy hlaviček na reprezentativním anonymizovaném
  exportu a uzavřít úplný paritní report; produkční cutover není autorizovaný.

  Předchozí dokončená akce: `5caa850` dokončuje izolovaný M2.3c sandbox promote/rollback.
  Akce je jen pro localhost administrátora, vyžaduje CSRF, potvrzení, důvod a čerstvý
  fingerprint. Promote je transakční, idempotentní a ukládá pouze neprůhledné položky
  a audit do `kis_import_sandbox_*`; kanonické osoby, soupisky, platby ani objednávky
  nemění. Rollback deaktivuje sandbox položky a funguje i při pozdějším driftu preview.
  Browser nad run #7 potvrdil 2/2 aktivní + 1 auditní událost a rollback 0/2 + 2;
  první průchod živě odhalil a opravil chybějící CSRF helper. Plná sada je 369/3254,
  syntaxe 396 souborů, migrace 41/41 a audit 0. Navázal na něj M2.3d field kontrakt.

  Předchozí dokončená akce: `26076ba` dokončuje M2.3b integritu uloženého KIS preview.
  Každý nový běh atomicky ukládá úplnou klasifikaci, verzi kontraktu, bezpečný JSON
  a stabilní fingerprint nezávislý na ID běhu. Chybějící raw archiv, neúplná identita,
  konflikt, nejednoznačnost a duplicate target jsou fail-closed blokátory. Report
  používá pouze neprůhledné pořadové reference a pevné důvody. Synchronizační centrum
  jej ukazuje a exportuje s `no-store`; stále neexistuje žádný promote ani zápis do
  profilů. Localhost demo run #7 má 2/2 klasifikované řádky, nula blokátorů a opakovaný
  seed vrací stejný run/fingerprint. Plná sada je 364/3197, syntaxe 392 souborů,
  migrace 40/40 a audit 0. Další akce: uzavřít finální external-ID/field kontrakt,
  potom implementovat výhradně izolovaný testovací promote a kompenzační rollback.

  Předchozí dokončená akce: `875c9e3` připravuje skutečnou vlastníkovu/Cowork bránu.
  Každá karta A01–A10 na localhost rozcestníku ukládá PASS/PARTIAL/FAIL/BLOCKED,
  důležitost a pozorované/očekávané chování. Zápis je admin+CSRF, omezený enumy a
  délkami, chráněný proti symlinku a zamčený; lokální JSON je ignorován Gitem.
  Markdown export je určen k ruční kontrole a vědomému commitu bez automaticky
  načtených hesel či osobních dat. Browser potvrdil save/reload/souhrn a návrat na
  čistých 0/10. Kořenový `CLAUDE.md` už neoznačuje aplikaci jako submodule Velocoty
  a nový `docs/CURRENT_STATE.md` dává Coworku krátký aktuální vstup. Plná sada je
  358/3156, syntaxe 388 souborů, migrace 39/39 a Composer audit 0. Produkce se
  nezměnila. Další konkrétní akce: vlastník nebo Cowork projde A01–A10 a uloží
  výsledky; blokující a důležité připomínky se potom vypořádají.

  Předchozí dokončená akce: `ef5ec21` přijímá jediný živě potvrzený technický nález
  nezávislé Cowork revize. CI má nový samostatný job s MariaDB 11.4 a `pdo_mysql`,
  který spouští existující child-access a KIS transition/idempotency smoke skripty na
  izolovaných testovacích databázích. Oba smokes prošly také lokálně. Cowork report je
  zachován v `docs/AUDIT-M2-AI-SIMULACE.md`, ale jeho úvod obsahuje validační dodatek:
  tvrzení o zastaralém handoffu, chybějícím detailu/klubové ceně a login chybách byla
  vyvrácena proti skutečnému stromu. Plná sada je 356/3142, syntaxe 386 souborů,
  migrace 39/39 a Composer audit 0. Produkce ani vzdálený Git se nezměnily. Další
  konkrétní akce zůstává závěrečná vlastníkova brána M2.6.

  Předchozí dokončená akce: `4ce0f17` uzavírá provozní průchod A10. Browser u demo
  sportovce spojil auditované změny přístupu, objednávek, soupisek a přihlášek
  včetně A08 registrace a jejího auditovaného resetu; aktéři a důvody odpovídají
  uloženým zdrojům. Nalezený auditní šum opravil seed: demo heslo sportovce resetuje
  jen při skutečné změně hashe. Dva po sobě jdoucí běhy zachovaly počet historických
  resetů 26 → 26. Plná sada je 355/3135, syntaxe 386 souborů, migrace 39/39 a Composer
  audit 0. Produkce ani vzdálený Git se nezměnily. Další konkrétní akce je závěrečná
  vlastníkova brána M2.6 a sepsání jeho připomínek.

  Předchozí dokončená akce: `6ae75c1` uzavírá A08 – přihlášení oprávněného dítěte na
  událost cílenou na dvě překrývající se soupisky. Rodičovské UI nabídlo dítě právě
  jednou a browser vytvořil jedinou potvrzenou přihlášku. Databáze současně doložila,
  že podmínku splnilo přes U15 i dráhovou soupisku. Seed nyní aktivní A08 přihlášku
  demo rodiče nemaže, ale auditovaně stornuje, takže je další průchod čistý a historie
  zůstává zachovaná. Plná sada je 355/3133, syntaxe 386 souborů, migrace 39/39 a
  Composer audit 0. Produkce ani vzdálený Git se nezměnily. Další konkrétní akce je
  A10 a potom závěrečná brána M2.6.

  Předchozí dokončená akce: `03774db` uzavírá A07 – plánovaný trénink ze soupisek,
  skutečnou docházku a sportovní přehled. Běžný trenér může zaevidovat pouze vlastní
  plán, hlavní trenér libovolný dostupný plán a datum nelze proti plánu změnit.
  Historický snapshot cílových soupisek a očekávaných členů se atomicky kopíruje ke
  skutečnému tréninku, zatímco docházku trenér vždy ručně potvrdí. Localhost průvodce
  rozlišuje přítomné podle plánu, chybějící a neočekávané. Browser vytvořil trénink
  528 z plánu 2, potvrdil počty 1/1/0/0 a stejný trénink zobrazil omezenému účtu
  sportovce. Následný seed zachoval historii a připravil čistý plán 3. Plná sada je
  354/3130, syntaxe 384 souborů, migrace 39/39 a Composer audit 0. Produkce ani
  vzdálený Git se nezměnily. Další konkrétní akce je A08 a potom A10 a závěrečná brána.

  Předchozí dokončená akce: `e67eed8` přidává klubové ceny e-shopu podle soupisek.
  Veřejnost vždy vidí veřejnou cenu a výzvu k přihlášení. Přihlášený účet získá
  nejvýhodnější cenu z aktuálních soupisek svých schválených osob; konkrétní cena
  produktu má uvnitř jedné soupisky přednost před procentní nebo pevnou slevou
  kategorie. Administrace je auditovaná, košík i kupón používají stejnou serverovou
  cenu a checkout ukládá neměnný snapshot. Změna pravidla zneplatní starý fingerprint.
  Localhost seed připravil desetiprocentní demo cenu produktu 1 pro `LOCALHOST U15
  2026`. HTTP průchod potvrdil veřejnou výzvu, rodičovskou klubovou cenu i admin
  přehled. Plná sada je 353/3120, syntaxe 383 souborů, migrace 39/39 a Composer audit
  0. Produkce ani vzdálený Git se nezměnily. Další konkrétní akce zůstává A07 –
  propojení plánované soupisky se skutečnou docházkou a sportovním přehledem.

  Předchozí dokončená akce: `dde0f3e` uzavírá provozní průchod A06. Nový
  localhost-only admin průvodce ukáže v jednom náhledu věkový postup U15→U17,
  přenos dráhové disciplíny a U13→U15 se zachovanou individuální výjimkou.
  Souhrnný i dílčí fingerprint se před zápisem znovu ověřují. Browser provedl
  tři auditní běhy: 3 přesuny, 2 výjimky, právě jedno aktivní členství v každém
  cíli a po dokončení žádné další tlačítko zápisu. Následný seed odstranil pouze
  syntetické A06 běhy a vrátil všechny tři kroky do čekajícího náhledu, takže A07
  začíná na čistých datech. Plná sada je 349/3081, lint 379, migrace 38/38 a audit
  0. Produkce ani vzdálený Git se nezměnily. Další konkrétní akce: A07 – propojení
  plánované soupisky se skutečnou docházkou a sportovním přehledem.

  Předchozí dokončená akce: `efa1ca8` zpřístupňuje veřejný portál bez registrace a master účet
  používá jeden login pro trenérskou Evidenci i zákaznický e-shop. Veřejné jsou
  katalog, detail produktu, kroužky/události, velodrom, rezervační kalendář a nový
  bezpečný rozvrh zveřejněných tréninků; košík, přihláška a rezervace vyžadují
  přihlášení. Veřejný rozvrh nevybírá osoby, docházku, interní popis ani poznámky.
  Trenérský login propojí nebo vytvoří právě jeden zákaznický profil, veřejný login
  umí stejnou trenérskou roli obnovit a reset hesla aktualizuje obě role. Localhost
  master `localhost-admin` je propojen bez duplicity. Browser potvrdil košík,
  objednávky, kroužky i návrat do trenérského dashboardu bez druhého loginu.
  Plná sada je 346/3063, lint 375, migrace 38/38 a Composer audit 0. Produkce ani
  vzdálený Git se nezměnily. Další konkrétní akce: provozní scénář A06.

  Předchozí dokončená akce: `8647bce` uzavírá provozní průchod A05. Reset seedu
  ponechá jedinou kanonickou identitu přechodu a bezpečně archivuje starý syntetický
  duplikát včetně jeho aktivních vazeb. Browser prošel preview, věkovou kontrolu,
  povinný důvod a auditovaný přechod stejné osoby do U17 2027. Nový náhled nad už
  aktivním cílem je nyní pravdivý no-op bez dalšího členství nebo auditního běhu.
  Demo je znovu vrácené před přechod: 1 osoba, 1 zdrojový kroužek, 0 cílových U17.
  Zaměřená sada prošla 9/80, plná 338/3025, lint 367, migrace 37/37 a audit 0.
  Produkce ani vzdálený Git se nezměnily.

  Předchozí dokončená akce: `18deb9c` uzavírá provozní průchod A02. Omezený účet
  sportovce má souhrn vlastních tréninků, soupisek, událostí a plateb, návrat na
  společnou homepage a srozumitelné české stavy, typy událostí i datumy. Browser
  potvrdil, že podvržené `sportovec_id=999999` nezmění zobrazenou osobu a homepage
  zachová sportovní režim bez trenérských práv. Zaměřená sada prošla 11/73, plná
  337/3014, first-party lint 367 souborů, migrace 37/37 a Composer audit je 0.
  Produkce ani vzdálený Git se nezměnily.

  Předchozí dokončená akce: `25830e1` přidává společnou homepage sloučeného projektu.
  Nepřihlášený návštěvník má na kořenovém `index.php` přímé cesty do e-shopu,
  kroužků/událostí, velodromu, rodinného účtu, sportovního účtu a trenérské Evidence.
  Rodičovská a sportovní relace mají své bezpečně oddělené přímé vstupy. Přihlášený
  trenér si zachovává celý původní dashboard, ale nahoře má čtyři rychlé volby:
  zadat trénink, KIS/soupisky, objednávky a veřejný portál. Veřejný HTTP průchod je
  200 se správným titulkem; přihlášený browser ověřil všechny čtyři zkratky a
  konzole byla bez chyby. Plná sada prošla 336/3004 a Composer audit je 0.
  Produkce ani vzdálený Git se nezměnily.

  Předchozí dokončená akce: druhý browser řez M2.6 proběhl bez potřeby změny kódu.
  Placená událost odmítla duplicitní aktivní přihlášku; její objednávka
  `KP2608047D5C1C6050` po stornu uvolnila kapacitu na 3/3 a samostatná vratka
  `LOCALHOST-M26-EVENT-REFUND` uzavřela platbu. Bezplatný velodrom po stornu vrátil
  kapacitu 3/3. Placený velodrom `KP260804813B7DE01C` držel kapacitu 0/1 již před
  platbou, po syntetické úhradě se potvrdil, storno ji vrátilo na 1/1 a vratka
  `LOCALHOST-M26-VELO-REFUND` uzavřela platbu. Zboží `KP26080452226EF4BA` rezervovalo
  sklad 157/MOD2 z 2 na 1 a nezaplacené storno jej vrátilo přesně na 2. Browser
  konzole byla bez chyb a MariaDB potvrdila všechny koncové stavy. Produkce ani
  vzdálený Git se nezměnily.

  Předchozí dokončená akce: `4090bdc` opravuje regresi nalezenou prvním browser
  průchodem M2.6. Bezpečnostní rotace dříve změnila odvozený A05 hash, seed vytvořil
  další osobu a následně narazil na unikátní login sportovce. A05 se nyní hledá
  stabilním interním localhost e-mailem, nový veřejný token zůstává kryptograficky
  náhodný a sportovní login upřednostní svého stávajícího vlastníka. Staré seedované
  guardian vazby A05 se auditovaně odvolají, protože A05 je administrační scénář,
  ne třetí dítě rodiče z A01. Seed proběhl dvakrát se stejnými ID. Browser potvrdil
  A01 se dvěma dětmi a A03+A04: objednávka `KP2608040ECDA87D7D`, QR NEPLATIT,
  auditovaná syntetická platba a právě jedna aktivní účast i školní soupiska.
  Plná sada před závěrečným seed cleanupem byla 334/2977; finální zaměřený test
  prošel 2/25. Produkce ani vzdálený Git se nezměnily.

  Předchozí dokončená akce: `fdbe30c` zahajuje M2.6 čerstvou ověřenou localhost
  zálohou mimo webroot. Ownership kontrakt `.8` nově zahrnuje také
  `password_reset_tokens`; manifest potvrzuje 125 tabulek a 2 triggery a SHA-256
  souboru `evidence_2026-08-04_104915_2404dfc4.sql.gz` je
  `a7382f999126595fbbabffc99c7f5e926c0a134600fcf8659f167c949a0174a9`.
  Zaměřený backup kontrakt prošel 2 testy / 41 assertions. Produkce ani vzdálený
  Git se nezměnily.

  Předchozí dokončená akce: `29f6029` uzavírá tři zbývající MEDIUM body externí
  revize. Placená událost nyní rozlišuje měnu varianty a akce a po změně varianty
  na jinou měnu selže uzavřeně. CSV export odmítne neplatné UTF-8 dříve, než by
  mohlo obejít neutralizaci tabulkových vzorců. Fio parser přijímá oficiální
  `RRRR-MM-DD+02:00`, prosté datum i kompatibilní epoch formát a neplatné datum
  odmítá. Plná sada je 333/2971, 367 PHP lintů, migrace 37/37 a Composer audit 0.
  Produkce ani vzdálený Git se nezměnily.

  Předchozí dokončená akce: M2.5 technicky uzavírá `7c1490e`. Rodičovský účet i
  samostatný účet sportovce mají společnou žádost o obnovu bez enumerace. Token je
  v DB pouze hashovaný, platí 60 minut, lze jej použít právě jednou a nová žádost
  zneplatní starší. U sportovce se odkaz doručí pouze ověřenému `self`/`guardian`
  účtu a vazba se znovu kontroluje při použití; zrušený guardian proto reset
  nedokončí. Změna hesla zvýší `session_version` a odhlásí staré relace. Trenérská
  role a tabulka oprávnění se nově načítají při každém požadavku, takže odebrání
  platí okamžitě a chyba autorizace selže uzavřeně. Localhost má 37/37 migrací,
  plná sada 330/2944, 367 PHP lintů, Composer audit 0 a HTTP smoke formulářů 200
  se stejnou odpovědí pro neznámý účet. `edc6c62` navíc pouze na localhostu po
  platné žádosti zobrazí testovací fragmentový odkaz, takže lze celý reset projít
  i bez lokálního mail serveru; produkční větev jej nikdy nezobrazuje. Produkce
  ani vzdálený Git se nezměnily.

  Předchozí dokončená akce: bezpečnostní audit legacy vrstvy byl porovnán s živým
  HEAD a potvrzené nálezy uzavřel `65a0433`. Všechny veřejné odkazy sportovců mají
  po jednorázové rotaci kryptograficky náhodný token, plaintext hesla trenérů byla
  na localhostu převedena na moderní hash a aplikace je už nepřijímá. Doplněny jsou
  CSRF hranice, oprávnění downloadu, redakce auditních dat, obecné chybové odpovědi,
  bezpečné inline JSON a Apache defense-in-depth. Root `.htaccess` už existoval;
  opačné tvrzení auditu bylo zastaralé. Katalog je 36/36, plná sada 323/2903,
  361 PHP souborů bez chyby, Composer audit bez nálezu a localhost HTTP ověřil
  hlavičky i 403 v upload cestě. Produkce ani vzdálený Git se nezměnily. Podrobný
  záznam je v [bezpečnostním auditu](../security-audit-2026-08-04.md). Navazující
  `b8ecdaa` uzavírá také enumeraci účtu při veřejné registraci: nový i existující
  e-mail dostává stejnou neutrální odpověď. Plná sada je po tomto řezu 324/2907.

  Předchozí dokončená akce: revize Claude Code byla porovnána s aktuálním HEAD.
  Její H1 vycházel ze starého snapshotu a je již uzavřený: backup kontrakt `.7`
  obsahuje `kis_import_source_artifacts`. Potvrzené H2, H3 a M1 uzavírá `b5f3f3f`:
  stornovaný a refundovaný kroužek lze znovu koupit se zachováním historie a
  databázově jedinou aktivní účastí, legacy rezervace velodromu bez
  `active_token` stále zabírá kapacitu a jeden košík nemůže překročit kapacitu
  placené události více dětmi. Nová migrace prošla reálnou localhost MariaDB;
  katalog je 35/35, plná sada 318/2774, 356 PHP souborů bez chyby a dependency
  audit bez nálezu. Produkce ani vzdálený Git se nezměnily.

  Předchozí dokončená akce: M2.4b přidalo explicitní neměnný rozsah kupónů pro
  zboží, kroužky, placené události a velodrom. Výchozí i migrační pravidlo je
  pouze zboží; služba se nezlevní bez samostatného zaškrtnutí administrátorem.
  Minimum a sleva se počítají ze způsobilého mezisoučtu a redemption ukládá jeho
  hodnotu i snapshot rozsahu. Browser potvrdil zamítnutí `LOCAL10` na samotný
  kroužek, 10% slevu na zboží, následný úklid košíku a čitelný administrační
  formulář. Katalog je 34/34, plná sada 315/2707, 355 PHP souborů bez chyby a
  dependency audit bez nálezu.

  Předchozí dokončená akce: M2.4a přidalo bezpečný zákaznický detail produktu.
  Katalog nyní sdružuje varianty, detail vrací jen aktivní publikované zboží a
  používá pouze schválený veřejný název a souhrn. Importovaný nedůvěryhodný HTML
  popis se nezobrazuje. Obrázky musí být validní HTTPS URL bez přihlašovacích
  údajů a načítají se bez referreru. Kroužková nabídka ukazuje jen svou aktivní
  variantu a nepřebírá zavádějící obrázek původního importovaného zboží.
  Přihlášený localhost browser prošel katalog, detail dvou variant, vložení do
  košíku, jeho vyčištění a samostatný detail kroužku. Katalog migrací je 33/33,
  plná sada 313/2664, 354 PHP souborů bez chyby a dependency audit bez nálezu.

  Předchozí dokončená akce: M2.3a přidalo neměnný archiv zdrojových KIS souborů
  mimo webroot. Metadata obsahují verzi kontraktu, SHA-256, velikost a storage
  key; preview běh může uložit kanonický manifest s fingerprintem. CLI je pouze
  localhost, defaultně dry-run a zápis vyžaduje `--confirm-archive`. Syntetický
  MariaDB průchod vytvořil právě jeden soubor a jeden DB řádek; druhé spuštění
  vrátilo `created_file=false` a `created_record=false`. Katalog je 33/33,
  plná sada 308/2635, 350 PHP souborů bez chyby a dependency audit bez nálezů.

  Předchozí dokončená akce: M2.1 přidalo auditovaný administrační CSV export
  účastníků jedné klubové akce. Endpoint je admin-only, POST+CSRF, soubor používá
  kontrakt `m2.event-participants.v1`, neutralizuje tabulkové vzorce a neobsahuje
  hesla ani celé texty souhlasů. Export zapisuje do auditu pouze počet řádků a
  rozpad stavů. Plná sada má 303 testů / 2603 assertions, Composer audit
  0 advisories; ověřeno bylo 345 PHP souborů a localhost browser potvrdil
  tlačítko, stažení a
  audit `export_participants`.

  Předchozí dokončená akce: placená klubová událost cílená na více soupisek je
  zapojená do standardního shop order/payment lifecycle. Checkout ukládá cenu,
  příjemce, souhlas, storno a eligibility snapshot; stav `payment_pending` drží
  kapacitu, ruční úhrada aktivuje přihlášku právě jednou a storno/expirace ji
  auditovaně uvolní. Localhost browser prošel objednávku `NEPLATIT`, QR, ruční
  syntetickou úhradu a potvrzenou účast. Seed nyní obsahuje také placenou
  událost, U13 → U15 → U17, dráhu, silnici a rollover výjimku. Katalog migrací
  je 32/32, plná sada 299/2547, 339 first-party PHP lintů a Composer audit bez
  advisories. Ověřená post-change záloha má 123 tabulek a 2 triggery.

  Historie: K3 má administrační frontu neodeslaných e-mailů a
  auditované bezpečné ruční retry (`4cd0eae`). K4 má první checkout přihlášeného
  účtu pro aktivní `goods`: košík, ochranu proti tiché změně ceny, neměnný
  snapshot, idempotentní objednávku, transakční rezervaci skladu, bankovní
  předpis s lokálním QR/SPD a ruční auditované potvrzení platby (`5750dd0`).
  Navazující `1763531` přidává auditované storno, transakční a právě-jednou
  vrácení skladu, bezpečný stav `refund_required` a tok příprava → připraveno →
  osobní výdej. Celkem 186/1339 testů, 257 PHP lintů, Composer audit bez
  advisories a izolovaný MariaDB průchod dokončením i stornem prošly.
  `f8b12a4` doplňuje databázově vlastnicky filtrovaný zákaznický seznam a detail
  a auditované jednorázové potvrzení celé bankovní vratky `refunded` s referencí.
  Celkem 187/1376 testů, 259 PHP lintů a izolovaný MariaDB tok vratky i IDOR
  hranice prošly. `135803a` přidává neměnné pevné/procentní kupóny, platnost,
  minimum, celkový limit, audit aktivace, serverový checkout a snapshot slevy.
  Celkem 190/1425 testů, 262 PHP lintů a izolovaný MariaDB limit i finanční
  snapshot prošly. `f23a332` přidává read-only Fio import v shadow režimu,
  neměnnou deduplikaci a návrhy podle přesného VS, částky a měny. Návrh nemění
  stav platby; 195/1457 testů, 270 PHP lintů a izolovaný MariaDB smoke prošly.
  Automatické potvrzení platby a Stripe zatím nevznikají.
  `fa452fd` zprovozňuje kompletní localhost demo a první KIS funkční vrstvu:
  sezóny, týmy, auditovanou soupisku, snapshot UCI ID a historické odebrání bez
  mazání. Lokálně prošel zákaznický login, dvě osoby, kroužek, košík, kupón,
  skladová ochrana, objednávka s QR i administrace soupisky. Celkem 198/1483
  testů a 276 PHP lintů prošlo; produkce se nezměnila. První paralelní přírůstek
  M1 doplnil rodinný read-only přehled, beneficiary snapshot košíku/objednávky,
  stabilní série soupisek, školní a kalendářní sezóny a bezpečný náhled rolloveru.
  Druhé kolo doplnilo M:N most plánovaných tréninků na soupisky, programy/nabídky/
  účasti a cílení událostí na více soupisek. Třetí kolo doplnilo automatickou
  aktivaci programu po úhradě a jeho storno/refund, auditované a souběžně bezpečné
  provedení rolloveru a veřejný self profil s kapacitní rezervací velodromu.
  M1.8 doplnilo localhost-only rozcestník A01–A10 s bezpečným resetem seedu,
  samostatný revokovatelný sportovní login a placený velodrom přes standardní
  shop order/payment/SPD/QR. Browser prošel A02 a celý A09 lifecycle: košík,
  objednávka, QR, paid aktivace, storno, refund a uvolnění kapacity. Migration
  catalog je lokálně 30/30. M1.9 doplnilo preview-first průvodce A05, jednotnou
  read-only osu A10 a bezpečnou expiraci nezaplacených objednávek. Browser ověřil
  deterministický A05 náhled, A10 události i admin expirace; reálná localhost
  programová objednávka expirovala právě jednou. Katalog je lokálně 31/31.
  Produkční workflow, kód ani DB se nezměnily.
- Další přesná akce: provést závěrečnou vlastníkovu bránu M2.6 a sepsat připomínky
  jako chyba, UX úprava nebo nový požadavek. Bez tohoto lidského průchodu se M2.6
  neoznačuje jako 100 %.
  Paralelně lze po dodání anonymizovaného vzorku potvrdit aliasy již implementovaného
  M2.3d field kontraktu a přijmout M2.3e paritní report nad skutečným formátem.
  Produkční
  Secret/config, deploy, Fio a ostrý import zůstávají samostatně blokované do
  pozdějšího výslovného rozhodnutí.

## Stav etap podle akceptačních bran

Procenta jsou řídicí odhad dokončených akceptačních bodů, nikoliv podíl řádků
kódu. Produkční aktivace se do nich nepočítá jako hotová bez živého důkazu.

| Etapa | Hotovo | Zbývá zejména |
|---|---:|---|
| K1 – katalog a publikace | 98 % | vlastníkova kontrola pravidel cen a finální publikace ostrého katalogu |
| K2 – účty, osoby a rodič–dítě | 92 % | self-service obnova sportovního hesla a potvrzení bezpečného párování na anonymizovaném finálním exportu |
| K3 – akce a přihlášky | 92 % | produktová kontrola exportu, rozhodnutí o ručních změnách čekací listiny a produkční UX |
| K4 – objednávky a platby | 97 % | úplný vlastnický průchod všech typů košíku včetně klubové ceny, ověřit Fio shadow návrhy a samostatně schválit automatické potvrzení/Stripe |
| K5 – náhrada starého KIS | 98 % | A05–A08 a A10 technicky i browserem uzavřeny; zbývá vlastníkova brána, finální jednorázový import a cutover |

## Rychlý aktuální stav pro další task

| Oblast | Implementováno | Otevřeno / blokováno |
|---|---|---|
| M1 integrovaný prototyp | technicky dokončen; Evidence, shop, rodina, programy, soupisky, události a velodrom mají localhost řezy | vlastníkova prohlídka A01–A10 |
| M2.1 akce | admin CSV export v `1b4d9e1`, kontrakt v1, CSRF, formula ochrana a audit | produktová kontrola sloupců v tabulkovém programu |
| KIS migrace | parser, matcher, raw archiv, fingerprintovaný preview, M2.3c sandbox, M2.3d stabilní KIS ID, M2.3e parita, M2.3f cílové předpisy a M2.3g auditovaný localhost promote/rollback | potvrdit aliasy a data na reprezentativním exportu, zopakovat cutover rehearsal v testovací DB a až potom plánovat ostrý cutover |
| e-shop | katalog, košík, objednávka, QR/převod, kupóny, sklad, výdej, storno/refund, program/událost/velodrom | detail/obrázky, pravidla slev pro služby, automatické platby a produkční aktivace |
| přístup | rodič–děti, sportovní účet, bezpečné session, revokace, limiter, jednorázové tokeny, samoobslužný reset a okamžitá oprávnění | produkční pepper, migrace a doručování reset e-mailu |
| finance | ruční převod a read-only Fio shadow návrhy | wallet D-009, Fio auto-confirm, Stripe, cash top-up a kombinované platby |

Aktuální M2 autorita je
[10 – Milník M2](10-milnik-m2-provozni-pilot.md). Nezávislá read-only revize má
kanonický prompt v
[PROMPT-CLAUDE-CODE-REVIZE-M2.md](PROMPT-CLAUDE-CODE-REVIZE-M2.md).

## Povinný kontrakt údržby tohoto souboru

`SESSION_HANDOFF.md` je jediný stručný živý stav projektu pro navázání dalšího
řídicího tasku. Board zůstává historickým programovým ledgerem a jednotlivé
milníkové dokumenty popisují cíle; nesmějí nahrazovat tento aktuální snapshot.

Řídicí task aktualizuje tento soubor:

1. po každém přijatém implementačním nebo integračním commitu,
2. po změně migrací, testovacích výsledků, brány nebo blokátoru,
3. před předáním do jiného tasku a před ukončením delší pracovní session,
4. po externí revizi, pokud její nález změní pořadí nebo stav M2.

Povinně zapíše datum, poslední implementační SHA, vlastněné dirty soubory,
aktuální migrace/testy/lint, dokončený výsledek, otevřené riziko a právě jednu
další konkrétní akci. Hodnoty GitHubu a produkce musí být označené jako historické,
dokud nejsou znovu živě ověřené. Soubor nesmí tvrdit 100 % jen podle množství
kódu; stav se mění pouze podle akceptačního důkazu.

## Pořadí autority

1. živě ověřený Git, lokální DB, GitHub a produkční důkaz,
2. schválená rozhodnutí v [02 – Zadání a rozhodnutí](02-zadani-a-rozhodnuti.md),
3. aktuální stav v [06 – Program board](06-program-board.md),
4. tento handoff,
5. předchozí chat a memory pouze jako vodítko, ne jako aktuální důkaz.

Při rozporu se nejprve zastaví mutace, zaznamená drift a aktualizuje board.

## Poslední známý důkazní snapshot

| Oblast | Poslední známá hodnota | Ověřeno | Zdroj | Obnovit při resume |
|---|---|---|---|---|
| Git remote | `https://github.com/KovoPraha/evidenceTreninku.git` | 2026-08-01 | `git remote -v` | ano |
| `origin/main` | `7f48b50b128b65f7340442ba33bfb9c66c27703a` | 2026-08-02 | fetch + rev-parse | ano |
| integrační branch | lokální `main`; M1 `9c4c3e1`, M2.3a `851288c`, M2.3b `26076ba`, M2.3c `5caa850`, M2.3d `2bcb346`, M2.3e `95693a2`, M2.3f `d69ee4f`, M2.3g `7c8b444`, backup hardening `281fcd0`, M2.4a `b4898f1`, M2.4b `838793d`, M2.4c `b5f3f3f`, M2.6 backup `fdbe30c`, seed/browser `4090bdc`, A02 `18deb9c`, A05 `8647bce`, A06 `dde0f3e`, A07 `03774db`, A08 `6ae75c1`, A10 `4ce0f17`, feedback `875c9e3` | 2026-08-04 | lokální Git, před docs commitem ahead 13 / behind 0 | ano |
| PR / remote CI | PR #1 až #6 merged; finální main run `30743017895` success | 2026-08-02 | GitHub | ano |
| ochranný snapshot | `d2b3c56` / `codex/pre-reconcile-20260801` | 2026-08-01 | lokální Git | před mazáním větve |
| GitHub deploy | run `30668559417`, success | 2026-08-01 | GitHub CLI | ano |
| produkční runtime | schema `2.20.2`, PHP `8.2.32`; DB server MariaDB `10.3.39` (Debian 10, host `replikant3544`, klientské připojení bez SSL v privátní síti `10.5.x.x`) | 2026-07-31 (schema/PHP); 2026-08-05 (MariaDB) | deploy post-check (schema/PHP); vlastník / phpMyAdmin (MariaDB) | před releasem |
| lokální schema | legacy `2.20.2` + 48/48 číslovaných migrací; audit připomínek nově ukládá typ a ID aktéra; hashované rodinné kalendáře a tři tabulky opt-in/fronty/auditu jsou aplikované | 2026-08-05 | migration apply/check + živá localhost MariaDB | ano |
| testy | 434/3872; M3.2 browserem prázdný aktuální týden a 12.–18. 8. jedna akce + jedna splatnost, bez cizí osoby/transportu a konzole 0; migrace 48/48, backup smoke 95 tabulek, 434 first-party syntaxí a audit 0 | 2026-08-05 | PHP 8.2.12 / PHPUnit 11.5.56 + localhost browser/MariaDB | ano |
| Shoptet staging | 241 produktů / 807 variant převedeno do draft katalogu; druhé spuštění bez duplicity, 1 bookable rental, 3 free varianty, 0 veřejně aktivních | 2026-08-02 | reálný XML + SQLite/MariaDB | před veřejnou aktivací |
| dependencies | PhpSpreadsheet 5.8.1, Guzzle 7.15.2, PSR-7 2.13.0, endroid/qr-code 6.0.9; 0 advisories | 2026-08-03 | Composer audit | ano |
| lokální backup drill | M2.6 `evidence_2026-08-04_104915_2404dfc4.sql.gz`: 125 tabulek, 2 triggery, SHA-256 `a7382f999126595fbbabffc99c7f5e926c0a134600fcf8659f167c949a0174a9`; ownership kontrakt `.8` včetně `password_reset_tokens` | 2026-08-04 | XAMPP DB backup mimo webroot + ověřený manifest/hash | zopakovat s produkčním artefaktem až před autorizovaným deployem |
| GitHub host key | Secret `SSH_KNOWN_HOSTS` dosud chybí | 2026-08-01 | pouze seznam názvů Secrets | ano; hodnotu nikdy nevypisovat |

Lokální DB, GitHub run a produkční runtime jsou tři různé zdroje. Výsledek
jednoho se nesmí vydávat za důkaz druhého.

**Poznámka ke kompatibilní podlaze (2026-08-05):** produkční DB server běží
MariaDB `10.3.39` na Debian 10 (`replikant3544`) — to je dnes nejstarší a
jediné dosud netestované prostředí projektu (localhost `10.4.32`, CI `11.4`).
MariaDB 10.3 je bez bezpečnostních záplat od roku 2023 a Debian 10 je po EOL;
jde o provozní riziko hostingu, ne o vadu této aplikace. Zůstává to otevřené
rozhodnutí vlastníka (upgrade hostingu/DB vs. vědomé setrvání) — tento řez k
tomu nepodniká žádnou akci vůči produkci ani jejímu poskytovateli, pouze
zaznamenává fakt a ověřuje statickou i smoke kompatibilitu aplikace s 10.3
(viz nová sekce níže).

## Povinný resume audit bez mutací

- [ ] přečíst tento soubor, README a dokumenty 02, 04, 05 a 06,
- [ ] spustit `git status --porcelain=v2 --branch`,
- [ ] zaznamenat `git rev-parse HEAD`, upstream, remote URL a výchozí branch,
- [ ] provést `git fetch --prune origin` a porovnat `HEAD...origin/main`,
- [ ] inventarizovat každý modified/untracked soubor a jeho vlastníka,
- [ ] ověřit GitHub repo, workflow a poslední run,
- [ ] ověřovat jen názvy/přítomnost Secrets, nikdy jejich hodnoty,
- [ ] ověřit lokální DB identitu a schema read-only dotazy,
- [ ] označit každý fakt jako lokální, GitHub, produkční nebo neověřený,
- [ ] před změnou stručně nahlásit drift proti handoffu.

Do dokončení inventury nepoužívat pull, stash, clean, reset, rebase, bulk stage
ani jinou operaci, která by mohla skrýt nebo přepsat cizí práci.

## Preservation ledger

| Povrch | Původ | Stav | Povolená akce |
|---|---|---|---|
| starý podrobný deploy manuál | práce před `58ec8ec` | zachován v `d2b3c56`, zastaralý | pouze ručně vytěžit stále platné rady |
| `overit_config.php` | dočasná produkční diagnostika | zachován v `d2b3c56` | neobnovovat a nenasazovat |
| přesné kopie nových deploy souborů | budoucí obsah originu v tehdy starém worktree | nyní kanonicky v `origin/main` | používat upstream verzi |
| programová dokumentace | řídicí task | obnovena na `codex/foundation` | řídicí task je jediný editor boardu/handoffu |

Snapshot branch se nemaže, dokud nebude ručně potvrzeno, že žádná zachovaná rada
nebo soubor už není potřebný. Snapshot není určen k merge ani pushnutí.

## Aktivní práce a vlastnictví

| ID | Stav | Base/branch | Vlastník | Povolený rozsah | Blokuje |
|---|---|---|---|---|---|
| W0-A | accepted | `58ec8ec` | řídicí task | Git reconciliation | nic |
| W0-B | accepted | `7106930` | KIS worker | matcher + integration testy | release do main |
| W0-C | partial accepted | auth commity v `main`, včetně PR #5 | security worker | hesla, dependencies, lifecycle, revokace, limiter, tokeny, logout | permission cache, reset hesla a produkční password apply |
| W0-D | accepted | `0d50584`, run `30718185103` | test worker | Composer dev, tests, CI/deploy gate | nic |
| W0-E | code accepted / production pending | `664745e`, `cd0c0e1`, PR #6 | integrační vlastník | migrace + deploy hardening | `SSH_KNOWN_HOSTS`, produkční pepper, autorizovaný první deploy |
| W0-F | waiting decision | dokumentace | produkt/ekonom | D-004 až D-011 | identity a wallet |
| W0-G | partial accepted | `98ff91d`, `168d132`, `8f0cbe8`, `699ddd4`, `d99a79e`, `2ba0782`, `f0370a3`, `3845eab`, `b77f8c3`, `8c374a4`, `d32fc08`, `5500927`, `4ef5690`, `88f5b97`, `e5fcaa0`, `a949c38`, `fb5e137`, `4cd0eae`, `5750dd0`, `1763531`, `f8b12a4`, `135803a` | KIS/shop workeři | Shoptet katalog, K2, bezplatný K3 a K4 checkout/storno/výdej/refund/kupóny | reálný KIS vzorek a automatické platby |

Řídicí task aktualizuje IDs, větve, commity a testy po každém worker handoffu.

## Integrační fronta

| Pořadí | Práce | Podmínka přijetí |
|---:|---|---|
| 1 | foundation dokumentace `6c7956c` | přijato |
| 2 | W0-D test baseline `0d50584` | přijato; pozdější main CI běhy zelené |
| 3 | W0-B KIS safety `7106930` | přijato; 10 testů / 61 assertions v tomto kroku |
| 4 | W0-C passwords `2ed5278` | přijato; produkční `--apply` výslovně neprovedeno |
| 5 | W0-C dependencies `1a9af03` | přijato; 0 advisories, celkem 16 testů / 75 assertions |
| 6 | W0-E migrations `664745e` | přijato lokálně; 29 testů / 119 assertions |
| 7 | W0-E deploy/backup `cd0c0e1` | přijato lokálně; restore drill prošel, GitHub/produkce čekají |
| 8 | W0-G Shoptet katalog `98ff91d` | přijato lokálně; provisional CSV-only dry-run, 2 produkty / 3 varianty |
| 9 | W0-G KIS parity `0537adf` | přijato lokálně; matcher/preview hardening + syntetický read-only kontrakt |
| 10 | Audit fixes `82eac98`, `b4207be` | přijato lokálně; jedno-snapshot CSV a pouze silné KIS identity signály |
| 11 | W0-G realistic KIS `168d132` | přijato lokálně; 10 opaque scénářů, 9 blockerů, missing nikdy nearchivuje |
| 12 | W0-G shop matrix `8f0cbe8` | přijato lokálně; varianty, kolize SKU, exact money/VAT a scope hranice |
| 13 | W0-C session lifecycle `dfce1ea`, `af49d57` | přijato lokálně; 102 entrypointů, bezpečné cookie/timeout/rotace/logout |
| 14 | W0-C DB revokace + rate limit `a3c2239`, `10c2cf9`, `9977b4d` | přijato; 103/569, MariaDB apply/runtime, dva finální audity ACCEPT a run `30740138748` success |
| 15 | W0-C one-time tokeny + booking lock `4b683ee` | přijato v PR #5; 110/626, SQLite + MariaDB, finální re-audit ACCEPT |
| 16 | W0-E release ordering `7361e48` | přijato v PR #6; 112/647, migrace před aktivací PHP, run `30743017895` success |
| 17 | Shoptet XML a katalogový staging `699ddd4`, `d99a79e`, `2ba0782`, `f0370a3` | přijato lokálně; reálných 241/807, 1 ruční kontrola, idempotentní SQLite/MariaDB staging, 127/762 |
| 18 | Auditovaný shop admin + KIS plán `3845eab` | přijato lokálně; admin-only, CSRF, audit změn, reálný MariaDB review smoke, 131/787 |
| 19 | Kanonický draft katalog `b77f8c3` | přijato lokálně; single-use transakce, collision rollback, reálných 241/807, 3 free varianty, 134/826 |
| 20 | K2 účet–osoba `8c374a4` | přijato lokálně; admin-only `self`/`guardian`, ověřený e-mail, revoke + audit, SQLite/MariaDB, 138/859 |
| 21 | K2 veřejný claim `d32fc08` | přijato lokálně; bez enumerace osob, admin review, atomické schválení, idempotence a limit, SQLite/MariaDB, 145/907 |
| 22 | Řízená aktivace katalogu `5500927` | přijato lokálně; pouze `goods`, explicitní potvrzení, plain-text snapshot, audit, K3 fail-closed, SQLite/MariaDB, 151/956 |
| 23 | K3 pracovní akce `4ef5690` | přijato lokálně; cílová skupina, termíny, kapacita, cena, produktová vazba a audit; bez přihlášek/KIS, SQLite/MariaDB, 158/1008 |
| 24 | K3 bezplatný kroužek `88f5b97` | přijato lokálně; schválené dítě z K2, transakční kapacita, unikátní přihláška, storno a audit; bez objednávky/platby/soupisky/KIS, SQLite/MariaDB, 165/1058 |
| 25 | K3 souhlasy a storno `e5fcaa0` | přijato lokálně; neměnný registr verzí, snapshot přihlášky a fail-closed deadline; SQLite/MariaDB, 166/1085 |
| 26 | K3 čekací listina `a949c38` | přijato lokálně; FIFO, K2 recheck, atomické povýšení a skutečný souběh dvou MariaDB procesů; 168/1122 |
| 27 | K3 oznámení + správní storno `fb5e137` | přijato lokálně; transakční outbox, retry/karanténa, CRON worker a auditovaná výjimka po termínu; SQLite/MariaDB, 173/1192 |
| 28 | K3 provozní fronta `4cd0eae` | přijato lokálně; admin přehled, CSRF, auditované retry a zákaz zásahu do `processing`/`sent`; SQLite/MariaDB |
| 29 | K4 bankovní checkout `5750dd0` | přijato lokálně; serverová cena + fingerprint, snapshot, idempotence, skladový pohyb, QR/SPD a ruční paid; SQLite/MariaDB, 182/1287 |
| 30 | K4 fulfillment/storno `1763531` | přijato lokálně; transakční restock právě jednou, `refund_required`, příprava/výdej a audit; SQLite/MariaDB, 186/1339 |
| 31 | K4 zákaznické objednávky/vratky `f8b12a4` | přijato lokálně; account-scoped seznam/detail, `refunded`, bankovní reference a idempotentní audit; SQLite/MariaDB, 187/1376 |
| 32 | K4 kupóny `135803a` | přijato lokálně; fixed/percentage, platnost/minimum/limit, audit, fingerprint, snapshot a souběžně bezpečný counter; SQLite/MariaDB, 190/1425 |
| 33 | K4 Fio shadow import `f23a332` | přijato lokálně; pouze GET `/periods`, kontrola IBAN, minimální bankovní data, neměnná deduplikace a návrh VS + částka + měna bez změny platby; SQLite/MariaDB, 195/1457 |
| 34 | Localhost demo + KIS sezóny/týmy/soupisky `fa452fd` | přijato lokálně; fail-closed seed, zákaznický/admin browser průchod, historická soupiska a audit bez externího KIS zápisu; SQLite/MariaDB/browser, 198/1483 |
| 35 | M1 rodinný sportovní portál `d42a30c` | přijato lokálně; guardian/self rozsah, IDOR ochrana, soupisky, akce a docházka |
| 36 | M1 příjemce shop položky `82b42a3` | přijato lokálně; autorizovaný beneficiary na košíku a neměnný snapshot v objednávce, běžné zboží smí mít NULL |
| 37 | M1 série a rollover preview `18b81a3` | přijato lokálně; školní/kalendářní sezony, čtyři politiky a read-only preview bez zápisu |
| 38 | Integrace prvního paralelního přírůstku | migration catalog 22/22, plná sada 213/1583, audit 0 advisories a browser průchod rodiče i KIS admina |
| 39 | M1.3 tréninkový most `9f7e531` | M:N vazba plánů/soupisek, snapshot a deduplikace; SQLite/MariaDB/browser |
| 40 | M1.4 kroužkové programy `e6fad8e` | nabídka, období, účast, kapacita, beneficiary, audit a ruční bezpečná aktivace |
| 41 | M1.6 cílené události `218bfd3` | M:N cíle, transakční oprávnění, snapshot a kompatibilita s čekací listinou |
| 42 | Integrace druhého paralelního přírůstku | migration catalog 25/25, plná sada 236/1779, audit 0 advisories, 296 lintů, backup 109/1 a browser smoke |
| 43 | M1.4 automatický lifecycle `15cd57b`, `589d79b` | paid aktivace, storno/refund, audit a bezpečné pořadí zámků; SQLite/MariaDB |
| 44 | M1.5 provedení rolloveru `8cf6774`, `94ab4a2` | fingerprint, výjimky, auditovaný přesun, idempotence a dvouprocesový concurrency smoke |
| 45 | M1.7 veřejný profil a velodrom `9d8cee5` | self profil, DOB, kapacita/exkluzivita, storno/rebook, ruční paid confirm; SQLite/MariaDB/browser |
| 46 | Integrace třetího paralelního přírůstku | migration catalog 28/28, plná sada 258/2029, audit 0 advisories, 310 lintů, backup 117/2 a autentizovaný browser smoke |
| 47 | M1.8 akceptační hub `bf3caa2` | A01–A10, localhost-only, admin+CSRF reset, bez zobrazení hesel |
| 48 | M1.8 sportovní přístup `91b105f` | 1:1 účet sportovce, revokace, izolovaná session a DB-scoped read-only přehled |
| 49 | M1.8 placený velodrom `664f855` | standardní shop order/payment/QR, snapshot, paid/cancel/refund lifecycle |
| 50 | Integrace M1.8 | 30/30, 281/2251, 327 lintů, backup 121/2 `.5`, opakovaný seed a browser A02/A09/reset |
| 51 | M1.9 A05 `d3e7e96` | preview-first přechod stejné osoby, kontrola věku, stale fingerprint, audit, idempotence a MariaDB smoke |
| 52 | M1.9 A10 `e363d86` | admin-only read-only osa devíti auditních zdrojů, omezené stránkování a pravdivě chybějící metadata |
| 53 | M1.9 expirace `c50e572` | dry-run default, explicitní admin potvrzení, společný storno lifecycle a payment-first pořadí zámků |
| 54 | Integrace M1.9 | 31/31, 298/2435, audit 0, deterministický A05 seed, browser A05/A10/expiry a backup 121/2 `.5` |
| 55 | Dokončení technické části M1 | placená soupiska/událost přes shop lifecycle, 32/32, 299/2547, 339 lintů, audit 0, browser paid flow a backup 123/2 `.6` |
| 56 | M2.1 provozní export účastníků | admin POST+CSRF, CSV kontrakt v1, izolace akce, formula neutralizace, audit; 303/2603, 345 PHP souborů a localhost browser |
| 57 | M2.3a KIS raw archiv `851288c` | externí storage, hash/size/contract metadata, preview manifest, localhost dry-run a explicit write, idempotence; 33/33, 308/2635, 350 lintů, backup 124/2 `.7` |
| 58 | M2.4a detail produktu `b4898f1` | seskupené varianty, schválené veřejné texty, sklad, bezpečné HTTPS obrázky a oddělený detail kroužku; 33/33, 313/2664, 354 lintů a browser průchod |
| 59 | M2.4b rozsah kupónů `838793d` | explicitní neměnná maska čtyř kategorií, výchozí pouze zboží, způsobilý mezisoučet a redemption snapshot; 34/34, 315/2707, 355 lintů a browser |
| 60 | M2.4c hardening `b5f3f3f` | H1 revize byl již zastaralý; H2/H3/M1 uzavřeny migrací aktivní účasti, legacy kapacitou a součtem dětí v košíku; 35/35, 318/2774, 356 lintů, audit 0 |
| 61 | bezpečnost legacy `65a0433` | náhodné profilové tokeny, hash všech hesel trenérů, CSRF/oprávnění/redakce/chybové odpovědi a Apache defense-in-depth; 36/36, 323/2903, 361 lintů, audit 0 |
| 62 | registrace bez enumerace `b8ecdaa` | nový i existující e-mail má stejnou veřejnou odpověď; 324/2907 |
| 63 | M2.5 recovery a oprávnění `7c1490e` | rodič i sportovec, guardian recheck, single-use token, session revokace a request-scoped permission refresh; 37/37, 330/2944, 367 lintů |
| 64 | localhost reset UX `edc6c62` | lokální testovací fragmentový odkaz bez mail serveru, produkce pouze e-mail; 330/2946 |
| 65 | MEDIUM kompatibilita `29f6029` | oddělená měna varianty/akce, fail-closed UTF-8 CSV a oficiální Fio datum; 333/2971, 367 lintů, 37/37 |
| 66 | M2.6 backup `fdbe30c` | ownership kontrakt `.8` včetně reset tokenů; ověřená záloha mimo webroot 125 tabulek / 2 triggery, zaměřený kontrakt 2/41 |
| 67 | M2.6 seed/browser `4090bdc` | stabilní A05 identita po rotaci tokenů, seed 2× se stejnými ID, A01 dvě děti a A03+A04 od QR přes syntetickou platbu po účast a soupisku |
| 68 | M2.6 lifecycle bez změny kódu | událost a velodrom od kapacitního hold přes platbu po storno/refund; skladové zboží 2→1→2; DB/UI shoda a konzole bez chyb |
| 69 | M2.2 homepage `25830e1` | společný veřejný vstup e-shop/rodina/sportovec/trenér a čtyři rychlé trenérské volby; HTTP 200, browser bez chyb, 336/3004 |
| 70 | M2.2/A02 `18deb9c` | sportovní souhrn, české stavy/datumy, společná homepage a browser IDOR důkaz jediné identity; 337/3014, 367 lintů, 37/37, audit 0 |
| 71 | M2.6/A05 `8647bce` | kanonický demo sportovec, auditovaný přechod a pravdivý no-op nového náhledu; reset zpět před přechod; 338/3025, 367 lintů, 37/37, audit 0 |
| 72 | M2.2/M2.5 veřejnost + jednotný účet `efa1ca8` | veřejný read-only katalog a rozvrhy, akce po loginu, jedna trenérská/zákaznická identita a společný reset; 346/3063, 375 lintů, 38/38, audit 0 |
| 73 | M2.6/A06 `dde0f3e` | společný preview/fingerprint, 3 auditní běhy, 3 přesuny, 2 zachované výjimky, žádná duplicita a reset zpět před A06; 349/3081, 379 lintů, 38/38, audit 0 |
| 74 | M2.4d klubové ceny `e67eed8` | veřejná cena a login výzva, ceny aktivních soupisek rodiny, kategorie/procento/pevná sleva, přesná cena produktu, audit a checkout snapshot; 353/3120, 383 parse, 39/39, audit 0 |
| 75 | M2.6/A07 `03774db` | vlastnická/datová ochrana plánu, neměnná kopie očekávání ke skutečnému tréninku, localhost porovnání docházky a sportovní přehled; browser 1/1/0/0, 354/3130, 384 parse, 39/39, audit 0 |
| 76 | M2.6/A08 `6ae75c1` | jedna přihláška oprávněného dítěte přes dvě cílové soupisky, UI bez duplicit a auditovaný opakovatelný seed reset; 355/3133, 386 parse, 39/39, audit 0 |
| 77 | M2.6/A10 `4ce0f17` | browser auditní osy objednávka/soupiska/přihláška/přístup, pravdiví aktéři a důvody, seed bez falešných password-reset událostí; 355/3135, 386 parse, 39/39, audit 0 |
| 78 | Cowork validace + MariaDB CI `ef5ec21` | zastaralé bridge závěry označeny, potvrzený CI nedostatek opraven jobem MariaDB 11.4 pro dva smoke skripty; 356/3142, 386 parse, 39/39, audit 0 |
| 79 | feedback + aktuální AI kontext `875c9e3` | admin+CSRF výsledky A01–A10, zamčený ignorovaný JSON, Markdown export, opravený samostatný rozsah projektu v CLAUDE a nový CURRENT_STATE; 358/3156, 388 parse, 39/39, audit 0 |
| 80 | M2.3b preview integrita `26076ba` | migrace preview reportu, úplná klasifikace, stabilní non-PII fingerprint, UI JSON export a idempotentní localhost seed; browser #7 2/2/0, 364/3197, 392 parse, 40/40, audit 0 |
| 81 | M2.3c sandbox promote/rollback `5caa850` | localhost admin+CSRF+fingerprint, transakční idempotentní promote, audit a rollback i při driftu bez kanonických zápisů; browser 2/2→0/2, 369/3254, 396 parse, 41/41, audit 0 |
| 82 | M2.3d field/external-ID kontrakt `2bcb346` | stabilní interní KIS ID oddělené od UCI, spojení tří exportů, archivace všech zdrojů, non-PII fingerprint a fail-closed execute; browser #8 2/2→0/2, legacy #7 blokován, 377/3308, 398 parse, 42/42, audit 0 |
| 83 | M2.3e cutover parity `95693a2` | uložené porovnání osob, členství, soupisek a platebních signálů bez PII; run #9 má 2 nové osoby + chybějící payment-prescription target, sandbox 2/2→0/2, 379/3332, 401 parse, 43/43, audit 0 |
| 84 | M2.3f členské předpisy `d69ee4f` | `member-charge-v1`, auditní cílové tabulky, stabilní ID+částka, atomický staging a non-PII porovnání; run #12 2 staging/2 čeká, 388/3369, 406 parse, 44/44, audit 0 |
| 85 | M2.3g auditovaný promote/rollback `7c8b444` | localhost admin+CSRF+fingerprint, transakční a idempotentní přenos předpisů, samostatná historická platba, invarianty a bezpečný rollback; browser #13 2/2 + 1 platba → 0/2 + 0 plateb, 391/3430, 408 parse, 45/45, audit 0 |
| 86 | oprava druhého kontrolního auditu `281fcd0` | ownership `.9` pokrývá všech 12 chybějících tabulek, generický katalogový guard + skutečný MariaDB backup smoke 90 tabulek, platební signál bez float; 393/3496, 409 parse, 45/45, audit 0 |
| 87 | M2.7a veřejný ICS kalendář `3aa39f8` | anonymní feed zveřejněných tréninků, otevřených akcí a veřejných hodin velodromu; stabilní UID, UTC, bez osobních a interních dat; 403/3617, 416 parse, 45/45, audit 0 |
| 88 | M2.7b rodinný ICS kalendář `004e4a6` | revokovatelný hashovaný 256bitový token, jednorázové zobrazení, audit, živé vazby osob a izolace rodin; tréninky/akce/rezervace/splatnosti, HTTP 200→404; 410/3662, 423 parse, 46/46, backup 92, audit 0 |
| 89 | M2.7c připomínky splatnosti `29e3d5d` | opt-in 3/7/14 dní, unikátní auditovaná fronta, stavová kontrola, 1 zpráva/20 h/účet, souběh a pět pokusů; login URL bez ID, browser končí vypnuto; 418/3728, 428 parse, 47/47, backup 95, audit 0 |
| 90 | M2.7d provozní obsluha připomínek `66b4241` | admin přehled pěti stavů, POST+CSRF+důvod+potvrzení, audit aktéra a bezpečné vrácení do fronty bez webového odeslání či obejití opt-out/stavu předpisu; 421/3761, 429 parse, 48/48, backup 95, audit 0 |
| 91 | M2.7e náhled/testovací outbox `68e1199` | no-store escapovaný náhled, localhost-only souborový transport, odmítnutí produkčního hostu a HTTP 403 pro `var/`; žádný skutečný mail, 423/3781, 429 parse, 48/48, backup 95, audit 0 |
| 92 | M2.7f syntetická browser ukázka `5843f70` | localhost admin+CSRF+potvrzení opakovatelně připraví auditovaný předpis, opt-in a jednu čekající zprávu, browser 0→1 + náhled, bez transportu; 425/3799, 431 parse, 48/48, backup 95, audit 0 |
| 93 | M2.7g browserové testovací doručení `6d290cc` | localhost admin+CSRF+potvrzení zpracuje jednu čekající připomínku pouze do souborového outboxu, claim/sent auditují trenéra; browser Čeká 1→Odesláno 1→Čeká 1, 427/3819, 431 parse, 48/48, backup 95, audit 0 |
| 94 | M2.6 závěrečná brána `9a04c3c` | localhost-only read-only panel kontroluje cesty, migrační checksumy a demo data odděleně od vlastníkova PASS; browser 3/3, 10/10 cest, 48/48 migrací, vlastník 0/10, blokátory 0; 429/3833, 433 parse, backup 95, audit 0 |
| 95 | M3.1 rodinný program `1510c20` | 30denní read-only agenda z kanonického rodinného kalendáře; browser rodiče 2 správné položky, cizí osoba nezobrazena, konzole 0; 431/3847, 433 parse, 48/48, backup 95, audit 0 |
| 96 | M3.2a týdenní náhled `82d41ac` | prostý text ze sedmidenní rodinné agendy, prázdný stav a omezené listování; browser 1 akce + 1 splatnost, bez cizí osoby a transportu; 434/3872, 434 parse, 48/48, backup 95, audit 0 |
| 97 | M3.3 roční přehled `63c8ec1` | read-only uhrazené členské předpisy a e-shopové položky po osobách a měnách; bez účetního/daňového exportu; 438/3897, 438 parse, 48/48, audit 0 |
| 98 | M3.4 provozní přehled `12c2300` | admin read-only signály peněz, kapacit, přihlášek a výjimek s odkazy do existujících auditovaných obrazovek; 452/3951, 450 parse, 48/48, audit 0 |
| 99 | sjednocený UI základ `3a35a2b` | společné pozadí, formuláře, načítání, toast zprávy a veřejná navigace pro 127 aktivních HTML stránek; 455/3970, 450 parse, 48/48, audit 0 |
| 100 | dokončení M3.2 | opt-in/opt-out, idempotentní fronta a audit, sdílený localhost-only outbox; browser zapnout → 1 zpráva → lokální uložení → vypnout, 462/4026, 457 parse, 49/49, backup 98, audit 0 |
| 101 | M3.5a kvalita sportovních dat | admin-only agregace pěti zdrojů bez jmen, ID a hodnot; browser 5 karet / 0 formulářů / 0 vstupů / konzole 0; integrovaný kontrakt hlídá jednotnou identitu, osoby a docházku; 466/4075, 461 parse, 49/49, audit 0 |
| 102 | M3.5b sportovní datový kontrakt | aditivní `sports-measurement-v1` pro m/km, metry, milisekundy, RPE 1–10 a stavy entered/finished/DNS/DNF/DSQ; žádný backfill, browser 0 formulářů/vstupů a konzole 0; 477/4107, 466 parse, 50/50, backup 100, audit 0 |
| 103 | M3.5c normalizovaný zápis sportovních dat | čtyři formuláře/handlery sdílí fail-closed parser a ukládají legacy + v1 hodnoty; výslovná jednotka, striktní čas/RPE, testovaný importní mapper bez ostrého zápisu; browser bez odeslání a konzole 0; 487/4161, 469 parse, 50/50, backup 100, audit 0 |
| 104 | M3.5d read-only příprava importu | admin-only no-store stránka bez formuláře: pokrytí v1 a konkrétní nejednoznačné legacy řádky s důvody; deterministické kandidáty pouze počítá, nic nepřevádí a neodhaduje; bez osob; oprava chybějícího Bootstrap head u M3.5a/M3.5d a reference legacy řádků bez PK; browser živě 2 účasti + konzole 0; 491/4217, 470 parse, bez nové migrace a změny závislostí |
| 105 | M3.5e rozpoznávání historické tabulky měření (`188407c`) | deterministický kontrakt „<číslo> km/m/min“ + striktní čas pro volný text `mereni`; rozsah/„cca“/více hodnot/prázdná hodnota je vždy nejednoznačné, nic se nepřevádí; admin sekce se všemi 7 řádky a verdikty bez sportovec_id a jmen; browser živě 0 rozpoznatelných/7 nejednoznačných, konzole 0; izolovaně 492/4276 (base 491/4217, +1 test/+59 assertions), 470 parse beze změny počtu, bez migrace a nové závislosti |
| 106 | oprava chybějící Bootstrap hlavičky na 4 stránkách (`a90de13`) | `provozni_prehled_admin.php`, `family_weekly_summaries_admin.php`, `member_charge_reminders_admin.php` a `member_charges_admin.php` dostaly stejnou hlavičku jako M3.5d; nový regresní test hlídá všechny stránky s `hlavicka.php`; browser 4× potvrzený Bootstrap stylesheet a konzole 0; nad M3.5e `188407c`; 493/4277, 471 parse, migrace beze změny (50/50); řídící vlákno nezávisle potvrdilo 493/4277 nad sloučeným stromem |
| 107 | MariaDB smoke pro sportovní read-only přehledy (`9e8d9d0`) | `bin/sports-review-smoke.php` na izolované testovací DB (regex-omezený název, nikdy `evidence`) reprodukuje reálné schéma včetně `zavod_sportovec` bez surrogate PK, spouští skutečnou migraci `20260805050000` a ověřuje `sportsImportReview()` i `sportsDataQualityInventory()` bez sportovec_id/jmen; zapojeno jako čtvrtý krok CI jobu mariadb-smoke (nespuštěno); lokální běh na MariaDB 10.4.32: 5 měření (1 v1), 3 výsledky (1 v1), 7 legacy textových řádků (2/5), 8 záznamů inventury; DB dropnuta ve finally; 493/4277 beze změny |
| 108 | statická kontrola kompatibility s produkční MariaDB 10.3 | zaznamenán produkční DB server (`10.3.39`/Debian 10/`replikant3544`, zdroj vlastník+phpMyAdmin 2026-08-05) do snapshotu s poznámkou o EOL riziku jako otevřeném vlastnickém rozhodnutí, bez akce; grep + ruční revize `migrations/*.php`, `auto_migrace.php`, `migration_runner.php`, `db.php` a first-party PHP nenašla žádnou konstrukci nad podlahou 10.3 (`RETURNING` 10.5.0, `JSON_TABLE`/`JSON_ARRAYAGG`/`JSON_OBJECTAGG` 10.5–10.6, `SKIP LOCKED`/`NOWAIT`/`LATERAL` 10.6.0) — bez nálezu; jediný nalezený `CHECK` constraint je kompatibilní (vynucováno od 10.2.1); utf8mb4 kolace explicitní a beze změny napříč 10.3/10.4/11.4; `mariadb-smoke` CI job dostal `strategy.matrix` 10.3/11.4 (workflow nespuštěn); lokálně 4/4 smoke skriptů OK na izolované MariaDB 10.4.32, `evidence` nedotčená; 493/4278, 471 parse, migrace beze změny (50/50) |
| 109 | deploy workflow pro kis.kovopraha.cz | cíl parametrizován přes GitHub Variables (KIS_APP_HOST/KIS_WEB_URL/KIS_REMOTE_DIR) s fail-closed kontrolou; job běží v environment `production`, concurrency `produkce-kis`; server má chroot SSH bez funkčního $HOME a nezapisovatelný kořen, deploy stav proto žije relativně v `data/.kis-deploy` a zálohy v `data/.kis-backups`; SSH klíč ověřen (KEY-OK), PHP CLI 8.2.32 + rsync na serveru potvrzeny, docroot `kis.kovopraha.cz`; kontraktní test rozšířen (zákaz $HOME a .evidence-deploy); 493/4288; první nasazení dál vyžaduje workflow_dispatch + NASADIT; hosting má omezený shell (rssh) zakazující `php -r` — preflight validace configu běží jako nahraný `bin/deploy-preflight.php` řízený vygenerovanými putenv() bootstrap soubory — hosting blokuje argumenty skriptů I externí env, jediný průchozí kanál je soubor; `migrate.php` a `db-backup.php` mají aditivní env vstupy MIGRATE_*/BACKUP_* čtené přes putenv z bootstrapu, migrace bootstrapy se před aktivací release mažou a `deploy-preflight.php` má CLI guard a vzdálený `php -l` je nahrazen CI lintem; první běh nasazení dále odhalil a potvrdil funkční Variables/Secrets řetěz |
| 110 | rozšířená akceptační sada B01–B30 (`d33ec55`) | `localhostAcceptanceScenariosB()` s 30 scénáři v 8 oblastech (kompletní funkční pokrytí systému); `testovaci_scenare.php` sloučené zobrazení A+B se společným feedback mechanismem a Markdown exportem; brána M2 dál počítá jen A01–A10; 13/114 akceptačních asercí zelených, CRLF→LF normalizace hubu |
| 111 | Stripe slice 1 za vypnutým flagem (`cb2c356`) | Checkout Session redirect nad serverovým snapshotem, podepsaný no-store webhook, event-id idempotence a sdílený auditovaný `pending→paid` přechod se systémovým aktérem; výchozí flag false, bez klíčů fail-closed, bez sítě v testech; Stripe SDK 21.1 včetně vendoru, číslovaná migrace (legacy `SCHEMA_VERSION` zůstává správně zmrazená), backup 101; 502/4407, 467 parse, composer audit 0, bez push/produkční aktivace |


PR #1 až #6 jsou sloučené do `main`. Produkční migrace, migrace hesel ani deploy
se v této session nespustily. Pořadí migrace před aktivací PHP je opravené;
workflow stále nesmí být spuštěno bez ověřeného `SSH_KNOWN_HOSTS`, externího
`AUTH_RATE_LIMIT_PEPPER` a výslovného souhlasu vlastníka.

Shop přírůstek zůstává lokálním a dosud nenasazeným krokem: má staging,
kontrolní UI, kanonický katalog, řízenou aktivaci zboží, K2 vazby účtů na
sportovce, checkout, objednávku, ručně potvrzenou bankovní platbu, storno,
fulfillment, refundaci, kupóny, beneficiary snapshot položky a první programovou
účast/prodloužení včetně automatického navázání na paid/storno/refund. Placený
velodrom používá stejnou shop objednávku, QR, paid/storno/refund lifecycle a
kapacitní rezervaci; chybí expirace pending objednávek, automatická platba,
Stripe, produkční import a externí KIS zápis.

Session increment používá vlastní cookie `EVIDENCESESSID`; jeho budoucí deploy
jednorázově odhlásí existující relace. DB revokace a atomický HMAC rate limit jsou
lokálně hotové, ale deploy je fail-closed bez externího `AUTH_RATE_LIMIT_PEPPER`.
Evidence je samostatný produkt. `VELOCOTA_INTEGRATION` musí zůstat `false`;
širší provozní nebo doménová integrace s Velocotou není plánovaná. Výhledově lze
samostatným rozhodnutím řešit pouze sdílenou/federovanou identitu uživatele.
Expirované hashované tokeny a POST+CSRF logout jsou hotové v `main`.
Permission cache, reset hesla a produkční ověření zůstávají otevřené, takže
W0-C ani F0 nejsou uzavřené.

## Stop podmínky

- nejasný vlastník dirty souboru,
- rozpor živého stavu s boardem,
- dvě větve mění stejný sdílený soubor nebo migraci,
- chybějící produktové rozhodnutí,
- požadavek na produkční změnu bez výslovného pověření,
- riziko vypsání tajemství nebo osobních údajů,
- worker nedodal base/commit SHA, testy a rozsah změn.

## Checklist před ukončením řídicího tasku

- [ ] aktualizovat stavy workerů a integrační frontu,
- [ ] měnit board pouze podle ověřeného důkazu,
- [ ] zapsat přesné SHA a všechny dirty soubory,
- [ ] uvést nedokončený proces nebo čekající rozhodnutí,
- [ ] zapsat jednu další konkrétní akci,
- [ ] aktualizovat čas tohoto handoffu,
- [ ] ověřit, že prompt nového řídicího tasku stále odpovídá procesu.
