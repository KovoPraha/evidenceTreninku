# Měsíční členství: obsluha a aktivace

**Vydání 5. 11. 2026:** implementace a migrace jsou [nasazené a technicky ověřené](verification/monthly-coach-release-20261105/RESULTS.md), včetně produkčního dry-run. [Automatizace a denní plán jsou následně zapnuté](verification/membership-automation-20261105/RESULTS.md), první prázdný apply a heartbeat PASS. [Závodní tým je podle produkční kontroly 6. 11. schválený a zveřejněný](verification/racing-team-status-20261106/RESULTS.md); sjednání vyžaduje ověřenou vazbu na sportovce. [Pošta je následně zapnutá](MEMBERSHIP_MAIL_ACTIVATION_2026-11-05.md), prázdný apply --send a heartbeat PASS. Následující postup popisuje ovládání a přípravu dalších tarifů.

Plán původního měsíčního toku je v [plánu](MONTHLY_MEMBERSHIP_PLAN.md).
Rozšíření o roční tarify, disciplíny a fakturační údaje popisuje
[navazující plán](CLUB_MEMBERSHIP_CATALOG_2026-11-05.md). Stav konkrétního vydání
určuje CURRENT_STATE; dostupný kód sám o sobě neznamená aktivovaný prodej.

## Příprava klubového katalogu

V pozici **Hospodář a platby → Členství v klubu** lze připravit koncept
„Závodní tým“ za 2 200 Kč měsíčně v kategorii „Členství v klubu“. Tlačítko
založí stálou skupinu a soupisky MTB, Silnice, Dráha. Opakování nevytvoří
kopie; neodpovídající existující kódy odmítne. Žádného sportovce tím nezařadí.

V **Fakturačních údajích klubu** doplňte skutečnou adresu, IČ, případně účet
pro doklady a podklad schválení. Název je předvyplněný, ostatní údaje se
neodhadují. Potvrzení vyžaduje skutečný režim neplátce DPH, který současný
modul podporuje. Uložení vytvoří novou verzi vystavovatele, výchozí členění
CLEN / KLUB a chybějící řady CLEN pro letošní a příští rok. Existující řady
se neresetují. Platební IBAN nastavujte zvlášť v Bankovním účtu e-shopu.

Při schválení tarifu zvolte skupinu, nabízené disciplíny, měsíční nebo roční
interval a odpovídající úplné podmínky. Každý tarif má samostatnou variantu
a schválenou cenu. Roční období tvoří dvanáct kalendářních měsíců od měsíce
sjednání; první období je za plnou cenu a ukončuje se na jeho konci.
Roční varianta vyžaduje vlastní cenu, nevzniká automatickým násobením měsíční.

Člen volí jednu či více nabízených disciplín bez příplatku. Vzniká jeden
předpis a samostatné zařazení do každé vybrané soupisky. Skupina své členy
odvozuje ze soupisek. Ukončení uzavře pouze zařazení z konkrétního členství.
Změna výběru disciplín po sjednání není samoobslužnou součástí této verze.

Po schválení tarifu lze produkt zveřejnit standardní publikací katalogu.
Prodej vyžaduje samostatnou aktivaci automatizace uvedenou níže. Pro roční
varianty se používají stejné provozní přepínače a worker; interní názvy
`monthly_membership_*` a zákaznická URL zůstávají kvůli kompatibilitě.

## Co zákazník udělá

1. V e-shopu otevře měsíční produkt a zvolí „Sjednat měsíční členství“. Přihlásí se, případně použije stávající registraci a schválení vazby na sportovce.
2. Vybere vlastní schválený profil nebo dítě, vyplní odběratele dokladu a potvrdí cenu, podmínky a závazek opakované měsíční platby. První kalendářní měsíc platí celý i při přihlášení během měsíce.
3. Systém založí členství, přístup do příslušné soupisky a první předpis. Další předpisy doplňuje plánovač. Potvrzovací cesta má vlastní záznam členství; nevytváří běžnou jednorázovou objednávku v košíku.
4. Na stránce `booking/mesicni_clenstvi.php` dostane bankovní účet a stálý VS plátce. V bance si sám nastaví trvalý příkaz. Systém peníze z banky nestrhává. Pro více dětí posílá součet cen pod týmž VS.
5. Ve stejném přehledu sleduje jednotlivé měsíce, zbývající dluh a nepřidělený přeplatek, otevře aktuální platební QR a stáhne vystavené PDF. Oznámení o dokladu obsahuje odkaz do přihlášeného přehledu.
6. Potvrzením ukončení vypoví členství k poslednímu dni aktuálního měsíce. Již vzniklé dluhy zůstávají. V bance musí trvalý příkaz zastavit sám. Ukončí se pouze přístup založený tímto členstvím, ručně přidělené přístupy se zachovají.

