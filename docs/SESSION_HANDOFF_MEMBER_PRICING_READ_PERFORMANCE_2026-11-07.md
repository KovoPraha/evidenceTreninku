# Předání — dávkové klubové ceny e-shopu 7. 11. 2026

Stav: IMPLEMENTACE, PARITA A MĚŘENÍ HOTOVÉ; #358 v main `37d0ad68`, bez nasazení.
Zadání vlastníka: optimalizovat klubové ceny, ověřit shodné výsledky a změřit
před/po, zachovat WIP, průběžně aktualizovat plán v repu. Bez produkčního deploye.

Kanonický [plán a průběžný stav](MEMBER_PRICING_READ_PERFORMANCE_2026-11-07.md),
[výsledky](verification/member-pricing-read-performance-20261107/RESULTS.md),
[registr P11](WORK_PLAN_CURRENT.md).
Větev `codex/member-pricing-read-performance-20261107`, kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-member-pricing-performance-20261107`,
výchozí main `feb8fe8c`; R05/R13/R06 již obsahuje. Původní WIP zachované.

Hotovo: převzetí zdrojů, izolace a verzovaný plán `911de8e1`; implementace
readeru a testů `0890e496`. Zmrazený původní algoritmus, společná měřicí fixture,
zapojení katalogu/detailu a transakční fallback. Cílené 20 testů / 301 assertions
PASS; MariaDB 10.3/11.4: 500 produktů 2000 → 4 SQL, 2500 → 504 načtených řádků,
medián cenové operace 557/377 → 3,21/2,88 ms. Nová data a práva
se čtou při každém volání. Styly a snížený PHPStan baseline budget PASS.
[PR #358](https://github.com/KovoPraha/evidenceTreninku/pull/358), měřený commit
`0890e496`: všech 11 kontrol PASS, plný Linux 2321/42987 a 3/45, obě MariaDB
39/39 kolací/transakčních kontrol, shodné celé výsledky všech 9 profilů.
Vzorky a EXPLAIN v protokolu; následná regrese skutečných katalogových karet
má cílený PASS; finální head `02ad8016` má 11 kontrol PASS a #358 je sloučený.
Žádné nasazení se neprovedlo. Časy nejsou celá stránka ani produkční SLA.

Vlastní místní služby zastavené; místní neuzavřené Windows/MariaDB běhy
se nevydávají za PASS. Automatická kontrola odmítla rekurzivní odstranění
dvou datadirů jako `blocked by policy`; adresáře zůstaly, přesné cesty
v protokolu. Žádný pokus o obejití. Původní WIP má shodný otisk.

Další samostatná práce: katalogový výřez, přihlášené celé HTTP/bootstrap/nav,
souběh session a browser metriky. Celé K0.2 ani tyto kroky nejsou tímto uzavřené.
Checkout, schéma a produkční konfigurace se nemění. Aktuální release
a provozní flags jsou výhradně v [CURRENT_STATE](CURRENT_STATE.md).

## Začlenění 7. 11. 2026

#358 sloučený podle pokynu vlastníka: `37d0ad687211aa912a90e8ad105842847956890d`,
head `02ad8016`, 11 kontrol PASS (Testy 42362702007). Bez deploye.
Navazuje [katalogový plán](CATALOG_READ_PERFORMANCE_2026-11-07.md).
