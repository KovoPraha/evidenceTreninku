# Průběžný plán, registr rozpracované a plánované práce

Aktualizováno 7. 11. 2026, Europe/Prague. Kontrola main `269684c2`; produkční stav určují výsledkové protokoly.
Tento soubor průběžně eviduje práci a další kroky; živé SHA/flags a release důkazy
jsou pouze v [CURRENT_STATE](CURRENT_STATE.md). Převzetí řídí
[HANDOFF_CURRENT](HANDOFF_CURRENT.md), datované podklady
[provozní inventura](verification/operations-inventory-20261106/RESULTS.md).
Po novém merge/deploy/provozní operaci stav znovu ověřit, nikoli převzít z názvu větve.
Jde o jediný průběžný seznam práce, včetně konceptů a budoucích etap; [WORK_REGISTER](WORK_REGISTER.md)
odkazuje sem. Sjednocení #333/#334 dokládá [protokol](verification/documentation-reconcile-20261106/RESULTS.md).
Následné zadání vlastníka 6. 11.: pokračovat plně autonomně přes P01–P14.
Aktivní průchod a bezpečně doložené výsledky vede [protokol](verification/autonomous-p01-p14-20261106/RESULTS.md).
Dřívější dokumentační rozsah je historický; chybějící skutečné podklady se nedoplňují odhadem.

## Úplný katalog plánů

[Katalog všech plánů a konceptů](PLAN_CATALOG.md) je součást tohoto registru:
každý dokument má vlastní stabilní ID, stav, vazbu na P/C/DOC a důkaz nebo
výslovně otevřenou podmínku. [Úplný index textových podkladů](PLAN_DOCUMENT_INDEX.md)
zahrnuje i vložené ověřovací plány, historické prompty a podpůrné artefakty.
[Původní etapy 0–6 a R01–R44](plans/realization-20261022/README.md) jsou nyní
sdílené v Gitu; jejich historické „plánováno“ nepřebírat jako dnešní stav.

## Priority a závislosti

DOC03, 7. 11. 2026: stručný přehled na žádost vlastníka. Pořadí níže je doporučení
podle dopadu a závislostí, nikoli nové povolení plateb, importů nebo aktivací.
„Samostatně“ znamená v již schváleném rozsahu; otevřený bod není automaticky chyba.
Větev `codex/work-priorities-20261107`, vlastní worktree stejného tématu.

