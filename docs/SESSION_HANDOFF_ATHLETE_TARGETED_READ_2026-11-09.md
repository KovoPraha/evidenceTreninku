# Předání P11-RIX — 9. 11. 2026

ROZPRACOVÁNO, fáze skutečné schéma → diagnóza → optimalizace.
[Plán](ATHLETE_PROFILE_TARGETED_READ_2026-11-09.md),
[důkazy](verification/athlete-profile-targeted-read-20261109/RESULTS.md).

Base mainb66cc059 / runtime17110a7f, větev
codex/athlete-profile-targeted-read-20261109, stejný izolovaný worktree mimo
htdocs. Původní WIP7a7afd75 a větev fio zachované.

Zbylý dominantní profilový souhrn/header/historie závisí na indexech. CI
fixture nemá idx_sportovec/idx_mereni z referenčního schématu; skutečnou
produkci nelze odhadnout z jejího plánu. Nejprve bezpečná metadatová
inventura přes chráněný read-only workflow. Podpůrný CLI/workflow PR se
po testech/CI sloučí bez deploye aplikace. Potom odpovídající syntetická
fixture a nativní diagnóza obouDB; výběr konkrétního nejdražšího čtení.

Aktuální pokyn dovoluje při plné shodě/párovém přínosu/aktuálních CI
commit/merge/protected deploy optimalizace. Gate20% a kontrolní1.2+20ms,
žádné role/importy/platby. Podpůrný audit sám úkol neuzavírá. Další krok:
CLI metadata nástroj/testy a workflow. Nový app merge/deploy NOT RUN.
