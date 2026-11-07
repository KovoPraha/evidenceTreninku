# P15 S4 — čtecí přehled pro registráře

Datum: 7. 11. 2026. Stav: implementace čtecího řezu HOTOVO, OVĚŘOVÁNÍ; vlastník Marek / KIS Codex.
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
  CI dokumentace PASS; S1/S4 MariaDB krok na 10.3 i 11.4 PASS. Celé CI probíhá.
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
Finální CI po úpravě textů běží; předání výsledků následuje.

SQL rozpočet detailu je nejvýše 10 dotazů včetně ověření role a pozorování
dávek, stejně pro 50 i poslední 1 řádek ze snímku s 301 řádky. Celý snímek
se pro čerstvé filtrování prochází; měření maximálních 100 000 řádků NOT RUN.
S4 nemá novou migraci ani zápisové API. Příjem zůstává pod původním OFF
flagem S1. Zobrazení návrhu nikdy neposune ukazatel přijatého snímku.
