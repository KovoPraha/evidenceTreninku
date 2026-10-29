# W7.1b — oprávnění během souběžného zápisu

Zahájeno 29. 10. 2026 z `b1db19ae6dacb29ea6cfac65261176250d91e3f3`,
větev `codex/refactoring-w71b-authorization-20261029`, izolovaná pracovní kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-refactoring-w71b-20261029`.
Vlastník autorizoval autonomní implementaci, merge a chráněné nasazení po
ověření. Původní checkout a cizí WIP zůstávají zachované.

## Kontrakt a postup

1. Reprodukovat revokaci během prokazatelného InnoDB LOCK WAIT pro
   `trainingRsvpSave`, `clubMakeupReserve`, `clubMakeupCancelReservation`.
   Rodinná role, aktivita/ověření účtu a samostatný sportovní účet; pozitivní
   kontrola, rollback revokace a nulové doménové/auditní změny při odmítnutí.
2. Až po RED důkazu doplnit aktuální zamykající autorizační kontrolu uvnitř
   transakce po doménových zámcích, před prvním zápisem. Zachovat oddělené
   identity, stávající chybové zprávy a veřejné čtecí helpery bez zámků.
   Oprávnění musí zůstat stabilní až do commit/rollback; běžný snapshot SELECT
   nestačí. Ověřit pořadí zámků a souběžné zápisy.
3. Izolované syntetické DB, nativní prepares, skutečné dva procesy a
   deterministická bariéra místo odhadované prodlevy; MariaDB 10.3/11.4 v CI.
   Žádné produkční zápisy testovacích dat, aktivace služeb nebo schéma změny.
4. Dotčené i úplné testy, PHPStan bez nové baseline, styl, dokumentace,
   zelený PR i přesný main. Pak před/po čtecí audit, HTTP, chráněný deploy,
   živá identita, konfigurace a migrační stav; UAT/import kroužků false.

## Stav

Plán uložen před implementací. Výchozí main má čtyři CI workflow SUCCESS.
Race prokázán: 15 nepovolených zápisů / 24 scénářů před opravou. Po opravě
32/32 PASS včetně držení oprávnění do konce transakce. Dotčené testy 30/263,
styl a AST shoda 47 funkcí mimo tři nové guardy PASS. Úplná sada a PHPStan
probíhají, CI/merge/deploy dosud neprovedeny.
[Konkrétní důkazy](verification/refactoring-w71b-20261029/RESULTS.md).
Po tomto balíku navazují úplné profily R02–R04 podle
[komplexního plánu](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md).
