# P11-RIX — průběžná diagnóza a ověření

9. 11. 2026. ROZPRACOVÁNO. [Plán](../../ATHLETE_PROFILE_TARGETED_READ_2026-11-09.md).
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
