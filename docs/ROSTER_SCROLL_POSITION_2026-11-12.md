# ROSTER-SCROLL-01 — pozice při správě soupisek

Datum: 12. 11. 2026. Stav: NASAZENO, přihlášené UAT otevřené. Vlastník: vývoj KIS; zadání a akceptace: vlastník. Vývojová větev `codex/roster-scroll-position-20261112`. Související registr: [ROSTER-SCROLL-01](WORK_PLAN_CURRENT.md).

## Rozsah a rozhodnutí

V `kis_roster_hierarchy_admin.php` zachovat svislou pozici po odeslání formulářů (včetně vložené správy členů), po úspěšném POST/303/GET, při náhledu a chybě. Zachovat výběr soupisky, rozhodné datum a hledání osoby. Jde o UX změnu bez změny autorizace, CSRF, validací nebo datových operací. Schváleno přímým zadáním 12. 11. 2026.

Zvolený postup: při submitu doplnit do formuláře aktuální `scrollY`; server propustí pouze nezáporné celé číslo s rozumným limitem do jednorázového parametru přesměrování. Načtená stránka po `load` a vykreslení obnoví posun a parametr odstraní pomocí `history.replaceState`. Stejná hodnota vrácená v HTML řeší náhled a chybu bez přesměrování. U POST se zároveň přenesou pouze výběr soupisky, datum a hledání; nepřenáší se libovolná návratová URL. GET formuláře předají pozici přímo. Proti alternativě `sessionStorage` se neuchovává nic pro další nezávislou návštěvu ani při přerušení navigace.

## Akceptace a otevřené kroky

- Několik akcí za sebou, uprostřed dlouhého seznamu; přidání, potvrzení, přestup a ukončení členství, úpravy skupin i další formuláře.
- Výběr soupisky, datum a hledání se po POST zachovají; chyba/náhled zůstane v původní části stránky.
- Desktop a mobil; navigace na stránku bez jednorázového parametru neobnovuje dřívější pozici.
- Ověřit syntaxi PHP, cílené testy, `php tools/check-current-docs.php`, `git diff --check`; přihlášené produkční UAT je samostatná přejímka.

Výsledek: změna kontroleru a vloženého view, [protokol](verification/roster-scroll-20261112/RESULTS.md) s desktop/mobilním syntetickým průchodem, cílenými testy a vydáním. Další krok: přihlášená přejímka podle [scénáře pro testerku](TESTER_ROSTER_SCROLL_2026-11-13.md) na skutečném dlouhém seznamu, včetně opakovaných akcí a mobilního zařízení.

Předání: [PR #462](https://github.com/KovoPraha/evidenceTreninku/pull/462) sloučen jako `22d151f7` po 11 PASS kontrolách. [Deploy 43320369018](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43320369018) SUCCESS na stejném SHA; přihlášené UAT NOT RUN.

Vydání 12. 11.: vlastník výslovně požádal „nasadit“. Po integraci tehdejšího `main` byl #462 sloučen a spuštěno chráněné `deploy-production.yml` se vstupy `potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`. Workflow provedlo zálohu, migrace a HTTP smoke. Přihlášené UAT uživatelského posunu zůstává samostatné; import kroužků ani aktivace další agendy nebyly součástí tohoto vydání.

Výsledek vydání: workflow dokončilo testy 2460/56455, zálohu 393 tabulek a 2 triggerů, migrace current/pending [], kontrolu neměnných VS, aktivaci i HTTP 200. Krok importu kroužků byl přeskočen. Anonymní GET na `kis_roster_hierarchy_admin.php` vrací 302 na `login.php`, jak vyžaduje přihlášení. Toto není důkaz přihlášeného zachování posunu; provozní UAT zůstává otevřené.
