# Klubové akce K3

Administrátor používá stránku `eshop_events_admin.php`. První provozní K3 průchod
umí otevřít pouze bezplatný kroužek. Přihlášený rodič jej obslouží na
`booking/krouzky.php` a může vybrat jen osobu schválenou v K2.

## Model

Akce má stabilní kód, typ `club_event` nebo `camp`, název a prostý text popisu,
cílovou skupinu, volitelné věkové rozmezí, celkovou kapacitu, registrační okno,
měnu a cenovou politiku `free` nebo `product_variants`.

Každý termín má začátek, konec, místo a volitelnou vlastní kapacitu. Překrývající
se termíny stejné akce jsou odmítnuty.

Kanonický produkt lze propojit pouze tehdy, když:

- jeho typ přesně odpovídá typu akce,
- akce už má alespoň jeden termín,
- produkt má použitelnou viditelnou variantu,
- měna placené varianty odpovídá měně akce,
- bezplatná akce používá pouze nulové varianty.

Jeden produkt smí být napojen nejvýše na jednu akci. Vazbu lze v pracovním stavu
auditovaně odstranit a opravit.

## Bezplatná přihláška

Otevření kroužku je výslovné administrační rozhodnutí s důvodem a potvrzovacím
checkboxem. Otevřít lze pouze `club_event` s cenovou politikou `free`, alespoň
jedním naplánovaným termínem a propojeným nulovým produktem.

Při přihlášení server v jedné transakci:

- zamkne řádek akce v MariaDB,
- ověří otevřený stav a registrační okno,
- ověří aktivní schválenou vazbu `self` nebo `guardian` z K2 a ověřený účet,
- spočítá věk k prvnímu termínu,
- zkontroluje efektivní kapacitu jako minimum kapacity akce a termínů,
- vytvoří nebo znovu aktivuje jediný řádek účastníka pro danou akci.

Databázový unikátní klíč `(event_id, sportovec_id)` je druhá ochrana proti
duplicitě. Opakované odeslání již potvrzené přihlášky je idempotentní. Storno
uvolní místo a zachová přihlášku i samostatnou auditní historii.

## Záměrně chybí v tomto průchodu

- souhlasy zákonného zástupce a čekací listina,
- placený kroužek a košík,
- objednávka, platba, soupiska nebo zápis do KIS.

## Nasazení

Model vyžaduje migrace `20260803110000_club_events` a
`20260803130000_club_event_registrations`. Read-only kontrola je
`php bin/migrate.php --check`; produkční migrace musí proběhnout před aktivací
nové verze PHP.
