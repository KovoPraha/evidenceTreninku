# Administrátorský přehled — agregace počtů

P11-ADM, zahájeno 12. 11. 2026, vydání 13. 11. 2026. [PR #465](https://github.com/KovoPraha/evidenceTreninku/pull/465)
sloučen jako be4ce55ad54a5c4eaed6dba12f93ccaccf574d1f. Testovaný commit 5dd88550f48cd34f2fa51b3b2459925a8ba781b2, shodný Git tree
3e6ee156ebef079240f7fe1931c4bdf17d1930b5. Všech 13 kontrol PASS.
[Párový běh](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43323634798), [kontroly](application-checks.json).

## Změna a shoda

Původní NOT EXISTS pro počet osob bez skupiny zůstává samostatný.
Dluh, stáří posledního importu a současný ruční/KIS stav sdílejí
jednu podmíněnou agregaci. Tato část má 2 SQL a 2 načtené řádky místo
4 SQL a 4 řádků; nejde o počet všech dotazů stránky. Predikáty i typ int
jsou stejné. App změna nepotřebuje migraci ani cache.

Na každé MariaDB 10.3/11.4 prošlo 12 kombinací velikostí 0, 100,
10 000 a 100 000 osob se smíšenými, všemi a žádnými skupinovými vazbami.
Nezávislá reference obsahuje čtyři původní dotazy z 5cd6ad07.
Očekávané počty jsou navíc vypočtené nezávisle na SQL. Zahrnují NULL,
záporný/nulový/kladný dluh, příznaky mimo 0/1, více skupin a osiřelou
vazbu. Čas je pevný, hranice 90 dnů ověřená přesně i ±1 sekunda.
Každá kombinace má 4 zahřívací a 17 střídavých měřených párů.
Query cache je vypnutá, čtecí transakce i kontrolní součty PASS.
[10.3](mariadb-10.3/native.json), [11.4](mariadb-11.4/native.json).

Celé HTTP na 10 221 syntetických osobách má 13 scénářů na databázi:
system_admin 200 s 17 měřenými páry, 11 jiných přihlášených účtů 403,
anonymní 302. Negativní cesty mají jeden kontrolní pár, jejich časy
nejsou výkonový benchmark. HTML a sledované bezpečnostní hlavičky
se shodují po jediné normalizaci CSP nonce. Všech 358 tabulek má shodné
kontrolní součty. Query cache vypnutá i pro HTTP, testovací schémata,
účty, konfigurace a porty uklizeny.
[HTTP 10.3](mariadb-10.3/http.json.gz), [HTTP 11.4](mariadb-11.4/http.json.gz).

Chrome: 88 měřených a 24 zahřívacích navigací, desktop 1036 × 703 light
a mobilní rozlišení 390 × 844 dark, cold/warm cache. Dvě dvojice full-page
snímků jsou pixelově shodné; všech 11 karet a jejich geometrie shodné.
Přenos CSS je shodný (cold 53 957 B, warm 0 B), obě kopie Bootstrapu
zůstávají. FCP shodný 84 ms cold / 60 ms warm. Ready kolísá
114,6→119,9 / 80,2→72,6 / 119,8→114,6 / 75,2→77,9 ms;
zrychlení vykreslení tímto během neprokazujeme.
[Prohlížeč a snímky](mariadb-11.4/browser-summary.json).

## Naměřené mediány v ms

| MariaDB | Osob | Vazby | Před | Po | Pokles času |
| --- | ---: | --- | ---: | ---: | ---: |
| 10.3 | 10,000 | mixed | 11.09 | 10.10 | 8.9 % |
| 10.3 | 10,000 | grouped | 12.98 | 12.20 | 6.0 % |
| 10.3 | 10,000 | ungrouped | 8.35 | 7.49 | 10.3 % |
| 10.3 | 100,000 | mixed | 100.69 | 97.70 | 3.0 % |
| 10.3 | 100,000 | grouped | 127.99 | 125.71 | 1.8 % |
| 10.3 | 100,000 | ungrouped | 72.81 | 70.11 | 3.7 % |
| 11.4 | 10,000 | mixed | 9.47 | 8.46 | 10.7 % |
| 11.4 | 10,000 | grouped | 11.62 | 10.57 | 9.1 % |
| 11.4 | 10,000 | ungrouped | 6.84 | 5.87 | 14.3 % |
| 11.4 | 100,000 | mixed | 88.85 | 82.28 | 7.4 % |
| 11.4 | 100,000 | grouped | 115.15 | 109.63 | 4.8 % |
| 11.4 | 100,000 | ungrouped | 60.12 | 54.03 | 10.1 % |

Peak nativní části 38 808→38 920 B, tedy +112 B. Celé HTTP správce:
10.3 18,087→16,986 ms (−6,1 %), 11.4 15,182→13,830 ms (−8,9 %).
[HTTP mediány](http-medians.json). Jde o syntetické CI měření, nikoli
o měření produkční odezvy. Na 100 000 osobách je přínos 1,8–10,1 %
podle verze a rozložení skupin.

Původně zkoušená varianta s jediným SQL měla shodné hodnoty, ale
zpomalovala větší sady. Běh 43319920379: 100 000 mixed 49,87→84,36 ms,
grouped 62,92→90,17 ms, ungrouped 32,90→53,24 ms na MariaDB 11.4.
Byla zamítnuta; čísla nejsou přímým srovnáním různých běhů CI.

## Vydání

[Deploy 43326177751](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43326177751) SUCCESS, 2467 testů / 56603 kontrol.
Ověřená záloha 394 tabulek a 2 triggerů.
Aktivace, zachování konfigurace, migrace bez pending, variabilní symboly,
index a odstranění kopie konfigurace PASS. Přímý runner HTTP 200,
výsledný smoke HTTP 200, import a příznak UAT false.
Vydání zachovává souběžné #462 a #463 z main 373b8d89; jejich dokumentace
a schéma se tímto plánem nepovažují za nově schválenou lidskou přejímku.
Produkční anonymní GET /admin_dashboard.php má stejné přesměrování 302
před i po, místní CSS stejný SHA-256. Přihlášené produkční UAT NOT RUN.
[Doklad](release.json), [produkce před](production-before.json), [po](production-after.json).

Původní fio checkout d1221f35 a WIP kis_rosters_admin.php zachované.
Další kandidát k měření: duplicitní Bootstrap na administrátorském přehledu.
Jde o pokračování již evidované analýzy, dosud bez implementace.
