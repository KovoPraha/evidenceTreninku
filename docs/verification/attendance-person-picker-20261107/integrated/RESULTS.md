# P11 picker — společné měření aktuálního main, 7. 11. 2026

ROZPRACOVÁNO. [Plán](../../../ATTENDANCE_PERSON_PICKER_2026-11-07.md),
[předání](../../../SESSION_HANDOFF_ATTENDANCE_PERSON_PICKER_2026-11-07.md),
[PR #366](https://github.com/KovoPraha/evidenceTreninku/pull/366).

Nové zadání vlastníka výslovně povoluje commit, merge a nasazení po shodě výsledků
a měření přínosu. Původní místní pár zůstává [samostatným historickým důkazem](../final/RESULTS.md).

## Metoda pokračování

Aktuální main před pickerem proti stejnému main s pickerem. Obě varianty používají
identické aktuální `training_day.php` po #367; runner tuto shodu ověřuje proti Git blobu.
Jediný nový syntetický dataset: 10221 osob, 728 plánů, 200 účastníků a 52 revizí,
zdravotní fixture 10k sledování / 30k záznamů. Bez reseedu mezi variantami.
Přepíná se jediná změněná aplikační stránka; nový helper je v before nepoužívaný.
Všechny ostatní produkční zdroje obou stromů jsou shodné.

Izolovaný Linux CI, MariaDB 11.4 service, PHP 8.2 a skutečný Chrome přes Playwright CLI 0.1.23.
21 HTTP případů na variantu, 5 warmups + 30 časů v 9 pozitivních případech;
3 role × 3 cold a 10 warm browser pozorování. Explicitní viewport 1036 × 703.
Zvlášť instrumentovaná diagnostika se striktně shoduje s prostým HTML každé varianty;
0 DML/DDL, 13 doménových checksumů. Aktuální oprávnění, negativní role, výběry,
zdroj, čas, verze a historie musí zůstat stejné; množina všech osob ověřená zvlášť.
Teprve potom explicitní syntetické writer UAT a revokace; nikdy produkční osoby.

Příkazy a kontrolovaný postup jsou verzované v
`tests/Support/AttendancePickerIntegratedProfile.py` a `.github/workflows/attendance-picker-performance.yml`.
Uploaduje se pouze whitelist bezpečných JSON, nikoli config, hesla, cookies,
HTML nebo soukromé CLI logy. Úklid vlastní DB, účtu, configu a session cookie jars
provádí původní guardovaný nástroj; PHP proces se zastaví a aplikační soubor obnoví i při chybě.
CI službu následně odstraní GitHub. Nové kompletní měření zatím NOT RUN.

## Místní ověření a neúspěšná kalibrace

- Cílená místní sada po převzetí #369: **22 testů / 1144 assertions PASS**.
  Samostatné kanonické writer/coach/makeup testy a úplnou sadu doplňuje CI.
- Čerstvý izolovaný Windows runtime `kis-picker-integrated-20261107-b96f611b`,
  localhost 33479, schéma `p11_read_bb466b3dee0c41cc`: fixture skončilo při GRANT
  chybou Aria 176 (wrong checksum v `mysql.db`). Místní měření se vůbec nespustilo.
- Oprava pouze vlastní systémové tabulky selhala při synchronizaci Aria logu
  (bad file descriptor). Neúspěch není PASS; instance zastavená po ověření PID,
  přesného datadiru a portu. Adresář zůstal pro diagnostiku, nic se plošně nemaže.
  Žádný cizí ani produkční server se neopravoval. Nové měření proto v Linux CI.

Stav commit/CI/merge/deploy se doplní podle skutečného výsledku, žádné nové
produkční nasazení pickeru dosud neprovedené. Původní WIP v htdocs i původní
picker worktree zachované; pokračování je ve vlastním worktree mimo htdocs.
