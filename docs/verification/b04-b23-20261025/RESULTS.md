# B04 a B23 — implementace a izolovaná přejímka

Datum: 25. 10. 2026. Výchozí main: `3940e6b5` (PR #204).

**B04: PASS pro ruční/CSV evidenci interních a externích výsledků. B23: PASS pro technický převod osob a aktuálních soupisek v izolaci; ostrý a finanční přechod zůstává odložen.** Nejde o přejímku produkčního importu ani souhlas s nasazením. Předchozích 38 scénářů nebylo znovu celé procházeno v prohlížeči; jejich důkaz zůstává v předchozím protokolu. Po doplnění B04 má původní širší seznam 39 PASS a 1 částečně uzavřený bod B23, jehož zbývající provozní část čeká na rozhodnutí.

## Izolace

- Vlastní checkout `evidencePavel-b04-b23-20261025`, větev `codex/race-results-kis-handoff-20261025`.
- HTTP `127.0.0.1:18097`, vlastní MariaDB `127.0.0.1:33437`, syntetická databáze `evidence_b1` a oddělené účetní databáze.
- Runtime `%LOCALAPPDATA%/EvidencePavelB1/b04-b23-20261025`; ověřený owner, datadir a databázová identita. Soukromá data a sessions mimo webroot.
- Webové PHP má zakázané odchozí transporty a procesy; Fio, SumUp, Stripe a Velocota jsou vypnuté. Nebyla použita produkční data ani nasazení.
- Readiness PASS: 333 vlastněných tabulek dostupných, žádná požadovaná tabulka nechybí. Tři historické volitelné tabulky Google Sheets zůstávají nepřítomné.
- Základní demo seed vytvořil osoby a účty, poté vyžadoval staging Shoptetu. Tento běh proto není deklarován jako kompletně naplněné e-shopové demo. B04/B23 používají vlastní syntetické fixtures.

## B04 — výsledky konkrétního závodu

Nové účasti mají samostatné ID, klíč v rámci závodu a volitelnou vazbu na klubovou osobu. Externí účastník nezakládá fiktivního člena. Výsledky rozlišují disciplínu/jízdu; stejně pojmenované osoby se neslučují. Kategorie nadále patří k závodu, samostatné kategorie účastníků ani veřejný výsledkový portál se tímto během nezavádějí.

`zavod_vysledky.php` patří pracovní pozici Vedoucí sportu. Náhled → koncept → zveřejnění v interním detailu tvoří oddělené kroky s důvodem, potvrzením, CSRF, kontrolou verze a idempotentním požadavkem. Oprava i návrat tvoří novou revizi. Ruční formulář má ochranu proti zkrácenému POST; nad 80 řádků se používá CSV (limit 500 výsledků / 1 MB).

Historické vazby, časy a pořadí se načítají pouze do čtecího předvyplnění. Neznámá disciplína, jízda a stav vyžadují kontrolu; žádný historický výsledek se automaticky nepublikuje ani nepřepáruje. Původní měření, soubory a přihlášení zůstávají v detailu dohledatelné.

### Prohlížeč a databázový odečet

Syntetický závod #1, osoba #1, dva externí účastníci se stejným jménem a různými klíči, dvě jízdy vlastní osoby:

1. Neplatný čas `00:99` odmítnut; žádná revize nevznikla.
2. Náhled čtyř řádků, koncept #1 a publikace #2; detail ukazuje tři účastníky a jednoho dokončivšího závodníka.
3. CSV oprava času `00:12.345` → `00:12.222`: koncept #3 nemění zveřejněnou #2, publikace #4 ukazuje opravu.
4. Načtení původní revize do náhledu, koncept #5 a publikace #6 vracejí původní čas; všechny předchozí revize zůstávají zachované.
5. DNS/DNF, více jízd a kolize jmen zůstaly oddělené. Automatické testy navíc pokrývají DSQ/validaci stavů, neplatnou osobu, duplicitní jízdu, únik HTML, souběžnou verzi a rollback SQL chyby.

Důkazy: [detail publikace](race-published.png), [obnovená revize 6](race-restored.png).

## B23 — řízené předání KIS

Starý přímý `execute` zůstává vyřazen. Konkrétní běh vede z průvodce do KIS centra a dále na `kis_review_admin.php` s otiskem úplných staged dat a tří ověřených archivů. Přiřazení ke stávající nebo nové osobě je výslovné podle stabilního KIS ID. Žádné párování pouze podle jména.

Neměnný plán obsahuje před/po pro jméno, příjmení, datum narození, KIS ID a zvolené aktuální soupisky. Normalizované vyhledávací indexy se aktualizují společně. Chybějící datum narození se neodhaduje. Osoby mimo import, účty, souhlasy, VS a platby se nemění.

Náhled i aplikace používají konzistentní transakční snapshot; aplikace znovu ověří zdroj a cílovou evidenci. Opakování je idempotentní. Kompenzační návrat kontroluje osobu i její přímé závislosti; při novější změně nic nevrátí. Nové osoby se při návratu archivují, vlastní přidaná členství ruší, historie zůstává. Archivované KIS ID se automaticky znovu nepoužije; další pokus vyžaduje registrářovu kontrolu.

### Prohlížeč a databázový odečet

Syntetický běh #1 / plán #1: první řádek výslovně přiřazen osobě #2, druhý založen jako osoba #4, oběma přidána soupiska #1 od 25. 10. 2026.

- Aplikace uspěla; běžná karta osoby #2 ukázala nové jméno, datum a aktuální soupisku.
- Návrat uspěl: osoba #2 má původní jméno a datum, osoba #4 je archivovaná, dvě přidaná členství jsou `cancelled`, audit obsahuje `apply` a `rollback`.
- Účetní parity v syntetickém zdroji zůstávají blokované; aplikace osob je neobchází ani nezapisuje platby.
- Automatické testy: změna cíle/zdroje, chybějící či poškozený archiv/manifest, duplicitní ID, neplatná vazba, chybějící datum, chyba během soupisek, novější závislá objednávka, opakování a návrat i po ztrátě zdrojového archivu.

Důkazy: [aplikovaný plán](kis-applied.png), [běžná karta osoby se soupiskou](kis-person-card.png), [vrácený plán a audit](kis-rollback.png).

## Opravy nalezené při přejímce

- Nová osoba explicitně dostává prázdný e-mail: skutečné starší schéma jej vyžaduje jako NOT NULL. Přidána regrese.
- Opraveny souhrnné počty v detailu závodu po publikaci externích účastníků a více jízd.
- Hláška z GET parametru už nepřepisuje zprávu o načtení historické revize při POST.
- Šest nových tabulek zařazeno do vlastněných záloh a dva migrační identifikátory do kontrolního katalogu.
- Náhled KIS uzamkne stejný stav, ze kterého ukazuje předchozí hodnoty a počítá otisk.

## Reprodukce technických kontrol

```text
php vendor/bin/phpunit --configuration phpunit.xml.dist
php vendor/bin/phpunit --filter 'RaceResultsTest|KisReviewHandoffTest'
php tests/Support/RaceKisReviewMariaDbSmoke.php
php tests/Support/RaceKisReviewHttpAcceptance.php
node --test tests/Support/PlannerJavascriptSmoke.cjs tests/Support/TrainingEditJavascriptSmoke.cjs tests/Support/FormularAutocompleteKeyboardSmoke.cjs tests/Support/AppUiSubmissionSmoke.cjs
```

MariaDB smoke vytváří a uklízí pouze náhodnou vlastní databázi; lokálně `EVIDENCE_MARIADB_SMOKE_PORT=33437`, v CI výchozí 3306. HTTP scénář je záměrně vázán na výše uvedený syntetický runtime a odmítne jiný owner, host, datadir či chybějící fixtures. Vytváří pouze syntetického trenéra pro negativní přístupové kontroly. Samostatná PHPUnit sada nepotřebuje běžící web.

Dosud ověřeno: 16 cílených testů / 72 assertions; MariaDB smoke 7 kontrol; HTTP 19 kontrol včetně neměnnosti osob, soupisek, plánů, událostí a revizí při odmítnutých požadavcích; JavaScript 14/14. Úplná lokální regrese: 1738 testů / 35040 assertions, bez chyb, tři unixové testy přeskočené na Windows. Následně doplněný čtecí legacy test i další validace prošly cílenou sadou 16/72. Linux CI včetně nepřeskočených unixových testů a MariaDB 10.3/11.4 je podmínkou sloučení.

## Odložená rozhodnutí a provozní hranice

1. Vlastník určí zdroj pravdy jednotlivých agend, finální exporty, rozsah pilotu, odpovědné osoby a datum uzávěrky.
2. Účetní potvrdí finanční paritu, převod otevřených předpisů, zachování VS a návaznost uzavřené evidence. Tento tok finance nepřenáší.
3. Potvrdit disciplíny/kategorie, publikační odpovědnost a případná veřejná pole výsledků. Nynější publikace je interní a oprávnění má Vedoucí sportu.
4. Skutečné importy, automatické integrace, produkční migrace a nasazení jsou mimo tento souhlas. Nový kanonický KIS zápis je navíc programově omezen na lokálního administrátora.

UX pro další iteraci: výběr klubového závodníka místo ručního ID, přidávání libovolného počtu řádků bez CSV a přehled stojících soupisek bez sezony v administraci. Poslední bod se nezasahoval do souběžné uživatelovy rozpracované změny `kis_rosters_admin.php`; skutečné členství bylo ověřeno běžnou kartou osoby.

Sloučení a výsledky CI: [PR #205](https://github.com/KovoPraha/evidenceTreninku/pull/205). Lokální konfigurace, runtime a pomocné startovací skripty zůstávají mimo Git.
