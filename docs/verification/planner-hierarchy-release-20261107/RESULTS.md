# P11-R05-H — produkční předání

7. 11. 2026: **SLOUČENO / NASAZENO / ČTECÍ KONTROLA PASS**.
[Plán](../../PLANNER_HIERARCHY_READ_PERFORMANCE_2026-11-07.md),
[parita a měření](../planner-hierarchy-read-performance-20261107/RESULTS.md),
[předání](../../SESSION_HANDOFF_PLANNER_HIERARCHY_2026-11-07.md).

## Schválení, merge a přesné vydání

Aktuální zadání vlastníka výslovně dovolilo commit/merge/deploy po shodě,
přínosu a zelených kontrolách. [PR #374](https://github.com/KovoPraha/evidenceTreninku/pull/374) sloučený na
`e36c9ba6e461b7da842c53939c2fe20eed821f38`. Integrovaná hlava `87130cfe` má všech **12 CI SUCCESS**:
[úplné kontroly](checks-integrated.json), testy 42393403294, HTTP/Chrome 42393403264,
PHP quality 42393403327 a docs 42393403265. Celá sada 2370/47564,
obě MariaDB10.3/11.4, PHPStan/styl/CodeQL PASS.
Při čekání převzaté dokumentační #377; blokované produkční UAT pickeru zachované.
Pracovní R05 události E38/39/40 se změnily na E39/40/41; přijaté E38 zachované.

[Shoda merge] (merge-verification.json): celý Git strom přesně totožný s testovanou
hlavou, všechny kontrolované zdrojové hashe odpovídají finálnímu měření65440e3b.
Před dispatch zkontrolovaný fresh main, žádný běžící/duplicitní deploy.
[Deploy 42394551344](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42394551344) **SUCCESS** na přesném výše uvedeném SHA.
[Bezpečný záznam](deployment.json): syntaxe/testy, předletová kontrola,
vytvoření/ověření zálohy, migrace, zachování dosavadních VS, aktivace a HTTP smoke PASS.
`potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`;
import/UAT označení přeskočené. Nepřibyla migrace ani nová aktivace integrací.

## Shoda a měřený přínos

Finální [protokol](../planner-hierarchy-read-performance-20261107/RESULTS.md)
a původní bezpečné JSON obsahují metodu a všechny časy:
1134 kombinací, 8/1170 na každé DB, 24 případů ×17 střídavých párů na DB,
1360 HTTP a104 skutečných Chrome, celé normalizované HTML přesně stejné,
13 doménových checksumů shodných, žádné DML/DDL v oddělené čtecí diagnostice,
izolovaný fixture/server/bridge účet uklizené.

| Syntetické měření | Před | Po |
| --- | ---: | ---: |
| Celý GET /20k historie /trenér | 70,59 ms | 47,36 ms (−32,9 %) |
| Celý GET /20k historie /vedoucí | 116,25 ms | 46,77 ms (−59,8 %) |
| Helper 36k /MariaDB10.3 | 127,23 ms | 2,07 ms |
| Helper 36k /MariaDB11.4 | 151,47 ms | 45,56 ms |
| Helper / řádky do PHP | 72946 | 167 (−99,77 %) |
| Helper / SQL | 70 | 5 |

Malá historie časově neutrální; týdenní filtrovaný GET trenéra má při 728 plánech
SQL 46→47, oba časy přibližně 16 ms. Velký GET PHP peak trenér9,42→0,864MB,
vedoucí17,99→0,798MB; Chrome warm load trenér 131,35→108,65ms,
vedoucí 195,20→121,10ms. Časy helperu nejsou časy celé stránky;
syntetické CI výsledky nejsou produkční SLA ani skutečná inventura objemů.

## Produkční čtecí ověření a meze

[Před](before.json) a [po](after.json): pět sériových anonymních katalogových GET,
všude HTTP200, **5/5 shodný normalizovaný celý main/navigace, ID/pořadí a porovnatelné veřejné údaje**.
U extrahovaného celkového počtu: dvě prázdné výsledkové stránky 222→222;
u zbývajících tří parser počet nezískal (null), celý DOM je přesto shodný.
[Sonda](snapshot.php.txt) ukládá jen veřejná ID/počty/hash, nikoli HTML/cookies/session.
[Plánovač před](anonymous-planner-before.json) i [po](anonymous-planner-after.json):
302 na login.php. Potvrzené anonymní odmítnutí, přihlášené produkční UI UAT **NOT RUN**.
Blokované UAT pickeru z #377 zůstává samostatné; toto nasazení je neřešilo.

Nebyly prováděny skutečné změny osob/rolí/lekcí/docházky/plateb ani integračních flags.
Záloha ověřená při deployi není důkaz její izolované obnovy. Rows examined,
fyzický mobil a souběžná produkční zátěž NOT RUN. Writer, transakční cesta,
kopírování, týdenní karty a RSVP nezměněné; žádná cache práv/dat mezi voláními.
[Původní WIP](wip-preserved.json) SHA256 shodný, původní checkout/výstupy zachované.
Toto výsledkové předání mění pouze dokumentaci/důkazy; další deploy neopakovat.
