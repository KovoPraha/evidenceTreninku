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


Support #424/ff182971 a metadata42704641179 SUCCESS, úklid PASS. App deploy
neproběhl. Inventura je v důkazech; indexy vlastníka/reverse v produkci existují,
odhady měření jen17. Nová větev codex/athlete-targeted-read-optimization-20261109
z ff182971. Probíhá syntetická nativní diagnóza s doplněnými shodnými indexy
před oběma variantami. Samotný prostý ownership UNION není vybraný (SQLite
regrese hustého profilu). Další krok nativní náklad parent/history/MZ pro4SID.

Diagnóza42705957545/c9 PASS oběDB a cleanup, raw v diagnostic-c9. Největší
souhrn288/1021ms; plainJOIN/IN historie nevybrané. Další krok grouped summary
join order + narrow materializace, stále bez app změny/prod DDL. První dva
přípravné FAIL zůstávají v RESULTS, nejsou PASS ani produkční incident.

SQL order/narrow/HEX a automatický nový cover index nepřinesly požadované
zrychlení (42706823562/42706996238/42707779079), raw uložené a nevybrané.
Další krok pouze syntetický FORCE cover probe; dosud žádná app/migrační
implementace. Žádný nový app merge/deploy. Zápisový probe bude čistý INSERT
VALUES, starý INSERT SELECT zahrnoval i čtení. Všechny WIP zachované.

FORCE index42708406091 nevybraný (10.3 regrese,11.4 pod20%). Žádná migrace
nevznikne. Další koncept ownership historie se stejným filtrem roku; základní
SQL aktuální rok230/293ms, all SQL zůstane původní. Další krok nativní probe.
