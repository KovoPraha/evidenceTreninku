# Průběžný plán, registr rozpracované a plánované práce

Aktualizováno 6. 11. 2026, Europe/Prague. Výchozí ověřený main `9c543ff9`.
Toto je jediný kanonický průběžný registr práce a dalších kroků; živé SHA/flags a release důkazy
jsou pouze v [CURRENT_STATE](CURRENT_STATE.md). Převzetí řídí
[HANDOFF_CURRENT](HANDOFF_CURRENT.md), datované podklady
[provozní inventura](verification/operations-inventory-20261106/RESULTS.md).
Po novém merge/deploy/provozní operaci stav znovu ověřit, nikoli převzít z názvu větve.

## Aktivní návaznosti

| ID | Oblast a stav | Další krok a závislost | Vlastník / podklady |
| --- | --- | --- | --- |
| P01 | Pracovní upozornění #330 nasazená; minutový běh, 18 sent a přihlášený produkční zvoneček/přesný odkaz/přečtení doložené | Získat nezávislé potvrzení schránky; příjem transportem není doručení. Neposílat další kopie kvůli potvrzení. Zdrojové žádosti neschvalovat kvůli testu. | Navazující práce upozornění; [postup](STAFF_NOTIFICATIONS_2026-11-06.md), [produkční přejímka](verification/staff-notifications-release-20261106/RESULTS.md) |
| P02 | BAR nasazený a převedený; technická provozní přejímka PASS | Skutečná Lucka se přihlásí původním heslem; vlastník potvrdí fyzické zásoby, počátek kasy a přejímku prvního prodeje. Nevytvářet odhadované zásoby. | Vlastník/obsluha; [plán B0–B7](BAR_INTEGRATION_PLAN_2026-11-05.md), [přejímka](verification/bar-acceptance-20261106/RESULTS.md) |
| P03 | BAR nabídka 1 / Bar KOVO – základní nabídka zveřejněná, vydání 1, 45 produktů a fotografií; dokončeno v #335 | Publikaci neopakovat kvůli testu a nezakládat duplicitní návrh. Provozní zahájení zásob/kasy je stále P02. | [Publikační plán](BAR_MENU_PUBLICATION_2026-11-06.md), [živé důkazy](verification/bar-menu-publication-20261106/RESULTS.md), [session BAR](SESSION_HANDOFF_BAR_2026-11-06.md) |
| P04 | BAR účetní kontrakt a návrh adaptéru připravené, skutečné účtování neaktivované | Odsouhlasit finanční pravidla a dokončit schválený adaptér/přejímku bez zdvojení tržeb; skutečná bezhotovostní platba/vratka není doložená. | Vlastník/účetní, vývoj; [kontrakt](BAR_ACCOUNTING_CONTRACT_V1.md), [plán adaptéru](BAR_ACCOUNTING_ADAPTER_PLAN_2026-11-05.md) |
| P05 | Měsíční členství a zastupování trenérů nasazené; plán i pošta zapnuté | Vlastník už schválil vyplnění údajů TJ KOVO, tarif 2200 Kč/měsíc a publikaci. Ověřit současný stav varianty 951 a doplnit doklad provedení; nevytvářet další produkt ani znovu nežádat stejný souhlas. Doložit skutečné sjednání, předpis/PDF, příchozí platbu se shodným VS, přiřazení a doručení. Plná cena i během měsíce, konec ke konci měsíce, disciplíny bez příplatku; roční cena neurčená. | Vlastník/účetní; [runbook](MONTHLY_MEMBERSHIP_RUNBOOK.md), [pošta](MEMBERSHIP_MAIL_ACTIVATION_2026-11-05.md), [tarify](verification/membership-catalog-release-20261105/RESULTS.md) |
| P06 | Evidence členů bez sezon nasazená, 435 zařazení převedeno | Vyčkat na poslední nový export, provést schválený rozdílový sync a role/UAT. E8 odstranění legacy schématu až po přejímce a bezpečné obnově. Počáteční datum ani import neopakovat. | Vlastník dodá export; [pracovní plán](MEMBER_EVIDENCE_WORK_PLAN_2026-11-02.md), [provoz](MEMBER_EVIDENCE_OPERATION_2026-11-02.md), [převod](verification/member-evidence-cutover-20261102/RESULTS.md) |
| P07 | Fio import/automatika a SumUp zapnuté; skutečný hostingový plán ověřený | Doložit skutečnou zaplacenou úhradu a přihlášený průchod. 45 pohybů bez odpovídajícího VS ponechat ručnímu posouzení, nepárovat jen částkou. | Vlastník/hospodář; [aktivace](verification/live-payments-20261105/ACTIVATION.md), [45 pohybů](verification/fio-followup-20261105/RESULTS.md) |
| P08 | Trenérská obnova a syntetický pilot připravené; část UAT doložená | Dokončit asistované UAT a lidské převzetí; heslo a T07 zůstávají na výslovné přání odložené. Pilot neprodlužovat automaticky. | Vlastník/tester; [pilot](TRAINER_PILOT_2026-10-31.md), [scénáře](TRAINER_UAT_POSTUP_2026-11-01.md) |
| P09 | Technický refaktoring W6–W15 uzavřený; provozní přejímka není kompletní | Lidská/účetní D12–D14, vazby, dohody a skutečné ocenění; dořešit dvě známé chybějící přílohy původní Evidence. | Vlastník/účetní; [technické uzavření](REFACTORING_CLOSURE_2026-10-30.md), [rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot D12–D14](CLUB_PILOT_D12_D14.md), [obnova/přílohy](verification/bar-cutover-20261106/RESULTS.md) |
| P10 | Lokální pracovní zbytky a staré větve nejsou frontou nasazení | Posoudit popisky legacy soupisek a staré dokumentační WIP proti main, archivovat už začleněné větve až po potvrzení zachování práce. Neprovádět plošný merge/reset/clean. | Vývoj/vlastník WIP; [inventura](verification/operations-inventory-20261106/RESULTS.md) |
| P11 | Další optimalizace jsou kandidáti, nikoli slíbená nová vlna | Plošné měření K0.2 a případné dávkování kapacit zvažovat jen podle měření a samostatného rozsahu. | Vývoj/vlastník; [komplexní plán](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md), [uzavření](REFACTORING_CLOSURE_2026-10-30.md) |

