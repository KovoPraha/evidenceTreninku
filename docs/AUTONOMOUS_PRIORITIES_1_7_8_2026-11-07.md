# Autonomní dokončení priorit 1, 7 a 8 — 7. 11. 2026

ID AUTO01, registr P11 / P14 / P10 / C04. Stav: HOTOVO pro převzatý rozsah P14/P10/C04 a kontrolu souběhu; bod 1 má původní vlastníky.
Vlastník výslovně zadal ověřit aktuální stav a práci ostatních vláken, nic
neduplikovat, dokončit dostupné kroky bez jeho vstupu a průběžně psát do repa.
Pořadí a závislosti: [registr](WORK_PLAN_CURRENT.md#priority-a-závislosti).

## Pracovní kopie a hranice

- Základ main `8e9cdce1`; větev `codex/autonomous-priorities-1-7-8-20261107`.
- Vlastní worktree `C:/Users/Marek/Documents/Codex/evidencePavel-autonomous-priorities-20261107`.
- Původní webroot i cizí worktrees zachovat; neprovádět reset, clean, hromadný
  merge ani mazání větví, souborů nebo cizích databází.
- Bod 7 je obnova dostupné zálohy pouze do izolovaného dočasného prostředí
  existujícího workflow; produkční DB se neobnovuje. Dumpy ani přístupy nejdou do Gitu.
- P05/P07, P06, heslo/T07, nové finanční aktivace a skutečná lidská přejímka
  zůstávají mimo tento průchod. Starý nález není důkaz dnešní chyby.

## Ověřené rozdělení práce před zahájením

Přečten aktuální main, registr, handoff, otevřené PR a poslední stav tří
souvisejících aktivních vláken. Nasazení lekcí a jeho výsledkové předání
řeší původní výkonové vlákno, výběr osob vlastní [PR #366](https://github.com/KovoPraha/evidenceTreninku/pull/366),
vizuální mobilní karty vlastní původní přístupové vlákno / [PR #365](https://github.com/KovoPraha/evidenceTreninku/pull/365).
Tato relace nepřebírá jejich kód, deploy ani ovládání stejného Chrome.
Doklady se po jejich dokončení převezmou do společného registru.

## Postup a akceptace

| Krok | Stav / přijetí |
| --- | --- |
| 1 — zabránit duplicitám | HOTOVO vstupní kontrola; průběžně sledovat výsledková předání a skutečný main. [Lekce](ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md), [karty](ACCESS_OVERVIEW_2026-11-07.md). |
| 7 — novější záloha | PASS 42376793141: novější generace z 7. 11., ověření SHA, počtů řádků 389 tabulek a 2 triggerů; izolované dočasné prostředí uklizené. [Důkaz](verification/autonomous-priorities-20261107/restore-proof.json). |
| 8 — větve a WIP | HOTOVO snímek 332 větví / 156 worktrees, PR/ancestry/shoda obsahu a otisky WIP. Root WIP nezměněný. 41 větví k posouzení vlastníkem, nikoli automatické smazání. |
| 8 — technické nálezy | HOTOVO: alias/export, blokace ebook a použití fontu ověřené; 14 testů / 289 assertions a 3 anonymní HEAD PASS. Bez doložené nové chyby; žádná zbytečná runtime změna nebo odstranění ochranných testů. |
| Společné předání | HOTOVO obsahově: důkazy, stav a další krok v [protokolu](verification/autonomous-priorities-20261107/RESULTS.md), registru, CURRENT, handoffu a deníku; přes GitHub PR. |

## Průběh a další krok

Vstupní kontrola zabránila třem duplicitním pracím. #369 s výsledky nasazení
je nyní v main, #366 také sloučené (bez nového produkčního povolení). Cílený
Chrome zůstává původnímu vlastníkovi #365. Vlastní P14 a triáž P10/C04 dokončené;
[předání PR #370](https://github.com/KovoPraha/evidenceTreninku/pull/370) určuje skutečný stav CI/merge.

Podklady: [provozní inventura](verification/operations-inventory-20261106/RESULTS.md),
[předchozí obnovy](verification/autonomous-p01-p14-20261106/RESULTS.md),
[pravidla dokumentace](WORK_DOCUMENTATION_POLICY.md).
