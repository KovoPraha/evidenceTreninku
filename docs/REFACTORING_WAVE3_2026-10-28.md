# Třetí vlna — prezentace programů v katalogu

28. 10. 2026. Výchozí main `3de0bdcd61a1466d618c1938dfcd67de62faec0c`,
aplikačně shodný s nasazeným `331bd974`; všechny čtyři CI běhy main PASS.
Navazuje na [druhou vlnu](REFACTORING_WAVE2_2026-10-28.md).

## Zjištění a rozsah

`booking/eshop.php` po sestavení prodejného katalogu opakuje pro každý produkt
`shopProductRequiresAthlete` a pro každou programovou variantu
`clubProgramOfferForVariant`. Tyto údaje již katalog načetl. Seskupení dat
a sdílený čtecí kontext umožní další dotazy odstranit bez nové dávkové SQL vrstvy.
Klasifikace musí používat původní zveřejněný název z řádku varianty, protože
prezentační názvy některých importovaných kurzů jsou záměrně přepsané.

1. Vyčlenit sestavení prezentačních údajů programu a zachytit původní chování.
2. Prokázat selhávající limit SQL/metadat na 50 programech.
3. Použít již načtené identity/kategorie a stejný operation-local kontext jako
   při ověřování prodejnosti; zachovat nové vyhodnocení v každé další operaci.
4. Porovnat celé výstupy s původním postupem: programy, více variant, staré
   importované kurzy, výjimky běžného zboží, chybějící/uzavřené nabídky,
   změna kapacity, souhlasu a zveřejnění; beze změny cen a oprávnění.
5. Změřit SQLite a MariaDB SQL/metadata a shodu výstupu, lokální HTTP a browser;
   projít PHPUnit, MariaDB 10.3/11.4, PHPStan, style a dokumentaci.
6. PR, sloučení a chráněné technické nasazení v navazujícím schváleném rozsahu;
   čtecí kontrola a stejné HTTP cesty před/po, aktualizace CURRENT_STATE.

Checkout, platební pravidla, členské ceny, importy a aktivační flags nejsou
součástí změny. Bez nové migrace či dlouhodobé cache. Plné lidské/účetní UAT
zůstává samostatně otevřené; nasazení s UAT=false a importovat_krouzky=false.

## Výsledek implementace

[PR #213](https://github.com/KovoPraha/evidenceTreninku/pull/213),
[výsledky a limity](verification/refactoring-wave3-20261028/RESULTS.md).
Pro 50 programů SQL 1 366 → 416, metadata 711 → 11; výstupy shodné
na MariaDB 10.3/11.4. Šest HTTP scénářů shodných a browser průchod PASS.
Lokální medián katalogu 5,00 → 0,77 s (30 vzorků, sdílená Windows stanice).
Technické vydání `09510ac6` proběhlo 28. 10. 2026;
[produkční protokol](verification/refactoring-wave3-release-20261028/RESULTS.md).
