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
