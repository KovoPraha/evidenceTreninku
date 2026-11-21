# Aktuální session handoff

Aktualizováno 21. 11. 2026. Audit vyšel z `origin/main` `a4df047d`; při dalším převzetí ověř nový HEAD. Aktuální přehled vydání je [CURRENT_STATE](CURRENT_STATE.md). Starší pracovní checkouty nejsou výchozí verze. [Audit větví a dokumentace](verification/branch-documentation-audit-20261121/RESULTS.md) uvádí ověřený rozsah a meze inventury.

## Co převzít jako další

| Oblast | Skutečný další krok |
| --- | --- |
| Rodičovská pošta AUTH-MAIL-01/02 | Dvě nové obnovy byly přijaty `mail()`; potvrdit přijetí ve dvou schránkách nebo prověřit MTA logy. [Plán a záložní KONCEPT](identity-email-queue.md), [výsledek](verification/auth-mail-reset-20261121/RESULTS.md). |
| KIS-PARENT-LINK-01 | Přihlášeně ověřit registráře a rodiče, včetně dotčeného účtu; [plán](IMPORTOVANE_UCTY_AKTIVACE_2026-11-12.md#kis-parent-link-01--oprávnění-při-propojení-rodiče-21-11-2026), [protokol](verification/parent-link-permissions-20261121/RESULTS.md). |
| TRAINING-PLAN-ACL-01 | V kontrolovaném UAT zkusit plánování správcem a běžným trenérem, bez zásahu do skutečných rozvrhů; [plán](TRAINING-RSVP-AND-MAKEUPS-2026-10-14.md#training-plan-acl-01--plánování-správcem-a-kolize-21-11-2026), [výsledek](verification/training-plan-admin-collision-20261121/RESULTS.md). |
| E4-06 / MAKEUP-AUTO-01 | Zajistit TEST role a nové okno pro celý tok omluva → docházka → nárok → rezervace. Aktivace pravidel vyžaduje samostatné schválení; [plán](TRAINING-RSVP-AND-MAKEUPS-2026-10-14.md), [protokol](verification/coach-attendance-20261113/RESULTS.md). |
| ROSTER-HISTORY-01 / KIS-ACT-01 / SEC01 | Testerka ověří denní a historickou soupisku, rodičovský pilot a soukromý dokument ve svých řízených scénářích. [Inventura](verification/worktree-reconciliation-20261121/RESULTS.md), [pilot](TESTER_KIS_AKTIVACE_RODICU_2026-11-13.md). |
| P02/P04, P05/P07, Packeta | Čekají na skutečné stavy zásob/kasy, objednávku/platbu/PDF a cenu/první zásilku. [BAR předání](SESSION_HANDOFF_BAR_2026-11-06.md), [registr](WORK_PLAN_CURRENT.md). |
| P15 ČSC/Sportsoft | S0 podklady v main; cílová inventura na odděleném počítači chybí. Runtime drafty #376/#380/#382 a audit #386 ponechat k revizi po vstupu; [plán](CSC_SPORTSOFT_READ_SYNC_PLAN_2026-11-07.md). |
| Zdravotní filtr #384 | Draft měření zůstává otevřený; dokončit párové měření a úklid nebo věcně uzavřít po revizi, [plán](MEDICAL_STATE_READ_PERFORMANCE_2026-11-07.md). |

Všech pět otevřených PR je draft; [inventární tabulky](verification/branch-documentation-audit-20261121/RESULTS.md) odlišují otevřený PR od historické větve. Neprovádět hromadné mazání: nepřímý předek `main` nedokazuje chybějící obsah po squash merge.

<!-- membership-morning-publish:start -->
## Ranní serverový výsledek PASS

[Ranní serverový výsledek PASS](verification/membership-scheduler-20261107/RESULTS.md).
Kontrola i přenos běží na serverech, bez zapnutého počítače vlastníka.
Skutečné P05/P07 a export P06 zůstávají odložené.

<!-- membership-morning-publish:end -->
<!-- membership-morning-preflight:start -->
## Serverový preflight

[Serverový preflight](verification/membership-morning-server-20261106/RESULTS.md).
Kontrola i přenos běží na serverech, bez zapnutého počítače vlastníka.
Skutečné P05/P07 a export P06 zůstávají odložené.

<!-- membership-morning-preflight:end -->

Při převzetí načti [AGENTS](../AGENTS.md), [pravidla dokumentace](WORK_DOCUMENTATION_POLICY.md), [registr](WORK_PLAN_CURRENT.md) a dotčený tematický plán. Ověř `git fetch --prune origin`, stav checkoutu, PR a důkazy; cizí WIP zachovej. Změny stavu zapiš do [deníku](EVENT_LOG.md), volby do [rozhodovacího logu](DECISION_LOG.md), úplný rozcestník je [katalog](PLAN_CATALOG.md). [Historický snímek](verification/branch-documentation-audit-20261121/STATE_BEFORE.md) uchovává zkrácené předchozí přehledy.
