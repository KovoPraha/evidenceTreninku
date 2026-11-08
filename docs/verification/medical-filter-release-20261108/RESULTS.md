# P11-R13-F — produkční předání

8. 11. 2026: **NASAZENO / technické ověření PASS**.
[Plán](../../MEDICAL_FILTER_READ_PERFORMANCE_2026-11-07.md), [výkon](../medical-filter-read-performance-20261107/RESULTS.md), [handoff](../../SESSION_HANDOFF_MEDICAL_FILTER_2026-11-07.md).

PR [#385](https://github.com/KovoPraha/evidenceTreninku/pull/385) sloučený jako
`519ff098961943f1aa58a70e40fed3ffba37cc96`, po všech 15 CI PASS na `2362f3a4`.
[CI snapshot](ci-checks.json), [shoda celého stromu a měřených zdrojů](tree-identity.json).
Převzaté již sloučené #387/#388 a úklid rychlého nákupu #389, vše integračně ověřené.

[Deploy 42477866185](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42477866185)
**SUCCESS**, import/UAT false. [Metadata běhu](deploy-summary.json).
Deploy znovu ověřil 2389 testů / 48273 assertions; úplná záloha **389 tabulek / 2 triggery**
ověřená před migrací. Migrace current / pending [], dosavadní VS beze změny.
Aktivace, standardní smoke i odstranění vlastních diagnostických souborů PASS.

## Skutečný produkční index

MariaDB 10.3.39, InnoDB. [Před migrací](index-preflight/medical-index-before.json)
12:05:14 UTC krycí index chyběl. [Po migraci, před aktivací](index-preflight/medical-index-after.json)
12:07:28 UTC přítomný neunikátní BTREE `idx_medical_clearance_read`:
`(watch_id,id,state,issued_on,valid_until,replaces_id)`, plné délky sloupců.
Definiční SHA256 shodná s vydaným zdrojem. Obě diagnostiky pouze metadata v READ ONLY
transakci, clinical_rows_read=false / ddl_executed=false; žádné zdravotní hodnoty v důkazu.
InnoDB nahradil automatický watch_id index jeho novým krycím prefixem; ostatní indexy zachované.
Klinická data se migrací neměnila. Přidaný index je kompatibilní i s původním readerem.

## Čtecí kontrola před a po

[Veřejné před](before.json), [veřejné po](after.json): **5/5 HTTP 200 a úplný normalizovaný
DOM/veřejná pole shodná** (katalog, řazení cenou, stránka 2, hledání, prázdné hledání).
[Zdravotní filtr před](anonymous-medical-filter-before.json),
[po](anonymous-medical-filter-after.json): **403 v obou fázích**.
Jen anonymní GET bez cookies, přesměrování a POST; těla odpovědí ani session se neukládají.
Přihlášené produkční zdravotní UAT **NOT RUN**, starší role/picker UAT zůstává samostatné.

Původní WIP `kis_rosters_admin.php` SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5` zachovaný;
žádné cizí větve, worktrees ani staré výstupy se nemažou.
Finální protokol se sdílí dokumentační větví `codex/medical-filter-release-proof-20261108`;
po jeho merge není nutné další produkční nasazení.
