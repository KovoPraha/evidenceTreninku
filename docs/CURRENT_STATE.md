# Aktuální stav projektu

Aktualizováno 5. 11. 2026 (Europe/Prague), po nasazení klubových tarifů a přípravě Závodního týmu (PR #316).

- **[Klubové tarify #316 nasazené](verification/membership-catalog-release-20261105/RESULTS.md):** více disciplín za jednu cenu, měsíční/roční interval a formulář fakturačních údajů.
  Založen produkční **koncept Závodní tým / 2200 Kč měsíčně**, varianta 951, kategorie Členství v klubu;
  skupina 86 se soupiskami MTB 87 / Silnice 88 / Dráha 89. Přihlášený zápis a kontrola UI PASS.
  **Prodej zatím neaktivovaný:** chybí skutečné údaje a schválení vystavovatele, tarifu a publikace.
  Linux 2064/39368, obě MariaDB matice a všech 11 kontrol PASS. Live `ad09174e`, deploy `42076138376` SUCCESS.
  Stávající platby/VS a Fio/SumUp zachované, nová automatizace a pošta se nezapínaly.

- **BAR → KIS pouze ve vývojové větvi, bez nasazení:** [B6 – integrační přejímka](verification/bar-acceptance-20261105/RESULTS.md) doplňuje B0–B5 v draft PR #306. Místní role/browser, ztráta spojení, nouzová kasa, UTC/DST a naplněná obnova 335 tabulek s přílohami PASS; konečný Linux CI stav přesného headu je v PR. Skutečný katalog, fotografie a lucka zatím nepřevedené. Automatické účetní zápisy ani bankovní propojení baru nejsou aktivované. [B7](BAR_PRODUCTION_READINESS_2026-11-05.md) vyžaduje lidskou přejímku a nový výslovný souhlas; produkční přechod je nadále zakázaný.

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
- **Živé vydání:** `9b0dec083a650df933b09ecd4545d782069daa62`, [deploy 42064630068](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42064630068) SUCCESS.
  **[PR #314: trenéři i měsíční členství nasazeny](verification/monthly-coach-release-20261105/RESULTS.md).** Vlastní soupisky první, zastupování bez grantu; měsíční předpisy, VS, doklady a upomínky implementované. Jednorázový nákup zachován.
  Nové měsíční automatizace/pošta neaktivovány, plán nemá zapínací proměnné. Produkční dry-run PASS (0 členství), tarif dosud neschválen.
- **Ověření:** 11 kontrol PR PASS, shodný sloučený strom; CI main i deploy SUCCESS, Linux 2054/39237 bez skipů, MariaDB 10.3/11.4, PHPStan/styl/JS PASS.
  HTTP před 48/48, po 54/54; přihlášený trenérský výběr/data/TEST hledání a měsíční správa PASS. Ostrý zápis, platba, nové PDF a upomínka NOT RUN.
  [Čtecí porovnání](verification/monthly-coach-release-20261105/release-comparison.json) potvrdilo živé SHA, shodný hash konfigurace a dosavadní integrační flags; úplná hash inventura se netvrdí.
- **Záloha a schéma:** čerstvá záloha před migrací, 347 tabulek / 2 triggery; [izolovaná obnova 42065531972](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42065531972) PASS včetně všech počtů.
  SHA-256 `568ff63f469f70911efa757d98c82374466a85562392f9e4527cf3b427a17947`. Schéma current / pending [], ledger 151, dosavadní VS zachované.
- **DB kapacita obnovena:** po úplné externí záloze a skutečné obnově Search
  byl autorizovaně vyprázdněn pouze odvozený index Velocoty; +3,35 GB,
  31. 10. v 09:34 CET přibližně 4,64 GB volných na systémovém oddílu.
  Diskové temporary tables fungují. Příčina kolísání kapacity není definitivně určena.
  Globální hledání Velocoty a jeho cron zůstávají dočasně vypnuté;
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
