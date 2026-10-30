# W8.3 — rodinný kalendář a historické členství

Plán před implementací, 30. 10. 2026, základ W8.1
`df09eab94ccc6545e57b71a41bc24aa0011850c0`; vydání až po W8.2.

Rodinný feed opakuje rozvinutí hierarchie a načítá všechny členy cílových
soupisek pro každý tým/datum, ačkoliv zobrazuje pouze čerstvě autorizované
osoby daného účtu. Změřit 1/12/52 týdnů, rodiny 0/1/5/20 a cizí osoby.

1. Zmrazit současný výstup feedu, RED rozpočet členských dotazů/řádků.
2. Sdílet s W8.1 čistě čtecí projekci členství: explicitní ID dávkovaná po
   400, SQL stavové predikáty, hrany jednou za volání, cykly a inkluzivní
   platnost. Timeline má roční interval; feed musí zachovat i akci, která
   začala před požadovaným obdobím a stále do něj zasahuje. Proto jeho
   historické členství/hranové intervaly neomezovat dolní hranicí feedu.
3. Nepoužívat projekci pro oprávnění nebo writery. Ponechat čerstvé
   familyPortalAuthorizedPeople, kontrolu účtu/tokenů a oddělené finanční
   či veřejné položky. Zachovat pořadí, UID a deduplikaci cílených akcí.
4. Testovat revokaci, rotaci tokenu, cizí osoby, přelom roku, dlouhé akce,
   historické záznamy, chybějící tabulky, shodu ICS i polí. Znovu ověřit
   timeline W8.1 a skutečný týdenní preview (bez odeslání).
5. Profily s migracemi/FK, nativní MariaDB 10.3/11.4, paměť a query budget;
   plná regrese, PHPStan/style, zelený přesný PR/main, deploy a čtecí/HTTP
   před/po. Žádná nová aktivace workeru, importu či provideru.

Navazující posouzení W8 zahrne konflikty vozidel, veřejný kalendář,
formuláře/uzavření a rezervace/náhrady. Jejich původní kontrakty zůstávají;
tento plán je předem neoznačuje za dokončené. Poté W9–W15.

## Realizace

Sdílená projekce a adaptér timeline jsou lokálně ověřeny. Roční feed pro
20 osob: 4199 → 39 SQL, členství 102000 → 20 řádků, shodná data/ICS.
Historie +1000 intervalů, dlouhé akce a legacy ID 0 zachovány; plná sada,
nativní MariaDB a statické kontroly PASS.
[Výsledky a meze](verification/refactoring-w83-20261030/RESULTS.md).
W8.2 a W8.3 se připravují ke společnému vydání po zeleném výsledném main.
