# P11-RHM — ověření shody a měření

9. 11. 2026: finální bounded selector. [Plán](../../ATHLETE_PROFILE_MEASUREMENT_PERFORMANCE_2026-11-09.md), [předání](../../SESSION_HANDOFF_ATHLETE_MEASUREMENT_2026-11-09.md).

Dvě agregace byly odmítnuty; původní vzorky jsou zachované v rejected-json/ a rejected-integer/. Finální kód ponechává původní souhrn, mění pouze výběr detailů dlouhých přehledů: globálníprefix64 a úplný původní fallback pro neplnou stránku, total>800/page<=32. Scope a přesný typ se kontrolují znovu.

## Metoda

Linux CI, PHP8.2, MariaDB10.3/11.4, cacheOFF. Zmrazený reader+parent+endpoint z5ff9fa2a65123aa1eef58be3678943cb4bfc2b53. Plně migrovaná izolovaná syntetická DB, stejné file-session, čtyři PHP workers na variantu. Skutečné přihlášení a explicitní trenérský rozsah; žádné podvržené session. 1k/10k/30k tréninků a až270k vlastních vazeb. Devět scénářů, čtyři warmups a17střídavých párů:918časovaných AJAX naDB,1836celkem. Výběr osoby přes parent před každým requestem není součástí časovaného AJAX. Raw vzorky obsahují celé signature a TTFB/total, nikoli těla odpovědí.

SQL/PHP diagnostika je samostatný request mimo časování. PHPmanagedpeak obsahuje SQLprofiler, ale v posledním běhu se zachytí před pozdějším EXPLAIN/schema; není to peak prostého requestu, RSS ani paměťDB. Chrome52měřených navigací: default i uložená záložkaMěření,3cold+10warm na scénář/variantu. DokumentTTFB, fetch→DOM+2frames a celá navigace odděleně; nejde o LCP ani produkční SLA.

## Párové HTTP mediány (ms)

| DB | Tréninky | Scénář | Před | Po | Změna |
| --- | --- | --- | ---: | ---: | ---: |
| 10.3 | 1000 | primary | 19.710 | 15.103 | -23.4% |
| 10.3 | 10000 | primary | 163.592 | 115.982 | -29.1% |
| 10.3 | 30000 | primary | 590.973 | 424.092 | -28.2% |
| 10.3 | 30000 | exact_case | 623.782 | 429.092 | -31.2% |
| 10.3 | 30000 | exact_space | 608.838 | 406.746 | -33.2% |
| 10.3 | 30000 | page2 | 607.216 | 409.907 | -32.5% |
| 10.3 | 30000 | last | 653.883 | 618.535 | -5.4% |
| 10.3 | 30000 | absent | 613.394 | 596.162 | -2.8% |
| 10.3 | 30000 | small | 335.916 | 318.400 | -5.2% |
| 10.3 | 30000 | old | 333.789 | 346.895 | +3.9% |
| 10.3 | 30000 | empty | 328.996 | 326.671 | -0.7% |
| 11.4 | 1000 | primary | 45.601 | 33.938 | -25.6% |
| 11.4 | 10000 | primary | 446.269 | 305.944 | -31.4% |
| 11.4 | 30000 | primary | 1348.186 | 915.044 | -32.1% |
| 11.4 | 30000 | exact_case | 1341.120 | 919.367 | -31.4% |
| 11.4 | 30000 | exact_space | 1346.847 | 914.332 | -32.1% |
| 11.4 | 30000 | page2 | 1343.842 | 916.256 | -31.8% |
| 11.4 | 30000 | last | 1394.505 | 1395.052 | +0.0% |
| 11.4 | 30000 | absent | 1334.649 | 1329.971 | -0.4% |
| 11.4 | 30000 | small | 795.790 | 794.690 | -0.1% |
| 11.4 | 30000 | old | 796.107 | 801.872 | +0.7% |
| 11.4 | 30000 | empty | 795.753 | 792.517 | -0.4% |

## SQL a PHP diagnostika

| DB | Varianta | SQL | Vrácené řádky | PHP peak B | Souhrn ms | Detail ms |
| --- | --- | ---: | ---: | ---: | ---: | ---: |
| 10.3 | before | 17 | 48 | 683784 | 435.074 | 193.888 |
| 10.3 | after | 17 | 48 | 717720 | 446.220 | 4.115 |
| 11.4 | before | 17 | 48 | 683768 | 900.696 | 439.186 |
| 11.4 | after | 17 | 48 | 683896 | 911.234 | 6.670 |

## Chrome

