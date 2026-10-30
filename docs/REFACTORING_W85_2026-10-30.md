# W8.5 — sportovní kontrakty a zbývající měření

Plán před implementací, 30. 10. 2026. Základ `50c542b057944c740e9424ad12f56d28e8183e98`.
W8.4 klubový kalendář se ověřuje samostatně v PR #241.

1. Doplnit přenositelné izolované MariaDB ověření původního rezervačního
   controlleru: dva souběžné požadavky na poslední místo, duplicita,
   čekací listina, rollback insertu/outboxu. Kontrolovat skutečné čekání na
   zámek, ne pouze následné sekvenční spuštění. Bez mail/push transportů.
2. Změřit uzavření 10/50/200 účastníků, oddělit povinné doménové zápisy
   od čtecích dotazů. Zachovat neměnné snapshoty, revize, role, důvody,
   vazby na odměny/náhrady. Existující race skripty mají pevné lokální
   prostředí a nejsou automaticky považovány za běžící součást CI.
3. R13 zdravotní seznam a historie: 0/100/1000 záznamů, soukromí,
   historie a revokace; měřit před návrhem další extrakce.
4. Posoudit a doložit veřejný rozvrh, sportoviště, RSVP, náhrady a čekací
   listinu proti dosavadním kontraktům. Přímé souhrnné dotazy ponechat,
   není-li naměřen problém. Browser klávesnice/mobil ověřit v W13/W15.
5. Případnou změnu provést teprve proti selhávajícímu kontraktu či měření.
   Plná regrese, PHPStan/styl, obě MariaDB, přesný main a chráněné vydání.
   Poté autonomně pokračovat W9–W15; skutečné UAT zůstává samostatné.

Všechny fixture databáze musí mít náhodné vlastněné jméno, localhost,
zapnuté FK, úklid ve `finally` a žádné produkční údaje či tajemství.
