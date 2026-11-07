# Předání P11 katalog — 7. 11. 2026

[Plán a akceptace](CATALOG_READ_PERFORMANCE_2026-11-07.md),
[výsledky](verification/catalog-read-performance-20261107/RESULTS.md).
Stav ROZPRACOVÁNO; vlastní větev/worktree jsou v plánu. #358 sloučený `37d0ad68`; 11 kontrol PASS, deploy zakázaný. Původní WIP zachovaný.
Omezená projekce implementovaná; cíleně 21 testů / 1357 assertions PASS.
Další krok: dokončit párové měření, kvalitu, nativní MariaDB CI a PR.
Objemové měření a CI probíhají; produkční UAT/deploy NOT RUN.

[PR #364](https://github.com/KovoPraha/evidenceTreninku/pull/364) otevřený jako draft.
První CI: nativní parita/měření 10.3 i 11.4 PASS, kvalita PASS;
jedna stará wiring kontrola velikosti stránky opravena, úplné CI se opakuje.
Dle měření doplněn fallback pro nejvýše 64 aktivních produktů (sonda max. 65 ID).
Finální časy zatím nejsou uzavřené, původní reporty jsou pouze před touto úpravou.
