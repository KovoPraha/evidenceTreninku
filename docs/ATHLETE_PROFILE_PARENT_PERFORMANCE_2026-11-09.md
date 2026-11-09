# P11-RAP — počáteční načtení profilu sportovce

9. 11. 2026: ROZPRACOVÁNO, plán před implementací. Vlastník: vývoj podle
aktuálního zadání Marka. Autorizace: zachovat WIP, ověřit přesnou shodu a
změřit před/po; po úspěšných kontrolách commit, merge a chráněné nasazení.
[Předání](SESSION_HANDOFF_ATHLETE_PARENT_2026-11-09.md),
[výsledky](verification/athlete-profile-parent-performance-20261109/RESULTS.md).

## Výchozí stav a volba

Čerstvý main02904936abb8eca3c859e6ad060dca57425783ab; skutečně nasazené
95cb33e5, deploy42531190845 SUCCESS. RHA/RHC/RAS hotové, neopakovat.
[Analýza priorit4/6](verification/performance-analysis-20261107/RESULTS.md)
a [poslední měření](verification/ajax-profile-session-performance-20261108/RESULTS.md)
ukazují zbylé TTFB parent profilu kolem2,7s při30k tréninků/270k vlastních měření.
To je největší doložená zbývající celá cesta tohoto měření, nikoli produkční SLA.
Příčinu nejprve určit skutečným SQL profilem. Zdroj obsahuje opakované členské
souhrny a sportsActivitySummary, přestože úvodní panel z něj použije pouze
training_count/training_minutes/race_count. Úplný kanonický souhrn navíc
načítá všechny roční measurement snapshoty pro export/hash; toto je hypotéza.

Větev codex/athlete-profile-parent-performance-20261109, vlastní worktree
C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107.
Původní C:/xampp/htdocs/evidencePavel je rozpracované na
codex/fio-auto-reconciliation-recovery; kis_rosters_admin.php SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5.
Žádný reset/clean/stash cizí práce, stage jen vlastní pojmenované soubory.

## Postup a akceptace

1. Změřit aktuální celou parent route bez změny aplikace: skutečný login a
   scoped profile grant, vlastní migrovaná MariaDB10.3/11.4, cacheOFF,
   1k/10k/30k přidaných tréninků. SQL diagnostiku provést zvlášť od prostých
   HTTP časů; identifikovat dominantní příkazy/vrácené řádky/paměť PHP.
2. Podle důkazu vybrat úzkou čistou projekci: přednost vynechání nevyužitých
   measurement detailů před pouhou redukcí počtu SQL. Případná přehledová
   projekce vrací jen tři zobrazené součty; úplný kanonický souhrn/export/
   hash zůstává beze změny. Alternativa je dávková agregace roků/počtů,
   pokud diagnostika doloží větší přínos. Žádná cache práv/dat ani DDL/indexy.
3. Celá parent route na dvou izolovaných HTTP clusterech: zmrazený celý route
   z main02904936 versus nový. Stejná data i společný bootstrap;4warmups+
   17střídavých before/after párů, totéž při1k/10k/30k, malé/prázdné kontroly.
   Nezavádět zpoždění, stuby nebo změny UI pro účely benchmarku.
4. Přesná projekční parita proti kanonické funkci: aktuální/odvolané revize,
   skutečná uzavřená účast, neověřená historie/plány/RSVP se nestanou účastí,
   neznámá délka versus0, attended/started/cancelled a hranice období,
   cizí osoby, invalidní období a poškozený JSON. Default plný souhrn a hash
   včetně všech vlastních measurement snapshotů se musí zachovat.
5. Celý normalizovaný DOM shodný (pouze náhodné CSP/CSRF nonce); oba skutečné
   AJAX fragmenty byte-parita, úplné počty/roky/typy. Pravé login/bearer303,
   12staff rolí, guest/retarget, fresh revokace/expirace/scope a rodinný gate.
   Žádná změna TTL/session/CSRF, poznámkových writerů ani odměn/rolí.
6. Chrome přes Playwright CLI: skutečné default i uložené Měření,3cold+
   10warm navigací/variantu, dokument TTFB/load a fetch→DOM+dvě smyčky,
   filtry/stránky/rychlé záložky a390px. PHP/UI chyby0, žádné nové writery.
7. Doménové checksumy a cleanup vlastního schématu/účtu/configu/cookies/
   browseru i celých vlastních worker groups; jen bezpečné vzorky do Gitu.
8. Aktuální celé CI, přesné measured-source a tested/merged tree. Poté
   protected deploy NASADIT, import=false/UAT=false, ověřená záloha,
   migrace/VS/config/smoke a anonymní produkční GET před/po. Finální důkazy
   i session handoff do main přes dokumentační PR, bez druhého deploye dokumentace.

Přihlášené produkční UAT a produkční výkon nejsou nahrazené syntetickou
fixture. Pokud není čistý přínos celé cesty nebo parita, kandidát neuvolnit.
Další krok: samostatná diagnostika parent route, zatím žádná aplikace ani produkce změněna.

## Rozhodnutí po diagnostice

Run42570580742 na40183284: oba native PASS. Dominantní nepoužitý snapshot
94752řádků,649/1665ms; PHP peak~96,6MB. Zvolena oddělená projekce3součtů,
plný kanonický souhrn/export/hash beze změny. [Důkaz](verification/athlete-profile-parent-performance-20261109/RESULTS.md).
PR[#408](https://github.com/KovoPraha/evidenceTreninku/pull/408), zatím bez release.
