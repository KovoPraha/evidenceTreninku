# Aktuální stav projektu

Aktualizováno 6. 11. 2026 (Europe/Prague), po skutečném převodu katalogu BAR a schválených přístupů; dřívější nasazení a aktivace zachované.

- **Pracovní upozornění připravená, zatím nenasazená:** [PR #330](https://github.com/KovoPraha/evidenceTreninku/pull/330) přidává zvoneček, osobní přehled a e-mail pro registráře při žádosti o propojení či registraci sportovce. Přečtení není vyřízení. [Rozsah, přejímka a postup](STAFF_NOTIFICATIONS_2026-11-06.md). Aktuální automatické kontroly jsou u PR; před uvedením do provozu ověřit plánování obecného workeru, příjemce a skutečné doručení. První běh zahrne i starší čekající žádosti. Produkční plánovač ani rozesílání nebyly touto prací změněny.
- **[Omluvenky a náhrady již nasazené a znovu ověřené](verification/makeup-audit-20261106/RESULTS.md):** 6. 11. prošlo 22 testů / 139 assertions, 23 HTTP scénářů a skutečný místní browser (omluva, čekání na docházku, rezervace a storno s vrácením nároku). Mobilní zobrazení a uložená historie PASS. Bez nové chyby či opravy, není potřeba další nasazení. Zápisy pouze na oddělených syntetických datech; přihlášené produkční UAT se nyní neprovádělo.
- **[Pravidelné zpracování členství i pošta zapnuté](MEMBERSHIP_MAIL_ACTIVATION_2026-11-05.md):** automatizace, plán/zápis a pošta aktivní, denně 05:23 UTC. Ostrý apply --send `42108711699` PASS (0 členství/předpisů/dokladů/zpráv, žádná chyba), kontrola `42108836694` potvrdila úspěšný heartbeat v 21:01:18 UTC. Skutečné doručení při prázdné frontě neověřené. Závodní tým zůstává konceptem.
- **[Klubové tarify #316 nasazené](verification/membership-catalog-release-20261105/RESULTS.md):** více disciplín za jednu cenu, měsíční/roční interval a formulář fakturačních údajů.
  Produkční **koncept Závodní tým / 2200 Kč měsíčně**, varianta 951, kategorie Členství v klubu; skupina 86 → MTB 87 / Silnice 88 / Dráha 89. Přihlášený zápis a kontrola UI PASS.
  **Prodej zatím neaktivovaný:** chybí skutečné údaje a schválení vystavovatele, tarifu a publikace.
  Linux 2064/39368, obě MariaDB matice a všech 11 kontrol PASS. Live `ad09174e`, deploy `42076138376` SUCCESS. Stávající platby/VS a Fio/SumUp zachované; nová automatizace i pošta následně zapnuty podle záznamu výše.

- **BAR → KIS: katalog, přístupy i doména převedené:** [Protokol B7](verification/bar-cutover-20261106/RESULTS.md), import `42128255522` a nezávislý status `42128603361` PASS. Přesně 68 produktů / 5 kategorií / 51 fotografií, při převodu zásoby a historie nula. Pouze lucka jako obsluha s původním heslem; jediný schválený správce má explicitní vlastní spotřebu. Úplná obnova KIS `42126301391` a 500 původních BAR souborů `42130849719` PASS. Přepnutí `42131182982` a status `42131378682`: 44 HTTP kontrol; aliasy 14/14 i přihlášený Chrome PASS. Pět původních produkčních workflow pozastaveno. [Technická provozní přejímka](verification/bar-acceptance-20261106/RESULTS.md) v prohlížeči nad syntetickou DB PASS: prodeje, čekající platby, kelímky, storna, uzávěrka −5 Kč a vlastní spotřeba podle grantu. Produkční nabídka **1 / Bar KOVO – základní nabídka**, 45 produktů, fotografie, je uložená jako **neveřejný návrh**; veřejná obrazovka stále čeká. Skutečný login Lucky, první zásoby/kasa, lidská finanční přejímka, publikace a účtování BAR zbývají.

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
  Audit 4. 11. potvrdil 30 aktivních pracovních účtů s pozicí trenéra, 29 bez rozsahu.
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
- **Živé vydání:** `76bf0370a06e0ab80bab5ee9af1815835ac24efc`, [deploy 42084130988](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42084130988) SUCCESS.
  [BAR protokol](verification/bar-release-20261105/RESULTS.md): otestovaný a sloučený strom shodný; předchozí členství #316 a předání #317 zachované.
- **Ověření:** všech 11 kontrol PR, CI main i deploy PASS; Linux 2203/41286 bez skipů, samostatné unixové hranice 3/45, MariaDB 10.3/11.4, PHPStan/styl/JS PASS.
  HTTP vydání před 71/71, po 77/77; přihlášený Chrome Finance/Správa baru/Obsluha i následný skutečný katalog PASS. Přihlášení lucky a skutečná barová platba NOT RUN.
  [Čtecí porovnání](verification/bar-release-20261105/release-comparison.json): živé SHA a shodná konfigurace/flags v okamžiku BAR vydání; pozdější samostatná aktivace členství viz výše. Historický manifest není úplnou inventurou BAR.
- **Záloha a schéma:** [úplná izolovaná obnova 42080871410](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42080871410) PASS: 357 tabulek / 2 triggery a 4341 existujících souborů včetně konfigurace a příloh.
  [Nejnovější DB záloha](verification/bar-release-20261105/deploy-database.json) vznikla před migracemi; [obnova 42085710492](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42085710492) PASS. Schema current / pending [], ledger 159 a VS zachované.
- **DB kapacita obnovena:** po úplné externí záloze a skutečné obnově Search
  byl autorizovaně vyprázdněn pouze odvozený index Velocoty; +3,35 GB,
  31. 10. v 09:34 CET přibližně 4,64 GB volných na systémovém oddílu. Diskové temporary tables fungují. Příčina kolísání kapacity není definitivně určena. Globální hledání Velocoty a jeho cron zůstávají dočasně vypnuté;
  návrat vyžaduje trvalou kapacitu nebo ověřený úspornější index.
  [Předání Velocoty](https://github.com/velocota/velocota-docs/pull/93).
- **Konfigurace a flags:** Fio import/auto-confirm a SumUp true; vlastní hostingový cron s minimálním odstupem 600 s.
  Dva skutečné importy PASS; redundantní GitHub plán vypnutý repozitářovou proměnnou, ruční spuštění zachované.
  Stripe/Velocota false, Packeta nedefinovaná, KIS canonical writes false; UAT/import false.
- **Otevřené provozní převzetí:** lidská/účetní přejímka D12–D14, skutečná
  inventura/ocenění, vazby a trenérské rozsahy, dohody/částky a dvě přílohy.
  [Rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md).
  Technické dokončení není odstranění všech legacy nálezů ani schválení UAT.

[Převzetí](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)
