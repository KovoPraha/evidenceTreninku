# P11-ASSET12 — správa programů bez duplicitního Bootstrapu

13. 11. 2026, Europe/Prague. [Plán a akceptace](../../BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset12--správa-kroužkových-programů-13-11-2026).
[PR #473](https://github.com/KovoPraha/evidenceTreninku/pull/473) sloučen jako
b2ca9f4b223fa88e4ac029eb13c74f49aaa3de1c; stejný testovaný Git tree 8a9d00fa3dff2fbdb43de4b04882bd8f454ff6d7.
Všech 13 kontrol PASS. [Deploy 43447880903](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43447880903) SUCCESS na stejném SHA.

## Změna a shoda

Odstraněna jediná CDN značka Bootstrapu 5.3.3 z club_programs_admin.php;
zůstává kanonická místní sada ze společné hlavičky. Source guard vyžaduje
přesně tento rozdíl proti bc8da209fc469df58f91e27a2fce2340ba47f785.
SQL, writery, role, podmínky, texty i ostatní HTML/PHP zůstávají původní.
Testovaný head acab26a83f1841bb72813cece6c11452fa44e30f.

[Párový běh 43445439575](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43445439575)
PASS, [provenience](paired-run.json). MariaDB 10.3 a 11.4: 16 scénářů
(přehled program_coordinator, 11 ostatních přihlášených účtů odmítnuto 403,
anonymní 302, editor programu/období a chybějící program) + prázdný přehled.
Každý z pěti kladných scénářů: 4 zahřátí a 17 střídavých měřených párů;
každý záporný scénář jeden kontrolní pár. SQL query cache vypnutá.

Celé HTML a sledované bezpečnostní hlavičky přesně shodné po odstranění
pouze jedné přesné 210B značky a request CSP nonce. CSRF tokeny v jednom
cookie jar se shodují bez obecného odstranění formulářů či atributů.
Právě 1 lokální CSS v obou, CDN 1 před / 0 po. Všech 355 tabulek má shodné
kontrolní součty na každé databázi. Prázdná data jsou samostatný syntetický
setup; po čtení je ověřený nezměněný prázdný stav i přesná obnova původních
řádků a všech checksumů. Schémata/účty/config/porty uklizeny.
[10.3](mariadb-10.3/http.json.gz), [11.4](mariadb-11.4/http.json.gz),
[prázdný 10.3](mariadb-10.3/empty-http.json.gz), [prázdný 11.4](mariadb-11.4/empty-http.json.gz).

Skutečný Apache/Chrome: 176 měřených + 48 zahřívacích navigací,
přehled a editor období, desktop 1036×703/light a mobile 390×844/dark,
cold/warm cache. Čtyři full-page dvojice pixelově shodné, geometrie,
computed styles a formulářové metody/akce/názvy/hodnoty shodné.
Bootstrap JS dostupný, žádné page/console/network chyby. Cold těla obou CSS
ověřená SHA-256 proti kanonickému souboru, jeho SHA-384 odpovídá SRI.
[Snímky a časování](mariadb-11.4/browser-summary.json).

## Přínos před a po

Studený přenos Bootstrap CSS **53 957 → 31 248 B**, úspora **22 709 B
(42,09 %)** na přehledu i editoru. Dekódované CSS **465 606 → 232 803 B
(−50 %)**. Teplý přenos **0 → 0 B**. Měří se skutečná těla odpovědí v
Chromu, nikoli velikosti souborů. Komprimovaná CDN reprezentace se mezi
běhy/prostředími může lišit; tato procenta platí pro tento párový běh.
Nekomprimované HTML je o přesně 210 B menší.

| Cesta / téma | Cache | CSS B před → po | FCP ms před → po | Ready ms před → po |
| --- | --- | ---: | ---: | ---: |
| overview / light | cold | 53957 → 31248 | 96 → 92 | 126.8 → 113.3 |
| overview / light | warm | 0 → 0 | 84 → 76 | 96.8 → 81.5 |
| overview / dark | cold | 53957 → 31248 | 92 → 88 | 123.4 → 115.6 |
| overview / dark | warm | 0 → 0 | 72 → 72 | 89.6 → 88.8 |
| terms / light | cold | 53957 → 31248 | 100 → 96 | 127.0 → 117.7 |
| terms / light | warm | 0 → 0 | 84 → 80 | 95.4 → 77.8 |
| terms / dark | cold | 53957 → 31248 | 100 → 92 | 125.0 → 118.0 |
| terms / dark | warm | 0 → 0 | 76 → 72 | 94.2 → 90.1 |

Celé HTTP přehledu, medián 17 párů: MariaDB 10.3 **23,647 → 23,548 ms**,
11.4 **22,716 → 22,566 ms**. Další případy a všechny vzorky jsou v
[HTTP mediánech](http-medians.json) a původních gzip protokolech.
Významné zrychlení serveru neprokázáno. FCP některých režimů klesá o 4 ms,
ready kolísá; syntetické časy nejsou produkční SLA. Přijatý přínos je
odstranění přenosu a zpracování druhé stejné kopie CSS.

## Testy a release

Místní cílený PHPUnit SharedUiShellTest, ClubProgramWiringTest a
ShopAdminWiringTest: **23 testů / 172 assertions PASS**. Composer validate
--strict, syntaxe PHP/JS/Python, změněné PHP PSR-12/200 znaků/braces,
check-current-docs a diff --check PASS. Celé Linux CI rozhoduje o přijetí.

Chráněný deploy: 2467 testů / 56603 assertions PASS;
záloha 394 tabulek / 2 triggery ověřena.
Stávající konfigurace zachovaná, žádná doplněná konfigurace; migrace current,
pending [], původní VS a definice zdravotního indexu shodné. Aktivace release,
HTTP smoke a úklid kopie produkčního configu PASS. UAT/import false,
import přeskočený. Přímý HTTP smoke z runneru 200 PASS. [Bezpečný receipt](release.json).

Nezávislé veřejné GET před/po: club_programs_admin.php 302 se stejným cílem
a místní Bootstrap 200 s přesně stejnými bytes/hash/cache hlavičkou PASS.
[Před](production-before.json), [po](production-after.json).
Jde o anonymní kontrolu a syntetickou přihlášenou přejímku v CI.
Přihlášené produkční UAT, produkční přihlášený waterfall, fyzický telefon
a produkční časový benchmark: NOT RUN. Žádné skutečné programy, podmínky,
objednávky, platby, e-maily ani role testem měněné nebyly.

Původní fio checkout d1221f35 a jeho kis_rosters_admin.php WIP SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 zachovaný.
Výsledková dokumentace má samostatné PR bez dalšího nasazení runtime.

## Další kandidáti

Duplicitní Bootstrap na club_calendar.php a eshop_catalog_admin.php zůstává
zdrojově potvrzený, vlastní waterfall/parita/měření se musí teprve provést.
Ve správě programů je další kandidát clubProgramAdminOffers(): pro každou
nabídku znovu skládá kapacitu a účinné podmínky. Před změnou nejdřív změřit
reálný objem, SQL růst a celé HTTP; malá nezávislá výběrová diagnostika SQLite in-memory nyní ukazuje
0/2/12/100 nabídek = 1/43/253/2101 SQL, stabilní výsledky dvou čtení.
Nejde o nativní výkon, paritu implementace před/po ani produkční objem.
[Reprodukovatelný probe](admin-offers-probe.php.txt), [výsledky](admin-offers-diagnostic.json).
Nová dávková implementace, MariaDB a objemový HTTP profil zatím NOT RUN.
P11-RHC2 a P11-ADM jsou již nasazené a znovu se neimplementují.
