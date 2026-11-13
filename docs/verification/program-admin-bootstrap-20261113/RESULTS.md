# P11-ASSET12 — správa programů bez duplicitního Bootstrapu

13. 11. 2026. ROZPRACOVÁNO. [Plán a akceptace](../../BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset12--správa-kroužkových-programů-13-11-2026).
Baseline bc8da209fc469df58f91e27a2fce2340ba47f785. Původní WIP zachovaný; vlastní izolovaný worktree.

Implementace, párové HTTP/Chrome/MariaDB a výkon, CI, merge/deploy: NOT RUN.
Přihlášené produkční UAT: NOT RUN. Další krok implementace a syntetická přejímka.

Implementace přesného odstranění značky a syntaxe/styl: PASS.
Párový profil připravený; lokální PHPUnit do připravení závislostí NOT RUN.
Veřejná produkční baseline: 302 a canonical CSS 200/hash PASS.
Celé HTTP/Chrome, měřený přínos, CI a vydání zatím NOT RUN.


## Průběžný pár před integrací souběžných dokumentů

PR #473 / e6c420a3, [běh 43443460733](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43443460733)
PASS na obou MariaDB: 17 HTTP scénářů, přesná shoda HTML/hlaviček,
355 nezměněných tabulek, ověřený úklid. Chrome: 176 měřených a 48
zahřívacích navigací, čtyři pixelově shodné dvojice. Cold Bootstrap
53 957 → 31 248 B (−42,09 %), warm 0 → 0 B. HTTP přehledu:
10.3 17,637 → 16,780 ms, 11.4 12,936 → 12,944 ms; žádný významný
serverový přínos neprokázán. Místní 23 testů / 172 assertions, styl,
composer validate a docs PASS. Tato čísla jsou průběžná, syntetická.

Převzaty souběžné dokumenty #472 / 784cdc7b včetně blokátoru úplného
UAT náhradních lekcí. Aplikační a měřicí zdroje shodné s e6c420a3;
nový head projde novou celou přejímkou. Konečný CI/merge/deploy čeká.
