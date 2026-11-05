# Měsíční členství: obsluha a aktivace

**Vydání 5. 11. 2026:** implementace a migrace jsou [nasazené a technicky ověřené](verification/monthly-coach-release-20261105/RESULTS.md), včetně produkčního dry-run. Ostrý prodej, nové účtování a pošta zatím neaktivované. Následující postup aktivace zůstává platný.

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

1. Po samostatném schválení vydání použít standardní chráněný deploy a zálohu; nová migrace je `20261105150000_monthly_memberships.php`. Zálohovací kontrakt zahrnuje všech osm nových tabulek. Neměnit historické migrace.
2. Ověřit soukromé ukládání PDF, účetní konfiguraci, produkt, soupisku, oprávnění schvalujícího pracovníka a doručování na určeném testovacím účtu. Připravit následující účetní rok před jeho začátkem.
3. Na serveru lze nejprve spustit `APP_HOST=kis.kovopraha.cz php bin/monthly-memberships.php --dry-run`. Náhled nezapisuje; vykazuje počet členství, nikoli simulaci budoucích účetních dokladů.
4. Výchozí soukromá konfigurace má `MONTHLY_MEMBERSHIP_AUTOMATION_ENABLED=false` a `MONTHLY_MEMBERSHIP_MAIL_ENABLED=false`. První příznak po schválení povolí sjednávání, nové měsíční účtování a automatické měsíční párování Fio. Druhý samostatně povoluje skutečné odesílání.
5. Workflow `monthly-memberships-production.yml` běží pouze z `main`, denně v 05:23 UTC. Pro plánované spuštění nastavit `KIS_MONTHLY_MEMBERSHIP_SCHEDULE_ENABLED=true`; zápis navíc vyžaduje `KIS_MONTHLY_MEMBERSHIP_APPLY_ENABLED=true`. Odesílání workflow přidá pouze s `KIS_MONTHLY_MEMBERSHIP_MAIL_ENABLED=true`. Workflow nepřepisuje soukromé příznaky aplikace. Ruční spuštění začíná v režimu `dry-run`.
6. Samostatný místní nácvik může použít `--apply --send --local-outbox`, který uloží zprávy přes stávající testovací outbox a neodesílá poštu. I nácvik zápisu vyžaduje povolenou automatizaci a izolovanou testovací databázi. Produkční `--apply --send` vyžaduje oba soukromé příznaky.
7. Kontrolovat heartbeat `monthly_memberships`, chyby období/dokladů v administraci, čekající Fio pohyby a neúspěšné zprávy. Doklady a doručení jsou omezené na dávku 100 na běh; pro větší backlog přidat další běhy. Předpisy doplní běh za všechna chybějící dosavadní období.

Pro pozastavení pošty vypnout workflow proměnnou `KIS_MONTHLY_MEMBERSHIP_MAIL_ENABLED`, aby běh pokračoval bez `--send`, a soukromý mailový příznak. Volání `--send` při vypnutém soukromém příznaku odmítne celý běh před zápisem. Pro úplné zastavení nových sjednání/automatizace vypnout soukromý příznak automatizace a zápis/plán workflow. Existující příjmy, předpisy a PDF se nemažou; stažení vlastních dokladů a ukončení členství zůstávají dostupné.

## Ověření a hranice

Integrační testy jsou v `tests/Integration/MonthlyMembershipTest.php`. MariaDB nácvik `tests/Support/MonthlyMembershipMariaDbSmoke.php` vytváří a nakonec ruší pouze novou izolovanou databázi `evidence_monthly_smoke_test_*`; testuje skutečnou novou migraci, částečnou rodinnou platbu, souběžný příjem a souběžné generování, opakování, PDF a ukončení. CI jej spouští v existující matici MariaDB 10.3/11.4. Místně byl ověřen na MariaDB 10.4.32. Samostatný nácvik všech migrací, zálohy a obnovy prošel pro 307 tabulek.

Mobilní i desktopový vzhled byl ověřen na synteticky vyrenderovaných skutečných PHP stránkách. Zápisové kroky ověřují služby v integračních testech; nejde o důkaz přihlášeného produkčního průchodu v prohlížeči. Skutečné bankovní převody, doručení této nové rozesílky, produkční vystavení dokladů a běh nového plánovače zatím ověřeny nejsou.
