# Převzetí vývoje na další stanici

Stav nasazení, integrací, CI a otevřené provozní body jsou výhradně v
[CURRENT_STATE.md](CURRENT_STATE.md). Dřívější snapshoty zachovává
[CHANGELOG-PROVOZ.md](CHANGELOG-PROVOZ.md). Před změnou čti také [CLAUDE.md](../CLAUDE.md).

## Poslední návaznost: P07 jednorázový kroužek — 7. 11. 2026

Na pokyn vlastníka dokončena izolovaná kontrola nákupu, úhrady, soupisky,
e-mailu, účetního dokladu a vratky. [Protokol, důkazy a reprodukce](verification/one-time-course-uat-20261107/RESULTS.md).
Větev `codex/one-time-course-uat-20261107`, stejnojmenná pracovní kopie v
`C:/Users/Marek/Documents/Codex/evidencePavel-one-time-course-uat-20261107`.
96 testů / 1 963 assertions a MariaDB účetní acceptance PASS; místní rodič
v prohlížeči viděl aktivaci a po stornu potvrzenou vratku. Historie zachovaná.
Otevřené N1: automatické účetní PDF není napojené na běžný nákup;
N2: běžný košík neodesílá zprávu při vytvoření, stornu ani vratce.
E-mail po přijetí úhrady se správně vytvoří a projde místním transportem.
Samostatné vystavení PDF, oprava a účetní export fungují. Další kroky jsou
v [P07](WORK_PLAN_CURRENT.md), bez implementace nebo nasazení v této session.
Produkční sjednání členství, skutečná bankovní platba a doručení do schránky
zůstávají oddělené, tato syntetická přejímka je neuzavírá. Cizí WIP zachováno.
## Předchozí návaznost: P11 optimalizace docházky

