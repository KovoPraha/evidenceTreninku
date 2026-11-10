# Rozhodovací log a rejstřík uzavřených plánů

Společný vstup pro rozhodnutí napříč projektem. Aktuální stav je v
[CURRENT](CURRENT_STATE.md), otevřené koncepty/plány v [registru](WORK_PLAN_CURRENT.md),
časová posloupnost v [deníku](EVENT_LOG.md). Nová rozhodnutí přidávej s ID
`DYYYY-MM-DD-NN`; ID neměň. Starší doménová rozhodnutí nepřečíslovávej.
Při nahrazení doplň stav a odkaz na nástupce; zachovej původní důvody.

## D2026-11-10-08

COACH-ROSTER-01: trenérská agenda čte pouze přímo svěřené `club_teams` a jejich aktuální `club_roster_members`. Varianta dědění jmen z rodičovské skupiny byla odmítnuta, protože grant rodiče neopravňuje ke každé podřízené soupisce. Registrátorská projekce `memberRosterRead()` vrací širší osobní údaje a je vázána na editační pracovní roli; místo ní je úzká čtecí projekce jméno/příjmení. Čekající a ukončená jména vyžadují zvláštní oprávnění, proto první etapa ukazuje jen aktivní členství. [Plán](COACH_GROUP_ROSTERS_2026-11-10.md), [testy](verification/coach-group-rosters-20261110/RESULTS.md).

## D2026-11-10-07

P11-ASSET4: před další změnou nejprve zjistit povahu tří katalogových rozdílů a vytvořit bezpečné dílčí otisky. Starý agregovaný hash bez beforeHTML neopravňuje libovolně normalizovat obsah. Varianty další optimalizace vybírat podle aktuálních přenosů, nikoliv starých časů. [Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset4--katalogová-diagnóza-a-další-měřený-řez-10-11-2026).

## D2026-11-10-06

P11-ASSET3: další řez z bodu7 analýzy zúžen na čtyři veřejné vstupy k účtu. Explicitní CDN Bootstrap je shodná verze/SRI jako společná lokální CSS. Přednost před defer společného JS kvůli nezměněnému spouštění inline skriptů; rozhodující skutečný cold přenos a přesná vizuální/source/HTTP shoda. Kalendář a další nalezené linky mimo rozsah. [Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset3--veřejné-vstupy-k-účtu-10-11-2026).

P11-ASSET3 uzavřen v[release](verification/public-account-assets-20261110/release/RESULTS.md): cílová parity/payload přejímka PASS. Neodstraňovat nonce/CSRF kontrolu ani libovolně maskovat HTML; povoleno jen přesně5ověřených assetfilemtime verzí. DoplňkovýcatalogDOM5/8 nepovažovat zaPASS; další katalogová práce potřebuje novou bezpečnou strukturovanou baseline.

## D2026-11-09-01

PŘIJATO 9. 11. 2026 podle přímého zadání vlastníka: v [QR01](QUICK_REGISTRATION_TERMS_2026-11-09.md) opravit přesný HTML název `term_version[účel]` a zachovat serverové porovnání aktuálních verzí. Odstranění kontroly by dovolilo potvrdit zastaralý text. Při změně verze mezi GET a POST se požadavek odmítne; nový formulář načte současný text a dřívější potvrzení už neponechá zaškrtnuté. [Izolovaný důkaz](verification/quick-registration-terms-20261109/RESULTS.md).

## D2026-11-08-01

