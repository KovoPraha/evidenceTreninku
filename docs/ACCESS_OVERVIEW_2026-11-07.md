# Přehled lidí, rolí a přístupů

Zadání 7. 11. 2026: společný přehled všech pracovních účtů a jejich přístupů
k trenérským funkcím, e-shopu a baru s jednoduchou změnou rolí.

- Větev `codex/access-overview-20261107`, samostatný worktree
  `C:/Users/Marek/Documents/Codex/evidencePavel-access-overview-20261107`.
- Základ `origin/main` `89edfdbe`; původní WIP zachované.
- Oprávnění: místní implementace a syntetické ověření. Produkční přiřazení,
  merge a nasazení nejsou součástí tohoto zadání.
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
- Další krok: revize a samostatně schválené nasazení; následně čtecí ověření
  skutečných účtů. Žádnému skutečnému člověku zde nebyla role změněna.
- Hranice: přehled pracovních účtů není seznam zákazníků ani všech sportovců;
  trenérské soukromé rozsahy zůstávají samostatnou správou.

Ověření: 44 cílených testů / 2413 assertions, 16 HTTP kontrol formulářů a
Chrome při šířkách 1280 a 390 px. Podrobnosti a hranice bootstrapu jsou v
[protokolu](verification/access-overview-20261107/RESULTS.md). Návaznosti:
[registr práce](WORK_PLAN_CURRENT.md), [hlavní předání](HANDOFF_CURRENT.md).
