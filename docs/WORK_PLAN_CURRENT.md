# Průběžný plán, registr rozpracované a plánované práce

Aktualizováno 6. 11. 2026, Europe/Prague. Výchozí ověřený main `9c543ff9`.
Tento soubor průběžně eviduje práci a další kroky; živé SHA/flags a release důkazy
jsou pouze v [CURRENT_STATE](CURRENT_STATE.md). Převzetí řídí
[HANDOFF_CURRENT](HANDOFF_CURRENT.md), datované podklady
[provozní inventura](verification/operations-inventory-20261106/RESULTS.md).
Po novém merge/deploy/provozní operaci stav znovu ověřit, nikoli převzít z názvu větve.
Jde o jediný průběžný seznam práce; [WORK_REGISTER](WORK_REGISTER.md)
odkazuje sem. Sjednocení #333/#334 dokládá [protokol](verification/documentation-reconcile-20261106/RESULTS.md).
Následné zadání vlastníka 6. 11.: pokračovat plně autonomně přes P01–P14.
Aktivní průchod a bezpečně doložené výsledky vede [protokol](verification/autonomous-p01-p14-20261106/RESULTS.md).
Dřívější dokumentační rozsah je historický; chybějící skutečné podklady se nedoplňují odhadem.

## Aktivní návaznosti

