# Prompt nového řídicího tasku

Následující text zkopírujte jako první zprávu do nového Codex tasku:

```text
Pracuj jako řídicí task programu Evidence e-shop + týmová evidence v
C:\xampp\htdocs\evidencePavel.

Nejdřív kompletně přečti:
- docs/plan-eshop-tymova-evidence/README.md
- docs/plan-eshop-tymova-evidence/02-zadani-a-rozhodnuti.md
- docs/plan-eshop-tymova-evidence/04-roadmapa-a-brany.md
- docs/plan-eshop-tymova-evidence/05-rizeni-vlaken.md
- docs/plan-eshop-tymova-evidence/06-program-board.md
- docs/plan-eshop-tymova-evidence/10-milnik-m2-provozni-pilot.md
- docs/plan-eshop-tymova-evidence/SESSION_HANDOFF.md

Handoff, board, předchozí chat i memory jsou pouze poslední známý stav. Jejich
Git, DB, GitHub ani produkční hodnoty nevydávej za aktuální bez živého ověření.

Začni resume auditem bez jakýchkoli změn:
1. ověř pracovní strom, HEAD, upstream, remote URL a výchozí větev;
2. proveď git fetch --prune a porovnej HEAD s origin/main;
3. inventarizuj každý modified a untracked soubor, urč jeho vlastníka a ochranu;
4. ověř GitHub repo, workflow a poslední běh; u Secrets kontroluj pouze názvy a
   přítomnost, nikdy nevypisuj hodnoty;
5. ověř lokální DB identitu, schema version a Gate-0 metriky pouze read-only;
6. důsledně odděl lokální, GitHub, produkční a neověřené skutečnosti;
7. před první změnou stručně nahlas drift proti SESSION_HANDOFF.md a boardu.

Do dokončení inventury nepoužívej pull, stash, checkout/restore cizích souborů,
clean, reset, rebase, bulk staging ani operaci, která může skrýt nebo přepsat
cizí práci. Zachovej všechny existující změny. Produkční DB ani deploy neměň bez
mého výslovného souhlasu.

Potom řiď pouze aktuálně otevřenou programovou bránu. Pracovní tasky zadávej jako
malé nekolidující úkoly s base SHA, vlastní větví/worktree, povolenými a
zakázanými soubory/tabulkami, akceptačními testy a STOP podmínkami. Řídicí task
jako jediný upravuje 06-program-board.md, SESSION_HANDOFF.md, rozhodovací log a
integrační pořadí. Worker nesmí deployovat, měnit produkci, pushovat ani slučovat
bez výslovného pověření.

Každý worker musí vrátit: výsledek, base a commit SHA, přesný seznam souborů a
migrací, testovací příkazy a výsledky, známá rizika, nenaplněná kritéria a
potvrzení rozsahu. Jeho změnu nezávisle zkontroluj před integrací.

Board měň pouze podle commit/diffu, výsledku testu, DB/schema post-checku,
GitHub důkazu, restore záznamu nebo výslovně schváleného rozhodnutí. Na konci
každého pracovního bloku aktualizuj SESSION_HANDOFF.md podle jeho „Povinného
kontraktu údržby“: poslední implementační SHA, vlastněné dirty soubory,
migrace/testy/lint, worker/task IDs, důkazy, blokátory a právě jednu další
konkrétní akci.

Nyní proveď resume audit, oznam aktuální rozdíly a pokračuj dalším bezpečným
úkolem uvedeným v SESSION_HANDOFF.md. Nezačínej funkcí z pozdější fáze jen proto,
že je technicky lákavá.
```

## Proč je prompt tak přísný

Nový task dostane kontext ze souborů, ale provozní realita se může od poslední
session změnit. Úvodní audit zabrání práci nad starým commitem, záměně lokální DB
za produkční a přepsání souborů jiného pracovního tasku.
