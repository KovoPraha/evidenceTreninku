# Pracovní pozice administrace

Stav k 22. 8. 2026.

Administrace je rozdělena na osm nepřekrývajících se pracovních pozic. Každá pozice má vlastní rozcestník a v navigaci vidí jen svoje funkce. Účet může mít přiřazeno více pozic, ale v jednu chvíli pracuje vždy právě v jedné z nich.

## Pozice a odpovědnosti

| Pozice | Odpovědnost |
| --- | --- |
| Trenér | Svěřenci, tréninky, testy a sportovní data vlastního družstva |
| Vedoucí sportu | Metodika, výkonnost, plánování sportu a přehled trenérů |
| Registrář členů a KIS | Členové, přihlášky, identity, souhlasy a citlivé členské údaje |
| Koordinátor programů a sportovišť | Kroužky, události, editace a storna rezervací, haly, veřejný velodrom a kalendáře |
| Správce katalogu e-shopu | Produkty, kategorie, nabídky, obrázky, sklad a import katalogu |
| Zákaznická péče a objednávky | Objednávky, expedice, předání, storna a zákaznická komunikace |
| Hospodář a platby | Bankovní účet, párování plateb, potvrzení úhrad, vratky a členské platby |
| Správce systému | Uživatelské účty, oprávnění, pracovní pozice, audit a technické nastavení |

Součet osmi rozcestníků pokrývá všechny odkazy určené přihlášenému personálu. Vlastnictví jednotlivých vstupních stránek je vedeno v jednom registru `includes/staff_workspaces.php`; stejný registr používá rozcestník, navigace, kontrola přístupu i automatické testy.

Podpůrný editor může mít jednoho vlastníka a výslovně uvedenou další pozici, která ho smí použít v navazujícím pracovním postupu. Taková delegace nepřidá cizí menu ani celou cizí agendu. Lokální diagnostické stránky jsou v registru označeny jako lokální a na produkčním rozcestníku ani v navigaci se nezobrazí.

## Superadministrátor

Superadministrátor není devátá pracovní pozice. Je to příznak účtu, který umožní přepínat mezi všemi osmi pozicemi. Po přepnutí se stránka i navigace chovají stejně jako u běžného držitele dané pozice; oprávnění se nesčítají.

Každé přepnutí se zapisuje do `staff_position_switch_events`. Změny přiřazených pozic a superadministrátorského příznaku se zapisují do `staff_position_assignment_events` s povinným důvodem. Systém nedovolí odebrat posledního superadministrátora.

## Přihlášení a správa

- Po přihlášení se personál dostane na `pracovni_pozice.php`.
- Běžný účet může přepínat jen mezi přiřazenými pozicemi.
- Superadministrátor může přepnout na kteroukoli z osmi pozic.
- Přiřazení spravuje pozice Správce systému na `sprava_pracovnich_pozic.php`.
- Nový účet trenéra automaticky dostane pozici Trenér.
- Původní aktivní účty jsou při migraci převedeny podle staré role; dosavadní administrátoři získají všech osm pozic a příznak superadministrátora, aby nasazení nezablokovalo správu.

## Bezpečnostní pravidla

- Kontrola vlastníka stránky proběhne centrálně po ověření přihlášení a před provedením změny.
- Neznámý klíč oprávnění je zakázán; starý automatický přístup pro vedoucího se nepoužívá.
- Peněžní operace nejsou součástí správy objednávek. Potvrzení platby a vratky vlastní pouze Hospodář a platby.
- Peněžní sazby za trénink vlastní Hospodář a platby; trenér eviduje odvedenou práci, ale neurčuje sazbu.
- Trenér ani koordinátor nepotvrzuje přijetí peněz za individuální lekci nebo velodrom. Takové potvrzení je ve společné finanční frontě Hospodáře a plateb.
- Změna lekce, přesun rezervace a uzavření termínu vyžadují důvod a zapisují se do auditní historie provozu sportovišť.
- Citlivé členské exporty vlastní Registrář členů a KIS.
- Stažení účtenky vlastní Hospodář a platby, fotografie člena Registrář a trenérské zátěžové výstupy Trenér.
- Všechny aktivní účty musí mít alespoň jednu a právě jednu výchozí pozici.

## Uzavřené pracovní cesty

- Koordinátor programů může opravovat a archivovat stabilní programy, upravit základní údaje klubové akce, měnit nebo rušit její termíny, uzavřenou akci vrátit k úpravě a nakonec ji archivovat. Otevřená akce nebo program s otevřenou nabídkou se archivovat nedá.
- Registrář spravuje členství v soupiskách i životní cyklus jejich struktury. Kódy, kalendáře a období zůstávají stabilní identita; názvy lze auditovaně opravit. Tým lze uzavřít až po ukončení aktivních členství, sezonu a sérii až po uzavření aktivních týmů.
- Hospodář může členský předpis založit, opravit před úhradou, potvrdit úhradu podle bankovního výpisu nebo ho zrušit. Uhrazený předpis se nezruší touto cestou, protože případné vrácení peněz je samostatný finanční děj.
- Správce systému může pracovní účet auditovaně založit, upravit, deaktivovat a znovu aktivovat. Deaktivace není mazání, ukončí existující relace zvýšením `session_version`; vlastní účet nelze deaktivovat a aktivace hlídá kolizi e-mailu.

## Testovací kontrakt

Automatické testy ověřují přesně osm pozic, jediné vlastnictví každého vstupního bodu, výslovné delegace podpůrných editorů, produkční skrytí lokálních nástrojů, úplné pokrytí personálních stránek, oddělení peněžních operací, přepínání superadministrátora a opakovatelnost migrace. Databázová migrace se v CI spouští také proti MariaDB 10.3 a 11.4.
