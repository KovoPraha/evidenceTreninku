# P11-R05-H — historický souhrn plánovače se soupiskou

7. 11. 2026, Europe/Prague. Stav: ROZPRACOVÁNO. Vlastník: vývoj podle zadání vlastníka aplikace.
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

- Lokální SQLite parita a regrese: PASS, 14 testů / 1579 assertions; 1134 kombinací nového kontraktu. Doplněný limit sondy: 8 testů / 1170 assertions PASS. Celé HTML/Chrome na 6fe44e57 PASS v běhu 42390451276; přesné výsledky stahuji, finální commit se znovu změří.
- Přínos před/po v nativní MariaDB: NOT RUN; nepřipisovat helper měření celému GET.
- Regrese, CI, merge, deploy: NOT RUN. Produkční stav vlastní [CURRENT](CURRENT_STATE.md).
- Lidská produkční přejímka: NOT RUN; syntetické testy ji nenahrazují.

Implementována omezená agregace: sonda 65, nejvýše 4096 dat / 512 hran / 128 skupin / 900 parametrů; nad limitem kanonický fallback. SQLite porovnání odhalilo ztrátu číselné afinity přes COALESCE; opraveno sloupcovým IN a samostatnou větví pro NULL. Lokální cílené testy nyní PASS.

[PR #374](https://github.com/KovoPraha/evidenceTreninku/pull/374) otevřený. Statická analýza/styl/dokumentace CI PASS. Další krok: finální nativní CI 10.3/11.4 a opakovaný celý HTTP/Chrome pár po doplnění pevného limitu sondy; výsledky zapsat před merge.
Rozhodnutí [D12](DECISION_LOG.md#d2026-11-07-12); průběžné předání [handoff](SESSION_HANDOFF_PLANNER_HIERARCHY_2026-11-07.md).
