# Autonomní dokončení priorit 1, 7 a 8 — 7. 11. 2026

ID AUTO01, registr P11 / P14 / P10 / C04. Stav: ROZPRACOVÁNO.
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
| 7 — novější záloha | PLÁNOVÁNO: prověřit existující `production-drills.yml`, spustit `obnovit-zalohu-izolovane` pro nejnovější dostupnou generaci. Uložit název, hash, počty tabulek/řádků/triggerů a úspěšný úklid; neplést se staršími třemi obnovenými generacemi. |
| 8 — větve a WIP | PLÁNOVÁNO: inventura všech místních větví a worktrees, rozlišení ancestry/patch shody po squash, bezpečné otisky rozpracovaných sledovaných souborů. Kandidát k posouzení není povolení smazání. |
| 8 — technické nálezy | PLÁNOVÁNO: prověřit konkrétní kandidáty [starého úklidu](DOZOR_UKLID_2026-10-25.md) proti dnešnímu kódu a testům. Opravit doloženou malou chybu v existujícím rozsahu, nebo zapsat důkaz, proč další změna není potřebná / vyžaduje vlastníka. |
| Společné předání | PLÁNOVÁNO: důkazy, stav a další krok v [protokolu](verification/autonomous-priorities-20261107/RESULTS.md), registru, CURRENT, handoffu a deníku; přes GitHub PR. |

## Průběh a další krok

Vstupní kontrola zabránila třem duplicitním pracím z bodu 1. Další krok je
čtecí kontrola bezpečnosti existující izolované obnovy a konkrétní novější záloha;
souběžně lze dokončit inventuru WIP a dnešní platnosti starých nálezů.

Podklady: [provozní inventura](verification/operations-inventory-20261106/RESULTS.md),
[předchozí obnovy](verification/autonomous-p01-p14-20261106/RESULTS.md),
[pravidla dokumentace](WORK_DOCUMENTATION_POLICY.md).
