# Aktuální stav projektu

Aktualizováno 30. 10. 2026 (Europe/Prague), po úspěšném finálním nasazení.

- **Navazující práce 31. 10.:** [trenérská obnova hesla a pilot](TRAINER_READINESS_2026-10-31.md)
  implementovány ve větvi; nasazení a doručení zatím nejsou potvrzeny.

- **W6–W15 implementovány, sloučeny, nasazeny a technicky ověřeny.**
  [Rozsah a omezení přejímky](REFACTORING_CLOSURE_2026-10-30.md).
- **Živé vydání:** `d3f507bc2d21e4e25cfab4c3ad867a9aadf33b5d`,
  [deploy 41129311692](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41129311692) SUCCESS.
  Zahrnuje finální aplikační PR #255 a dokumentační PR #256.
  [Protokol obnovy a nasazení](verification/db-temp-recovery-20261030/RESULTS.md),
  [porovnání vydání](verification/db-temp-recovery-20261030/release-comparison.json).
  Živé SHA/run potvrdil následný read-only audit 41130072050;
  identita je doložena deployment metadata a workflow, nikoli novou inventurou všech souborů.
- **Ověření:** přesné main CI Testy 41097723229, PHP quality 41097723222,
  docs 41097723233 a CodeQL 41097722410 SUCCESS. Linux 1864 testů /
  37843 assertions, POSIX bez skipů, MariaDB 10.3/11.4 a objemové profily PASS.
  Deploy znovu provedl 1864/37843; HTTP před i po 26/26.
- **Záloha a schéma:** čerstvá záloha 336 tabulek / 2 triggery ověřena před
  migrací. [Izolovaná obnova této zálohy](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41130077458)
  po nasazení PASS. Schéma current / pending [], ledger 141 → 142,
  dosavadní variabilní symboly beze změny.
- **DB kapacita obnovena:** po úplné externí záloze a skutečné obnově Search
  byl autorizovaně vyprázdněn pouze odvozený index Velocoty; +3,35 GB,
  následně přibližně 5,6 GB volných na systémovém oddílu. Diskové temporary
  tables opakovaně fungují. Původní příčina kolísání kapacity není definitivně určena.
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
