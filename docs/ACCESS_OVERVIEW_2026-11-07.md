# Přehled lidí, rolí a přístupů

ID ROLE01; vlastník: vývoj a vlastník aplikace. **HOTOVO: implementace, nasazení
a technická produkční přejímka 16/16.** [Rozhodnutí](DECISION_LOG.md#d2026-11-07-02).

Zadání 7. 11. 2026: společný přehled všech pracovních účtů a jejich přístupů
k trenérským funkcím, e-shopu a baru s jednoduchou změnou rolí.

- Větev `codex/access-overview-20261107`, samostatný worktree
  `C:/Users/Marek/Documents/Codex/evidencePavel-access-overview-20261107`.
- Základ `origin/main` `89edfdbe`; původní WIP zachované.
- Původní rozsah: místní implementace a syntetické ověření. Následný výslovný
  pokyn vlastníka 7. 11. schvaluje merge, produkční nasazení a ověření změn rolí.
  Stávající role lidí se nesmějí měnit; test pouze na novém syntetickém účtu,
  na konci odebrat jeho oprávnění a deaktivovat jej. Srovnat původní přístupy před/po.
- HOTOVO místně: `sprava_pracovnich_pozic.php` je společný přehled všech
  pracovních účtů s vyhledáváním a filtry rolí/stavu, dostupný v menu jako
  **Správce systému → Lidé a přístupy**. URL zůstává stejná.
- Zahrnout zvláštní grant vlastní spotřeby baru; superadmin má dostupné všechny
  pracovní pozice, samotná role správce baru vlastní spotřebu nepovoluje.
- Editor rolí, výchozí pozice, deaktivace, přejmenování a správa superadmina
  zachované. Vlastní spotřebu lze auditovaně povolit/odebrat přímo v editoru
  prostřednictvím existující autorizační služby baru.
- Tři dávkové dotazy načítají účty, přiřazení a zvláštní granty. Přehled
  ukazuje i deaktivované a nezařazené účty; neodvozuje přístupy z legacy role.
- Sloučeno [PR #356](https://github.com/KovoPraha/evidenceTreninku/pull/356),
  commit `ebb8b2e3`, všech 11 CI kontrol PASS; novější hlavní změny zachované.
  NASAZENO / ověřeno: deploy `42360171293`, přejímka `42360515313`,
  [produkční protokol](verification/access-release-20261107/RESULTS.md).
  Dokumentační předání `codex/access-release-20261107` ve stejném worktree.
  Žádnému skutečnému člověku zde nebyla role změněna.
- Hranice: přehled pracovních účtů není seznam zákazníků ani všech sportovců;
  trenérské soukromé rozsahy zůstávají samostatnou správou.

## Schválené produkční ověření

Browser Computer Use se zastavil, protože nedokázal ověřit aktuální URL.
Původní browser úkon se neopakuje. Produkční ověření proto provede chráněný
`production-drills.yml / overit-pracovni-pristupy` přes skutečné HTTPS formuláře:

1. `access_phase=baseline` před nasazením uloží pouze čtecí otisk rolí, výchozích
   pozic, superadminů a zvláštních barových grantů původních účtů mimo webroot.
2. Standardní deploy z main vytvoří a ověří zálohu; bez importu kroužků.
3. `access_phase=verify`, `access_run_id=<baseline běh>` založí dva nové technické
   účty na neexistujících adresách `@example.invalid`, bez sdílení hesel či pošty.
   Použije skutečný login, CSRF, formuláře rolí a explicitního grantu spotřeby,
   zkontroluje jejich persistenci, audit i povolení/odmítnutí přístupu.
4. Úklid ve `finally` deaktivuje výhradně tyto nové účty a odebere jejich pozice
   i granty. Původní ID nesmějí být přijata jako testovací účet ani při úklidu.
   Porovná otisk všech původních přiřazení s bodem 1. Při přerušení procesu lze
   spustit samostatné `access_phase=cleanup` se stejným baseline ID.

Hesla zůstávají pouze v paměti procesu; soukromý obnovovací soubor obsahuje jen
ID, otisky a stav. Výstupem jsou agregace a výsledky kontrol, nikoli HTML
se skutečnými jmény/e-maily. Nejde o nové vizuální produkční UAT v Chrome.

Ověření: 44 cílených testů / 2413 assertions, 16 HTTP kontrol formulářů a
Chrome při šířkách 1280 a 390 px. Podrobnosti a hranice bootstrapu jsou v
[protokolu](verification/access-overview-20261107/RESULTS.md). Návaznosti:
[registr práce](WORK_PLAN_CURRENT.md), [hlavní předání](HANDOFF_CURRENT.md).
