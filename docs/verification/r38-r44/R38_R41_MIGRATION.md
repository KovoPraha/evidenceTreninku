# R38 / R41 — rozsah a ověření převodu

Ověřeno 2026-10-23 pouze v izolovaném B1 prostředí (`127.0.0.1:33426`, syntetická data). Žádné produkční zápisy, externí odesílání ani nasazení.

## Implementace

- `includes/club_migration.php`: privátní staging, neměnný manifest, schvalované jednotlivé položky, zdrojové a cílové otisky, zámky, pokračování přerušené dávky a idempotentní provenance. Migrace `20261023080000` přidává čtyři evidenční tabulky a mutex.
- `includes/club_migration_sports.php`: explicitní mapy osob, trenérů, účtů, rodinných vazeb, soupisek a programů; skutečné tréninky, historické akce, výsledky a měření zapisuje do stávajících kanonických tabulek. Nevytváří přístupová oprávnění ani peněžní pohyby.
- `bin/club-migration.php`: příkazy `stage`, `review`, `apply`, `report`; živá pracovní pozice a identita aktéra; kontrola přesného místního datadiru, portu a syntetického prostředí. Produkční přepínač neexistuje.
- R39 se připojuje přes typované `authorize`, `preview`, `apply` a `target` adaptéry. Součástí schválení je SHA-256 skutečného převodního kódu, navázaných finančních služeb a migrací včetně nového helperu bankovních návratů.

Původní soubor zůstává beze změny. Přesné bajty se uloží pod SHA-256 do `private/imports/archive`; manifest navíc obsahuje úplný zdrojový objekt, časové okno, explicitní `Europe/Prague`, počet položek a otisky. Review i každá transakce apply kontrolují archiv, zdrojovou položku, schválený návrh a aktuální cíl. Report při poruše integrity odmítá stav dokončené zkoušky.

Kandidát duplicitní aktivity je konflikt k posouzení. Převzetí existující aktivity vyžaduje výslovné cílové ID, podklad a přesnou shodu skutečného obsahu, včetně účastníků a trenérů v uzavřeném snímku. Není povolen automatický merge. Výmaz ve zdroji zůstává k posouzení a nemaže cíl.

## Uzavřené nálezy nezávislé revize

1. Archivace vyžaduje oprávnění k typu; archivovaná či nevyřešená položka nikdy neznamená dokončenou migrační zkoušku. Otevřený finanční případ nelze skrýt jako úspěšně převzatý archiv.
2. Rodinný vztah ověřuje přesnou osobu, účet, roli a platnost; soupiska osobu, tým a období; program zdrojovou identitu. Samotná poznámka nebo cílové ID nestačí.
3. Rezervovaný `data._tombstone` nelze použít k přepsání příznaku zdrojového výmazu.
4. Mapa vytvořená jinou dávkou mezi review a apply musí odpovídat právě schválenému cíli a podkladům. Jiný schválený cíl import nezmění.
5. Úplný zdrojový payload a původní bajty mají ověřitelný archiv. Úprava archivu, manifestu nebo převodníku zneplatňuje schválení.
6. Prázdná dávka nemůže projít jako dokončená zkouška. Novější kanonická data nelze přepsat opakováním staré dávky.

## Důkazy

- `tests/Integration/ClubMigrationTest.php`: **19 testů, 146 assertions PASS** na finálním převodním kódu.
- `tests/Support/ClubMigrationAcceptance.php`: **17 skutečných CLI/MariaDB kontrol PASS**, run `LOCALHOST NEPLATIT R38-R41 ab13bd0746`. Výstup `migration-cli-mariadb.json`.
- Souběh dvou procesů byl pozorován přímo jako MariaDB `LOCK WAIT`; po uvolnění vznikl jediný skutečný trénink (75 minut). Druhý scénář při souběžné změně identity import odmítl a nevytvořil částečný trénink.
- Samostatná nezávislá revize zopakovala **8 reprodukcí PASS**, včetně změny jednoho bajtu skutečného archivu a změny úplného source payload. Její pracovní skripty zůstaly pouze v místním runtime.

## Záměrné hranice

Toto je ověřený syntetický převodník, nikoli uskutečněná migrace původní Evidence nebo Shoptetu. Živé exporty, vlastníci zdrojů, skutečné mapy, účetní metodika a lidská přejímka zůstávají k samostatnému potvrzení. Neznámé typy, jednotky, neověřené identity, nedoložené otevřené případy a archivní rozsah se odkládají; neodhadují se. Historická akce pouze s datem nevytváří vymyšlený čas kalendářní lekce. Obnova zdrojových archivů je součástí samostatné zkoušky zálohy/obnovy rodičovského balíku.
