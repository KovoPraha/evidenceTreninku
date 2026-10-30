# W8.5 — přenositelné sportovní kontrakty

30. 10. 2026, [plán](../../REFACTORING_W85_2026-10-30.md).
Tato vlna nemění produkční služby; doplňuje měření a povinné CI kontrakty.

## Rezervační controller

[BookingControllerMariaDbRace](../../../tests/Support/BookingControllerMariaDbRace.php)
spouští skutečný `booking/rezervovat.php` ve dvou PHP procesech. Jedinou
substitucí je bootstrap s již autentizovaným syntetickým PDO; podmínka
počtu nahrazených require chrání proti tichému driftu. Parent drží stejný
`GET_LOCK` a v PROCESSLIST ověří obě skutečná čekání před uvolněním.
Nejde o HTTP/browser test a není za něj vydáván.

[Výsledek](booking-race.json):

- poslední místo: dva čekající procesy, jedna rezervace a jeden kontext oznámení;
- duplicita stejného účtu: dva čekající procesy, jedna rezervace a oznámení;
- plný termín: dvě čekací registrace, žádné oznámení o potvrzené rezervaci;
- trigger odmítající outbox: insert rezervace i kontextu vrácen zpět.

Celý katalog migrací, localhost, unikátní vlastněné schéma a úklid ve
`finally`, FK zapnuté. Syntetické účty/example.test, žádný VAPID ani worker
transportu. Povoleny jsou pouze přesné diagnostiky neaktivního WebPush,
nikoli libovolný stderr. Tento test nepotřeboval měnit původní writer.

## Skutečné souběhy historie a oprávnění

Tři existující skripty byly vázané na pevný Windows adresář, port a účet
`b1_app`; ve standardním workflow se nespouštěly. Nový `SportRaceRuntime`
zajišťuje localhost, validovaný port, náhodné vlastní schéma, oddělené
artefakty, úklid procesů/databáze/souborů i při selhání. Není potřeba
přidávat globální GRANT. Doménové scénáře, očekávané chyby a skutečné
pozorování InnoDB LOCK WAIT zůstaly zachované.

- [Trénink/náhrady](training-race.json): dva reálně čekající procesy,
  uzavření jedné revize, kolize plánu, poslední náhradní místo a
  zneplatnění versus finalizace; jediný vítěz tam, kde je vyžadován.
- [Autorizace](training-authorization-race.json): odnětí role, deaktivace,
  odebrání přiřazení i superadmina během čekání; žádná nová revize.
- [Zdravotní rozhodnutí](medical-race.json): souběžné ověření a odvolání,
  jedna změna i audit, zamítnutí zastaralé revize.

Skripty zachovávají vlastní redukované doménové fixtures; nejsou vydávány
za celý migrační katalog. Ten používá rezervační test a následující profil.
Všechny čtyři testy nově běží v CI MariaDB 10.3 i 11.4. Snímky před čistým
formátováním a soubory `*.ast.txt` dokazují shodu AST/literálů; normalizátor
je v [formatting-ast-check.txt](formatting-ast-check.txt).

## Měření a rozhodnutí o dalších změnách

[sport-assessment.json](sport-assessment.json), plně migrovaná MariaDB,
FK zapnuté. Čas/paměť plain PDO; uzavření warmup + pět dalších revizí,
stejná účast, explicitní kontrola původního snapshotu i částek odměn.

| Účastníci uzavření | Čtecí SQL | Zápisové SQL | Zámky historie odměn | Medián ms | Špička B |
| --- | ---: | ---: | ---: | ---: | ---: |
| 10 | 52 | 26 | 10 | 18,12 | 151072 |
| 50 | 172 | 106 | 50 | 66,21 | 164640 |
| 200 | 622 | 406 | 200 | 230,42 | 232112 |

Zápisy zahrnují jednotlivou skutečnou účast a auditní review uzavřených
odměn. Čtení obsahuje čerstvé oprávnění a zámky osob/historie v transakci.
Růst je lineární a ověřovaný (`read <= 32 + 3N`, `write = 6 + 2N`).
V měřeném rozsahu se další změna nepřijímá: odstranění těchto kontrol
bez zachování zámků by měnilo souběhový kontrakt, naměřená paměť je malá.

Zdravotní seznam pro 0/100/1000 sledování má stále **2 SQL**; při 1000
načte 2000 řádků, vrátí stránku 25, lokálně 21,39 ms / 2864320 B.
Stavový filtr se vyhodnocuje nad historickým rozhodnutím; prostý SQL LIMIT
před jeho výpočtem by změnil total a stránkování. Ponecháno. Pole originálu
a storage key do seznamu nevstupují. Dalších 1000 historických záznamů
jedné osoby: 5,79 ms / 673704 B; čerstvé odvolání se okamžitě projeví.
Časy jsou lokální vzorky, nikoli produkční latence ani SLA.

Veřejný rozvrh a sportoviště mají po jednom souhrnném SQL bez per-row
načítání; `PublicTrainingScheduleTest` / `VenueCalendarTest` hlídají
veřejná pole, data a deduplikaci. Nová extrakce není doloženě potřebná.
RSVP/náhrady navíc kryjí stávající integrační testy a nově standardní race.

## Rozsah přejímky a návaznost

Technické sportovní kontrakty jsou po CI podkladem pro pokračování W9.
Zbývající browser klávesnice/mobil a souvislý uživatelský tok jsou výslovně
součást W13/W15; lokální/CI testy nejsou skutečné UAT. Finanční historie
osoby pokračuje ve W10. K0.2 a posouzení cross-offer kapacity zůstávají
v komplexním plánu, neoznačují se tímto za dokončené.

Lokální plná regrese na základu W8.3: 1839 testů / 36650 assertions,
tři očekávané POSIX skipy Windows ([záznam](phpunit-windows.txt)).
PHPStan bez nálezů, styl a dokumentační kontrakt PASS.
Plná CI výsledného základu a obě MariaDB jsou nutné před sloučením.
Změna je testovací/CI a neaktivuje žádné služby ani transporty.
