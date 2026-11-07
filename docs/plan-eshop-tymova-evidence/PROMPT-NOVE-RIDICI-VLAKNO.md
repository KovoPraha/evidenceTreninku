# Prompt pro pokračování práce

Text pro nové vlákno nebo vývojáře (žádné automatické pověření k delegaci):

```text
Pracuj v repozitáři KovoPraha/evidenceTreninku. Nejprve ověř pracovní strom,
HEAD, upstream a remote; proveď git fetch --prune origin. Cizí WIP zachovej,
pro novou práci použij vlastní větev/worktree z čerstvého origin/main.

Načti soubory AGENTS.md, CLAUDE.md, docs/WORK_DOCUMENTATION_POLICY.md,
docs/CURRENT_STATE.md, docs/HANDOFF_CURRENT.md a docs/WORK_PLAN_CURRENT.md.
Podle položky registru načti odpovídající tematický plán, výsledky a rozhodnutí
z docs/DECISION_LOG.md; poslední vývoj dohledáš v docs/EVENT_LOG.md.
Historický board a starý session archiv nejsou dnešní prioritou ani oprávněním.

Všechny koncepty, záměry, implementační plány, zamýšlené postupy, změny,
rozhodnutí a výsledky průběžně zapisuj do verzovaných souborů repozitáře.
Interní paměť ani chat nejsou projektovou evidencí. Před implementací ulož
rozsah, oprávnění, postup, akceptaci a další krok do odpovídajícího souboru;
aktualizuj registr a odkazy v CURRENT i hlavním handoffu. Oba mají limit 80 řádků.

Během práce obnovuj stav a doklady, do deníku zapisuj významné události,
do rozhodovacího logu volby a důvody. Před předáním zapiš hotové/otevřené kroky,
testy, omezení, větev/PR a další akci. Po uzavření ponech plán dostupný z logu.
Dokumentaci commituj a předej přes GitHub PR spolu se změnou; rozlišuj lokální
práci, push, PR, merge, deploy, aktivaci a lidské převzetí.

Ověř php tools/check-current-docs.php, git diff --check a přiměřené testy.
Zachovej souběžné změny, tajemství a osobní data do Gitu nevkládej.
Plán není povolením produkční operace. Pokračuj dalším povoleným krokem
z aktuálního registru a tematického plánu.
```
