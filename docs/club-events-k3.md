# Klubové akce K3

Administrátor používá stránku `eshop_events_admin.php`. První K3 přírůstek
modeluje pouze pracovní akce a nepřijímá veřejné přihlášky.

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

## Záměrně chybí

- veřejná nabídka a přihlašovací formulář,
- rezervace kapacity a kontrola duplicitního účastníka,
- souhlasy zákonného zástupce a čekací listina,
- objednávka, platba, soupiska nebo zápis do KIS.

## Nasazení

Model vyžaduje migraci `20260803110000_club_events`. Read-only kontrola je
`php bin/migrate.php --check`; produkční migrace musí proběhnout před aktivací
nové verze PHP.
