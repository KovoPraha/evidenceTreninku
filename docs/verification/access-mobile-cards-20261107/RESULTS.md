# ROLE01-MOB — mobilní karty lidí a přístupů

> Aktualizace katalogu 7. 11. 2026: #361 sloučené jako `3b37e053`.
> Původní přehled a čtecí vizuální kontrola dokončené; navazující mobilní
> karty jsou implementované v main. Jejich následný deploy a nový produkční
> Chrome průchod zůstávají nedoložené, viz registr ROLE01-MOB.


7. 11. 2026, [schválený plán](../../ACCESS_OVERVIEW_2026-11-07.md),
[rozhodnutí](../../DECISION_LOG.md#d2026-11-07-06). Vlastní worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-access-overview-20261107`, větev
`codex/access-visual-uat-20261107`, [PR #361](https://github.com/KovoPraha/evidenceTreninku/pull/361).

## Implementace a ověření

- HOTOVO místně: pod 768 px sémantické karty osob místo široké tabulky.
  Jméno, kontakt, stav, čtyři skupiny rolí, výchozí pozice, superadmin,
  samostatná vlastní spotřeba a přímý GET odkaz do editoru.
- Desktopová tabulka zůstává; obě zobrazení používají stejné filtrované účty
  a kanonické definice rolí. Pouze šablona, žádná změna zápisu nebo schématu.
- PASS: PHP syntaxe šablony, dokumentační kontrakt, `git diff --check`.
- PASS: 44 cílených testů / 2413 assertions přehledu/pozic/životního cyklu a baru.
- PASS: `php tools/check-php-style.php`, PSR-12 a délka řádků (1 změněný PHP soubor).
- CI: čeká na poslední hlavu PR.
- Merge, deploy a produkční Chrome 390/360 px + desktop: NOT RUN.

## Oprávnění a další krok

Vlastník schválil merge, nasazení a vizuální přejímku. Žádné stávající role
nikomu neměnit. V produkci jen GET, filtry a otevření editoru; neukládat
formuláře, neaktivovat účty, nepřidávat granty ani testovací identity.
Neopakovat starší zapisující drill. Standardní deploy bez importu kroužků.
Další krok: dokončit testy/CI, sloučit přesnou hlavu PR a nasadit; poté
vizuálně zkontrolovat karty a filtry bez bočního posunu a desktopovou tabulku.

Čtecí baseline 42364034851 PASS: 48 účtů, 82 přiřazení, 5 superadminů,
1 barový grant. SHA-256 `7cb89121cb73926ba98601ee9e4b04d3684229ab1b966a99585c28de3fbcdd41`.
Vlastník výslovně schválil společné nasazení karet a cenové optimalizace #358.
Pokus o místní vizuální ukázku přerušilo neodpovídající spojení Computer Use;
žádný úspěšný snímek nevrácen, vizuální přejímka zůstává NOT RUN. Místní server zastaven.
