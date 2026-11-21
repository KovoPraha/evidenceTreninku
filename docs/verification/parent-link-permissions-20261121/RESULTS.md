# KIS-PARENT-LINK-01 — kontrola propojení rodiče, 21. 11. 2026

Větev `codex/parent-link-permissions-20261121` z `origin/main` `11505aed51`; samostatný worktree. Použity pouze syntetické účty a SQLite v paměti, bez produkčního čtení a zápisu. Commit `47f4362d` pushnutý, [PR #507](https://github.com/KovoPraha/evidenceTreninku/pull/507) otevřený; CI ve frontě, merge/deploy/uživatelská přejímka NOT RUN při tomto zápisu.

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

## Souběh 21. 11. 2026

Nové `origin/main` `5acd225f` z dokumentačního #505 bylo převzato merge commitem `84af1987`. Konflikty v `EVENT_LOG` a `HANDOFF_CURRENT` byly vyřešeny zachováním obou věcných záznamů; další sdílené dokumenty z #505 zůstaly v jejich novější podobě. Po sloučení PASS: `php tools/check-current-docs.php` (80/80 řádků, 5861 odkazů, 0 chyb), `git diff --check` a 33 cílených PHPUnit testů / 218 assertions. PR #507 je znovu mergeable; jeho nová CI revize ještě běží. Produkční merge a deploy NOT RUN.

Předchozí aplikační commit `c15e854c` dokončil [Testy 44901514147](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44901514147) SUCCESS včetně PHPUnit a MariaDB 10.3/11.4; samostatné PHP quality a dokumentační workflow byly také SUCCESS. Nový head po převzetí `main` má vlastní CI, jejíž výsledek zatím není převzat.

Následný head `f96abbf8` po #505 dokončil 11/11 kontrol PR #507 jako SUCCESS (PHPUnit, PHPStan/styl, dokumentace, MariaDB 10.3/11.4 v obou sadách a CodeQL). Poté přibyl `origin/main` `741c0625` (#506) s rodičovskou e-mailovou frontou. Kód vazeb se nepřekrývá; konflikty sdílených dokumentů byly sloučeny se zachováním obou témat. Na společném stromu PASS 40 cílených testů / 273 assertions, dokumentační kontrola 80/80 řádků a 5882 lokálních odkazů bez chyby. CI výsledného merge commitu dosud NOT RUN.

Následně head `5a372d46` dokončil 11/11 kontrol PR #507 jako SUCCESS: PHPUnit, PHPStan a styl změněných souborů, dokumentace, obě integrační a profilové úlohy MariaDB 10.3/11.4 a CodeQL. PR je otevřený a mergeable; merge, deploy a přihlášené produkční UAT NOT RUN. Tento závěr se týká syntetického ověření kódu, nikoli účtu č. 453.

Následné `origin/main` `eb78c45b` (#509) doplnilo provozní dokumentaci e-mailové fronty, nikoli aplikační kód. Při převzetí byly ponechány jeho nové produkční výsledky i záznam KIS-PARENT-LINK-01. Po sloučení dokumentů `php tools/check-current-docs.php` PASS (oba přehledy 80 řádků, 5889 odkazů bez chyby); merge/deploy opravy vazeb a UAT NOT RUN.

Před vydáním vlastník výslovně schválil „nasadit“. Souběžné `origin/main` `ccac61ab` (#510) přidalo kód 72hodinového resetu a samostatný workflow pro řízené vydání dvou náhradních odkazů; tento workflow nebyl spuštěn. Společný strom: 28 cílených PHPUnit testů / 202 assertions PASS (`SportovecEditorDetailsTest`, `AuthMailOutboxTest`, `PasswordResetTest`), `php tools/check-current-docs.php` PASS (80/80 řádků, 5907 lokálních odkazů, 0 chyb), `git diff --check` PASS. Merge PR #507, deploy a přihlášené produkční UAT při tomto zápisu NOT RUN.

Revize `fa817de4` po #510 dokončila 11/11 CI SUCCESS. Souběžné `main` `6dcd0a20` (#508) změnilo plánování tréninků, nikoli kód rodičovské vazby. Po převzetí na společném stromu 27 cílených testů / 151 assertions PASS (`SportovecEditorDetailsTest`, `TrainingClosureTest`); dokumentační kontrola 80/80 řádků a 5931 lokálních odkazů bez chyby. PR #507 čeká na CI nové revize; merge, deploy a přihlášené UAT NOT RUN.

## Produkční vydání 21. 11. 2026

- Finální head `069cbe37` po #508: 11/11 CI SUCCESS. [PR #507](https://github.com/KovoPraha/evidenceTreninku/pull/507) sloučen jako `7d856ed667be5a0a3ff02a6893abd30018e1456e`.
- [Chráněný deploy 44912234389](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44912234389): workflow_dispatch z `main` na přesném SHA `7d856ed6`, `potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`; dokončeno SUCCESS. Release testy 2538/60410 PASS, ověřená záloha mimo webroot PASS, migrace `current=true` a `pending=[]` PASS, kontrola dosavadních variabilních symbolů PASS, aktivace release PASS a veřejný HTTP smoke 200 PASS. Krok importu kroužků byl SKIPPED.
- Jednorázový workflow pro vystavení náhradních resetů nebyl v rámci tohoto vydání spuštěn. Žádný skutečný rodičovský účet ani vazba nebyly v tomto ověření změněny; přihlášené produkční UAT a kontrola účtu č. 453 jsou NOT RUN. Anonymní HTTP smoke ani syntetické testy je nenahrazují.
