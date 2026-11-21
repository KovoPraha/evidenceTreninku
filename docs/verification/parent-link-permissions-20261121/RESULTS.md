# KIS-PARENT-LINK-01 — kontrola propojení rodiče, 21. 11. 2026

Větev `codex/parent-link-permissions-20261121` z `origin/main` `11505aed51`; samostatný worktree. Použity pouze syntetické účty a SQLite v paměti, bez produkčního čtení a zápisu. PR/CI/merge/deploy/uživatelská přejímka: NOT RUN při místním ověření.

## Nález a změna

- PASS (statická analýza): obecnou chybu vyvolávala `personSensitiveRequireAdmin()` přes `sportovecEditorRequireActor()` při `attach_account`; tato kontrola je určena k přístupu k rodnému číslu. Čtení `sportovecEditorContext()` pro sekci účtů ji nevolá. Pracovní pozice `registrar` vlastní správu vazeb.
- Oprava: zápisy rodičovských kontaktů a vazeb kontrolují aktivní pozici registráře a v transakci aktuální DB grant. Čtení a změna RČ dál používají původní striktní kontrolu. Při chybějící pozici nebo zrušeném grantu se zobrazí konkrétní důvod.
- Stav účtu se po schválení vysvětlí v potvrzení i v tabulce. Samotné schválení vazby neaktivuje účet ani neověřuje e-mail; portál nadále vyžaduje obě podmínky. Stávající sportovec i jedinečná vazba se použijí znovu.

## Ověření

| Kontrola | Výsledek |
| --- | --- |
| `tests/Integration/SportovecEditorDetailsTest.php` + `tests/Unit/SportovecEditorWiringTest.php` | PASS 15 testů / 108 assertions. Registrář bez přístupu k RČ načetl sekci a schválil čekající vazbu; neaktivní či neověřený účet nemohl spravovat sportovce, po aktivaci a ověření mohl; opakování nevytvořilo další vazbu ani osobu. Nesprávná pozice a odebraný DB grant zápis odmítly. |
| `tests/Integration/AccountPersonRoleTest.php`, `FamilyPortalTest.php`, `SportovecEditorTest.php` | PASS 18 testů / 110 assertions. |
| PHP syntax změněných PHP souborů; `git diff --check` | PASS. |
| `composer validate --strict`; `tools/check-current-docs.php` | PASS; oba krátké přehledy 80 řádků. |
| PHP CS Fixer proti čtyřem změněným PHP souborům | PASS, 0 souborů k opravě. Použitá binárka z oddělené lokální instalace; standardní `tools/check-php-style.php` v tomto worktree nelze dokončit, protože sdílený `vendor` neobsahuje `vendor/bin/php-cs-fixer`. CI musí ověřit běžný příkaz s úplnými závislostmi. |
| Přihlášený browser proti produkci, skutečný účet, CI, merge, deploy | NOT RUN. |

Omezení: syntetický test neprokazuje skutečné oprávnění konkrétního produkčního správce ani to, že konkrétní rodič prokázal vztah k dítěti. Provozní schválení vazby zůstává individuálním lidským krokem. Žádný skutečný rodičovský účet nebyl změněn.
