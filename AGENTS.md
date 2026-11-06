# Projektové instrukce pro agenty

Před prací čti [vývojový kontext](CLAUDE.md), [aktuální stav](docs/CURRENT_STATE.md),
[handoff](docs/HANDOFF_CURRENT.md) a [registr práce](docs/WORK_REGISTER.md).

## Průběžné souborové záznamy

Výslovný požadavek vlastníka z 6. 11. 2026: vše, na čem se pracuje nebo co je
v plánu udělat, musí být průběžně zapsané ve verzovaných souborech repozitáře,
s odkazy na související dokumenty. Chat nesmí být jediný záznam.

- Při zahájení/pokračování doplň registr a související tematický plán: rozsah,
  oprávnění, větev/worktree, stav, další krok a závislosti. Nerozepisuj duplicitní
  plán, když už existuje; odkazuj na něj.
- Výsledky, testy a omezení ulož do `docs/verification/` nebo existujícího
  tematického dokumentu. Aktualizuj handoff před ukončením/předáním session.
- Implementaci, merge, nasazení a provozní ověření eviduj odděleně. Aktuální
  release/flags s důkazy patří do CURRENT_STATE; historické protokoly nepřepisuj
  jako dnešní stav. Plán není souhlas s produkční změnou.
- Zachovej cizí WIP, používej vlastní větev/worktree a stage pouze vlastní soubory.
  Do Gitu nedávej konfiguraci s tajemstvími, hesla, session, dumpy ani osobní přílohy.
- Dokumentační změny ověř `php tools/check-current-docs.php` a `git diff --check`;
  CURRENT_STATE zachovej do 80 řádků. Ověř také odkazy v nových souborech.
