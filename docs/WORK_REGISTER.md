# Registr rozpracované a plánované práce

Aktualizováno 6. 11. 2026, Europe/Prague. Výchozí ověřený main `9c543ff9`.
Tento soubor průběžně eviduje práci a další kroky; živé SHA/flags a release důkazy
jsou pouze v [CURRENT_STATE](CURRENT_STATE.md). Převzetí řídí
[HANDOFF_CURRENT](HANDOFF_CURRENT.md), datované podklady
[provozní inventura](verification/operations-inventory-20261106/RESULTS.md).
Po novém merge/deploy/provozní operaci stav znovu ověřit, nikoli převzít z názvu větve.
Jde o jediný průběžný seznam práce; [WORK_PLAN_CURRENT](WORK_PLAN_CURRENT.md)
odkazuje sem. Sjednocení #333/#334 dokládá [protokol](verification/documentation-reconcile-20261106/RESULTS.md).
Aktuální zadání této relace je pouze dokumentační: nic na produkci nezapínat,
nepublikovat a neodesílat. Další kroky níže nejsou příkazem k jejich provedení.

## Aktivní návaznosti

| ID | Oblast a stav | Další krok a závislost | Vlastník / podklady |
| --- | --- | --- | --- |
| P01 | Pracovní upozornění #330 nasazená; minutový běh, 18 sent, přihlášený zvoneček, přesný odkaz a přečtení/nepřečtení doložené | Získat potvrzení příjemce o doručení stávající zprávy do schránky. Neposílat další kopie ani opakovat instalaci. Produkční schválení/zamítnutí zdrojové žádosti se kvůli testu neprovádělo. | Příjemce/vlastník; [produkční přejímka](verification/staff-notifications-release-20261106/RESULTS.md), [postup](STAFF_NOTIFICATIONS_2026-11-06.md) |
| P02 | BAR nasazený a převedený; technická provozní přejímka PASS | Skutečná Lucka se přihlásí původním heslem; vlastník potvrdí fyzické zásoby, počátek kasy a přejímku prvního prodeje. Nevytvářet odhadované zásoby. | Vlastník/obsluha; [plán B0–B7](BAR_INTEGRATION_PLAN_2026-11-05.md), [přejímka](verification/bar-acceptance-20261106/RESULTS.md) |
| P03 | HOTOVO: nabídka BAR 1 / vydání 1 zveřejněná, 45 produktů a fotografií; novější doklad #335 nahrazuje původní koncept | Publikaci neopakovat a nezakládat další návrh. Úpravy nabídky až podle dalšího zadání; zásoby/kasa a skutečný prodej zůstávají P02. Přihlášené vytvoření tiskového PDF zatím NOT RUN. | Správce baru; [publikace](verification/bar-menu-publication-20261106/RESULTS.md), [session handoff](SESSION_HANDOFF_BAR_2026-11-06.md) |
| P04 | BAR má účetní export/kontrakt a návrh adaptéru; zápis do hlavního účetnictví dosud není dokončený | Odsouhlasit účetní pravidla, implementovat a převzít adaptér bez zdvojení peněz. Není to pouze čekání na deploy nebo přepínač. Skutečná bezhotovostní úhrada/vratka také nedoložená. | Vlastník/účetní, vývoj; [kontrakt](BAR_ACCOUNTING_CONTRACT_V1.md), [plán adaptéru](BAR_ACCOUNTING_ADAPTER_PLAN_2026-11-05.md) |
| P05 | Měsíční členství a zastupování nasazené; plán/pošta povolené, ruční prázdný běh doložený. Dokončení publikace varianty 951 neověřené | Nejprve čtením ověřit fakturační údaje TJ KOVO, vystavovatele, účetní pravidlo, tarif 2200 Kč/měsíc a publikaci existujícího produktu. #333 zaznamenává již udělený souhlas; chybí doklad dokončení, nikoli nutně souhlas. Doplnit první automatický časový start a skutečné sjednání/platbu/PDF/doručení. Roční cena neurčená. | Vlastník/účetní; [předání #333](verification/documentation-reconcile-20261106/WORK_PLAN_SOURCE_333.md), [runbook](MONTHLY_MEMBERSHIP_RUNBOOK.md), [pošta](MEMBERSHIP_MAIL_ACTIVATION_2026-11-05.md), [založení](verification/membership-catalog-release-20261105/RESULTS.md) |
| P06 | Evidence členů bez sezon nasazená, 435 zařazení převedeno | Vyčkat na poslední nový export, provést schválený rozdílový sync a role/UAT. E8 odstranění legacy schématu až po přejímce a bezpečné obnově. Počáteční datum ani import neopakovat. | Vlastník dodá export; [pracovní plán](MEMBER_EVIDENCE_WORK_PLAN_2026-11-02.md), [provoz](MEMBER_EVIDENCE_OPERATION_2026-11-02.md), [převod](verification/member-evidence-cutover-20261102/RESULTS.md) |
| P07 | Fio import/automatika a SumUp zapnuté; skutečný hostingový plán ověřený | Doložit skutečnou zaplacenou úhradu a přihlášený průchod. 45 pohybů bez odpovídajícího VS ponechat ručnímu posouzení, nepárovat jen částkou. | Vlastník/hospodář; [aktivace](verification/live-payments-20261105/ACTIVATION.md), [45 pohybů](verification/fio-followup-20261105/RESULTS.md) |
| P08 | Trenérská obnova a syntetický pilot připravené; část UAT doložená | Dokončit asistované UAT a lidské převzetí; heslo a T07 zůstávají na výslovné přání odložené. Pilot neprodlužovat automaticky. | Vlastník/tester; [pilot](TRAINER_PILOT_2026-10-31.md), [scénáře](TRAINER_UAT_POSTUP_2026-11-01.md) |
| P09 | Technický refaktoring W6–W15 uzavřený; provozní přejímka není kompletní | Lidská/účetní D12–D14, vazby, dohody a skutečné ocenění; dořešit dvě známé chybějící přílohy původní Evidence. | Vlastník/účetní; [technické uzavření](REFACTORING_CLOSURE_2026-10-30.md), [rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot D12–D14](CLUB_PILOT_D12_D14.md), [obnova/přílohy](verification/bar-cutover-20261106/RESULTS.md) |
| P10 | Lokální pracovní zbytky a staré větve nejsou frontou nasazení | Posoudit popisky legacy soupisek a staré dokumentační WIP proti main, archivovat už začleněné větve až po potvrzení zachování práce. Neprovádět plošný merge/reset/clean. | Vývoj/vlastník WIP; [inventura](verification/operations-inventory-20261106/RESULTS.md) |
| P11 | Další optimalizace jsou kandidáti, nikoli slíbená nová vlna | Plošné měření K0.2 a případné dávkování kapacit zvažovat jen podle měření a samostatného rozsahu. | Vývoj/vlastník; [komplexní plán](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md), [uzavření](REFACTORING_CLOSURE_2026-10-30.md) |
| P12 | Další pracovní upozornění jsou záměr, konkrétní typy nejsou vybrané | Určit události, příjemce, vznik/ukončení úkolu, bezpečný odkaz, deduplikaci a testy. Push a eskalace nejsou součástí dokončené první verze. | Vlastník/vývoj; [registr typů a hranice](STAFF_NOTIFICATIONS_2026-11-06.md) |
| P13 | Předání #333 zmiňuje balíček pro testerku ve sdíleném ChatGPT projektu; dokončení nedoložené | Dohledat existující instrukce asistenta, průvodce, scénáře a hlášení chyb; propojit do dokumentace. Způsob sdílení a čerpání z GitHubu ověřit před navázáním. Tato relace externí projekt nezakládá. | Vlastník/testerka; [původní předání](verification/documentation-reconcile-20261106/WORK_PLAN_SOURCE_333.md), [uživatelské testování](PRODUKCNI-UZIVATELSKE-TESTOVANI.md) |
| P14 | Při dnešním nasazení ověřená záloha 385 tabulek / 2 triggery; její samostatná obnova nebyla provedena | Při další schválené technické přejímce doložit obnovu konkrétní dnešní zálohy v izolaci. Historické úspěšné obnovy jiných záloh za ni nevydávat; produkci neobnovovat. | Vývoj/provoz; [doklad dnešního vydání](verification/staff-notifications-release-20261106/RESULTS.md), [historická obnova BAR](verification/bar-cutover-20261106/RESULTS.md) |

