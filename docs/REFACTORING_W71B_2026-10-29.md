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

Plán uložen před implementací. Race prokázán: 15 nepovolených zápisů / 24
scénářů před opravou; po opravě 32/32 PASS lokálně a v CI MariaDB 10.3/11.4.
Windows 1808/36046 (3 POSIX skipy), Linux 1808/36095 a POSIX 3/45 bez skipů.
PHPStan, styl, Composer, dokumentace, anonymous sweep a JS PASS. AST všech
47 funkcí shodné mimo tři nové guardy. PR #227 sloučen jako
`66521929f7e9ce55f7a03d669cb7821f49382f6c`. Přesný main CI PASS; nasazeno
[40939479761](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40939479761).
[Produkční protokol](verification/refactoring-w71b-release-20261029/RESULTS.md):
živá identita potvrzena, před/po 26/26 HTTP, záloha 336 tabulek / 2 triggery,
config/flags/ledger shodné, VS zachovány. UAT/import kroužků false.
[Konkrétní důkazy](verification/refactoring-w71b-20261029/RESULTS.md).
Po tomto balíku navazují úplné profily R02–R04 podle
[komplexního plánu](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md).