| Pořadí | Oblast a skutečně otevřený krok | Lze dokončit samostatně | Co čeká na vlastníka / člověka |
| --- | --- | --- | --- |
| 1 | P11 / ROLE01-MOB: uzavřít technická předání. [Lekce #367](ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md) už jsou v main; doložit schválené vhodné nasazení a kontrolu. U [mobilních karet](ACCESS_OVERVIEW_2026-11-07.md) zbývá cílený čtecí Chrome 390/360 px a desktop. | Vývoj dokončí vydání/ověření v původní relaci, sjednotí dokumentaci. [Katalog už nasazený](verification/catalog-release-20261107/RESULTS.md); vydání `c70939d4` obsahuje také karty #361 a ceny #358, nepřipravovat jejich duplicitní deploy. | Žádné nové zadání pro tyto schválené kroky. Výběr osob už má [PR #366](https://github.com/KovoPraha/evidenceTreninku/pull/366) a [plán ve větvi](https://github.com/KovoPraha/evidenceTreninku/blob/codex/attendance-person-picker-20261107/docs/ATTENDANCE_PERSON_PICKER_2026-11-07.md), dosud mimo main; nepřipravovat duplicitní implementaci. |
| 2 | P02: dokončit skutečný provoz BAR. [Plán B0–B7](BAR_INTEGRATION_PLAN_2026-11-05.md), [předání](SESSION_HANDOFF_BAR_2026-11-06.md). | Připravit a doprovodit přejímku; technická regrese hotová. | Lucka se přihlásí; vlastník/obsluha potvrdí fyzické zásoby, počáteční kasu a první prodej. |
| 3 | P04 / P09: propojit BAR s účetnictvím a uzavřít účetní podklady. [Adaptér](BAR_ACCOUNTING_ADAPTER_PLAN_2026-11-05.md), [D12–D14](CLUB_PILOT_D12_D14.md). Adaptér zatím není implementovaný. | Připravit technické mapování a testovací případy; účetní zápisy až po rozhodnutí. | Vlastník/účetní určí účty, DPH, ocenění, číselné řady, vazby úhrad a schválení. Doložit skutečné dohody/částky a dvě chybějící přílohy. |
| 4 | P05 / P07: skutečné sjednání členství, úhrada, PDF a doručení. [Runbook](MONTHLY_MEMBERSHIP_RUNBOOK.md), [doklady objednávek](SHOP_DOCUMENT_AUTOMATION_2026-11-07.md). | Lze připravit kontrolní scénář; automatika a doklady jsou aktivní, ranní čas už doložený. | Vlastník obnoví výslovně odložený skutečný test a poskytne vhodného ověřeného sportovce/objednávku; doložit platbu a příjem PDF ve schránce. |
| 5 | P06: poslední rozdílová synchronizace evidence. [Pracovní plán](MEMBER_EVIDENCE_WORK_PLAN_2026-11-02.md). | Po dodání podkladu připravit rozdílový náhled a kontroly. | Vlastník dodá nový export a obnoví odložený sync; E8 až po přejímce a bezpečné obnově. |
| 6 | P08 / P09 / P12: lidské trenérské a klubové UAT. [Scénáře](TRAINER_UAT_POSTUP_2026-11-01.md), [balíček testerky](testing/chatgpt-tester-20261105/README.md), [pilot D12–D14](CLUB_PILOT_D12_D14.md). | Doprovodit testera a evidovat výsledky; technická příprava hotová. | Určit testerku/testera, roli, prostředí a scénáře, zajistit skutečný přístup a převzetí. Heslo/T07 zůstávají odložené. |
| 7 | P14: zvážit izolovanou obnovu novější konkrétní zálohy. [Rozsah již ověřených obnov](verification/autonomous-p01-p14-20261106/RESULTS.md). | Vývoj/provoz může technicky ověřit novou dostupnou zálohu v izolaci. Předchozí tři generace už PASS; nejde o obnovu produkce. | Jen pokud chybí přístup k přesné záloze; starší PASS není důkaz nové generace. |
| 8 | P10 / C04: prověřit zbývající technický dluh a staré WIP. [Inventura](verification/operations-inventory-20261106/RESULTS.md), [kandidáti](DOZOR_UKLID_2026-10-25.md). | Porovnat s main, reprodukovat aktuální nález a navrhnout malý ověřitelný řez. Lze souběžně při čekání na podklady. | Vlastník WIP před archivací/mazáním; vlastník rozhodne hosting/kapacitu, fyzická TV vyžaduje místní kontrolu. |
| 9 | P13 / C01–C03: další upozornění, wallet, progres a nové integrace. [Typy upozornění](STAFF_NOTIFICATIONS_2026-11-06.md), [backlog](plan-eshop-tymova-evidence/11-backlog-hodnota-pro-cleny.md), koncepty níže. | Zpřesnit varianty a odhad; nic automaticky nezapínat. | Vlastník vybere konkrétní funkci, příjemce/scénář a podmínku přijetí; právní/retentní otázky řeší odpovědná osoba klubu. |

Hotové: pracovní upozornění P01, zveřejnění nabídky P03, technické jádro ROLE01,
[ranní audit 7. 11. PASS](verification/membership-scheduler-20261107/RESULTS.md)
a katalog #364. Jejich opakování není další prioritou. Technické body 1, 7 a 8
mohou běžet souběžně s dodáváním podkladů pro body 2–6; nečekají na nové produktové zadání.

## Aktivní návaznosti

P15: vlastník zvolil čtecí synchronizaci ČSC/Sportsoftu, UCI ID a nonstop stanici.
[Implementační plán](CSC_SPORTSOFT_READ_SYNC_PLAN_2026-11-07.md), [průzkum](verification/csc-sportsoft-discovery-20261107/RESULTS.md); dokumentace se připravuje, runtime/aktivace NOT RUN.

P07 N1/N2: [automatické doklady a komunikace](SHOP_DOCUMENT_AUTOMATION_2026-11-07.md)
jsou sloučené v #355, nasazené a zapnuté pro nové objednávky.
Místní browser/MariaDB celý tok PASS, 2337 testů / 43257 assertions a 11 CI PASS.
[Provozní výsledky a meze](verification/shop-document-automation-20261107/RESULTS.md).
Skutečná bankovní platba, externí schránka a produkční přejímka členství zůstávají samostatné.

| ID | Oblast a stav | Další krok a závislost | Vlastník / podklady |
| --- | --- | --- | --- |
| P15 | PLÁNOVÁNO: [čtecí ČSC/Sportsoft, párování UCI a nonstop stanice](CSC_SPORTSOFT_READ_SYNC_PLAN_2026-11-07.md) | S0: inventura stanice, minimální kontrakt a syntetické fixtures; pak S1 úložiště/resolver. Žádné změny portálů ani automatické přepisy karet. | Marek / vývoj; vlastní codex/csc-sportsoft-read-sync-plan-20261107; [důkazy a meze](verification/csc-sportsoft-discovery-20261107/RESULTS.md). Dokumentace lokální, PR bude doplněno. |
| ROLE01-MOB | ČÁSTEČNĚ HOTOVO: karty #361 jsou v main a obsažené ve vydání katalogu `c70939d4` | Doložit cílený nový čtecí Chrome 390/360 px a desktop; stávající role neměnit, deploy neopakovat jen podle starého statusu | Vývoj; [plán](ACCESS_OVERVIEW_2026-11-07.md), [vydání](verification/catalog-release-20261107/RESULTS.md), vlastní `codex/access-visual-uat-20261107` / PR #361 |
| P01 | HOTOVO: pracovní upozornění #330 nasazená, minutový běh a přihlášený zvoneček ověřené; vlastník 6. 11. potvrdil doručení zprávy do schránky | Neposílat další kopie ani opakovat instalaci. Potvrzení se týká skutečně přijaté zprávy, nikoli všech 18 zásilek. Zdrojové žádosti neschvalovat kvůli testu. | Vlastník; [potvrzení](verification/membership-copy-wip-20261106/RESULTS.md), [produkční přejímka](verification/staff-notifications-release-20261106/RESULTS.md) |
| P02 | BAR nasazený a převedený; technická provozní přejímka PASS | Skutečná Lucka se přihlásí původním heslem; vlastník potvrdí fyzické zásoby, počátek kasy a přejímku prvního prodeje. Nevytvářet odhadované zásoby. | Vlastník/obsluha; [plán B0–B7](BAR_INTEGRATION_PLAN_2026-11-05.md), [přejímka](verification/bar-acceptance-20261106/RESULTS.md) |
| P03 | HOTOVO: nabídka BAR 1 / vydání 1 zveřejněná, 45 produktů a fotografií; novější doklad #335 nahrazuje původní koncept | Publikaci neopakovat a nezakládat další návrh. Úpravy nabídky až podle dalšího zadání; zásoby/kasa a skutečný prodej zůstávají P02. Přihlášené vytvoření tiskového PDF zatím NOT RUN. | Správce baru; [publikace](verification/bar-menu-publication-20261106/RESULTS.md), [session handoff](SESSION_HANDOFF_BAR_2026-11-06.md) |
| P04 | BAR má účetní export/kontrakt a návrh adaptéru; zápis do hlavního účetnictví dosud není dokončený | Odsouhlasit účetní pravidla, implementovat a převzít adaptér bez zdvojení peněz. Není to pouze čekání na deploy nebo přepínač. Skutečná bezhotovostní úhrada/vratka také nedoložená. | Vlastník/účetní, vývoj; [kontrakt](BAR_ACCOUNTING_CONTRACT_V1.md), [plán adaptéru](BAR_ACCOUNTING_ADAPTER_PLAN_2026-11-05.md) |
| P05 | Skutečný tok ODLOŽENO vlastníkem; [hostingový plán #341 nasazený a přirozené minutové tiky ověřené](verification/membership-scheduler-20261106/RESULTS.md), denně 05:23 UTC; redundantní GitHub schedule vypnutý.  Závodní tým 297 / varianta 951 schválený, aktivní a zveřejněný za 2200 Kč/měsíc; údaje TJ KOVO uložené. Novější #336 doložilo cestu ke sjednání. Plán/pošta povolené, ruční prázdný běh doložený | Neopakovat schválení/publikaci. Sjednání vyžaduje klubem ověřeného sportovce; použitý účet jej neměl. První skutečné sjednání/platba/PDF/doručení zůstávají odložené. Technická oprava časování hotová; časovou přejímku 7. 11. vede serverový plán níže, dlouhodobý provoz jediný audit neuzavírá. Roční cena neurčená; textová oprava členských popisků #340 nasazena a ověřena (viz [důkaz vydání](verification/membership-copy-release-20261106/RESULTS.md)). | Vlastník/účetní; [čtecí doklad #336](verification/racing-team-status-20261106/RESULTS.md), [runbook](MONTHLY_MEMBERSHIP_RUNBOOK.md), [pošta](MEMBERSHIP_MAIL_ACTIVATION_2026-11-05.md), [historické založení](verification/membership-catalog-release-20261105/RESULTS.md) |
| P06 | Export/sync ODLOŽENO vlastníkem.  Evidence členů bez sezon nasazená, 435 zařazení převedeno | Vyčkat na poslední nový export, provést schválený rozdílový sync a role/UAT. E8 odstranění legacy schématu až po přejímce a bezpečné obnově. Počáteční datum ani import neopakovat. | Vlastník dodá export; [pracovní plán](MEMBER_EVIDENCE_WORK_PLAN_2026-11-02.md), [provoz](MEMBER_EVIDENCE_OPERATION_2026-11-02.md), [převod](verification/member-evidence-cutover-20261102/RESULTS.md) |
| P07 | Skutečná platba ODLOŽENO vlastníkem.  Fio import/automatika a SumUp zapnuté; skutečný hostingový plán ověřený | Doložit skutečnou zaplacenou úhradu a přihlášený průchod. 45 pohybů bez odpovídajícího VS ponechat ručnímu posouzení, nepárovat jen částkou. | Vlastník/hospodář; [aktivace](verification/live-payments-20261105/ACTIVATION.md), [45 pohybů](verification/fio-followup-20261105/RESULTS.md) |
| P08 | Trenérská obnova a syntetický pilot připravené; část UAT doložená | [Navazující syntetická regrese 6. 11. PASS](verification/offline-followup-20261106/RESULTS.md); dokončit asistované UAT a lidské převzetí. Heslo a T07 zůstávají na výslovné přání odložené. Pilot neprodlužovat automaticky. | Vlastník/tester; [pilot](TRAINER_PILOT_2026-10-31.md), [scénáře](TRAINER_UAT_POSTUP_2026-11-01.md) |
| P09 | Technický refaktoring W6–W15 uzavřený; provozní přejímka není kompletní | Lidská/účetní D12–D14, vazby, dohody a skutečné ocenění; dořešit dvě známé chybějící přílohy původní Evidence. | Vlastník/účetní; [technické uzavření](REFACTORING_CLOSURE_2026-10-30.md), [rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot D12–D14](CLUB_PILOT_D12_D14.md), [obnova/přílohy](verification/bar-cutover-20261106/RESULTS.md) |
| P10 | Lokální pracovní zbytky a staré větve nejsou frontou nasazení | Posoudit popisky legacy soupisek a staré dokumentační WIP proti main, archivovat už začleněné větve až po potvrzení zachování práce. Neprovádět plošný merge/reset/clean. | Vývoj/vlastník WIP; [inventura](verification/operations-inventory-20261106/RESULTS.md) |
| P11 | [Celé HTTP/browser na feb8fe8c HOTOVO](verification/full-http-performance-20261107/RESULTS.md): 65 případů / 930 vzorků, 403 browser pozorování; role/filtry/revokace PASS. [Klubové ceny #358](MEMBER_PRICING_READ_PERFORMANCE_2026-11-07.md) zahrnuté ve vydání katalogu `c70939d4`: 500 produktů 2000 → 4 SQL; R13/R05/R06 zahrnuje ROLE01. K0.2 otevřené. | Dále R06 omezený výběr osob (10221 voleb, 1,37 MB, cca 6 s) a lekce (203 SQL), R13/R05 objemy, mobil/souběh. [Katalog #364 nasazený](CATALOG_READ_PERFORMANCE_2026-11-07.md), vlastní větev `codex/catalog-read-performance-20261107`: parita a 480 párů 10.3/11.4 PASS; 5000 produktů 202/340 → 87/199 ms, PHP 71,6 → 16,8 MiB; release `c70939d4`, deploy 42372002993 SUCCESS; [protokol a meze](verification/catalog-release-20261107/RESULTS.md). Plné HTTP/browser měření novějšího main zůstává otevřené; katalogový deploy doložený. | Vývoj/vlastník; [předání plného HTTP / PR #363](SESSION_HANDOFF_FULL_HTTP_PERFORMANCE_2026-11-07.md), [předání katalogu](SESSION_HANDOFF_CATALOG_READ_PERFORMANCE_2026-11-07.md), [předání cen](SESSION_HANDOFF_MEMBER_PRICING_READ_PERFORMANCE_2026-11-07.md), [komplexní plán](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md) |
| P11-R06-LESSONS | [Dávkové lekce](ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md): implementace, parita, měření a merge #367 (`269684c2`) hotové; 19/993, 720 párů 10.3/11.4, 153 → 6 SQL | Doložit schválené vhodné nasazení a následnou kontrolu; writer nezměněný, pokračuje původní relace | Vývoj; `codex/attendance-lesson-read-performance-20261107`, [předání](SESSION_HANDOFF_ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md), PR #367 |
| P12 | Technická příprava HOTOVO: soubory 00–06, 20 scénářů, hlášení, mapa a spojený podklad sjednocené 6. 11. proti 3ff0fd13; přenosový ZIP ověřený. [Důkazy](verification/offline-followup-20261106/RESULTS.md). Není to provedené lidské UAT ani sdílení projektu | Vlastník určí testerku, roli/prostředí a konkrétní scénáře; oprávněná osoba dokončí pozvánku/přístup a kontrolní otázky v jejím účtu. Nové ověření hlavní verze před použitím; žádné opakování technické aktualizace bez změny podkladů. | Vlastník/testerka; [balíček a hranice](testing/chatgpt-tester-20261105/README.md), [uživatelské testování](PRODUKCNI-UZIVATELSKE-TESTOVANI.md) |
| P13 | Další pracovní upozornění jsou záměr, konkrétní typy nejsou vybrané | Určit události, příjemce, vznik/ukončení úkolu, bezpečný odkaz, deduplikaci a testy. Push a eskalace nejsou součástí dokončené první verze. | Vlastník/vývoj; [registr typů a hranice](STAFF_NOTIFICATIONS_2026-11-06.md) |
| P14 | HOTOVO pro tři konkrétní generace v běhu 42224275796, včetně zálohy evidence_2026-11-06_060929_014837d4.sql.gz (385 tabulek / 2 triggery). Pozdější záloha vydání #341 má 387 tabulek / 2 triggery, její hash ověřen, konkrétní obnova NOT RUN | [Přesná obnova doložená](verification/autonomous-p01-p14-20261106/restore-evidence.txt). Historické úspěšné obnovy jiných záloh za ni nevydávat; produkci neobnovovat. | Vývoj/provoz; [obnovované starší vydání](verification/staff-notifications-release-20261106/RESULTS.md), [novější záloha #341](verification/membership-scheduler-20261106/deploy-summary.json), [historická obnova BAR](verification/bar-cutover-20261106/RESULTS.md) |

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