## Dokončené položky, které znovu neotvírat podle názvu větve

- Omluvenky a náhrady: nasazené, kontrola 6. 11. PASS 22 testů / 139 assertions,
  23 HTTP scénářů a místní browser. [Důkazy](verification/makeup-audit-20261106/RESULTS.md),
  dokumentace #331 sloučená. Žádná čekající oprava; přihlášené produkční UAT oddělené.
- Měsíční členství a zastupování trenérů: společné PR #314 a
  [vydání](verification/monthly-coach-release-20261105/RESULTS.md); samostatné starší větve neslučovat znovu.
- BAR B0–B6, převod B7 a aliasy: [vydání](verification/bar-release-20261105/RESULTS.md),
  [převod](verification/bar-cutover-20261106/RESULTS.md), [přejímka](verification/bar-acceptance-20261106/RESULTS.md).
  Import/striktní nulový počátek neopakovat přes vzniklé legitimní záznamy.

## Povinný záznam při každé další práci

Trenérské přiřazení po #314 určuje přednost výběru, nikoli možnost zastupovat.
Chybějící rozsahy v P09 se týkají samostatných soukromých a pracovních agend;
nedoplňovat je jen kvůli odblokování již dostupného tréninkového zápisu.
U členství zachovat schválené chování: plná cena i během měsíce, ukončení
ke konci měsíce, více disciplín bez příplatku a zachování jednorázových nákupů.

