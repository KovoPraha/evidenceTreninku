# P11-ASSET13 — klubový kalendář a správa katalogu

14. 11. 2026, Europe/Prague. NASAZENO #481/837a7112afdd53f1a84d3c875e14b2948ea94f3f, testovaný/sloučený strom a2b779bf36481c8df879dc88de2d4965cffca979, 13 CI PASS.
[Plán](../../BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset13--klubový-kalendář-a-správa-katalogu-14-11-2026),
[PR #481](https://github.com/KovoPraha/evidenceTreninku/pull/481).
Testovaný head b0fde0bcf8963d1a96b468fa683311b6ac9412a1, immutable baseline f9b27e4ca0e12769e702a3ff3c170fcb7d05b09e,
[párový běh 43648335428](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43648335428).

## Změna a přesná shoda

Z club_calendar.php a eshop_catalog_admin.php funkčně odebrán jen přesný
CDN Bootstrap5.3.3 CSS tag. Společný lokální payload/hash/SRI zůstává.
Ikony, kalendářové styly, JS, SQL hodnoty, role a zápisové akce zachované.
Povinné formátování starých minifikovaných šablon je hlídané source contractem:
celé literální HTML kromě přesného CSS tagu a všechny PHP tokeny shodné,
kromě 21/9 připnutých editů guard závorek, jednoho hodnotově stejného
rozdělení SQL literálu a stejného staticky ověřitelného tagu ikon emitovaného
spojením literálů. Žádná obecná výjimka z logiky nebo stylu.
[Source contract10.3](mariadb-10.3/source-contract.json),[11.4](mariadb-11.4/source-contract.json).

## MariaDB a celé HTTP

Vlastněný syntetický plně migrovaný FullHttpReadFixture; čtyři kalendářové
stavy s osobou/odkazem a katalog. Na každé MariaDB 10.3/11.4 celkem 33 scénářů:
všechny pracovní pozice na obou cestách, anonymní 302, odmítnuté 403,
kladné role, detail/nenalezená akce/vyhledávání, prázdný měsíc, katalogový
filtr, prázdný a neplatný filtr. Kladné 4 warmup / 17 střídavých párů,
negativy 1 pár. Query cache off, obyčejné PDO přes skutečné HTTP.

Celé HTML i sledované bezpečnostní hlavičky shodné po odstranění pouze
přesného tagu a request CSP nonce. CSRF v téže cookie jar zachované.
Každé kladné HTML je přesně o 210 B menší, pořadí/hodnoty/formuláře/odkazy stejné.
Na obou DB všech 356 shodných checksumů tabulek a úklid schématu,
účtů/configu/portů PASS. Žádný serverový SQL zisk se tímto řezem neprokazuje.
[HTTP10.3](mariadb-10.3/http.json.gz),[11.4](mariadb-11.4/http.json.gz).

| MariaDB | Scénář | Medián celého HTTP ms před → po |
| --- | --- | ---: |
| 10.3 | calendar-coach | 13.518 → 13.155 |
| 10.3 | calendar-sports_lead | 10.999 → 10.895 |
| 10.3 | calendar-program_coordinator | 11.287 → 11.123 |
| 10.3 | catalog-catalog_manager | 42.321 → 42.998 |
| 10.3 | calendar-finance_manager | 11.336 → 11.220 |
| 10.3 | calendar-other_coach | 13.259 → 13.328 |
| 10.3 | calendar-detail | 15.104 → 15.216 |
| 10.3 | calendar-empty | 11.364 → 11.378 |
| 10.3 | calendar-missing | 13.548 → 13.390 |
| 10.3 | calendar-search | 21.765 → 21.672 |
| 10.3 | catalog-filter | 41.695 → 42.302 |
| 10.3 | catalog-empty | 31.486 → 31.200 |
| 10.3 | catalog-invalid | 41.384 → 41.607 |
| 11.4 | calendar-coach | 10.552 → 10.514 |
| 11.4 | calendar-sports_lead | 9.012 → 8.921 |
| 11.4 | calendar-program_coordinator | 7.959 → 7.887 |
| 11.4 | catalog-catalog_manager | 29.686 → 29.932 |
| 11.4 | calendar-finance_manager | 8.070 → 8.103 |
| 11.4 | calendar-other_coach | 9.456 → 9.384 |
| 11.4 | calendar-detail | 10.704 → 10.739 |
| 11.4 | calendar-empty | 8.164 → 8.100 |
| 11.4 | calendar-missing | 9.606 → 9.798 |
| 11.4 | calendar-search | 18.198 → 18.150 |
| 11.4 | catalog-filter | 30.228 → 30.267 |
| 11.4 | catalog-empty | 21.886 → 21.989 |
| 11.4 | catalog-invalid | 29.920 → 30.235 |

## Skutečný Apache a Chrome

16 scénářů: přehled/detail kalendáře a přehled/filtr katalogu, desktop 1036×703
light a mobile 390×844 dark, cold/warm. 352 měřených +96 warmup navigací.
Osm full-page pixelových párů, stejné formuláře a geometrie/computed styles,
Bootstrap JS dostupný; bez page/console/network chyb. Skutečné místní i CDN
requesty, bez mockování nebo umělé latence. Payload SHA256/SRI obou kopií
ověřený. CSS decoded 465606→232803 B, dvě kopie→jedna. Cold úspora v měřených
scénářích 22709 B, warm 0→0 B. Konkrétní časy uvádíme i tam,
kde rozdíl není průkazný; nejsou produkční SLA.

| Cesta/téma | Cache | CSS přenos B před → po | FCP ms před → po | Ready ms před → po |
| --- | --- | ---: | ---: | ---: |
| club_calendar-overview / light | cold | 53957 → 31248 | 88 → 76 | 116.3 → 106.9 |
| club_calendar-overview / light | warm | 0 → 0 | 60 → 60 | 69.8 → 74.2 |
| club_calendar-overview / dark | cold | 53957 → 31248 | 84 → 76 | 113.2 → 101.6 |
| club_calendar-overview / dark | warm | 0 → 0 | 60 → 56 | 75.3 → 76.2 |
| club_calendar-detail / light | cold | 53957 → 31248 | 96 → 88 | 118.7 → 108.4 |
| club_calendar-detail / light | warm | 0 → 0 | 72 → 68 | 81.9 → 82.2 |
| club_calendar-detail / dark | cold | 53957 → 31248 | 92 → 84 | 120.3 → 113.6 |
| club_calendar-detail / dark | warm | 0 → 0 | 68 → 64 | 89.1 → 84.9 |
| eshop_catalog_admin-overview / light | cold | 53957 → 31248 | 112 → 108 | 138.2 → 137.1 |
| eshop_catalog_admin-overview / light | warm | 0 → 0 | 88 → 84 | 90.1 → 99.7 |
| eshop_catalog_admin-overview / dark | cold | 53957 → 31248 | 108 → 100 | 133.4 → 131.4 |
| eshop_catalog_admin-overview / dark | warm | 0 → 0 | 84 → 80 | 102.4 → 102.6 |
| eshop_catalog_admin-filter / light | cold | 53957 → 31248 | 112 → 104 | 139.5 → 129.2 |
| eshop_catalog_admin-filter / light | warm | 0 → 0 | 84 → 80 | 100.1 → 92.8 |
| eshop_catalog_admin-filter / dark | cold | 53957 → 31248 | 108 → 104 | 132.4 → 132.1 |
| eshop_catalog_admin-filter / dark | warm | 0 → 0 | 84 → 80 | 101.3 → 103.7 |

[Browser souhrn a snímky](mariadb-11.4/browser-summary.json),[HTTP mediány](http-medians.json).

## Kontroly a vydání

Místní 15 testů / 92 kontrol SharedUiShell PASS, cílený PHPStan, PSR12/200/braces,
source contract, syntaxe PHP/JS/Python, docs/diff PASS. Stávající testy guardují
společný asset pro tyto dvě stránky; ostatní staré stránky zůstávají v inventáři.
Nezměněné pozitivní/negativní SRI testy lokálně 2/9 PASS. První CI brána
byla odmítnutá na zděděném počtu formulářů a statickém důkazu ikon; úzká
oprava i původní neúspěch jsou v [checkpointu](checkpoint-1.json).
Před prvním merge vstoupil do main dokumentační PR #476; všechny jeho
aktualizace zachované, naše aplikační/testové/workflow Git bloby shodné. Přesto
znovu provedena celá aktuální brána; [doklad převzetí](rebase-checkpoint.json).

[Chráněný deploy 43649462751](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43649462751) SUCCESS.
2479 testů / 56982 assertions; záloha 394 tabulek / 2 triggery.
Konfigurace zachovaná bez doplnění, pending migrace [], původní VS i zdravotní
index shodné; aktivace, HTTP 200 a úklid config-copy PASS.
Přímé spojení z runneru nedokončené (HTTP 0); podporovaný server-side
smoke HTTP 200 PASS. Import přeskočený, UAT/import false.
[Receipt](release.json). Nezávislé anonymní GET obou administrací a CSS bytes/
hash/cache před/po stejné ([před](production-before.json),[po](production-after.json)).
Přihlášené produkční UAT a produkční přihlášený waterfall NOT RUN. Syntetický
Chrome není produkční SLA. Skutečné akce, účasti, objednávky, sklady ani role
nebyly tímto ověřením měněné. Doklady samostatným PR bez dalšího nasazení.

Původní fio/d1221f35 a kis_rosters_admin.php SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 zachované.
Ostatní rozpracované PR/prototypy tímto nesloučené, souběžná dokumentace
#476 zachovaná a lidské blokátory otevřené. Další kandidáti:
zbylé administrativní CSS duplicity, nebo velikost seznamů až po samostatném
profilu a doloženém objemu; žádný další neověřený prototyp tímto nevydávat.
