# Deník událostí projektu

Průběžná evidence významných změn stavu, plánů, implementací, ověření a předání.
Časy/dny jsou Europe/Prague, pokud zdroj neuvádí UTC. Nové záznamy přidávej
nahoru s jedinečným ID `EYYYY-MM-DD-NN`, vazbou na práci a odkazy.
Nezapisuj každý příkaz; zachyť co se změnilo, výsledek/meze a další krok.
Aktuální úkoly jsou v [registru](WORK_PLAN_CURRENT.md), důvody voleb a rejstřík
uzavřených prací v [rozhodovacím logu](DECISION_LOG.md).

| Datum / ID | Práce a událost | Výsledek / následující krok | Trvalé podklady |
| --- | --- | --- | --- |
| 7. 11. 2026 / E2026-11-07-13 | P11 katalog: zahájena navazující optimalizace | Plán před implementací, vlastní worktree, parita a měření před/po, bez deploye. | [Plán](CATALOG_READ_PERFORMANCE_2026-11-07.md), [předání](SESSION_HANDOFF_CATALOG_READ_PERFORMANCE_2026-11-07.md) |
| 7. 11. 2026 / E2026-11-07-08 | P07 N1/N2: doklady a e-maily implementované na výslovné zadání vlastníka | Místní celý tok, 2337 testů a obě integrační MariaDB PASS. Srovnávací prostředí doplněno o stejnou migraci; finální CI/merge/deploy/aktivace následují. | [Plán](SHOP_DOCUMENT_AUTOMATION_2026-11-07.md), [důkazy](verification/shop-document-automation-20261107/RESULTS.md), [PR #355](https://github.com/KovoPraha/evidenceTreninku/pull/355) |
| 7. 11. 2026 / E2026-11-07-07 | ROLE01: nasazení a technická produkční přejímka HOTOVO | Deploy 42360171293 SUCCESS / `ebb8b2e3`, záloha 387 tabulek / 2 triggery. Drill 42360515313: 16/16, oba nové testovací účty deaktivované a bez grantů. Původních 46 účtů / 82 přiřazení / 5 superadminů / 1 barový grant přesně shodných před/po. Žádný další deploy není potřeba. | [Výsledek a meze](verification/access-release-20261107/RESULTS.md), [plán](ACCESS_OVERVIEW_2026-11-07.md), [rozhodnutí](DECISION_LOG.md#d2026-11-07-02) |
| 7. 11. 2026 / E2026-11-07-06 | ROLE01: #356 sloučené, čtecí baseline hotový, deploy spuštěný | 11 CI PASS, původních 46 účtů / 82 pozic / 5 superadminů / 1 barový grant. Deploy 42360171293 běží; dále formuláře na nových technických účtech a shoda otisků. | [Produkční protokol](verification/access-release-20261107/RESULTS.md), [plán](ACCESS_OVERVIEW_2026-11-07.md) |
| 7. 11. 2026 / E2026-11-07-05 | ROLE01: společný přehled rolí a schválené produkční ověření | Implementace a CI hlavy `380c0086` PASS: 2325 testů / 42966 assertions, obě MariaDB, PHPStan/styl/CodeQL. Souběžný #357 převzatý bez změny aplikačního kódu; znovu CI, pak baseline/deploy/HTTPS test a úklid. Produkce zatím beze změny. | [Plán](ACCESS_OVERVIEW_2026-11-07.md), [PR #356](https://github.com/KovoPraha/evidenceTreninku/pull/356), [rozhodnutí](DECISION_LOG.md#d2026-11-07-02) |
| 7. 11. 2026 / E2026-11-07-04 | DOC01: vlastník zpřesnil povinnost souborových plánů, konceptů, rozhodnutí a stručného předání | Obsah a místní ověření HOTOVO; handoff 70 řádků, session cesta 15, historie zachovaná. [Předání #357](https://github.com/KovoPraha/evidenceTreninku/pull/357), stav CI/merge u PR; produkční stav beze změny | [Plán a audit](DOCUMENTATION_GOVERNANCE_2026-11-07.md), [rozhodnutí](DECISION_LOG.md#d2026-11-07-01) |
| 7. 11. 2026 / E2026-11-07-03 | P07-UAT: otevřený návrh přejímky jednorázového kroužku | PR #355 dosud nesloučené v kontrolovaném main; syntetické důkazy a mezery automatizace posoudit při integraci | [PR #355](https://github.com/KovoPraha/evidenceTreninku/pull/355), [registr](WORK_PLAN_CURRENT.md) |
| 7. 11. 2026 / E2026-11-07-02 | P11 R06: dávkové ověření účastníků docházky začleněné | #354 je v kontrolovaném main `feb8fe8c`; 9 párů/parita 10.3/11.4 doložené. Bez deploye; dále plné HTTP/role/propojené lekce | [Předání](SESSION_HANDOFF_ATTENDANCE_READ_PERFORMANCE_2026-11-07.md), [důkazy](verification/attendance-read-performance-20261107/RESULTS.md) |
| 6. 11. 2026 / E2026-11-07-01 | P11 R13 a R05: optimalizace zdravotního přehledu a plánovače | #352/#353 v kontrolovaném main. Testy/parita doložené, neznamená to nové nasazení | [R13](SESSION_HANDOFF_MEDICAL_READ_PERFORMANCE_2026-11-06.md), [R05](SESSION_HANDOFF_PLANNER_READ_PERFORMANCE_2026-11-06.md) |

## Historie před zavedením společného deníku

Výše uvedené starší události jsou zpětně indexované podle odkazovaných
podkladů, nikoli nové provedení nebo úplná rekonstrukce všech session.

- [Provozní historie a dřívější CURRENT/HANDOFF](CHANGELOG-PROVOZ.md).
- [Předání do 7. 11. 2026](HANDOFF_ARCHIVE_2026-11-07.md).
- [Dřívější rozpracovanost a session registru](WORK_PLAN_ARCHIVE_2026-11-07.md).
- [Úplná historie starého session handoffu](plan-eshop-tymova-evidence/SESSION_HISTORY_2026-11-07.md).
- [Sjednocení #333/#334](verification/documentation-reconcile-20261106/RESULTS.md).

Uzavřený plán musí zůstat dosažitelný odtud a z [rozhodovacího rozcestníku](DECISION_LOG.md).
Záznam události není sám o sobě souhlasem s provozní operací.
