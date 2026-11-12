# ROSTER-SCROLL-01 — pozice při správě soupisek

Datum: 12. 11. 2026. Stav: K OVĚŘENÍ. Vlastník: vývoj KIS; zadání a akceptace: vlastník. Větev `codex/roster-scroll-position-20261112`, samostatný worktree `C:/xampp/htdocs/evidencePavel-roster-scroll-20261112` nad `origin/main`. Související registr: [ROSTER-SCROLL-01](WORK_PLAN_CURRENT.md).

## Rozsah a rozhodnutí

V `kis_roster_hierarchy_admin.php` zachovat svislou pozici po odeslání formulářů (včetně vložené správy členů), po úspěšném POST/303/GET, při náhledu a chybě. Zachovat výběr soupisky, rozhodné datum a hledání osoby. Jde o UX změnu bez změny autorizace, CSRF, validací nebo datových operací. Schváleno přímým zadáním 12. 11. 2026.

Zvolený postup: při submitu doplnit do formuláře aktuální `scrollY`; server propustí pouze nezáporné celé číslo s rozumným limitem do jednorázového parametru přesměrování. Načtená stránka po `load` a vykreslení obnoví posun a parametr odstraní pomocí `history.replaceState`. Stejná hodnota vrácená v HTML řeší náhled a chybu bez přesměrování. U POST se zároveň přenesou pouze výběr soupisky, datum a hledání; nepřenáší se libovolná návratová URL. GET formuláře předají pozici přímo. Proti alternativě `sessionStorage` se neuchovává nic pro další nezávislou návštěvu ani při přerušení navigace.

## Akceptace a otevřené kroky

- Několik akcí za sebou, uprostřed dlouhého seznamu; přidání, potvrzení, přestup a ukončení členství, úpravy skupin i další formuláře.
- Výběr soupisky, datum a hledání se po POST zachovají; chyba/náhled zůstane v původní části stránky.
- Desktop a mobil; navigace na stránku bez jednorázového parametru neobnovuje dřívější pozici.
- Ověřit syntaxi PHP, cílené testy, `php tools/check-current-docs.php`, `git diff --check`; přihlášené produkční UAT je samostatná přejímka.

Výsledek: změna kontroleru a vloženého view, [místní protokol](verification/roster-scroll-20261112/RESULTS.md) s desktop/mobilním syntetickým průchodem a cílenými testy. Další krok: PR a CI; poté přihlášená přejímka u registráře. Nasazení ani lidská přejímka dosud neproběhly.

Předání: commit `5ab66f2d` pushnutý do větve, [PR #462](https://github.com/KovoPraha/evidenceTreninku/pull/462) otevřen proti `main`; CI běží. Merge, deploy a přihlášené UAT NOT RUN.
