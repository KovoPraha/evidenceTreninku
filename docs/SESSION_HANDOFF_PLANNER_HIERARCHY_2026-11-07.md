# Předání P11-R05-H — plánovač

7. 11. 2026: **HOTOVO pro technický řez**, [plán](PLANNER_HIERARCHY_READ_PERFORMANCE_2026-11-07.md).
[PR #374](https://github.com/KovoPraha/evidenceTreninku/pull/374) sloučený; aktuální nasazený runtime
`e36c9ba6e461b7da842c53939c2fe20eed821f38`, [deploy 42394551344](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42394551344) SUCCESS.
[Provozní protokol a meze](verification/planner-hierarchy-release-20261107/RESULTS.md).

- Historický souhrn při filtru soupisky přenáší různá data, COUNT/MIN přes EXISTS.
  Předci/potomci platní ke dni lekce, bez sourozenců a dvojího započtení.
  Malé historie, transakce a případy nad limity původní cestou; bez cache mezi voláními.
- Frozen baseline69f49033, runtime 65440e3b, integrovaná hlava 87130cfe všech 12 CI PASS;
  celý sloučený strom stejný, source hashe shodné. Linux 2370/47564, obě DB 8/1170.
- [Měření](verification/planner-hierarchy-read-performance-20261107/RESULTS.md):
  velký GET trenér70,59→47,36ms, vedoucí116,25→46,77ms;1360 HTTP / 104 Chrome,
  celé HTML/13 checksumů shodné, žádné DML/DDL v diagnostice, úklid PASS.
  Helper 36k10.3 127,23→2,07ms /11.4 151,47→45,56ms; řádky 72946→167.
- Záloha/migrace/VS/aktivace/HTTP PASS; workflow import/UAT false, žádná aktivace.
  Veřejné před/po 5/5 DOM/pole shodné; anonymní plánovač před/po302login.
- Přihlášené produkční UI UAT NOT RUN. Blokované picker UAT #377 zachované;
  rows examined, fyzický mobil a souběžná produkční zátěž otevřené.

Původní checkout `codex/fio-auto-reconciliation-recovery` a neversionované výstupy zachované.
`kis_rosters_admin.php` SHA256 `7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5` stejný.
Implementační větev `codex/planner-hierarchy-read-performance-20261107`;
výsledkové předání `codex/planner-hierarchy-release-proof-20261107`, stejný vlastní worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-planner-hierarchy-performance-20261107`.
Výsledkové dokumenty po CI sloučit do main; další deploy neopakovat.
Dále samostatné R13/rows examined/mobil/souběh; celé P11/K0.2 není uzavřené.
