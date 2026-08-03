# M1.8 – placený velodrom přes shop objednávku

## Výsledek

Placený veřejný slot nepoužívá vlastní platební tabulku ani ruční potvrzení rezervace. Je položkou existujícího `shop_carts` a po checkoutu existujícího `shop_orders`; platba vzniká v `payments` jako `payable_type=shop_order` a používá stejné VS, SPD a QR jako ostatní objednávky.

Bezplatný slot zůstává v původní přímé cestě `publicVelodromeReserve()` a ihned se potvrzuje.

## Datový kontrakt

- `public_velodrome_cart_items` je rozšíření aktivního košíku. Nezabírá kapacitu.
- `public_velodrome_order_items` je neměnný snapshot názvu, data, času, výhradnosti, ceny, měny a beneficiary self.
- Každý objednávkový řádek odkazuje právě na jednu `verejne_rezervace`.
- Kapacita se drží vytvořením rezervace `ceka` uvnitř stejné transakce jako objednávka a platba.
- Potvrzení bankovní platby přepne rezervaci na `potvrzena` a `zaplaceno=1`.
- Storno objednávky přepne rezervaci na `zrusena` a uvolní `active_token`; zaplacená objednávka pokračuje standardně do `refund_required`.
- Potvrzení vratky pouze ověří, že propojená rezervace už není aktivní.
- Staré ruční potvrzení a přímé storno odmítají order-linked rezervace.

## Transakční pořadí zámků

Checkout:

1. aktivní košík,
2. katalogové varianty v jejich stávajícím pořadí,
3. self profil,
4. rozšiřující řádky košíku a sloty podle `lesson_id ASC`,
5. aktivní rezervace / kontrola kapacity,
6. objednávka, snapshot, rezervace a `payments`.

Lifecycle administrace zachovává společné pořadí `payment -> shop_order -> program lifecycle -> velodrome order rows -> lessons ASC -> reservations`. Programový lock order se tedy nemění a velodrom se připojuje až za něj.

MariaDB používá `SELECT ... FOR UPDATE` nad slotem. Dva checkouty posledního místa se serializují na řádku `individualni_lekce`; poražený checkout vrátí objednávku, platbu i rezervaci v jedné transakci.

## Migrace a nasazení

Forward migrace: `20260804200000_public_velodrome_shop`. Je opakovatelná a vytváří dvě rozšiřující tabulky s unikátními vazbami. Před aktivací kódu musí být aplikována běžným migračním runnerem.

## Ověření

- `PublicVelodromeShopTest`: SQLite lifecycle, snapshot, QR/SPD, idempotence, poslední místo, rollback a free-regression.
- `PublicVelodromeShopWiringTest`: MariaDB `FOR UPDATE`, deterministické pořadí a unikátní indexy.
- `PublicVelodromeTest`, `ShopCheckoutTest` a `ClubProgramPaymentLifecycleTest`: regresní sousední kontrakty.

Na localhost MariaDB proběhla skutečná migrace, vytvoření objednávky a QR,
potvrzení platby, aktivace rezervace, storno, refundace a opětovné uvolnění
kapacity. Dvouprocesový závod posledního místa zůstává pokryt SQLite rollbackem
a statickým MariaDB `FOR UPDATE` kontraktem; samostatný souběžný smoke je ještě
vhodné doplnit nad jednorázovou testovací databází.

## Zbývající rizika

- Nezaplacená objednávka drží místo až do ručního storna; automatická expirace pending objednávek je samostatný navazující krok.
- Kupón se nyní může započítat i do placeného slotu, pokud je ve smíšeném košíku; obchodní pravidlo pro vyloučení konkrétních služeb ještě není definováno.
