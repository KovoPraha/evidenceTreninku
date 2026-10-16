# Produkční UAT účty a data

Aktualizováno: 16. 10. 2026

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
- správce zůstává samostatný vyhrazený účet `KIS testovací superadministrátor`.

Oba rodiče mají schválenou vazbu na obě děti. Dětské účty mají pouze omezený
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
- placený termín `TEST - Veřejný velodrom`,
- `TEST - Individuální lekce`,
- veřejný plán `TEST - Trénink nového dítěte` napojený na TEST soupisku.

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

## Úklid

Operace `deaktivovat-testovaci-ucty`:

- zneaktivní oba rodiče a oba dětské přístupy,
- uzavře a skryje akce `TEST -`,
- zruší veřejné lekce a tréninky `TEST -`,
- deaktivuje publikace, produkty a varianty `TEST -`, včetně neveřejné varianty
  použité pouze pro objednávkový tok placené akce.

Záznamy se nemažou, aby zůstal audit a historie objednávek. Operace se nesmí
rozšiřovat na obecný prefix e-mailů nebo běžné veřejné nabídky.
