# Druhé autonomní funkční ověření — 2. 11. 2026

Následné autorizované [sloučení PR #274 a nasazení](verification/functional-round2-release-20261102/RESULTS.md) mají samostatný protokol.
Níže je zachován rozsah původního místního běhu před tímto vydáním.

Vlastník povolil další autonomní testování, opravy a jejich ověření.
Změna hesla a T07 jsou vynechány. Tento běh neopakuje předchozí produkční
nasazení; případné nové změny budou předány přes vlastní PR a CI.

Výchozí main `25085e4ef4d771d9d36429265e61b0f0c5053d88`, pracovní kopie
`evidencePavel-functional-uat-round2-20261102`, větev
`codex/functional-uat-round2-20261102`. Původní WIP ani první syntetická DB
se nemění. Žádné produkční zápisy, maily, nové granty nebo aktivace integrací.

## Postup

1. Nová vlastní MariaDB pouze loopback a čerstvý verzovaný schema/migrace/demo
   seed. Samostatný Apache, soukromé session/storage mimo webroot, mail/cURL
   transporty vypnuté. Pouze fiktivní místní identity a bankovní údaje NEPLATIT.
2. Skutečným Chrome ověřit skladové zboží a kladné uplatnění kupónu,
   klubovou akci včetně zrušení přihlášky a rezervaci velodromu. Rozšířit
   související chybové/autorizační scénáře podle pozorované nabídky UI.
3. Prokázat uložení čtecí kontrolou pouze vlastní DB. Nálezy reprodukovat,
   opravit v místě příčiny, přidat účelnou regresi a zopakovat browser scénář.
4. Zapsat konkrétní PASS/FAIL/limity, spustit přiměřené testy, PR a CI.
   Nepřekládat místní PASS jako produkční přejímku. Na konci odhlásit účty,
   resetovat případný viewport, zavřít vlastní karty a zastavit vlastní servery.

## Stav

Dokončeno 35 konkrétních kontrol a jedna oprava F04 v localhost seedu;
[protokol](verification/functional-uat-round2-20261102/RESULTS.md). Skutečná
MariaDB reprodukovala původní selhání a ověřila opravu staršího stavu i dvě
spuštění čerstvého seedu. Historické objednávky/platby/rezervace beze změny.
Čerstvý browser checkout smíšeného košíku 450,69 Kč ověřen i v DB.

Windows 1905/38260, 3 známé POSIX skipy; cílené 7/71, PHPStan, styl a anonymní
sweep PASS. Dočasné diagnostické PHP soubory přesunuty mimo zdrojový strom,
baseline nezměněna. Testovací servery zastaveny a karty zavřeny, data uchována.
Zdrojové formátování vyžadovalo povinné CI; sémantický diff doložen parserem.
Předchozí CURRENT_STATE přesně archivován; dokumentace aktualizována.

Místní implementace a ověření dokončeny. Navazuje vlastní PR a přesný Linux /
MariaDB CI jeho headu; jejich stav určuje GitHub. Produkce se v tomto běhu
neměnila, žádné další nasazení nebylo provedeno. Heslo a T07 vynechány.
