# P11-R05-H — historický souhrn plánovače se soupiskou

7. 11. 2026, Europe/Prague. Stav: HOTOVO — implementace, parita/měření, merge/deploy a čtecí smoke. Vlastník: vývoj podle zadání vlastníka aplikace.
Plán uložen před implementací. Navazuje na [analýzu](verification/full-http-performance-20261107/RESULTS.md)
a [předchozí řez plánovače](SESSION_HANDOFF_PLANNER_READ_PERFORMANCE_2026-11-06.md).

## Rozsah a oprávnění

Aktuální zadání: optimalizovat plánovač, ověřit přesnou shodu, změřit před/po;
po úspěchu commit, merge a standardní produkční deploy. WIP zachovat.
Větev `codex/planner-hierarchy-read-performance-20261107`, base `69f49033`.
Worktree `C:/Users/Marek/Documents/Codex/evidencePavel-planner-hierarchy-performance-20261107`.
Původní `kis_rosters_admin.php` a neversionované výstupy nedotýkat.

Řez: pouze čtecí počet a nejstarší datum minulých plánovaných lekcí při filtru soupisky.
Týdenní karty, RSVP, náhled kopírování, POST, transakční chování a oprávnění zůstávají kanonické.
Žádné změny schématu, produkčních dat, rolí ani flags.

## Volba a postup

1. Zmrazit přesnou předchozí implementaci jako testovací referenci.
2. Přenášet různá data kandidátů místo všech ID a odkazů; platnost orientované
   hierarchie vyhodnotit v PHP ke dni lekce, databáze provede COUNT/MIN přes EXISTS.
   Předek nebo potomek se počítá; sourozenci nikoli. Více odkazů nezvyšuje počet.
3. Omezit velikost sestaveného predikátu; pro transakce, nestandardní data nebo
   složitou hierarchii návrat k původnímu postupu. Bez cache mezi voláními.
   Rekurzivní SQL ani trvalá cache nejsou potřeba; ruší přenos všech ID bez nové DB závislosti.
4. Parita SQLite a MariaDB 10.3/11.4: filtry trenéra/skupiny/mine, prázdno,
   chybějící hrany, datumové hranice, cykly, více rodičů/odkazů, čerstvé změny,
   neplatná data a fallback. Zmrazit kontrolní součty, ověřit žádné zápisy.
5. Párové měření na jednom syntetickém datasetu pro malé a objemové historie:
   střídání před/po, zahřátí, medián/p95, SQL a přenesené řádky.
   Celý GET porovnat normalizovaným HTML a podle možnosti změřit HTTP.
6. Povinné kontroly, průběžná evidence, commit/PR/CI; převzít nový main,
   po zelených kontrolách merge a schválený standardní deploy s import/UAT false.
   Čtecí produkční smoke, ověřit WIP, výsledkové předání do repa.

## Akceptace a výsledný stav

**HOTOVO pro tento technický řez**: implementace, parita/měření, commit, merge
[PR #374](https://github.com/KovoPraha/evidenceTreninku/pull/374), schválený deploy a veřejné čtecí ověření PASS.
Release `e36c9ba6e461b7da842c53939c2fe20eed821f38`, [deploy 42394551344](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42394551344) SUCCESS.
[Provozní důkaz a meze](verification/planner-hierarchy-release-20261107/RESULTS.md).

- Frozen reference69f49033; runtime 65440e3b, integrovaná hlava 87130cfe všech 12 CI PASS.
  Celý strom sloučeného vydání přesně stejný; měřené source hashe shodné.
- SQLite nový kontrakt 8/1170, celá Linux sada 2370/47564; obě MariaDB 8/1170 PASS,
  1134 kombinací filtrů, 24 případů ×17 střídavých párů na DB.
- Celé HTML přesně stejné:1360 HTTP / 104 Chrome,13 checksumů shodných,
  čtecí diagnostika bez DML/DDL, izolovaný runtime uklizený.
- Velký GET20k: trenér70,59→47,36ms (−32,9 %), vedoucí116,25→46,77ms (−59,8 %).
  Helper 36k: 10.3 127,23→2,07ms,11.4 151,47→45,56ms; řádky 72946→167,SQL 70→5.
  Malá historie časově neutrální. [Metoda a všechny časy](verification/planner-hierarchy-read-performance-20261107/RESULTS.md).
- Deploy: ověřená záloha, migrace, původní VS, aktivace a HTTP smoke PASS;
  importovat_krouzky=false, uat_schvaleno=false; žádné nové aktivace/datové operace.
- Produkční veřejné před/po 5/5: DOM/pole shodné, plánovač302login. WIP hash shodný.

Agregace má sondu 65; nejvýše 4097 dat pro detekci limitu 4096,513 hran pro limit 512,
128 skupin / 900 parametrů. Nad limitem původní cesta. První test zachytil SQLite
ztrátu afinity COALESCE; opraveno sloupcovým IN a samostatným NULL. Místní PHPStan
nad sdílenými balíčky nedokončen/zastaven; fresh Linux PHPStan/styl PASS rozhodující.
Během čekání přijatý docs-only #377/b3d0f4bb; blokované UAT pickeru zachované,
pracovní události R05 nyníE39/40/41, integračníE42, merge/deployE43/44.

## Předání a otevřené návaznosti

Runtime je v main a nasazený; výsledkové předání vlastní větev
`codex/planner-hierarchy-release-proof-20261107`, stejný worktree, pouze dokumentace.
Po kontrolách toto předání sloučit; **další deploy dokumentů není potřeba**.
Původní WIP a neversionované výstupy zachované. Přihlášené produkční UI UAT,
rows examined, fyzický mobil/souběh NOT RUN; další R13 a celé K0.2 otevřené.
[Handoff](SESSION_HANDOFF_PLANNER_HIERARCHY_2026-11-07.md), [D12](DECISION_LOG.md#d2026-11-07-12).
