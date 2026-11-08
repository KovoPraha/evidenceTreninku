# P11-R13-F — provozní předání

8. 11. 2026: PŘÍPRAVA, merge/deploy NOT RUN.
[Plán](../../MEDICAL_FILTER_READ_PERFORMANCE_2026-11-07.md), [parita/přínos](../medical-filter-read-performance-20261107/RESULTS.md), [předání](../../SESSION_HANDOFF_MEDICAL_FILTER_2026-11-07.md).
Aktuální zadání dovoluje merge a standardní deploy po shodě, přínosu a zelených CI.
V1 a V2 měly datumové regrese, proto nešly do produkce. V3/a88cabc8 je v CI.
Dosavadní runtime938e9c50 zachovaný. Bez skutečných zdravotních/osobních/rolových změn.

Připravované čtecí ověření: pět anonymních veřejných katalogových GET a anonymní
odmítnutí zdravotního přehledu s q/datem/stavem. Jen veřejná ID/počty/hash/status,
žádné HTML/cookies/přihlášení/soukromé soubory v důkazu. Přihlášené produkční UAT NOT RUN.
Po zeleném výkonu/testech fresh main, přesná shoda stromu/měřených hashů, deploy
s import/UAT false, kontrola zálohy/VS/aktivace/smoke, poté opakované čtecí ověření.