| P12 | Sdílený asistent pro testerku: pracovní balíček z 5. 11. je zachovaný v repozitáři | Před skutečným sdílením aktualizovat scénáře proti main a ověřit aktuální možnosti ChatGPT a přístup testerky ke GitHubu. Balíček není důkaz dokončeného sdílení ani živého připojení. | Vlastník/testerka; [balíček a hranice](testing/chatgpt-tester-20261105/README.md), [produkční testování](PRODUKCNI-UZIVATELSKE-TESTOVANI.md) |
| P13 | Další typy pracovních upozornění jsou záměr, nikoli vybraný rozsah | Před rozšířením určit typ, vlastníka, příjemce, bezpečný odkaz, vznik/ukončení, deduplikaci a testy. Push/eskalace nejsou součástí první verze. | Vlastník/vývoj; [registr a hranice](STAFF_NOTIFICATIONS_2026-11-06.md) |

## Dokončené položky, které znovu neotvírat podle názvu větve

- Omluvenky a náhrady: nasazené, kontrola 6. 11. PASS 22 testů / 139 assertions,
  23 HTTP scénářů a místní browser. [Důkazy](verification/makeup-audit-20261106/RESULTS.md),
  dokumentace #331 sloučená. Žádná čekající oprava; přihlášené produkční UAT oddělené.
- Měsíční členství a zastupování trenérů: společné PR #314 a
  [vydání](verification/monthly-coach-release-20261105/RESULTS.md); samostatné starší větve neslučovat znovu.
- BAR B0–B6, převod B7 a aliasy: [vydání](verification/bar-release-20261105/RESULTS.md),
  [převod](verification/bar-cutover-20261106/RESULTS.md), [přejímka](verification/bar-acceptance-20261106/RESULTS.md).
  Import/striktní nulový počátek neopakovat přes vzniklé legitimní záznamy.

