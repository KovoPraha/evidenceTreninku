# R31–R37 — místní přejímka

23. 10. 2026. Izolovaná syntetická databáze `127.0.0.1:33426/evidence_b1`,
web `127.0.0.1:18086`. Žádný produkční zápis, nasazení, push ani externí odeslání.

| Kontrola | Výsledek |
|---|---|
| Kompletní PHPUnit | **1 479 testů / 30 751 assertions**, 0 chyb, selhání a přeskočení |
| Závěrečná cílená regrese výdajů, odměn, uzávěr a exportu | **44 testů / 301 assertions PASS** |
| Výdaje a zálohy přes skutečné HTTP | **40 kontrol PASS** |
| Odměny přes skutečné HTTP | **20 kontrol PASS** |
| Rozhodnutí a účetní předání přes skutečné HTTP | **33 kontrol PASS** |
| Skutečná souběžnost MariaDB | **36 kontrol PASS**, 8 dvojic workerů; před uvolněním vždy pozorovány dvě transakce v `LOCK WAIT` |
| Jeden souvislý provozní scénář | **13 kontrol PASS**; sport → odměna, záloha → výdaj/vratka, prodej → sklad → společný export |
| Syntaxe změněných PHP souborů | **41 souborů PASS** |
| Migrace tohoto balíku | Tři nové migrace aplikovány pouze v B1; vydání má 133 migrací |
| Obnova | 316 tabulek, 2 triggery, oddělené soukromé soubory, kontrola otisků a kanonických vazeb |

Přesné výsledky a identifikace jednotlivých běhů jsou v `phpunit.json`,
`expenses-http.json`, `expenses-race.json`, `rewards-http.json`,
`rewards-race.json`, `r35-r36-http.json`, `r35-r36-race.json`,
`operating-month.json` a `backup-restore.json`. Podrobnosti k dílčím postupům
jsou v [R31/R32](R31_R32.md) a [R35/R36](R35_R36.md).

## Co technická přejímka prokazuje

- Výplata nároku čerpá existující peníze. Dvojí potvrzení, souběžné přeplacení
  a obecný opravný pohyb nemohou obejít zůstatky.
- Přeplatek po nižší opravě zůstává pohledávkou do skutečného vrácení peněz.
- Uzavření zálohy vyžaduje vypořádání; pouhý přepis limitu nebo uvolnění
  přídělu nenahrazuje vratku.
- Výkaz uchovává zdroj a sazbu. Změna docházky nezmění starou schválenou částku
  a vyžádá opravnou verzi před další výplatou.
- Stažení účetního balíku neznamená jeho předání. Vrácení a přijetí se evidují
  samostatně a nemění původní archiv.
- Při obnově se kontrolují nejen počty tabulek, ale také soubory, skladové
  projekce, snímky a vazba odměny na příděl skutečného pohybu.

## Co nebylo převzato jako skutečný provoz

R37 vyžaduje také přejímku obsluhou a účetní. Ta nebyla provedena. Syntetický
třicetidenní scénář není třicet dní živého provozu. Export výslovně upozorňuje
na nepotvrzenou metodiku, zdroje a počáteční stavy; neznámé historické saldo
nenahrazuje nulou. [Rozhodovací registr](../../CLUB_COMPLETION_DECISIONS.md)
zachycuje podklady, které lze dodat při následné kontrole uživatele.