Pokyn vlastníka „ok, pokračuj“ navazuje R06 bez změn produkce a se zachováním WIP.
Větev `codex/attendance-read-performance-20261107`, vlastní kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-attendance-performance-20261107`.
[Výsledky a trvalé důkazy](verification/attendance-read-performance-20261107/RESULTS.md).
Dávkové ověření ID mimo transakci hotové; čerstvá role a přesná původní
transakční cesta zachované. Místně 29 testů / 206 assertions a PHPStan/styl PASS.
Párové CI 10.3/11.4 PASS, všech 11 kontrol: 200 osob 244 → 45 SQL,
medián měřené části 2,30–2,48× rychlejší, všechna data i HTML shodné.
Finální dokumentační CI/merge u [PR #354](https://github.com/KovoPraha/evidenceTreninku/pull/354).
[Datované předání a zbývající práce](SESSION_HANDOFF_ATTENDANCE_READ_PERFORMANCE_2026-11-07.md):
plné HTTP, další role/propojené lekce a hierarchické filtry. Kalibrace měřidla
oddělená od platných výsledků; původní WIP shodné, žádná místní DB/HTTP služba.
Žádný deploy ani aktivace, produkční stav tato relace nemění.

## Předchozí návaznost: P11 optimalizace plánovače

Vlastník zadal optimalizaci R05 podle měření se zachováním chování a WIP,
bez produkčních změn. Větev `codex/planner-read-performance-20261106`, kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-planner-performance-20261106`.
[Výsledky a trvalé důkazy](verification/planner-read-performance-20261106/RESULTS.md).
Postup: dávkové souhrny RSVP, explicitní čtecí metadata a agregované historiové
upozornění bez filtru soupisky. Implementace hotová, 38 testů / 584 assertions,
4 JavaScript testy, styl a cílený PHPStan PASS. Transakce a POST zachované.
Párové CI 10.3/11.4 PASS: stejná data i HTML, 112/203 → 28 SQL,
medián měřené části 3,32–6,07× rychlejší. Finální CI/merge ověřit u
[PR #353](https://github.com/KovoPraha/evidenceTreninku/pull/353).
[Datované předání a zbývající práce](SESSION_HANDOFF_PLANNER_READ_PERFORMANCE_2026-11-06.md):
R06, plné HTTP měření a hierarchický filtr. WIP otisk shodný, žádné místní služby
nevznikly. Žádný deploy ani aktivace, produkční stav tato relace nemění.

## Předchozí návaznost: P11 optimalizace zdravotního přehledu

Vlastník zadal navázat optimalizací R13 bez produkčních změn a zachovat WIP.
[Plán, stav a důkazy](verification/medical-read-performance-20261106/RESULTS.md).
Vlastní větev `codex/medical-read-performance-20261106`, kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-medical-performance-20261106`.
Implementace hotová: stránka před načtením historie, minimální stream metadat
pro stavové filtry, omezený výběr osob při zachování jejich dostupnosti.
32 testů / 14 132 assertions a MariaDB parita 36 kombinací PASS, skutečný
místní browser výběru a původního formuláře PASS. Devět srovnávacích R13
scénářů před/po i párové CI 10.3/11.4 PASS. Úplný stav CI/merge určuje PR.
Při 10k: 50 224 → 132 řádků pro běžnou stránku, paměť asi 43 → 0,18 MiB.
Vlastní schémata uklizená, obě localhost služby zastavené, původní WIP otisk shodný.
[Datované předání a zbývající práce](SESSION_HANDOFF_MEDICAL_READ_PERFORMANCE_2026-11-06.md),
[PR #352](https://github.com/KovoPraha/evidenceTreninku/pull/352).
Žádný deploy ani aktivace. Původní rozpracované soubory se nepřepisují.

## Předchozí návaznost: P11 syntetické měření výkonu

Vlastník zadal měření bez produkčních změn se zachováním WIP. Místně dokončeno
21 případů R05/R06/R13, 630 časových vzorků, samostatné SQL/řádky/paměť/EXPLAIN.
[Výsledek, metoda a priority](verification/synthetic-performance-20261106/RESULTS.md).
Hlavní kandidát je zdravotní přehled: při 10k sledováních načte přes 50k řádků
pro stránku 25 položek; i filtr osoby stále načítá celý seznam osob. Následují
metadata/RSVP plánovače a dávkové ověření účastníků docházky. Runtime se nyní
neoptimalizoval a žádná změna se nenasazuje. Datová/HTML parita měřicích režimů
a čtyři negativní autorizační případy PASS. Testovací CI doplňuje stejnou metodu
na MariaDB 10.3/11.4: obě verze PASS, shodné SQL/řádky ve všech 21 případech;
[trvalé CI důkazy](verification/synthetic-performance-20261106/CI-VERIFICATION.md).
Aktuální hlavu ověřit u PR. Úplné HTTP/bootstrap/nav a další role,
včetně plánem propojené docházky, jsou dosud samostatné úkoly.
Větev `codex/synthetic-performance-20261106`, vlastní kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-performance-20261106`.
Původní `kis_rosters_admin.php` má shodný otisk; žádná rozpracovanost se nemazala.
Vlastní databázová schémata odstraněná a server zastavený. Automatická kontrola
odmítla smazání dočasného adresáře pouze jako „blocked by policy“; adresář
`C:/xampp/tmp/kis-perf-20261106-f39c6d1a` zůstává jako nepovinný místní úklid.
Produkční stav/flags nadále pouze v [CURRENT_STATE.md](CURRENT_STATE.md).
P05/P07, export P06, heslo/T07 a ranní audit jiné relace se nemění.

## Předchozí návaznost: P12 a syntetické přejímací podklady bez produkčních změn

Vlastník zadal pokračovat samostatně bez jeho podkladů a bez změn produkce.
P12 technicky hotové: všech sedm textů, 20 scénářů a spojený podklad sjednocené
proti revizi `3ff0fd13`, ověřené pevné zdroje a přenosový ZIP. R05/W11 nyní
rozlišují zastupování trenérů od soukromých profilů a cizího přehledu odpovědí;
členské popisky #340 i následný hostingový plán #341 zůstávají zohledněné.
[Sjednocený balíček](testing/chatgpt-tester-20261105/README.md),
[výsledky a meze](verification/offline-followup-20261106/RESULTS.md).
Existující syntetické regrese 97 testů / 527 assertions PASS; nebylo provedeno
nové produkční/mobilní/lidské UAT. P08/P09 zůstávají otevřené pro lidské převzetí.
P12 potřebuje už jen konkrétní testerku, roli/prostředí a skutečné předání přístupů;
žádná pozvánka, účet či oprávnění nebyly založené. Skutečné P05/P07, export P06
a heslo/T07 jsou nadále odložené. Ranní kontrolu členství vede souběžná relace,
neopakovat její běhy. P14 platí pro jmenované starší zálohy, nikoli novou zálohu #341.
Větev `codex/autonomous-offline-followup-20261106`, vlastní kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-offline-followup-20261106`.
Původní WIP zachované; žádný deploy ani jiné produkční workflow této relace.
Současný release/flags určuje [CURRENT_STATE.md](CURRENT_STATE.md); starší
oddíly níže jsou datovaná předání, nikoli tvrzení o posledním vydání.

## Serverová ranní kontrola členství — připraveno bez PC

[Serverové předání](SESSION_HANDOFF_SERVER_MEMBERSHIP_AUDIT_2026-11-06.md),
[důkazy](verification/membership-morning-server-release-20261106/RESULTS.md),
[plán](MEMBERSHIP_SCHEDULER_MORNING_AUDIT_2026-11-07.md).
#346 nasazené, pozorovatel přirozeně čeká, #350 opravilo předání PR CI.
Server sám sloučil preflight #348 po všech 11 kontrolách. Místní úloha PAUSED;
hosting i GitHub pracují bez počítače vlastníka. Skutečné P05/P07 a export P06
dál odložené. Nový deploy kvůli tomuto souborovému předání nedělat.

<!-- membership-morning-publish:start -->
## Ranní audit 7. 11. — čeká na skutečný termín

Ranní výsledek **NOT RUN**. Hosting jej zachytí od 05:30 UTC (06:30 CET),
serverový přenos doplní protokol a tento handoff přes připravený
[výsledkový PR #347](https://github.com/KovoPraha/evidenceTreninku/pull/347).
PC ani místní Codex nejsou potřeba. [Přesný postup a stav](MEMBERSHIP_SCHEDULER_MORNING_AUDIT_2026-11-07.md).
Skutečné P05/P07 a export P06 zůstávají odložené.
<!-- membership-morning-publish:end -->
## Původní místní úloha je pozastavená

Původní navazující úloha rann-audit-lenstv-7-11 z #344 má stav PAUSED. Nahradila
ji serverová kontrola podle plánu výše, počítač není podmínkou auditu ani
zápisu výsledků. Kvůli přejímce ručně nespouštět účtování. P05/P07 a P06 dál
odložené vlastníkem.
## Předchozí relace: autorizované vydání členských popisků #340

Vlastník zadal výslovné NASADIT pouze #340. Chráněný deploy 42236370197 SUCCESS,
6. 11. v 15:46 CET; přesnou identitu vydání vede [CURRENT_STATE.md](CURRENT_STATE.md).
Katalog a detail nyní používají členství/tarif; živý Chrome i anonymní HTTP PASS.
Cena 2200 Kč/měsíc a běžné nečlenské nabídky zachované. Konfigurace a kontrolované
integrační přepínače před/po shodné, import/UAT false; žádná další aktivace.
[Úplný výsledek, důkazy a meze](verification/membership-copy-release-20261106/RESULTS.md).
Větev `codex/membership-copy-release-20261106`, izolovaná kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-membership-copy-release-20261106`;
původní WIP v `C:/xampp/htdocs/evidencePavel` zachovaný včetně kontrolního otisku.
Záloha před aktivací má 387 tabulek / 2 triggery; její hash ověřen, nová izolovaná
obnova NOT RUN. Historická obnova P14 zůstává platná pro předchozí generace.
Skutečné sjednání/platba/PDF P05/P07 i export P06 nadále odložené vlastníkem.
Následující záznamy o připravené opravě bez nasazení popisují předchozí relaci.
Dokumentační předání nevyžaduje další deploy; aktuální stav PR/CI je v protokolu.

## Povinné průběžné předání

Autonomní návaznost 6. 11.: vlastník potvrdil doručení P01, ostatní skutečné
podklady dodá později. Hlavní technický průchod vede souběžná větev
`codex/autonomous-p01-p14-20261106`; nepouštět duplicitní provozní běhy.
[Doplňující relace P10 a členských popisků](verification/membership-copy-wip-20261106/RESULTS.md)
posoudila 135 pracovních kopií a připravila úzkou opravu katalogu/detailu.
Původní WIP zachovaný, bez produkčního zápisu či nasazení. Stav dokončení
a další krok jsou ve společném plánu a propojených protokolech.

Tato dokumentační relace pouze sjednocuje předání; nic nového na produkci
nezapíná. [Záznam sjednocení a kontroly](verification/documentation-reconcile-20261106/RESULTS.md)
uvádí původ souběžných změn #333/#334. Jediný seznam další práce je
[WORK_PLAN_CURRENT](WORK_PLAN_CURRENT.md); [WORK_REGISTER](WORK_REGISTER.md)
zůstává kompatibilním rozcestníkem na tentýž registr, nikoli druhou frontou úkolů.
Novější souběžné předání #335 dokládá již zveřejněnou nabídku BAR;
[session handoff baru](SESSION_HANDOFF_BAR_2026-11-06.md) a [pravidla dokumentace](WORK_DOCUMENTATION_POLICY.md)
jsou zachované. Tato relace stav pouze načetla a popsala, publikaci neprováděla.

Vlastník 6. 11. výslovně požaduje zapisovat rozpracovanou i plánovanou práci
přímo do verzovaných souborů, nikoli ponechat její jediný záznam v chatu.
Před prací číst a během práce aktualizovat [WORK_PLAN_CURRENT](WORK_PLAN_CURRENT.md):
ID, stav, další krok, závislosti, větev/worktree a odkazy na tematický plán/důkazy.
Release a flags nadále určuje pouze CURRENT_STATE; plán nenahrazuje provozní souhlas.
Před ukončením session aktualizovat související soubory a uvést stav PR/merge/deploy.
Starší [session handoff](plan-eshop-tymova-evidence/SESSION_HANDOFF.md) je historická
kompatibilní cesta s odkazem sem, nikoli zdroj dnešního SHA nebo přepínačů.

[Provozní inventura z 6. 11.](verification/operations-inventory-20261106/RESULTS.md)
opravuje mezitím zastaralý přehled: #330 už je sloučený a nasazený,
minutový worker běží a fronta má doložené přijetí zpráv transportem.
[Produkční protokol upozornění](verification/staff-notifications-release-20261106/RESULTS.md)
dokládá také přihlášený zvoneček, přesný odkaz a změnu přečtení. P01 už nečeká
na opakování této kontroly; vlastník 6. 11. potvrdil přijetí zprávy do schránky,
P01 je splněné v doloženém rozsahu. Nejde o potvrzení všech příjemců. Kontrolní workflow
`staff-notifications-production.yml` / `action=status` / `OVERIT-UPOZORNENI`
nic neposílá. Instalaci ani první rozesílku neopakovat; přečtení není vyřízení.
P02–P14 vedou BAR, členství, poslední synchronizaci, platby, lidskou přejímku,
lokální WIP, další typy upozornění, podklady testerky a ověření aktuální zálohy.


Podrobnosti zápisu určuje [trvalé pravidlo](WORK_DOCUMENTATION_POLICY.md). Aktuální [session handoff BAR](SESSION_HANDOFF_BAR_2026-11-06.md) obsahuje dokončenou publikaci i pořadí zbývajících kroků.

Omluvenky a náhrady nepovažovat za čekající nasazení podle existence starých větví.
Aktuální stav a [kontrola z 6. 11.](verification/makeup-audit-20261106/RESULTS.md)
dokládají již nasazenou funkčnost: 22 testů / 139 assertions, 23 HTTP scénářů
a místní browser omluvy, rezervace a storna PASS. Nárok vzniká až po ověření
skutečné docházky; samotná omluva nestačí. Kontrola nevyžádala opravu ani nové
nasazení. Zápisy byly pouze syntetické; přihlášené produkční UAT tím není uzavřené.

[Klubové tarify #316](verification/membership-catalog-release-20261105/RESULTS.md) jsou nasazené.
[Závodní tým je podle produkční kontroly 6. 11. schválený a zveřejněný](verification/racing-team-status-20261106/RESULTS.md).
Produkt 297 / varianta 951 stojí 2200 Kč/měsíc; výběr více disciplín nemění cenu.
Fakturační údaje TJ KOVO jsou uložené. Neopakovat založení, schválení ani publikaci.
Zákazník musí mít klubem ověřeného sportovce; použitý účet jej nemá, proto úplné
sjednání/úhrada/PDF zůstávají neověřené. Starý protokol konceptu popisuje jen minulý stav.
[Pravidelné zpracování i pošta jsou zapnuté](MEMBERSHIP_MAIL_ACTIVATION_2026-11-05.md).
[Denní časování je opravené a hostingový plán ověřený](verification/membership-scheduler-20261106/RESULTS.md):
05:23 UTC, přirozené minutové tiky, GitHub schedule false, APPLY/MAIL true.
Soukromá automatizace i pošta zůstaly true; dnešní úspěšné zpracování se
neopakovalo. Další ranní běh je budoucí důkaz, nezaměňovat jej s testem hodin.
Skutečné sjednání/platba/PDF/doručení členství zůstávají odložené vlastníkem;
nejde o doručení pracovního upozornění P01. Export P06 také odložený.
Podporované roční tarify nemají zadanou skutečnou cenu a žádný roční produkt nevznikl.

[Měsíční členství a zastupování trenérů #314](verification/monthly-coach-release-20261105/RESULTS.md) jsou sloučené a nasazené.
Trenérské přiřazení nově určuje pořadí výběru, ne možnost vypsat/zapsat trénink za kolegu.
Měsíční automatizace a plán následně zapnuté v #318, pošta zapnutá v #321; před ostrým sjednáním je třeba schválit konkrétní tarif podle [runbooku](MONTHLY_MEMBERSHIP_RUNBOOK.md).
Produkční apply i uložený heartbeat PASS (0 členství), skutečná měsíční platba/PDF/doručení ještě neověřené.

Ostré platby mají [protokol aktivace Fio a SumUp](verification/live-payments-20261105/ACTIVATION.md).
Vlastník schválil stávající SumUp klíč a kartu pro všech 223 aktivních produktů
pro nové objednávky. Historické platby zachovávají uložený režim. Fio historii
neimportovat znovu od začátku; nejednoznačné pohyby čekají na účetní posouzení.
[Hostingový Fio plán a 45 pohybů](verification/fio-followup-20261105/RESULTS.md) jsou nově ověřené.
Všech 45 zůstává bez odpovídajícího VS; tři shody částky nejsou dostatečný důkaz.
Status číst přes fio-operations-production.yml; skutečná placená úhrada a přihlášené
platební UAT nejsou doložené. Redundantní GitHub plán je vypnutý. Heslo/T07 dál vynechat.

Pro následující práci na členství platí [pracovní plán evidence členů bez sezon](MEMBER_EVIDENCE_WORK_PLAN_2026-11-02.md)
z 2. 11. 2026. Vlastník potvrdil trvalé skupiny/soupisky a časové členství;
historie před přechodem zůstane v původním KIS. Plán obsahuje inventář stránek,
etapy E0–E8, rozhodnutí D1–D8 a akceptaci A01–A20. Aplikační implementaci E1–E5 a nástroj E6 popisuje
[implementační protokol](MEMBER_EVIDENCE_IMPLEMENTATION_2026-11-02.md). Produkční
inventura a technický nácvik skutečné aplikační zálohy prošly. [Podklady převodu](MEMBER_EVIDENCE_PRODUCTION_TRANSITION_2026-11-02.md)
obsahují potvrzené rozhodnutí převzít 435 aktivních zařazení od 2. 11. 2026 a odstranit
560 historických. Jedna poslední synchronizace je očekávána; datum začátku neposouvá.
Nový nástroj přijímá explicitní rozdíl nad konkrétním exportem a odmítá opakovanou dávku.
Rozšířený nácvik 435/560 na skutečné izolované záloze prošel v běhu 41512931635,
včetně odstranění 560 importních událostí, odpojení převodních vazeb a úplné obnovy.
Vlastník následně schválil [konkrétní mapu D2](MEMBER_EVIDENCE_APPROVED_MAP_2026-11-02.md):
sedm provozních skupin / 41 soupisek a samostatná skupina pro tři testy.
Rexonix je závodní, KDC a Bikros dvě samostatné placené skupiny `other`.
Zdrojová ID, kódy a názvy jsou v soukromém provozním podkladu mimo Git.
Implementace mapy je sloučená v PR #285. Nácvik přesné mapy 41532405191 prošel:
8 skupin, 44 hran, 435 počátečních zařazení, 560 odstraněných historických intervalů,
parita skupin, pilotu, opakování i obnova DB PASS. Všech 11 kontrol PASS (1953 testů / 38293 assertions).
Schválený produkční převod byl potvrzen v běhu 41542954307: 435 počátečních zařazení od 2. 11.,
560 historických intervalů a importních událostí odstraněno, 8 skupin / 44 hran. Technická
identita je po převodu neaktivní bez oprávnění; provoz obnoven. Červený výsledek workflow
způsobil až následný HTTP timeout runneru; nezávislé HTTP 40/40 a čtecí inventura PASS.
Přihlášené UAT, poslední synchronizace a E8 zůstávají otevřené. Živé vydání a zálohy
určuje CURRENT_STATE a [protokol převodu](verification/member-evidence-cutover-20261102/RESULTS.md). Převod neopakovat.

Navazuje [trenérská obnova hesla](TRAINER_READINESS_2026-10-31.md) a její
[produkční přejímka](verification/trainer-recovery-20261031/RESULTS.md).
Odesílání trenérských odkazů zajišťuje [ověřený minutový cron hostingu](verification/trainer-recovery-20261031/scheduler/RESULTS.md).
Vlastník potvrdil doručení a zvolil [samostatný syntetický pilot](TRAINER_PILOT_2026-10-31.md).
Pilot je [založen a technicky ověřen](verification/trainer-pilot-20261101/RESULTS.md):
trenér 56, soupiska 77, pouze dvě syntetické osoby. Grant končí 15. 11. 2026
v 18:17:31 databázového času; nedochází k automatickému prodloužení ani smazání.
Vlastník si nastaví heslo sám přes trenérskou obnovu na schválené samostatné
adrese. Adresa zůstává v soukromém provozním dokladu, není součástí zdrojů.
První dvě adresy patřily veřejným účtům bez pracovní vazby; nepovyšovaly se.
[Dohledání](TRAINER_OWNER_LOOKUP_2026-11-01.md) a
[provozní varianta](TRAINER_PILOT_OPERATIONAL_2026-11-01.md) mají samostatné doklady.
Ke kontrole použít `trainer-pilot-production.yml` na main s `action=status`
a `mode=operational`. Opakované `create` neobnovuje heslo ani neprodlužuje grant.
Technická auditní identita zůstává neaktivní, bez pozic a superadmina.
Pro interní test jsou připravené [scénáře T01–T08](TRAINER_UAT_POSTUP_2026-11-01.md),
[checklist k přeposlání](TRAINER_UAT_CHECKLIST_2026-11-01.md) a
[browser zadání pro jiné vlákno](TRAINER_UAT_BROWSER_2026-11-01.md).
[Částečné browser UAT](verification/trainer-browser-uat-20261101-1955-a7c2/RESULTS.md) proběhlo:
vznikl a jednou se upravil syntetický trénink 1604, záznam zůstává pro diagnostiku.
Nález zobrazení formuláře pro použitý odkaz řeší nasazený PR #269;
[protokol vydání](verification/trainer-reset-release-20261101/RESULTS.md) odděluje technické
ověření neplatných odkazů od skutečného použitého odkazu a T07, které zůstávají odložené.
Nové heslo nadále zadává vlastník; nečíst hesla ani správce hesel.
Výslovně povolené otevření odkazu z cílené testovací zprávy je doloženo níže;
hodnotu odkazu neexportovat ani neukládat do důkazů.
Stav deploye, SHA a flags určují výhradně CURRENT_STATE a jeho doklady.
[Autonomní rozšíření](verification/trainer-recovery-autonomous-20261101-2341/RESULTS.md)
ověřilo skutečné nové doručení, platnou předběžnou kontrolu a oba režimy bez změny hesla.
Nový izolovaný test se dvěma PHP relacemi ověřuje backend revokace, nepředstírá produkční T07.
[Funkční rozšíření 2. 11.](verification/functional-uat-20261102/RESULTS.md) odkládá změnu hesla/T07 dle vlastníka;
opravuje vrstvy účtového menu, přístupné názvy a skrytou poznámku měření.
[PR #272 je sloučen a nasazen](verification/functional-release-20261102/RESULTS.md):
HTTP 26/26, skutečné CSS a veřejné filtry PASS; přihlášené produkční UI
se kvůli odhlášené relaci neopakovalo. Změnu hesla a T07 nadále vynechat.
Dokumentační PR nemění živé SHA a nevyžaduje další deploy.
[Druhý místní funkční průchod](verification/functional-uat-round2-20261102/RESULTS.md)
ověřil akce, velodrom, katalog, kupóny, storno a simulovanou úhradu/vratku.
Oprava odděluje zboží od programu v localhost seedu a zachovává historii staršího dema.
[PR #274 je sloučen a nasazen](verification/functional-round2-release-20261102/RESULTS.md);
produkční dostupnost a konfigurace ověřeny. Samotný seed běží výhradně v lokálním testu;
do produkce se demo data nepřenášela. Heslo a T07 nadále vynechat.
[Třetí autonomní průchod 4. 11.](verification/functional-uat-round3-20261104/RESULTS.md)
rozšířil přihlášené produkční kontroly, místní browser trenéra/registráře/rodiče
a 119 kontrol pracovních přístupů. [PR #293 je sloučen a nasazen](verification/functional-round3-release-20261104/RESULTS.md):
1971 testů, všech 11 kontrol, HTTP 30/30 i izolovaná obnova čerstvé zálohy PASS.
Produkční náhled členství ověřen bez potvrzení; rodinná stránka funguje, ale účet nemá
propojené profily pro ověření štítků. Úplné UAT všech rolí, poslední synchronizace a E8 zůstávají otevřené.
[Čtvrtý funkční průchod](verification/functional-uat-round4-20261104/RESULTS.md) ověřil zdravotní podklady,
docházku a náhrady: 70 HTTP scénářů a místní browser. Oprava zachování papírového původu
při opravě docházky je [sloučená a nasazená v PR #295](verification/functional-round4-release-20261104/RESULTS.md). Heslo a T07 se nadále vynechávají.
[Pátý funkční průchod](verification/functional-uat-round5-20261104/RESULTS.md) přidal 73 HTTP kontrol
souhlasů, papírových podkladů a vyřízení osoby, cílené 35/288 a MariaDB souběžnost PASS.
Nový runtime nález se nepotvrdil; ovládání prohlížeče neodpovídalo, browser této vlny NOT RUN.
[Šestý průchod v Chrome](verification/functional-uat-round6-20261104/RESULTS.md) doplnil skutečné browser scénáře registráře:
produkční mobilní UI bez zápisů, místní souhlasy, historický papírový podklad s opravou a pracovní úkol PASS;
16 kontrol persistence PASS, bez nové chyby runtime. PR #295 je následně sloučený a nasazený; heslo/T07 vynechané.
[Sedmý funkční průchod](verification/functional-uat-round7-20261104/RESULTS.md) opravil ztrátu osob za první tisícovkou
ve filtru úrovní zapojení; úplné počty a stránkování, Chrome a 39 HTTP scénářů PASS.
Oprava je [sloučená a nasazená s bezpečnostním odblokováním](verification/functional-round7-release-20261104/RESULTS.md); sportovní podklady a původní export ověřené. Heslo/T07 vynechané.
[Osmý funkční průchod](verification/functional-uat-round8-20261104/RESULTS.md) ověřil přípravu období a věkové návrhy:
25 kontrol, skutečný místní browser a 21/190 cílených testů PASS; bez nové chyby runtime.
Přihlášené produkční UAT tohoto vydání bez relace NOT RUN; technické ověření a izolovaná obnova PASS.
Chroot nemá `sha256sum` a zakazuje PHP `-r`; kontrola hashů používá soukromý
PHP soubor spouštěný bez přepínačů, stejně jako již ověřený worker.
Před použitím reálných skupin vyřešit blokované e-maily/vazby a potvrdit jejich rozsahy. Rozsahy nepřiřazovat odhadem.

W9.2 / W10.1 jsou [nasazené a ověřené](verification/refactoring-w92-release-20261030/RESULTS.md).
W10.2 je [nasazená](verification/refactoring-w102-release-20261030/RESULTS.md).
W10.3–W15 jsou zahrnuty v [závěrečné technické přejímce](REFACTORING_CLOSURE_2026-10-30.md).
Implementace, CI i finální chráněné nasazení jsou dokončené a technicky ověřené.
[Původní neúspěšný pokus](verification/refactoring-final-release-20261030/RESULTS.md)
a [následná obnova DB kapacity a úspěšný release](verification/db-temp-recovery-20261030/RESULTS.md)
mají oddělené protokoly. Globální Search Velocoty a jeho cron zůstávají
vypnuté; bez trvalé kapacitní nápravy nebo ověřeného úspornějšího indexu je nezapínat.
Stav nasazení ověřte v CURRENT_STATE; samotný lokální PASS není release.
Skutečné účetní UAT zůstává oddělené. Po nové migraci nevracet starý katalog;
opravné vydání zachová migrační soubor a ledger, bez mazání dat nebo záloh.

[Devátý autonomní průchod](verification/functional-uat-round9-20261104/RESULTS.md) odhalil souběh generování s odhlášením či revokací vazby. [PR #300 je sloučený a nasazený](verification/functional-round9-release-20261105/RESULTS.md); přesné vydání a výsledky určuje CURRENT_STATE. Heslo/T07 dál vynechat; produkční přihlášené UAT této vlny bez relace NOT RUN.

[Desátý průchod](verification/functional-uat-round10-20261105/RESULTS.md) opravil kontext objednávky a neúplný seznam vratek. [PR #305 je sloučený a nasazený](verification/functional-round10-release-20261105/RESULTS.md): skutečný přihlášený čtecí Chrome, HTTP a izolovaná obnova PASS; rozsah a omezení jsou v protokolu. Přesné živé vydání určuje CURRENT_STATE. Heslo a T07 dál vynechat.

## Nová stanice: čisté převzetí

```powershell
cd C:\Users\Marek\Documents\Codex
git clone https://github.com/KovoPraha/evidenceTreninku.git evidencePavel
cd evidencePavel
git fetch --prune origin
git switch main
git pull --ff-only origin main
git rev-parse HEAD
git rev-parse origin/main
```

Poslední dva příkazy musí vrátit stejné SHA. Potom spusťte
`PRIPRAVIT_LOCALHOST_TESTOVANI.cmd`. Skript připraví ignorovaný `config.php`,
oddělenou lokální MariaDB na portu 3308, migrace a syntetická demo data. Pro
další starty slouží `START_LOCALHOST_TESTOVANI.cmd`.

## Existující clone: bezpečná synchronizace

Nejdřív zjistěte, zda stanice neobsahuje rozpracovanou práci:

```powershell
git status --short --branch
git fetch --prune origin
git branch --show-current
git rev-list --left-right --count HEAD...origin/main
```

Pokud je pracovní strom čistý a lokální `main` nemá vlastní commity:

```powershell
git switch main
git pull --ff-only origin main
```

Pokud jsou přítomné lokální změny nebo vlastní commity, nepoužívejte force,
reset ani přepis souborů. Nejdřív práci uložte do samostatné větve a teprve
potom synchronizujte `main`. Cizí rozpracované změny se nesmí přidat do nového
commitu jen proto, že leží ve stejném adresáři.

## Zahájení nové práce

```powershell
git fetch --prune origin
git switch -c codex/kratky-popis origin/main
```

Před commitem zkontrolujte změněné soubory a spusťte přiměřené testy. Úplná
základní brána je:

```powershell
composer install
composer validate --strict
composer test
$env:APP_HOST='localhost'
php bin/migrate.php --check --json
git diff --check
```

Nová migrace musí být nový neměnný soubor v `migrations/`. Již přijatá migrace
se neupravuje. Pokud přidává tabulku vlastněnou aplikací, musí se současně
aktualizovat zálohovací ownership kontrakt v `bin/db-backup.php` a jeho testy.

## Pull request a předání

Vlastní větev odešli, otevři PR a ověř zelené kontroly pro přesný head commit.
Před případným sloučením znovu fetchni main a ověř aktuální pravidla repozitáře.
Merge a produkční deploy jsou samostatná rozhodnutí vlastníka podle
[NASAZENI.md](NASAZENI.md); samotné předání, push ani zelené CI je nepovolují.

## Průběžný plán

Začlenění baru řídí [plán z 5. 11. 2026](BAR_INTEGRATION_PLAN_2026-11-05.md).
Práce probíhá v izolovaném worktree; vlastník následně výslovně povolil nasazení
a pokračování převodu. Zachovává se katalog a pouze účet lucka, provozní historie
se nepřenáší. Dvě nové pozice doplňuje samostatné oprávnění vlastní spotřeby.
B0–B6 jsou po výslovném souhlasu vlastníka sloučené a [nasazené do KIS](verification/bar-release-20261105/RESULTS.md).
Úplná skutečná obnova DB i 4341 souborů před vydáním a čtecí přihlášený Chrome PASS;
aktuální živé SHA, CI a migrace určuje výhradně CURRENT_STATE. [Skutečný převod B7](verification/bar-cutover-20261106/RESULTS.md)
je ověřený: 68 produktů / 51 fotografií, pouze lucka jako obsluha s původním heslem,
schválená správa a vlastní spotřeba přidělené pouze existujícímu vlastníkovi. Import
neopakovat ani neobnovovat starou DB přes nové operace. Starý BAR je přesměrovaný:
44 vstupů a samostatný status PASS, úplná obnova 500 původních souborů ověřená.
Pět původních produkčních workflow je pozastavených, zdrojová DB zachovaná.
[Technická provozní přejímka](verification/bar-acceptance-20261106/RESULTS.md) nad novou syntetickou DB
v prohlížeči PASS: hotovost, čekající QR/karta, vratky kelímků, storna, uzávěrka se schodkem a vlastní spotřeba podle grantu.
Na výslovný pokyn vlastníka je **[nabídka 1 / Bar KOVO – základní nabídka zveřejněná jako vydání 1](verification/bar-menu-publication-20261106/RESULTS.md)**:
45 produktů, 45 fotografií a staré menu → KIS anonymně ověřené. Nabídku znovu nezakládat ani bez důvodu znovu nepublikovat.
Kasa ani zásoby publikací nevznikly. Skutečné přihlášení Lucky, lidská finanční přejímka,
první skutečné zásoby/kasa a účetní propojení mají další postup v [session handoffu](SESSION_HANDOFF_BAR_2026-11-06.md).
[Účetní kontrakt](BAR_ACCOUNTING_CONTRACT_V1.md) a [návrh adaptéru](BAR_ACCOUNTING_ADAPTER_PLAN_2026-11-05.md)
neaktivují skutečné zaúčtování ani nové bankovní párování.

Navazující [komplexní refaktoring celé aplikace](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md)
je sloučený v PR #219 a vlastník zahájil autonomní realizaci. Vlny 1–5 jsou
dokončené; [první K0/W6.1 balík](REFACTORING_K0_W6_2026-10-29.md) je sloučený
v PR #220. [W6.2](REFACTORING_WAVE6_2026-10-29.md), oddělení čistého výpočtu
kapacit, je ověřené a nasazené. [W6.3](REFACTORING_W63_2026-10-29.md) doplnilo
migrovaný profil a postupné čtení s úsporou paměti; je také nasazené.
[W7.1a](REFACTORING_W71_2026-10-29.md) nasadilo cílené ověření rodinného přístupu
s rozpočtem čtyř SQL. [W7.1b](REFACTORING_W71B_2026-10-29.md) opravilo
revokaci během čekání RSVP/náhrad: aktuální zamykající kontrola před zápisem,
32/32 souběhů v obou MariaDB CI. [W7.2](REFACTORING_W72_2026-10-29.md)
nasadilo cílené načítání jednotlivých profilů: celý GET pro 20 osob má
2706 → 2326 SQL a shodná data/HTML; CI medián o 10–12 % nižší.
[W7.3](REFACTORING_W73_2026-10-30.md) je také nasazené: metadata během GET
sdílená v rámci PDO, SQL 2244 → 752 a metadata 1590 → 98 pro 20 osob.
Výstup, historie a aktuální oprávnění zachovány, POST bez cache.
[W7.4](REFACTORING_W74_2026-10-30.md) je nasazena: členské dotazy 160 → 27,
celý GET 752 → 619 SQL, stejné výsledky a čerstvá autorizace.
[W7.5](REFACTORING_W75_2026-10-30.md) je nasazena: stránka 50 z 10 000 osob
načítá 53 místo 10 003 řádků; CI paměť stránky 100 klesla z 36,8 na 0,43 MB.
[Provozní důkazy](verification/refactoring-w75-release-20261030/RESULTS.md).
[W7.6 / W8.1](REFACTORING_W76_W81_2026-10-30.md) je nasazena z PR #237:
roční časová osa 4176 → 16 SQL, shodný výstup;
[release důkazy](verification/refactoring-w81-release-20261030/RESULTS.md).
R03 nemá N+1, finanční historie editoru pokračuje ve W10. Vlastník autorizoval
autonomní pokračování až do technického dokončení W15 včetně sloučení a nasazení
po úspěšném ověření; nový pokyn pro každou vlnu není potřeba.
Plošné měření K0.2 zůstává otevřené;
dávkování kapacit mezi nabídkami je samostatný kandidát.

[W8.2](REFACTORING_W82_2026-10-30.md) je nasazena: společné historické
filtry plánů/tréninků a náhled kopírování, roční filtr 1875 → 5 SQL.
[Společné vydání s W8.3](verification/refactoring-w82-release-20261030/RESULTS.md)
prošlo 1839 testy / 36699 assertions a produkčními čtecími kontrolami.

Před větší implementací ulož konkrétní plán do docs/; zaznamenej rozsah
oprávnění, větev, pracovní kopii, dokončené kroky, testy a další krok.
Před předáním/kompaktací jej obnov a při pokračování znovu přečti.
Průběžné změny a další etapy patří přímo do příslušných souborů podle [pravidel zápisu](WORK_DOCUMENTATION_POLICY.md), včetně vzájemných odkazů; chat není jediným předáním.
Aktuální práce dozoru: [pět balíků a jejich hranice](DOZOR_2026-10-25.md).
Navazující opravy a důkazy: [F1–F3 po nezávislé přejímce](DOZOR_F1_F3_2026-10-26.md).

## Co není v GitHubu

- `config.php` a jakákoli tajemství;
- lokální nebo produkční databáze a obnovovací dumpy;
- skutečné osobní údaje, importy a přílohy;
- lokální outboxy, session a cache;
- `vendor/`, který obnoví Composer podle `composer.lock`.

Tyto výjimky jsou bezpečnostní hranice, nikoli chybějící část synchronizace.

## Kde hledat aktuální informace

- stav GitHubu, produkce a posledních oprav: [`CURRENT_STATE.md`](CURRENT_STATE.md);
- dokumentační rozcestník: [`README.md`](README.md);
- lokální instalace: [`localhost-testovani.md`](localhost-testovani.md);
- produkční nasazení: [`NASAZENI.md`](NASAZENI.md);
- produkční UAT: [`PRODUKCNI-UZIVATELSKE-TESTOVANI.md`](PRODUKCNI-UZIVATELSKE-TESTOVANI.md);
- pravidla migrací: [`../migrations/README.md`](../migrations/README.md).

Datované dokumenty jako `HANDOFF_2026-08-29.md`, auditní zprávy a implementační
prompty jsou historické záznamy. Při rozporu nemají přednost před aktuálním
kódem, `origin/main`, CI ani tímto dokumentem.

### Průběžná W8.3

[W8.3](REFACTORING_W83_2026-10-30.md) nasazena společně s W8.2:
rodinný kalendář 4199 → 39 SQL pro 20 osob/rok.
[Release a čtecí důkazy](verification/refactoring-w82-release-20261030/RESULTS.md).
W8.4 klubový kalendář je rovněž
[nasazen z PR #241](verification/refactoring-w84-release-20261030/RESULTS.md):
1842 testů / 36744 assertions, HTTP 26/26, živé SHA a beze změny konfigurace.
W9.1 skladový přehled je [nasazen z PR #244](verification/refactoring-w91-release-20261030/RESULTS.md):
1844 testů / 36778 assertions, HTTP 26/26, živé SHA a flags ověřené.
Součástí jsou i testovací/CI kontrakty W8.5. Navazující W9.2 / W10.1 mají
vlastní [release důkazy](verification/refactoring-w92-release-20261030/RESULTS.md).

## Navazující pořadí práce 6. 11. 2026

[Registr P01–P14](WORK_PLAN_CURRENT.md) nyní obsahuje také doporučené pořadí
a rozdělení autonomních kroků oproti vstupům vlastníka. Nejprve P14 a čtecí
důkaz automatického plánu P05, souběžně P01/P12 a příprava BAR P02; potom
skutečný tok P05/P07, účetní specifikace P04 a rozdílový sync P06 po exportu.
Tato plánovací session nové produkční operace neprovedla ani nepovolila.

## Autonomní průchod přes P01–P14

[Protokol](verification/autonomous-p01-p14-20261106/RESULTS.md): P01 potvrzené
vlastníkem, konkrétní obnova P14 PASS, schedule P05 PASS s časovou odchylkou.
Skutečné P05/P07 a export P06 vlastník odložil. Cílené testy 136/753 PASS,
produkční invarianty 16/16, kapacitní profil a syntetický tisk BAR PASS.
Chybějící skutečné podklady a lidská rozhodnutí vede tentýž protokol.


## Časování členství — dokončená technická oprava 6. 11. 2026

[Session handoff](SESSION_HANDOFF_MEMBERSHIP_SCHEDULER_2026-11-06.md),
[plán](MEMBERSHIP_SCHEDULER_2026-11-06.md),
[provozní důkazy](verification/membership-scheduler-20261106/RESULTS.md).
#341 sloučené a chráněně nasazené; hosting instalovaný, přirozené tiky a
zachování konfigurace ověřené. Redundantní GitHub schedule false, ruční
workflow zachované. Skutečné P05/P07 a export P06 dále odložené vlastníkem.
Dnešní zpracování se neopakovalo, čas se kvůli testu neposouval.
Navazující časovou přejímku a její skutečný stav vede serverový plán výše.
Při diagnostice použít čtecí status, nikoli ruční apply. Původní WIP zachovaný; přesný rozsah je v protokolu.

<!-- membership-morning-preflight:start -->
## Serverový preflight

[Serverový preflight](verification/membership-morning-server-20261106/RESULTS.md).
Kontrola i přenos běží na serverech, bez zapnutého počítače vlastníka.
Skutečné P05/P07 a export P06 zůstávají odložené.

<!-- membership-morning-preflight:end -->
