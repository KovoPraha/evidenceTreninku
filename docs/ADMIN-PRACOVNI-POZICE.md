# Pracovní pozice administrace

Stav k 21. 8. 2026.

Administrace je rozdělena na osm nepřekrývajících se pracovních pozic. Každá pozice má vlastní rozcestník a v navigaci vidí jen svoje funkce. Účet může mít přiřazeno více pozic, ale v jednu chvíli pracuje vždy právě v jedné z nich.

## Pozice a odpovědnosti

| Pozice | Odpovědnost |
| --- | --- |
| Trenér | Svěřenci, tréninky, testy a sportovní data vlastního družstva |
| Vedoucí sportu | Metodika, výkonnost, plánování sportu a přehled trenérů |
| Registrář členů a KIS | Členové, přihlášky, identity, souhlasy a citlivé členské údaje |
| Koordinátor programů a sportovišť | Kroužky, události, rezervace, haly a kalendáře |
| Správce katalogu e-shopu | Produkty, kategorie, nabídky, obrázky, sklad a import katalogu |
| Zákaznická péče a objednávky | Objednávky, expedice, předání, storna a zákaznická komunikace |
| Hospodář a platby | Bankovní účet, párování plateb, potvrzení úhrad, vratky a členské platby |
| Správce systému | Uživatelské účty, oprávnění, pracovní pozice, audit a technické nastavení |

Součet osmi rozcestníků pokrývá všechny odkazy určené přihlášenému personálu. Vlastnictví jednotlivých vstupních stránek je vedeno v jednom registru `includes/staff_workspaces.php`; stejný registr používá rozcestník, navigace, kontrola přístupu i automatické testy.

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
- Citlivé členské exporty vlastní Registrář členů a KIS.
- Stažení účtenky vlastní Hospodář a platby, fotografie člena Registrář a trenérské zátěžové výstupy Trenér.
- Všechny aktivní účty musí mít alespoň jednu a právě jednu výchozí pozici.

## Testovací kontrakt

Automatické testy ověřují přesně osm pozic, jediné vlastnictví každého vstupního bodu, úplné pokrytí personálních stránek, oddělení peněžních operací, přepínání superadministrátora a opakovatelnost migrace. Databázová migrace se v CI spouští také proti MariaDB 10.3 a 11.4.