Jednorázové produkty, kroužky a jejich existující objednávky používají dosavadní košík a platební cestu. Měsíční varianta se do tohoto košíku nedostane ani zastaralým přímým požadavkem.

## Nastavení správcem

Správce financí otevře `monthly_memberships_admin.php` (odkaz také ve správě dosavadních členských plánů). Připraví samostatný programový produkt a neskladovou variantu s pevnou kladnou cenou v CZK. Nepřipojí ji současně k jednorázové programové nabídce.

Schválí cílovou aktivní soupisku, podmínky, měsíční cenu převzatou z varianty a den splatnosti 1–28. První předpis je splatný nejdříve sedm dní od přihlášení; další podle nastaveného dne. Vybere schváleného vystavovatele a pravidlo pro `member_charge`, druh `invoice` nebo `contribution_confirmation` a připraví číselnou řadu pro každý příslušný rok.

Tarif zachovává schválenou cenu a podmínky. Novou cenu/podmínky je nutné schválit na nové variantě; existující dohody se tím nepřecení. Pozastavení tarifu zabrání novým přihláškám, existující závazky trvají. Změna ceny nebo typu původní varianty zablokuje nové sjednání, včetně již otevřeného formuláře.

Doklady vydává CLI worker na základě explicitního pověření schvalujícího finančního pracovníka; nevytváří přihlášenou relaci. Při odebrání jeho oprávnění se vydání zastaví s evidovanou chybou. Chybějící novoroční řada rovněž ponechá předpis a chybu k opravě; opakování použije stejnou identitu dokladu a nezaloží nový předpis. Předpis a přijetí peněz jsou v účetní evidenci rozlišeny, rozdělení platby není další příjem.

## Platby a upozornění

Napojení používá stávající import a automatický potvrzovací běh Fio. Musí tedy být povolen i tento dosavadní automatický proces. Kontroluje zdrojový bankovní účet, CZK, kladnou částku, datum od začátku dohody a přidělený VS. Přijatý pohyb má jediný záznam příjmu; transakčně se rozdělí na nejstarší měsíční předpisy plátce a přebytek čeká na další období. Opakovaný import ani souběžné spuštění nesmějí peníze nebo předpis zdvojit.

Pokud má plátce nezaplacené starší členské předpisy mimo tento nový tok, automatické rozdělení se odmítne a Fio pohyb zůstane ke kontrole. Chybný VS, měna, účet, neplatné oprávnění nebo platba před vznikem dohody také neznamenají automatickou úhradu. Staré dohody se hromadně nepřevádějí. Změna přijímacího účtu vyžaduje vyřešit existující bankovní dohody; služba takovou změnu nepřepíše potichu.

Upomínky používají stupně 3, 7 a 14 dní po splatnosti, každý nejvýše jednou. Při delším výpadku se zařadí aktuální stupeň, neposílají se všechny zmeškané zprávy najednou. Částka a oprávnění se znovu ověřují těsně před odesláním; po úplném zaplacení se upomínka zruší. Starší volitelná připomínka před splatností zůstává oddělená a nově rovněž respektuje částečnou úhradu.

Zpráva s nejistým doručením po pádu nebo odmítnutí transportem se automaticky neposílá znovu. Správce nejprve ověří poštovní log, potom v administraci výslovně potvrdí opakování a důvod. Nejde o marketingovou rozesílku.

## Nasazení a zapnutí

### Hostingový denní plán

[Oprava opožděného plánu](MEMBERSHIP_SCHEDULER_2026-11-06.md) přesouvá časování
na existující `CRON.1minuta` hostingu. Stav nasazení a aktivace určuje protokol.
Čas zůstává **05:23 UTC denně**, tedy 06:23 CET / 07:23 CEST. Při zdravém
minutovém cronu se worker spustí při prvním ticku po tomto čase. Po výpadku
hostingu dožene aktuální práci, nepřehrává jednotlivé zmeškané denní běhy.

`membership-scheduler-production.yml` z `main`, chráněné prostředí production:

- `action=status`, `potvrzeni=OVERIT-CLENSTVI-CRON`: pouze čtení, přesný hash
  spouštěče, čerstvost skutečného ticku (do 180 s), poslední úspěch a denní
  splatnost. Status nevytváří heartbeat ani účetní zápis.
- `action=install`, `potvrzeni=ZAPNOUT-CLENSTVI-CRON`: atomicky instaluje pouze
  vlastní spouštěč, odmítá cizí soubor i symlinky a nezakládá cron adresář.
  Vyžaduje nasazený přesný worker a obě související knihovny.
