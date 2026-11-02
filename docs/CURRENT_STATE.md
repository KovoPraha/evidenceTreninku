# Aktuální stav projektu

Aktualizováno 2. 11. 2026 (Europe/Prague), po sloučení a nasazení druhé funkční vlny PR #274.

- **Evidence členů bez sezon: sloučeno, dosud nenasazeno.** PR #279–#285 přidávají
  trvalé soupisky, časové členství, datované nabídky/náhrady a nástroj převodu.
  Všech 11 kontrol #285 PASS, Linux 1 953 testů / 38 293 assertions. Vlastník potvrdil
  převzetí 435 aktivních zařazení od 2. 11. 2026 a odstranění 560 historických;
  nástroj podporuje jednu poslední synchronizaci s neměnným počátečním dnem.
  Čtecí produkční
  inventura a [nácvik na izolované záloze](verification/member-evidence-20261102/RESULTS.md)
  prošly: 44 soupisek, 435 převzatých intervalů, odstraněno 560 historických intervalů
  a 560 importních událostí; parita, opakování a obnova 337 tabulek / 2 triggerů PASS.
  [Schválená mapa D2](MEMBER_EVIDENCE_APPROVED_MAP_2026-11-02.md) je implementovaná:
  sedm provozních skupin / 41 soupisek, samostatná skupina TEST / 3 soupisky.
  [Nácvik přesné mapy 41532405191](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41532405191)
  PASS: 8 nových skupin, 44 hran, shoda skupinového členství i dosavadního trenérského pilotu.
  [Konkrétní podklady a otevřená rozhodnutí](MEMBER_EVIDENCE_PRODUCTION_TRANSITION_2026-11-02.md).
  E7/E8, cleanup a přihlášená přejímka nejsou dokončené; živé vydání níže se nemění.

- [Druhá funkční vlna nasazena](verification/functional-round2-release-20261102/RESULTS.md): oprava přípravy localhost dema, [35 syntetických PASS](verification/functional-uat-round2-20261102/RESULTS.md). Seed se v produkci nespouštěl.

- **Trenérská obnova hesla nasazena:** samostatný režim obnovy, šifrovaná fronta,
  odvolání relací a výhradně trenérský worker. [Příprava pilotu](TRAINER_READINESS_2026-10-31.md).
  [Oddělený syntetický pilot](verification/trainer-pilot-20261101/RESULTS.md) je založen:
  trenér 56, soupiska 77, dvě syntetické osoby, třetí mimo rozsah. Grant končí
  15. 11. 2026 v 18:17:31 databázového času; obnova hesla způsobilá.
  37 aktivních pracovních účtů má pozici trenéra, 36 zůstává bez rozsahu;
  29 účtů je způsobilých k obnově hesla a původních 8 zůstává blokovaných.
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
- **Živé vydání:** `51796fcafa16823f6c84208406a5c75682023409`,
  [deploy 41437064975](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41437064975) SUCCESS.
  [PR #274](https://github.com/KovoPraha/evidenceTreninku/pull/274) odděluje zboží a program v localhost seedu;
  předchozí UI opravy #272, reset #269, pilot a W6–W15 zachovány. Bez nové migrace.
  [Protokol](verification/functional-round2-release-20261102/RESULTS.md),
  [porovnání](verification/functional-round2-release-20261102/release-comparison.json).
  Živé SHA/run potvrdil čtecí audit 41437714419; rsync obou aplikačních souborů PASS.
  Nejde o úplnou inventuru hashů runtime. CLI příprava dema se v produkci nespouštěla.
- **Ověření:** všech 11 PR kontrol PASS, sloučený strom shodný s ověřeným,
  Linux 1905 testů / 38263 assertions bez skipů;
  MariaDB 10.3/11.4, objemové profily a PHPStan/styl PASS.
  Deploy zopakoval 1905/38263; HTTP před i po 26/26; browser katalog/filtry PASS.
  Webové guardy seedu a helperu 403, localhost rozcestníku 404; přihlášené UAT neopakováno.
  Změna hesla, skutečný použitý odkaz a T07 jsou dle vlastníka nyní vynechány.
- **Záloha a schéma:** nová ověřená záloha 337 tabulek / 2 triggery;
  [doklad](verification/functional-round2-release-20261102/deployment.json). Poslední doložená
  [izolovaná obnova starší generace](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41330986065)
  PASS; nová generace se v tomto vydání neobnovovala. Schéma current / pending [], ledger 143,
  dosavadní variabilní symboly beze změny.
- **DB kapacita obnovena:** po úplné externí záloze a skutečné obnově Search
  byl autorizovaně vyprázdněn pouze odvozený index Velocoty; +3,35 GB,
  31. 10. v 09:34 CET přibližně 4,64 GB volných na systémovém oddílu.
  Diskové temporary tables fungují. Příčina kolísání kapacity není definitivně určena.
  Globální hledání Velocoty a jeho cron zůstávají dočasně vypnuté;
  návrat vyžaduje trvalou kapacitu nebo ověřený úspornější index.
  [Předání Velocoty](https://github.com/velocota/velocota-docs/pull/93).
- **Konfigurace a flags:** hash konfigurace a kandidáti příloh před/po shodné; Fio import/auto-confirm,
  SumUp, Stripe a Velocota false; Packeta nedefinovaná, KIS canonical writes false.
  UAT false, import false; žádné integrace nebyly aktivovány.
- **Otevřené provozní převzetí:** lidská/účetní přejímka D12–D14, skutečná
  inventura/ocenění, vazby a trenérské rozsahy, dohody/částky a dvě přílohy.
  [Rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md).
  Technické dokončení není odstranění všech legacy nálezů ani schválení UAT.

[Převzetí](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)
