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
  Celkové finální testy, browser a CI probíhají; push/PR S4 zatím NOT RUN.
  Merge/deploy/aktivace: NOT RUN.