- Po instalaci vyčkat na přirozený tick a ověřit status. Již úspěšný dnešní
  apply v `operational_job_runs` potlačí opakování. Teprve po důkazu ticku
  přepnout repo proměnnou `KIS_MONTHLY_MEMBERSHIP_SCHEDULE_ENABLED=false`.

Hosting čte přímo soukromé `MONTHLY_MEMBERSHIP_AUTOMATION_ENABLED` a
`MONTHLY_MEMBERSHIP_MAIL_ENABLED`. GitHub proměnné APPLY/MAIL řídí jen původní
workflow. Pro zastavení hostingové pošty použít **mail-disable**, pro celou
automatizaci **disable** v konfiguračním workflow níže. Pouhé vypnutí proměnné
GitHubu hosting nezastaví. Stávající povolení pošty/automatizace instalace nemění.

Sdílený MariaDB advisory lock chrání hosting i ruční CLI před souběhem. Při
chybě nebo pádu je další pokus odložen nejméně o 10 minut. Nejisté doručení
se automaticky neopakuje. Stav a zámek jsou mimo webroot v `data/.kis-deploy`.
Denní úspěch a minutový heartbeat jsou odlišné: čerstvý tick se starým denním
úspěchem po 05:23 vyžaduje kontrolu soukromého logu a chyb v administraci.

Původní `monthly-memberships-production.yml` zůstává pro ruční dry-run/apply a
jako záložní plán, jehož doba startu není přesná. Při návratu nejdříve odstranit
pouze ověřený vlastní spouštěč z CRON.1minuta, zachovat soukromý stav a ověřit
absenci spouštěče; potom povolit repo schedule. Cizí crony a data neodstraňovat.

### Jednorázová serverová kontrola 7. 11.

[Přejímka](verification/membership-morning-server-release-20261106/RESULTS.md) a
[plán](MEMBERSHIP_SCHEDULER_MORNING_AUDIT_2026-11-07.md) nahrazují místní úlohu
(PAUSED). Hosting zachytí první výsledek od 05:30 UTC bez apply/send, SQL READ
ONLY. JSON a odvozený protokol/handoff jsou v data/.kis-deploy; vlastní
pozorovací cron se po zápisu odstraní, hlavní účtování dál běží.

membership-morning-audit-production.yml z main / production:

- mode=status: čtení zdroje, heartbeat, hlavního plánu a případného reportu.
  Nevytváří heartbeat ani účetní zápis.
- mode=install, potvrzení ZAPNOUT-RANNI-AUDIT: jen vlastní pozorovací soubor;
  po hotovém reportu se znovu neaktivuje. Dnešní instalace je hotová.
- mode=preflight: jednorázové doložení čekajícího ticku a přenosu do #348.
  Celý postup už prošel i automatickým merge.
- mode=publish: vyzvednutí skutečného neměnného reportu od termínu. Připravený
  PR #347, repo KIS_MEMBERSHIP_AUDIT_REPORT_PR=347 a preflight=348. Workflow
  povoluje jen PR CI vlastního ověřeného dokumentačního SHA, vyčká na skutečné
  kontroly a slučuje bez admin bypassu. Po publikaci se vypne.

Přenosy 7. 11. v 05:35/06:35/12:35 UTC neopakují audit ani účtování. Při závadě
číst report a Action výsledek, znovu nepouštět worker. Případné FAIL/LATE má
být zdokumentované, P05/P07 a P06 zůstávají odložené.

### Soukromé příznaky a ruční workflow

Soukromý příznak automatizace spravuje `configure-memberships-production.yml`
z `main` přes chráněné prostředí `production`. Režimy `probe` / `enable` /
`disable` vyžadují potvrzení `OVERIT-CLENSTVI` / `ZAPNOUT-CLENSTVI` /
`VYPNOUT-CLENSTVI`. Nástroj nejprve čtecím způsobem ověří prostředí, schéma,
počty a poslední heartbeat. Změna má ověřenou soukromou zálohu configu,
atomický zápis a kontrolu uložených příznaků v novém procesu. Tyto tři režimy nemění poštu.
Pošta má vlastní režimy `mail-enable` / `mail-disable` a potvrzení
`ZAPNOUT-POSTU-CLENSTVI` / `VYPNOUT-POSTU-CLENSTVI`. Ty mění pouze soukromý
mailový příznak, nikoli automatizaci nebo ostatní integrace. Zapnutí vyžaduje
aktivní automatizaci a dostupnou PHP funkci `mail`; workflow proměnná
`KIS_MONTHLY_MEMBERSHIP_MAIL_ENABLED=true` následně přidá `--send` do denního běhu.
Náhled uvádí souhrn fronty podle stavů bez adres či obsahu zpráv. Dostupná mail
funkce ani prázdný běh samy o sobě nedokazují doručení do schránky.
Výslovné pověření a postup viz [aktivace pošty](MEMBERSHIP_MAIL_ACTIVATION_2026-11-05.md).
První zapnutí s prázdnou evidencí je schválené v
[plánu aktivace](MEMBERSHIP_AUTOMATION_ACTIVATION_2026-11-05.md).
`KIS_MONTHLY_MEMBERSHIP_SCHEDULE_ENABLED` musí být repozitářová proměnná,
protože podmínka úlohy se vyhodnocuje před načtením prostředí `production`.

