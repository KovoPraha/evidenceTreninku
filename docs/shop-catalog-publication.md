# Řízená aktivace katalogu

Administrátor používá stránku `eshop_catalog_publication_admin.php`. Aktivace je
samostatné rozhodnutí nad již zkontrolovaným kanonickým katalogem a neprobíhá
automaticky při importu ani převodu stagingu.

## Bezpečnostní brána

V první etapě lze aktivovat pouze nabídku typu `goods`. Produkt musí mít název a
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

- Produkt se nikde veřejně nezobrazí, protože storefront zatím neexistuje.
- Nevznikne košík, objednávka, rezervace, platba ani skladový pohyb.
- Typy `club_event`, `camp`, `bookable_service`, `rental`, `bookable_rental` a
  `custom_quote` zůstanou blokované do dokončení jejich doménových funkcí.

## Nasazení

Funkce vyžaduje migraci `20260803090000_shop_product_publication`. Stav lze bez
zápisu ověřit příkazem `php bin/migrate.php --check`. Produkční deploy musí
migraci provést před aktivací nové verze PHP.
