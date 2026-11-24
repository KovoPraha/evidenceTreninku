# Průběžný plán, registr rozpracované a plánované práce

## KIS-ACCESS-STATUS-01 — aktivace a stavy členství (24. 11. 2026)

**KONCEPT / čtecí analýza**, bez oprávnění ke změně kódu, účtů či produkce. Vlastník zadání Marek / vedení klubu, vývoj KIS; větev `codex/account-membership-concept-20261124`, oddělený worktree z `origin/main` `b8830ea7`, dokumentace v [draft PR #527](https://github.com/KovoPraha/evidenceTreninku/pull/527), bez merge. [Zjištění, varianty a akceptace](ACCOUNT_ACTIVATION_AND_MEMBER_STATUS_2026-11-24.md) navazují na KIS-ACT-01 a bezsezonní členství. Vlastník [potvrdil](DECISION_LOG.md#d2026-11-24-05--aktivní-členství-před-úhradou-první-faktury) aktivní členství a soupisku před úhradou první faktury a [uznal všechny současné e-maily](DECISION_LOG.md#d2026-11-24-06--současné-e-mailové-adresy-v-databázi-jsou-ověřené). Kód již dává soupisku a předpis, obecný štítek člena však neaktualizuje; vypnuté importované účty reset stále odmítá. Doloženo čtením kódu; produkční počty a lidské UAT NOT RUN. Závislosti: přesný přechodový snímek, inventura adres/účtů/vazeb, spolehlivá pošta a tolerance plateb. Další krok: čtecí inventura a návrh prvního hesla pro importované účty a sportovce bez účtu, plus zbývající pravidla jednorázového kroužku, dluhu a archivu. Implementace až po schváleném plánu.

## AUTH-MAIL-05 — společná administrace e-mailů (24. 11. 2026)

NASAZENO, PŘIHLÁŠENÉ UAT A DORUČENÍ OTEVŘENÉ. Vlastník Marek/klub výslovně schválil implementaci a nasazení; vývojová větev `codex/auth-mail-admin-plan-20261124`, [PR #524](https://github.com/KovoPraha/evidenceTreninku/pull/524) → `2cae19d6`, [deploy 45396452344](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45396452344) SUCCESS. [Plán a akceptace](identity-email-queue.md#auth-mail-05--společný-přehled-pošty-kontrolní-kopie-a-bezpečné-opakování-24-11-2026), [výsledek](verification/auth-mail-unified-20261124/RESULTS.md), [návod pro testerku](TESTER_AUTH_MAIL_2026-11-24.md). Audit registrace/ověření/resetu, přehled šesti front, bezpečné opakování a dočasná [skutečná BCC](DECISION_LOG.md#d2026-11-24-02--administrátorská-skrytá-kopie-pro-testování) jsou v produkci; BCC výchozně vypnutá. Čtecí [status 45397914894](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45397914894) potvrzuje úspěšný cron a rodičovské `pending=0`, `failed=0`, `sent=12`, nikoli doručení do schránky. Další krok: přihlášená kontrola stránky a kontrolní schránky, konkrétní forenzní dohledání hlášené registrace. Lidská přejímka NOT RUN.

## BAR-PHOTO-SCREEN-01 — fotografie barové obrazovky (23. 11. 2026)

KÓD NASAZEN, TV UAT OTEVŘENÉ: [#521](https://github.com/KovoPraha/evidenceTreninku/pull/521) + [#522](https://github.com/KovoPraha/evidenceTreninku/pull/522) → `7918ca05`, [deploy 45233456861](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45233456861) SUCCESS. Vlastník hlásil prázdné fotografické karty po migraci. Vývoj BAR: `codex/bar-photo-screen-20261123`, `codex/bar-photo-screen-preload-20261123`; dokumentace vydání `codex/bar-photo-screen-release-20261123` ve vlastním worktree. [Nález, rozsah a akceptace](BAR_DISPLAY_STOCK_PARITY_2026-11-10.md#bar-photo-screen-01--chybějící-fotografie-na-živé-obrazovce-23-11-2026), [výsledky](verification/bar-photo-screen-20261123/RESULTS.md). Živé malé obrázky a nový JS PASS, místní přednačtení s 3s zpožděním PASS. Dlouhé živé měření rotace nedokončeno. Další krok: skutečná TV přejímka.

## BAR-CHECKOUT-TABLET-01 — kompaktní pokladna baru (23. 11. 2026)

KÓD NASAZEN, přihlášené UAT otevřené: [PR #519](https://github.com/KovoPraha/evidenceTreninku/pull/519) sloučený jako `e647b7bf`, [deploy 45156963794](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45156963794) SUCCESS. Vlastník požaduje vidět na tabletu více produktů pokladny. Rozsah: rozvržení `/bar/checkout.php`, bez změn prodejní logiky a dat. Vlastník vývoj BAR; implementace `codex/bar-checkout-tablet-20261123`, dokumentace vydání `codex/bar-checkout-tablet-release-20261123` v odděleném worktree. [Plán a akceptace](BAR_INTEGRATION_PLAN_2026-11-05.md#bar-checkout-tablet-01--kompaktní-pokladna-na-tabletu-23-11-2026), [výsledek](verification/bar-checkout-tablet-20261123/RESULTS.md). Syntetický prohlížeč: 768 × 1024 z 7 na 17 viditelných řádků, 1024 × 768 z 5 na 12; 44px pole počtu. Další krok: přihlášená přejímka obsluhou na skutečném tabletu.

Aktualizováno 24. 11. 2026, Europe/Prague. Produkční stav určují výsledkové protokoly a [CURRENT_STATE](CURRENT_STATE.md). [Předchozí revize](verification/documentation-sync-20261112/RESULTS.md).
Tento soubor průběžně eviduje práci a další kroky; živé SHA/flags a release důkazy
jsou pouze v [CURRENT_STATE](CURRENT_STATE.md). Převzetí řídí
[HANDOFF_CURRENT](HANDOFF_CURRENT.md), datované podklady
[provozní inventura](verification/operations-inventory-20261106/RESULTS.md).

## AUTH-MAIL-04 — dohledatelnost požadavku na obnovu hesla (22. 11. 2026)

KÓD NASAZEN, přihlášené UAT otevřené. Vlastník vývoj KIS; implementace `codex/auth-mail-reset-request-audit-20261122` v [PR #517](https://github.com/KovoPraha/evidenceTreninku/pull/517) sloučená jako `641493a6`, dokumentační předání `codex/auth-mail-reset-request-audit-release-20261122` ve vlastním worktree `C:\Users\Marek\Documents\Codex\evidencePavel-auth-mail-repair-20261121`. [Příčina, varianty a akceptace](identity-email-queue.md#auth-mail-04--dohledatelnost-požadavku-na-obnovu-hesla-22-11-2026), [výsledky](verification/auth-mail-request-audit-20261122/RESULTS.md). Rozsah: POST formulář, vyhledání účtu, CSRF/limit/validace, atomické vložení a bezpečná reference v administraci; 38 cílených testů a finální CI 13/13 PASS. Vlastník výslovně schválil merge/deploy; [chráněné vydání](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44972109908) PASS. Závislosti pro přejímku: přihlášení správce, kontrolní rodičovský účet a schránka, rozhodnutí o retenci. Další krok: dohledat syntetickou referenci, ověřit oprávněný reset/doručení na kontrolním účtu; historický pokus bez reference zůstává neurčen.

## DOC-BRANCH-AUDIT-01 — soulad dokumentace a větví (21. 11. 2026)

Inventurní a dokumentační řez [PR #514](https://github.com/KovoPraha/evidenceTreninku/pull/514) sloučený jako `cdf57d76` po 11/11 CI PASS; věcné drafty a provozní přejímky otevřené. Vlastník vývoj KIS; větev `codex/documentation-branch-audit-20261121`, samostatný worktree z `origin/main` `a4df047d`. [Postup, varianty, inventář a výsledky](verification/branch-documentation-audit-20261121/RESULTS.md). Rozsah oprávnění: čtecí audit, oprava rozporů, zkrácení CURRENT/HANDOFF a dokumentační PR; bez mazání větví či produkčních zápisů. Závislosti: aktuální PR/deploy, obsah historických větví, TEST role a skutečné provozní podklady. Další krok: #384 a P15 drafty vyřešit jednotlivě po věcném ověření. Žádný z pěti věcných draft PR není nasazený. Z 478 místních větví je 424 spárováno se sloučeným PR podle názvu, 11 se zavřeným nesloučeným PR a 30 bez PR; archivní CSV samo nedokazuje obsahové převzetí.

## DOC-BRANCH-CLOSE-01 — rozhodnutí o pěti draftech (21. 11. 2026)

**KONCEPT, nikoli schválené zavření PR.** Vlastník produktové volby Marek/klub; příprava vývoj KIS. Větev `codex/pr-closeout-concept-20261121`, worktree `evidencePavel-doc-audit-20261121` z `origin/main` `d94203a4`. [Varianty, dispozice každého PR, akceptace a závislosti](verification/branch-documentation-audit-20261121/RESULTS.md#koncept-doc-branch-close-01--rozhodnutí-o-pěti-draftech-21-11-2026). P15 #376/#380/#382/#386 závisí na S0 inventuře a volbě pokračovat/odložit; #384 je doplňkové měření již vydané optimalizace. Další krok: určit prioritu P15 a měření #384, potom věcně zachovat unikátní výsledky a uzavřít či obnovit jednotlivé PR. Bez merge, deploye, aktivace a mazání větví.

## KIS-PARENT-LINK-01 — propojení rodičovského účtu (21. 11. 2026)

KÓD NASAZEN: [PR #507](https://github.com/KovoPraha/evidenceTreninku/pull/507) sloučen jako `7d856ed6`; [deploy 44912234389](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44912234389) SUCCESS na stejném SHA, 11/11 CI, 2538/60410 v release. Vlastník vývoj KIS; implementační větev `codex/parent-link-permissions-20261121`, dokumentační větev `codex/parent-link-release-20261121`. [Nález, rozsah a akceptace](IMPORTOVANE_UCTY_AKTIVACE_2026-11-12.md#kis-parent-link-01--oprávnění-při-propojení-rodiče-21-11-2026), [výsledky](verification/parent-link-permissions-20261121/RESULTS.md). Další krok: řízené přihlášené UAT registráře a rodiče; skutečný účet č. 453 nebyl měněn. UAT/import false, lidská přejímka NOT RUN.
## TRAINING-PLAN-ACL-01 — správce a kolize plánovaných tréninků (21. 11. 2026)

KÓD NASAZEN, přihlášené UAT otevřené. [Rozsah a akceptace](TRAINING-RSVP-AND-MAKEUPS-2026-10-14.md#training-plan-acl-01--plánování-správcem-a-kolize-21-11-2026), [výsledky](verification/training-plan-admin-collision-20261121/RESULTS.md). Vlastník vývoj KIS; implementace `codex/training-plan-admin-collision-20261121` v [PR #508](https://github.com/KovoPraha/evidenceTreninku/pull/508), vydaná jako `6dcd0a20` v [deploy 44910511209](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44910511209) SUCCESS; release evidence `codex/training-plan-admin-release-20261121`. Závislosti: plánovací mutex, audit a KIS soupisky. Finální CI 21/21; UAT/import false. Další krok přihlášené UAT správce a běžného trenéra v kontrolovaném prostředí; skutečné organizační potvrzení a lidská přejímka NOT RUN.

## AUTH-MAIL-02 — třídenní obnova a náhrada dvou odkazů (21. 11. 2026)

K OVĚŘENÍ DORUČENÍ. Vlastník vývoj KIS; implementace `codex/auth-mail-reset-72h-reissue-20261121` v [PR #510](https://github.com/KovoPraha/evidenceTreninku/pull/510), výsledková dokumentace `codex/auth-mail-reset-release-proof-20261121` v [PR #511](https://github.com/KovoPraha/evidenceTreninku/pull/511). [Plán a akceptace](identity-email-queue.md#auth-mail-02--třídenní-obnova-a-náhrada-dvou-starých-odkazů-21-11-2026), [produkční protokol](verification/auth-mail-reset-20261121/RESULTS.md). 72h reset nasazen; čtecí inventura potvrdila dva oprávněné cíle, jednorázová operace vytvořila dvě nové zprávy a přirozený tick obě předal `mail()`. Rodičovská fronta `pending=0`, `failed=0`. Další krok: potvrdit skutečný příjem v obou schránkách, případně prověřit mail/MTA logy. Doručení do schránky NOT RUN.

## AUTH-MAIL-03 — záložní provoz rodičovské pošty (21. 11. 2026)

KONCEPT podle návrhu vlastníka mít backup režim. Vlastník návrhu vývoj KIS; větev `codex/auth-mail-reset-release-proof-20261121`, oddělený worktree z `origin/main` `ccac61ab`. [Varianty, rozsah, rizika a akceptace](identity-email-queue.md#auth-mail-03--záložní-provoz-rodičovské-pošty-21-11-2026). První etapa je nezávislý čtecí dohled nad heartbeatem a splatnou frontou s řízeným záložním spuštěním existujícího workeru; druhá etapa případný alternativní transport až po důkazu potřeby a bezpečném pravidlu přepnutí. Není schválen poskytovatel ani aktivace automatického failoveru. Další krok: potvrdit doručení současného transportu a určit kanál provozního upozornění; potom připravit první etapu.

## AUTH-MAIL-01 — rodičovské ověřovací a obnovovací e-maily (21. 11. 2026)

AUTOMATIKA NASAZENA A AKTIVNÍ; doručení do schránky otevřené. Vlastník vývoj KIS; implementace `codex/auth-mail-worker-repair-20261121` v [PR #506](https://github.com/KovoPraha/evidenceTreninku/pull/506), výsledky `codex/auth-mail-release-proof-20261121` v odděleném worktree. [Příčina, plán a akceptace](identity-email-queue.md#auth-mail-01--oprava-automatického-zpracování-rodičovské-fronty-21-11-2026), [produkční protokol](verification/auth-mail-worker-20261121/RESULTS.md). Čtecí produkční stav potvrdil chybějící plán, 3 splatné zprávy a připravený transport; po instalaci přirozený běh vyprázdnil frontu: 1 `verify` přijal `mail()`, 2 neplatné staré `reset` zrušeny, `failed=0`, klubové zprávy nedotčeny. Druhý tick potvrdil pravidelný běh. Další krok: řízené doručení obou typů do TEST schránky, případně provozní mail/MTA logy; náhradní odkazy byly vydány v AUTH-MAIL-02 a přijaty transportem; ověřit příjem. Skutečné doručení NOT RUN.

## ROSTER-HISTORY-01 — výchozí seznam členů a historie (21. 11. 2026)

KÓD NASAZEN, přihlášené UAT otevřené. [Rozsah, rozhodnutí a akceptace](MEMBER_EVIDENCE_IMPLEMENTATION_2026-11-02.md#roster-history-01--výchozí-soupiska-a-historie-členství-21-11-2026), [PR #502](https://github.com/KovoPraha/evidenceTreninku/pull/502) → `85a81986`, 11 CI PASS; [deploy 44896953740](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44896953740) SUCCESS na stejném SHA, záloha/migrace/VS/HTTP PASS, UAT/import false. [Dokumentační #503](https://github.com/KovoPraha/evidenceTreninku/pull/503) sloučený. Vlastník vývoj KIS; `codex/roster-membership-history-20261121`. Výchozí seznam používá časovou platnost, ostatní intervaly jsou v rozbalovací historii; 22 cílených testů PASS. Další krok: testerka v přihlášené administraci ověří dnešek, poslední platný den a dřívější den, včetně nadřazené skupiny.

## WIP-AUDIT-01 / SEC01 — převzetí historických checkoutů (21. 11. 2026)

ČTECÍ INVENTURA HOTOVÁ, SEC01 NASAZENO. [Přesná inventura a dispozice sedmi stromů](verification/worktree-reconciliation-20261121/RESULTS.md), [bezpečnostní audit](SECURITY_AUDIT_2026-11-08.md). Vlastník vývoj KIS; [PR #504](https://github.com/KovoPraha/evidenceTreninku/pull/504) po 11 CI PASS sloučený jako `11505aed`, [deploy 44899472344](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44899472344) SUCCESS na stejném SHA, záloha/migrace/VS/HTTP PASS, UAT/import false. Konfliktní #391 uzavřen jako nahrazený. Původní hlavní checkout byl na 399 commitů staré Fio větvi; její zastaralá lokální změna a další historické patche jsou lokálně archivované. Všech 202 worktrees má nyní 0 změněných sledovaných souborů; soukromé a neversionované výstupy zachované. Fio, BAR sklad a W85 byly dříve vydané. Další krok: přihlášená přejímka ROSTER-HISTORY-01 a provozní kontrola soukromého souboru; P02/P05/P07/Packeta čekají na podklady vlastníka, které 21. 11. přislíbil dodat později. Ostatní staré PR se řeší jednotlivě podle inventury.

## DOC-WIP-01 — převzetí starých dokumentačních PR (21. 11. 2026)

Z konfliktních #399, #401, #402, #417 a #371 byly věcné výsledky, přejímací scénáře, syntetické důkazy a kontrakt P15 S0 převzaty do [sloučeného PR #505](https://github.com/KovoPraha/evidenceTreninku/pull/505) jako `5acd225f`. Původní konfliktní PR již nejsou otevřené. Historické společné CURRENT/HANDOFF z jejich větví nebyly převzaty. [Protokol převzetí](verification/worktree-reconciliation-20261121/RESULTS.md) rozlišuje vydaný kód a další NOT RUN. Dokumentační merge nespustil nové nasazení ani provozní aktivaci; navazující P15 runtime drafty jsou v [aktuální inventuře](verification/branch-documentation-audit-20261121/RESULTS.md).

## P15 — čtecí ČSC / Sportsoft a oddělená stanice

S0 DOKUMENTACE PŘEVZATA, cílová inventura BLOKOVÁNA vstupem z druhého Windows počítače. [Plán](CSC_SPORTSOFT_READ_SYNC_PLAN_2026-11-07.md), [kontrakt](CSC_SPORTSOFT_S0_CONTRACT_V1.md), [stanice a ochrana soukromých dat](CSC_SPORTSOFT_S0_STATION.md), [výsledek S0](verification/csc-sportsoft-s0-20261107/RESULTS.md). Původní [#371](https://github.com/KovoPraha/evidenceTreninku/pull/371) měl konflikty ve společných dokumentech; návrhy runtime v #376/#380/#382 a audit #386 zůstávají drafty. Neproběhla inventura cílové stanice, instalace workeru, ingest ani produkční aktivace. Další krok: místní Codex na cílovém počítači provede pouze [čtecí inventuru podle promptu](verification/csc-sportsoft-s0-20261107/STATION_PROMPT.md), doplní Sportsoft scope a negativní platební mapu; teprve potom lze posoudit navazující drafty.

## E4-06 — docházka trenéra z rozvrhu (13. 11. 2026)

KÓD NASAZEN: [PR #474](https://github.com/KovoPraha/evidenceTreninku/pull/474) → `ea995408`, [deploy 43651304138](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43651304138) SUCCESS; 21/21 CI, záloha, migrace a HTTP 200 PASS, `uat_schvaleno=false`, import přeskočen. [Audit a převzetí požadavku testerky](COACH_ATTENDANCE_2026-11-13.md), [technické a vizuální důkazy](verification/coach-attendance-20261113/RESULTS.md). Trenérský zápis, omluvy, rezervovaní náhradníci, verzované opravy, historie dítěte a mobilní rozhraní jsou vydané. Místní HTTP tok náhrady 31/31 a izolovaná vizuální rezervace automatického cíle i zápis běžnou rolí `trener` PASS. **Produkční přihlášené UAT E4-06 a MAKEUP-AUTO-01 NOT RUN.** [Čtecí prověrka 43654981616](verification/coach-attendance-20261113/RESULTS.md#čtecí-prověrka-produkčního-uat-15-11-2026) vrátila `ready=false`; zkusmé okno bylo vráceno bez produkčních zápisů. Další krok: v nově schváleném okně ověřit trenérskou dostupnost, skutečnou absenci, nárok a rodičovskou rezervaci pomocí TEST rolí; pravidla a automatický režim aktivovat až po samostatném schválení.

## MAKEUP-AUTO-01 — automatické náhradní lekce (12. 11. 2026)

**KÓD NASAZEN; místní syntetický celý tok PASS, produkční UAT otevřené.** Automatický režim je výchozí vypnutý.
[Technická analýza, etapy, migrace, rizika a akceptace](TRAINING-RSVP-AND-MAKEUPS-2026-10-14.md#makeup-auto-01--automatické-cíle-a-termíny-12-11-2026).
Vlastník vývoj KIS; vedení KOVO potvrzuje věkové hranice a centrální pravidla.
Větev `codex/makeup-auto-20261112`, oddělený worktree z `origin/main` `4b9bcb1a`.
[PR #459](https://github.com/KovoPraha/evidenceTreninku/pull/459) sloučen jako `428a2258`;
[deploy 43269836068](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43269836068) SUCCESS, aktivace automatiky NOT RUN; runtime obsahuje migraci,
administrace, rodičovské a trenérské stránky. [Průběžné testy a meze](verification/makeup-auto-20261112/RESULTS.md).
CI 43248400672: 2460 testů / 56453 assertions, MariaDB 10.3/11.4 včetně
souběhu PASS; PHPStan/styl, docs a CodeQL PASS. Deploy: záloha 389/2,
migrace current/pending [], VS shodné, HTTP 200; `uat_schvaleno=false`,
`importovat_krouzky=false`. Další krok: čtecí kontrola nastavení a rolové UAT.
Aktivace skutečných
nabídek závisí na schválených kategoriích, lhůtách a kapacitách.
Podle hlášení vlastníka 13. 11. se omluvenka rodiči zobrazovala jako čekající,
ale trenér neměl dostupný zápis docházky konkrétní lekce. Následný samostatný
test na izolovaných účtech prošel přes přihlášeného rodiče a trenérskou pozici:
omluva → čekání → uzavřená skutečná absence → právě jeden nárok → rodičovské
zobrazení → automatický cíl a rezervace. **Nejde o produkční ani lidskou
přejímku.** Zápis provedl účet s legacy rolí `admin` a pozicí `coach`;
po přepnutí na `trener` prošel nový login a čtení, nikoli nový zápis.
Na skutečném nasazení zbývá ověřit dostupnost trenérské cesty,
nastavení a pravidla před aktivací; lokální souborový úklid je částečný.
[Blokátor a akceptace](TRAINING-RSVP-AND-MAKEUPS-2026-10-14.md#blokující-závislost-úplného-funkčního-ověření-13-11-2026),
[výsledek a úklid](verification/makeup-attendance-e2e-20261113/RESULTS.md).
Po novém merge/deploy/provozní operaci stav znovu ověřit, nikoli převzít z názvu větve.

ROSTER-SCROLL-01 — NASAZENO, PŘIHLÁŠENÉ UAT OTEVŘENÉ: [pozice správy soupisek](ROSTER_SCROLL_POSITION_2026-11-12.md), zadání a povolení nasazení 12. 11. 2026. Vlastník vývoj KIS. [PR #462](https://github.com/KovoPraha/evidenceTreninku/pull/462) sloučen jako `22d151f7` po 11 CI PASS; [deploy 43320369018](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43320369018) SUCCESS, UAT/import false. [Syntetické a technické ověření](verification/roster-scroll-20261112/RESULTS.md) PASS. Další krok: registrář ověří opakované akce v dlouhém seznamu na počítači a mobilu podle [scénáře S01–S07 pro testerku](TESTER_ROSTER_SCROLL_2026-11-13.md).
Jde o jediný průběžný seznam práce, včetně konceptů a budoucích etap; [WORK_REGISTER](WORK_REGISTER.md)
odkazuje sem. Sjednocení #333/#334 dokládá [protokol](verification/documentation-reconcile-20261106/RESULTS.md).
Následné zadání vlastníka 6. 11.: pokračovat plně autonomně přes P01–P14.
Aktivní průchod a bezpečně doložené výsledky vede [protokol](verification/autonomous-p01-p14-20261106/RESULTS.md).
Dřívější dokumentační rozsah je historický; chybějící skutečné podklady se nedoplňují odhadem.

## KIS-ACT-01 — první heslo importovaných rodičů (12. 11. 2026)

KÓD NASAZEN; pilot a rodičovské UAT otevřené. Vlastník 12. 11. autorizoval vydání kódu a migrace z PR #463; [deploy 43322186478](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43322186478) SUCCESS na `373b8d89`, UAT/import false. [Rozsah](IMPORTOVANE_UCTY_AKTIVACE_2026-11-12.md), [protokol](verification/parent-activation-release-20261112/RESULTS.md), [scénář pro testerku](TESTER_KIS_AKTIVACE_RODICU_2026-11-13.md). Pozvánky a změny vazeb vyžadují zvláštní provozní schválení.

Vlastník vývoj; identitu, text pozvánky a pilot schvaluje vedení TJ KOVO Praha. Vývojová větev `codex/parent-account-activation` v izolovaném worktree. Cílené syntetické testy a plné PR CI PASS; produkční počty, přihlášené rodičovské UAT a doručení pozvánek NOT RUN. Další krok: schválení konkrétní malé dávky a její provozní přejímka.

Předání: [PR #463](https://github.com/KovoPraha/evidenceTreninku/pull/463) sloučen, poslední společný strom `bdece243` měl 11 CI PASS včetně obou MariaDB; záloha, migrace a živé anonymní GET ověřeny v [release protokolu](verification/parent-activation-release-20261112/RESULTS.md). Vydání neobsahovalo provozní odeslání.

## BAR-DISPLAY-STOCK — oprava obrazovek a převod zásob (10. 11. 2026)

TECHNICKY HOTOVO, vizuální a lidská přejímka OTEVŘENÁ. [Plán a akceptace](BAR_DISPLAY_STOCK_PARITY_2026-11-10.md), [výsledek a bezpečné důkazy](verification/bar-display-stock-20261110/RESULTS.md). #440/#443 sloučené, TV nasazené a 68 produktů / oba sklady přesně převedené; součet stánku 1073 / předskladu 0, včetně pěti záporných stavů. Nezávisle 0 rozdílů, 44 starých URL i oba odlišné TV cíle PASS. Vlastník vývoj / vedení baru; souborové předání `codex/bar-display-stock-release-20261110`, původní vlastní worktree, cizí WIP zachovaný. Další krok: fyzická inventura v KIS a skutečné P02/P04; vizuální kontrola po obnovení Computer Use. Počáteční převod ani publikaci neopakovat, starý BAR znovu nesynchronizovat.

## Úplný katalog plánů

TESTER-20261110 — NASAZENO, PŘIHLÁŠENÉ UAT OTEVŘENÉ: [řazení objednávek a maskovaná kontrola registrace](verification/tester-orders-registration-20261110.md), #438 `f39f3157`, [společné vydání](verification/coach-group-rosters-20261110/RESULTS.md). Ověřit smíšené platby, masku, audit a zákaz přístupu běžného trenéra pod příslušnými účty.

COACH-ROSTER-01 — NASAZENO, PŘIHLÁŠENÉ UAT OTEVŘENÉ: [trenérské skupiny a soupisky](COACH_GROUP_ROSTERS_2026-11-10.md), #436 `4e3df207`; [syntetické a produkční technické ověření](verification/coach-group-rosters-20261110/RESULTS.md) PASS. Pověřený trenér a registrář mají ověřit skutečné Aligátory po schválení a přidělení přímého grantu.

QR01 — implementace a release hotové; živé UAT otevřené: [verze podmínek rychlé přihlášky](QUICK_REGISTRATION_TERMS_2026-11-09.md), vlastník vývoj; #416/#419 sloučené, `e3f2990` nasazené, [20/395 místních kontrol a veřejné čtení produktu 298 PASS](verification/quick-registration-terms-20261109/RESULTS.md). Další krok vlastníka/testera: přihlášené rodičovské UAT se skutečnou objednávkou a platbou podle provozního scénáře.

[Katalog všech plánů a konceptů](PLAN_CATALOG.md) je součást tohoto registru:
každý dokument má vlastní stabilní ID, stav, vazbu na P/C/DOC a důkaz nebo
výslovně otevřenou podmínku. [Úplný index textových podkladů](PLAN_DOCUMENT_INDEX.md)
zahrnuje i vložené ověřovací plány, historické prompty a podpůrné artefakty.
[Původní etapy 0–6 a R01–R44](plans/realization-20261022/README.md) jsou nyní
sdílené v Gitu; jejich historické „plánováno“ nepřebírat jako dnešní stav.

## UX01 — celoroční nabídka stávajícího kroužku

Stav: KÓD SLOUČENÝ A NASAZENÝ; nové nabídky a přihlášené UAT otevřené. [PR #395](https://github.com/KovoPraha/evidenceTreninku/pull/395) sloučen jako `1c828fbf`; [deploy 42492821311](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42492821311) SUCCESS na stejném SHA. Vlastník: vývoj; ceny a zveřejnění určuje vedení klubu. [Plán a postup testerky](club-programs.md#postup-pro-testerku-po-nasazení), [dobový release důkaz](verification/year-offer-release-20261108/RESULTS.md), [rozhodnutí](DECISION_LOG.md#d2026-11-08-01). Další krok: přihlášené čtecí UAT a schválení cen před tvorbou či zveřejněním nabídek.

## Q2-TIME — ochrana rozepsaného tréninku

NASAZENO / technické ověření PASS, cílené přihlášené UAT 3/3 PASS. Vlastník vývoj; [plán a akceptace](QUALITY_NEXT_SLICES_2026-11-08.md#q2-time--odolný-zápis-tréninku-9-11-2026), [release](verification/training-form-resilience-release-20261109/RESULTS.md), [produkční UAT a meze](verification/training-form-uat-20261109/RESULTS.md). [PR #412](https://github.com/KovoPraha/evidenceTreninku/pull/412) sloučený; 11 CI / 2409 testů, 6 HTTP a shoda produkčního JS PASS. Přihlášený pokus 9. 11. ověřil chybný čas, reload a znovuotevření konceptu. Platný submit a potvrzení odstranění vlastního TEST konceptu NOT RUN; další krok je ověřit jen tento konkrétní úklid a případně širší scénáře.

## KONCEPT Q01 — další vývoj: doladění, úklid, příprava (8. 11. 2026)

Vlastník: vývoj; produktové a provozní přijetí: vlastník klubu a dotčené role.
Stav: **návrh pořadí**, nikoli souhlas s platbou, importem, změnou práv,
nasazením nebo smazáním WIP. Podklad: `origin/main` `35fdad44`, tento registr,
[aktuální stav](CURRENT_STATE.md), [předání](HANDOFF_CURRENT.md) a otevřené PR
k 8. 11. Ověření bylo čtecí; žádná funkce tímto návrhem není implementována.
Větev `codex/quality-roadmap-20261108`, samostatný worktree mimo `htdocs`.
Pokyn vlastníka 8. 11. schválil autonomní pokračování dle tohoto pořadí.
Q1 je nyní **částečně ověřeno** po přihlášení vlastníka: role, docházka,
plánovač a zdravotní filtry mají [přesné výsledky](verification/quality-uat-20261108/RESULTS.md).
Při 360 px je reprodukovaná vada plánovače; zbytek úplného UAT není uzavřený.
Dokumentační převzetí Q1 je sloučené v [#388](https://github.com/KovoPraha/evidenceTreninku/pull/388),
původní #365/#378 uzavřené po převzetí jejich obsahu. Q2 opravuje uvedenou
vadu; Q3 je sloučený v [#389](https://github.com/KovoPraha/evidenceTreninku/pull/389)
a Q4/Q5 mají [připravený podklad](QUALITY_NEXT_SLICES_2026-11-08.md).

### Q01-Q2 — mobilní filtr plánovače

Stav 8. 11.: reprodukovaná prezentační vada v produkčním Chrome, role
`Superadmin · Trenér`, viewport 360 × 800: dokument má `clientWidth=345`,
`scrollWidth=367`; filtr `team_id` s `style="width:auto"` přesahuje pravý okraj
o 22 px. Při 390 px `clientWidth=scrollWidth=375`. [Čtecí průchod](verification/quality-uat-20261108/RESULTS.md).
Opravit jen šířkové omezení tří obdobných selectů v `planovac.php`; zachovat
hodnoty, pořadí, GET parametry, role a JS přepínání `Moje/Vše`.
Akceptace: 360/390 px bez vodorovného posunu, filtry viditelné a použitelné,
desktop zachovaný; PHP syntaxe, dokumentační kontrola a povinné CI PASS.
Produkční opravu a lidské převzetí doložit odděleně.
PR [#390](https://github.com/KovoPraha/evidenceTreninku/pull/390) je sloučený
jako `5b9279f9`; všech 11 CI PASS včetně MariaDB 10.3/11.4. Obsahuje
opravu a syntetické měření. Profil s historickým HTML porovnává po
normalizaci pouze tří očekávaných šířek; původní hash HTML zůstává zachovaný.
Produkční nasazení na `755b20a2` v běhu 42483838259 SUCCESS a oprava je
zahrnutá i v následném P11-RH. Cílené
přihlášené Chrome UAT 360/390/1536 px v Moje/Vše PASS. Nová záloha před
aktivací, standardní ochrany a veřejné HTTP doložené v
[protokolu vydání](verification/planner-mobile-release-20261108/RESULTS.md).
Úplné funkční UAT plánování a ostatních rolí zůstává otevřené.
Přihlášené hledání/editor ROLE01-MOB po obnoveném přihlášení cíleně PASS:
prázdný stav 0/48 a deaktivovaný #64 čtecí, bez uložení,
[protokol](verification/quality-uat-20261108/RESULTS.md).

### Q01-Q3 — první měřitelný úklid, rychlý nákup

Stav 8. 11.: SLUČENO [#389](https://github.com/KovoPraha/evidenceTreninku/pull/389)
jako `a3cca282`, všech 11 CI PASS včetně obou MariaDB. Větev
`codex/guest-checkout-read-cleanup-20261108`, samostatný worktree mimo `htdocs`;
původní WIP zachované. Kód je zahrnutý ve vydání #385 / deploy 42477866185;
[provozní protokol](verification/medical-filter-release-20261108/RESULTS.md).
Před #389 volalo `booking/rychly_nakup.php` pro běžné zboží
`shopProductRequiresAthlete($pdo, $productId)` dvakrát za jeden požadavek.
Klasifikace pro jeden produkt má být jedna konzistentní hodnota; opakované volání
čte stejné tabulky. Nejdřív doložit počet příkazů nad syntetickými daty, potom
odstranit druhé volání beze změny přesměrování programu a výběru varianty zboží.
Změna odstraňuje druhé volání až po guardu, který sportovce vyžadující produkt
přesměruje. Syntetický `CountingPDO` nad třemi druhy produktů naměřil pro
opakovanou klasifikaci 8 → 4 připravené SELECTy u běžného importovaného zboží
a 4 → 2 u kontrolované historické výjimky. Produkty vyžadující sportovce
zůstávají přesměrované po prvním volání. Místní PHPUnit výběr
`ShopPurchaseModeTest|ShopAnonymousCheckoutBootstrapTest|ShopCheckoutTest`
PASS: 43 testů / 799 assertions, PHP lint PASS. Jde o měření volání klasifikační
funkce nad syntetickými daty, nikoli o produkční HTTP profil. Přijetí PR vyžaduje
CI včetně obou MariaDB PASS; žádná změna plateb ani práv. Tento řez sám
nepřidal migraci, pozdější společné vydání #385 obsahovalo index zdravotního čtení.

| Pořadí | Výstup dalšího řezu | Přijetí a závislost |
| --- | --- | --- |
| Q1 — uzavřít existující cesty | Cíleně projít nasazené mobilní karty rolí na 390/360 px a desktopu (ROLE01-MOB); zopakovat přihlášené čtecí UAT výběru osob v docházce, až nástroj bezpečně ověří URL a obsah stránky; u zdravotního přehledu a plánovače ověřit přihlášenou roli, filtry a mobilní čitelnost. | Pro každou cestu zapsat PASS/FAIL/NOT RUN, roli, zařízení a reprodukční kroky. Dosavadní technické PASS, nasazení a blokované UAT nezaměňovat za uživatelské přijetí. [P11](ATTENDANCE_PERSON_PICKER_2026-11-07.md), [ROLE01](ACCESS_OVERVIEW_2026-11-07.md). |
| Q2 — opravy podle výsledků | Opravit nejvýše několik reprodukovaných vad z Q1 a lidského UAT P08/P12; přednost mají ztráta dat, chybné oprávnění, nejasný stav platby/dokladu, nedokončitelný formulář a mobilní blokátor. | Každá oprava má scénář před/po, cílený test, kontrolu ostatních rolí a samostatný release důkaz. Skutečné P05/P07 a P06 zůstávají odložené do dodání podkladů. |
| Q3 — měřitelný úklid | Z kandidátů C04/K0.2 vybrat jeden konkrétní problém: duplicitní čtení nebo nedosažitelný fallback po povinné migraci, případně test křehký na textové wiring. Nejprve potvrdit současné použití a změřit dopad; pak malý izolovaný řez. | Stejný výsledek dat, oprávnění a HTTP před/po; MariaDB 10.3/11.4 a dotčené testy PASS. Nemaže se plošně introspekce, historická dokumentace, větve ani worktrees. [C04](DOZOR_UKLID_2026-10-25.md), [inventura](verification/autonomous-priorities-20261107/RESULTS.md). |
| Q4 — provozní připravenost | Pro BAR P02/P04 připravit přejímací scénář fyzické zásoby → kasa → prodej → uzávěrka a účetní mapu bez ostrých zápisů. Pro členství/doklady P05/P07 připravit jediný důkazový scénář od objednávky po úhradu, PDF a doručení; pro P06 rozdílový náhled až s novým exportem. | Skutečné hodnoty, účetní pravidla a reálný sportovec/platba jsou vstupem vlastníka. Technický scénář nezakládá zásobu, pokladní hotovost ani platbu. [BAR](SESSION_HANDOFF_BAR_2026-11-06.md), [doklady](SHOP_DOCUMENT_AUTOMATION_2026-11-07.md). |
| Q5 — příprava funkcí | Ze záměrů P13/C01–C03 vybrat jednu funkci podle potřeby konkrétní role; sepsat uživatelský scénář, vlastníka dat, oprávnění, retenci, chybové stavy, migraci a měřitelné přijetí. ČSC/Sportsoft P15 hodnotit zvlášť podle otevřených PR #371/#376/#380/#382/#386 a jejich závislostí. | Teprve odsouhlasený kontrakt a bezpečnostní review dávají podklad k implementaci. Žádná automatická aktivace importu, zápisů do osob, walletu, Stripe ani nového upozornění. |

Q4 přejímací pořadí a Q5 návrh soukromého progresu C01/V5 jsou rozepsané v
[navazujícím podkladu](QUALITY_NEXT_SLICES_2026-11-08.md). Jsou připravené
scénáře a rozhodovací kontrakt, nikoli provedené provozní kroky nebo schválená funkce.

První proveditelný balík: Q1 v přihlášeném prostředí a čtecí inventura jednoho
Q3 kandidáta. Následující balík vznikne z doložených vad a výsledků, nikoli
z historického seznamu. Při každém převzetí ověřit nové PR, jejich CI a produkční
release; stav souběžného #385 se může mezitím změnit. Původní priorita
DOC03 níže zůstává datovaným snímkem 7. 11.; Q01 ji pro nové plánování zpřesňuje.

## Priority a závislosti

DOC03, 7. 11. 2026: stručný přehled na žádost vlastníka. Pořadí níže je doporučení
podle dopadu a závislostí, nikoli nové povolení plateb, importů nebo aktivací.
„Samostatně“ znamená v již schváleném rozsahu; otevřený bod není automaticky chyba.
Větev `codex/work-priorities-20261107`, vlastní worktree stejného tématu.

| Pořadí | Oblast a skutečně otevřený krok | Lze dokončit samostatně | Co čeká na vlastníka / člověka |
| --- | --- | --- | --- |
| 1 | P11 / ROLE01-MOB: uzavřít technická předání. [Lekce #367](ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md) mají merge, schválené nasazení a čtecí kontrolu dokončené; [protokol](verification/attendance-lesson-release-20261107/RESULTS.md). U [mobilních karet](ACCESS_OVERVIEW_2026-11-07.md) zbývá cílený čtecí Chrome 390/360 px a desktop. | Výsledkové předání lekcí #369 sjednocuje dokumentaci; nový deploy není potřeba. Vývoj dokončí cílený Chrome mobilních karet v původní relaci. [Katalog už nasazený](verification/catalog-release-20261107/RESULTS.md); vydání `c70939d4` obsahuje také karty #361 a ceny #358, nepřipravovat jejich duplicitní deploy. | Žádné nové zadání pro tyto schválené kroky. Výběr osob už má [PR #366](https://github.com/KovoPraha/evidenceTreninku/pull/366) a [místně hotový finální plán](ATTENDANCE_PERSON_PICKER_2026-11-07.md), sloučený `faae75e8`, schválené nasazení a nové společné měření hotové; nepřipravovat duplicitní implementaci. |
| 2 | P02: dokončit skutečný provoz BAR. [Plán B0–B7](BAR_INTEGRATION_PLAN_2026-11-05.md), [předání](SESSION_HANDOFF_BAR_2026-11-06.md). | Připravit a doprovodit přejímku; technická regrese hotová. | Lucka se přihlásí; vlastník/obsluha potvrdí fyzické zásoby, počáteční kasu a první prodej. |
| 3 | P04 / P09: propojit BAR s účetnictvím a uzavřít účetní podklady. [Adaptér](BAR_ACCOUNTING_ADAPTER_PLAN_2026-11-05.md), [D12–D14](CLUB_PILOT_D12_D14.md). Adaptér zatím není implementovaný. | Připravit technické mapování a testovací případy; účetní zápisy až po rozhodnutí. | Vlastník/účetní určí účty, DPH, ocenění, číselné řady, vazby úhrad a schválení. Doložit skutečné dohody/částky a dvě chybějící přílohy. |
| 4 | P05 / P07: skutečné sjednání členství, úhrada, PDF a doručení. [Runbook](MONTHLY_MEMBERSHIP_RUNBOOK.md), [doklady objednávek](SHOP_DOCUMENT_AUTOMATION_2026-11-07.md). | Lze připravit kontrolní scénář; automatika a doklady jsou aktivní, ranní čas už doložený. | Vlastník obnoví výslovně odložený skutečný test a poskytne vhodného ověřeného sportovce/objednávku; doložit platbu a příjem PDF ve schránce. |
| 5 | P06: poslední rozdílová synchronizace evidence. [Pracovní plán](MEMBER_EVIDENCE_WORK_PLAN_2026-11-02.md). | Po dodání podkladu připravit rozdílový náhled a kontroly. | Vlastník dodá nový export a obnoví odložený sync; E8 až po přejímce a bezpečné obnově. |
| 6 | P08 / P09 / P12: lidské trenérské a klubové UAT. [Scénáře](TRAINER_UAT_POSTUP_2026-11-01.md), [balíček testerky](testing/chatgpt-tester-20261105/README.md), [pilot D12–D14](CLUB_PILOT_D12_D14.md). | Doprovodit testera a evidovat výsledky; technická příprava hotová. | Určit testerku/testera, roli, prostředí a scénáře, zajistit skutečný přístup a převzetí. Heslo/T07 zůstávají odložené. |
| 7 | P14 HOTOVO pro novější konkrétní zálohu: [389 tabulek / 2 triggery PASS](verification/autonomous-priorities-20261107/restore-proof.json). | Izolovaná obnova i úklid dokončené; další generace se ověřují samostatně. | Pro tento důkaz nic; produkční DB se neobnovovala. |
| 8 | P10/C04: [čtecí inventura a konkrétní kandidáti dokončené](verification/autonomous-priorities-20261107/RESULTS.md), bez nové runtime opravy. | Vývoj zapsal důkazy; staré vyřešené nálezy znovu neimplementovat. | Vlastník WIP před archivací/mazáním; hosting a fyzická TV zůstávají samostatné. |
| 9 | P13 / C01–C03: další upozornění, wallet, progres a nové integrace. [Typy upozornění](STAFF_NOTIFICATIONS_2026-11-06.md), [backlog](plan-eshop-tymova-evidence/11-backlog-hodnota-pro-cleny.md), koncepty níže. | Zpřesnit varianty a odhad; nic automaticky nezapínat. | Vlastník vybere konkrétní funkci, příjemce/scénář a podmínku přijetí; právní/retentní otázky řeší odpovědná osoba klubu. |

Hotové: pracovní upozornění P01, zveřejnění nabídky P03, technické jádro ROLE01,
[ranní audit 7. 11. PASS](verification/membership-scheduler-20261107/RESULTS.md)
a katalog #364. Jejich opakování není další prioritou. Technické body 1, 7 a 8
mohou běžet souběžně s dodáváním podkladů pro body 2–6; nečekají na nové produktové zadání.

## AUTO01 — dokončený samostatný průchod priorit 1, 7 a 8

HOTOVO pro P14 a bezpečnou triáž P10/C04: [plán AUTO01](AUTONOMOUS_PRIORITIES_1_7_8_2026-11-07.md),
[výsledky](verification/autonomous-priorities-20261107/RESULTS.md), [PR #370](https://github.com/KovoPraha/evidenceTreninku/pull/370).
Novější DB záloha obnovena v izolaci: 389 tabulek / 2 triggery PASS.
332 větví a 156 worktrees prověřených; WIP zachované, nic nesmazané.
Lekce #369 nasazené/předané, výběr osob #366 sloučený; nový Chrome zůstává #365.
Tato relace neduplikuje vlastníky ani neruší zákaz produkční změny pickeru.

## P11-PICKER-PROD-RO — čtecí přihlášené UAT

**BLOKOVANÉ** podle aktuálního pokusu: [plán](ATTENDANCE_PERSON_PICKER_2026-11-07.md#přihlášené-produkční-uat-pouze-čtením--7-11-2026),
[protokol](verification/attendance-picker-production-readonly-uat-20261107/RESULTS.md).
Pouze stávající přihlášení a hledání/stránkování; žádná role, uložení docházky,
omluva nebo storno. Vlastní větev/worktree v plánu. Další krok: přihlášená
kontrola až po obnovení spolehlivého ověření URL a čtení DOM/snímků v nástroji.
Windows Computer Use běh automaticky zastavil kvůli neověřenému aktuálnímu URL;
žádný scénář UAT neproveden. Docházka ani role se tímto pokusem neměnily.
Dokumentační předání sdílí [PR #377](https://github.com/KovoPraha/evidenceTreninku/pull/377); nejde o provedené UAT.

## Aktivní návaznosti

P07 N1/N2: [automatické doklady a komunikace](SHOP_DOCUMENT_AUTOMATION_2026-11-07.md)
jsou sloučené v #355, nasazené a zapnuté pro nové objednávky.
Místní browser/MariaDB celý tok PASS, 2337 testů / 43257 assertions a 11 CI PASS.
[Provozní výsledky a meze](verification/shop-document-automation-20261107/RESULTS.md).
Skutečná bankovní platba, externí schránka a produkční přejímka členství zůstávají samostatné.

| ID | Oblast a stav | Další krok a závislost | Vlastník / podklady |
| --- | --- | --- | --- |
| ROLE01-MOB | Nasazeno #361 / `3b37e053`; otisky 48 účtů / 82 rolí / 5 superadminů / 1 grantu přesně zachované | Nové přihlášené čtecí Chrome 390/360 px a desktop NOT RUN; aktuální kontrolní karta skončila na loginu. Po obnovení relace projít filtry, prázdný výsledek a editor bez uložení. Deploy neopakovat. | Vývoj/vlastník; [plán](ACCESS_OVERVIEW_2026-11-07.md), [protokol vydání](verification/access-mobile-cards-20261107/RESULTS.md), původní PR #365 |
| P01 | HOTOVO: pracovní upozornění #330 nasazená, minutový běh a přihlášený zvoneček ověřené; vlastník 6. 11. potvrdil doručení zprávy do schránky | Neposílat další kopie ani opakovat instalaci. Potvrzení se týká skutečně přijaté zprávy, nikoli všech 18 zásilek. Zdrojové žádosti neschvalovat kvůli testu. | Vlastník; [potvrzení](verification/membership-copy-wip-20261106/RESULTS.md), [produkční přejímka](verification/staff-notifications-release-20261106/RESULTS.md) |
| P02 | BAR nasazený; 68 počátečních zásob obou skladů již převedeno, technické ověření PASS | Lucka se přihlásí; vlastník fyzicky odsouhlasí zásoby včetně pěti převzatých záporných stavů, počátek kasy a první prodej. Převod neopakovat ani zásoby neodhadovat. | Vlastník/obsluha; [plán B0–B7](BAR_INTEGRATION_PLAN_2026-11-05.md), [přejímka](verification/bar-acceptance-20261106/RESULTS.md) |
| P03 | HOTOVO: nabídka BAR 1 / vydání 1 zveřejněná, 45 produktů a fotografií; novější doklad #335 nahrazuje původní koncept | Publikaci neopakovat a nezakládat další návrh. Úpravy nabídky až podle dalšího zadání; zásoby/kasa a skutečný prodej zůstávají P02. Přihlášené vytvoření tiskového PDF zatím NOT RUN. | Správce baru; [publikace](verification/bar-menu-publication-20261106/RESULTS.md), [session handoff](SESSION_HANDOFF_BAR_2026-11-06.md) |
| P04 | BAR má účetní export/kontrakt a návrh adaptéru; zápis do hlavního účetnictví dosud není dokončený | Odsouhlasit účetní pravidla, implementovat a převzít adaptér bez zdvojení peněz. Není to pouze čekání na deploy nebo přepínač. Skutečná bezhotovostní úhrada/vratka také nedoložená. | Vlastník/účetní, vývoj; [kontrakt](BAR_ACCOUNTING_CONTRACT_V1.md), [plán adaptéru](BAR_ACCOUNTING_ADAPTER_PLAN_2026-11-05.md) |
| P05 | Skutečný tok ODLOŽENO vlastníkem; [hostingový plán #341 nasazený a přirozené minutové tiky ověřené](verification/membership-scheduler-20261106/RESULTS.md), denně 05:23 UTC; redundantní GitHub schedule vypnutý.  Závodní tým 297 / varianta 951 schválený, aktivní a zveřejněný za 2200 Kč/měsíc; údaje TJ KOVO uložené. Novější #336 doložilo cestu ke sjednání. Plán/pošta povolené, ruční prázdný běh doložený | Neopakovat schválení/publikaci. Sjednání vyžaduje klubem ověřeného sportovce; použitý účet jej neměl. První skutečné sjednání/platba/PDF/doručení zůstávají odložené. Technická oprava časování hotová; časovou přejímku 7. 11. vede serverový plán níže, dlouhodobý provoz jediný audit neuzavírá. Roční cena neurčená; textová oprava členských popisků #340 nasazena a ověřena (viz [důkaz vydání](verification/membership-copy-release-20261106/RESULTS.md)). | Vlastník/účetní; [čtecí doklad #336](verification/racing-team-status-20261106/RESULTS.md), [runbook](MONTHLY_MEMBERSHIP_RUNBOOK.md), [pošta](MEMBERSHIP_MAIL_ACTIVATION_2026-11-05.md), [historické založení](verification/membership-catalog-release-20261105/RESULTS.md) |
| P06 | Export/sync ODLOŽENO vlastníkem.  Evidence členů bez sezon nasazená, 435 zařazení převedeno | Vyčkat na poslední nový export, provést schválený rozdílový sync a role/UAT. E8 odstranění legacy schématu až po přejímce a bezpečné obnově. Počáteční datum ani import neopakovat. | Vlastník dodá export; [pracovní plán](MEMBER_EVIDENCE_WORK_PLAN_2026-11-02.md), [provoz](MEMBER_EVIDENCE_OPERATION_2026-11-02.md), [převod](verification/member-evidence-cutover-20261102/RESULTS.md) |
| P07 | Skutečná platba ODLOŽENO vlastníkem.  Fio import/automatika a SumUp zapnuté; skutečný hostingový plán ověřený | Doložit skutečnou zaplacenou úhradu a přihlášený průchod. 45 pohybů bez odpovídajícího VS ponechat ručnímu posouzení, nepárovat jen částkou. | Vlastník/hospodář; [aktivace](verification/live-payments-20261105/ACTIVATION.md), [45 pohybů](verification/fio-followup-20261105/RESULTS.md) |
| P08 | Trenérská obnova a syntetický pilot připravené; část UAT doložená | [Navazující syntetická regrese 6. 11. PASS](verification/offline-followup-20261106/RESULTS.md); dokončit asistované UAT a lidské převzetí. Heslo a T07 zůstávají na výslovné přání odložené. Pilot neprodlužovat automaticky. | Vlastník/tester; [pilot](TRAINER_PILOT_2026-10-31.md), [scénáře](TRAINER_UAT_POSTUP_2026-11-01.md) |
| P09 | Technický refaktoring W6–W15 uzavřený; provozní přejímka není kompletní | Lidská/účetní D12–D14, vazby, dohody a skutečné ocenění; dořešit dvě známé chybějící přílohy původní Evidence. | Vlastník/účetní; [technické uzavření](REFACTORING_CLOSURE_2026-10-30.md), [rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot D12–D14](CLUB_PILOT_D12_D14.md), [obnova/přílohy](verification/bar-cutover-20261106/RESULTS.md) |
| P10 | HOTOVO: čtecí triáž 332 větví / 156 worktrees; 288 s důkazem začlenění, 41 k posouzení, 3 otevřená PR v datovaném snímku | Bez mazání/merge; vlastník posoudí skutečné WIP. Starý auditní checkout s 1564 chybějícími soubory není kandidát k vydání. | Vývoj/vlastník WIP; [nová inventura a meze](verification/autonomous-priorities-20261107/RESULTS.md) |
| P11 | [Celé HTTP/browser na feb8fe8c HOTOVO](verification/full-http-performance-20261107/RESULTS.md): 65 případů / 930 vzorků, 403 browser pozorování; role/filtry/revokace PASS. [Klubové ceny #358](MEMBER_PRICING_READ_PERFORMANCE_2026-11-07.md) zahrnuté v nasazení: 500 produktů 2000 → 4 SQL; R13/R05/R06 zahrnuje ROLE01. K0.2 otevřené. | Finální omezený výběr osob ověřený v PR #366; sloučeno jako `faae75e8`, bez našeho deploye. Celé R06 HTTP společné verze hotové v Linuxu i rozšířeném Windows páru; dále větší R13/R05 měření hotové (P11-SCALE), dále R13 čtení, mobil/souběh. [Katalog #364 nasazený](CATALOG_READ_PERFORMANCE_2026-11-07.md), vlastní větev `codex/catalog-read-performance-20261107`: parita a 480 párů 10.3/11.4 PASS; 5000 produktů 202/340 → 87/199 ms, PHP 71,6 → 16,8 MiB; release `c70939d4`, deploy 42372002993 SUCCESS; [protokol a meze](verification/catalog-release-20261107/RESULTS.md). R06 aktuální aplikace přeměřené, ostatní nové stránky tím nejsou přeměřené; [lekce #367 a provozní výsledek](verification/attendance-lesson-release-20261107/RESULTS.md). | Vývoj/vlastník; [předání plného HTTP / PR #363](SESSION_HANDOFF_FULL_HTTP_PERFORMANCE_2026-11-07.md), [předání katalogu](SESSION_HANDOFF_CATALOG_READ_PERFORMANCE_2026-11-07.md), [předání cen](SESSION_HANDOFF_MEMBER_PRICING_READ_PERFORMANCE_2026-11-07.md), [komplexní plán](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md) |
| P11-ASSET9 | NASAZENO / technická přejímka PASS | Vývoj/Codex, #453/18bd541b, deploy 43104809135 | [Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset9--vstupní-přihlášení-11-11-2026), [měření](verification/signin-bootstrap-20261111/RESULTS.md). Přihlášené UAT NOT RUN |
| P11-ASSET10 | NASAZENO / technická přejímka PASS | Vývoj/Codex, #455/dbab9e6e, deploy 43164465663 | [Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset10--zápis-úprava-a-kopírování-tréninku-12-11-2026), [měření](verification/training-form-bootstrap-20261112/RESULTS.md). Kopírování již P11-RHC2, administrace P11-ADM/ASSET11; další kandidáty vede ASSET12. |
| P11-ASSET8 | NASAZENO / technická přejímka PASS | Vývoj/Codex, #451/d8ae4f85, deploy 42979420239 | [Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset8--plánovač-11-11-2026), [měření a přejímka](verification/schedule-bootstrap-20261111/RESULTS.md). Přihlášené UAT NOT RUN |
| P11-ASSET7 | NASAZENO / technicky PASS, přihlášené produkční UAT NOT RUN | Vývoj/Codex; #447/c29642f1, deploy42905781826; WIP7a7afd75 zachovaný | [Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset7--stránky-rodinného-účtu-10-11-2026), [předání](SESSION_HANDOFF_BOOTSTRAP_ASSETS_2026-11-10.md), [měření a meze](verification/family-assets-20261110/RESULTS.md); doklady přes #448, sloučením v main bez dalšího deploye |
| P11-ASSET6 | NASAZENO / technická přejímka PASS | Vývoj/Codex; #445/c9e5501a, deploy42881039034; WIP7a7afd75 zachovaný | [Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset6--rychlý-nákup-a-přihláška-10-11-2026), [předání](SESSION_HANDOFF_BOOTSTRAP_ASSETS_2026-11-10.md), [měření a meze](verification/checkout-assets-20261110/RESULTS.md); doklady přes PR #446, sloučením v main; bez dalšího deploye, controlfoto LIMIT |
| P11-EVENTREAD | ODLOŽENO / nevybráno | Vývoj/Codex; d8274970 pouze vlastní prototyp, bez view změny/deploye; SQLite6506→17SQL, ale produkční nabídka0karet | [Plán](CLUB_EVENT_PUBLIC_READ_PERFORMANCE_2026-11-10.md), [předání](SESSION_HANDOFF_CLUB_EVENT_PUBLIC_READ_2026-11-10.md); návrat při doloženém objemu a nové native2DB/HTTP/Chrome přejímce |
| P11-ASSET5 | NASAZENO / technická přejímka PASS | Vývoj/Codex; #439/8182489e, deploy42855987339; WIP7a7afd75 zachovaný | [Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset5--veřejný-kalendář-a-velodrom-10-11-2026), [předání](SESSION_HANDOFF_BOOTSTRAP_ASSETS_2026-11-10.md), [měření a meze](verification/public-calendar-assets-20261110/RESULTS.md); finální doklady po CI sloučit bez dalšího deploye |
| P11-ASSET4 | NASAZENO / nová technická parita PASS; historické hashy LIMIT | Vývoj/Codex; #435 / `94c10708`, 19 CI; deploy 42837011604 SUCCESS; doklady [#437](https://github.com/KovoPraha/evidenceTreninku/pull/437), codex/stable-ui-assets-release-docs-20261110 | [Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset4--katalogová-diagnóza-a-další-měřený-řez-10-11-2026), [výsledky](verification/catalog-audit-assets-20261110/RESULTS.md), [předání](SESSION_HANDOFF_BOOTSTRAP_ASSETS_2026-11-10.md); CSS 51890 B úspora po vydání, nové katalogové 5/5 PASS; WIP zachovaný |
| P11-ASSET3 | HOTOVO / NASAZENO: veřejné vstupy k účtu bez duplicitní CSS | Vývoj/Codex; PR432/beead425,13CI/deploy42810948814 SUCCESS; liveCSS−42%,6targetHTML/12pixelpairs PASS | [Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset3--veřejné-vstupy-k-účtu-10-11-2026), [předání](SESSION_HANDOFF_BOOTSTRAP_ASSETS_2026-11-10.md), [release/meze doplňkového katalogového probe](verification/public-account-assets-20261110/release/RESULTS.md); WIP zachovaný; další novýwaterfall/baseline |
| P11-ASSET2 | HOTOVO / NASAZENO: Bootstrap rozcestníku právě jednou | Vývoj/Codex; PR430/706a8d42,13CI a deploy42790212078 SUCCESS; coldCSS−31248B,8pixelpairs/8liveDOM PASS | [Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset2--pracovní-rozcestník-nový-řez-10-11-2026), [předání](SESSION_HANDOFF_BOOTSTRAP_ASSETS_2026-11-10.md), [release](verification/workspace-bootstrap-assets-20261110/release/RESULTS.md); WIP zachovaný, authUAT NOTRUN, další novýwaterfall |
| P11-HJS3 | NASAZENO / technicky PASS, přihlášené produkční UAT NOT RUN | Vývoj/Codex; #449/b753f6e7; deploy42920002806, WIP zachovaný | [Plán](SHARED_HEADER_SCRIPT_PERFORMANCE_2026-11-10.md#p11-hjs3--společný-skript-po-odstranění-duplicitních-stylů-10-11-2026), [předání](SESSION_HANDOFF_SHARED_HEADER_SCRIPT_2026-11-10.md), [měření](verification/shared-header-cache-20261110/RESULTS.md); [#449](https://github.com/KovoPraha/evidenceTreninku/pull/449), 14CI/native2DB/544Chrome/8pixelpárů PASS; warm gzip−25–30%, cold+955–1264B; deploy42920002806 SUCCESS, živé GET/hash/SRI/cache PASS; doklady #450 bez redeploye |
| P11-HJS2 | NEVYBRÁNO: přednost celé duplicitníCSS | Vývoj/Codex; codex/shared-header-script-gzip-20261110 /PR429 zachovat; native10.3PASS, Chrome odhalil2CSS v workspace, další ASSET2 | [Plán](SHARED_HEADER_SCRIPT_PERFORMANCE_2026-11-10.md#p11-hjs2--nové-posouzení-po-nasazení-gzip-10-11-2026), [předání](SESSION_HANDOFF_SHARED_HEADER_SCRIPT_2026-11-10.md), [důkazy](verification/shared-header-script-gzip-20261110/RESULTS.md); WIP zachovat, release až po PASS |
| P11-JSZ | HOTOVO / NASAZENO: gzip veřejných JS | Vývoj/Codex; PR427/f682cc46, 12CI PASS, deploy42774356221 SUCCESS; live13JS−69 %, přesné decodedSHA/8DOM PASS | [Plán](JAVASCRIPT_COMPRESSION_PERFORMANCE_2026-11-10.md), [předání](SESSION_HANDOFF_JAVASCRIPT_COMPRESSION_2026-11-10.md), [release](verification/javascript-compression-20261110/release/RESULTS.md); flagsfalse, WIP zachovaný, časový zisk neprokázaný |
| P11-HJS | NEVYBRÁNO: inline přesun před kompresí | Vývoj/Codex; prototyp zachovaný v codex/shared-header-assets-performance-20261110, bez merge/deploy; produkčníGET odhalily89kB JS bez gzip, další P11-JSZ | [Plán](SHARED_HEADER_SCRIPT_PERFORMANCE_2026-11-10.md), [předání](SESSION_HANDOFF_SHARED_HEADER_SCRIPT_2026-11-10.md), [důkazy](verification/shared-header-script-20261110/RESULTS.md); WIP zachovaný |
| P11-ASSET | NASAZENO / TECHNICKÁ PŘEJÍMKA PASS | Vývoj/Codex;425/9dbd9eaf/30CI, oba2DB běhy,544Chrome/8pixelpárů; deploy42727693090 SUCCESS,389/2/VS/config/migrace/smoke/8GET+DOM PASS; docs426 bez dalšího deploye, lidskéUAT NOT RUN | [Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md), [předání](SESSION_HANDOFF_BOOTSTRAP_ASSETS_2026-11-10.md), [důkazy](verification/bootstrap-assets-20261110/RESULTS.md); WIP zachovaný |
| P11-RIX | DIAGNÓZA UZAVŘENA: SQL/index varianty nevybrané | Vývoj/Codex; codex/athlete-targeted-read-optimization-20261109, baseff182971; #424/42704641179 PASS, diagnóza42705957545 PASS, SQL/index/header varianty nevyhověly, reader přesně obnoven; další implementace P11-ASSET. Žádný nový app deploy | [Plán](ATHLETE_PROFILE_TARGETED_READ_2026-11-09.md), [předání](SESSION_HANDOFF_ATHLETE_TARGETED_READ_2026-11-09.md), [důkazy](verification/athlete-profile-targeted-read-20261109/RESULTS.md); WIP zachovaný |
| P11-RHMS / RHM | HOTOVO / NASAZENO #422 | Vývoj/Codex; 17110a7f/deploy42679050552 SUCCESS,20CI/parita/HTTP/Chrome/čtecí produkce PASS; lidské produkční UAT otevřené | [Plán](ATHLETE_PROFILE_MEASUREMENT_PERFORMANCE_2026-11-09.md), [předání](SESSION_HANDOFF_ATHLETE_MEASUREMENT_2026-11-09.md), [release](verification/athlete-profile-measurement-summary-20261109/release/RESULTS.md); WIP zachovaný |
| P11-RHI | HOTOVO / NASAZENO #413 | Vývoj/Codex; c3d34b87/deploy42625502461 SUCCESS,18CI/parita/nativeHTTP/Chrome/čtecí produkce PASS; meze Měření/UAT v protokolu | [Plán](ATHLETE_PROFILE_HISTORY_PERFORMANCE_2026-11-09.md), [předání](SESSION_HANDOFF_ATHLETE_HISTORY_2026-11-09.md), [release](verification/athlete-profile-history-release-20261109/RESULTS.md); WIP zachovaný |
| P11-RAH | HOTOVO / NASAZENO #410 | Vývoj/Codex; c47d4008/deploy42587406908 SUCCESS,18CI/parita/HTTP/Chrome/čtecí produkce PASS; UAT samostatné | [Plán](ATHLETE_PROFILE_HEADER_PERFORMANCE_2026-11-09.md), [předání](SESSION_HANDOFF_ATHLETE_HEADER_2026-11-09.md), [release](verification/athlete-profile-header-release-20261109/RESULTS.md); WIP zachovaný |
| P11-RAP | HOTOVO / NASAZENO #408 | ca5fc219/deploy42574460894 SUCCESS;16CI/parita/HTTP/Chrome/produkční čtení PASS, UAT samostatné | [Plán](ATHLETE_PROFILE_PARENT_PERFORMANCE_2026-11-09.md), [předání](SESSION_HANDOFF_ATHLETE_PARENT_2026-11-09.md), [release](verification/athlete-profile-parent-release-20261109/RESULTS.md); WIP zachovaný; header agregace dokončené v P11-RAH |
| P11-RAS | HOTOVO / NASAZENO #406 | 95cb33e5/deploy42531190845 SUCCESS; parita/16CI/HTTP/Chrome/čtecí produkce PASS, UAT samostatné | [Plán](AJAX_PROFILE_SESSION_PERFORMANCE_2026-11-08.md), [předání](SESSION_HANDOFF_AJAX_PROFILE_SESSION_2026-11-08.md), [release](verification/ajax-profile-session-release-20261108/RESULTS.md); WIP zachovaný; navazuje uzavřené [RAP](ATHLETE_PROFILE_PARENT_PERFORMANCE_2026-11-09.md) |
| P11-RGS | NEVYBRÁNO / nahrazené RAS | Globální hledání má vypnutý UI guard; neprokázaný přínos běžné cestě, žádná aktivace/deploy | [Rozhodnutí](GLOBAL_SEARCH_SESSION_PERFORMANCE_2026-11-08.md#uzavření-výběru-kandidáta--nevybráno), [RAS](AJAX_PROFILE_SESSION_PERFORMANCE_2026-11-08.md) |
| P11-RHA | HOTOVO technicky / NASAZENO | #404/82efa16d, deploy42521982178 SUCCESS;14CI/parita PASS, profil129→53ms a historie111→33ms; přihlášené UAT NOT RUN | [Plán](ATHLETE_PROFILE_READ_PERFORMANCE_2026-11-08.md), [předání](SESSION_HANDOFF_ATHLETE_PROFILE_2026-11-08.md), [release](verification/athlete-profile-release-20261108/RESULTS.md); WIP zachovaný |
| P11-RHC | HOTOVO technicky / NASAZENO | #400/da152282, deploy42494231010 SUCCESS;14CI/parita PASS, AJAX148→10ms; přihlášené UAT NOT RUN, veřejný obsah222→223 | [Plán](COACH_HISTORY_PAGE_PERFORMANCE_2026-11-08.md), [předání](SESSION_HANDOFF_COACH_HISTORY_2026-11-08.md); vlastní větev, WIP zachovaný |
| P11-RHC2 | NASAZENO / technická přejímka PASS | Vývoj/Codex, #457/87171a67, deploy 43215069511 | [Plán](TRAINING_HISTORY_PAGE_PERFORMANCE_2026-11-08.md#p11-rhc2--omezená-agregace-vzorů-kopírování-12-11-2026), [měření](verification/training-copy-20261112/RESULTS.md). Počty a styly přehledu již P11-ADM/ASSET11; další kandidáty vede ASSET12. |
| P11-ADM | NASAZENO / technická přejímka PASS | Vývoj/Codex, #465/be4ce55a, deploy 43326177751 | [Plán](ADMIN_DASHBOARD_PERFORMANCE_2026-11-12.md), [měření](verification/admin-dashboard-20261112/RESULTS.md). Styly přehledu dokončené ASSET11; další kandidáty vede ASSET12. Přihlášené UAT NOT RUN. |
| P11-CATSORT | NASAZENO / technická přejímka PASS | Vývoj/Codex,#500/62c00649,deploy44473829467 | [Plán](CATALOG_READ_PERFORMANCE_2026-11-07.md#p11-catsort--řazení-katalogu-19-11-2026), [výsledky](verification/catalog-sort-20261119/RESULTS.md);10000/1000 přehledHTTP 318.8→237.3ms / 232.7→164.0ms,30native/82HTTP scénářů/DB,4pixelpáry/13CI PASS. SQL3/6TX stejné, UAT NOT RUN. |
| P11-CATSEL | NASAZENO / technická přejímka PASS | Vývoj/Codex,#498/a752cd96,deploy44404265875 | [Plán](CATALOG_READ_PERFORMANCE_2026-11-07.md#p11-catsel--výběr-katalogu-po-catread-18-11-2026), [výsledky](verification/catalog-selection-20261118/RESULTS.md);10000/1000 filtrHTTP 1321.5→415.9ms / 632.6→206.8ms,46scénářů/DB,4pixelpáry/13CI PASS. SQL3/6TX stejné, UAT NOT RUN. Řadicí klíče navazují v P11-CATSORT. |
| P11-CATREAD | NASAZENO / technická přejímka PASS | Vývoj/Codex,#496/e846e5a5,deploy44367469594 | [Plán](CATALOG_READ_PERFORMANCE_2026-11-07.md#p11-catread--další-čtení-kategorií-18-11-2026), [výsledky](verification/category-descendants-20261118/RESULTS.md); tree1000 filtrHTTP 45.0→21.8ms / 32.2→15.8ms,36scénářů/DB,4pixelpáry/13CI PASS. MimoTX6→3SQL, uvnitř původní6SQL. UAT NOT RUN. |
| P11-CATTOP | NASAZENO / technická přejímka PASS | Vývoj/Codex,#494/43121578,deploy44269813909 | [Plán](CATALOG_READ_PERFORMANCE_2026-11-07.md#p11-cattop--počty-kořenových-kategorií-18-11-2026), [výsledky](verification/catalog-top-counts-20261118/RESULTS.md); 10000×1000 HTTP 1079.3→321.1ms / 828.4→232.8ms; 36scénářů/DB,4pixelpáry/13CI PASS. UAT NOT RUN. |
| P11-CATLABEL | NASAZENO / technická přejímka PASS | Vývoj/Codex,#492/8347ff50,deploy44189981302 | [Plán](CATALOG_READ_PERFORMANCE_2026-11-07.md#p11-catlabel--skládání-popisků-kategorií-17-11-2026), [výsledky](verification/category-label-20261117/RESULTS.md); chain500 HTTP 81.1→32.3ms / 110.7→43.3ms; 36scénářů/DB,4pixelpáry/13CI PASS. UAT NOT RUN. |
| P11-CATMENU | NASAZENO / technická přejímka PASS | Vývoj/Codex, #490/a3796752, deploy44152881039 | [Plán](CATALOG_READ_PERFORMANCE_2026-11-07.md#p11-catmenu--strom-kategorií-veřejného-katalogu-17-11-2026), [výsledky](verification/category-menu-20261117/RESULTS.md); tree1000 HTTP 94.0→25.2ms / 76.3→19.2ms; 32scénářů/DB,4pixelpáry/13CI PASS. UAT NOT RUN. |
| P11-REVIEW | NEVYBRÁNO / původní kód zachovaný | Vývoj/Codex, codex/next-performance-20261117c | [Plán](CATALOG_READ_PERFORMANCE_2026-11-07.md#p11-review--agregace-kontroly-importovaného-katalogu-17-11-2026); lokální zisk nebyl stabilně ≥20 %; případný návrat až po nové nativní diagnóze. Žádný merge/deploy. |
| P11-ATTR | NASAZENO / technická přejímka PASS | Vývoj/Codex, #488/4270d0cc, deploy44131137781 | [Plán](CATALOG_READ_PERFORMANCE_2026-11-07.md#p11-attr--objevování-parametrů-variant-17-11-2026), [výsledky](verification/attribute-discovery-20261117/RESULTS.md); repeated HTTP −42 až50 %, PHP peak přibližně −50 %, unique HTTP režie6–12 %. 51scénářů/DB,8pixelpárů/13CI PASS; přihlášené UAT NOT RUN. |
| P11-ASSET14 | NASAZENO / technická přejímka PASS | Vývoj/Codex, #486/9a0bc834, deploy44114428641 | [Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset14--kategorie-a-parametry-e-shopu-17-11-2026), [výsledky](verification/category-attribute-bootstrap-20261117/RESULTS.md); cold CSS −42,09 %, parita/13CI PASS. Další samostatně profilované CSS/objemové kandidáty; přihlášené produkční UAT NOT RUN. |
| P11-ASSET13 | NASAZENO / technická přejímka PASS | Vývoj/Codex, #481/837a7112, deploy43649462751 | [Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset13--klubový-kalendář-a-správa-katalogu-14-11-2026), [výsledky](verification/calendar-catalog-bootstrap-20261114/RESULTS.md); zbylé admin CSS až po vlastním profilu. Přihlášené produkční UAT NOT RUN. |
| P11-PCAP | NASAZENO / technická přejímka PASS | Vývoj/Codex, #479/c7b77fa5, deploy43633315305 | [Plán](club-programs.md#p11-pcap--dávkové-kapacity-správy-programů-14-11-2026), [výsledky](verification/program-capacity-read-20261114/RESULTS.md); další kandidát objem seznamu a CSS. Přihlášené produkční UAT NOT RUN. |
| P11-PADMIN | NASAZENO / technická přejímka PASS | Vývoj/Codex, #477/b4d5cfe2, deploy 43478690110 | [Plán](club-programs.md#p11-padmin--dávkové-čtení-správy-programů-13-11-2026), [výsledky](verification/program-admin-read-20261113/RESULTS.md). Kapacitní dávky dokončené P11-PCAP; dále rozsah seznamu; přihlášené produkční UAT NOT RUN. |
| P11-ASSET12 | NASAZENO / technická přejímka PASS | Vývoj/Codex, #473/b2ca9f4b, deploy 43447880903 | [Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset12--správa-kroužkových-programů-13-11-2026), [měření](verification/program-admin-bootstrap-20261113/RESULTS.md). Styly kalendáře/katalogu dokončené P11-ASSET13, kapacitní dávky P11-PCAP. Přihlášené UAT NOT RUN. |
| P11-ASSET11 | NASAZENO / technická přejímka PASS | Vývoj/Codex, #470/cd04bad6, deploy 43386354560 | [Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset11--administrátorský-přehled-13-11-2026), [měření](verification/admin-dashboard-bootstrap-20261113/RESULTS.md). Programy dokončuje ASSET12; další kandidáti kalendář/katalogová administrace; přihlášené UAT NOT RUN. |
| P11-RH | HOTOVO technicky / NASAZENO | #394/ab49947d, deploy42486500282 SUCCESS; parita/14CI PASS, GET398→16ms; přihlášené UAT NOT RUN | [Plán](TRAINING_HISTORY_PAGE_PERFORMANCE_2026-11-08.md), [předání](SESSION_HANDOFF_TRAINING_HISTORY_2026-11-08.md); vlastní větev, WIP zachovaný |
| P11-R13-F | TECHNICKY HOTOVO / NASAZENO: #385/519ff098, deploy42477866185 SUCCESS | Parita, výkon, 15CI, index a čtecí produkční přejímka PASS; přihlášené UAT NOT RUN | [Plán](MEDICAL_FILTER_READ_PERFORMANCE_2026-11-07.md), [předání](SESSION_HANDOFF_MEDICAL_FILTER_2026-11-07.md), [protokol](verification/medical-filter-release-20261108/RESULTS.md); WIP zachovaný |
| P11-R13-S | HOTOVO: #381 v main a nasazeno938e9c50; parita/přínos/12CI i čtecí produkční kontrola PASS | Další deploy neopakovat. Přihlášené UAT/rows examined/scoped čtení/mobil/souběh a K0.2 otevřené | Vývoj; [plán](MEDICAL_STATE_READ_PERFORMANCE_2026-11-07.md), [předání](SESSION_HANDOFF_MEDICAL_STATE_2026-11-07.md), [protokol](verification/medical-state-release-20261107/RESULTS.md); WIP zachovaný |
| P11-R05-H | HOTOVO: #374 v main a nasazeno; parita obou DB/celého HTML, přínos, všech12 CI a čtecí produkční kontrola PASS | Další deploy neopakovat. Přihlášené UAT/rows examined/fyzický mobil/souběh a R13/K0.2 samostatně otevřené | Vývoj; [plán](PLANNER_HIERARCHY_READ_PERFORMANCE_2026-11-07.md), [předání](SESSION_HANDOFF_PLANNER_HIERARCHY_2026-11-07.md), [protokol](verification/planner-hierarchy-release-20261107/RESULTS.md); WIP zachovaný |
| P11-SCALE | HOTOVO: primární2160 + aktuální#374 540HTTP,90doménových/DOM parit,36negativ; oba úklidy a původní WIP PASS | R13 stavový řez a řízený Linux100k uzavírá P11-R13-S; dále scoped čtení/mobil/souběh. R05 opravu/deploy neopakovat. | Vývoj; [plán](MEDICAL_PLANNER_SCALE_2026-11-07.md), [předání](SESSION_HANDOFF_MEDICAL_PLANNER_SCALE_2026-11-07.md), [PR #375](https://github.com/KovoPraha/evidenceTreninku/pull/375) |
| P11-PICKER | HOTOVO: #366 sloučené, nové integrované měření/parita s #367 a schválené nasazení dokončené; 540 HTTP / 78 Chrome, HTML −93,3 % | Deploy neopakovat. Další objemy/rows examined/mobil/souběh samostatně. Přihlášené UAT BLOKOVANÉ: URL/DOM fungují, kontrolní karta skončila na loginu; scénáře NOT RUN. [Protokol](verification/attendance-picker-production-readonly-uat-20261107/RESULTS.md). | Vývoj/vlastník; [plán](ATTENDANCE_PERSON_PICKER_2026-11-07.md), [předání](SESSION_HANDOFF_ATTENDANCE_PERSON_PICKER_2026-11-07.md), [release](verification/attendance-picker-release-20261107/RESULTS.md); výsledkové #372, WIP zachovaný |
| P11-R06-HTTP | Místní společný Windows pár HOTOVO: 540HTTP/234Chrome všech9scénářů, 20shodných checksumů, JS/noJS/44HTTP/zápisy/role PASS; žádná změna produkce touto relací | [Výsledkové CI/přijetí #373](https://github.com/KovoPraha/evidenceTreninku/pull/373); dále R13 čtení / řízené další běhy/fyzický mobil/síť/CPU/souběh. #372 již doložilo vlastní nasazení, neopakovat. | Vývoj; [plán](ATTENDANCE_PERSON_PICKER_2026-11-07.md), [předání](SESSION_HANDOFF_ATTENDANCE_HTTP_2026-11-07.md), [výsledek](verification/attendance-integrated-http-20261107/RESULTS.md); `codex/attendance-integrated-http-20261107` |
| P11-R06-LESSONS | HOTOVO: dávkové lekce #367, parita 720 párů 10.3/11.4, 153 → 6 SQL; finální CI PASS | Omezený výběr osob i celé R06 HTTP/browser hotové; dále větší R13/R05 měření hotové (P11-SCALE), dále R13 čtení a mobil/souběh; přihlášené produkční UAT tohoto helperu NOT RUN | Vývoj; [plán](ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md), [předání](SESSION_HANDOFF_ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md), [protokol](verification/attendance-lesson-release-20261107/RESULTS.md) |
| P12 | Technická příprava HOTOVO: soubory 00–06, 20 scénářů, hlášení, mapa a spojený podklad sjednocené 6. 11. proti 3ff0fd13; přenosový ZIP ověřený. [Důkazy](verification/offline-followup-20261106/RESULTS.md). Není to provedené lidské UAT ani sdílení projektu | Vlastník určí testerku, roli/prostředí a konkrétní scénáře; oprávněná osoba dokončí pozvánku/přístup a kontrolní otázky v jejím účtu. Nové ověření hlavní verze před použitím; žádné opakování technické aktualizace bez změny podkladů. | Vlastník/testerka; [balíček a hranice](testing/chatgpt-tester-20261105/README.md), [uživatelské testování](PRODUKCNI-UZIVATELSKE-TESTOVANI.md) |
| P13 | Další pracovní upozornění jsou záměr, konkrétní typy nejsou vybrané | Určit události, příjemce, vznik/ukončení úkolu, bezpečný odkaz, deduplikaci a testy. Push a eskalace nejsou součástí dokončené první verze. | Vlastník/vývoj; [registr typů a hranice](STAFF_NOTIFICATIONS_2026-11-06.md) |
| P14 | PASS: novější evidence_2026-11-07_125007_ce199079.sql.gz obnovena v běhu 42376793141; shoda řádků 389 tabulek / 2 triggerů, SHA ověřen, dočasná data uklizená. Předchozí tři generace mají vlastní dřívější důkaz | Pro tuto konkrétní generaci hotovo; nové pozdější zálohy mají samostatné ověření. Produkci neobnovovat. | Vývoj/provoz; [nový důkaz](verification/autonomous-priorities-20261107/restore-proof.json), [předchozí generace](verification/autonomous-p01-p14-20261106/restore-evidence.txt) |

## Dokončené položky, které znovu neotvírat podle názvu větve

- ROLE01-VIS: [skutečný Chrome desktop/mobil](verification/access-visual-20261107/RESULTS.md) dokončen pouze čtením.
  Filtry, posun a otevření editoru PASS; mobilní přehled má výhradu čitelnosti široké tabulky. Role beze změny.
- ROLE01: [Lidé a přístupy](ACCESS_OVERVIEW_2026-11-07.md) nasazené / 16 produkčních kontrol PASS.
  [Protokol](verification/access-release-20261107/RESULTS.md): původních 46 účtů a 82 přiřazení beze změny,
  nové testovací účty 63/64 deaktivované a bez rolí. [Uzavření a důvod](DECISION_LOG.md#d2026-11-07-02).
  Výsledkové předání `codex/access-release-20261107`; nasazení ani zápisový test neopakovat.

- Omluvenky a náhrady: nasazené, kontrola 6. 11. PASS 22 testů / 139 assertions,
  23 HTTP scénářů a místní browser. [Důkazy](verification/makeup-audit-20261106/RESULTS.md),
  dokumentace #331 sloučená. Žádná čekající oprava; přihlášené produkční UAT oddělené.
- Měsíční členství a zastupování trenérů: společné PR #314 a
  [vydání](verification/monthly-coach-release-20261105/RESULTS.md); samostatné starší větve neslučovat znovu.
- BAR B0–B6, převod B7 a aliasy: [vydání](verification/bar-release-20261105/RESULTS.md),
  [převod](verification/bar-cutover-20261106/RESULTS.md), [přejímka](verification/bar-acceptance-20261106/RESULTS.md).
  Import/striktní nulový počátek neopakovat přes vzniklé legitimní záznamy.


## Dříve neevidované koncepty a otevřené návaznosti

Tyto položky evidují existující návrhy; tento audit jim neuděluje schválení realizace.

| ID | Stav a rozsah | Další krok / vlastník / podklady |
| --- | --- | --- |
| C01 | KONCEPT / ČÁSTEČNĚ PŘEKRYTO: wallet, osobní progres/rekordy, retenční přehled, roční přehled/export, TrainingPeaks/Strava/FIT; M3 normalizace/read-only příprava hotová, řízený import a nejasné řádky otevřené | Vlastník vybere konkrétní záměr a ověří jeho dnešní rozsah, datový/účetní kontrakt a podmínku přijetí; [V1–V7 a blokované návrhy](plan-eshop-tymova-evidence/11-backlog-hodnota-pro-cleny.md), [M3 a zbývající kroky](plan-eshop-tymova-evidence/12-milnik-m3-clenska-hodnota.md), [úplný katalog](PLAN_CATALOG.md) |
| C02 | KONCEPT / K REVIZI: zbytky návrhu informační architektury a registrace; registrační a rolové cesty již v main, starý text „nic neimplementováno“ není dnešní stav | Vývoj/vlastník porovná zbývající varianty s dnešním UI; právní/retentní podmínky řeší odpovědná osoba klubu. [Návrh IA](navrh-informacni-architektury.md), [registrace](navrh-registrace-sportovce.md); nepovažovat starý R7 ani rename za nové pověření |
| C03 | IMPLEMENTOVÁNO ČÁSTEČNĚ / PROVOZ NEDOLOŽEN: volitelné rodinné souhrny a připomínky předpisů, budoucí Stripe aktivace/refund slice, PPL a skutečná přepravní přejímka Packety | Správce/vlastník vybere konkrétní aktivaci, příjemce, účet a scénář; doloží skutečný průchod před zapnutím. [Souhrny](family-weekly-summaries.md), [upomínky V3](plan-eshop-tymova-evidence/11-backlog-hodnota-pro-cleny.md), [Stripe](stripe-integration-plan.md), [PPL](PPL_BOX_CHECKOUT.md), [Packeta](PACKETA_MODULE_AUDIT_2026-10-21.md). Nezaměňovat s již aktivovanými Fio/SumUp P07 a členstvím P05 |
| C04 | K OVĚŘENÍ: zbylé návrhy starých bezpečnostních/datových/UX auditů, hosting a kapacita, další technický dluh a fyzická TV | Vývoj nejprve ověří aktuální reprodukci; hosting rozhodne vlastník. Starý nález není automaticky dnešní závada. [Úklid a kandidáti](DOZOR_UKLID_2026-10-25.md), [hosting](mariadb-10.3-compatibility-2026-08-06.md), [TV postup](TV-PROGRAMY.md), [historické audity a dispozice](PLAN_CATALOG.md); aktivní výkonové měření zůstává P11 |

## Stav souběžně začleněných změn

- P11 katalogový výřez: [PR #364](https://github.com/KovoPraha/evidenceTreninku/pull/364)
  je sloučené a nasazené jako `c70939d4`; [protokol a meze](verification/catalog-release-20261107/RESULTS.md).

- ROLE01: #356 a [výsledkové #359](https://github.com/KovoPraha/evidenceTreninku/pull/359) dokončené;
  [přehled a skutečná přejímka](verification/access-release-20261107/RESULTS.md) PASS, test neopakovat.
- P11-PRICING: [PR #358](https://github.com/KovoPraha/evidenceTreninku/pull/358),
  dávkové klubové ceny sloučené `37d0ad68`, následně obsažené ve vydání katalogu `c70939d4`.
- P07-UAT: [PR #355](https://github.com/KovoPraha/evidenceTreninku/pull/355)
  implementovalo a nasadilo automatické doklady/e-maily; pověření 1 aktivní, doložené #362. Při přejímce
  přezkoumat proti main; příprava šablon v původním [plánu](ORDER_STATUS_EMAIL_PLAN_2026-10-21.md)
  sama nedokládá všechny producenty zpráv. Skutečné P07 zůstává odložené.

## Povinný záznam při každé další práci

Trenérské přiřazení po #314 určuje přednost výběru, nikoli možnost zastupovat.
Chybějící rozsahy v P09 se týkají samostatných soukromých a pracovních agend;
nedoplňovat je jen kvůli odblokování již dostupného tréninkového zápisu.
U členství zachovat schválené chování: plná cena i během měsíce, ukončení
ke konci měsíce, více disciplín bez příplatku a zachování jednorázových nákupů.

Ještě při zahájení zapsat položku nebo odkaz na existující plán: zadání, rozsah
oprávnění, větev/worktree, stav, další krok, závislost a související dokumenty.
Při změně výsledku aktualizovat tento registr a tematický plán; při zahájení, změně i před předáním
[handoff](HANDOFF_CURRENT.md) a [CURRENT_STATE](CURRENT_STATE.md), stručně odkazem na plán. Release/flags zapisovat do CURRENT_STATE s důkazem,
nikdy neodvozovat nasazení z merge nebo zeleného CI. Výsledky ukládat do
`docs/verification/` a propojit z tematického dokumentu; ne pouze do chatu nebo lokálního output.
Plán není souhlas s publikací, reálnou platbou, rozesílkou nebo datovým převodem.
Tajemství, hesla, session, produkční dumpy a osobní přílohy do Gitu nepatří;
uvést jen bezpečnou referenci a způsob ověření soukromého podkladu.

## Aktuální předání a souběžné návrhy

DOC02 obsah a místní ověření HOTOVO: [audit všech plánů a konceptů](PLAN_CATALOG_AUDIT_2026-11-07.md).
Větev `codex/plan-catalog-audit-20261107`; předání [PR #360](https://github.com/KovoPraha/evidenceTreninku/pull/360), skutečný stav CI/merge u PR.

| ID | Stav a podklad | Další krok / vlastník |
| --- | --- | --- |
| DOC01 | Obsah a místní ověření HOTOVO: [souborové plány a krátké předání](DOCUMENTATION_GOVERNANCE_2026-11-07.md), [předání #357](https://github.com/KovoPraha/evidenceTreninku/pull/357); větev `codex/repo-documentation-policy-20261107` | Stav CI/merge u PR; další práci vést podle nových pravidel. Pouze dokumentace a její kontrola, bez deploye. |
| P07 N1/N2 | [PR #355](https://github.com/KovoPraha/evidenceTreninku/pull/355) sloučené, doklady a e-maily nasazené a zapnuté | [Protokol a hranice](verification/shop-document-automation-20261107/RESULTS.md), [provozní návod](SHOP_DOCUMENT_AUTOMATION_2026-11-07.md). |

## Ranní serverový audit — samostatná návaznost P05

[Plán a přesný stav](MEMBERSHIP_SCHEDULER_MORNING_AUDIT_2026-11-07.md),
[předání](SESSION_HANDOFF_SERVER_MEMBERSHIP_AUDIT_2026-11-06.md).
[PR #347](https://github.com/KovoPraha/evidenceTreninku/pull/347) je sloučené;
[výsledek PASS](verification/membership-scheduler-20261107/RESULTS.md) doložil skutečný start s odchylkou 3 s.
Místní úloha PAUSED, skutečné P05/P07 a export P06 dál odložené.


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

## Historie a pravidla údržby

Předchozí session, pořadí před autonomní vlnou a pracovní kopie jsou v
[archivu registru](WORK_PLAN_ARCHIVE_2026-11-07.md). Chronologii vede
[deník událostí](EVENT_LOG.md), důvody a uzavřené plány
[rozhodovací log](DECISION_LOG.md). Nové session sem nepřipojovat jako historii.
Položku odstranit z aktivního seznamu až po zápisu výsledku a trvalého odkazu
do deníku; částečně dokončené etapy ponechat s dalším krokem.
Postup včetně konceptů a GitHub předání určuje [pravidlo](WORK_DOCUMENTATION_POLICY.md).

Aktuální pokračování pickeru po #370: #366 sloučené, nový celý HTTP/Chrome pár s #367 PASS; standardní schválené nasazení a čtecí ověření dokončené. [Ověřovací předání #372](https://github.com/KovoPraha/evidenceTreninku/pull/372), [průběžný protokol](verification/attendance-person-picker-20261107/integrated/RESULTS.md). Přijaté P14/AUTO01 a ostatní WIP zachované.
