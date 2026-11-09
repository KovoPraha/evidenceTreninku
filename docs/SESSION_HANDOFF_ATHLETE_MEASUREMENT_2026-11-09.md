# Předání P11-RHM — 9. 11. 2026

[Plán](ATHLETE_PROFILE_MEASUREMENT_PERFORMANCE_2026-11-09.md),
[výsledky](verification/athlete-profile-measurement-performance-20261109/RESULTS.md).
ROZPRACOVÁNO, baseline5ff9fa2a, produkce c3d34b87/deploy42625502461 SUCCESS.
Uživatel povolil podmíněný commit/merge/deploy po shodě a přínosu.
Větev codex/athlete-measurement-performance-20261109, WIP zachovaný.
Další krok: kandidát + nativní parita/HTTP/Chrome. Zatím žádný nový deploy.

## Lokální kandidát

Implementována MariaDB agregace s jedním JSON klíčem na binární typ a COUNT,
připojení reprezentantů až po agregaci. Typové filtry i stránka zůstávají
původní. Podmíněná verze>=10.3.3 a SESSIONlimit>=128; bez nastavování DB.
Původní window souhrn zachovaný pro SQLite/MySQL/krátký limit. Metadata dotaz
přidává1SQL na MariaDB, případný přínos se musí projevit v celémHTTP.

Lokálně SQLite10testů/1139assertions PASS (původníprofil/historie/header + nové
3testy/247), PSR12 všech6dotčenýchPHP, PythonAST a composer validate --strict PASS.
Jde o portable/fallback paritu včetně64bitklíčů; skutečný novýSQLfastpath a
limity128/4 musí prokázat nativní CI na MariaDB10.3/11.4. Zatím NOT RUN.
Žádná změna readeru mimo measurements()/nový helper, parent/endpoint/UI/
security/session/writery byte-nezměněné. Baseline3soubory zmrazené z5ff9fa2a.
Vlastní runner připraven pro1k/10k/30k,8scénářů/3profily,4warmups+17párů,
oddělenou SQL/PHP diagnostiku a52Chrome navigací obou režimů.

## Pokus 1 — JSON agregace odmítnuta

PR #418, commit3b7fbfba, [native run42632034781](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42632034781).
Nativní přesná parita obou DB3testy/499assertions PASS; celé HTTP/role/Chrome
provedeny, výkonnostní akceptace FAIL. Mediány30k celé odpovědi:

MariaDB10.3: 756.473 → 1215.773 ms (+60.7%).
MariaDB11.4: 798.339 → 839.414 ms (+5.1%).

První bezpečné vzorky, diagnostika, testy, security a cleanup zachované v
[rejected-json](verification/athlete-profile-measurement-performance-20261109/rejected-json/failed-run-identity.json).
Souhrnný report nevznikl, protože gate před ním správně zastavila běh.
Identita prvního pokusu odvozena z Gitblobs a označena jako odvozená;
nový runner ukládá skutečné source-identity hned při startu. Zatím nic nasazeno.

## Pokus 2 — integer klíč a omezení prvních stránek

JSON serializaci/parsování nahrazuje přesný integer tuple s oddělovačem `:`
a tokenem `N` pro NULL pozici. Dekódování DECIMAL(65,0), původní verze/limit
fallback zachovány. Tréninky pro stránky1–32 se nejdřív omezí na prvních
page*25 s EXISTS vlastní vazby a přesně stejného typu. Každý takový trénink
přispívá alespoň jednou vazbou; proto nemůže chybět žádná potřebná položka.
Vlastník/typ se znovu ověří v detailním výběru. Pozdější stránky zůstávají původní.
Test nově pokrývá80vazeb jednoho tréninku, přechod32/33 a prázdné/typové filtry.
Lokálně nové4testy/271assertions PASS. Nativní přínos druhého pokusu čeká;
release pouze po nové shodě, měření a celém CI.

## Pokus 2 odmítnut, pokus 3 — pouze bounded selector

[Run42634946860](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42634946860),
head3b127f98, MariaDB10.3 native4/523 PASS, sourcehashes přesné, HTTPparita PASS,
výkon FAIL:30k primary519.448→914.333ms (+76.0%).
Samostatná diagnostika: původní souhrn410.259→748.548ms, ale detail164.894→4.190ms.
Druhá agregace je stále pomalejší; zamítnuta. První/nové vzorky se nenahrazují.
[Zdrojová identita a vzorky](verification/athlete-profile-measurement-performance-20261109/rejected-integer/native-10.3/source-identity.json).
MariaDB11.4 tohoto pokusu dosud běží, bezpečné výsledky budou doplněny.

Třetí kandidát vrací původní numeric-window souhrn byte-přesně, odstraňuje
nový helper/verzový probe/metadata SQL a ponechává jen bounded training selector
pro stránky1–32. Získané typy/součty ani jejich pořadí se nepočítají jinak.
Čerstvé GROUP_CONCAT limity128/4/1048576 nadále pokryté paritou a nezměněnou
SESSION hodnotou; původní souhrn se o limit neopírá. Detailní scope/type recheck
zachovaný. Nové celé native/HTTP/Chrome měření a CI před případným release.
