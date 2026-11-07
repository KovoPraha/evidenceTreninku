# Deník událostí projektu

Průběžná evidence významných změn stavu, plánů, implementací, ověření a předání.
Časy/dny jsou Europe/Prague, pokud zdroj neuvádí UTC. Nové záznamy přidávej
nahoru s jedinečným ID `EYYYY-MM-DD-NN`, vazbou na práci a odkazy.
Nezapisuj každý příkaz; zachyť co se změnilo, výsledek/meze a další krok.
Aktuální úkoly jsou v [registru](WORK_PLAN_CURRENT.md), důvody voleb a rejstřík
uzavřených prací v [rozhodovacím logu](DECISION_LOG.md).

| Datum / ID | Práce a událost | Výsledek / následující krok | Trvalé podklady |
| --- | --- | --- | --- |
| 7. 11. 2026 / E2026-11-07-30 | P11 picker #366: nové zadání dokončení a nasazení | Vlastník schválil commit/merge/deploy po paritě a měření. Izolované pokračování, převzaté #369 a d62c3ad2; nový celý HTTP/browser pár s #367, původní WIP zachovaný. | [Plán](ATTENDANCE_PERSON_PICKER_2026-11-07.md), [předání](SESSION_HANDOFF_ATTENDANCE_PERSON_PICKER_2026-11-07.md), [volba](DECISION_LOG.md#d2026-11-07-10) |
| 7. 11. 2026 / E2026-11-07-29 | P11 picker: sjednocení integračního SQL rozpočtu | CI 42375011560 data/HTML 18 párů shodné; nový pevný celostránkový cap56 pro aktuální UI, stejné lekce v obou stromech. Samostatný validator budget a ostatní guardy zachované; replay18 a tři odmítací kontroly PASS. Nový nativní CI, bez změny aplikace/produkce. | [Výsledek](verification/attendance-person-picker-20261107/final/RESULTS.md), [plán](ATTENDANCE_PERSON_PICKER_2026-11-07.md) |
| 7. 11. 2026 / E2026-11-07-28 | P11 picker: oprava přenosu obou draftů a nové úplné měření | Společný formulář, 540 HTTP/78 Chrome, 36/380, 44 HTTP, JS/noJS a kanonické zápisy PASS; starý pár předběžný. Převzaté #347/#364/#367, finální CI/review #366, žádný náš deploy. | [Finální důkazy](verification/attendance-person-picker-20261107/final/RESULTS.md), [předání](SESSION_HANDOFF_ATTENDANCE_PERSON_PICKER_2026-11-07.md), [volba](DECISION_LOG.md#d2026-11-07-09) |
| 7. 11. 2026 / E2026-11-07-27 | P11 R06: předběžný dvouformulářový picker změřený, nahrazen E28 | 1,37 MB → 92 kB, Chrome trenér 5,23 → 1,11 s. 540 HTTP časů / 78 browser pozorování, JS/noJS, 36 HTTP kontrol, skutečné zápisy a revokace PASS. Runtime uklizený, WIP shodný, žádný náš deploy. Finální CI/merge u PR. | [Výsledky](verification/attendance-person-picker-20261107/RESULTS.md), [předání](SESSION_HANDOFF_ATTENDANCE_PERSON_PICKER_2026-11-07.md), [PR #366](https://github.com/KovoPraha/evidenceTreninku/pull/366) |
| 7. 11. 2026 / E2026-11-07-26 | P11 R06: vlastník zadal optimalizaci výběru osob podle #363 | Plán před kódem c0883ff2, vlastní větev/worktree, stejné kombinované fixture před/po; bez produkce a zásahu do WIP. Pracovní ID 14 bylo před integrací přesunuto kvůli přijatému P07 záznamu. | [Plán](ATTENDANCE_PERSON_PICKER_2026-11-07.md), [rozhodnutí](DECISION_LOG.md#d2026-11-07-09) |
| 7. 11. 2026 / E2026-11-07-23 | P11 R06 lekce #367 sloučené a schválené nasazení dokončené | Finální CI včetně obou MariaDB PASS, Linux 2355/46183. Veřejný normalizovaný DOM 5/5 shodný, anonymní soukromá trasa odmítnutá; přihlášené R06 UAT NOT RUN. WIP zachovaný. | [Plán](ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md), [provozní důkaz](verification/attendance-lesson-release-20261107/RESULTS.md), [předání](SESSION_HANDOFF_ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md) |
| 7. 11. 2026 / E2026-11-07-22 | DOC03: stručné priority a závislosti podle zadání vlastníka | Hotový přehled samostatných kroků, lidských podkladů a odložených prací; snapshot main 269684c2. Ranní PASS, vydaný katalog a merge lekcí zohledněné. Pořadí je doporučení, žádná nová finanční aktivace. | [Priority v registru](WORK_PLAN_CURRENT.md#priority-a-závislosti), [handoff](HANDOFF_CURRENT.md); větev `codex/work-priorities-20261107` |
| 7. 11. 2026 / E2026-11-07-21 | P11 katalog #364 NASAZENO podle nového souhlasu | `c70939d4`, deploy 42372002993 SUCCESS. Veřejná pole 5/5 shodná, 222 nabídek; původní DOM baseline neprokazatelný kvůli CSP nonce, v2 následně 5/5 stabilní. Dále R06 #367. | [Produkční protokol](verification/catalog-release-20261107/RESULTS.md), [plán](CATALOG_READ_PERFORMANCE_2026-11-07.md), [handoff](SESSION_HANDOFF_CATALOG_READ_PERFORMANCE_2026-11-07.md) |
| 7. 11. 2026 / E2026-11-07-20 | P11 R06 lekce: implementace, parita a párové měření hotové | 19/993 cíleně, první runtime 11 CI PASS; 720 párů 10.3/11.4, 153 → 6 SQL pro trenéra. Převzatý katalog #364; po integraci CI a vhodný samostatný deploy podle zadání vlastníka. | [Plán](ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md), [důkazy](verification/attendance-lesson-read-performance-20261107/RESULTS.md), [předání](SESSION_HANDOFF_ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md), [volba](DECISION_LOG.md#d2026-11-07-08) |
| 7. 11. 2026 / E2026-11-07-19 | P11 katalog: ověřená implementace a nové zadání deploye / dalších optimalizací | #364 k dokončení CI, merge a chráněnému deployi; vlastník nyní nasazení povolil. Dále R06 dávkové lekce, parita a měření. Původní WIP zachovaný. | [Plán](CATALOG_READ_PERFORMANCE_2026-11-07.md), [výsledky](verification/catalog-read-performance-20261107/RESULTS.md), [předání](SESSION_HANDOFF_CATALOG_READ_PERFORMANCE_2026-11-07.md) |
| 7. 11. 2026 / E2026-11-07-18 | DOC02: úplný katalog plánů a opravy stavů | Inventura, záchrana 18 lokálních textových plánů/příloh a registr C01–C04; obsah a místní ověření hotové; [PR #360](https://github.com/KovoPraha/evidenceTreninku/pull/360), stav CI/merge u PR, bez provozních změn | [Audit](PLAN_CATALOG_AUDIT_2026-11-07.md), [katalog](PLAN_CATALOG.md), [výsledky](verification/plan-catalog-audit-20261107/RESULTS.md) |
| 7. 11. 2026 / E2026-11-07-17 | ROLE01-MOB: vlastník schválil mobilní karty, nasazení a nové vizuální ověření | ROZPRACOVÁNO, jen prezentační změna; žádná změna produkčních rolí | [Plán](ACCESS_OVERVIEW_2026-11-07.md), pokračuje vlastní PR #361 |
| 7. 11. 2026 / E2026-11-07-16 | ROLE01-VIS: skutečný přihlášený Chrome průchod dokončen | Desktop přehledný; mobil 390/360 px s výhradou široké tabulky. Posun a otevření čitelného editoru PASS. Role, účty ani vydání se neměnily. | [Vizuální výsledek](verification/access-visual-20261107/RESULTS.md), [plán](ACCESS_OVERVIEW_2026-11-07.md); dokumentační větev `codex/access-visual-uat-20261107` |
| 7. 11. 2026 / E2026-11-07-15 | ROLE01-VIS: vlastník požádal o skutečný vizuální desktop/mobilní průchod | ROZPRACOVÁNO, jen filtry a otevření editoru; žádné změny rolí či stavu účtů, žádný nový účet | [Plán](ACCESS_OVERVIEW_2026-11-07.md), větev `codex/access-visual-uat-20261107` |
| 7. 11. 2026 / E2026-11-07-14 | P07 N1/N2: nasazení a aktivace HOTOVO | #355 / `9460ee76`, deploy 42362258878 SUCCESS; pověření 1 zapnuté, přirozený tick 10:13:03 UTC bez chyb. Skutečná banka a externí schránka neověřené. | [Protokol](verification/shop-document-automation-20261107/RESULTS.md), [plán a provoz](SHOP_DOCUMENT_AUTOMATION_2026-11-07.md), [rozhodnutí](DECISION_LOG.md#d2026-11-07-03) |
| 7. 11. 2026 / E2026-11-07-13 | P11: celé HTTP/browser na feb8fe8c dokončené | 65 případů / 930 HTTP vzorků, 403 browser pozorování; role, filtry, revokace a nezměněná data PASS. Náš deploy neproveden. Dále R06 výběr osob a lekce; novější main nepřeměřen. | [Výsledky](verification/full-http-performance-20261107/RESULTS.md), [předání / větev a PR](SESSION_HANDOFF_FULL_HTTP_PERFORMANCE_2026-11-07.md), [rozhodnutí](DECISION_LOG.md#d2026-11-07-05) |
| 7. 11. 2026 / E2026-11-07-12 | P11 ceny: vlastník povolil sloučení #358 a návaznou optimalizaci katalogu | Před merge převzatý nový produkční handoff #359; cenový kód nezměněný. Po CI sloučit bez deploye, katalog v samostatné větvi. | [Plán cen](MEMBER_PRICING_READ_PERFORMANCE_2026-11-07.md), [PR #358](https://github.com/KovoPraha/evidenceTreninku/pull/358) |
| 7. 11. 2026 / E2026-11-07-11 | P11 ceny: implementace, parita a měření dokončené; převzatý společný dokumentační rámec #357 | 500 produktů 2000 → 4 SQL, 557/377 → 3,21/2,88 ms cenové části na 10.3/11.4; 20/301 cíleně, měřený commit 11 CI PASS. PR #358 otevřený, bez merge/deploye. Místní služby zastavené; automatická kontrola odmítla rekurzivní úklid, adresáře ponechané. | [Výsledky](verification/member-pricing-read-performance-20261107/RESULTS.md), [předání](SESSION_HANDOFF_MEMBER_PRICING_READ_PERFORMANCE_2026-11-07.md), [rozhodnutí](DECISION_LOG.md#d2026-11-07-04) |
| 7. 11. 2026 / E2026-11-07-10 | P11 ceny: oddělený reader zapojený do katalogu/detailu | Commit `0890e496`; kanonický helper a checkout beze změny, původní SQL v transakci. Cílené testy a párové CI, [PR #358](https://github.com/KovoPraha/evidenceTreninku/pull/358). | [Plán](MEMBER_PRICING_READ_PERFORMANCE_2026-11-07.md), [důkazy](verification/member-pricing-read-performance-20261107/RESULTS.md) |
| 7. 11. 2026 / E2026-11-07-09 | P11 ceny: vlastník zadal optimalizaci podle výkonové analýzy se zachováním WIP | Plán uložený před předáním kódu v commitu `911de8e1`; vlastní worktree, stejná data před/po, bez produkčních změn. | [Plán](MEMBER_PRICING_READ_PERFORMANCE_2026-11-07.md), [analýza](verification/performance-analysis-20261107/RESULTS.md), [rozhodnutí](DECISION_LOG.md#d2026-11-07-04) |
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

Pracovní události cen ve starších commitech PR #358 měly ID 06/07/08.
Při převzetí již sloučeného ROLE01 #359 jsou kolidující pracovní ID zavedena
ve společném deníku jako 08/09/10; původní commitové doklady zůstávají zachované.

Při převzetí #355 je přijaté P07 ID 08 a rozhodnutí 03 zachováno; pracovní cenové události jsou nyní 09–12 a rozhodnutí 04.

Při převzetí #358 jsou pracovní ROLE01-VIS/MOB události z PR #361 přiděleny jako 13–15; přijatá cenová ID 09–12 zachována.

Po převzetí #363 jsou pracovní ROLE01-VIS/MOB události 14–16; přijaté P11 ID 13 zachováno. Vlastník následně výslovně schválil společné nasazení karet i cen #358.

Po převzetí #362 jsou pracovní ROLE01-VIS/MOB události 15–17; přijaté P07 ID 14 zachováno.

Pracovní katalogová událost dříve 18 je po přijetí DOC02 #360 zachycena jako 19; přijaté DOC02 ID 18 zachované.

Při převzetí priorit #368 zůstává přijaté DOC03 ID 22 zachované; pracovní release událost této větve nyní nese 23. Původní commitové doklady zůstávají v Gitu.

Při převzetí #369 přijaté E23 patří produkčnímu dokladu lekcí. Naše pracovní E23/E24/E25 jsou nyní E27/E28/E29; vlastní E26 a D09 zůstávají. Původní commity se nemění.

Při převzetí d62c3ad2 zachované jeho E26–29 a kanonické D09 pickeru, nové pokračování je E30 / D10; pracovní čísla v 75801847 nejsou novými souběžnými událostmi.
