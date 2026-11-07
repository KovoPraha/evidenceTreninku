# P11-R05-H — historický souhrn plánovače se soupiskou

7. 11. 2026, Europe/Prague. Stav: K OVĚŘENÍ výsledkového commitu / merge / deploy. Vlastník: vývoj podle zadání vlastníka aplikace.
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

## Akceptace a aktuální výsledek

- Implementace zmrazená na `65440e3b`; [PR #374](https://github.com/KovoPraha/evidenceTreninku/pull/374).
- SQLite parita/regrese PASS: původně 14/1579, po pevném LIMIT sondy nový kontrakt 8/1170;
  1134 kombinací filtrů. Linux celá sada 2370 testů / 47564 assertions PASS.
- Finální celé HTML/Chrome PASS: [42390969061](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42390969061).
  Velká historie: trenér HTTP 70,59 → 47,36 ms; vedoucí 116,25 → 46,77 ms.
- Obě finální nativní MariaDB PASS v 42390969094, kontrakt 8/1170 na každé; 24 případů ×17 párů na DB.
  Helper měření není čas celého GET. [Protokol a artefakty](verification/planner-hierarchy-read-performance-20261107/RESULTS.md).
- Statická analýza/styl/dokumentace CI PASS. Merge a deploy NOT RUN;
  produkční stav vlastní [CURRENT](CURRENT_STATE.md), produkce zatím beze změny.
- Lidská produkční přejímka NOT RUN; syntetické testy ji nenahrazují.

Agregace má sondu 65, nejvýše 4097 načtených dat pro detekci limitu 4096,
513 hran pro limit 512, 128 skupin / 900 parametrů; nad limitem kanonický fallback.
První testy odhalily SQLite afinitu COALESCE/parametrů; opraveno sloupcovým IN
s odděleným NULL. Lokální PHPStan nad sdílenými balíčky nedokončen a vlastní proces
zastaven; fresh Linux PHPStan/styl PASS je rozhodující ověření.

Nativní i celé HTTP důkazy archivované, hashe runtime shodné s Git objekty.
Další krok: commit důkazů, zelené kontroly výsledkové hlavy,
merge a autorizovaný standardní deploy; veřejné čtecí srovnání a session handoff.
Rozhodnutí [D12](DECISION_LOG.md#d2026-11-07-12); průběžné předání [handoff](SESSION_HANDOFF_PLANNER_HIERARCHY_2026-11-07.md).

Převzato nové main `b3d0f4bb` / #377: pouze dokumentační blokované UAT pickeru, jeho fakta a metadata zachované; žádná změna měřeného runtime. Výsledková hlava 0ac7680c měla všech12 CI PASS; integrovaná hlava se musí znovu ověřit. Veřejný baseline pěti GET a odmítnutí anonymního plánovače PASS.
