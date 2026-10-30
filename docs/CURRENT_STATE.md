# Aktuální stav projektu

Aktualizováno 30. 10. 2026 (Europe/Prague), po pokusu o nasazení v 17:04 UTC.

- **Implementace W6–W15:** dokončená a sloučená, poslední [PR #255](https://github.com/KovoPraha/evidenceTreninku/pull/255).
  [Přehled oblastí a omezení](REFACTORING_CLOSURE_2026-10-30.md).
  Cílové aplikační SHA `2345b648a237973e96593daa5d8588be2a99c28a`.
- **CI přesného cíle:** [Testy 41094283333](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41094283333),
  [PHP quality 41094283244](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41094283244),
  [docs 41094283278](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41094283278),
  [CodeQL 41094283424](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41094283424) SUCCESS.
  Linux 1864 testů / 37843 assertions, POSIX bez skipů, MariaDB 10.3/11.4,
  typy/styl, JS, souběhy, rozpočty a skutečná izolovaná obnova PASS.
- **Poslední doložené živé vydání:** W10.2 `7828758cfe832a99c11ddeafe916b8480e501feb`,
  [deploy 41077903487](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41077903487).
  [Poslední úspěšné porovnání vydání](verification/refactoring-w102-release-20261030/release-comparison.json).
  Ověřeno read-only runem 41094301278 před novým pokusem.
- **Nasazení W10.3–W15 BLOKOVÁNO:** [41095272194](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41095272194)
  selhalo při záloze, před migrací a aktivací: MariaDB 1021 / disk full v `/tmp`.
  [Protokol a podklady pro hosting](verification/refactoring-final-release-20261030/RESULTS.md).
  Následný audit chybu potvrdil 3×. Web vidí 17,9 GB volných; stav DB prostoru,
  inodeů a kvót tím není prokázán. Nic z rollbacků/záloh nebylo odstraňováno.
  HTTP před 26/26, po pokusu 13/26: katalog/detail vracejí 500 na původní verzi.
- **Provozní zásah:** správce hostingu musí prověřit skutečný DB `/tmp`,
  inody/kvóty a namespace procesu. Po nápravě čerstvý audit + HTTP,
  nový deploy se zálohou a následný release důkaz. Finální brána není uzavřena.
- **Poslední úspěšná kontrola flags:** Fio import/auto-confirm, SumUp, Stripe
  a Velocota false; Packeta nedefinovaná, KIS canonical writes false.
  Config hash a přílohy odpovídaly W10.2; ledger 141. Nová migrace čeká (cílově 142).
  Selhaný pokus měl UAT false a import false; integrace nebyly aktivovány.
- **Otevřené provozní převzetí:** lidská/účetní přejímka D12–D14, skutečná
  inventura/ocenění, vazby a trenérské rozsahy, dohody/částky a dvě přílohy.
  [Rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md).
  Technické dokončení není odstranění všech legacy nálezů ani schválení UAT.

[Převzetí](HANDOFF_CURRENT.md) · [Historie](CHANGELOG-PROVOZ.md) · [Dokumentace](README.md)
