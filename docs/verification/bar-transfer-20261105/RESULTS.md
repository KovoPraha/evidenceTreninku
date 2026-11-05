# Převod katalogu baru a základ správy skladu

5. 11. 2026, navazuje na B1 v draft PR #306. Jde o implementaci a syntetický nácvik; skutečný zdrojový katalog, fotografie, účet lucka ani produkce se neměnily. [Plán začlenění](../../BAR_INTEGRATION_PLAN_2026-11-05.md).

## Co je připravené

Převodní nástroj čte konzistentní read-only snímek zdroje, vybere pouze účet lucka a vytvoří soukromý balíček mimo Git a webroot. Kontroluje vazby katalogu, přesné částky, moderní hash hesla a skutečný obsah fotografií včetně rozměrů a otisků. Import znovu ověří celý balíček. Do nové barové evidence převede katalog a receptury s nulovými zásobami, fotografie do soukromého úložiště a lucku pouze s pozicí Obsluha baru. Kolize jména či e-mailu import zastaví, včetně kolize s veřejným účtem. Jiný již naplněný katalog se nepřepisuje.

Identická dávka se pod zámkem rozpozná a neopakuje; pozdější změny cen a skladu zůstanou zachované. DB zápisy, přidělení pozice a audit jsou v jedné transakci. Při pádu mohou v soukromém úložišti zůstat nepřiřazené soubory podle otisku; nejsou veřejně dostupné a opakování je bezpečně znovu použije.

Technický e-mail je výslovně označený v účtu. Lucka se přihlašuje jménem a nevytváří se jí automatický zákaznický účet. Adresa neumožňuje veřejnou ani pracovní e-mailovou obnovu hesla a je blokovaná pro nové i již čekající bookingové zprávy a připomínky. Změna příznaku se ověřuje v aktuální DB také při doručení či použití tokenu.

Správce baru upravuje produkty, ceny, nastavení a jednoduché receptury. Změny vyžadují důvod a shodnou verzi záznamu. Sklad podporuje příjem, přesuny oběma směry a inventuru na dvou místech. Operace má jedinečný token, audit a pohybový deník s historickým názvem i nákupní cenou; neznámá cena zůstává neznámá. Zamykání brání záporným zásobám a přepsání souběžné změny. Obsluha nedostává předsklad, nákupní ceny ani správu katalogu. Fotografie se poskytují pouze přihlášené oprávněné roli.

## Ověření

| Kontrola | Výsledek |
| --- | --- |
| Úplná sada PHPUnit | 2065 testů, 39167 assertions, bez selhání; 3 symlink testy přeskočené na Windows, povinné v Linux CI |
| Cílená sada | 26 testů / 152 assertions: převod, správa, vykreslení a ochrana technického e-mailu PASS |
| Statická analýza | PHPStan celého projektu: 832 souborů, bez chyb; odstraněné překonané potlačení pro inicializaci připojení v loginu, žádné nové potlačení |
| Styl, syntaxe a Composer | Styl všech 46 změněných PHP souborů, lint 36 PHP souborů změněných proti B1, `composer validate --strict` a `git diff --check` PASS |
| Anonymní HTTP | 243 chráněných tras, 34 veřejných výjimek, 2 CLI, 0 chyb |
| MariaDB 10.4.32 | Opakované migrace, read-only čtení zdroje, fotografie, omezená lucka, opakování importu, skladový deník a aktuální zamykající autorizace přes dvě spojení PASS |
| Záloha a skutečná obnova | 309 vlastněných syntetických tabulek PASS; vlastnictví zahrnuje všechny nové tabulky a příznak technického e-mailu |
| Místní přihlášený browser | Lucka: přihlášení jménem, fotografie, cena a zásoba stánku; správa katalogu a skladu vrací 403. Správce: úprava ceny českým desetinným zápisem, příjem 10 kusů do předskladu a přesun 3 kusů na stánek PASS |
| Kontrola DB po browseru | Cena 3750 haléřů, stánek 3, předsklad 7; 2 operace / 3 pohyby, celkový přírůstek 10. Opakované CLI použití stejné dávky vrací `repeated: true` a všechny tyto hodnoty zachová |

Browser používá výhradně syntetickou databázi `evidence_bar_test_*`, vlastní loopback port a vypnutou funkci odeslání mailu. Snímky obrazovky jsou v ignorovaném `output/playwright/bar-b2-operator.png` a `bar-b2-stock.png`. Při browser ověření se opravilo předání DB spojení společné hlavičce a dostupnost pomocné funkce pro editaci cen; regresi pokrývá test skutečného rendereru. Kliknutí na společné odhlášení blokovala jiná vrstva hlavičky; odhlášení nebylo tímto průchodem potvrzeno.

Linux CI přesného commitu je samostatná brána: obě MariaDB varianty 10.3/11.4 obsahují nový převodový smoke. Výsledek lokální MariaDB sám tuto bránu nenahrazuje.

## Hranice dokončení

Skutečný zdroj se zatím neexportoval ani neimportoval. Není ověřená parita reálného katalogu a všech jeho fotografií; neplatný hash, chybějící fotografie či kolize musí být vyřešené před převodem. Importní CLI nyní přijímá pouze izolovaný místní cíl. Produkční převod vyžaduje samostatně schválený postup.

B3 má hotové základní operace produktů, receptur a skladu. Navazuje správa kategorií a výměna fotografií, pokladna/kasy/storna/vlastní spotřeba (B4), faktury/nabídky/účetní kontrakt (B5) a úplná integrační přejímka (B6). Zelené CI ani tento nácvik neznamenají dokončení celého začlenění. Stav nasazení dál určuje CURRENT_STATE; žádný deploy ani aktivace integrací neproběhly.
