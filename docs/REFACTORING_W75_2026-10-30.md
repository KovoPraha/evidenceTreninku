# W7.5 — seznam osob a cílené ověření správy

Příprava 30. 10. 2026 během CI W7.4, založeno z `a3fae123`, následně
synchronizováno s `1e273c205ac4f25c686780e46bac2a38da4eef86` (důkazy W7.4).
Navazuje na autonomní pokyn vlastníka dokončit technické vlny včetně vydání.

## Zjištění

R04 `sprava_sportovcu.php` řádky 226–285 načtou všechny filtrované osoby včetně
agregátů tréninků/skupin a až v PHP vyberou stránku. Výchozí stránka má 100 osob,
ale materializace roste s celým seznamem. Řazení nemá poslední klíč ID.
Globální statistika trénujících při prázdném hledání je odlišná od počtu při
neprázdném hledání; tento existující význam musí zůstat zachován.

R03 používá dva přímé seznamové dotazy, bez N+1. Samostatně změřit rostoucí
historii žádostí; není důvod jej nahrazovat rodinným readerem (odlišný kontrakt
duplicit a rolí). `accountPersonCanManage` načítá celý seznam pro jedinou osobu;
prověřit jeho volající a charakterizovat existující predikát před cíleným SELECTem.
Karta sportovce už používá cílené ID a limity 5/6; samostatně měřit timeline,
editor a historie, délka souboru sama není důvod k rozdělení.

## Přijatý kandidát a brány

1. Oddělit čtecí projekci registráře do helperu, aktuální guard a POST akce
   ponechat ve stránce. COUNT/EXISTS pro statistiky, LIMIT/OFFSET pro stránku;
   explicitní volba pp=0 zachová celý seznam, limity 50/100/500 a clamping stránky.
2. Zachovat LIKE kolaci, NULL, filtry a všechny dostupné sorty; posledním klíčem
   přidat ID ve stejném směru, aby shodné hodnoty mezi stránkami nepřeskakovaly.
   Nativní prepared statements nesmějí opakovat stejný pojmenovaný placeholder.
3. Zmrazit původní projekci a porovnat výsledky/statistiky; shody řazení testovat
   se specifikovaným ID klíčem. Profily 100/1000/10000 osob s realistickou šířkou,
   indexy, duplicitními vazbami a tréninky. Paměť i načtené řádky mají odpovídat
   stránce, nikoli celému adresáři; SQL COUNT může skenovat více řádků.
4. Ověřit stránku, odkazy a editor, prázdný stav a POST CSRF, soukromé údaje,
   celý PHPUnit/PHPStan/style a obě MariaDB. Neměnit členství, zámky ani zápisy.
5. Zelený přesný PR/main, chráněné nasazení bez UAT/importů, čtecí před/po důkazy.
   Neuzavírat další kandidáty bez měření; navázat W8 podle komplexního plánu.

## Realizovaný rozsah a stav

W7.5 realizuje čtecí projekci a stránkování registráře. R03 a cílené ověření
správy zůstávají navazujícími kandidáty W7.6; nejsou označeny jako hotové.
Přidána charakterizace smíšených kolací, paměťové brány a přesná shoda 50 scénářů.
Nutné formátování stránky ověřeno AST a doslovným HTML. Test SRI byl zpřesněn
pro konstantní konkatenace, s negativní detekcí chybějících atributů.
[Měření a stav kontrol](verification/refactoring-w75-20261030/RESULTS.md).
Lokální kontroly PASS; sloučení a nasazení následují až po zeleném CI.
