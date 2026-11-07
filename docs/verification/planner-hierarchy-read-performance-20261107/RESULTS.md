# P11-R05-H — parita a přínos historického souhrnu plánovače

7. 11. 2026. [Plán](../../PLANNER_HIERARCHY_READ_PERFORMANCE_2026-11-07.md),
[předání](../../SESSION_HANDOFF_PLANNER_HIERARCHY_2026-11-07.md), [PR #374](https://github.com/KovoPraha/evidenceTreninku/pull/374).
Runtime `65440e3b2f080a9f2efd37a85d10cf202e2bc890`, baseline přesně
`69f490332b0027232d7a58bffb578c28b2715ffa`. Implementace/parita/měření PASS;
#374 sloučené, schválené nasazení42394551344 a veřejné před/po PASS;
[produkční důkaz a meze](../planner-hierarchy-release-20261107/RESULTS.md).

## Kontrakt a regrese

- Lokální SQLite 14 testů / 1579 assertions PASS na prvním řezu; po doplnění
  pevného LIMIT sondy nový kontrakt 8/1170 PASS. První testy zachytily SQLite
  afinitu COALESCE/parametrů; opraveno sloupcovým IN a samostatným NULL.
- Finální nativní kontrakt **8 testů / 1170 assertions na každé DB PASS**:
  1134 kombinací filtrů, hranice validity, cykly, více rodičů/odkazů, sourozenci,
  NULL/0 odkazy, čerstvé změny, stejné transakční SQL, chybějící hrany,
  neplatná data a návrat dlouhé historie. Každá DB proti vlastní původní kolaci.
- Linux celá sada **2370 testů / 47564 assertions PASS**; zvlášť unixové hranice 3/45.
  PHPStan/styl/docs/CodeQL PASS. Místní PHPStan se sdílenými balíčky nedokončen,
  vlastní proces zastaven; rozhodující je fresh Linux ověření.
- [Testy a nativní měření 42390969094](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42390969094),
  [celé HTTP/Chrome 42390969061](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42390969061).
  Dva runtime zdrojové hashe přesně shodné v nativních a HTTP důkazech i Git objektech.
  Integrovaná výsledková hlava 87130cfe získala všech 12 zelených kontrol před merge.

## Nativní helper před/po

[MariaDB 10.3](final/native-10.3.json), [MariaDB 11.4](final/native-11.4.json).
PHP 8.2.35; MariaDB 10.3.39 a 11.4.13. Každá velikost jednou nasetá,
read-only session, 4 zahřátí + **17 střídavých časovaných párů**, 24 případů na DB
(40/720/7200/36000 plánů × moje/všechny × tři soupisky). Shodné výsledky všech
párů a tří tabulkových checksumů; vlastní schémata odstraněná ve finally.
SQL/fetched diagnostika oddělená od prostého času, žádné zápisy.
Tabulka: všechny plány / kořenová soupiska; p95 je nearest-rank ze 17 časů.

| MariaDB | Plánů | Medián ms před → po | p95 ms před → po | SQL | Řádky do PHP |
| --- | ---: | ---: | ---: | ---: | ---: |
| 10.3 | 40 | 1.468 → 1.465 | 1.542 → 1.544 | 4 → 4 | 91 → 91 |
| 10.3 | 720 | 3.258 → 1.935 | 4.896 → 2.318 | 5 → 5 | 1471 → 167 |
| 10.3 | 7200 | 18.887 → 1.929 | 19.712 → 2.191 | 17 → 5 | 14599 → 167 |
| 10.3 | 36000 | 127.232 → 2.073 | 135.909 → 2.391 | 70 → 5 | 72946 → 167 |
| 11.4 | 40 | 1.139 → 1.135 | 1.173 → 1.288 | 4 → 4 | 91 → 91 |
| 11.4 | 720 | 3.349 → 2.382 | 4.193 → 2.539 | 5 → 5 | 1471 → 167 |
| 11.4 | 7200 | 25.580 → 10.100 | 26.886 → 10.681 | 17 → 5 | 14599 → 167 |
| 11.4 | 36000 | 151.470 → 45.563 | 156.993 → 46.323 | 70 → 5 | 72946 → 167 |

Rozdíl optimizerů je podstatný: u 36 tisíc plánů přínos helperu přibližně 61×
na 10.3 a 3,3× na 11.4. Obě verze přenášejí 72 946 →167 řádků (−99,77 %).
Malá historie má stejné 4 SQL / 91 řádků a prakticky stejný čas.
Nejde o čas celé stránky ani důkaz počtu rows examined.

## Celý přihlášený HTTP a skutečný Chrome

[Souhrn a zdrojové hashe](final/integrated-summary.json), [úklid](final/runner-cleanup.json).
Jeden fixture 728 plánů a následně rozšířený o 20 tisíc historických plánů;
žádný reseed mezi před/po. Týdenní karty a všechny ostatní aplikační soubory stejné.
Dvě role, stejné rozměry 1036 ×703; skutečný Chrome 156, PHP 8.2.35, MariaDB 11.4.
21 scénářů na variantu/velikost (11 pozitivních, 10 odmítnutí/anonymní).
**1360 uložených HTTP pozorování** =1320 pozitivních časů +40 odmítnutí;
navíc 220 nezapočtených zahřátí. Celé normalizované HTML přesně shodné.
SQL/PHP diagnostika zvlášť, ve všech 84 případech HTML shodné s prostým GET;
13 doménových checksumů shodných i mezi variantami, žádné DML/DDL.

| Celý GET | Medián ms před → po | p95 ms před → po |
| --- | ---: | ---: |
| 728-plans / planner-coach-own | 12.61 → 12.49 | 13.24 → 13.24 |
| 728-plans / planner-coach-root | 16.05 → 16.13 | 16.83 → 20.60 |
| 728-plans / planner-lead-root | 17.85 → 16.52 | 18.92 → 17.14 |
| 20728-plans / planner-coach-own | 29.29 → 29.46 | 30.11 → 30.01 |
| 20728-plans / planner-coach-root | 70.59 → 47.36 | 71.77 → 50.95 |
| 20728-plans / planner-lead-root | 116.25 → 46.77 | 118.24 → 49.41 |

Objemový trenér: medián HTTP **−32,9 %**, vedoucí **−59,8 %**.
Bez soupisky neutrální; malá historie kořenové soupisky 16,05 →16,13 ms,
SQL 46 →47, PHP peak 1 219 432 →863 624 B. Tuto malou režii nezakrýváme.
Velká historie trenér: SQL 66 →47, PHP peak 9 422 016 →863 624 B (−90,8 %);
vedoucí SQL 87 →47, PHP peak 17 987 600 →798 224 B (−95,6 %).

**104 browser pozorování**, na roli/variantu/velikost 3 cold +10 warm,
navíc jedno nezapočtené warm zahřátí. Objemový warm load medián:
trenér 131,35 →108,65 ms (−17,3 %), vedoucí 195,20 →121,10 ms (−38,0 %).
Žádné console errors nebo failed requests; povinná networkidle mezera se do
loadEventEnd nepočítá. Browser/HTTP raw bezpečné JSON jsou vedle souhrnu v gzip.
[První nezávislý pár](initial/integrated-summary.json) na 6fe44e57 přínos potvrzuje;
finální hodnoty výše už obsahují pevný limit sondy a nový test dlouhé historie.

## Meze a další stav

Syntetické Linux CI měření není produkční SLA ani inventura skutečných objemů.
Přenesené řádky nejsou rows examined; rows examined, fyzický mobil a souběžná
produkční zátěž NOT RUN. Bez cache práv/dat mezi voláními; transakční/writer,
kopírování, týdenní karty a RSVP nezměněné. Pro extrémní graf/data funguje fallback.
Nasazení i veřejné čtecí ověření dokončené po všech 12 CI integrované hlavy 87130cfe;
žádné nové aktivace ani skutečné změny osob/rolí/plateb/docházky.
Přihlášené produkční UI UAT NOT RUN. Původní WIP hash beze změny.
