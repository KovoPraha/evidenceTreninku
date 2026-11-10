# P11-RIX — průběžná diagnóza a ověření

10. 11. 2026. DIAGNÓZA UZAVŘENA: žádná SQL/index optimalizace přijata ani nasazena.
Aktivní [P11-ASSET](../../BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md) v [#425](https://github.com/KovoPraha/evidenceTreninku/pull/425).
Níže průběžné historické důkazy včetně zamítnutých variant. [Plán](../../ATHLETE_PROFILE_TARGETED_READ_2026-11-09.md).
Base mainb66cc059/runtime17110a7f; WIP SHA2567a7afd75 ověřený beze změny.

Zjistěno pouze ze zdrojů: poslední RHMS CI metadata mají PK-only pro
mereni_zaznamy/trenink_mereni, local-demo schema má idx_sportovec/idx_mereni.
Produkční metadatový audit a nový nativní A/B výsledek zatím NOT RUN.
Původní RHMS raw a výslovné syntetické hranice zachované; žádný nový
produkční index, aplikační změna nebo nasazení zatím nevznikly.


## Metadatový nástroj — místní ověření

CLI a source guard hotové, pracovní HTTP cesty/reader beze změny.
Pevných5tabulek, žádné default hodnoty/sloupcová data/credentials v reportu;
jen metadata, odhad řádků výslovně označený jako estimate. Bez COUNT historie.
SQLite2testy/30assertions PASS: nezměněný úplný doménový snapshot, žádné
soukromé hodnoty v JSON, správné pořadí složeného indexu a chybějící tabulky.
Inventuru test provádí v transakci (native větev READ ONLY); MySQL testy se
nově spustí v obou standardních smoke jobech. Native výsledek zatím NOT RUN.
Neplatný target odmítnut s exit1 a sanitizovaným error_type bez detailu DSN.
PHP syntax/style cílených dvou souborů PASS, docs5107/108/0 PASS.
Další krok: podpůrný PR, všechny aktuální kontroly, merge bez app deploye,
pak chráněný read-only produkční workflow a odpovídající fixture/diagnóza.


## Metadatová inventura — PASS

Support #424/ff182971, 11 CI SUCCESS; native metadata test2/30 oběDB PASS.
[Workflow42704641179](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42704641179)
SUCCESS včetně cleanup. [Production schema](production-schema.json) neobsahuje
žádné doménové hodnoty; read_onlytrue/domain_rows_readfalse/ddl_executedfalse.
[HTTP CLI guard](cli-http-guard.json): skutečný lokální GET404,0bytes, config
nenačtena. App runtime17110a7f zůstává. Nová nativní diagnóza teprve následuje.


První nový diagnostický běh42705202126 FAIL v přípravě: vyrovnání indexů
bylo před doplněním poradi existujícím syntetickým builderem. Žádné A/B
výsledky nevznikly, cleanup PASS oběDB. Příprava přesunuta za všechny
buildery; opravný běh následuje. SQL kandidáti EXISTS/owned JOIN/IN se
nejprve porovnají na přesné uspořádané narrow projekci (7párů/2warmupy),
nikoli jako přijatý HTTP přínos. Aplikační reader stále beze změny.

Druhý diagnostický běh42705556534 FAIL v syntetické topologii: historický HTTP
builder poznámek nemá produkční PK/id. Žádné naměřené přínosy; cleanup PASS.
Vlastněný builder nově doplní chybějící note identity a PK před oběma variantami.
Jeho změny jsou pouze v oddělené syntetické DB, produkční DDL stále false.


## Nativní diagnóza c9 — PASS

[42705957545](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42705957545),
headc9efb111, baselineff182971. Oba runtime readery byte-shodné; jde o
výběrovou diagnózu, nikoli hotový A/B přínos. Native measurement parita6/634
oběDB; 24plných bootstrap protokolů, shodné produkční definice indexů pěti
tabulek, bezpečné raw i source/checksum/cleanup v diagnostic-c9/native-10.3
a diagnostic-c9/native-11.4. Syntetické typy/signed/null odchylky zveřejněné.
HistorieJOIN není přijatá: husté all249→259/307→314ms; IN sparse zásadně
regresuje. Souhrn měření je nejdražší (jednorázově288/1021ms), parentheader
268/289ms, historie241/305ms. Sparse souhrn pod12ms; žádné produkční SLA.
Další krok porovnání grouped summary join order a narrow materializace.


## Aktuální vybraný reader — úplný gate probíhá

Po vyřazení summary order/narrow/HEX i indexových variant vybral nativní
[42709050977](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42709050977)
redundantní YEAR v measurement ownership historie. SQL probe7párů/2warmupy,
12scénářů striktně stejných orderedid/delka; velký aktuální rok10.3
382.575→160.521ms a11.4 386.842→171.171ms. Datumový interval nevybraný.
[Raw10.3](history-year-probe/native-10.3/candidate-selectors.json.gz),
[raw11.4](history-year-probe/native-11.4/candidate-selectors.json.gz).
To zatím není celý HTTP ani měření produkce.

Implementace v b9b4cda0, zmrazený celý ff182971 reader s jediným přejmenováním
exact. Windows3/285 PASS; native rozšířené signed64/partial-zero/extreme-year
potvrzení čeká nový checkpoint. [Úplný běh42709994135](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42709994135)
17střídavých párů/4warmupy,1k/10k/30k,14scénářů/oběDB, skutečný Chrome a
security/checksum/cleanup zatím probíhá. Nový dataset má actual small12/empty0
historií, všech5tabulek společně ANALYZE; žádný nový produkční index.
CI static/style/docs/CodeQL na b9 PASS, celé22CI ještě neuzavřené.
Nový app merge/deploy NOT RUN. Staré nevybrané výsledky a WIP zachované.


## První úplný běh b9 — VÝKON NEVYHOVĚL

42709994135: celá11.4 SUCCESS,10.3 FAILURE. Shodné HTML/security/checksum/
cleanup oběDB; nový native2/514 každá. Při30k10.3 36.221→157.399ms (regrese),
11.4 239.224→106.124ms (−55.6%). Na10.3 10k116.467→59.251ms,1k9.062→12.985ms.
[Raw10.3](first-b9/native-10.3/targeted-http-30000.json.gz),
[raw11.4](first-b9/native-11.4/targeted-http-30000.json.gz).
Rozdíl vůči probe: plně čerstvé TS/notes statistiky ovlivnily původní10.3plán.
Výkonový gate nesnižujeme; PR425 bez merge/deploy. Další probe1k/10k/30k,
fresh5tables, anti attendance v measurement větvi a CASE. Nová probe větev
codex/athlete-targeted-probe-20261109. Místní PHPStan úplný PASS bez chyb.
