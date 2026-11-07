# Deník událostí projektu

Průběžná evidence významných změn stavu, plánů, implementací, ověření a předání.
Časy/dny jsou Europe/Prague, pokud zdroj neuvádí UTC. Nové záznamy přidávej
nahoru s jedinečným ID `EYYYY-MM-DD-NN`, vazbou na práci a odkazy.
Nezapisuj každý příkaz; zachyť co se změnilo, výsledek/meze a další krok.
Aktuální úkoly jsou v [registru](WORK_PLAN_CURRENT.md), důvody voleb a rejstřík
uzavřených prací v [rozhodovacím logu](DECISION_LOG.md).

| Datum / ID | Práce a událost | Výsledek / následující krok | Trvalé podklady |
| --- | --- | --- | --- |
| 7. 11. 2026 / E2026-11-07-P15-S2S3-PROMPT | P15: prompt pro druhé pracoviště připraven podle zadání vlastníka | Syntetické adaptéry/transport proti S1, vlastní worktree a testovací originy; bez přístupu k Rikimu/portálům, instalace a aktivace NOT RUN. | [Prompt](CSC_SPORTSOFT_S2_S3_STATION_PROMPT.md), [plán](CSC_SPORTSOFT_READ_SYNC_PLAN_2026-11-07.md), draft [#376](https://github.com/KovoPraha/evidenceTreninku/pull/376) |
| 7. 11. 2026 / E2026-11-07-P15-S1-DONE | P15 S1: serverová implementace a syntetická přejímka HOTOVO | 2fe8aa93, 11 CI PASS; 2381/46650, MariaDB 10.3 i 11.4 každý 14/166, neprázdný restore 8 tabulek. Draft PR, bez merge/deploye/aktivace; další S2/S3 a cílová S0 inventura. | [S1](CSC_SPORTSOFT_S1_KIS.md), [důkazy](verification/csc-sportsoft-s1-20261107/RESULTS.md), [PR #376](https://github.com/KovoPraha/evidenceTreninku/pull/376) |
| 7. 11. 2026 / E2026-11-07-P15-S1 | P15: zahájená a lokálně implementovaná serverová S1 podle zadání vlastníka | Osm tabulek, machine ingest, lease/manifest a UCI resolver; první syntetické testy PASS, rozšířené ověření probíhá. Bez nasazení/aktivace. | [S1](CSC_SPORTSOFT_S1_KIS.md), [výsledky](verification/csc-sportsoft-s1-20261107/RESULTS.md), [rozhodnutí](DECISION_LOG.md#d2026-11-07-p15-s1) |
| 7. 11. 2026 / E2026-11-07-P15-S0 | P15: kontrakt a předání nonstop stanici | 4 čtecí kolekce, schéma/fixtures, požadavky, bezpečná inventura a prompt hotové; cílová inventura otevřená. Vlastník vyloučil kontakt s Riki daty; žádný portálový zápis/aktivace. | [S0](verification/csc-sportsoft-s0-20261107/RESULTS.md), [rozhodnutí](DECISION_LOG.md#d2026-11-07-p15-s0), [PR #371](https://github.com/KovoPraha/evidenceTreninku/pull/371) |
| 7. 11. 2026 / E2026-11-07-24 | P15: čtecí průzkum a plán ČSC/Sportsoftu | Vlastník vybral UCI ID a nonstop stanici. Plán, datový kontrakt a etapy hotové v [PR #371](https://github.com/KovoPraha/evidenceTreninku/pull/371); implementace/aktivace NOT RUN, další S0. | [Plán](CSC_SPORTSOFT_READ_SYNC_PLAN_2026-11-07.md), [protokol](verification/csc-sportsoft-discovery-20261107/RESULTS.md), [rozhodnutí](DECISION_LOG.md#d2026-11-07-p15); codex/csc-sportsoft-read-sync-plan-20261107 |
