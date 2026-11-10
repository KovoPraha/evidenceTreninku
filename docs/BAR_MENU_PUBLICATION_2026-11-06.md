# BAR: zveřejnění základní nabídky

Stav 10. 11.: [původní TV obrazovky, převod zásob a oprava starých URL dokončené](verification/bar-display-stock-20261110/RESULTS.md). 68 počátečních zůstatků obou skladů převzato přesně včetně pěti záporných hodnot; fyzická inventura a lidské P02/P04 jsou nadále otevřené. Nabídka 1 / vydání 1 se znovu nepublikovala. Původní postup a nulové počátky níže jsou datovaná historie; další zásoby spravovat pouze v KIS, původní zdroj znovu nesynchronizovat.

Zadání vlastníka 6. 11. 2026: zveřejnit existující nabídku „Bar KOVO – základní nabídka“ a aktualizovat přímo souborovou provozní dokumentaci, session handoff i navazující plán. Nový návrh se nezakládá. Tato etapa navazuje na [technickou přejímku a návrh 1](verification/bar-acceptance-20261106/RESULTS.md) a [plán začlenění](BAR_INTEGRATION_PLAN_2026-11-05.md).

## Rozsah a postup

1. Z čerstvého `origin/main` pracovat ve větvi `codex/bar-menu-publication-20261106` ve stávajícím izolovaném worktree. Zachovat souběžné změny i původní uživatelský checkout.
2. Přes existující přihlášené rozhraní Administrátor baru ověřit nabídku 1, její název, fotografie a všech 45 prodejních produktů. Před publikací zde dosud žádná veřejná nabídka nebyla.
3. Zveřejnit uloženou nabídku s výslovným důvodem schválení vlastníkem. Neměnit katalogové ceny, role, zásoby nebo kasy a nevytvářet finanční doklady.
4. Ověřit uloženou publikaci, veřejné menu, fotografie, tiskový lístek a návaznost pokladny. Výsledky a snímky uložit do [publikačního protokolu](verification/bar-menu-publication-20261106/RESULTS.md). Zveřejnění mění výběr veřejného menu i pokladny; není dokladem připravených skutečných zásob.
5. Aktualizovat CURRENT_STATE, HANDOFF_CURRENT, dokumentační rozcestník, plán začlenění, provozní připravenost a datovaný session handoff. Původní přejímku zachovat jako historický důkaz a připojit odkaz na novější publikaci.
6. Ověřit místní odkazy a dokumentační kontrakt, zapsat kontrolovatelné výsledky, commit a PR. Nasazení aplikačního kódu tato datová operace nevyžaduje.

## Průběžný stav

**Dokončeno:** nabídka 1 zveřejněná jako vydání 1, 45 produktů a 45 fotografií. Přihlášené rozhraní i veřejný Chrome PASS; nezávisle ověřené anonymní menu, všechny fotografie, přesměrování staré adresy a ochrana neveřejných cest. [Protokol](verification/bar-menu-publication-20261106/RESULTS.md) obsahuje snímky a strojový souhrn. Pokladna nadále vyžaduje vlastní kasu, finanční zápis nevznikl. Přihlášené generování PDF v této etapě NOT RUN.

Souborové návaznosti jsou aktualizované a místní dokumentační kontroly PASS. Další skutečné provozní kroky a plán účetního propojení vede [session handoff](SESSION_HANDOFF_BAR_2026-11-06.md), trvalý způsob zápisu [pravidla dokumentace](WORK_DOCUMENTATION_POLICY.md). Plné automatické brány a související PR doplňují předání v session handoffu.
