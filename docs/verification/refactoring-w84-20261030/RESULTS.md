# W8.4 — klubový kalendář

Plán: [REFACTORING_W84_2026-10-30.md](../../REFACTORING_W84_2026-10-30.md).
Lokální syntetická data, 30. 10. 2026. Produkční vydání se dokládá samostatně.

## Přínos a hranice

`clubCalendarEvents` ponechává původní čerstvou kontrolu viditelnosti každého
řádku. Až povolené akce předá `ClubCalendarRead`: cílové týmy, odkazy, osoby,
počty a kapacity po 400 unikátních ID. Opakované termíny sdílejí stejné podklady.
Veřejný pohled nenačítá vozidla a dál odstraňuje interní poznámky/kontakty.

Vozidla načítají konflikty po 200 zdrojových rezervacích. Parametrizovaná
odvozená tabulka používá již načtený interval, aktivní protistranu, vlastní
exclude ID a původní otevřené hranice překryvu. Detail znovu nečte vozidla
jen kvůli zploštění konfliktů. Writer a jeho transakční kontrola se nemění.
Žádná cache mezi voláními, účty ani PDO. Stavové porovnání zůstává v SQL.

## Celý seznam na migrované MariaDB

[calendar-profile.json](calendar-profile.json): celý katalog migrací,
cizí klíče zapnuté, legacy tabulka vozidel založena před migracemi, takže je
přítomen i její podmíněný FK. 0/1/12/52 týdnů, čtyři akce týdně, dvě části
každé akce, čtyři viditelnosti, osoby/odkazy a překrývající se vozidla.
Rodina má schválenou vazbu a skutečné členství v cílové soupisce.
Každý výsledek se přesně porovnává s původním zmrazeným čtením.

| 52 týdnů | Vrácené řádky | SQL před → po | Načtené řádky před → po | Špička B před → po | Medián ms před → po |
| --- | ---: | ---: | ---: | ---: | ---: |
| Správce | 416 | 2913 → 9 | 4576 → 2288 | 5665864 → 4290920 | 2966,70 → 155,85 |
| Veřejnost | 104 | 521 → 6 | 936 → 624 | 2152248 → 1510888 | 442,46 → 35,82 |
| Oprávněná rodina | 312 | 2289 → 734 | 2600 → 1664 | 3481696 → 2648104 | 1690,43 → 512,41 |

Rodinný rozpočet obsahuje nezměněné kontroly viditelnosti a členství každé
akce. Nejde o tvrzení, že celá autorizace má konstantní počet dotazů.
Prázdný seznam zůstává jeden SQL. Malý veřejný scénář má cca 3,5 KiB
paměťové režie navíc; celkový rozpočet maximálně baseline +64 KiB prošel
ve všech scénářích. Čas a paměť: plain PDO bez profilování, jeden warmup,
pět střídavých opakování. Časy jsou lokální a mohou být ovlivněné souběžnou
validací; nejde o měření produkční latence.

## Kontrakty

- RED původní implementace: [red-budget.txt](red-budget.txt), 24 dotazů
  pro odkazy místo nejvýše jednoho. Green [contracts.txt](contracts.txt).
- Shodné celé seznamy staff/public/obě rodiny, skrytá osoba a soukromá pole,
  čerstvá revokace, chybějící detail a prázdné období.
- 401 unikátních akcí: dvě dávky, 13 SQL včetně základního seznamu; shodné
  celé pole. UNION počítá duplicitního účastníka jednou i nullable legacy
  registraci jednou. Čerstvé storno změní počet bez cache.
- Konflikty: sousední intervaly, překryv, zrušený zdroj, změna protistrany,
  shodný detail. Native profil přes 200 vozidel a následná změna stavu;
  uppercase `CONFIRMED` zachovává skutečnou MariaDB kolaci.
- [calendar-ast.txt](calendar-ast.txt) a reprodukovatelný
  [normalizátor](formatting-ast-check.txt): formátování proti snímku
  po funkční změně zachovalo AST a literální SQL/HTML. Writer tedy nemá
  skrytou funkční změnu způsobenou formátováním.
- Jedna původní zdrojová aserce přesného jednořádkového `if` neodolala
  formátování; nahrazena přímými pozitivními/negativními testy veřejnosti,
  members, staff, neznámé viditelnosti a neplatného účtu. Fail-closed větev
  je testována chováním, nikoli vypuštěna.
- [PHPStan](phpstan.txt) bez nových ignorování; lokální plná regrese
  na základu W8.1: 1834 testů / 36287 assertions, tři POSIX skipy Windows,
  [záznam](phpunit-windows.txt). Plná regrese výsledného main a obě CI
  MariaDB jsou podmínkou sloučení. Výsledky konkrétního release se doplní.

[Otevřené navazující sportovní kontrakty](SPORT_FOLLOWUP.md): W8.5 a R13,
potom W9–W15. W8.4 není úplná technická ani lidská přejímka celé aplikace.
