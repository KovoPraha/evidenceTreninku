# Předání P11-R13-F — hledání a datum zdravotního přehledu

7. 11. 2026: ROZPRACOVÁNO, plán7.11. před implementací; pokračování8.11.2026.
[Plán](MEDICAL_FILTER_READ_PERFORMANCE_2026-11-07.md), [výsledky](verification/medical-filter-read-performance-20261107/RESULTS.md).
Nové zadání dovoluje commit/merge/deploy po shodě, přínosu a zelených kontrolách.
Větev `codex/medical-filter-read-performance-20261107`, vlastní dřívější čistý worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107`.
Base35fdad44, dosavadní produkce938e9c50/#381; její stavový řez a důkazy zachovat.
Plán: bounded kandidáti, prázdné/vzácné výsledky původním čtením nad ID;
široké stavové hledání bez vnitřních JOIN lidí, datum omezí i historii.
DDL/cache/rekurze neplánované. Nezměnit přímou osobu/transakce/práva/writer/dokumenty.
Další krok: zmrazená reference, implementace, parita SQLite/nativní obě DB,
párové cache-off helper/HTTP/Chrome10k/100k, důkazy, kontroly, teprve pak release.
Původní WIP SHA256 `7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5` shodný;
staré untracked výstupy původního checkoutu i tohoto worktree zachované.
První implementace/testy/měření hotové, PR #385 otevřené; merge/deploy nového řezu NOT RUN.

## Průběh implementace

První implementace hotová lokálně, přesný současný baseline35fdad44 včetně jeho stavového readeru zmrazený nezávisle. Nová sada7/454 a společná regrese25/801 PASS, styl/lint PASS. Přímá osoba a transakce zachovávají SQL/bindingy; předchozí test požadující původní SQL i u q/data nahrazen novým kontraktem výsledků podle D15. Nativní profiler14případů×4objemy100/1k/10k/100k,17střídavých párů a4zahřátí s query cache OFF. Samostatné nativní joby nezvětšují rozpočet staré integrační sady. HTTP benchmark porovnává přesné dva původní aplikační soubory, všech30k/300k záznamů a rozložená data revizí na jednom seedu;15kladných/12záporných případů a6Chrome scénářů. Výkon zatím NOT RUN; implementace je kandidát k měření, nikoli k deployi.

## Pokračování8.11.2026

cc2a825e má15CI PASS a celý GET100k hledání907,81→462,51ms. Smíšené nativní datum_day a date_to regredovaly, proto zatím nenasazovat. Bezpečný archiv v protokolu. Další varianta pouze pro široké datumové stavy použije korelované nejnovější verified/poslední ID přes existující watch_id/id index; původní pure status/future/fallback beze změny. Hledání a kandidátní sonda zachované. Znovu přesná parita a cache-off oba DB/HTTP/Chrome páry před release. WIP zachovaný.

Datumová varianta2 implementovaná: jeden korelovaný nejnovější verified přes watch_id/id; poslední libovolný záznam jen když verified chybí. Datum je next_review_on sledování, historie ani explicitní řetězec nejsou ořezané podle issued_on/valid_until. Vše v původním počtu SQL, bez N+1 síťových dotazů a bez DDL. První nativní7/466 na každé DB PASS; aktuální lokální a nové nativní/HTTP ověření V2 ještě běží.

V2 korelované datumové lookupy měly na nativním11.4 ještě vyšší režii (date_to502,30→748,19ms), zamítnuté. V3 použije jedinou agregaci kandidátů/head ID s původním WHERE a MAX(next_review_on), poté jen head records; vnější people/watch filtry se neopakují. Nejde o další korelovaný lookup ani o změnu indexů. Nativní i migrované CI indexy jsou zaznamenané; produkční indexový inventář NOT RUN. Nová parita/přínos před release.

V3 doplnění: filtrované okno třídí pouze ID sledování a dvou head záznamů; metadata se připojí až vně okna pro vybranou stránku v témže SQL. Výchozí stavový seznam bez doplňkových filtrů zůstává původní. Menší pracovní sada je hypotéza k novému měření, nikoli tvrzení o skutečných rows examined nebo diskových tempech. Lokálně18/751 PASS, styl/lint PASS. Nová nativní/celá HTTP měření čekají; bez nasazení.

V3 datum_day na11.4 253,72→267,98ms mírně regresní; V4 anti-join latest verified pro datum a HAVING pro stavy bez verified s explicitními budoucími výjimkami. Další2pending hledací případy v benchmarku a datumové legacy grafy v paritě. Bez DDL, nové indexy se nezavádějí; skutečné produkční indexy se tím neprohlašují za prověřené. Měření/testy V4 před release.

V4 lokálně26/813 PASS včetně nových12datumových legacy kontrol; nativní profiler nyní16případů×4objemy×17párů/DB, celkem2176párů. HTTP17kladných/12záporných případů a7Chrome scénářů. Indexově závislá korelovaná V2 zamítnutá; do deploye jde až vítězná ověřená cesta bez DDL. Produkční indexový inventář zůstává NOT RUN.
