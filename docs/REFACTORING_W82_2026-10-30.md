# W8.2 — filtry plánovače a sportovních seznamů

Plán před implementací 30. 10. 2026, základ `8c71a2d281956db1fb941aa42a3e045f1f5bca89`.
W7.6/W8.1 je mezitím ověřována v PR #237; tento balík se zařadí až za ni.

1. Zmrazit filtry plánů/skutečných tréninků. Ověřit 1/12/52 týdnů, více
   týmů, historické rodiče, cykly, duplicitní vazby, chybějící cíle/tabulku
   hran, stejné pořadí a čerstvou změnu v následujícím volání.
2. Oba filtry již dávkují ID po 500, ale hierarchii opakovaně načítají
   pro každé tým/datum. Sdílet jejich shodné čtení a načíst hrany jen jednou
   v lokální projekci. Žádná statická ani mezi-požadavková cache.
3. Náhled kopírování týdne v `planovac.php` provádí COUNT/FOUND_ROWS/COUNT
   a při týmovém filtru ještě znovu vybírá ID. Oddělit čtecí počet od HTML:
   bez týmu jeden COUNT, s týmem jediný výběr ID a stejný historický filtr.
   Zachovat databázovou sémantiku stavu a precedence trenérských filtrů.
4. Neměnit kopírovací transakce, oprávnění, skutečné účasti ani uzavření.
   Filtr využívaný POST stále dostává čerstvou instanci a aktuální data.
5. RED/green rozpočty, profily migrované MariaDB, shoda výsledků a HTML/AST
   nutného formátování, kompletní regrese a CI obou MariaDB. Poté přesné
   zelené main, chráněné nasazení a nové čtecí/HTTP důkazy před a po.
6. Další kalendář/formuláře/rezervace i W9–W15 zůstávají k posouzení;
   tento balík je neuzavírá bez konkrétních důkazů.

## Stav implementace

Čtecí filtry sdílejí jednu projekci hran a dávky 500 ID. Náhled počtu je
oddělen od HTML, bez opakovaných COUNT/FOUND_ROWS. Roční profil plánů:
1875 → 5 SQL, paměť i čas nižší, přesně shodná data. Nutné formátování
ověřeno AST/HTML, nativní MariaDB kontrakty a plná lokální sada PASS.
[Výsledky a meze](verification/refactoring-w82-20261030/RESULTS.md).
Následuje CI a ověřené vydání; integrace a lidské UAT beze změny.
