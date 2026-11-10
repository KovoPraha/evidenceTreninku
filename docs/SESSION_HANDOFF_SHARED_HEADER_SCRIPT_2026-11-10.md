# Předání P11-HJS — 10. 11. 2026

ROZPRACOVÁNO, před implementací. [Plán](SHARED_HEADER_SCRIPT_PERFORMANCE_2026-11-10.md),
[důkazy](verification/shared-header-script-20261110/RESULTS.md).
Base main6dee3253, runtime9dbd9eaf/deploy42727693090 SUCCESS; nic nového nasazeno.
Vlastní větev codex/shared-header-assets-performance-20261110, worktree mimo htdocs.
Původní fio WIP/kis_rosters_admin SHA7a7afd75 zachovaný.

Analýza7: poslední staff-only inline script má14717bytes těla/9interpolací.
Kandidát: veřejný verzovaný JS, šest čerstvých hodnot v privátním JSON HTML;
bez defer, aktivace hledání/push, změny SQL/rolí/session/writerů nebo CSS.
Akceptace: mechanická shoda JS/PHP hodnot, celý HTML/business a bezpečnost
obou DB, >=12kB menší HTML + teplý cache payload0,17paired cold/warm Chrome
bez časové regrese, shodné UI a funkce. Pak celéCI/merge/protecteddeploy aGETpo.
Další krok implementace/kontrakty; nové testy/CI/merge/deploy zatím NOT RUN.
