# Aktuální stav projektu

Aktualizováno 4. 11. 2026 (Europe/Prague), po vydání PR #293 a pátém funkčním průchodu.

- **[Pátý funkční průchod](verification/functional-uat-round5-20261104/RESULTS.md):** 73 HTTP kontrol souhlasů, papírových podkladů a vyřízení osob PASS; cílená sada 35/288 a souběžné zveřejnění v MariaDB PASS. Bez nové potvrzené chyby runtime; browser této vlny kvůli timeoutům NOT RUN. PR #295 zůstává nesloučený/nenasazený, heslo/T07 odložené.

- **[Čtvrtý funkční průchod](verification/functional-uat-round4-20261104/RESULTS.md):** 70 HTTP kontrol zdravotních podkladů, docházky a náhrad PASS; browser ověřil papírový podklad a návaznost do rodiny. Opravené zachování původu papírové docházky je **nenasazené**. Místní sada 1977 testů; heslo/T07 dále odložené.

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
- **Živé vydání:** `94982c44d7b7737bddcca46d3ebb9fb4f698abef`,
  [deploy 41835865547](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41835865547) SUCCESS.
  PR #293 dodal čtyři runtime soubory; evidence bez sezon a předchozí CLI opravy jsou zahrnuté.
  [Protokol](verification/functional-round3-release-20261104/RESULTS.md),
  [strojové porovnání](verification/functional-round3-release-20261104/release-comparison.json).
  Živé SHA potvrdil čtecí audit 41836644728, aktivaci souborů log rsync; nejde o úplnou hash inventuru.
  Schválený datový převod zůstává zachovaný, při vydání se neopakoval.
- **Ověření:** všech 11 kontrol #293 PASS, sloučený strom shodný s ověřeným,
  Linux i deploy 1971 testů / 38392 assertions; MariaDB 10.3/11.4, profily a PHPStan/styl PASS.
  Deploy ověřil dostupnost a ochranu klíče archivu; anonymní HTTP před/po 30/30 PASS.
  Produkční náhled členství PASS; úplné přihlášené UAT, změna hesla a T07 nejsou uzavřené.
- **Záloha a schéma:** čerstvá záloha vydání, 342 tabulek / 2 triggery;
  [izolovaná obnova 41837623683](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41837623683) PASS včetně počtů řádků všech tabulek.
  SHA-256 `bd429f420f6fd0592b52f1a93ad2967b938731fa6ee881f8c5676bb1b182a553`.
  Schéma current / pending [], migrační ledger 149; dosavadní variabilní symboly zachované.
- **DB kapacita obnovena:** po úplné externí záloze a skutečné obnově Search
  byl autorizovaně vyprázdněn pouze odvozený index Velocoty; +3,35 GB,
  31. 10. v 09:34 CET přibližně 4,64 GB volných na systémovém oddílu.
  Diskové temporary tables fungují. Příčina kolísání kapacity není definitivně určena.
  Globální hledání Velocoty a jeho cron zůstávají dočasně vypnuté;
  návrat vyžaduje trvalou kapacitu nebo ověřený úspornější index.
  [Předání Velocoty](https://github.com/velocota/velocota-docs/pull/93).
- **Konfigurace a flags:** čtecí audit po nasazení potvrdil Fio import/auto-confirm,
  SumUp, Stripe a Velocota false; Packeta nedefinovaná, KIS canonical writes false.
  UAT false, import false; žádné integrace nebyly aktivovány.
- **Otevřené provozní převzetí:** lidská/účetní přejímka D12–D14, skutečná
  inventura/ocenění, vazby a trenérské rozsahy, dohody/částky a dvě přílohy.
  [Rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md).
  Technické dokončení není odstranění všech legacy nálezů ani schválení UAT.

[Převzetí](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)