Ještě při zahájení zapsat položku nebo odkaz na existující plán: zadání, rozsah
oprávnění, větev/worktree, stav, další krok, závislost a související dokumenty.
Při změně výsledku aktualizovat tento registr a tematický plán; před předáním
[handoff](HANDOFF_CURRENT.md). Release/flags zapisovat do CURRENT_STATE s důkazem,
nikdy neodvozovat nasazení z merge nebo zeleného CI. Výsledky ukládat do
`docs/verification/` a propojit z tematického dokumentu; ne pouze do chatu nebo lokálního output.
Plán není souhlas s publikací, reálnou platbou, rozesílkou nebo datovým převodem.
Tajemství, hesla, session, produkční dumpy a osobní přílohy do Gitu nepatří;
uvést jen bezpečnou referenci a způsob ověření soukromého podkladu.

## Historická dokumentační session #334 — 6. 11. 2026

Rozsah: souborový registr, provozní stav, handoffy, rozcestníky a projektové
instrukce na výslovný pokyn vlastníka; bez nové provozní aktivace.
Větev `codex/operations-handoff-register-20261106`, pracovní kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-operations-handoff-20261106`,
[PR #334](https://github.com/KovoPraha/evidenceTreninku/pull/334).
Soubory aktualizované; místní kontrola dokumentace, archivních bloků a odkazů PASS.
Stav povinného CI a sloučení ověřit u PR; dokumentační merge nevyžaduje deploy.
Následující session pokračuje věcnými položkami P01–P11 podle nového zadání,
nevytváří novou provozní operaci jen kvůli aktualizaci dokumentace.

CI při dokumentační aktualizaci zachytilo vypuštěnou identitu staršího BAR vydání
vedle zachovaného historického srovnání. Doplněny zpět historické SHA/run ID
s výslovným rozlišením od nového nasazení; bez změny testovacího kontraktu.

## Aktuální session: sjednocení #333/#334 podle ověřené reality

Zadání: aktualizovat dokumentaci a session handoff, odstranit rozpory a jasně
zapsat zbývající kroky. Výslovné omezení: zachovat WIP a nic nového na produkci
nezapínat. Rozsah je pouze dokumentační; žádná publikace, zpráva ani deploy.
Větev `codex/documentation-reconcile-20261106`, izolovaná pracovní kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-documentation-reconcile-20261106`.
Původní pracovní kopie i obě souběžné větve zůstávají zachované.

Dokončené sjednocení obsahu: P01 produkční zvoneček již ověřený; P03 podle
novějšího souběžného #335 již zveřejněná nabídka, ověřeno anonymním čtením;
P05 dřívější
souhlas zachovaný, publikace neověřená, první automatický start nedoložený;
P04 účetní adaptér dosud vyžaduje implementaci. Převzaté další úkoly #333
jsou P12 a P13, mez obnovy aktuální zálohy P14. Aktuální stav, handoff,
session ledger a oba rozcestníky používají jediný registr.
Přesné kontroly, stav PR/merge a důkazní hranice vede
[protokol sjednocení](verification/documentation-reconcile-20261106/RESULTS.md).
