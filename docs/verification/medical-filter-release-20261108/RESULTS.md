# P11-R13-F — provozní předání

8. 11. 2026: PŘÍPRAVA, merge/deploy NOT RUN.
[Plán](../../MEDICAL_FILTER_READ_PERFORMANCE_2026-11-07.md), [parita/přínos](../medical-filter-read-performance-20261107/RESULTS.md), [předání](../../SESSION_HANDOFF_MEDICAL_FILTER_2026-11-07.md).
Aktuální zadání dovoluje merge a standardní deploy po shodě, přínosu a zelených CI.
V1–V4 měly datumové/pending regrese, proto nešly do produkce. V5 s krycím indexem se měří odděleně i spolu se změnou readeru. Lokální medical + deploy kontrakt 44/1328 PASS, migrace 1/91 PASS; nativní a HTTP/Chrome V5 přínos/parita PASS; po převzetí #387/Q01 se dokončuje CI se sjednoceným schématem historických benchmarků.
Dosavadní runtime938e9c50 zachovaný. Bez skutečných zdravotních/osobních/rolových změn.

Připravované čtecí ověření: pět anonymních veřejných katalogových GET a anonymní
odmítnutí zdravotního přehledu s q/datem/stavem. Jen veřejná ID/počty/hash/status,
žádné HTML/cookies/přihlášení/soukromé soubory v důkazu. Přihlášené produkční UAT NOT RUN.
Po zeleném výkonu/testech fresh main, přesná shoda stromu/měřených hashů, deploy
s import/UAT false, kontrola zálohy/VS/aktivace/smoke, poté opakované čtecí ověření.
