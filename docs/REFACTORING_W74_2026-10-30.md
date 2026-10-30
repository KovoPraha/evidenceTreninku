# W7.4: dávkové načítání členských polí

Plán před implementací, 30. 10. 2026. Základ `e65a1f5fc873f0a307b81261ed06548eb0623a24`.
Vlastník výslovně povolil implementaci, sloučení a nasazení této i dalších vln
až do technického dokončení [komplexního plánu](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md).
Aktivace integrací, skutečné převody dat a lidská přejímka tím nejsou povoleny.

## Zjištění a změna

`familyPortalAuthorizedPeople` po čerstvé kontrole vazeb načítá čtyři členská
pole jedním samostatným SELECTem pro každou osobu. Jednotlivé sekce stránky
volají tento seznam opakovaně. W7.3 naměřila při 20 osobách 160 těchto dotazů.

Uvnitř jediného volání deduplikovat autorizovaná ID a načíst členství v dávkách
nejvýše 400 ID. Pro prázdný seznam žádný dotaz; pro jedinou osobu ponechat
stávající čtení. Uchovat pořadí osob, rolí i klíčů, chybějící volitelné sloupce,
filtr čtyř polí a chování při chybějící osobě. Žádná cache mezi voláními.
Autorizační SQL, zápisy a kontroly po čekání na zámek zůstávají stejné.

## Ověření a vydání

1. Nejdříve selhávající rozpočet dotazů; pak shoda s původním čtením pro
   0/1/5/20 a hranice 399/400/401/1000 osob, duplicity, cizí účet a revokaci.
2. Porovnat celý kontroler a HTML proti zmrazenému W7.3 readeru ve všech
   sekcích, ostatní SQL a počet načtených řádků. Oddělit profilování od času.
3. Lokální PHPUnit, PHPStan, styl, dokumentace; CI na MariaDB 10.3 a 11.4,
   historie, POST a existující souběhové autorizační testy.
4. Čerstvá read-only produkční diagnostika a HTTP před/po; sloučit až zelený
   přesný commit, nasadit chráněným workflow s UAT/importem vypnutými.
5. Uložit skutečné výsledky a navázat zbývajícími R03/R04 ve W7, poté W8–W15.
   Každá oblast musí mít implementaci s důkazem nebo doložené vyřazení kandidáta;
   samotná absence změny ani zelené HTTP neznamenají dokončení celé aplikace.

Stav: plán uložen, implementace a ověření probíhají. Nasazení zatím neprovedeno.