| ID | Oblast a stav | Další krok a závislost | Vlastník / podklady |
| --- | --- | --- | --- |
| P01 | HOTOVO: pracovní upozornění #330 nasazená, minutový běh a přihlášený zvoneček ověřené; vlastník 6. 11. potvrdil doručení zprávy do schránky | Neposílat další kopie ani opakovat instalaci. Potvrzení se týká skutečně přijaté zprávy, nikoli všech 18 zásilek. Zdrojové žádosti neschvalovat kvůli testu. | Vlastník; [potvrzení](verification/membership-copy-wip-20261106/RESULTS.md), [produkční přejímka](verification/staff-notifications-release-20261106/RESULTS.md) |
| P02 | BAR nasazený a převedený; technická provozní přejímka PASS | Skutečná Lucka se přihlásí původním heslem; vlastník potvrdí fyzické zásoby, počátek kasy a přejímku prvního prodeje. Nevytvářet odhadované zásoby. | Vlastník/obsluha; [plán B0–B7](BAR_INTEGRATION_PLAN_2026-11-05.md), [přejímka](verification/bar-acceptance-20261106/RESULTS.md) |
| P03 | HOTOVO: nabídka BAR 1 / vydání 1 zveřejněná, 45 produktů a fotografií; novější doklad #335 nahrazuje původní koncept | Publikaci neopakovat a nezakládat další návrh. Úpravy nabídky až podle dalšího zadání; zásoby/kasa a skutečný prodej zůstávají P02. Přihlášené vytvoření tiskového PDF zatím NOT RUN. | Správce baru; [publikace](verification/bar-menu-publication-20261106/RESULTS.md), [session handoff](SESSION_HANDOFF_BAR_2026-11-06.md) |
| P04 | BAR má účetní export/kontrakt a návrh adaptéru; zápis do hlavního účetnictví dosud není dokončený | Odsouhlasit účetní pravidla, implementovat a převzít adaptér bez zdvojení peněz. Není to pouze čekání na deploy nebo přepínač. Skutečná bezhotovostní úhrada/vratka také nedoložená. | Vlastník/účetní, vývoj; [kontrakt](BAR_ACCOUNTING_CONTRACT_V1.md), [plán adaptéru](BAR_ACCOUNTING_ADAPTER_PLAN_2026-11-05.md) |
| P05 | Skutečný tok ODLOŽENO vlastníkem; [hostingový plán #341 nasazený a přirozené minutové tiky ověřené](verification/membership-scheduler-20261106/RESULTS.md), denně 05:23 UTC; redundantní GitHub schedule vypnutý.  Závodní tým 297 / varianta 951 schválený, aktivní a zveřejněný za 2200 Kč/měsíc; údaje TJ KOVO uložené. Novější #336 doložilo cestu ke sjednání. Plán/pošta povolené, ruční prázdný běh doložený | Neopakovat schválení/publikaci. Sjednání vyžaduje klubem ověřeného sportovce; použitý účet jej neměl. První skutečné sjednání/platba/PDF/doručení zůstávají odložené. Technická oprava časování hotová; příští ranní start a dlouhodobý provoz zatím nejsou dnešním tickem prokázané. Roční cena neurčená; textová oprava členských popisků #340 nasazena a ověřena (viz autorizované vydání níže). | Vlastník/účetní; [čtecí doklad #336](verification/racing-team-status-20261106/RESULTS.md), [runbook](MONTHLY_MEMBERSHIP_RUNBOOK.md), [pošta](MEMBERSHIP_MAIL_ACTIVATION_2026-11-05.md), [historické založení](verification/membership-catalog-release-20261105/RESULTS.md) |
| P06 | Export/sync ODLOŽENO vlastníkem.  Evidence členů bez sezon nasazená, 435 zařazení převedeno | Vyčkat na poslední nový export, provést schválený rozdílový sync a role/UAT. E8 odstranění legacy schématu až po přejímce a bezpečné obnově. Počáteční datum ani import neopakovat. | Vlastník dodá export; [pracovní plán](MEMBER_EVIDENCE_WORK_PLAN_2026-11-02.md), [provoz](MEMBER_EVIDENCE_OPERATION_2026-11-02.md), [převod](verification/member-evidence-cutover-20261102/RESULTS.md) |
| P07 | Skutečná platba ODLOŽENO vlastníkem.  Fio import/automatika a SumUp zapnuté; skutečný hostingový plán ověřený | Doložit skutečnou zaplacenou úhradu a přihlášený průchod. 45 pohybů bez odpovídajícího VS ponechat ručnímu posouzení, nepárovat jen částkou. | Vlastník/hospodář; [aktivace](verification/live-payments-20261105/ACTIVATION.md), [45 pohybů](verification/fio-followup-20261105/RESULTS.md) |
| P08 | Trenérská obnova a syntetický pilot připravené; část UAT doložená | Dokončit asistované UAT a lidské převzetí; heslo a T07 zůstávají na výslovné přání odložené. Pilot neprodlužovat automaticky. | Vlastník/tester; [pilot](TRAINER_PILOT_2026-10-31.md), [scénáře](TRAINER_UAT_POSTUP_2026-11-01.md) |
| P09 | Technický refaktoring W6–W15 uzavřený; provozní přejímka není kompletní | Lidská/účetní D12–D14, vazby, dohody a skutečné ocenění; dořešit dvě známé chybějící přílohy původní Evidence. | Vlastník/účetní; [technické uzavření](REFACTORING_CLOSURE_2026-10-30.md), [rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot D12–D14](CLUB_PILOT_D12_D14.md), [obnova/přílohy](verification/bar-cutover-20261106/RESULTS.md) |
| P10 | Lokální pracovní zbytky a staré větve nejsou frontou nasazení | Posoudit popisky legacy soupisek a staré dokumentační WIP proti main, archivovat už začleněné větve až po potvrzení zachování práce. Neprovádět plošný merge/reset/clean. | Vývoj/vlastník WIP; [inventura](verification/operations-inventory-20261106/RESULTS.md) |
| P11 | Další optimalizace jsou kandidáti, nikoli slíbená nová vlna | Plošné měření K0.2 a případné dávkování kapacit zvažovat jen podle měření a samostatného rozsahu. | Vývoj/vlastník; [komplexní plán](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md), [uzavření](REFACTORING_CLOSURE_2026-10-30.md) |
| P12 | Datovaný pracovní balíček pro testerku z 5. 11. zachovaný v repozitáři; není důkaz sdílení projektu | Před použitím aktualizovat instrukce, průvodce, 20 scénářů a hlášení proti main. Ověřit aktuální možnosti sdílení ChatGPT a přístup testerky ke GitHubu. Tato relace externí projekt nezakládá ani nepřipojuje. | Vlastník/testerka; [balíček a hranice](testing/chatgpt-tester-20261105/README.md), [uživatelské testování](PRODUKCNI-UZIVATELSKE-TESTOVANI.md) |
| P13 | Další pracovní upozornění jsou záměr, konkrétní typy nejsou vybrané | Určit události, příjemce, vznik/ukončení úkolu, bezpečný odkaz, deduplikaci a testy. Push a eskalace nejsou součástí dokončené první verze. | Vlastník/vývoj; [registr typů a hranice](STAFF_NOTIFICATIONS_2026-11-06.md) |
| P14 | HOTOVO: konkrétní dnešní záloha i dvě předchozí generace obnovené, běh 42224275796.  Při dnešním nasazení ověřená záloha 385 tabulek / 2 triggery nyní skutečně obnovená v izolaci | [Přesná obnova doložená](verification/autonomous-p01-p14-20261106/restore-evidence.txt). Historické úspěšné obnovy jiných záloh za ni nevydávat; produkci neobnovovat. | Vývoj/provoz; [doklad dnešního vydání](verification/staff-notifications-release-20261106/RESULTS.md), [historická obnova BAR](verification/bar-cutover-20261106/RESULTS.md) |

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
P05 podle novějšího #336 již schválený a zveřejněný tarif, první automatický start nedoložený;
P04 účetní adaptér dosud vyžaduje implementaci. Převzaté další úkoly #333
jsou balíček testerky P12 a další upozornění P13, mez obnovy aktuální zálohy P14. Aktuální stav, handoff,
session ledger a oba rozcestníky používají jediný registr.
Přesné kontroly, stav PR/merge a důkazní hranice vede
[protokol sjednocení](verification/documentation-reconcile-20261106/RESULTS.md).
Vzdálené předání: [PR #337](https://github.com/KovoPraha/evidenceTreninku/pull/337),
včetně novějších dokladů publikace BAR #335 a členství #336. Dokumentační
začlenění nevyžaduje deploy; stav CI/merge ověřit u PR. Původní větve zůstávají zachované.

## Doporučené pořadí dokončení a rozdělení práce — 6. 11. 2026

Následující pořadí vzniklo před autonomní vlnou #339. P01 a P14 jsou již hotové
a automatický start P05 doložený; aktuální stavy určuje první registr a navazující protokoly.

Zadání: projít P01–P14, určit pořadí podle závislostí a oddělit autonomní
technickou práci od chybějících skutečných údajů/rozhodnutí. Tato session
pouze připravuje přehled; nepovoluje nové platby, rozesílání nebo produkční zápisy.
Větev `codex/completion-priorities-20261106`, kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-operations-handoff-20261106`.

| Pořadí | ID | Samostatně při navázání práce | Potřebný vstup vlastníka / podmínka uzavření |
| --- | --- | --- | --- |
| 1 | P14 | Ověřit metadata konkrétní zálohy a provést izolovanou obnovu chráněným postupem, porovnat tabulky/triggery a zapsat důkaz. Nikdy neobnovovat produkci. | Obvykle bez nového věcného rozhodnutí; potřebné existující technické přístupy a dostupná záloha. |
| Hotovo | P01 | Vlastník následně potvrdil přijetí již odeslané zprávy do schránky. | Stejné potvrzení znovu nevyžadovat a neposílat další kopie. Nejde o doložení všech 18 doručení. |
| 2 | P05 — plán | Z historie spouštěče a heartbeat doložit skutečný automatický start; případnou technickou chybu reprodukovat a opravit. Samotný prázdný běh není selhání. | Bez nové ceny, publikace či schválení tarifu; tyto kroky jsou hotové. Nevyvolávat skutečné předpisy/poštu jen kvůli dokazování plánu. |
| 2 | P12 | Aktualizovat dokumenty, 20 scénářů a hlášení proti main; ověřit aktuální možnosti sdílení při konkrétní realizaci. | Určit testerku, její roli/prostředí a povolené scénáře; připojení jejího účtu a přístup ke GitHubu dokončí oprávněná osoba. |
| 3 | P05 + P07 — skutečný tok | Připravit scénář, sledovat objednávku/závazek, VS, párování, PDF a technický výsledek doručení; bez duplicitních úhrad. | Určit konkrétní skutečné sjednání, ověřenou osobu/plátce a provést skutečnou platbu. Bankovní přístup ani účetní shodu neodhadovat; 45 nejasných pohybů posuzuje hospodář. |
| 3, souběžně | P02 | Připravit krokový checklist Lucky, zásob, vlastní kasy, prodeje, kelímků, storna a uzávěrky; porovnat uložené výsledky. | Lucka se přihlásí sama; dodat fyzicky spočtené zásoby, skutečný počáteční vklad a obsluhu první reálné směny. Heslo nevyžadovat v chatu. |
| 4 | P04 | Zkontrolovat kontrakt/export, připravit technický návrh, syntetické dávky a testy opakování/souběhu; po specifikaci implementovat adaptér. | Vlastník/účetní určí cílové doklady, řady, účty/středisko, daňová pravidla, ocenění, zálohy/spotřebu, poplatky, vratky a uzávěry. Schválení konkrétních finančních zápisů je samostatné. |
| 4, po exportu | P06 | Připravit a ověřit rozdílový náhled nad dodaným exportem, role/UAT a důkazy obnovy. | Nový export a potvrzení konkrétního rozdílu při skutečné změně dat; E8 až po přejímce. Převod 435 osob nezačínat znovu. |
| 4, souběžně | P08 | Dokončit nezávislé technické a syntetické scénáře a checklist dostupných rolí. | Účast vlastníka/trenéra v lidské přejímce. Změna hesla a T07 zůstávají odložené, dokud vlastník tento pokyn nezmění; pilot neprodlužovat automaticky. |
| 5 | P09 | Připravit přejímací matici D12–D14, dohledat dvě chybějící přílohy v dostupných řízených podkladech a sepsat rozpory. | Klub/účetní dodá skutečné dohody, částky, ocenění, vazby/kompetence a přijme výsledek. Chybějící přílohy nebo identity nevyrábět. |
| Průběžně | P10 | Porovnat staré větve/WIP s main, určit už převzaté změny a připravit seznam bezpečného úklidu. | Před odstraněním nezachované rozpracovanosti potřebujeme stanovisko jejího vlastníka; analýza není důvod k plošnému resetu či mazání. |
| Poté | P11 | Změřit K0.2 a kapacity na syntetických datech, navrhnout jen prokazatelně přínosné úpravy. | Vlastník určí prioritu případné další vlny; není to podmínka běžného provozu již dokončených funkcí. |
| Poté | P13 | Připravit varianty rozšíření a technickou specifikaci registrované události. | Vybrat konkrétní události, příjemce, okamžik upozornění a požadovaný kanál; samotný obecný záměr není zadání všech typů. |
| Hotovo | P03 | Publikaci neprovádět znovu. Lze doplnit přihlášený průchod tiskového PDF bez změny nabídky. | Žádný další údaj pro současnou publikaci; změna obsahu nebo ceny vyžaduje konkrétní nové zadání. |

Začít samostatnou technickou vlnou P14, čtecím ověřením plánu P05 a přípravou
P12/checklistů P02. P01 je následným potvrzením vlastníka splněné. Poté spojit skutečné
členství P05 s platbou P07 v jediném scénáři; BAR P02 je nezávislá provozní větev.
P04 vyžaduje účetní specifikaci, P06 export a E8 zůstává až za přejímkou.
Nevyžadovat znovu souhlas s již zveřejněným BAR ani tarifem 2200 Kč/měsíc.

## Autonomní vlna přes všechny P

[Výsledky a meze](verification/autonomous-p01-p14-20261106/RESULTS.md),
[účetní a provozní podklady](verification/autonomous-p01-p14-20261106/READINESS.md).
P01/P14 hotové, P05 automatický běh doložený; skutečné P05/P07 a export P06
odložené vlastníkem. Účetní adaptér ani plošné K0.2 se neoznačují za hotové.

## Doplňující relace: členské popisky a místní WIP — 6. 11. 2026

Vlastník povolil autonomní pokračování a potvrdil P01 slovy „1 OK, doručeno“;
ostatní skutečné podklady dodá později. Souběžné vlákno „Zkontrolovat stav vývoje
a nasazení“ vede P01, automatický běh P05, obnovu P14 a hlavní technický průchod
ve větvi `codex/autonomous-p01-p14-20261106`. Jeho operace se neopakují.

Doplňující větev `codex/membership-copy-and-wip-audit-20261106`, kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-membership-copy-audit-20261106`:
[P10 inventura a úzká oprava názvosloví P05](verification/membership-copy-wip-20261106/RESULTS.md).
135 kopií posouzeno pouze čtením, žádný úklid/reset. Členské šablony mají
připravené správné popisky místo „kroužek/termín“; tato relace nic nenasazuje.
34 testů / 206 assertions a 8 případů / 62 kontrol výrazů šablon PASS.
Tento krok následně dokončen: #340 prošlo kontrolami, bylo sloučeno a po výslovném NASADIT nasazeno; viz následující záznam.

## Autorizované vydání členských popisků #340 — 6. 11. 2026

Vlastník výslovně zadal NASADIT pouze připravenou opravu #340 a ověřit ji na
produkci. Žádná další aktivace; WIP zachovat. Větev
`codex/membership-copy-release-20261106`, pracovní kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-membership-copy-release-20261106`.
[Přesný rozsah, průběh a výsledky](verification/membership-copy-release-20261106/RESULTS.md).
Hotovo: chráněný deploy 42236370197 SUCCESS na přesném commitu sloučeného #340,
UAT/import false. Čtecí audit před/po, konfigurace/flags, živý Chrome katalog/detail
a anonymní HTTP 4 stránky / 19 kontrol PASS; cena zachovaná a WIP nedotčené.
Záloha 387 tabulek / 2 triggery ověřena, schéma current bez nových migrací.
Žádná další aktivace. Zbývající skutečné sjednání/platba/PDF a podklady P05/P07
zůstávají odložené vlastníkem; tato textová oprava je neuzavírá.

## Spolehlivé denní spouštění členství

Aktuální zadání: [hostingový plán](MEMBERSHIP_SCHEDULER_2026-11-06.md).
P05/P07 se skutečnou platbou a export P06 nadále odložené; řeší se pouze
spolehlivost již povoleného plánovače a její důkazy. Hotovo: #341 sloučené a nasazené, hosting instalovaný, přirozené tiky a zachování konfigurace PASS; [výsledek](verification/membership-scheduler-20261106/RESULTS.md). Budoucí ranní běh nepřehrávat kvůli testu.


### Naplánovaný audit prvního rána

[Ranní audit 7. 11.](MEMBERSHIP_SCHEDULER_MORNING_AUDIT_2026-11-07.md),
06:30 CET / 05:30 UTC, aktivní jednorázová navazující úloha
`rann-audit-lenstv-7-11`. Pověření: ověřit skutečný start vůči 05:23 UTC a
zapsat výsledek přímo do dokumentace/handoffu. Výsledek zatím NOT RUN;
žádný nový apply, konfigurace, skutečné P05/P07 ani export P06.
