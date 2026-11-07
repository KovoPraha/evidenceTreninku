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

### Mobilní karty ROLE01-MOB

7. 11. vlastník výslovně schválil úpravu mobilního přehledu na karty, merge,
nasazení a nové vizuální ověření. **NASAZENO, vizuální přejímka K OVĚŘENÍ**; implementační větev
`codex/access-visual-uat-20261107`, stejný worktree, základ main `9460ee76`.
PR #361 sloučeno jako `3b37e053`; deploy `42365946802` SUCCESS, všech 11 CI PASS.
Mobil pod 768 px: jedna karta na člověka, celé jméno/kontakt, stav, skupiny rolí,
výchozí role, superadmin a samostatný grant spotřeby; tlačítko úpravy po ruce.
Desktop zachová tabulku. Obě varianty čtou stejný filtrovaný seznam; bez změny
autorizace, ukládání, schématu či přiřazení. Varianta pouhého fixování jména
neřeší zalamování štítků, proto samostatné mobilní karty.
Akceptace: 390/360 px bez vodorovného posunu, jméno a role pohromadě, filtry,
prázdný výsledek a otevření editoru; desktop bez regrese. Produkční kontrola
jen GET, žádné uložení, aktivace, grant ani nový účet. Otisky před/po přesně shodné:
48 účtů / 82 přiřazení / 5 superadminů / 1 grant. Vlastník následně schválil
společný deploy s cenami #358. [Výsledky a hranice](verification/access-mobile-cards-20261107/RESULTS.md).
Další krok: obnovit neodpovídající spojení Computer Use a dokončit skutečný
Chrome 390/360 px a desktop. Nové karty vizuálně NOT RUN; další deploy není potřeba.
Výsledkové předání `codex/access-mobile-release-20261107`, stejný worktree; [otevřený PR #365](https://github.com/KovoPraha/evidenceTreninku/pull/365).

### Navazující vizuální kontrola ROLE01-VIS

7. 11. vlastník výslovně požádal znovu otevřít produkci v prohlížeči a ověřit
přehlednost a mobilní použitelnost. **HOTOVO s výhradou čitelnosti mobilní tabulky**, větev
`codex/access-visual-uat-20261107`, stejný samostatný worktree; základ `5cca5526`.
Rozsah jen čtením: desktop, mobilní šířky 390/360 px, filtry GET, posun tabulky
a otevření editoru. Žádné uložení, aktivace, grant, přepnutí pracovní role ani
nový testovací účet. Snímky s osobními údaji zůstanou mimo Git; do protokolu
patří bezpečný souhrn. [Výsledek skutečného Chrome průchodu](verification/access-visual-20261107/RESULTS.md):
desktop přehledný, mobil 390/360 px s bočním posunem, editor čitelný.
Filtr Bar 6/48, hledání neaktivního technického účtu 1/48, posun i otevření editoru PASS.
Role ani stav účtů se neměnily. Mobilní karty jsou pouze doporučená další úprava;
aplikační kód ani produkční vydání tento průchod nemění.

### Původní technická přejímka před navazujícím vizuálním průchodem

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
