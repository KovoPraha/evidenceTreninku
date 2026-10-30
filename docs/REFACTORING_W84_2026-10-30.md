# W8.4 — klubový kalendář a dokončení posouzení sportovních toků

Plán před implementací 30. 10. 2026, základ `fa57ff8676bb4746a2d91654efa7f20bedb8021a`.
Navazuje na připravované společné vydání W8.2/W8.3.

## Měřicí a implementační balík

1. Zmrazit čtení klubového seznamu/detailu: každý řádek nyní samostatně
   načítá cílové týmy, odkazy, osoby, počty, kapacitu a konflikty vozidel.
   Opakované termíny stejné akce opakují stejná data. Změřit 1/12/52 týdnů
   v rolích staff/public/family, prázdné přílohy, víc termínů a vozidel.
2. Po původní čerstvé kontrole viditelnosti načíst podklady povolených ID
   v omezených dávkách. Zachovat přesné pole/řazení, SQL stavové kolace,
   nulové/volitelné hodnoty a odstranění interních polí pro ne-staff.
3. Konflikty vozidel zachovají otevřené hranice překryvu, vlastní exclude ID,
   aktivní protistranu i zrušenou zdrojovou rezervaci. Nezměnit writer,
   explicitní potvrzení kolize ani opakovanou kontrolu v transakci.
4. RED/green SQL rozpočty a přesná data; srovnání na migrované MariaDB,
   čas/paměť bez instrumentace. Nutné formátování prokázat AST.
5. Posoudit veřejné kalendáře a formuláře/uzavření/rezervace/náhrady;
   zmapovat současné přímé dotazy, řízené zápisy a existující negativní/
   souběhové kontrakty. Další změnu přijmout pouze s doloženým přínosem.
6. Plná regrese, obě MariaDB, PHPStan/style, přesný zelený main a chráněné
   vydání s čtecími/HTTP důkazy. Poté pokračovat W9–W15.

Žádné reálné registrace, rezervace vozidel, zprávy, převody či aktivace.
Lidské UAT a provozní přejímka se neslučují s technickými testy.
