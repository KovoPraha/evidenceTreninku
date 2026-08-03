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

Před otevřením administrátor povinně nastaví verzi a prostý text souhlasu,
prostý text storno podmínek a přesný termín bezplatného storna. Termín musí být
v budoucnosti a před prvním termínem kroužku. Rodič musí potvrdit právě aktuální
verzi. Přihláška ukládá snapshot verze, obou textů, času souhlasu a storno termínu,
takže pozdější změna jiné akce nepřepíše historické rozhodnutí. Po snapshotovaném
termínu je uživatelské storno fail-closed a vyžaduje kontakt s administrátorem.

Při plné kapacitě vznikne místo chyby stav `waitlisted`. Pořadí je FIFO podle
času zařazení a ID. Dvojité odeslání zůstává idempotentní díky stejnému unikátnímu
klíči účastníka. Storno čekající osoby pouze odstraní její čekání. Storno
potvrzeného účastníka ve stejné transakci uvolní kapacitu a povýší nejstarší
stále oprávněnou osobu. Před povýšením se znovu kontroluje K2 vazba a přítomnost
snapshotu souhlasu; neplatný kandidát se auditovaně vyřadí a pokračuje se dalším.

Povýšení současně vloží do tabulky `club_event_notifications` neměnný e-mailový
snapshot. Zápis je součástí stejné transakce jako storno a povýšení; selhání
fronty proto vrátí zpět celý zásah. Samotné odeslání probíhá až mimo tuto
transakci. Jedna přihláška smí mít nejvýše jedno oznámení typu
`waitlist_promoted`.

Administrátor může aktivní přihlášku auditovaně zrušit i po termínu. Formulář
vyžaduje důvod a zvláštní potvrzovací checkbox. Pozdní zásah má auditní akci
`admin_cancel_late`; uvolněná kapacita používá stejný FIFO mechanismus a frontu
oznámení jako uživatelské storno.

## Odesílání oznámení

Krátký CLI worker převezme zprávu unikátním tokenem, transakci ukončí a teprve
potom zavolá PHP `mail()`. Neúspěšnou zprávu vrátí do fronty se zpožděním,
nejvýše pětkrát; poté zůstane ve stavu `failed` k ruční kontrole. Zaseknuté
`processing` oznámení lze po 15 minutách převzít znovu. Jde o doručení typu
at-least-once: pád procesu těsně po předání poštovnímu serveru může výjimečně
vést k duplicitnímu e-mailu.

Na hostingu spusťte z CRONu každou minutu například:

```sh
APP_HOST=data.kovopraha.cz php /absolutni/cesta/bin/club-event-notifications.php --limit=20
```

Návratový JSON obsahuje počty `processed`, `sent` a `failed`. Návrat `mail()`
potvrzuje pouze převzetí lokálním poštovním systémem, nikoliv doručení do schránky.

Administrační stránka `eshop_notifications_admin.php` zobrazuje počty i detail
stavů `pending`, `processing`, `failed` a `sent`. Výchozí filtr ukazuje pouze
neodeslané položky. Ruční opakování je dostupné jen administrátorovi, vyžaduje
CSRF token, důvod a výslovné potvrzení. Stav `processing` nelze přepsat během
držení workerem a stav `sent` nelze vrátit do fronty. Povolené opakování v jedné
transakci vynuluje pracovní počet pokusů a zapíše původní stav, původní počet
pokusů, administrátora a důvod do `club_event_notification_events`.

## Záměrně chybí v tomto průchodu

- obecné ruční změny pořadí čekací listiny,
- placený kroužek a košík,
- objednávka, platba, soupiska nebo zápis do KIS.

## Nasazení

Model vyžaduje migrace `20260803110000_club_events` a
`20260803130000_club_event_registrations` a `20260803150000_club_event_terms`.
Čekací listina navíc vyžaduje `20260803170000_club_event_waitlist`.
Oznámení a správní storno vyžadují `20260803190000_club_event_notifications`.
Administrační dohled a ruční retry navíc vyžadují
`20260803210000_club_event_notification_admin`.
Read-only kontrola je
`php bin/migrate.php --check`; produkční migrace musí proběhnout před aktivací
nové verze PHP.
