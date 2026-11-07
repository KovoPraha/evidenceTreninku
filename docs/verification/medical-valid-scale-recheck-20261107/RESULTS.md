# P11-R13-S — ověření na původních větších sadách

7. 11. 2026: ROZPRACOVÁNO. Žádná změna produkce v této relaci.

[Plán](../../MEDICAL_STATE_READ_PERFORMANCE_2026-11-07.md), [předání](../../SESSION_HANDOFF_MEDICAL_STATE_2026-11-07.md).
Nové zadání překrylo již hotové #381/#383. Neopakovat implementaci ani deploy;
nezávisle ověřit skutečnou optimalizaci na stejném relativním fixture #375:
10k/50k/100k sledování, tři záznamy na sledování, 10 % odvolaných posledních záznamů.
Původní vyhodnocení a současný kód na jediném seedovaném objemu; stejné session,
párové celé HTTP, úplná doménová/HTML shoda a skutečné rows examined odděleně.
Kontrolovat stránkování, hledání, osobu, datum a zamítnuté role.

Větev `codex/medical-valid-recheck-20261107`, base `35fdad44`, vlastní worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-medical-valid-recheck-20261107`.
Žádný produkční config, skutečné osoby, zdravotní dokumenty, zprávy ani finanční operace.
Zachovat původní WIP SHA256 `7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.

Další krok: izolovaný runtime, kontrola zdrojů, původní seed #375, měření/shoda,
bezpečný úklid, zápis výsledků a PR. Pokud již přijatý kód splní zadání,
nevytvářet další aplikační změnu jen kvůli duplicitnímu požadavku.
