# Rozhodovací log a rejstřík uzavřených plánů

Společný vstup pro rozhodnutí napříč projektem. Aktuální stav je v
[CURRENT](CURRENT_STATE.md), otevřené koncepty/plány v [registru](WORK_PLAN_CURRENT.md),
časová posloupnost v [deníku](EVENT_LOG.md). Nová rozhodnutí přidávej s ID
`DYYYY-MM-DD-NN`; ID neměň. Starší doménová rozhodnutí nepřečíslovávej.
Při nahrazení doplň stav a odkaz na nástupce; zachovej původní důvody.

## D2026-11-07-13

PŘIJATO 7. 11. 2026 na přímé zadání vlastníka: [P11-SCALE](MEDICAL_PLANNER_SCALE_2026-11-07.md).
Měřit stejnou zmrazenou aplikaci 69f49033 při větším objemu; neimplementovat
souběžnou optimalizaci plánovače. Nový datadir, standardní login a SELECT-only
web účet, oddělené prosté HTTP časy a diagnostika se skutečnými rows examined
ze slow logu. Zdravotní růst a růst historie měnit odděleně; členství a šířku
hierarchie rozšířit společně a tuto mez uvést. Staré časy nejsou kontrolní skupina.
Žádné produkční změny, deploy ani nové zápisové UAT. [Předání](SESSION_HANDOFF_MEDICAL_PLANNER_SCALE_2026-11-07.md).

## D2026-11-07-11

PŘIJATO, 7.11.2026, nové přímé zadání vlastníka pro měření bez změny produkce.
Volba: nový jediný seed, původní d958fd6e proti společné faae75e8, všech9GET
scénářů ve skutečném Chrome a 21HTTP včetně negativních rolí. Staré časy nejsou
kontrolní skupina; oddělená SQL diagnostika, 20doménových checksumů, JS/noJS,
zápisové UAT až po časování a vlastní úklid. Aplikace se nemění.
[Výsledek](verification/attendance-integrated-http-20261107/RESULTS.md):540HTTP/234Chrome,
role/drafty/kanonické zápisy PASS. [Plán](ATTENDANCE_PERSON_PICKER_2026-11-07.md),
[předání](SESSION_HANDOFF_ATTENDANCE_HTTP_2026-11-07.md). K0.2 zůstává otevřené.
Přijaté D10 je jiné pověření k deployi #372; nepřenáší se na tuto relaci.
Původní pracovní D10/E30 v3ca7e028 kolidovaly s přijatými ID, nyní D11/E36/E37.

## D2026-11-07-09

- **Stav / datum:** PŘIJATO, 7. 11. 2026; vlastník zadal optimalizovat výběr osob
  podle měření, ověřit stejná syntetická data a zachovat chování/WIP bez produkce.
- **Volba / důvod:** omezená hledaná stránka plus všechny již vybrané osoby;
  nativní POST hledání zachová práci i bez JavaScriptu. AJAX není pro omezení
  HTML nutný a přidal by další asynchronní stav/endpoint.
  Změna neomezuje hosty a nedotýká se kanonického writeru ani oprávnění.
- **Varianty:** pouhé skrytí dlouhého seznamu nezmenší HTML; vlastní soupiska
  jako hranice by změnila chování. Cache oprávnění není přípustná.
