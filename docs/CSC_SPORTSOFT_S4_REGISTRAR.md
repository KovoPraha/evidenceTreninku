# P15 S4 — čtecí přehled pro registráře

Datum: 7. 11. 2026. Stav: čtecí implementace a syntetická přejímka HOTOVO v draft #380; vlastník Marek / KIS Codex.
Navazuje na [hlavní plán P15](CSC_SPORTSOFT_READ_SYNC_PLAN_2026-11-07.md),
[S1](CSC_SPORTSOFT_S1_KIS.md) v draft [#376](https://github.com/KovoPraha/evidenceTreninku/pull/376).
Větev `codex/csc-sportsoft-s4-registrar-20261107`, vlastní worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-csc-sportsoft-s4-20261107`.
Base `e36c9ba6` + S1/prompt `dc3dc66a`; cizí WIP zachovaný.

## Zadání a hranice

Vlastník povolil implementaci přehledu synchronizací a nespárovaných záznamů,
syntetické testy, dokumentaci a push pro druhé pracoviště. Nasazení, zapnutí,
portálové operace a kontakt s daty Rikiho nejsou součástí tohoto kroku.
[Prompt S2/S3](CSC_SPORTSOFT_S2_S3_STATION_PROMPT.md) je již pushnutý v #376;
stanice pokračuje na vlastní větvi, S4 nemění příjmový kontrakt.

## Plán před implementací

1. Registrářská GET stránka a položka pracovního prostoru. Aktivní kontext
   registráře i současné oprávnění v databázi; jiné role a rodiny bez přístupu.
2. Přehled připojení, posledního přijatého snímku a posledního pokusu zvlášť.
   Vypnutý příjem/připojení, chybějící data, chyby a stáří musí být rozlišitelné.
3. Historie běhů, náhled úplného návrhu čekajícího na kontrolu, stránkovaný
   přehled řádků a filtr důvodů nespárování. Žádná tlačítka schválení či zápisu.
4. Párování přepočítat podle současné kmenové evidence při čtení; staré vazby
   nezobrazovat jako platné. Kontrolovat rozpory v celém snímku, nejen stránce.
   Dotazy bez N+1, výsledná stránka nejvýše 50 řádků; identifikátory jako text.
5. Zobrazovat jen pole kontraktu, čas pozorování zdroje odděleně od příjmu,
   přeložené důvody a bezpečně escapované hodnoty; žádné tokeny či raw payload.
6. Syntetické testy čtení/rolí/filtrů/stránkování/změny UCI/XSS a nulových
   zápisů. Ověření PHP, dokumentace, cílené testy a CI před předáním.

## Akceptace a zbývající rozsah

Požadovaný čtecí řez je hotový až po fungujícím přehledu, syntetických testech
a sdílení větve s důkazy. Přístup k živým portálům, skutečný worker/pilot,
provozní aktivace a lidská přejímka zůstávají NOT RUN. Širší S4 z hlavního plánu
(panel na kartě osoby a auditovaná ruční rozhodnutí) zůstává otevřené;
duplicitní UCI nelze v této obrazovce ručně potvrdit.

## Průběžné výsledky

- Plán zapsán před implementací; S1 začleněné do samostatné větve, věcné změny
  současného main v dokumentaci zachované. Další krok: čtecí služba a stránka.
- Implementována `external_sync_admin.php`, registrářská navigace, stránkování
  připojení/běhů/řádků, filtry důvodů a časy pozorování jednotlivých dávek.
  Detail čte konzistentní transakci bez zápisů; celé snímky vyhodnotí dvěma
  průchody, aktuální osoby indexuje podle UCI a ID, bez dotazu za každý řádek.
- S1 kontrakt zůstává beze změny. Jediná úprava sdíleného resolveru je přijetí
  `iterable` pro kontrolu rozporů zdroje, aby přehled nemusel `fetchAll` řádky.
- První běh 39 testů: 2 opravy testovacího prostředí (aktivní role v session
  fixture a nezařazený nový soubor v Git inventuře). Šest doménových S4 testů
  prošlo. Opakovaná HTTP/registry sada PASS: 15 testů / 2349 assertions.
- HTTP harness používá skutečnou stránku, čtecí službu, šablonu, registr rolí
  a S1 ingest nad syntetickou SQLite. Nahrazuje pouze init/session bootstrap
  a společnou hlavičku; plné přihlášení KIS a skutečný portál tím nejsou ověřené.
  Při GET je DB `query_only=ON`, test navíc porovnává hash všech integračních
  a kmenových tabulek. Ověřeny 403/405/400/404/503, odebrání role a HTML escaping.
- Dokumentace: kontrola PASS, CURRENT 80 řádků, 4701 místních odkazů, 0 chyb.
  Lokální finální sada PASS: 39 testů / 2630 assertions, jeden očekávaný
  SQLite skip MariaDB lock-race. Styl PHP PASS (23 změněných souborů).
- Push hotový, [draft PR #380](https://github.com/KovoPraha/evidenceTreninku/pull/380).
  Integrovaný dokumentační main `6b1860d5`, runtime `5095d1ee`, merge `fb6fbc13`.
  CI dokumentace PASS; S1/S4 MariaDB krok na 10.3 i 11.4 PASS. Finální výsledek je v tabulce níže.
  PHPStan odhalil neprokázanou inicializaci `$pdo` za vstupní ochranou;
  doplněna skutečná kontrola dostupnosti PDO, bez potlačení/baseline.
- Playwright localhost: přehled i detail načtené, mobil 390 px má scrollWidth
  390 px a 50 řádků; desktop 1440 px má scrollWidth 1440 px. Filtr Spárováno
  zobrazil jednu správnou osobu, detail polí zdroje lze rozbalit, návrat na
  K prověření a přechod na druhou stránku fungují. Vizuálně zkontrolované oba
  viewporty. České důvody chyb a UCI v řádku doplněné po této kontrole;
  cílená S4 sada znovu PASS: 7 testů / 122 assertions.
  Jediná browser console chyba byla chybějící favicon lokálního harnessu.
  Živé portály a skutečné přihlášení KIS NOT RUN.
- Merge do main/deploy/aktivace: NOT RUN.

## Reprodukce a meze ověření

V čistém checkoutu s `composer install`, bez `config.php`:

```text
php vendor/bin/phpunit tests/Integration/ExternalSyncRegistrarTest.php tests/Integration/ExternalSyncRegistrarHttpTest.php
php vendor/bin/phpunit tests/Unit/ExternalIdentityMatchTest.php tests/Integration/ExternalReadSyncTest.php tests/Unit/StaffWorkspaceRegistryTest.php
php tools/check-current-docs.php
git diff --check
```

MariaDB 10.3/11.4 ověřuje `.github/workflows/tests.yml` se zapnutým výhradně
testovacím `EVIDENCE_EXTERNAL_SYNC_MARIADB=1`; test vytváří vlastní náhodně
pojmenovanou databázi a odstraňuje pouze ji. Nepoužívá konfiguraci aplikace.
Lokální úplný PHPStan na Windows nedoběhl v praktickém čase a byl zastaven;
autoritativní PHPStan level 5, baseline budget i styl v Linux CI na `53916669`
PASS: [běh 42399094794](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42399094794).
Finální ověření runtime `db7a174fa19006380ff2b8f0ed4ee7a7b940c561`: **všech 11 kontrol PASS**.
Výsledkové předání mění pouze dokumentaci; neprohlašuje opakování celé sady na pozdějším dokumentačním commitu.

| Kontrola | Výsledek | Důkaz |
| --- | --- | --- |
| Celá sada PHP 8.2 na Linuxu | PASS: 2396 testů / 48111 assertions, 1 očekávaný SQLite skip MariaDB lock-race | [PHPUnit](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42399616696/job/128484347389) |
| S1 + S4 na MariaDB 10.3 | PASS: 20 testů / 252 assertions, včetně skutečného lock-race | [MariaDB 10.3](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42399616696/job/128484347662) |
| S1 + S4 na MariaDB 11.4 | PASS: 20 testů / 252 assertions, celý job PASS | [MariaDB 11.4](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42399616696/job/128484347761) |
| Oba MariaDB profily čtení celého projektu | PASS | [Testy](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42399616696) |
| PHPStan level 5, baseline, styl a anonymní inventura | PASS, bez nové výjimky; anonymní přístup 0 chyb | [PHP quality](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42399616756) |
| Dokumentace a odkazy | PASS | [Dokumentace](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42399616677) |
| CodeQL / actions, JS a Python | PASS | [CodeQL](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42399613215) |

Playwright i dočasný localhost PHP server byly ukončené, vlastní syntetická
SQLite a HTTP sandbox odstraněné. Vlastní worktree byl po pushi čistý.
Samostatné S0/S1 PR zůstávají závislostí; #380 je obsahuje, do main se nic
nesloučilo. Prompt stanice je shodný s pushnutou větví S1/#376.

SQL rozpočet detailu je nejvýše 10 dotazů včetně ověření role a pozorování
dávek, stejně pro 50 i poslední 1 řádek ze snímku s 301 řádky. Celý snímek
se pro čerstvé filtrování prochází; měření maximálních 100 000 řádků NOT RUN.
S4 nemá novou migraci ani zápisové API. Příjem zůstává pod původním OFF
flagem S1. Zobrazení návrhu nikdy neposune ukazatel přijatého snímku.

## Předání a další krok

Čtecí řez požadovaný vlastníkem je implementovaný, synteticky ověřený a
pushnutý v draft [#380](https://github.com/KovoPraha/evidenceTreninku/pull/380).
Stanice může pokračovat podle [S2/S3 promptu](CSC_SPORTSOFT_S2_S3_STATION_PROMPT.md)
v pushnuté větvi `codex/csc-sportsoft-s1-ingest-20261107` / #376.
S4 příjmový kontrakt nemění. Širší S4 (osobní panel, auditovaná ruční
rozhodnutí), skutečný worker/pilot a plné přihlášené UAT zůstávají otevřené.
Merge/deploy/aktivace a portálové operace NOT RUN. Riki ani jeho data nebyly
použity; místní syntetické procesy jsou ukončené.

## Navazující S4 — panel osoby a auditovaná řešení (zadání 7. 11.)

Stav: ROZPRACOVÁNO, vlastník Marek / KIS Codex. Nová větev
`codex/csc-sportsoft-s4-review-20261107` ve stávajícím čistém izolovaném worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-csc-sportsoft-s4-20261107`.
Base: aktuální main `6b1860d5` je předkem #380 / `ad80e3dc`; S1 a čtecí S4
zůstávají závislostmi. Výslovné zadání: doplnit zbývající S4, pouze syntetika,
bez deploye, aktivace, portálů a Rikiho.

Plán před implementací:

1. Nová append-only tabulka rozhodnutí po řádku snímku: stav, revize, autor,
   čas, povinný důvod a hash kontextu bez kopie osobních údajů. Samostatná
   migrace, rozšíření záloh a syntetické obnovy; žádná DDL z webu.
2. Oddělený detail a POST pro registráře s CSRF. Akce odložit, nespadá do KIS,
   znovu otevřít a ověřit po opravě. Poslední akce uspěje pouze při aktuálním
   jednoznačném `matched`; neposkytuje výjimku z UCI resolveru ani editor karty.
   Schvalování celého baseline patří do dalšího pilotu, tento řez jej nezapíná.
3. Serializace na connection → run → aktuální actor → kmenové osoby/vazby;
   po čekání znovu ověřit oprávnění, revizi, zdroj a hash náhledu. Starý snímek,
   změněná identita či druhý souběžný zápis vrací konflikt bez částečného auditu.
   Rozhodnutí platí jen pro konkrétní řádek a nezměněný kontext; nesmí potlačit
   nový snímek či nově vzniklý konflikt. Výsledek resolveru a stav řešení oddělit.
4. Přehled zobrazí stav řešení a filtr fronty. Detail zpřístupní celou historii
   stránkovaně. Audit obsahuje povinný důvod a interní reference; při ověření
   jednoznačné osoby doplnit referenci do její existující historie.
5. Registrářský panel ČSC / Sportsoft na kartě osoby: jen přijaté aktuální
   snímky, čerstvá shoda UCI, více licencí, původ/čas/stáří; bez cizích nebo
   konfliktních vazeb a bez N+1. Návrhy čekající na kontrolu nejsou data osoby.
6. Testy: syntetický lifecycle rozhodnutí, stale preview/revision/snapshot,
   role a CSRF, souběh a revokace při čekání MariaDB, rollback auditu, nový
   snímek/změna UCI, panel a query budget, backup/restore, HTTP a browser.
   Průběžně zapsat PASS/FAIL/NOT RUN a předat vlastní draft PR.

Akceptace: panel a celé auditované řešení dostupné registráři, žádné vynucené
párování, zápisy pouze integračních rozhodnutí/auditu a oprávněné historie
osoby; kmenové údaje, licence, přihlášky a platby beze změny. Dosud NOT RUN.

### Průběžný výsledek implementace navazujícího S4

Implementace HOTOVÁ LOKÁLNĚ, před push/CI; nejde o nasazenou ani aktivní funkci.
Panel je v záložce KIS na kartě osoby a načítá se pouze registráři (i při
přepínání záložek bez reloadu). Obsahuje přijaté licence, členství a přihlášky,
25 řádků na stránku, původ/čas/stáří a odkaz na audit. Konflikt stejného UCI
kdekoli ve stejném snímku zabrání zobrazení dotčených řádků. Kalendář závodů
není údajem osoby. Přehled dostal filtr stavů řešení; detail rozhodnutí je
samostatná trasa `external_sync_review.php`, historie po 20 událostech.

Nová migrace `20261107230000_external_sync_decisions` přidává devátou vlastněnou
integrační tabulku. Backup kontrakt `2026-11-07.2` a restore fixture zahrnují
neprázdné rozhodnutí. S1 kontrakt ani stanice se nemění. Rozhodnutí nevytváří
identity link a nemění přijatý snímek; `resolved` potvrzuje aktuální výsledek
S1 resolveru a zapíše referenci do historie osoby. Každý nový pokus (i neúspěšný)
konzervativně zneplatní dosavadní hash rozhodnutí; nové posouzení je výslovné.
Zdrojová data ani kmenové údaje se v tomto workflow neopravují. Historické
snímky jsou pouze ke čtení, nový snímek nepřebírá rozhodnutí předchozího.

- PASS lokálně: S1 + oba S4 integrační testy, skutečné HTTP GET/POST,
  migrace/backup ownership, registr tras a anonymous policy: **59 testů,
  3378 assertions, 2 očekávané SQLite skips** (nativní MariaDB lock-wait).
- PASS HTTP: role/CSRF/array vstupy, nepovolený forced-resolve, 303 po zápisu,
  409 při replay, escapování důvodu, čtení panelu; GET nemění hash dat.
- PASS logika: odložit → nespadá → otevřít, oprava UCI → ověřit, audit/ref
  osoby, duplicitní UCI zneplatní rozhodnutí, rollback při chybě historie,
  starý snímek, historie 20+3, více licencí a zdroje, návrhy se nezobrazují.
- PASS budget: panel nejvýše **7 SELECT**, přehled s workflow nejvýše **11**;
  počet nezávisí na počtu řádků, části se načítají hromadně.
- PASS style check po mechanickém zalomení dlouhých řádků existující karty.
  První kontrola backup seznamu odhalila požadavek na jeden název tabulky na
  řádek; formát opraven a celý výše uvedený testovací výběr prošel.
- Browser ROZPRACOVÁNO: skutečný lokální formulář uložil syntetické odložení
  a historii revize 1, 390 px bez horizontálního přetečení. Celá KIS session
  a portály nejsou v tomto harnessu; bootstrap/session/header jsou testové.
- MariaDB 10.3/11.4 lock-wait, plný Linux CI a obnova deváté tabulky čekají
  na PR. Deploy, merge, aktivace, portály a data Rikiho **NOT RUN**.

### Převzetí souběžné dokumentace / draft PR

Push `3e510713` a [draft #382](https://github.com/KovoPraha/evidenceTreninku/pull/382).
Při otevření PR main postoupil na `eafe835b` (#375 P11-SCALE). Převzato do
vlastní větve; konflikty pouze v dokumentačních přehledech, logy i katalog
zachovávají oba výsledky. Nejde o merge PR do main ani o deploy.
Browser dále PASS: filtr „Odloženo“ vrací jeden rozhodnutý řádek; panel na
390/1440 px bez přetečení, původní UCI s nulami a licence zůstávají zobrazené.
