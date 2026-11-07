# Projektové instrukce pro všechny vývojáře a agenty

Před prací načti [vývojový kontext](CLAUDE.md), [aktuální stav](docs/CURRENT_STATE.md),
[session handoff](docs/HANDOFF_CURRENT.md), [registr práce](docs/WORK_PLAN_CURRENT.md)
a [pravidla souborové dokumentace](docs/WORK_DOCUMENTATION_POLICY.md).
Potom načti příslušný tematický plán a související rozhodnutí ze souborů.

## Povinná evidence v repozitáři

Trvalé zadání vlastníka z 6.–7. 11. 2026: veškeré implementační plány, koncepty,
záměry, zamýšlené postupy, změny, rozhodnutí i výsledky patří do verzovaných
souborů tohoto repozitáře. Chat, interní paměť vlákna ani lokální `output/`
nejsou projektovou evidencí dostupnou ostatním přes GitHub.

- Už při zahájení/pokračování načti nebo založ odpovídající soubor v `docs/`.
  Zapiš ID, stav, rozsah, oprávnění, důvody/varianty, akceptaci, závislosti,
  vlastníka, větev/worktree a další krok. Koncept označ jako KONCEPT, nikoli schválený plán.
- Při zahájení, změně a před předáním aktualizuj tematický soubor,
  [registr](docs/WORK_PLAN_CURRENT.md), CURRENT a hlavní session handoff.
  V obou krátkých přehledech uveď odkaz na plán přímo nebo přes označenou položku registru.
  Výsledky a PASS/FAIL/NOT RUN ukládej do `docs/verification/` nebo tematického souboru.
- Změny stavu zapisuj do [deníku událostí](docs/EVENT_LOG.md), významné volby
  a jejich důvody do [rozhodovacího logu](docs/DECISION_LOG.md).
  Po dokončení doplň trvalý odkaz na plán/výsledek do historie, teprve potom
  jej odstraň z aktivního přehledu. Částečně hotové etapy zůstávají otevřené.
- CURRENT a HANDOFF_CURRENT udržuj do 80 řádků každý; starý SESSION_HANDOFF
  je pouze rozcestník do 25 řádků. Podrobnosti a historii odkazuj, nehromaď je zde.
- Dokumentaci commituj s příslušnou změnou a předej přes GitHub PR.
  Uveď skutečný stav lokální / push / PR / merge. Dokud není v main, není
  součástí společné výchozí verze; při předání uveď větev a PR.
- Implementaci, merge, nasazení, aktivaci a provozní/lidskou přejímku eviduj
  odděleně. Release/flags vlastní pouze CURRENT s důkazy; plán není provozní souhlas.
- Zachovej cizí WIP, používej vlastní větev/worktree a stage pouze vlastní soubory.
  Tajemství, hesla, session, dumpy ani osobní přílohy do Gitu nepatří.
- Ověř `php tools/check-current-docs.php` a `git diff --check` a přiměřené
  kontroly změny. Při souběhu převezmi věcné změny ostatních a znovu ověř odkazy.
