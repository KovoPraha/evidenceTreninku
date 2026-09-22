# Řízená aktivace katalogu

Administrátor používá stránku `eshop_catalog_publication_admin.php`. Aktivace je
samostatné rozhodnutí nad již zkontrolovaným kanonickým katalogem a neprobíhá
automaticky při importu ani převodu stagingu.

## Bezpečnostní brána

Aktivovat lze zboží, prodejný program a placenou klubovou akci nebo tábor.
Program musí mít navázanou nabídku a platné podmínky. `club_event` i `camp` musí
být propojeny s pracovní akcí stejného typu. Produkt musí mít název a
alespoň jednu variantu, která není explicitně skrytá. Každá taková varianta musí
mít SKU a buď platnou pevnou cenu s třípísmennou měnou, nebo konzistentní nulovou
cenu. Chybějící příznak viditelnosti ve starším CSV se připustí pouze po ručním
potvrzení administrátora; explicitně skrytá varianta zůstane neaktivní.

Administrátor vždy zadá:

- veřejný název,
- veřejný popis jako prostý text bez HTML,
- důvod aktivace,
- potvrzení konkrétního produktu.

Aktivní text nelze tiše přepsat. Produkt je nutné nejprve deaktivovat a následně
znovu aktivovat, čímž vznikne další auditní událost.

## Co aktivace nyní neznamená

- Samotná aktivace ještě nevytvoří košík, objednávku, rezervaci, platbu ani
  skladový pohyb.
- Zboží a prodejné programy se zobrazují v e-shopu. Propojené otevřené položky
  `club_event` a `camp` se zobrazují v části Akce, nikoli podruhé jako samostatné
  zboží v e-shopu.
- Typy `bookable_service`, `rental`, `bookable_rental` a `custom_quote` zůstávají
  blokované do dokončení jejich doménových funkcí.

## Nasazení

Funkce vyžaduje migraci `20260803090000_shop_product_publication`. Stav lze bez
zápisu ověřit příkazem `php bin/migrate.php --check`. Produkční deploy musí
migraci provést před aktivací nové verze PHP.
