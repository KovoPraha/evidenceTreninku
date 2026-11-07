# Aktuální stav projektu

Aktualizováno 7. 11. 2026 (Europe/Prague). [Registr práce](WORK_PLAN_CURRENT.md) a [úplný katalog](PLAN_CATALOG.md).
- **P11 katalog a lekce NASAZENO:** release `269684c234698cb40ae661e4080e318d4a05cfd3`, deploy 42374036053 SUCCESS; [protokol](verification/attendance-lesson-release-20261107/RESULTS.md).
  #367 / dávkové lekce navazují na #364; klubové ceny #358 zahrnuté. Záloha, migrace, VS a HTTP smoke PASS; import/UAT false. [Plán R06](ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md).
- **P07 N1/N2 NASAZENO / ZAPNUTO:** [protokol](verification/shop-document-automation-20261107/RESULTS.md), #355 / `9460ee76`, deploy 42362258878 SUCCESS. 2337 testů / 43257 assertions, 11 CI PASS; migrace current, původní VS beze změny. Pověření 1 / vystavovatel TJ KOVO 1 aktivované během 42362688999. Import/UAT flag false; záloha 387 tabulek / 2 triggery ověřená. Status 42362771928 PASS: přirozený tick 10:13:03 UTC, fronta/chyby 0. Skutečné doručení nového PDF do schránky neověřené.
- **ROLE01-MOB rozpracováno:** [mobilní karty / plán](ACCESS_OVERVIEW_2026-11-07.md), karty #361 zahrnuje vydání katalogu `c70939d4`; cílený nový čtecí Chrome zbývá. **ROLE01 NASAZENO A OVĚŘENO:** [Lidé a přístupy](ACCESS_OVERVIEW_2026-11-07.md), release `ebb8b2e3`, [deploy 42360171293 a přejímka 42360515313](verification/access-release-20261107/RESULTS.md) PASS. 2325 testů / 42966 assertions, skutečné formuláře 16/16, HTTP 7/7. Všech 46 původních účtů a 82 rolí beze změny; oba nové testovací účty deaktivované a bez oprávnění. Záloha 387 tabulek / 2 triggery ověřená, import false, migrace current / pending [].
- **[Pracovní upozornění nasazená a minutový běh aktivní](verification/staff-notifications-release-20261106/RESULTS.md):** PR #330, produkční zvoneček, přesný odkaz a přečtení/nepřečtení ověřeny. Tři žádosti × šest účtů = 18 upozornění a 18 e-mailů přijatých transportem; další běhy bez duplikace či chyb. Vlastník 6. 11. [potvrdil přijetí zprávy do schránky](verification/membership-copy-wip-20261106/RESULTS.md); P01 splněné v tomto rozsahu, nejde o potvrzení všech 18 doručení. Čtecí dohled: staff-notifications-production.yml / status.
- **[Omluvenky a náhrady již nasazené a znovu ověřené](verification/makeup-audit-20261106/RESULTS.md):** 6. 11. prošlo 22 testů / 139 assertions, 23 HTTP scénářů a skutečný místní browser (omluva, čekání na docházku, rezervace a storno s vrácením nároku). Mobilní zobrazení a uložená historie PASS. Bez nové chyby či opravy, není potřeba další nasazení. Zápisy pouze na oddělených syntetických datech; přihlášené produkční UAT se nyní neprovádělo.
- **[Členství a ranní kontrola bez PC](verification/membership-morning-server-release-20261106/RESULTS.md):** denně **05:23 UTC (06:23 CET / 07:23 CEST)**, hlavní hostingový plán #341 zachovaný. Pozorovatel #346 je instalovaný na 7. 11. od 05:30 UTC; přirozený čekající tick a celý serverový zápis/CI/merge ověřené. Místní úloha PAUSED, GitHub plán účtování false, soukromá automatizace/pošta true. Časová brána a chyby prošly testy; Ranní serverový audit 7. 11.: [PASS](verification/membership-scheduler-20261107/RESULTS.md). Skutečné P05/P07 a export P06 dále odložené.
- **[Klubové tarify #316 nasazené](verification/membership-catalog-release-20261105/RESULTS.md):** více disciplín za jednu cenu, měsíční/roční interval a formulář fakturačních údajů.
  **Závodní tým schválený a zveřejněný za 2200 Kč měsíčně:** produkt 297 / varianta 951, kategorie Členství v klubu, skupina 86 → MTB 87 / Silnice 88 / Dráha 89. [Produkční kontrola 6. 11.](verification/racing-team-status-20261106/RESULTS.md) potvrdila aktivní tarif a funkční odkaz ke sjednání.
  Fakturační údaje TJ KOVO jsou uložené. Sjednání vyžaduje klubem ověřenou vazbu zákazníka na sportovce; použitý účet ji nemá. Skutečné dokončení závazku, platba a PDF pro tento produkt nebyly tímto čtecím průchodem ověřeny.
  Historické vydání #316 `ad09174e`, deploy `42076138376` SUCCESS; Linux 2064/39368, obě MariaDB matice a 11 kontrol PASS. Platby/VS a Fio/SumUp zachované; automatizace a pošta zapnuty následně. [Oprava členských popisků #340 nasazena a ověřena](verification/membership-copy-release-20261106/RESULTS.md): katalog/detail členství správně mluví o tarifu; cena i sjednání zachované.
- **BAR → KIS: katalog, přístupy i doména převedené:** [Protokol B7](verification/bar-cutover-20261106/RESULTS.md), import `42128255522` a nezávislý status `42128603361` PASS. Přesně 68 produktů / 5 kategorií / 51 fotografií, při převodu zásoby a historie nula. Pouze lucka jako obsluha s původním heslem; jediný schválený správce má explicitní vlastní spotřebu. Úplná obnova KIS `42126301391` a 500 původních BAR souborů `42130849719` PASS. Přepnutí `42131182982` a status `42131378682`: 44 HTTP kontrol; aliasy 14/14 i přihlášený Chrome PASS. Pět původních produkčních workflow pozastaveno. [Technická provozní přejímka](verification/bar-acceptance-20261106/RESULTS.md) v prohlížeči nad syntetickou DB PASS: prodeje, čekající platby, kelímky, storna, uzávěrka −5 Kč a vlastní spotřeba podle grantu. **[Nabídka 1 / Bar KOVO – základní nabídka zveřejněná, vydání 1](verification/bar-menu-publication-20261106/RESULTS.md)**: 45 produktů a 45 veřejných fotografií, anonymní HTTP i staré menu → KIS PASS. Publikace neotevřela kasu ani nezaložila zásoby. Skutečný login Lucky, první zásoby/kasa, lidská finanční přejímka a účtování BAR zbývají podle [session handoffu](SESSION_HANDOFF_BAR_2026-11-06.md).
- **[Ostré Fio a SumUp zapnuté](verification/live-payments-20261105/ACTIVATION.md):** karta pro 223 aktivních produktů / nové objednávky, 8 starých plateb zachováno.
  **[Fio hostingový plán ověřený](verification/fio-followup-20261105/RESULTS.md):** dva skutečné importy, všech 45 pohybů bez shodného VS. Žádné vynucené přiřazení; skutečná placená úhrada stále nedoložená.
- **[PR #305 sloučený a nasazený](verification/functional-round10-release-20261105/RESULTS.md):** vratky zachovávají objednávku a stránkují úplnou historii. Linux i deploy 2009/38764, HTTP před/po 48/48 a izolovaná obnova PASS. Přihlášený čtecí Chrome PASS v dostupném rozsahu; heslo/T07 vynechané.

- **[Osmý funkční průchod](verification/functional-uat-round8-20261104/RESULTS.md):** příprava období, hierarchie ke dni lekce, věkové návrhy a stránkování. Skutečný místní browser, 25 kontrol HTTP/model/persistence a cílené 21/190 PASS; všech 12 doménových snapshotů shodných. Bez nové chyby runtime.

- **[Pátý](verification/functional-uat-round5-20261104/RESULTS.md) a [šestý průchod](verification/functional-uat-round6-20261104/RESULTS.md):** 73 HTTP kontrol souhlasů, papírových podkladů a vyřízení osob; následný skutečný Chrome a 16 kontrol persistence PASS. Bez další chyby runtime; výsledky jsou v nasazeném PR #295, heslo/T07 vynechané.

- **[Třetí funkční vlna sloučena a nasazena](verification/functional-round3-release-20261104/RESULTS.md):** opravené náhledy členství a české časové stavy rodinného přehledu. Přihlášený produkční náhled PASS bez potvrzení změny; rodinná stránka načtena, účet nemá propojené sportovce pro ověření štítků. Místní matice 119/119 PASS; úplné UAT všech rolí není uzavřené. Heslo/T07 nadále odložené.

- **Evidence členů bez sezon: aplikace nasazena a schválená data převedena.**
  [Převod 41542954307](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41542954307)
  potvrdil 435 výchozích zařazení od **2. 11. 2026**, odstranění 560 historických
  intervalů a 560 importních událostí. Osoby, doklady a dosavadní trenérské rozsahy zachované.
  [Schválená mapa](MEMBER_EVIDENCE_APPROVED_MAP_2026-11-02.md) je živá: sedm provozních
  skupin / 41 soupisek a TEST / 3 soupisky. Rexonix závodní, KDC a Bikros samostatně `other`.
  Čtecí inventura 41545932556 potvrdila počátky všech 435 intervalů i neaktivní technickou identitu bez oprávnění.
  Běh skončil červeně až na HTTP timeoutu runneru po commitu; následné nezávislé
  HTTP 40/40 a čtecí inventura všech 44 soupisek / 8 skupin PASS.
  [Důkazy a meze](verification/member-evidence-cutover-20261102/RESULTS.md).
  Jedna poslední synchronizace čeká na nový export, počáteční den se neposouvá.
  Přihlášené UAT a E8 nejsou dokončené; převodní mapy a bod návratu zůstávají zachované.

- [Druhá funkční vlna nasazena](verification/functional-round2-release-20261102/RESULTS.md): oprava přípravy localhost dema, [35 syntetických PASS](verification/functional-uat-round2-20261102/RESULTS.md). Seed se v produkci nespouštěl.

- **Trenérská obnova hesla nasazena:** samostatný režim obnovy, šifrovaná fronta,
  odvolání relací a výhradně trenérský worker. [Příprava pilotu](TRAINER_READINESS_2026-10-31.md).
  [Oddělený syntetický pilot](verification/trainer-pilot-20261101/RESULTS.md) je založen:
  trenér 56, soupiska 77, dvě syntetické osoby, třetí mimo rozsah. Grant končí
  15. 11. 2026 v 18:17:31 databázového času; obnova hesla způsobilá.
  Audit 4. 11. zaznamenal 30 aktivních trenérských účtů, 29 bez rozsahu; po nasazeném #314 to neblokuje zápis ani zastupování. Soukromé profily mají samostatná práva.
  Historické počty způsobilosti k obnově určuje datovaná příprava pilotu.
  [Provozní auditní identita](TRAINER_PILOT_OPERATIONAL_2026-11-01.md) je neaktivní a bez oprávnění;
  veřejné účty se nepropojovaly ani nepovyšovaly. Úplné lidské UAT ještě neproběhlo.
  Původní testovací e-mail transport přijal a příjemce 31. 10.
  [potvrdil doručení](verification/trainer-recovery-20261031/mailbox-confirmation.json).
  [Autonomní ověření 1. 11. 23:42](verification/trainer-recovery-autonomous-20261101-2341/RESULTS.md)
  potvrdilo doručení nové žádosti ve webmailu, platný odkaz i anonymní/mobilní formulář;
  produkční heslo nezměněno, skutečné spotřebování a T07 nadále nejsou ověřeny.
  Hostingový minutový cron znovu ověřen
  [1. 11. v 17:22:03 UTC](verification/trainer-pilot-20261101/trainer-readiness.json). Tři staré klubové zprávy nedotčeny.

- **W6–W15 implementovány, sloučeny, nasazeny a technicky ověřeny.**
  [Rozsah a omezení přejímky](REFACTORING_CLOSURE_2026-10-30.md).
- **Předchozí doložené nasazení #346:** acb511cc5338911f141bc49334d515c0b21034fa, [deploy 42265034726](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42265034726) SUCCESS; [pozorovatel #346](verification/membership-morning-server-release-20261106/RESULTS.md). GitHub předání #350 aktivované sloučením, bez dalšího deploye webu; následná dokumentace toto vydání nemění.
  Historické BAR vydání `76bf0370a06e0ab80bab5ee9af1815835ac24efc`, deploy `42084130988`: [protokol](verification/bar-release-20261105/RESULTS.md), shoda otestovaného a sloučeného stromu; členství #316 a předání #317 zachované.
- **Ověření předchozího vydání #346:** všech 11 kontrol #346 i #350 PASS; Linux/deploy 2300 testů / 42293 assertions, Python publikace 5 testů, MariaDB 10.3/11.4, PHPStan/styl/CodeQL PASS. Hostingový pozorovatel přirozeně čeká a server sám sloučil preflight #348 po skutečných PR CI kontrolách. Přihlášené UI UAT se tímto CLI vydáním nedělalo.
  HTTP vydání před 71/71, po 77/77; přihlášený Chrome Finance/Správa baru/Obsluha i následný skutečný katalog PASS. Přihlášení lucky a skutečná barová platba NOT RUN.
  [Doklad předchozího vydání #346](verification/membership-morning-server-release-20261106/release-comparison.json): přesné SHA a úspěšný deploy, shodná konfigurace/integrační flags před/po, import/UAT false. Čtecí audity 42264116168/42266770816 PASS. [Předchozí členství #341](verification/membership-scheduler-20261106/RESULTS.md) a popisky #340 zachované.
- **Záloha a schéma:** [úplná izolovaná obnova 42080871410](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42080871410) PASS: 357 tabulek / 2 triggery a 4341 existujících souborů včetně konfigurace a příloh.
  [Předchozí DB záloha #346](verification/membership-morning-server-release-20261106/deploy-summary.json): 387 tabulek / 2 triggery, hash ověřen před aktivací; obnova této generace NOT RUN. Tři předchozí generace obnoveny PASS v běhu 42224275796, [důkaz](verification/autonomous-p01-p14-20261106/restore-evidence.txt). Schéma current / pending [], 8 dosavadních VS zachovaných.
- **DB kapacita obnovena:** po úplné externí záloze a skutečné obnově Search
  byl autorizovaně vyprázdněn pouze odvozený index Velocoty; +3,35 GB,
  31. 10. v 09:34 CET přibližně 4,64 GB volných na systémovém oddílu. Diskové temporary tables fungují. Příčina kolísání kapacity není definitivně určena. Globální hledání Velocoty a jeho cron zůstávají dočasně vypnuté;
  návrat vyžaduje trvalou kapacitu nebo ověřený úspornější index.
  [Předání Velocoty](https://github.com/velocota/velocota-docs/pull/93).
- **Konfigurace při vydání #346:** Fio import/auto-confirm a SumUp true; Stripe/Velocota false, Packeta nedefinovaná. Hash configu a kontrolované flags před/po shodné; změněna jen samostatná serverová kontrola. Hlavní čas účtování a ostatní crony se nepřeinstalovaly.
  Dva skutečné importy PASS; redundantní GitHub plán vypnutý repozitářovou proměnnou, ruční spuštění zachované.
  Stripe/Velocota false, Packeta nedefinovaná, KIS canonical writes false; UAT/import false.
- **Otevřené provozní převzetí:** lidská/účetní přejímka D12–D14, skutečná
  inventura/ocenění, vazby a trenérské rozsahy, dohody/částky a dvě přílohy.
  [Rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md).
  Technické dokončení není odstranění všech legacy nálezů ani schválení UAT.
AUTO01: [výsledky priorit 1/7/8](AUTONOMOUS_PRIORITIES_1_7_8_2026-11-07.md): novější záloha 389 tabulek / 2 triggery PASS v izolaci; inventura 332 větví / 156 worktrees, WIP zachované.
[Převzetí](HANDOFF_CURRENT.md) · [Deník](EVENT_LOG.md) · [Rozhodnutí / uzavřené plány](DECISION_LOG.md) · [Historie provozu](CHANGELOG-PROVOZ.md) · [Pravidla](WORK_DOCUMENTATION_POLICY.md)
