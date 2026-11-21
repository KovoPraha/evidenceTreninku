# DOC-BRANCH-AUDIT-01 — soulad dokumentace a větví, 21. 11. 2026

Stav: **inventurní a dokumentační řez HOTOVO; věcné drafty a provozní přejímky zůstávají otevřené**. Vlastník: vývoj KIS. Oprávnění: uživatel požádal o kontrolu skutečného stavu, rozpracovaných větví a zjednodušení dokumentace. Větev `codex/documentation-branch-audit-20261121`, samostatný worktree `evidencePavel-doc-audit-20261121` z `origin/main` `a4df047d`. Závislosti: GitHub PR, deploy workflow, repo plány a výsledkové protokoly. [Registr](../../WORK_PLAN_CURRENT.md#doc-branch-audit-01--soulad-dokumentace-a-větví-21-11-2026).

## Postup, varianty a akceptace

Prověřit čerstvý `origin/main`, otevřené PR, poslední produkční deploy workflow, místní větve a všechny registrované worktrees. Opravit konkrétní rozpory mezi přehledy a doloženým stavem; zachovat původní text přehledů v [historickém snímku](STATE_BEFORE.md). Vydat krátký CURRENT/HANDOFF s odkazy do registru a tematických důkazů. Vyhodnotit archivaci větví bez ztráty práce. Varianta okamžitého hromadného mazání odmítnuta: samotná ancestry neodhalí squash merge ani nepřevzatý obsah; přihlášené UAT a externí podklady nelze uzavřít úpravou Gitu. Akceptace: čitelné aktuální vstupy, úplná inventura refs/worktrees, zdůvodněná dispozice otevřených PR, kontrola dokumentace a diffu.

## Ověřený stav

| Kontrola | Výsledek |
| --- | --- |
| `git fetch --prune origin`, `origin/main` | PASS; při inventuře `a4df047d`, pozdější než původní checkout `622ade9d`. |
| GitHub PR | PASS; otevřených **5**, všechny draft. PR #508 a #510–#513 jsou sloučené; poslední tři jsou dokumentační záznamy vydání. |
| Produkční workflow | Poslední ověřený SUCCESS [44912234389](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44912234389) na `7d856ed6`; po něm jsou #511–#513 pouze dokumentace. Skutečný serverový SHA mimo workflow NOT RUN. |
| Větve | [Snímek refs](branches.csv): 478 místních a 437 `origin` refs. Z místních: 424 názvově spárovaných se sloučeným PR, 11 se zavřeným nesloučeným PR, 30 bez nalezeného PR, 9 dalších headů přímo v main a 4 otevřené drafty. Pátý otevřený draft #386 má jen remote ref. Z místních commitů je 10 přímým předkem `origin/main`, 468 nikoli. To **neznamená 468 chybějících změn**; historie používá PR/squash. |
| Worktrees | [Snímek worktrees](worktrees.csv): 207 registrovaných, 207 s nulou změněných sledovaných souborů, 0 chyb `git status --porcelain -uno`. Neversionované soubory a cizí soukromé výstupy nejsou zahrnuté ani měněné. |
| Dokumentace | CURRENT a HANDOFF měly přesně 80 řádků s dlouhými historickými odstavci. V katalogu E4-06 stálo chybně, že #474 je návrh a deploy je otevřený; [vydání](../coach-attendance-20261113/RESULTS.md) je doložené. DOC-WIP-01 označoval #505 jako připravený, ačkoli je sloučený. Opraveno v tomto řezu. |

## Dispozice pěti otevřených PR

| PR | Skutečný rozsah a rozhodnutí |
| --- | --- |
| [#376](https://github.com/KovoPraha/evidenceTreninku/pull/376) | P15 S1 čtecí ingest, závisí na S0 inventuře cílové stanice. Draft ponechat; merge/deploy/aktivace NOT RUN. |
| [#380](https://github.com/KovoPraha/evidenceTreninku/pull/380) | P15 S4 registrářský přehled navazující na #376. Draft ponechat do revize aktuálního main a kontraktu; merge/deploy/aktivace NOT RUN. |
| [#382](https://github.com/KovoPraha/evidenceTreninku/pull/382) | P15 panel osoby a audit rozhodnutí, obsahuje stack #376/#380. Draft ponechat do rozhodnutí o celém řetězci. |
| [#386](https://github.com/KovoPraha/evidenceTreninku/pull/386) | Dokumentační bezpečnostní audit #380; jeho vlastní výsledek není v main. Vyhodnotit s #380 a případný LOW nález ověřit proti aktuálnímu bootstrapu. |
| [#384](https://github.com/KovoPraha/evidenceTreninku/pull/384) | Nezávislé měření zdravotního filtru, v PR je rozpracovaný test a protokol; párové měření a úklid nebyly doložené. Draft ponechat do dokončení nebo věcné revize. |

Žádný z pěti PR není doklad nasazení. Ostatní místní/remote větve zůstávají v [CSV](branches.csv) jako archivní inventura k jednotlivé obsahové kontrole. Párování s PR je podle názvu head větve; zejména u 424 sloučených PR ještě není prokázáno, že pozdější head větve neobsahuje nové commity. Jedenáct zavřených nesloučených a 30 větví bez PR vyžaduje při skutečném mazání individuální dispozici. `commit_in_main` je bezpečně určena historií; absence ancestry sama nestačí k merge nebo mazání. Worktree existence neznamená běžící úkol. Žádná větev ani worktree nebyly tímto auditem smazány.

## Otevřené věcné hranice a další krok

Přihlášené UAT KIS-PARENT-LINK-01, TRAINING-PLAN-ACL-01, E4-06/MAKEUP-AUTO-01, ROSTER-HISTORY-01, KIS-ACT-01 a SEC01 je samostatná provozní/lidská přejímka. Doručení dvou obnov do schránek není potvrzené. BAR/P05/P07/Packeta potřebují skutečné vstupy od vlastníka. Tyto položky zůstávají v [registru](../../WORK_PLAN_CURRENT.md), neuzavírají se administrativním úklidem. Následující technický krok je jednotlivě porovnat obsah historických větví, pokud mají být odstraněny; prioritně vyřešit #384 a P15 řetězec po cílové inventuře. Historické dokumentační PR již převzaté v #505 nejsou otevřené.

## Ověření tohoto řezu

`php tools/check-current-docs.php`: PASS, 0 chyb. `git diff --check`: PASS. Kontrola textu a odkazů: PASS v rozsahu dokumentačního checkeru; úplná věcná revize všech historických plánů NOT RUN. [PR #514](https://github.com/KovoPraha/evidenceTreninku/pull/514): první PHPUnit běh FAIL v `CurrentDocumentationContractTest` kvůli chybějícímu historickému release odkazu ve zkráceném CURRENT. Odkaz, SHA, run ID a klubové rozhodnutí doplněny s výslovným označením historického vydání. Finální 11/11 CI PASS, sloučeno do main jako `cdf57d76`; produkční deploy NOT RUN, dokumentační změna jej nevyžaduje.