| Scénář | Cache | Metrika | Před ms | Po ms | Změna |
| --- | --- | --- | ---: | ---: | ---: |
| default | cold | document_ttfb_ms | 464.40 | 470.10 | +1.2% |
| default | cold | ajax_pair_render_ready_ms | 544.30 | 544.50 | +0.0% |
| default | cold | navigation_to_both_ready_ms | 1054.00 | 1052.30 | -0.2% |
| default | warm | document_ttfb_ms | 468.45 | 471.20 | +0.6% |
| default | warm | ajax_pair_render_ready_ms | 539.95 | 542.25 | +0.4% |
| default | warm | navigation_to_both_ready_ms | 1049.20 | 1053.40 | +0.4% |
| measurements | cold | document_ttfb_ms | 465.80 | 469.00 | +0.7% |
| measurements | cold | ajax_pair_render_ready_ms | 1498.10 | 1052.10 | -29.8% |
| measurements | cold | navigation_to_both_ready_ms | 2008.80 | 1559.40 | -22.4% |
| measurements | cold | measurement_fetch_to_render_ready_ms | 1498.10 | 1052.10 | -29.8% |
| measurements | warm | document_ttfb_ms | 466.80 | 469.20 | +0.5% |
| measurements | warm | ajax_pair_render_ready_ms | 1688.70 | 1456.55 | -13.7% |
| measurements | warm | navigation_to_both_ready_ms | 2191.60 | 1959.90 | -10.6% |
| measurements | warm | measurement_fetch_to_render_ready_ms | 1441.80 | 988.35 | -31.5% |

## Shoda a bezpečnost

Nativní5testů/624assertions na každéDB, SQLite5/312 PASS: úplný návratový objekt a PHPtypy proti zmrazené referenci, native/emulated prepares, stringify obě varianty, exactcase/mezery/Unicode/numerické/NULLtypy, nulové/NULLdatum, duplicity/orphans/foreign, signed64klíče a pozice, čerstvé změny,900vazeb jednoho tréninku a hranice32/33. GROUP_CONCAT limity128/4/1048576 výsledek neovlivňují a SESSION se nemění.

Celé AJAXfragmenty, parentHTML/JS,12staffrolí a4povolené kontexty, bearer303/retarget/expirace/revokace/scope/inaktivní účet, CSRF/logout,2nezávislé fallbacky parentu a11doménových checksumů PASS. Všechny vlastní workers/DB/uživatel/config/session/alias odstraněné. Zdrojové soubory po běhu beze změny. Žádné DDL/cache/session/UI/writer změny v aplikaci.

Raw důkazy: [10.3 identita](checked-head/native-10.3/source-identity.json), [11.4 identita](checked-head/native-11.4/source-identity.json), souhrny, gzvzorky, diagnostika, security, cleanup, XML a PNG ve stejných adresářích. Source SHA256 ověřené proti Gitblobům testovaného head a sloučeného kódu.

Přihlášené produkční UAT, produkční měření, fyzický mobil a obnova konkrétní produkční zálohy NOT RUN. Syntetické časy nejsou produkční SLA. Release se dokládá samostatně.

## Význam výsledku a kontrolní profily

Hlavní20%gate PASS na obouDB. Starý900MZprofil má kvůli úplnému fallbacku
1MZSQL navíc: +13.106ms/+3.9 % na10.3, +5.765ms/+0.7 % na11.4 při30k.
Kontrolní gate pro tento nový scénář v každé škále: after < before*1.2 +20ms;
PASS. Malé12/prázdné0 a vzácné filtry drží původní SQL. Nejde o tvrzení, že
všechny profily a stránky zrychlují. Hlavní17SQL→17SQL, detail194→4ms a439→7ms;
typový numeric-window souhrn zůstává dominantní a je byte-původní.

Chrome uloženéMěření warm: fetch→DOM+2frames1441.80→988.35ms (−31.5 %),
celá navigace2191.60→1959.90ms (−10.6 %). Cold celá navigace2008.80→1559.40ms
(−22.4 %). Default/historie warm1049.20→1053.40ms (+0.4 %), dokumentTTFB
přibližně stejný. Nepřičítat SQL úsporu celé navigaci ani historii.
Desktop/390px before/after PNG jsou byte-identické a vizuálně zkontrolované;
syntetické dlouhé poznámky mají na úzké tabulce původní ořez/scroll, žádné nové
mobilní UX ani fyzické mobilní UAT se tím nepotvrzuje. Konzole/transport0.

[Produkční předání](../athlete-profile-measurement-release-20261109/RESULTS.md):
#418/5cc9cf83,20CI/source/tree PASS, protecteddeploy42654922651 SUCCESS,
veřejné8/8HTTP/DOM shodné; konfigurace/VS zachované. Produkční výkon,
přihlášenéUAT a obnova této zálohy NOT RUN.