1. Po samostatném schválení vydání použít standardní chráněný deploy a zálohu; nová migrace je `20261105150000_monthly_memberships.php`. Zálohovací kontrakt zahrnuje všech osm nových tabulek. Neměnit historické migrace.
2. Ověřit soukromé ukládání PDF, účetní konfiguraci, produkt, soupisku, oprávnění schvalujícího pracovníka a doručování na určeném testovacím účtu. Připravit následující účetní rok před jeho začátkem.
3. Na serveru lze nejprve spustit `APP_HOST=kis.kovopraha.cz php bin/monthly-memberships.php --dry-run`. Náhled nezapisuje; vykazuje počet členství, nikoli simulaci budoucích účetních dokladů.
4. Výchozí soukromá konfigurace má `MONTHLY_MEMBERSHIP_AUTOMATION_ENABLED=false` a `MONTHLY_MEMBERSHIP_MAIL_ENABLED=false`. První příznak po schválení povolí sjednávání, nové měsíční účtování a automatické měsíční párování Fio. Druhý samostatně povoluje skutečné odesílání.
5. Záložní workflow `monthly-memberships-production.yml` má cron 05:23 UTC. Při aktivním hostingovém plánu ponechat `KIS_MONTHLY_MEMBERSHIP_SCHEDULE_ENABLED=false`. Pro případný záložní schedule řídí zápis `KIS_MONTHLY_MEMBERSHIP_APPLY_ENABLED=true` a poštu `KIS_MONTHLY_MEMBERSHIP_MAIL_ENABLED=true`. Workflow nepřepisuje soukromé příznaky aplikace. Ruční spuštění začíná v režimu `dry-run`; apply a pošta vyžadují výslovné parametry.
6. Samostatný místní nácvik může použít `--apply --send --local-outbox`, který uloží zprávy přes stávající testovací outbox a neodesílá poštu. I nácvik zápisu vyžaduje povolenou automatizaci a izolovanou testovací databázi. Produkční `--apply --send` vyžaduje oba soukromé příznaky.
7. Kontrolovat heartbeat `monthly_memberships`, chyby období/dokladů v administraci, čekající Fio pohyby a neúspěšné zprávy. Doklady a doručení jsou omezené na dávku 100 na běh; pro větší backlog přidat další běhy. Předpisy doplní běh za všechna chybějící dosavadní období.

Pro pozastavení hostingové pošty vypnout soukromý mailový příznak. U záložního GitHub plánu vypnout také proměnnou `KIS_MONTHLY_MEMBERSHIP_MAIL_ENABLED`, aby běh pokračoval bez `--send`. Volání `--send` při vypnutém soukromém příznaku odmítne celý běh před zápisem. Pro úplné zastavení nových sjednání/automatizace vypnout soukromý příznak automatizace a zápis/plán workflow. Existující příjmy, předpisy a PDF se nemažou; stažení vlastních dokladů a ukončení členství zůstávají dostupné.

## Ověření a hranice

Integrační testy jsou v `tests/Integration/MonthlyMembershipTest.php`. MariaDB nácvik `tests/Support/MonthlyMembershipMariaDbSmoke.php` vytváří a nakonec ruší pouze novou izolovanou databázi `evidence_monthly_smoke_test_*`; testuje skutečnou novou migraci, částečnou rodinnou platbu, souběžný příjem a souběžné generování, opakování, PDF a ukončení. CI jej spouští v existující matici MariaDB 10.3/11.4. Místně byl ověřen na MariaDB 10.4.32. Samostatný nácvik všech migrací, zálohy a obnovy prošel pro 307 tabulek.

Mobilní i desktopový vzhled byl ověřen na synteticky vyrenderovaných skutečných PHP stránkách. Zápisové kroky ověřují služby v integračních testech; nejde o důkaz přihlášeného produkčního průchodu v prohlížeči. Skutečné bankovní převody, doručení této nové rozesílky a produkční vystavení dokladů zatím ověřeny nejsou. První prázdný apply --send běh a uložený heartbeat jsou ověřené; automatický GitHub běh 42222661557 byl úspěšný, ale opožděný o 7 h 42 min. Nápravu a důkazy hostingového plánu vede [protokol](MEMBERSHIP_SCHEDULER_2026-11-06.md). Skutečné P05/P07 a export P06 vlastník odložil.