Průběžnou dokumentaci závazně upravují [pravidla souborového zápisu](WORK_DOCUMENTATION_POLICY.md).

## Povinný záznam při každé další práci

Ještě při zahájení zapsat položku nebo odkaz na existující plán: zadání, rozsah
oprávnění, větev/worktree, stav, další krok, závislost a související dokumenty.
Při změně výsledku aktualizovat tento registr a tematický plán; před předáním
[handoff](HANDOFF_CURRENT.md). Release/flags zapisovat do CURRENT_STATE s důkazem,
nikdy neodvozovat nasazení z merge nebo zeleného CI. Výsledky ukládat do
`docs/verification/` a propojit z tematického dokumentu; ne pouze do chatu nebo lokálního output.
Plán není souhlas s publikací, reálnou platbou, rozesílkou nebo datovým převodem.
Tajemství, hesla, session, produkční dumpy a osobní přílohy do Gitu nepatří;
uvést jen bezpečnou referenci a způsob ověření soukromého podkladu.

## Session: sjednocení dokumentace 6. 11. 2026

Rozsah: souborový registr, provozní stav, handoffy, rozcestníky a projektové
instrukce na výslovný pokyn vlastníka; bez nové provozní aktivace.
Větev `codex/operations-handoff-register-20261106`, pracovní kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-operations-handoff-20261106`,
[PR #334](https://github.com/KovoPraha/evidenceTreninku/pull/334).
Soubory aktualizované; místní kontrola dokumentace, archivních bloků a odkazů PASS.
Stav povinného CI a sloučení ověřit u PR; dokumentační merge nevyžaduje deploy.
Mezitím začleněn dokumentační PR #333: zachované novější produkční důkazy, schválení tarifu a návaznosti, oba plány sjednocené do tohoto souboru.
Následující session pokračuje věcnými položkami P01–P11 podle nového zadání,
nevytváří novou provozní operaci jen kvůli aktualizaci dokumentace.

CI při dokumentační aktualizaci zachytilo vypuštěnou identitu staršího BAR vydání
vedle zachovaného historického srovnání. Doplněny zpět historické SHA/run ID
s výslovným rozlišením od nového nasazení; bez změny testovacího kontraktu.

## Předchozí relace upozornění

### Předání relace #333

- Upozornění: implementace, sloučení, nasazení, instalace minutového běhu a produkční zvoneček dokončené. [Plán](STAFF_NOTIFICATIONS_2026-11-06.md), [důkazy](verification/staff-notifications-release-20261106/RESULTS.md), [aktuální stav](CURRENT_STATE.md).
- Otevřená mez ověření: příjemce zatím nepotvrdil doručení do schránky. Transport přijal 18 skutečných zpráv; kvůli potvrzení neposílat další kopie. Případné potvrzení připsat do protokolu.
- Dokumentační práce této relace: souborový provozní stav, historie, pravidlo v CLAUDE, handoff a tento rozcestník. Pracovní větev `codex/staff-notifications-release-20261106`, izolovaná kopie `C:\Users\Marek\Documents\Codex\evidencePavel-staff-notifications-20261106`. Původní `C:\xampp\htdocs\evidencePavel` obsahuje nesouvisející rozpracovanost; nepřepisovat ji.
- Předání této dokumentace: [PR #333](https://github.com/KovoPraha/evidenceTreninku/pull/333). Kontrola 80 řádků / 814 odkazů / 104 archivních bloků bez chyby, dalších 111 místních odkazů bez chyby, dokumentační testy 2/14 PASS. PR #333 je nyní sloučený; původní průběh kontrol určuje odkazovaný PR. Dokumentační commit nevyžaduje další produkční deploy a nemění živé SHA.

## Následně začleněná relace BAR

[PR #335](https://github.com/KovoPraha/evidenceTreninku/pull/335) sloučený:
nabídka zveřejněná bez nového aplikačního deploye. Větev
`codex/bar-menu-publication-20261106`, kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-bar-integration-20261105`.
[Předání](SESSION_HANDOFF_BAR_2026-11-06.md) zachovává pořadí navazujících kroků.
P03 je nyní dokončená; předchozí datované doklady neveřejného návrhu jsou historie.