- **Akceptace / stav:** [plán a průběh](ATTENDANCE_PERSON_PICKER_2026-11-07.md),
  implementace/místní měření/UAT HOTOVO; větev `codex/attendance-person-picker-20261107`,
  [PR #366](https://github.com/KovoPraha/evidenceTreninku/pull/366), finální CI/merge u PR.

Pracovní ID 06/07 kolidovalo s přijatým DOC02/katalogem; ta ID zůstávají,
vlastní rozhodnutí je 09. Historické commitové doklady se nemění.

Doplnění před přijetím PR #366: dvě nativní formy nepředávají neodeslaná pole
sousední části. Volba se zpřesňuje na společný formulář s oddělenými prefixy
omluvy a původní serverovou validací. Nový úplný pár měření a JS/noJS
regrese obou současných draftů PASS; předběžný pár zůstává historický; bez produkce.

[Finální důkazy](verification/attendance-person-picker-20261107/final/RESULTS.md);
#367 dávkové lekce převzaté, celé integrované HTTP zatím nepřeměřené.


Doplnění integračního měřidla: starý celostránkový cap45 je po započtení nové
UI projekce a #367 nahrazen přesným cap56. Původní validator budget, cap1000řádků,
HTML/data parita a relativní úspora zůstávají; vadné reporty jsou odmítané.


## D2026-11-07-05

- **Stav / datum:** PŘIJATO, 7. 11. 2026; autonomní měření podle pokynu vlastníka bez produkčních změn.
- **Volba:** standardní HTTP/UI přihlášení a celé stránky, další pozice/filtry,
  oddělené nezinstrumentované časy a diagnostika, stejné syntetické schéma.
- **Důvod / alternativa:** izolovaný PHP profil neobsahuje bootstrap/navigaci ani
  práci prohlížeče. Lokální časy nejsou produkční SLA; samotný SQL count neurčuje příčinu.
- **Dopad:** 65 HTTP případů a 403 browser pozorování hotové; další priorita
  R06 výběr osob / lekce, nikoli neověřená další optimalizace nebo nasazení.
  Měřená verze feb8fe8c zůstává oddělená od souběžného main 37d0ad68 / ROLE01.
- **Podklady:** [výsledky](verification/full-http-performance-20261107/RESULTS.md),
  [předání a otevřené etapy](SESSION_HANDOFF_FULL_HTTP_PERFORMANCE_2026-11-07.md),
  [událost](EVENT_LOG.md). Větev `codex/full-http-performance-20261107`; [PR #363](https://github.com/KovoPraha/evidenceTreninku/pull/363), CI/merge u PR.

## D2026-11-07-01

- **Stav / datum:** PŘIJATO, 7. 11. 2026. Rozhodl vlastník explicitním zadáním.
- **Otázka:** kde uchovávat plány, koncepty, změny, zamýšlené postupy a rozhodnutí,
  aby na ně mohli navazovat všichni vývojáři přes GitHub bez původního vlákna?
- **Volba a důvod:** verzované tematické soubory jsou projektovou evidencí;
  CURRENT a hlavní session handoff jsou malé průběžně udržované rozcestníky.
  Historii a uzavřené plány zpřístupňují deník, tento log a propojené archivy.
- **Varianty:** paměť/chats samotné nejsou dostupné všem; kumulování historie
  v handoffu prodlužovalo načítání a směšovalo staré a aktuální výsledky.
- **Dopad:** povinné načtení/zápis souborů před implementací, při změně i před
  předáním; limity CURRENT/HANDOFF 80 řádků, kompatibilní session cesta 25.
  Plány se při uzavření nemažou. Dokumentace se předává spolu se změnou přes PR.
- **Realizace a ověření:** [DOC01](DOCUMENTATION_GOVERNANCE_2026-11-07.md),
  [závazný postup](WORK_DOCUMENTATION_POLICY.md), [deník](EVENT_LOG.md).
  Zavedení pravidla neuděluje nové oprávnění k produkčním zásahům.

## D2026-11-07-02

- **Stav / datum:** PŘIJATO, 7. 11. 2026. Vlastník po přípravě přehledu výslovně
  zadal nasazení a ověření změn rolí; stávající role lidí se nesmějí měnit.
- **Volba a důvod:** zachovat současné role a audit, rozšířit dosavadní stránku
  o společný přehled. Ověření zápisů jen na nových technických účtech, nikoli
  na existujícím zaměstnanci nebo dosavadním testovacím účtu.
- **Provozní ověření:** před nasazením čtecí otisk původních přístupů; následně
  skutečné HTTPS formuláře a odvolání všech nových testovacích přístupů s
  deaktivací účtů. Hesla jen v paměti procesu, bez e-mailů. Obnova úklidu má
  vlastní chráněnou fázi a odmítá původní ID.
- **Varianta a omezení:** Computer Use skončil kvůli neurčené URL prohlížeče;
  nepokračuje ovládání desktopu. Serverové formulářové ověření neoznačovat
  za vizuální produkční Chrome UAT. [Plán a akceptace](ACCESS_OVERVIEW_2026-11-07.md).
- **Navazující výslovné zadání ROLE01-VIS:** vlastník následně požádal o nové
  vizuální ověření v prohlížeči pouze čtením. Dokončeno na desktopu a 390/360 px;
  mobilní tabulka má výhradu čitelnosti, posun a editor fungují. Žádné role se
  neměnily. [Nový vizuální protokol](verification/access-visual-20261107/RESULTS.md)
  doplňuje původní technickou přejímku; mobilní redesign zatím jen doporučený.

## D2026-11-07-03

- **Stav / datum:** PŘIJATO, 7. 11. 2026. Vlastník zadal automatické doklady,
  e-maily při vytvoření/stornu/vratce, ověření, sloučení a nasazení.
- **Volba a důvod:** nové objednávky s výslovným finančním pověřením a adresou;
  neměnný podklad při nákupu, faktura až po úhradě, opravný doklad při stornu.
  Existující minutový worker, stejné účetní služby a unikátní události chrání
  před duplicitami. PDF chyba neblokuje zaúčtování příjmu.
- **Hranice:** samotné storno nevyplácí peníze. Potvrzení vratky až po evidované
  výplatě. Nejasné doručení držet k prověření, neodesílat naslepo znovu.
  Historické objednávky bez pověření zpětně nerozesílat; ruční doklady nepřepisovat.
- **Varianty:** vystavení během platební transakce by spojilo PDF výpadek s penězi;
  plošný nový odesílač by zasáhl staré zprávy. Zvolen omezený worker a verze pověření.
- **Provozní cesta:** při nefunkčním Computer Use chráněná operace s přesným
  vystavovatelem/IČ a existujícím platným finančním pověřením; bez falešné session.
- **Realizace / akceptace:** [plán a provoz](SHOP_DOCUMENT_AUTOMATION_2026-11-07.md),
  [lokální důkazy a otevřené meze](verification/shop-document-automation-20261107/RESULTS.md).

## D2026-11-07-04

- **Stav / datum:** PŘIJATO, 7. 11. 2026; vlastník výslovně zadal začít
  klubovými cenami, změřit před/po, ověřit shodu a nic nenasazovat.
- **Volba a důvod:** oddělený reader pro jednu cenovou prezentaci katalogu/detailu.
  Společné soupisky a pravidla načíst dávkově, zachovat databázové kolace
  a původní cenová rozhodnutí. Checkout a transakční cesta zůstanou kanonické.
- **Varianty:** rozšíření na celý katalogový výřez nebo dlouhodobá cache
  oprávnění nejsou potřeba k odstranění prokázaných opakovaných SQL.
  Měření celého HTTP zůstává další samostatný krok.
- **Dopad a důkazy:** [plán P11 cen](MEMBER_PRICING_READ_PERFORMANCE_2026-11-07.md),
  [parita a měření](verification/member-pricing-read-performance-20261107/RESULTS.md),
  [předání](SESSION_HANDOFF_MEMBER_PRICING_READ_PERFORMANCE_2026-11-07.md),
  [PR #358](https://github.com/KovoPraha/evidenceTreninku/pull/358).
  Implementace/parita hotové a sloučené jako `37d0ad68` / #358; bez deploye a aktivace.

Pracovní označení této volby ve starších commitech PR #358 bylo D2026-11-07-02.
Při převzetí #356 je kolize s jeho již přijatým ROLE01 rozhodnutím vyřešena
společným ID D2026-11-07-04; obsah ani původní commitové důkazy se nemění.

## D2026-11-07-06

- **Stav / datum:** PŘIJATO, 7. 11. 2026, výslovné zadání vlastníka po ROLE01-VIS.
- **Volba:** pod 768 px samostatné sémantické karty osob se skupinami rolí,
  stavem a přímým tlačítkem editoru; nad breakpointem zůstává tabulka.
  Obě zobrazení používají stejný filtrovaný seznam a kanonické definice.
- **Důvod / varianta:** boční posun původní tabulky fungoval, ale skryl jméno
  a lámal štítky do úzkých sloupců. Fixování jména by nevyřešilo šířku rolí.
- **Oprávnění:** implementace, merge, standardní nasazení a čtecí vizuální
  kontrola schválené. Žádná změna stávajících rolí ani založení nových účtů.
- **Realizace / další krok:** [ROLE01-MOB](ACCESS_OVERVIEW_2026-11-07.md), PR #361;
  testy a vizuální produkční přejímka se zaznamenají samostatně.


Následné výslovné potvrzení vlastníka: společně nasadit karty a cenovou optimalizaci #358. Tím je pro toto vydání schválen i deploy původně omezený v D2026-11-07-04.

## Úplný rejstřík plánů a jejich výsledků

[Dokončené technické a dokumentační řezy](PLAN_CATALOG.md#dokončené-technické-a-dokumentační-řezy)
mají pro každý soubor vlastní ID, registr a důkaz vymezeného uzavření.
[Historické a nahrazené plány](PLAN_CATALOG.md#historické-a-nahrazené-plány)
včetně etap 0–6 zůstávají dostupné se zdrojem nebo nástupcem.
[Aktivní a neuzavřené plány](PLAN_CATALOG.md#aktivní-a-neuzavřené-plány)
se neztrácejí uvnitř starých promptů. [Úplný index](PLAN_DOCUMENT_INDEX.md)
zahrnuje také datované protokoly a textové podpůrné artefakty.
Tento zpětný audit není novým produktovým rozhodnutím ani dodatečným schválením konceptů.






## Starší rozhodnutí — zachovaná v původních souborech

| Oblast | Trvalý zdroj | Jak číst |
| --- | --- | --- |
| ROLE01 — Lidé a přístupy: implementace, nasazení a technická produkční přejímka hotové | [Plán](ACCESS_OVERVIEW_2026-11-07.md), [důvod a oprávnění](#d2026-11-07-02), [produkční protokol](verification/access-release-20261107/RESULTS.md) | [Uzavření E2026-11-07-07/16](EVENT_LOG.md), #356; původní role přesně zachované, nové testovací účty deaktivované. Formuláře 16/16 PASS; [navazující skutečný Chrome průchod](verification/access-visual-20261107/RESULTS.md) dokončen s výhradou čitelnosti mobilní tabulky |
| Klubové dokončení a přejímka | [CLUB_COMPLETION_DECISIONS](CLUB_COMPLETION_DECISIONS.md) | D01–D20 a jejich skutečné podmínky; není to seznam všech hotových kroků |
| Členská evidence bez sezon | [Pracovní plán](MEMBER_EVIDENCE_WORK_PLAN_2026-11-02.md), [schválená mapa](MEMBER_EVIDENCE_APPROVED_MAP_2026-11-02.md) | D1–D8 a provozní rozhodnutí; E6–E8 ověřit v aktuálním registru |
| Původní architektura a program | [Zadání a rozhodnutí](plan-eshop-tymova-evidence/02-zadani-a-rozhodnuti.md) | Historické programové D-xxx; dnešní stav a priority určuje registr |
| BAR a účetní hranice | [Plán](BAR_INTEGRATION_PLAN_2026-11-05.md), [kontrakt](BAR_ACCOUNTING_CONTRACT_V1.md) | Provozní přejímka a adaptér zůstávají oddělené |

## Rejstřík uzavřených prací a jejich plánů

Uzavření zde označuje jen uvedený rozsah; zbývající etapy dál patří do registru.
Při každém dokončení doplň řádek nebo odkaz na datovanou část deníku.

| Práce / rozsah | Plán, výsledek a důvody | Historie / další etapy |
| --- | --- | --- |
| AUTO01 / P14 / P10 — konkrétní izolovaná obnova a čtecí triáž hotové | [Plán](AUTONOMOUS_PRIORITIES_1_7_8_2026-11-07.md), [důkazy a meze](verification/autonomous-priorities-20261107/RESULTS.md) | #370; 389 tabulek / 2 triggery, WIP zachované. Neuzavírá celé C04, živé finanční ani vizuální přejímky |
| P05 ranní serverový audit 7. 11. — technický důkaz dokončený | [Plán](MEMBERSHIP_SCHEDULER_MORNING_AUDIT_2026-11-07.md), [výsledek PASS](verification/membership-scheduler-20261107/RESULTS.md) | #347 / 599979d4, start s odchylkou 3 s. Skutečné sjednání/platba a export dál odložené |
| P07 N1/N2 — automatické doklady a komunikace nasazené/zapnuté | [Plán a provoz](SHOP_DOCUMENT_AUTOMATION_2026-11-07.md), [důkazy](verification/shop-document-automation-20261107/RESULTS.md), [volba](#d2026-11-07-03) | #355, přirozený worker ověřen; skutečný bankovní převod a externí doručení zůstávají samostatnou přejímkou |
| P11 klubové ceny — implementace a párové měření sloučené #358 | [Plán](MEMBER_PRICING_READ_PERFORMANCE_2026-11-07.md), [důkazy](verification/member-pricing-read-performance-20261107/RESULTS.md), [volba](#d2026-11-07-04) | [Předání](SESSION_HANDOFF_MEMBER_PRICING_READ_PERFORMANCE_2026-11-07.md), [PR](https://github.com/KovoPraha/evidenceTreninku/pull/358); v main `37d0ad68`, bez deploye, katalog/HTTP dál otevřené |
| DOC01 — souborová dokumentace a stručné předání | [Plán a ověření](DOCUMENTATION_GOVERNANCE_2026-11-07.md), [důvod rozhodnutí](#d2026-11-07-01) | [Předání #357](https://github.com/KovoPraha/evidenceTreninku/pull/357), [deník](EVENT_LOG.md); stav CI/přijetí určuje PR |
| P11 R13 — implementace #352, zahrnutá v nasazení ROLE01 | [Plán a důkazy](verification/medical-read-performance-20261106/RESULTS.md) | [Předání](SESSION_HANDOFF_MEDICAL_READ_PERFORMANCE_2026-11-06.md), celé K0.2 otevřené |
| P11 R05 — implementace #353, zahrnutá v nasazení ROLE01 | [Plán a důkazy](verification/planner-read-performance-20261106/RESULTS.md) | [Předání](SESSION_HANDOFF_PLANNER_READ_PERFORMANCE_2026-11-06.md), hierarchický filtr/HTTP otevřené |
| P11 R06 — implementace #354, zahrnutá v nasazení ROLE01 | [Plán a důkazy](verification/attendance-read-performance-20261107/RESULTS.md) | [Předání](SESSION_HANDOFF_ATTENDANCE_READ_PERFORMANCE_2026-11-07.md), role/HTTP otevřené |
| Refaktoring W6–W15 — technické dokončení | [Komplexní plán](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md), [uzavření](REFACTORING_CLOSURE_2026-10-30.md) | Lidská/účetní přejímka v P09; [provozní historie](CHANGELOG-PROVOZ.md) |
| Starší session a dokončené části programu | [Deník a archivní rozcestník](EVENT_LOG.md) | Původní plány, důkazy a rozhodnutí zůstávají v Gitu; nejde o zpětné schválení neověřených kroků |

P11 celé HTTP/browser — [uzavřené měření a důkazy](verification/full-http-performance-20261107/RESULTS.md), [navazující etapy](SESSION_HANDOFF_FULL_HTTP_PERFORMANCE_2026-11-07.md); celé K0.2 otevřené.

## D2026-11-07-07

PŘIJATO, 7. 11. 2026, podle zadání vlastníka pokračovat katalogem.
[Plán](CATALOG_READ_PERFORMANCE_2026-11-07.md): lehká operační projekce celého
katalogu zachová prodejnost, počty a ceny; těžká prezentace jen pro stránku.
SQL LIMIT před cenami/prodejností zamítnuto kvůli změně výsledků; trvalá cache
by potřebovala invalidaci. Parita a měření jsou podmínkou předání. Bez deploye.
První nativní měření ukázalo režii malého katalogu; do 64 aktivních produktů
proto původní cesta podle sondy nejvýše 65 ID. Sonda neurčuje výsledný katalog.
Pracovní ID v předchozích commitech této větve bylo 05; přijaté HTTP rozhodnutí
#363 ponecháno jako 05, katalog nyní 06. Historické doklady zůstávají v Gitu.

P11 katalog — [plán a volba](CATALOG_READ_PERFORMANCE_2026-11-07.md), [důkazy](verification/catalog-read-performance-20261107/RESULTS.md), [předání](SESSION_HANDOFF_CATALOG_READ_PERFORMANCE_2026-11-07.md); implementace/parita/měření hotové v #364, bez merge/deploye.

Při převzetí #361 zůstává jeho přijaté mobilní rozhodnutí 06, katalogové pracovní 06 nyní nese 07. V tomto katalogovém úkolu se deploy nadále neprovádí; samostatné produkční oprávnění ROLE01-MOB se zde nevykonává.

Aktuální pokyn vlastníka v tomto úkolu: ověřené změny commitnout, podle vhodnosti nasadit a pokračovat implementací další doložené optimalizace. Pro katalog je nyní merge/deploy schválený; pro další etapu se nejprve dokládá parita a měření.

## D2026-11-07-08

PŘIJATO podle pokračovacího zadání vlastníka. [Dávkové lekce](ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md): omezit opakování stejné aktuální kontroly v GET, zachovat výběr kandidátů a transakční writer. Bez cache mezi voláními; parita a měření před/po nutné. UI výběru osob je samostatný větší zásah.

Po nativní paritě a přínosu přibližně 20× u 50 lekcí je podle aktuálního zadání vhodné nasadit i tuto čtecí optimalizaci. Merge a standardní deploy následují až po zelených požadovaných kontrolách; žádné změny osob, rolí nebo docházky.

P11 R06 nabídka lekcí — uzavřený dílčí řez podle D2026-11-07-08: #367, finální CI, parita a měření PASS; [plán](ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md), [produkční důkaz a meze](verification/attendance-lesson-release-20261107/RESULTS.md), [předání](SESSION_HANDOFF_ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md). Výběr osob a celé K0.2 dál otevřené.

P11 picker — [finální místní výsledek](verification/attendance-person-picker-20261107/final/RESULTS.md), [plán](ATTENDANCE_PERSON_PICKER_2026-11-07.md), [předání](SESSION_HANDOFF_ATTENDANCE_PERSON_PICKER_2026-11-07.md), PR #366 bez našeho deploye.

## D2026-11-07-10

PŘIJATO 7. 11. podle nového výslovného zadání vlastníka: dokončit #366,
ověřit shodu a přínos, po zelených kontrolách commit/merge/deploy. Původní
zákaz nasazení je historický. Před nasazením doplnit celý HTTP/browser pár
aktuálního main s #367 a pickerem; žádné aktivace ani skutečné zápisy docházky.
[Plán](ATTENDANCE_PERSON_PICKER_2026-11-07.md), [předání](SESSION_HANDOFF_ATTENDANCE_PERSON_PICKER_2026-11-07.md).
Kanonické D09 původního pickeru zachované; dočasné přeznačení na D10 v pracovním
75801847 bylo sjednocené při převzetí d62c3ad2. D10 označuje toto nové pokračování.

P11 picker — dílčí řez uzavřený podle D10 a nového výslovného zadání: #366, nové společné měření, parita a schválené nasazení hotové. [Plán](ATTENDANCE_PERSON_PICKER_2026-11-07.md), [provozní důkaz a meze](verification/attendance-picker-release-20261107/RESULTS.md), [předání](SESSION_HANDOFF_ATTENDANCE_PERSON_PICKER_2026-11-07.md). Celé K0.2 a lidská přejímka otevřené.

Rozšířené celé R06 Windows měření — [plán](ATTENDANCE_PERSON_PICKER_2026-11-07.md), [důkazy](verification/attendance-integrated-http-20261107/RESULTS.md), [předání](SESSION_HANDOFF_ATTENDANCE_HTTP_2026-11-07.md), [volba D11](#d2026-11-07-11). Bez změny produkce v této relaci, další P11 otevřené.

## D2026-11-07-12

PŘIJATO podle nového zadání vlastníka: [P11-R05-H](PLANNER_HIERARCHY_READ_PERFORMANCE_2026-11-07.md) omezí přenos historických kandidátů čtecí agregací podle dne a hierarchie. Předek/potomek odděleně, bez sourozenců, počty přes EXISTS; složité/nekánonické případy a transakce původní cestou. Rekurzivní SQL a trvalá cache zamítnuty kvůli složitosti/invalidaci. Parita a nativní přínos před zelenými kontrolami a schváleným merge/deployem.

P11-R05-H podle D12: finální parita a objemový přínos PASS na obou DB i celém HTTP/Chrome. [Důkazy](verification/planner-hierarchy-read-performance-20261107/RESULTS.md). Vhodné pro schválený standardní deploy až po zelených kontrolách výsledkového commitu; drobná režie jednoho SQL u malé filtrované historie doložená, bez soupisky čas neutrální.
