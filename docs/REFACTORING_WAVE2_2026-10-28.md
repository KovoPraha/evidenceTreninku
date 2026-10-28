# Druhá vlna: programové nabídky ve veřejném e-shopu

Zahájeno 28. 10. 2026 na pokyn vlastníka „Pokračuj tedy“ po vydání první vlny.
Výchozí main `4215eac93dd207142a16dfef57cda1464717cba3`, produkce `20ab8637`.
Větev `codex/refactoring-wave2-20261028`, izolované worktree mimo htdocs.
Původní checkout a jeho WIP se nepoužívají. Navazuje na
[první vlnu](REFACTORING_WAVE1_2026-10-28.md) a
[produkční měření](verification/refactoring-release-20261028/RESULTS.md).

## Rozsah a postup

1. Změřit SQL a náklady programových variant v detailu, katalogu a navigaci
   na syntetických datech včetně sdílené kapacity, termínů a zveřejněných souhlasů.
2. Před implementací doplnit reprodukovatelnou regresi výkonnostního kontraktu.
3. Odstranit opakované čtení metadat a stejné nabídky v jediné čtecí operaci;
   zachovat kanonické vyhodnocení kapacity a podmínek. Žádná dlouhodobá cache.
4. Ověřit stejné výstupy a čerstvé rozhodnutí po změně kapacity, stavu nabídky,
   času a podmínek; checkout musí dál znovu ověřovat aktuální stav pod zámkem.
5. MariaDB a HTTP porovnání na stejné fixture, 5 zahřátí + 30 měření,
   počty SQL, medián/p95, paměť, EXPLAIN a funkční průchod.
6. Dotčené testy, úplné Linux CI včetně MariaDB 10.3/11.4, PHPStan,
   bezpečnostní sweep a dokumentace; předání v PR s výsledky a limity.

Nezahrnuje změny finančních pravidel, účtů/oprávnění, datové importy,
integrace ani doplnění skutečných provozních dat. Další produkční vydání
posoudit podle skutečně dokončeného rozsahu a postupu NASAZENI.
Členské ceny měnit pouze tehdy, pokud měření prokáže jejich nutnost v této vlně;
jinak ponechat samostatnému balíku s regresí revokace rodinné vazby.

## Průběžný stav

- [x] Aktuální main, čisté izolované worktree, čtení provozních důkazů a plánu.
- [x] Syntetický profil a původně selhávající regresní test (1 801 metadatových dotazů).
- [x] Cílená implementace a 7 nových regresních testů; zamykání ignoruje starý kontext.
- [x] MariaDB/HTTP 5+30, browser a Linux CI aplikačního headu `1463da73`, 1 764/35 753 PASS.
- [x] PR #211, [výsledky a limity](verification/refactoring-wave2-20261028/RESULTS.md).

Výsledek pro 50 programů: navigace 2 608 → 417 SQL, metadata 1 801 → 10.
Všech 9 MariaDB výstupů shodných. HTTP medián detailu zboží 7,31 → 0,53 s,
programu 6,53 → 0,86 s, katalogu 8,41 → 2,49 s na syntetické fixture.
Členské ceny nebyly měněny; další výkonový kandidát je prezentace programů
v katalogové stránce. Produkční vydání této druhé vlny neproběhlo.
