# Pátá vlna — nabídky a fyzické týmy v dávkách

[PR #217](https://github.com/KovoPraha/evidenceTreninku/pull/217), výchozí main
`0275e6acaaafae1de08adfc480175e577eaba343`. [Plán](../../REFACTORING_WAVE5_2026-10-28.md).

## Změna

Kontext jedné veřejné operace přednačte nabídky a vazby fyzických týmů v dávkách
po nejvýše 400 ID. Kanonická a dávková cesta sdílejí stejnou SQL projekci nabídky.
Původní WHERE a FOR UPDATE kanonického čtení zůstávají zachované. U legacy
variant s více aktivními nabídkami dávka ponechá výběr původnímu SQL dotazu.
Prázdné vazby se cachují, ale primární tým se stále určuje z konkrétní nabídky.

Výpočet obsazenosti, rezervací a limitu kapacity zůstává v původních funkcích.
Zamykající čtení ignoruje kontext, další veřejná operace vytvoří nový. Žádná
cache mezi requesty, žádná migrace, změna cen, oprávnění nebo skutečných dat.

## Regrese, SQL a databáze

[Před opravou](regression-before.txt): test selhal na 218 SQL proti limitu 125.
Po opravě šest nových testů / 36 assertions PASS: plné řádky, sdílená kapacita,
více fyzických týmů, primární fallback, neaktivní program/nabídka, chybějící
tým/sezona, legacy duplicity, chybějící tabulky/sloupce, hranice 401 variant,
nová operace a ignorování starého kontextu pod zámkem.
Stávající podmínky 7/41, kompatibilita 6/35, platební cyklus 30/906 PASS.

[Před](before.json) / [po](after.json): všech 12 úplných výstupních hashů
stejných (navigace, katalog, karty, detail; 1/10/50 programů).
[Reprodukce](profile-source.txt): spustit PHP z kořene worktree s absolutním
kořenem zkoumané verze jako argumentem. Časy jednotlivých volání nejsou benchmark.

| Programů | SQL karet před | Po | Nabídky před / po | Vazby týmů před / po |
| ---: | ---: | ---: | ---: | ---: |
| 1 | 22 | 22 | 1 / 1 | 1 / 1 |
| 10 | 58 | 40 | 10 / 1 | 10 / 1 |
| 50 | 218 | 120 | 50 / 1 | 50 / 1 |

[MariaDB 10.4 lokálně](mariadb-local.json), [CI 10.3](ci-mariadb-10.3.json)
a [CI 11.4](ci-mariadb-11.4.json): 1/50/401 programů, SQL 22/120/834,
pro 401 programů dvě dávky nabídek a dvě dávky vazeb, nejvýše 400 parametrů.
Porovnává každý úplný řádek s kanonickým čtením; sdílená kapacita, pořadí týmů,
čerstvý zámek a duplicate fallback PASS. Pro 401 programů nativní PDO prepares.
Reprodukce `php tests/Support/ProgramOfferBatchMariaDbSmoke.php`, pouze localhost,
port přes `EVIDENCE_MARIADB_SMOKE_PORT`; náhodná syntetická DB odstraněna ve finally.

## HTTP a limity měření

[Šest HTTP profilů](http-checks.json) má shodný viditelný text, odkazy a obrázky.
Ignorují se jen číselné cache verze assetů po ověření shodných bajtů souborů.
Šablony, CSS a JavaScript se nemění. Stejná syntetická DB: 71 aktivních produktů,
20 programů; střídavé pořadí před/po, 5 zahřátí a 15 měření.

[První měření](http-benchmark-concurrent.json) běželo současně s velkým MariaDB
testem: medián 560,78 → 590,99 ms. Kvůli souběhu se neopíráme o jeho latenci.
[Opakování bez souběžného DB testu](http-benchmark.json): 416,56 → 336,93 ms,
tj. přibližně −19 %. Oba výsledky jsou zachované. Čas celé HTTP odpovědi včetně
lokální sítě na sdíleném Windows hostu, nikoli browser rendering, zátěžový test
nebo produkční příslib. [Skript](http-check-source.py).

Zůstávají dvě kanonická čtení obsazenosti na fyzický tým každé nabídky;
více týmů počet dotazů zvyšuje. Celý katalog nemá konstantní počet SQL.

## Kompletní ověření

[Testy 40775728422](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40775728422):
1 782 testů / 35 866 assertions, POSIX 3/45, anonymní sweep, JS a MariaDB matice PASS.
Statická analýza odhalila starou chybnou PHPDoc anotaci fyzických týmů jako
výsledku kapacity. Typ je opraven na seznam ID; odpovídající zastaralé položky
baseline se odstraňují, nevzniká nové potlačení chyby. Finální commit musí projít
kompletním CI před sloučením; přesný main znovu před nasazením.

Plné lidské/účetní UAT zůstává otevřené. Importy a integrace se neaktivují.
