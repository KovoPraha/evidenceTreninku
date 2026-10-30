# W10 — finanční přehledy a účetní výstupy

Příprava 30. 10. 2026, základ `3b2c027a115a51bf43bc6cfac59380e2cbaf5f96`.
Sloučení následuje až po ověřených kontraktech W9.

Ve `accounting_export.php` jsou opakované průchody nad celým seznamem
členění, přídělů, pohybů, událostí a výplat pro každý jednotlivý záznam.
Kandidát: čisté indexované projekce pro výjimky snapshotu a list Saldo.
Nezměnit SQL výběr, transakční okamžik, pořadí, typy, znaménka, minor units,
historické opravy, poslední platné členění, stornované vratky ani význam nuly.

Před změnou zmrazit současné funkce/projekce. Porovnat všechny výstupní
řádky, včetně duplicitních klíčů, pořadí historie, chybějících hodnot,
pozdního otevření účtu, příjmů/přídělů/skutečných vratek a zrušených rozhodnutí.
Měřit 1000/10000/100000 řádků: výpočet i paměť odděleně od XLSX serializace.
Kvadratický baseline lze na největším vzorku omezit předem popsaným časovým
rozpočtem; neuvádět extrapolaci jako skutečné měření.

Upřesnění po prvním běhu: 35 opakování baseline na 10000 řádcích překročilo
300 sekund a bylo automaticky ukončeno. Pro baseline od 10000 řádků proto
jen jedno měření bez zahřátí; u 100000 navíc limit 15 sekund. Nová projekce
má pět zahřátí a 30 vzorků. Rozdílnou metodiku uvést, nevydávat jeden vzorek
za statistiku latence. Shoda celých řádků a analytické součty platí nezávisle
na výkonovém měření. Jde o čistý výpočet, nikoli celý export.

Dále prověřit osobní finanční historii, saldo/ledger a render dokladů;
další extrakci přijmout pouze s konkrétním přínosem. Existující konzistentní
snapshoty, oprávnění hospodáře, souběhy a rollback zůstanou povinnými branami.
Exportované XLSX ověřit po načtení buňky/typy/listy; změněný vizuální obsah
také renderem. PDF a soubory nesmí ztratit checksum a privátní uložení.

Neprovádět skutečné finanční operace ani aktivaci integrací. Výsledky
zapsat do protokolu, ověřit plnou regresi a obě MariaDB, pak autonomní
sloučení a technické nasazení. Lidská účetní přejímka zůstává samostatná.
