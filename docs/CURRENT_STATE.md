# Aktuální stav projektu

Aktualizováno 31. 10. 2026 (Europe/Prague), po nasazení trenérské obnovy hesla.

- **Trenérská obnova hesla nasazena:** samostatný režim obnovy, šifrovaná fronta,
  odvolání relací a výhradně trenérský worker. [Příprava pilotu](TRAINER_READINESS_2026-10-31.md).
  28 z 36 aktivních pracovních účtů splňuje podmínky obnovy; 8 je blokováno
  e-mailem nebo explicitní vazbou. Všech 36 účtů má pozici trenéra, ale žádný
  aktuální explicitní rozsah. Konkrétní trenéry/skupiny musí určit vlastník.
  Jediný schválený testovací e-mail transport přijal; doručení do schránky
  ještě nepotvrdil příjemce. Worker ručně PASS, plán po 5 minutách aktivní,
  skutečný naplánovaný běh zatím nedoložen. Tři staré klubové zprávy nedotčeny.

- **W6–W15 implementovány, sloučeny, nasazeny a technicky ověřeny.**
  [Rozsah a omezení přejímky](REFACTORING_CLOSURE_2026-10-30.md).
- **Živé vydání:** `3d19aae6f9e0c9e008fe4c80e7229faa07b09cf1`,
  [deploy 41185815825](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41185815825) SUCCESS.
  Zahrnuje PR #258 a dříve nasazené W6–W15.
  [Protokol](verification/trainer-recovery-20261031/RESULTS.md),
  [porovnání vydání](verification/trainer-recovery-20261031/release-comparison.json).
  Živé SHA/run potvrdil následný read-only audit 41186287421;
  identita je doložena deployment metadata a workflow, nikoli novou inventurou všech souborů.
- **Ověření:** všech 11 PR kontrol PASS, sloučený strom shodný s ověřeným,
  následné main kontroly také PASS. Linux 1873 testů / 37959 assertions,
  POSIX bez skipů, MariaDB 10.3/11.4 a objemové profily PASS.
  Deploy znovu provedl 1873/37959; HTTP před i po 26/26, trenérské stránky 3/3.
  Izolovaný HTTP průchod prokázal reset, přihlášení a odvolání původní relace.
- **Záloha a schéma:** čerstvá záloha 336 tabulek / 2 triggery ověřena před
  migrací. [Izolovaná obnova této zálohy](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41186359964)
  po nasazení PASS. Schéma current / pending [], ledger 142 → 143,
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
