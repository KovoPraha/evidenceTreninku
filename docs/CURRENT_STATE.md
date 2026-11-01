# Aktuální stav projektu

Aktualizováno 1. 11. 2026 (Europe/Prague), po založení odděleného trenérského pilotu.

- **Trenérská obnova hesla nasazena:** samostatný režim obnovy, šifrovaná fronta,
  odvolání relací a výhradně trenérský worker. [Příprava pilotu](TRAINER_READINESS_2026-10-31.md).
  [Oddělený syntetický pilot](verification/trainer-pilot-20261101/RESULTS.md) je založen:
  trenér 56, soupiska 77, dvě syntetické osoby, třetí mimo rozsah. Grant končí
  15. 11. 2026 v 18:17:31 databázového času; obnova hesla způsobilá.
  37 aktivních pracovních účtů má pozici trenéra, 36 zůstává bez rozsahu;
  29 účtů je způsobilých k obnově hesla a původních 8 zůstává blokovaných.
  [Provozní auditní identita](TRAINER_PILOT_OPERATIONAL_2026-11-01.md) je neaktivní a bez oprávnění;
  veřejné účty se nepropojovaly ani nepovyšovaly. Úplné lidské UAT ještě neproběhlo.
  Jediný schválený testovací e-mail transport přijal a příjemce 31. 10.
  [potvrdil doručení](verification/trainer-recovery-20261031/mailbox-confirmation.json). Hostingový minutový cron znovu ověřen
  [1. 11. v 17:22:03 UTC](verification/trainer-pilot-20261101/trainer-readiness.json). Tři staré klubové zprávy nedotčeny.

- **W6–W15 implementovány, sloučeny, nasazeny a technicky ověřeny.**
  [Rozsah a omezení přejímky](REFACTORING_CLOSURE_2026-10-30.md).
- **Živé vydání:** `87596760f4267c9db378e8a4062e604fd22d68fa`,
  [deploy 41330031591](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41330031591) SUCCESS.
  Zahrnuje provozní pilot PR #266, předchozí obnovu hesla a W6–W15.
  [Protokol](verification/trainer-pilot-20261101/RESULTS.md),
  [porovnání vydání](verification/trainer-pilot-20261101/release-comparison.json).
  Živé SHA/run potvrdil následný read-only audit 41330824978;
  identita je doložena deployment metadata a workflow, nikoli novou inventurou všech souborů.
- **Ověření:** všech 11 PR kontrol PASS, sloučený strom shodný s ověřeným,
  Linux 1895 testů / 38095 assertions,
  POSIX bez skipů, MariaDB 10.3/11.4 a objemové profily PASS.
  Deploy znovu provedl 1895/38095; HTTP před i po 26/26, trenérské stránky 3/3.
  Izolovaný HTTP průchod prokázal reset, přihlášení a odvolání původní relace.
- **Záloha a schéma:** čerstvá záloha 337 tabulek / 2 triggery včetně hotového
  pilotu. [Izolovaná obnova této zálohy](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41330986065)
  PASS. Schéma current / pending [], ledger 143 beze změny,
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
