# Bootstrap při zápisu, úpravě a kopírování tréninku

P11-ASSET10, 12. 11. 2026. Výchozí main c11a1937. [PR #455](https://github.com/KovoPraha/evidenceTreninku/pull/455)
sloučen jako `dbab9e6e`. Celý Git tree `bc0854612043bcb5a20ccd121d448a5de4fa953d`
je shodný s testovaným `da41c0d059e8ad03da09605450a04a9de14fa672`. [15 kontrol](application-checks.json) PASS.

## Shoda výsledků

[Párový běh 43163176775](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43163176775)
s izolovaným PHP, Apache a MariaDB 10.3 a 11.4. Sedm pozitivních cest s 17 páry
na verzi zahrnuje prázdný zápis, předvyplnění z plánu a vzoru, úpravu, seznam
vzorů a dva týmové filtry. Čtyři kontrolní páry ověřují anonymní 302 a úpravu
cizího tréninku 403. Na každé verzi je 246 měřených HTTP odpovědí a shodné
kontrolní součty 354 tabulek po zkoušce. Celé HTML a sledované bezpečnostní
hlavičky jsou shodné po normalizaci nonce, přesné značky Bootstrapu a
zalomení atributu samostatných Bootstrap Icons v kopírovacím seznamu.
První běh na MariaDB 11.4 ukázal rozdíl v předvyplněném formuláři při
shodných syntetických jménech. Po jejich rozlišení prošlo celé porovnání.
[HTTP 10.3](mariadb-10.3/http.json.gz), [HTTP 11.4](mariadb-11.4/http.json.gz),
[zdrojový rozdíl](mariadb-11.4/source.json).

Chrome provedl 264 měřených navigací po 72 zahřívacích. Desktop 1036 × 703
ve světlém režimu a mobilní rozlišení 390 × 844 v tmavém režimu mají šest
pixelově shodných párů. Prohlížeč přidal řádek měření při zápisu i úpravě
a ověřil skrytí a znovuzobrazení vzoru po vyhledávání. Veškerá data byla
syntetická. Ovládání se ověřovalo bez odeslání formuláře.
[Mediány a velikosti](mariadb-11.4/browser-summary.json).

## Přenos a první vykreslení

| Metrika | Před | Po |
| --- | ---: | ---: |
| Bootstrap CSS, studený komprimovaný přenos na každé trase | 64 454 B | 31 248 B |
| Bootstrap CSS, velikost po dekompresi na každé trase | 465 606 B | 232 803 B |
| Bootstrap CSS, přenos z teplé cache | 0 B | 0 B |
| Zápis, FCP desktop / mobil za studena | 84 / 84 ms | 80 / 76 ms |
| Úprava, FCP desktop / mobil za studena | 92 / 84 ms | 80 / 72 ms |
| Výběr vzoru, FCP desktop / mobil za studena | 76 / 72 ms | 68 / 68 ms |

Studená úspora je 33 206 B (51,52 %). Výsledky vykreslení pocházejí z CI.
Rozdíly mediánů celého lokálního HTTP na pozitivních cestách jsou pod
0,3 ms. Teplé měření připravenosti má různé směry změny podle stránky.

## Vydání

[Deploy 43164465663](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43164465663) SUCCESS. 2456 testů a 56288 kontrol.
Záloha 389 tabulek a 2 triggerů ověřena.
Aktivace, konfigurace, migrace, variabilní symboly, index a úklid PASS.
Přímý smoke z runneru skončil HTTP 0. Serverový smoke následně vrátil
HTTP 200. Import a příznak UAT false. Před nasazením i po něm tři
anonymní požadavky vrátily stejnou 302 na přihlášení a místní Bootstrap
CSS měl stejný SHA-256. Přihlášená produkční přejímka NOT RUN.
[Doklad vydání](release.json), [produkce před](production-before.json), [po](production-after.json).
