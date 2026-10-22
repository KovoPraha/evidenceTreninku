# R31–R37: klubová administrativa a společná přejímka

Lokální implementace 23. 10. 2026 navazuje na R25–R30 (`4033bf18`).
Pracovní kopie `C:\xampp\htdocs\evidencePavel-finance-b1-20261022`, větev
`codex/club-administration-r31-r37-20261023`. Produkce nebyla změněna.

## Výsledek podle realizačního plánu

| Úkol | Implementace | Návaznost / omezení |
|---|---|---|
| R31 Výdaje | Doložený doklad, kontrola duplicity, rozdělení uznané a neuznané části, plátce a věřitel, předložení a nezávislé schválení, opravné verze, částečné proplacení a skutečné vrácení přeplatku | Jedna kanonická peněžní evidence. Staré účtenky navázané do nové agendy nelze přepsat původním editorem. |
| R32 Zálohy a akce | Finanční případ, vazba na akci, příjemce zálohy, skutečné předání, čerpání, převod mezi příjemci, vrácení a schválený doplatek | Uzavření vyžaduje vypořádané výdaje a jednotlivé zálohy. Pouhé zadání limitu není výdej peněz. |
| R33 Trenérské výkazy | Výslovné pravidlo, uzavřená skutečná docházka a další činnost, neměnný předložený výkaz, nezávislé schválení, výplata z doloženého pohybu | Změna podkladu vyžádá novou verzi. Nejde o mzdový systém ani výpočet odvodů. |
| R34 Sportovní odměny | Datované pravidlo, odměňovaná osoba a výslovný příjemce, schválený nárok, částečná výplata, opravná verze a skutečná vratka | Uzavřená historická období nejsou přepočtena. Historický příznak vyplacení není bankovní důkaz. |
| R35 Klubová rozhodnutí | Zdroj, účinnost a obsah rozhodnutí, soukromý originál nebo umístění papíru, neměnné následné verze | Vlastník, další krok a termín používají společné provozní případy R12. Nezavádí se nepotvrzená agenda smluv či mezd. |
| R36 Účetní předání | Stávající XLSX/ZIP rozšířen o výdaje, zálohy, odměny, salda a podklady; PDF přehled; oddělené předání, vrácení a převzetí účetní | Archiv zůstává neměnný. Úplnost skutečných zdrojů a účetní metodiky není prohlášena za potvrzenou. |
| R37 Souvislý scénář | Jedna syntetická osoba, skutečná docházka, odměna, záloha, výdaj, společná úhrada, prodej a výdej oblečení a jeden účetní export | Místní technická přejímka doplňuje HTTP a souběžné scénáře. Skutečná obsluha a účetní svůj provoz dosud nepřevzaly. |

Podrobnosti: [odměny](CLUB_REWARDS_R33_R34.md),
[účetní předání](verification/r31-r37/R35_R36.md),
[odložená rozhodnutí](CLUB_COMPLETION_DECISIONS.md).

## Jeden peněžní zdroj

Výdaj, schválení odměny ani čerpání zálohy samy nezapisují druhý bankovní
pohyb. `accounting_movements` je skutečný peněžní pohyb; rozdělení na případy
vede `accounting_allocations`. Jedna platba může hradit více výdajů a odměn.
Součet přídělů, vratek a vyhrazených peněz nesmí překročit skutečný pohyb.
Tato ochrana nyní platí i pro obecný opravný pohyb, nejen pro akci „vratka“.

Schválení nižšího nároku po výplatě není vrácení peněz. Záporné saldo zůstává
viditelné do doloženého kladného peněžního pohybu přiřazeného jako skutečná
vratka. Uvolnění chybného přiřazení zachová původní peníze a není dokladem
vrácení. Uzavřený účet se neopravuje zpětným přepsáním.

## Pracovní postupy

- Hospodář: **Výdaje a zálohy**, **Výkazy a odměny**, **Finanční rozhodnutí**,
  **Účetní podklady**. Všechny zápisy vyžadují odpovídající aktuální oprávnění;
  finanční operace důvod a výslovné potvrzení.
- Trenér: v agendě odměn předloží vlastní výkaz. Nemůže potvrdit výplatu ani
  schválit vlastní nárok.
- Vedoucí sportu: doloží sazbu a její platnost a posoudí výkaz jiné osoby.
- Účetní předání: nejprve vytvořit konkrétní revizi a zkontrolovat výjimky,
  potom zaznamenat skutečné předání. Stažení ZIPu není předání ani převzetí.

## Izolace a přejímka

Všechny případy jsou označené LOCALHOST / NEPLATIT, kontakty jsou syntetické.
MariaDB běží pouze v odděleném datadir na portu 33426; web na 18086.
Neproběhl skutečný nákup, platba, import osob ani rozeslání zpráv.

`ClubOperatingMonth.php` prochází souvislé třicetidenní období syntetických
podkladů. Data a důkazy vytváří zkušební běh; nepředstírá měsíc skutečného
provozu. Kontroluje 5 000 Kč zálohu proti 4 200 Kč čerpání a 800 Kč vratce,
společnou platbu 1 100 Kč za výdaj a odměnu a prodej za 100 Kč. Z počátečních
10 000 Kč musí zbýt přesně 4 800 Kč. Výkaz, čerpání ani přiřazení peněz nesmí
vzniknout jako další obrat v exportu.

Obnova kontroluje všechny vlastněné tabulky, triggery a odděleně soukromé
soubory. Navíc prověřuje vazby odměn na kanonické příděly a peníze, skladové
pohyby a šarže a neměnné sportovní snímky. Výsledky a přesné počty jsou v
[protokolu přejímky](verification/r31-r37/RESULTS.md).

Migrace: `20261023050000_club_expenses`, `20261023060000_club_rewards`,
`20261023070000_financial_decisions`. Zálohovací kontrakt `2026-10-23.2`
přidává dvanáct tabulek. Žádná z těchto migrací nebyla aplikována v produkci.
