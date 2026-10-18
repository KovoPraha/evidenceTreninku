# Produkční UAT účty a data

Aktualizováno: 18. 10. 2026

## Účel a hranice

Na `https://kis.kovopraha.cz` lze chráněným workflow připravit dočasnou,
jednoznačně označenou sadu pro navazující uživatelské testy. Operace je
idempotentní, přijímá pouze předem určené identity a odmítne kolizi s jinou
osobou. Heslo se předává jako dočasný GitHub Environment secret a nesmí být v
Gitu ani v logu workflow.

Účty:

- rodič `Tester Karel` – `tester.karel@velocota.com`,
- rodič `Tester Petra` – `tester.petra@velocota.com`,
- dítě `Ema Tester` – přihlašovací jméno `tester.ema`,
- dítě `Adam Tester` – přihlašovací jméno `tester.adam`,
- správce je samostatný vyhrazený účet `Tester Správce`
  (`tester.spravce@velocota.com`).

Karel má schválenou vazbu pouze na Emu, Petra pouze na Adama. Křížové vazby
z předchozího testovacího nastavení se auditovaně ruší jen tehdy, pokud je
prokazatelně vytvořil UAT provisioning; ručně změněná vazba přípravu zastaví.
Dětské účty mají pouze omezený
sportovní přehled; nejsou rodičovskými ani administrátorskými účty.

## Připravená scénářová data

Veřejné záznamy mají povinný prefix `TEST -`:

- zboží `TEST - Klubová láhev`,
- prodejný program `TEST - Cyklistický kroužek` včetně období, soupisky,
  věkového rozsahu a schválených UAT podmínek,
- bezplatná akce `TEST - Rodinný nábor`,
- placená akce `TEST - Příměstský den` s interní aktivní variantou 50 Kč;
zobrazuje se v části Akce a vede přes košík, objednávku a zvolenou platební
  metodu, ale neduplikuje se jako samostatný produkt v běžném e-shopu,
- pro placenou akci se Karlovi připojí nový nepoužitý TEST účastník
  `PlatbaYYMMDD-N Tester` v TEST soupisce; skript jej při opakování bezpečně
  znovu použije jen dokud k této akci nemá žádnou historickou přihlášku,
- placený termín `TEST - Veřejný velodrom`,
- `TEST - Individuální lekce`,
- veřejný plán `TEST - Trénink nového dítěte` napojený na TEST soupisku.

Pro TEST kroužek se při prvním vytvoření nastaví pravidlo včasné omluvy na
24 hodin; provisioning následně synchronizuje chybějící pravidlo se stávajícím
TEST plánem. Již ručně upravené pravidlo bez dalšího nepřepisuje. Readiness
kontroluje oddělení rodin i aktivní synchronizované pravidlo omluv.
Zároveň kontroluje, že v otevřené placené TEST akci zůstává alespoň jeden
nepoužitý účastník pro pozitivní nákup; po jeho využití je potřeba znovu
spustit chráněnou přípravu dat.

Ostré nabídky kroužků nejsou součástí TEST provisioningu. Při prohlížečovém
UAT bylo 76 nabídek bez potvrzeného typu období; protože klub zatím nemá
autoritativní mapu, zůstávají převody kreditů pro ně bezpečně nezařazené.
Koordinátor je jednotlivě označí na stránce `club_makeup_policies_admin.php`
(filtr „Jen k prověření“) jako celoroční, první/druhé pololetí s konkrétní
návazností, nebo jednorázové/jiné. Z názvu produktu se nic neodvozuje.

Placená akce ověřuje celý skutečný řetězec: ruční položka typu Tábor se nejprve
propojí s pracovní akcí, potom se auditovaně aktivuje její katalogová varianta a
nakonec se otevře registrace. Běžná ruční správa katalogu podporuje stejným
postupem typy Klubová akce i Tábor / soustředění.

Termíny se odvozují od dne spuštění, ale vždy se vejdou do proměnné
`KIS_UAT_WINDOW_END`. Zdrojový testovací trénink se plánuje na druhý následující
kalendářní den; ostatní termíny mají preferované rozestupy 5–8 dní, při kratším
okně se bezpečně posunou na poslední vhodný den. Pokud okno nekončí nejdříve
v 18:00 druhý následující den, příprava skončí ještě před prvním zápisem.
Okno smí být nejvýše 31 dní. Opakované spuštění nepřesune již zodpovězený či
evidovaný trénink do nového okna; je třeba založit nový označený TEST plán.
Skutečné finanční potvrzení
není součástí provisioningu: QR, Stripe/SumUp a bankovní spárování se provádějí
až v uživatelském scénáři s výslovným potvrzením před pohybem peněz.

## Spuštění a ověření

Workflow `production-drills.yml` nabízí operaci
`pripravit-uat-ucty-a-data`. Vyžaduje přesné potvrzení `PROVEST`, environment
secret `KIS_UAT_TEST_PASSWORD` a proměnnou `KIS_UAT_WINDOW_END`. Po přípravě se
spouští read-only operace `overit-uat-pripravenost`. Ta nyní počítá pouze
termíny, které skutečně končí v rámci UAT okna; testovací trénink navíc musí
mít vazbu na soupisku. Budoucí termín až za koncem okna proto bránou neprojde.

Brána čte také proměnné `KIS_UAT_OWNER`, `KIS_UAT_INBOX_READY` a
`KIS_UAT_BANK_RECONCILIATION_READY`. Nejde o náhradu reálné kontroly inboxu a
banky; hodnoty pouze evidují, že konkrétní testující tuto odpovědnost převzal.

Úplný pozitivní scénář náhrad nelze uzavřít jen vytvořením dat: vyžaduje
potvrzenou placenou účast, včasnou omluvu, proběhlý trénink a skutečně
uzavřenou docházku. Teprve potom lze ověřit připsání a rezervaci kreditu.
Předchozí stornovaná objednávka placené akce záměrně neotevírá opakovaný nákup
téže akce pro stejnou osobu; opakované spuštění připraví dalšího nepoužitého
TEST účastníka (nejvýše 20 v jednom dni). Provisioning nepřepisuje historii
přihlášek ani nehýbe akcí se živými přihláškami. Pokud stará akce již leží
mimo aktuální UAT okno, vytvoří oddělenou TEST akci i produkt označené datem
konce nového okna; úklid rozpozná oba typy SKU.

## Úklid

Operace `deaktivovat-testovaci-ucty`:

- zneaktivní přesně oba vyjmenované rodiče, oba dětské přístupy a
  vyhrazeného TEST správce včetně jeho propojeného veřejného profilu;
  změna verze relací odhlásí existující přístupy a resetovací tokeny se spotřebují,
- ukončí aktivní členství TEST dětí a dočasných TEST účastníků placené akce
  výhradně v soupisce `TEST-UAT-DETI` (bez mazání historických zápisů),
- uzavře a skryje akce `TEST -`,
- zruší veřejné lekce a tréninky `TEST -`,
- deaktivuje publikace, produkty a varianty `TEST -`, včetně neveřejné varianty
  použité pouze pro objednávkový tok placené akce.

Záznamy se nemažou, aby zůstal audit a historie objednávek. Operace se nesmí
rozšiřovat na obecný prefix e-mailů nebo běžné veřejné nabídky.
