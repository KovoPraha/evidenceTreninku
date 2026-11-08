# Průběžný plán, registr rozpracované a plánované práce

Aktualizováno 7. 11. 2026, Europe/Prague. Kontrola main `e36c9ba6`; produkční stav určují výsledkové protokoly.
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

## UX01 — celoroční nabídka stávajícího kroužku

Stav: KÓD NASAZEN, [PR #395](https://github.com/KovoPraha/evidenceTreninku/pull/395) v main jako `1c828fbf`, [deploy 42492821311](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42492821311) SUCCESS; 11/11 finální PR CI PASS. Vlastník: vývoj; ceny a ostré zveřejnění určuje vedení klubu. [Plán a postup pro testerku](club-programs.md#ux01--doplnění-celoroční-nabídky-ke-stávajícím-skupinám-8-11-2026), [release protokol](verification/year-offer-release-20261108/RESULTS.md), [rozhodnutí](DECISION_LOG.md#d2026-11-08-01). Přihlášené UAT náhledu a skutečný nákup NOT RUN. Produkční nabídky bez schválených cen nevytvářet.

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
| P02 | BAR nasazený a převedený; technická provozní přejímka PASS | Skutečná Lucka se přihlásí původním heslem; vlastník potvrdí fyzické zásoby, počátek kasy a přejímku prvního prodeje. Nevytvářet odhadované zásoby. | Vlastník/obsluha; [plán B0–B7](BAR_INTEGRATION_PLAN_2026-11-05.md), [přejímka](verification/bar-acceptance-20261106/RESULTS.md) |
| P03 | HOTOVO: nabídka BAR 1 / vydání 1 zveřejněná, 45 produktů a fotografií; novější doklad #335 nahrazuje původní koncept | Publikaci neopakovat a nezakládat další návrh. Úpravy nabídky až podle dalšího zadání; zásoby/kasa a skutečný prodej zůstávají P02. Přihlášené vytvoření tiskového PDF zatím NOT RUN. | Správce baru; [publikace](verification/bar-menu-publication-20261106/RESULTS.md), [session handoff](SESSION_HANDOFF_BAR_2026-11-06.md) |
| P04 | BAR má účetní export/kontrakt a návrh adaptéru; zápis do hlavního účetnictví dosud není dokončený | Odsouhlasit účetní pravidla, implementovat a převzít adaptér bez zdvojení peněz. Není to pouze čekání na deploy nebo přepínač. Skutečná bezhotovostní úhrada/vratka také nedoložená. | Vlastník/účetní, vývoj; [kontrakt](BAR_ACCOUNTING_CONTRACT_V1.md), [plán adaptéru](BAR_ACCOUNTING_ADAPTER_PLAN_2026-11-05.md) |
| P05 | Skutečný tok ODLOŽENO vlastníkem; [hostingový plán #341 nasazený a přirozené minutové tiky ověřené](verification/membership-scheduler-20261106/RESULTS.md), denně 05:23 UTC; redundantní GitHub schedule vypnutý.  Závodní tým 297 / varianta 951 schválený, aktivní a zveřejněný za 2200 Kč/měsíc; údaje TJ KOVO uložené. Novější #336 doložilo cestu ke sjednání. Plán/pošta povolené, ruční prázdný běh doložený | Neopakovat schválení/publikaci. Sjednání vyžaduje klubem ověřeného sportovce; použitý účet jej neměl. První skutečné sjednání/platba/PDF/doručení zůstávají odložené. Technická oprava časování hotová; časovou přejímku 7. 11. vede serverový plán níže, dlouhodobý provoz jediný audit neuzavírá. Roční cena neurčená; textová oprava členských popisků #340 nasazena a ověřena (viz [důkaz vydání](verification/membership-copy-release-20261106/RESULTS.md)). | Vlastník/účetní; [čtecí doklad #336](verification/racing-team-status-20261106/RESULTS.md), [runbook](MONTHLY_MEMBERSHIP_RUNBOOK.md), [pošta](MEMBERSHIP_MAIL_ACTIVATION_2026-11-05.md), [historické založení](verification/membership-catalog-release-20261105/RESULTS.md) |
| P06 | Export/sync ODLOŽENO vlastníkem.  Evidence členů bez sezon nasazená, 435 zařazení převedeno | Vyčkat na poslední nový export, provést schválený rozdílový sync a role/UAT. E8 odstranění legacy schématu až po přejímce a bezpečné obnově. Počáteční datum ani import neopakovat. | Vlastník dodá export; [pracovní plán](MEMBER_EVIDENCE_WORK_PLAN_2026-11-02.md), [provoz](MEMBER_EVIDENCE_OPERATION_2026-11-02.md), [převod](verification/member-evidence-cutover-20261102/RESULTS.md) |
| P07 | Skutečná platba ODLOŽENO vlastníkem.  Fio import/automatika a SumUp zapnuté; skutečný hostingový plán ověřený | Doložit skutečnou zaplacenou úhradu a přihlášený průchod. 45 pohybů bez odpovídajícího VS ponechat ručnímu posouzení, nepárovat jen částkou. | Vlastník/hospodář; [aktivace](verification/live-payments-20261105/ACTIVATION.md), [45 pohybů](verification/fio-followup-20261105/RESULTS.md) |
| P08 | Trenérská obnova a syntetický pilot připravené; část UAT doložená | [Navazující syntetická regrese 6. 11. PASS](verification/offline-followup-20261106/RESULTS.md); dokončit asistované UAT a lidské převzetí. Heslo a T07 zůstávají na výslovné přání odložené. Pilot neprodlužovat automaticky. | Vlastník/tester; [pilot](TRAINER_PILOT_2026-10-31.md), [scénáře](TRAINER_UAT_POSTUP_2026-11-01.md) |
| P09 | Technický refaktoring W6–W15 uzavřený; provozní přejímka není kompletní | Lidská/účetní D12–D14, vazby, dohody a skutečné ocenění; dořešit dvě známé chybějící přílohy původní Evidence. | Vlastník/účetní; [technické uzavření](REFACTORING_CLOSURE_2026-10-30.md), [rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot D12–D14](CLUB_PILOT_D12_D14.md), [obnova/přílohy](verification/bar-cutover-20261106/RESULTS.md) |
| P10 | HOTOVO: čtecí triáž 332 větví / 156 worktrees; 288 s důkazem začlenění, 41 k posouzení, 3 otevřená PR v datovaném snímku | Bez mazání/merge; vlastník posoudí skutečné WIP. Starý auditní checkout s 1564 chybějícími soubory není kandidát k vydání. | Vývoj/vlastník WIP; [nová inventura a meze](verification/autonomous-priorities-20261107/RESULTS.md) |
| P11 | [Celé HTTP/browser na feb8fe8c HOTOVO](verification/full-http-performance-20261107/RESULTS.md): 65 případů / 930 vzorků, 403 browser pozorování; role/filtry/revokace PASS. [Klubové ceny #358](MEMBER_PRICING_READ_PERFORMANCE_2026-11-07.md) zahrnuté v nasazení: 500 produktů 2000 → 4 SQL; R13/R05/R06 zahrnuje ROLE01. K0.2 otevřené. | Finální omezený výběr osob ověřený v PR #366; sloučeno jako `faae75e8`, bez našeho deploye. Celé R06 HTTP společné verze hotové v Linuxu i rozšířeném Windows páru; dále větší R13/R05 měření hotové (P11-SCALE), dále R13 čtení, mobil/souběh. [Katalog #364 nasazený](CATALOG_READ_PERFORMANCE_2026-11-07.md), vlastní větev `codex/catalog-read-performance-20261107`: parita a 480 párů 10.3/11.4 PASS; 5000 produktů 202/340 → 87/199 ms, PHP 71,6 → 16,8 MiB; release `c70939d4`, deploy 42372002993 SUCCESS; [protokol a meze](verification/catalog-release-20261107/RESULTS.md). R06 aktuální aplikace přeměřené, ostatní nové stránky tím nejsou přeměřené; [lekce #367 a provozní výsledek](verification/attendance-lesson-release-20261107/RESULTS.md). | Vývoj/vlastník; [předání plného HTTP / PR #363](SESSION_HANDOFF_FULL_HTTP_PERFORMANCE_2026-11-07.md), [předání katalogu](SESSION_HANDOFF_CATALOG_READ_PERFORMANCE_2026-11-07.md), [předání cen](SESSION_HANDOFF_MEMBER_PRICING_READ_PERFORMANCE_2026-11-07.md), [komplexní plán](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md) |
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
