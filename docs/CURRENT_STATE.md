# Aktuální stav projektu

Aktualizováno 6. 11. 2026 (Europe/Prague), po nasazení a ověření hostingového plánu členství. Další kroky vede jediný [registr práce](WORK_PLAN_CURRENT.md).

- **[Pracovní upozornění nasazená a minutový běh aktivní](verification/staff-notifications-release-20261106/RESULTS.md):** PR #330, produkční zvoneček, přesný odkaz a přečtení/nepřečtení ověřeny. Tři žádosti × šest účtů = 18 upozornění a 18 e-mailů přijatých transportem; další běhy bez duplikace či chyb. Vlastník 6. 11. [potvrdil přijetí zprávy do schránky](verification/membership-copy-wip-20261106/RESULTS.md); P01 splněné v tomto rozsahu, nejde o potvrzení všech 18 doručení. Čtecí dohled: staff-notifications-production.yml / status.
- **[Omluvenky a náhrady již nasazené a znovu ověřené](verification/makeup-audit-20261106/RESULTS.md):** 6. 11. prošlo 22 testů / 139 assertions, 23 HTTP scénářů a skutečný místní browser (omluva, čekání na docházku, rezervace a storno s vrácením nároku). Mobilní zobrazení a uložená historie PASS. Bez nové chyby či opravy, není potřeba další nasazení. Zápisy pouze na oddělených syntetických datech; přihlášené produkční UAT se nyní neprovádělo.
- **[Denní členství převedeno na hosting a ověřeno](verification/membership-scheduler-20261106/RESULTS.md):** #341 nasazeno, vlastní spouštěč v minutovém cronu aktivní; denně **05:23 UTC (06:23 CET / 07:23 CEST)**. Přirozené tiky 15:18:02, 15:20:04 a 15:21:02 UTC, poslední odstup 58 s; dnešní úspěšné zpracování 13:05:33 UTC se neopakovalo. GitHub schedule false, APPLY/MAIL i soukromá automatizace/pošta true. Časová brána UTC/DST, retry a souběh PASS v testech; příští ranní běh zatím nelze doložit. Skutečné P05/P07 a export P06 zůstávají odložené.
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
- **Poslední doložené nasazení:** `c55b1615885c9e8753bb334d95f06119022963cd`, [deploy 42240723957](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42240723957) SUCCESS, 6. 11. v 16:14 CET; [hostingové členství #341](verification/membership-scheduler-20261106/RESULTS.md). Popisky #340 i upozornění zachované; následné předání samo vydání nemění.
  Historické BAR vydání `76bf0370a06e0ab80bab5ee9af1815835ac24efc`, deploy `42084130988`: [protokol](verification/bar-release-20261105/RESULTS.md), shoda otestovaného a sloučeného stromu; členství #316 a předání #317 zachované.
- **Ověření aktuálního vydání:** všech 11 kontrol #341 PASS; Linux CI i deploy 2290 testů / 42246 assertions, MariaDB 10.3/11.4, PHPStan/styl/CodeQL PASS. Nasazené hashe workeru/služeb ověřené, skutečný minutový cron i čtecí kontroly před/po PASS. Produkční UI se touto opravou neměnilo; přihlášený Chrome #340 je předchozí důkaz.
  HTTP vydání před 71/71, po 77/77; přihlášený Chrome Finance/Správa baru/Obsluha i následný skutečný katalog PASS. Přihlášení lucky a skutečná barová platba NOT RUN.
  [Doklad aktuálního vydání](verification/membership-scheduler-20261106/release-comparison.json): přesné SHA, shodný otestovaný/sloučený strom a hash konfigurace/integrační flags před/po; import/UAT false. Čtecí audity 42240286470/42241933037 PASS. [Předchozí popisky #340](verification/membership-copy-release-20261106/RESULTS.md) zůstávají zachované.
- **Záloha a schéma:** [úplná izolovaná obnova 42080871410](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42080871410) PASS: 357 tabulek / 2 triggery a 4341 existujících souborů včetně konfigurace a příloh.
  [Aktuální DB záloha](verification/membership-scheduler-20261106/deploy-summary.json): 387 tabulek / 2 triggery již před aktivací, hash ověřen; obnova této nové zálohy NOT RUN. Tři předchozí generace obnoveny PASS v běhu 42224275796, [důkaz](verification/autonomous-p01-p14-20261106/restore-evidence.txt). Schéma current / pending [], 8 dosavadních VS zachovaných. [Předchozí obnova 42085710492](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42085710492) PASS.
- **DB kapacita obnovena:** po úplné externí záloze a skutečné obnově Search
  byl autorizovaně vyprázdněn pouze odvozený index Velocoty; +3,35 GB,
  31. 10. v 09:34 CET přibližně 4,64 GB volných na systémovém oddílu. Diskové temporary tables fungují. Příčina kolísání kapacity není definitivně určena. Globální hledání Velocoty a jeho cron zůstávají dočasně vypnuté;
  návrat vyžaduje trvalou kapacitu nebo ověřený úspornější index.
  [Předání Velocoty](https://github.com/velocota/velocota-docs/pull/93).
- **Konfigurace při vydání #341:** Fio import/auto-confirm a SumUp true; Stripe/Velocota false, Packeta nedefinovaná; kontrolované flags i hash configu před/po shodné. Znovu ověřen pouze hostingový plán členství; ostatní crony se nepřeinstalovaly.
  Dva skutečné importy PASS; redundantní GitHub plán vypnutý repozitářovou proměnnou, ruční spuštění zachované.
  Stripe/Velocota false, Packeta nedefinovaná, KIS canonical writes false; UAT/import false.
- **Otevřené provozní převzetí:** lidská/účetní přejímka D12–D14, skutečná
  inventura/ocenění, vazby a trenérské rozsahy, dohody/částky a dvě přílohy.
  [Rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md).
  Technické dokončení není odstranění všech legacy nálezů ani schválení UAT.

[Převzetí](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)
