# Aktuální stav projektu

Aktualizováno 2. 11. 2026 (Europe/Prague), po nasazení funkčních oprav PR #272.

- [Funkční opravy PR #272 nasazeny](verification/functional-release-20261102/RESULTS.md): vrstvy účtového menu, přístupné názvy měření a viditelná poznámka Kolo. [Místní browser](verification/functional-uat-20261102/RESULTS.md): 36 PASS / 3 FIXED.

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
- **Živé vydání:** `c1686b4895c2d45428a4ca492b3c56fc4d0fe286`,
  [deploy 41365449270](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41365449270) SUCCESS.
  [PR #272](https://github.com/KovoPraha/evidenceTreninku/pull/272) přidává tři UI opravy;
  předchozí oprava neplatných/použitých odkazů PR #269, pilot a W6–W15 zachovány.
  [Protokol](verification/functional-release-20261102/RESULTS.md),
  [porovnání](verification/functional-release-20261102/release-comparison.json).
  Živé SHA/run potvrdil čtecí audit 41365953921; rsync všech tří runtime souborů
  a přesný veřejný CSS hash ověřeny. Nejde o úplnou inventuru hashů runtime.
- **Ověření:** všech 11 PR kontrol PASS, sloučený strom shodný s ověřeným,
  Linux 1902 testů / 38239 assertions, JavaScript 19/19;
  POSIX bez skipů, MariaDB 10.3/11.4 a objemové profily PASS.
  Deploy zopakoval 1902/38239; HTTP před i po 26/26; produkční katalog/filtry PASS.
  Produkční relace odhlášená: přihlášené UI znovu neověřeno; syntetické UAT je místní.
  Změna hesla, skutečný použitý odkaz a T07 jsou dle vlastníka nyní vynechány.
- **Záloha a schéma:** nová ověřená záloha 337 tabulek / 2 triggery;
  [doklad](verification/functional-release-20261102/deployment.json). Poslední doložená
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
- **Konfigurace a flags:** hash i přílohy před/po shodné; Fio import/auto-confirm,
  SumUp, Stripe a Velocota false; Packeta nedefinovaná, KIS canonical writes false.
  UAT false, import false; žádné integrace nebyly aktivovány.
- **Otevřené provozní převzetí:** lidská/účetní přejímka D12–D14, skutečná
  inventura/ocenění, vazby a trenérské rozsahy, dohody/částky a dvě přílohy.
  [Rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md).
  Technické dokončení není odstranění všech legacy nálezů ani schválení UAT.

[Převzetí](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)
