# P11-ASSET11 — administrátorský přehled bez duplicitního CSS

13. 11. 2026. [PR #470](https://github.com/KovoPraha/evidenceTreninku/pull/470)
sloučen jako cd04bad6eef682f46a9ba20ddb79f3a36e0b17f5. Testovaný commit 1507b1e1f7539bdedd22ee84a34b2be1c57a475d, shodný Git tree
b61eacacf96e595308a573ac5383865157514f4f. Všech 15 kontrol PASS.
[Párový běh](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43384657393), [kontroly](application-checks.json).

## Rozsah a shoda

Odstraněna přesně první CDN značka Bootstrapu 5.3.3 z admin_dashboard.php,
tedy dvě řádky PHP echo. Sdílená místní kopie z hlavicka.php zůstává.
Reference 722099b7 už obsahuje předchozí agregaci P11-ADM, proto tento
profil měří pouze CSS. Zdrojový guard v CI vyžaduje přesně tento rozdíl
a nezměněný includes/admin_dashboard_read.php. Počet SQL se tímto řezem nemění.

MariaDB 10.3/11.4: každá 13 HTTP scénářů na 10 221 syntetických osobách.
system_admin 200 má 4 zahřívací a 17 střídavých měřených párů; dalších
11 přihlášených účtů 403 a anonymní 302 mají po jednom kontrolním páru.
Celé HTML a sledované bezpečnostní hlavičky se shodují po normalizaci
nonce a odstranění jedné přesně určené CSS značky z baseline.
Počty Bootstrap značek jsou výslovně před 2 / po 1; lokální značka je
vždy jedna, CDN před jedna / po nula. Query cache vypnutá.
Kontrolní součty všech 358 tabulek shodné na obou databázích, úklid
syntetických schémat, účtů, konfigurace a portů PASS.
[10.3](mariadb-10.3/http.json.gz), [11.4](mariadb-11.4/http.json.gz).

Historický profil agregace současně prošel na obou databázích:
12 nativních kombinací, původní 4 SQL proti optimalizovaným 2 SQL,
shodné hodnoty a kontrolní součty. Jeho zdrojová reference zůstává
5cd6ad07, HTTP má pouze úzkou kompatibilitu pro právě odebranou značku.
Nejde o nově vykázanou SQL úsporu tohoto CSS řezu.
[Regrese 10.3](counts-regression-10.3.json), [11.4](counts-regression-11.4.json).

Chrome CSS profilu: 88 měřených + 24 zahřívacích navigací. Desktop
1036 × 703 light, mobile 390 × 844 dark, cold/warm cache. Dvě full-page
dvojice snímků pixelově shodné, 11 karet, odkazy a geometrie shodné,
Bootstrap JS dostupný a žádná chyba stránky. Dekódované tělo každé
kopie CSS má ověřený SHA-256 i SHA-384 proti místní kanonické verzi.
[Snímky a časování](mariadb-11.4/browser-summary.json).

## Měření

Studený přenos Bootstrap CSS 64454 → 31248 B, úspora 33206 B
(51.52 %). Dekódované CSS 465 606 → 232 803 B (−50 %).
Teplý přenos 0 → 0 B. Jde o skutečná těla odpovědí v Chromu,
nikoli součet velikostí zdrojových souborů.

Průběžný běh fe11fb95 naměřil 53 957→31 248 B (−42,09 %).
Finální běh 1507b1e1 dostal větší komprimovanou CDN reprezentaci;
dekódovaný obsah i ověřené otisky zůstaly stejné. Úsporu proto počítáme
vždy uvnitř jednoho párového běhu a nerozšiřujeme ji na jiné prostředí.

| Režim | Cache | CSS B před → po | FCP ms před → po | Ready ms před → po |
| --- | --- | ---: | ---: | ---: |
| light | cold | 64454 → 31248 | 84 → 80 | 113.5 → 109.1 |
| light | warm | 0 → 0 | 60 → 60 | 63.2 → 79.7 |
| dark | cold | 64454 → 31248 | 80 → 76 | 111.4 → 104.4 |
| dark | warm | 0 → 0 | 56 → 56 | 80.4 → 69.0 |

Celé HTTP správce, mediány 17 párů v ms:

| MariaDB | Před | Po |
| --- | ---: | ---: |
| 10.3 | 9.818 | 9.756 |
| 11.4 | 15.076 | 15.087 |

Časy jsou syntetické CI měření a podléhají kolísání runneru. Přijatý
přínos je odstranění přenosu jedné kopie CSS; změna SQL ani produkční
časový příslib z toho nevyplývají. [Všechny HTTP mediány](http-medians.json).
Lokální SharedUiShellTest a ShopAdminWiringTest: 21 testů / 155 kontrol PASS;
syntax, PSR-12 a kontrola dokumentace PASS.

## Vydání

[Deploy 43386354560](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43386354560) SUCCESS, 2467 testů / 56603 kontrol.
Ověřená záloha 394 tabulek a 2 triggerů,
konfigurace zachovaná, migrace bez pending, VS i zdravotní index shodné,
aktivace a odstranění kopie konfigurace PASS. Přímý test z runneru
vrátil HTTP 0 (spojení nedokončeno); workflow ověřilo serverový smoke
s HTTP 200. UAT/import false. Produkční anonymní GET přehledu má
shodné 302 a místní Bootstrap shodný SHA-256 před/po.
Přihlášené produkční UAT a živý autentizovaný waterfall NOT RUN.
[Doklad](release.json), [před](production-before.json), [po](production-after.json).

Původní fio checkout d1221f35 a kis_rosters_admin.php (SHA-256 7a7afd75…)
zachované. Čerstvá kontrola zdrojů potvrdila další kandidáty k měření: duplicitní
Bootstrap club_calendar.php, club_programs_admin.php a eshop_catalog_admin.php.
Další implementace zatím NOT RUN; před výběrem ověřit aktuální zdroje a četnost.
