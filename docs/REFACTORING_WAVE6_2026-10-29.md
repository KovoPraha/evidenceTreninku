# W6.2 — oddělení výpočtu obsazenosti

Zahájeno 29. 10. 2026 po sloučení plánu #219 a charakterizace #220.
Základ `9da70c97b3b825ae50459a8663aea5af50dc7982`, izolované worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-refactoring-wave6-20261029`,
větev `codex/refactoring-wave6-capacity-calculation-20261029`.
Vlastník schválil autonomní práci včetně sloučení a nasazení po ověření.

## Problém a konkrétní změna

`clubProgramOfferCapacityStateForTeam` současně sestavuje SQL, pracuje se zámky
a vyhodnocuje pravidla person/request/item, max(quantity) a dostupného místa.
Čistý výpočet nad již načtenými daty přesunout do samostatného helperu bez PDO,
hodin, session nebo konfigurace. Existující fasáda, SQL a parametry, pořadí
načítání a zamykání, filtry expirace/claimů i veřejný read context zůstávají.

Přínosem je oddělený, přímo testovatelný doménový výpočet před další optimalizací.
Nejde o nový dávkový algoritmus, index ani deklarované zrychlení. Výstupní
struktury/typy i počet SQL musí být identické. Nová migrace není potřeba.

## Ověření a vydání

1. Charakterizace z PR #220 už prošla na původním kódu, SQLite/MariaDB.
2. Po extrakci zopakovat cílené i plné testy, přidat test nezávislosti na pořadí
   a opakovaném předání stejných vstupů; porovnat měřicí profily a SQL.
3. Finální CI: MariaDB 10.3/11.4, checkout souběh/idempotence, PHPStan bez nové
   baseline, styl, anonymous sweep, JS, dokumentace; potom merge a přesný main CI.
4. Před/po deployi čtecí diagnostika a HTTP smoke; záloha a migrační check přes
   existující workflow. `uat_schvaleno=false`, `importovat_krouzky=false`.
5. Zpětný návrat: předchozí kód je kompatibilní se stejným schématem. Žádná
   automatická obnova databáze přes nové transakce, žádná aktivace integrací.

Další výkonové W6.3 vyžaduje profil migrovaného schématu a paměťové limity;
K0.2 ostatních agend je průběžné podle [registru cest](REFACTORING_ROUTE_REGISTER_2026-10-29.md).