PŘIJATO 8. 11. 2026 na přímé zadání vlastníka: zjednodušit doplnění celoročních nabídek k existujícím kroužkům. Správce má vybrat současné skupiny a zadat schválené ceny, zatímco datum, vazby, kapacita, věk, kód a SKU se odvodí z obou pololetí. Zápis více skupin musí být atomický a opakování bez duplicit. Nový samostatný program nebo produkt pro stejnou skupinu by rozpojil existující soupisku; ruční vyplnění všech polí zůstane jako pokročilá cesta. [Plán](club-programs.md#ux01--doplnění-celoroční-nabídky-ke-stávajícím-skupinám-8-11-2026). Ceny a produkční zápis toto rozhodnutí neschvaluje.

## D2026-11-07-13

PŘIJATO 7. 11. 2026 na přímé zadání vlastníka: [P11-SCALE](MEDICAL_PLANNER_SCALE_2026-11-07.md).
Měřit stejnou zmrazenou aplikaci 69f49033 při větším objemu; neimplementovat
souběžnou optimalizaci plánovače. Nový datadir, standardní login a SELECT-only
web účet, oddělené prosté HTTP časy a diagnostika se skutečnými rows examined
ze slow logu. Zdravotní růst a růst historie měnit odděleně; členství a šířku
hierarchie rozšířit společně a tuto mez uvést. Staré časy nejsou kontrolní skupina.
Žádné produkční změny, deploy ani nové zápisové UAT. [Předání](SESSION_HANDOFF_MEDICAL_PLANNER_SCALE_2026-11-07.md).

Dokončení: primární2160 a aktuální#374 540HTTP,90parit a oba úklidy PASS.
Po přijetí nové aplikace přidané dvě největší sady; přímá shoda se starými
doménami, strukturální přínos prokázaný, časový poměr není kontrolovaný experiment.
[Obě fáze](SESSION_HANDOFF_MEDICAL_PLANNER_SCALE_2026-11-07.md), bez produkce.

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
Realizace: #361 / `3b37e053`, deploy 42365946802 SUCCESS. [Výsledky](verification/access-mobile-cards-20261107/RESULTS.md): role zachované, nové karty vizuálně NOT RUN.

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

P11-R05-H — uzavřený technický řez podle D12: [plán](PLANNER_HIERARCHY_READ_PERFORMANCE_2026-11-07.md), [parita/přínos](verification/planner-hierarchy-read-performance-20261107/RESULTS.md), [merge/deploy/čtecí důkaz](verification/planner-hierarchy-release-20261107/RESULTS.md), [handoff](SESSION_HANDOFF_PLANNER_HIERARCHY_2026-11-07.md). #374/e36c9ba6 a deploy42394551344 SUCCESS. Lidské UAT/rows examined a celé K0.2 tím nejsou uzavřené.

## D2026-11-07-14

PŘIJATO podle aktuálního zadání vlastníka: [P11-R13-S](MEDICAL_STATE_READ_PERFORMANCE_2026-11-07.md) oddělí běžnou čtecí SQL projekci od složitých budoucích řetězců. Ty zůstávají kanonické, nad limitem a v transakci původní stream. Práva, writer a zdravotní rozhodnutí beze změny. Persistentní stavy/rekurzivní SQL odmítnuté kvůli invalidaci a složitosti. Parita, měření a CI před schváleným merge/deploy. Historický zákaz deploye #352 není aktuální pokyn.

D13 průběžně: první prototyp a7b1bee9 zamítnut pro deploy kvůli regresi celého GET. Opakované agregace nahrazuje cílená sonda a jediná agregace s COUNT/ROW_NUMBER OVER; nejde o rekurzivní SQL ani cache. [Důkaz](verification/medical-state-read-performance-20261107/RESULTS.md), [oficiální MariaDB reference](https://mariadb.com/docs/server/reference/sql-functions/special-functions/window-functions/window-functions-overview). Po změně opakovat paritu a měření obou DB i HTTP, bez předčasného deploye.

D14 upřesnění po měření V3: rychlá projekce pouze pro stav bez doplňkových filtrů; hledání/datum/osoba původní SQL, protože nativní11.4 profil doložil režii scoped větve. Žádný odhad velikosti ani další prahová sonda. Finální časování s explicitně vypnutou SQL query cache, malá pevná režie přiznaná. [Výsledky](verification/medical-state-read-performance-20261107/RESULTS.md).

P11-R13-S — uzavřený technický řez podle D14: [plán](MEDICAL_STATE_READ_PERFORMANCE_2026-11-07.md), [finální parita/přínos](verification/medical-state-read-performance-20261107/RESULTS.md), [merge/deploy/čtecí kontrola](verification/medical-state-release-20261107/RESULTS.md), [handoff](SESSION_HANDOFF_MEDICAL_STATE_2026-11-07.md). #381/938e9c50, deploy42405912779 SUCCESS. Malá pevná režie přiznaná; původní doplňkové filtry zachované. Přihlášené UAT/rows examined a celé K0.2 dál otevřené.

## D2026-11-07-15

PŘIJATO podle nového výslovného zadání Marka: [P11-R13-F](MEDICAL_FILTER_READ_PERFORMANCE_2026-11-07.md). Oddělit bounded výběr kandidátů od stavové historie; malé/prázdné dotazy neopakují plnou tabulku, široké dotazy neopakují textové hledání uvnitř agregace. Datum předfiltruje i historii. Přesná kanonická parita a cache-off měření všech tvarů před schváleným merge/deploy. D14/#381 zůstává historií, novým oprávněním jsou hledání a datum. Bez cache/DDL/rekurze, práva a writer zachované.

D15 upřesnění8.11.2026: cc2a825e hledání přínos PASS, ale dva datumové smíšené profily regresní. Pro datum zkusit nejnovější verified/poslední záznam korelovaným indexovým lookupem, aby se neagregovala širší historie. Bez DDL a bez změny stavu; rozhodne nové párové měření. [Důkazy](verification/medical-filter-read-performance-20261107/RESULTS.md).

D15/V3: korelované head lookupy V2 měřením zamítnuté i při existujícím owner/id indexu. Datumový kandidát/head se vypočte jednou s původním filtrem, vnější dotaz už jen spojuje vypočtené head ID a stránkuje. Žádné produkční DDL nebo nový indexový předpoklad. [Důkazy](verification/medical-filter-read-performance-20261107/RESULTS.md).

D15/V4: po mírné regresi V3 datum_day zvolené latest-verified anti-join pro platnostní datumové stavy, a časné HAVING pro běžné skupiny bez verified. Budoucí missing/revoked s verified hlavičkou musí zůstat výjimkou podle matching ID. Nutné nové široké datumové legacy testy a pending hledání; měření rozhodne. Bez DDL.

D15/V5,8.11.: po čtyřech SQL-only měřeních zůstává datum/poloviční pending regresní. Schválený cíl optimalizace nyní řeší i krycí index bez změny klinických hodnot. Původní plán bezDDL nahrazen; samotný index i kód měřit odděleně. Produkční metadata přečíst před migrací, online ADD INDEX až po záloze a testech, ověřit před aktivací. [Online ADD INDEX/LOCK NONE a krátké metadata zámky — MariaDB](https://mariadb.com/docs/server/server-usage/storage-engines/innodb/innodb-online-ddl/innodb-online-ddl-operations-with-the-inplace-alter-algorithm); žádný slib nulového blokování. [Výsledky](verification/medical-filter-read-performance-20261107/RESULTS.md).

D15 uzavření8.11.2026: V5 přijatá po přesné paritě, opakovaném nativním/HTTP přínosu a15CI; #385/519ff098 nasazeno42477866185. Produkční metadata potvrdila krycí index, klinická data beze změny. Přihlášené UAT zůstává samostatné. [Trvalé důkazy](verification/medical-filter-release-20261108/RESULTS.md).

## D2026-11-08-RH — stránka před podrobnostmi správní historie

PŘIJATO podle nového zadání Marka: po dokončení cen/katalogu/docházky a R05/R13 zvolené zbývající pořadí4 analýzy. Tenké kandidáty a kanonická hierarchie před stránkou25, plné detaily až po ní; přesné celkové souhrny a dostupnost celé historie zachovat. Datové remízy zpřesnit idDESC, hledání potvrzovat serveru. Parita a naměřený přínos před release. [Plán](TRAINING_HISTORY_PAGE_PERFORMANCE_2026-11-08.md).

Uzavření D2026-11-08-RH: po přesné paritě všech stránek a přínosu odstraněné nepoužité LEFT JOIN bez ztráty filtrů; malá režie jediného záznamu přiznaná. #394/ab49947d a deploy42486500282 SUCCESS po14CI. [Výsledky](verification/training-history-page-performance-20261108/RESULTS.md), [produkční protokol](verification/training-history-release-20261108/RESULTS.md). Technický řez hotový; přihlášené UAT a ostatní P11 otevřené.

## D2026-11-08-RHC — stránka před drahými trenérskými AJAX detaily

PŘIJATO podle nového zadání Marka: pokračování zbývající priority4 analýzy po uzavřeném RH. Moje tréninky stále načítají celou historii včetně účastníků; zvoleno před samostatným osobním profilem a plošným bootstrap/session zásahem. Zachovat veškeré výsledky a oprávnění, nejprve měřit celou cestu. [Plán](COACH_HISTORY_PAGE_PERFORMANCE_2026-11-08.md); převzaté ukončené P11-SCALE/R13-F zůstávají v registru a trvalých protokolech.

Uzavření D2026-11-08-RHC: #400/da152282 a deploy42494231010 SUCCESS po14CI a přesné paritě. [Důkazy](verification/coach-history-page-performance-20261108/RESULTS.md), [provozní protokol](verification/coach-history-release-20261108/RESULTS.md). CSRF otáčení se normalizuje pouze v porovnání; žádné oslabení aplikace. Původní veřejný katalog má nově produkt298, výsledek neshody zůstává zachovaný, nový současný průchod stabilní; žádný rollback dat. Technický řez hotový, lidské UAT/ostatní P11 samostatné.

## D2026-11-08-RHA — omezené čtení historie a lazy měření profilu

PŘIJATO podle nového zadání Marka: navázat na uzavřené RHC a prioritu4 analýzy. Zachovat kanonické přístupy/poznámky a přesné celé souhrny, odstranit násobení cizích měření při členství, detaily až pro stránku a měření až po otevření. UNION/window varianty jsou kandidáti k paritě a měření, nikoli předem tvrzený přínos. [Plán](ATHLETE_PROFILE_READ_PERFORMANCE_2026-11-08.md). Historické RH zůstává v registru a trvalých protokolech.

Upřesnění RHA: místo window/group agregace zvolen seřazený úzký sloupec typů a původní PHP počítání; zachovává case/mezery, PHP klíče a pořadí prvního výskytu. Počáteční GET čte pouze COUNT; souhrn typů zůstává lineární až při otevření měření.

RHA měření nahradilo první úzkou PHP agregaci: nativně se prokázala regrese až23% v záložce všech měření. Zvolena binární SQL window/group agregace se stejným prvním výskytem a LIMIT úzkých ID před širokými detaily; předchozí měření zůstane v důkazech.

RHA finální varianta: pouze číselné ID/ordinal uvnitř window, binární typy agregovat až po připojení a detaily číst po LIMIT. Native3/651 obě verze a skutečné HTTP/Chrome PASS; finální časy i přiznané malé režie jsou v [protokolu](verification/athlete-profile-read-performance-20261108/RESULTS.md). Přenos35 místo90909 řádků a lazy HTML řeší celý profil, nikoli jen izolovaný helper. Uvolnění čeká na aktuální CI a exacttree.

Uzavření D2026-11-08-RHA: #404/82efa16d a deploy42521982178 SUCCESS po14CI, přesné paritě a měřeném přínosu. Původní dvě regresní varianty zachované, finální číselné okno+LIMIT a lazy HTML výrazně omezují paměť/přenos. [Výsledky](verification/athlete-profile-read-performance-20261108/RESULTS.md), [release/meze](verification/athlete-profile-release-20261108/RESULTS.md). Původní veřejný wholehash neměl úplnou normalizaci nonce/asset verzí; neshoda zachovaná, nový současný8/8stabilní. Technický řez hotový, UAT a ostatní P11 samostatné.

## D2026-11-08-RGS — souběh globálního hledání bez změny shod

PŘIJATO podle nového zadání Marka: po uzavřených hlavních řezech1–4 ověřit mechanismus analýzy5/6 na skutečné HTTP cestě. Globální substringové SQL neměnit na prefix/fulltext kvůli paritě. Kandidát je bezpečné uzavření session až po čerstvých kontrolách a zápisech, před dlouhým čistým čtením. Syntetický souběh a session revokace/odhlášení musí prokázat přínos a zachování stavu; production handler není zatím doložený. [Plán](GLOBAL_SEARCH_SESSION_PERFORMANCE_2026-11-08.md). Starší RHC zůstává v registru a protokolu.

## D2026-11-08-RAS — aktuální aktivní cesta před teoretickou optimalizací

RGS nevybrané po ověření aktuálníhlavicka.php: false guard vypíná input. Nezapínat funkci kvůli benchmarku. Session mechanismus analýzy6 zacílit na skutečná dvě profilová AJAX čtení, kde již existuje nativní profil vysokého objemu. [Plán](AJAX_PROFILE_SESSION_PERFORMANCE_2026-11-08.md); změna až po fresh auth/profile/note gate, stejné SQL a výstupy. Předchozí koncept/důvody zůstávají verzované.

## D2026-11-08-RAS-RELEASE

PŘIJATO a technicky dokončeno podle podmíněné autorizace vlastníka: #406/95cb33e5,
deploy42531190845 SUCCESS. Přesné celé fragmenty/16CI/SQLfilelock/souběžný logout
a přínos actualpair +Chrome opravňují úzké early-close po fresh gates; žádné SQL
změny/cachepráv či globální read_and_close. První kandidát RGS nevybrán kvůli
vypnutému UI a nebyl aktivován. První fixture/login FAIL zachován.
[Plán a akceptace](AJAX_PROFILE_SESSION_PERFORMANCE_2026-11-08.md),
[provozní důkaz a meze](verification/ajax-profile-session-release-20261108/RESULTS.md).
Handler/lockwait produkce a přihlášené UAT NOT RUN; nevyvozovat produkční SLA.
Parent profil~2,7sTTFB při extrémní fixture je další diagnostický KONCEPT,
bez autorství nového release nebo cache čerstvých práv v tomto řezu.

## D2026-11-09-RAP

Podle dalšího zadání vlastníka zvolen největší doložený zbývající úsek
[analýzy4/6](verification/performance-analysis-20261107/RESULTS.md): parent profil
po RAS stále~2,7sTTFB na extrémní fixture. Nejprve SQL diagnostika; kandidáti
jsou vynechání nepoužitých measurement snapshotů v panelu3součtů nebo dávkové
header agregace. Žádná předem deklarovaná úspora, kanonický export/hash se
nesmí ochudit. [Plán a akceptace](ATHLETE_PROFILE_PARENT_PERFORMANCE_2026-11-09.md).

Upřesnění D2026-11-09-RAP: native diagnostika42570580742 potvrzuje nejdražší
sportsActivityMeasurements (649/1665ms,94752řádků). Vybrána čistá3polová
projekce; používá kanonické closure/event readers a paritu proti úplnému souhrnu,
aniž by měnila legacy souhrn/writery nebo vydávala neúplný contract snapshot.

## D2026-11-09-RAP-RELEASE

Podle podmíněné autorizace vlastníka po přesné paritě a doloženém přínosu
sloučeno #408/ca5fc219, deploy42574460894 SUCCESS. Nová3polová projekce
odstranila nevyužité measurement snapshoty; plný canonical/export/hash i
writery zůstávají byte-identické.16CI/current whole-tree/source, oběDB a
Chrome PASS. [Plán](ATHLETE_PROFILE_PARENT_PERFORMANCE_2026-11-09.md),
[release/meze](verification/athlete-profile-parent-release-20261109/RESULTS.md).
Syntetická data nejsou produkční SLA, přihlášené produkční UAT NOT RUN.
Původní diagnóza/první páry zachované, další header agregace pouze KONCEPT.


## D2026-11-09-TIME

Vlastník 9. 11. zadal blokování chybného času a odolnost zápisu vůči ztrátě
rozepsaných dat. Q2-TIME používá lokální koncept oddělený podle trenéra a
formuláře plus session zálohu při odmítnutém POST. Nejasný výsledek odeslání
neobnovuje automaticky jako nový zápis, protože by mohl duplikovat trénink.
Přílohy nelze bezpečně obnovit z úložiště formulářových hodnot. Rozsah a důkazy:
[plán](QUALITY_NEXT_SLICES_2026-11-08.md#q2-time--odolný-zápis-tréninku-9-11-2026),
[protokol](verification/training-form-resilience-20261109/RESULTS.md).

Následné výslovné pověření vlastníka zahrnulo merge, deploy a ověření.
[Produkční výsledek](verification/training-form-resilience-release-20261109/RESULTS.md)
uzavírá technické vydání; přihlášené UAT ponecháno otevřené po blokaci
Computer Use při zjištění URL. Blokace se neobchází výrobou session ani
zápisem skutečného tréninku. Testerka má konkrétní přejímací scénář.
## D2026-11-09-RAH

Zadání vlastníka 9. 11. vybírá zbývající tři header agregace po RAP.
Zkusit jednu agregaci s nezávislým původním fallbackem; alternativou dvě SQL,
pokud celé HTTP nezrychlí. Bez cache, DDL a změny práv/session.
[Plán a akceptace](ATHLETE_PROFILE_HEADER_PERFORMANCE_2026-11-09.md).

## D2026-11-09-RAH-RELEASE

Po přesné paritě, přínosu celé stránky a18CI na b8f91037 dle podmíněného pokynu
vlastníka sloučeno #410/c47d4008 a chráněně nasazeno42587406908 SUCCESS.
Jedna agregace zachovává duplicity/null/0roky/PDOtypy i nezávislý fallback.
Bez nových suppression/cache/DDL a změny AJAX/writerů/práv/session.
[Plán](ATHLETE_PROFILE_HEADER_PERFORMANCE_2026-11-09.md),
[release/meze](verification/athlete-profile-header-release-20261109/RESULTS.md).
Produkční UAT samostatné; syntetické časy nejsou produkční SLA.

## D2026-11-09-RHI

Vlastník vybral načítání historie po RAH. Ověřit EXISTS membership/detail
s omezenými klíči a postupný původní PHP součet; SQL SUM by měnil numerickou
sémantiku. Bez cache/DDL/změny práv/session. [Plán](ATHLETE_PROFILE_HISTORY_PERFORMANCE_2026-11-09.md).

## D2026-11-09-RHI-RELEASE

Dle podmíněného pokynu vlastníka po přesné paritě a prokázaném přínosu historie
sloučeno #413/c3d34b87,18CI SUCCESS, protected deploy42625502461 SUCCESS.
EXISTS a postupné klíče zachovávají původní orderedPHPsoučet i opětovnou kontrolu
vlastnictví/roku detailů. Bez DDL/cache/práv/session/writer změn. Přijaté #412 zachované.
[Plán](ATHLETE_PROFILE_HISTORY_PERFORMANCE_2026-11-09.md),
[release a meze včetně proměnlivého Měření](verification/athlete-profile-history-release-20261109/RESULTS.md).

## D2026-11-09-RHM

Podle aktuálního pokynu vlastníka ověřit přímý binární souhrn a první klíč
MariaDB GROUP_CONCAT LIMIT1 místo číslování celé historie. Původní reference
zůstává fallbackem při nepodporovaném backendu/krátkém limitu. Bez změny DB
nastavení, cache nebo DDL. [Plán](ATHLETE_PROFILE_MEASUREMENT_PERFORMANCE_2026-11-09.md).

### D2026-11-09-RHM-2 — odmítnutí pomalejšího kandidáta

Nativní parita sama nestačí: první JSON varianta má regresi HTTP, neuvolnit.
Zachovat první vzorky a změřit integer tuple + bounded training selector;
[plán a důkazy](ATHLETE_PROFILE_MEASUREMENT_PERFORMANCE_2026-11-09.md).

### D2026-11-09-RHM-3 — ponechat původní souhrn

Druhý native pokus izoloval přínos detailu165→4ms, ale regresi agregace.
Obě GROUP_CONCAT varianty zamítnuté; další kandidát obsahuje jen bounded
selector a původní souhrn, bez přidaného SQL. [Důkazy](ATHLETE_PROFILE_MEASUREMENT_PERFORMANCE_2026-11-09.md).

### D2026-11-09-RHM-4 — reprezentativní spojení a pořadí joinů

Původní oddělený instrument přednačítal db.php; jeho dílčí SQL čísla se
nepřenesla do HTTP. Celý bootstrap17SQL ukázal pomalý detail i po omezení.
Změna nyní nejprve provede bounded eligible seznam a zachová pořadí pomocí
STRAIGHT_JOIN; ověřit EXPLAIN a celé HTTP, neměnit20%gate.
[Plán a původní důkazy](ATHLETE_PROFILE_MEASUREMENT_PERFORMANCE_2026-11-09.md).

### D2026-11-09-RHM-STATS — reprezentativní statistiky fixture

SHOW INDEX prokázal1–2cardinality při300křádcích a EXPLAIN ALL/BNL.
Obnovit statistiky3vlastních tabulek před OBOU měřenými variantami, uložit
metadata i původní FAIL; žádné aplikační ANALYZE/DDL, gate20% zachovaná.
[Plán a důkazy](ATHLETE_PROFILE_MEASUREMENT_PERFORMANCE_2026-11-09.md).

### D2026-11-09-RHM-PREFIX — omezený pokus s úplným fallbackem

Hledání vlastních25tréninků přes celý klub může u starého profilu škálovat
s cizí historií. Před vydáním zvolen globálníprefix64tréninků; jen plná
25řádková stránka je přesný prefix původního pořadí, jinak původní selector.
Typ/vlastník a původní souhrn zachované. Doplněn velký starý profil900MZ
1990 a gate bez podstatné regrese; případný fallback přidává1SQL.
[Plán a důkazy](ATHLETE_PROFILE_MEASUREMENT_PERFORMANCE_2026-11-09.md).

## D2026-11-09-RHM-RELEASE

Po přesné paritě, doloženém přínosu a20CI sloučeno#418/5cc9cf83, protecteddeploy42654922651 SUCCESS dle podmíněného pokynu vlastníka. Původní souhrn ponechaný, dvě agregace odmítnuté, pouze bounded selector dlouhých přehledů. Obnova statistik výhradně ve společné syntetické přípravě obou variant. [Plán](ATHLETE_PROFILE_MEASUREMENT_PERFORMANCE_2026-11-09.md), [release a meze](verification/athlete-profile-measurement-release-20261109/RESULTS.md).

## D2026-11-09-RHMS — nové zadání souhrnu

Vlastník nyní výslovně převzal koncept souhrnu do implementace a podmíněně
povolil release. Zachovat nasazený prefix64 selector. Porovnat přesné
binární počty a první klíč typu s původním numeric-window souhrnem na
stejných čerstvých statistikách, nejprve plný bootstrap a pak celé HTTP.
Staré neúspěchy zůstávají historickými důkazy. Žádná změna SQL nastavení,
cache, rolí, writerů nebo DDL. [Akceptace](ATHLETE_PROFILE_MEASUREMENT_PERFORMANCE_2026-11-09.md#p11-rhms--souhrn-měření-nové-zadání-9-11-2026).

### D2026-11-09-RHMS-2 — neskládat klíč pro každou vazbu

První přesný pevný klíč prokázal nativní paritu, ale plná bootstrap diagnóza
ukázala vysokou per-row režii. Není vybraný pro release. Měřit count/MAX-date
po typech a první tuple nejnovějšího data; zachovat jeden SQL statement a
původní stránkový selector. Surové důkazy zůstávají v [protokolu](verification/athlete-profile-measurement-summary-20261109/RESULTS.md).

### D2026-11-09-RHMS-GATE — chybějící job není PASS

Přestože kandidát2 má přesnou nativní10.3 paritu a předběžný SQL přínos,
GitHub Internal server error opakovaně nevytvořil11.4/část aktuálních CI.
Podmíněný pokyn vlastníka nelze zatím naplnit merge/deployem; žádná změna
produkce. Nevynechávat backend ani20%HTTPgate, neoznačovat diagnózu za
párový medián. [Důkazy a další postup](verification/athlete-profile-measurement-summary-20261109/RESULTS.md).

### D2026-11-09-RHMS-ACCEPT — souhrn podle nejnovějšího data typu

Kandidát2 má přesnou nativní paritu na obouDB a celé párové HTTP/Chrome
PASS. Počty/MAX datum a první klíč jsou v jednom statementu; PHP řadí jen
seznam typů. Oproti window přes všechny vazby HTTP −36.7/41.6 %, Chrome
warm celá navigace −24.1 %. Původní selector a autorizace/canonical writer
beze změny. Kandidát1 i infrastrukturní neúspěchy zůstávají doložené.
Přijetí implementace ještě není release: čeká všech20aktuálních CI,
whole-tree/source proof a chráněné nasazení dle aktuálního pokynu vlastníka.
[Úplná metoda a hranice](verification/athlete-profile-measurement-summary-20261109/validated-d8/RESULTS.md).

## D2026-11-09-RHMS-RELEASE

Po přesné paritě, doloženém přínosu a všech20CI sloučeno #422/17110a7f;
protected deploy42679050552 SUCCESS dle podmíněného pokynu vlastníka. Souhrn
COUNT/MAX datum a první tuple zachovává přesné pořadí/počty, původní selector
a canonical/autorizaci. Původní pokusy i infrastruktura zachované. Závěrečné
důkazy samostatným docs PR bez druhého nasazení. [Plán](ATHLETE_PROFILE_MEASUREMENT_PERFORMANCE_2026-11-09.md),
[release a hranice](verification/athlete-profile-measurement-summary-20261109/release/RESULTS.md).

## D2026-11-09-RIX — ověřit skutečné schéma před další optimalizací

Aktuální pokyn vlastníka dovoluje další optimalizaci a podmíněný release.
Zbylý drahý profilový read je nejsilnější současný kandidát, ale PK-only
fixture se liší od local-demo indexů. Neodvozovat produkční nedostatek ani
produkční úsporu z neúplného schématu. Testovaný metadata-only support
workflow nejprve přečte skutečné definice; potom obě A/B varianty dostanou
stejné odpovídající indexy a rozhodne nový celý HTTP/Chrome výsledek.
Support PR není dokončením optimalizace. [Postup a brány](ATHLETE_PROFILE_TARGETED_READ_2026-11-09.md).


## D2026-11-09-RIX-H — filtrovat materializaci ownership stejným rokem

Vývoj/Codex podle pokynu vlastníka vybral po nativním42709050977 kandidáta
YEAR v measurement EXISTS: SQL −58.0/55.8%, úplná shoda12scénářů oběDB.
PlainJOIN/IN, summary join-order/narrow/HEX i pokrývající index nevybrané
kvůli nedostatečnému přínosu nebo regresím; raw v [plánu](ATHLETE_PROFILE_TARGETED_READ_2026-11-09.md).
Pouze redundantní filtr stejného tréninku, žádná změna scope, sčítání či schématu.
Commit/merge/deploy aplikace zůstává podmíněný celým HTTP/paritou/Chrome/CI.


## D2026-11-10-ASSET — odstranit prokázaně identickou druhou kopii Bootstrap CSS

Po nativním vyřazení RIX SQL/index variant zvolen bod7 původní analýzy:
3stránky mají stejný CDN i lokální Bootstrap5.3.3. Canonical232803bytes a živé
oba payloady mají shodný SHA384/SRI; appUiAssets poskytuje lokální kopii.
Pouze3tagy odstranit, ostatní zdroje/SQL/roles/writery beze změny. Přínos
primárně2→1Bootstrapstylesheet/30829CDNgzip bytes; FCP/cold/warm/ready změřit
reálně bez mockování sítě a zachovat bez významné regrese. Úzká normalizace
jediného známého resource rozdílu s negativními testy; data/HTML/UI jinak shodné.
Nasadit jen po celé přejímce a aktuálním CI podle [plánu](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md).


## D2026-11-10-ASSET-V — přijmout pouze ověřené odstranění duplicitních stylů

Přijato po 30 zelených CI kontrolách, dvou čerstvých nativních bězích obou DB,
544 měřených Chrome navigacích a 8 pixelově shodných dvojicích screenshotů.
Cold FCP je podle stránky/zařízení o 7–35 % nižší, CSS požadavky 2→1, živý gzip
přenos o 30 829 bytes menší. SQL se nemění a jeho čas není deklarovaným ziskem.
Baseline fyzické PHP kopie mají vlastní správný route root a jediný bootstrap;
jedinou úmyslnou HTML výjimkou je přesný druhý identický CDN tag. PHPUnit
fixture hodin se izolovaně sjednotila na Europe/Prague po reprodukci chyby i na
mainu. Žádná produkční účetní, rolová nebo datová změna. #425/9dbd9eaf sloučeno,
whole-tree totožné s6162; chráněný deploy42727693090 probíhá podle původního
podmíněného zadání vlastníka. [Přejímka](verification/bootstrap-assets-20261110/RESULTS.md).


Potvrzení vydání:42727693090 SUCCESS, záloha389/2, konfigurace/VS/migrace/indexy
zachované, HTTP smoke200 přes serverový fallback a8nezávislýchGET/DOM shodných.
Produkční /bin vrací stávající Apache403 před PHP, stejné jako migrate.php;
nativní PHP404/0 zůstává backstop. Přijato skutečné hostingové odmítnutí bez
uvolnění přístupu; nebylo nutné měnit aplikaci nebo rewrite pravidla.
Dokumentace426 neměníruntime9dbd9eaf a nevyžaduje druhý deploy.


## D2026-11-10-HJS — měřit cachovatelný společný staff JavaScript

Podle nového zadání vybrán neuzavřený bod7 původní analýzy:14.7kB stejného
kódu v každém staff HTML. P11-ASSET ani zamítnuté RIX varianty se neopakují.
Pouze kandidát do měření, nikoli slíbené zrychlení: nový studený request musí
projít bezregresním gate a úspora teplého HTML/cache být skutečně doložená.
Šest serverových hodnot zůstane čerstvých/soukromých, nikdy ve statickém JS;
stejný JS/pořadí/SQL a žádná aktivace hledání nebo push. [Plán](SHARED_HEADER_SCRIPT_PERFORMANCE_2026-11-10.md).


## D2026-11-10-JSZ — přednost skutečné kompresi před novým inline assetem

ProdukčníGET obou stávajícíchJS ukázaly89282bytes identity a max-age30; CSSgzip
není důkazem kompreseJS. HJS by přidal nový nekomprimovaný cold request, proto
není přijat. Prototyp zachovat vevlastnívětvi, novýslice JSZ ověří gzip stejného
veřejnéhoJS podassets skutečnýmApache. Žádná změnaTTL, SWroot, privátníhoHTML,
JSbytes, práv nebo zápisů. [Doklad](verification/shared-header-script-20261110/production-js-transport.json).


### P11-JSZ — přijetí a uzavření

Úzký7řádkový assets/.htaccess prošel12CI, nativním Apache2.4.58/2.4.66,
Chrome a produkčním ověřením po deploy42774356221. Rozbalenébytes13JS i8DOM
shodné, skutečný payload−69 % (hlavní2−70 %); časové zrychlení stránky
neprokázané. Cache30s zachovaná, standardní gzipETag revalidace200 přiznaná,
žádné oslabeníETag nebo force-gzip. PHP/SQL/JS/rootSW/práva beze změny.
HJS4c6ffea0 zůstává pouze prototypem; původní WIP zachovaný. [Release](verification/javascript-compression-20261110/release/RESULTS.md).


## D2026-11-10-HJS2 — nová přejímka inline JS po skutečném gzip

Po dokončeném JSZ má odložený14.7kB staff modul jiné transportní předpoklady.
Znovu jej posoudit podle aktuálního faed76c0 a skutečného Apache gzip/30s cache,
nikoli přeznačit původní zamítnutí. Nevynucovat výsledek změnou TTL, umělými
latencemi nebo oslabením cold/public gate. [Plán](SHARED_HEADER_SCRIPT_PERFORMANCE_2026-11-10.md#p11-hjs2--nové-posouzení-po-nasazení-gzip-10-11-2026).


## D2026-11-10-ASSET2 — skutečná duplicita rozcestníku před inlineJS

Chrome42783645170 odhalil skutečnéCSS2: unversioned +verzovaná totožnáBootstrap,
APItrue/chyby0. HJS2výběr tedy uzavřít bezrelease, všechnycommity/prototyp zachovat.
JednaodstraněnáduplicitníCSSodpověď v centrálním pracovnímrozcestníku má větší
přímýpayloadpřínos a menší sémantickýrozsah nežnováDOMJSONhranice. Ostatnínálezy
bezaktuálníhoHTTP/browserdůkazu nerozšiřovat. [Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset2--pracovní-rozcestník-nový-řez-10-11-2026).


### ASSET2 — přijetí a release

13CI i oběnativníDB, skutečnýApache/Chrome/cold/warm, source/wholeHTML/pixels/
role/security/checksum/cleanup PASS; přesnýmergedtree nasazen42790212078.
BootstrapCSS2→1, cold−31248gzipB; coldFCPmedian−13,3%desktop/−7,1%mobile,
warmFCP/payload bezezměny, žádné produkčníSLA. Prod8GET/DOM i CSS/SRI/cache
shodné; authUAT kvůlibrowserAPI NOTRUN. HJSprototypy a původníWIP zachované.
[Trvalérelease](verification/workspace-bootstrap-assets-20261110/release/RESULTS.md).

- D2026-11-10-08 — P11-ASSET4: obsahové verze pěti společných assetů místo mtime zachovají payloady i TTL a ušetří při nezměněném vydání CSS 51890 B (v JS TTL 78508 B). Manifest vzniká offline a skutečné bytes hlídá PHPUnit/deploy. Lossless WebP zamítnuto pro zvětšení. Nová přísná katalogová parita je doložená samostatně; chybějící historické before HTML se nenahrazuje domněnkou o fotografiích. Explicitní system-curl ve verifikačním CLI obchází pozorovanou místní transportní chybu, obsah/status/HTTPS gate se nemění. [Měření a meze](verification/catalog-audit-assets-20261110/RESULTS.md).

- D2026-11-10-09 — P11-ASSET5: odstranit dvě CDN Bootstrap CSS kopie z kalendáře individuálních lekcí a velodromu; společné lokální CSS ponechat byte-for-byte. Klubový kalendář již duplicitu nemá, slouží jako control. Akceptace samostatná, bez rozšiřování SQL/rezervací/manifestu. [Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset5--veřejný-kalendář-a-velodrom-10-11-2026).
