# K0.1 + první měřicí a charakterizační balík W6.1

Datum: 29. 10. 2026. Základ `3cc60fb6d7fad4bc6430724529295ef025d75178`.
Rozsah a autorizace: [realizační záznam](../../REFACTORING_K0_W6_2026-10-29.md).
[Registr 16 kritických cest](../../REFACTORING_ROUTE_REGISTER_2026-10-29.md)
obsahuje identitu/guard, scope, služby, hlavní tabulky, vedlejší účinky,
citlivost, testy a mezery v měření. K0.1 dokončeno; plošné K0.2 zůstává otevřené.

## Implementované nástroje a charakterizace

- `ProgramCapacityCharacterizationTest`: 13 nových scénářů s úplnými očekávanými
  strukturami. Hranice expirace a UTC/Prague/DST, překryv dat, NULL/0 kapacita,
  person/request/item + max(quantity), source item, placená identita, legacy
  schema/fallback, více fyzických týmů, exclude, čerstvý výsledek a rollback.
- `ProgramOfferBatchReadTest`: navíc 0/1/10/400 variant; existující 50/401,
  duplicate fallback, neaktivní nabídky a aktuální zamykající čtení zachovány.
- `QueryProfilePdo` s vlastním testem měří úspěšné execute/fetch a načtené řádky.
  Nejde o produkční telemetry; žádný zásah do bootstrapu ani aplikačního PDO.
- `ProgramCapacityProfile`: 4 syntetické profily, vždy 5 zahřátí a 30 střídavých
  vzorků kanonického a již existujícího kontextového čtení. Výstup zahrnuje SQL,
  parametry syntetických dat, execute/fetch, vrácené řádky, paměť, velikost výsledku
  a EXPLAIN. Oba režimy musejí vracet identické struktury a očekávanou obsazenost.
- CI spouští stejné nové charakterizační testy na MariaDB 10.3/11.4 s nativními
  prepared statements a uchovává profil jako artefakt. Existující checkout smoke
  nad migrovaným schématem nadále hlídá souběh posledního místa a idempotenci.

## Naměřený lokální základ

[Úplné vzorky a EXPLAIN](capacity-profile-mariadb-10.4.json), MariaDB 10.4.32,
PHP 8.2.12. Redukovaná indexovaná fixture, nikoli migrované produkční schéma.
Každý profil má polovinu účastí/rezervací na týmu 10 a polovinu na týmu 20;
fan-out čte oba týmy a vrací obsazenost nejplnějšího týmu, ne jejich součet.

| Profil | Přihlášky + rezervace ve fixture | Týmy | SQL canonical/context | Načtené řádky context | Medián canonical/context ms |
| --- | --- | --- | --- | --- | --- |
| empty | 0 + 0 | 1 | 13 / 8 | 20 | 117,31 / 63,26 |
| small | 10 + 10 | 1 | 13 / 8 | 30 | 108,49 / 56,61 |
| dense | 1 000 + 1 000 | 1 | 13 / 8 | 1 020 | 108,86 / 60,05 |
| fanout | 1 000 + 1 000 | 2 | 24 / 10 | 2 022 | 204,17 / 70,79 |

Řádky zahrnují i schema metadata. Query count je nezávislý na hustotě, zatímco
načtené řádky rostou. Při fan-out se čtení obsazenosti opakuje pro další tým.
Nejde o nové zrychlení: porovnávají se dvě již existující cesty. Lokální latence
obsahuje testovací instrumentaci a režii hostu; není to SLA, HTTP ani produkční
zátěžový výsledek. Z tohoto EXPLAIN nelze zdůvodnit produkční index.

## Ověření a omezení

- Cílená finální SQLite sada: 21 testů / 108 assertions PASS.
- MariaDB 10.4: finálních 13 charakterizačních scénářů / 43 assertions PASS.
- Composer validate, styl změněných PHP, aktuální dokumentace a diff check PASS.
- Plný lokální PHPUnit: 1 795 testů / 35 878 assertions, 3 Windows POSIX skipy.
  Tento běh začal před přidáním posledních dvou metod; finální cílená sada
  výše je již zahrnuje. Úplný finální head ověřuje Linux CI v
  [PR #220](https://github.com/KovoPraha/evidenceTreninku/pull/220).
  Windows POSIX skip není úplný Linux PASS; merge je podmíněn zeleným finálním CI.
- První běh nového DST testu odhalil chybu testovacího generování času (lokální
  `modify` přes neexistující hodinu). Generátor nyní posouvá UTC okamžik před
  převodem do Prague. Aplikační chování se neměnilo.
- Test instrumentace odhalil nutný výchozí index 0 pro PDO::FETCH_COLUMN;
  měřicí wrapper opraven před uložením výsledků. Doménový kód beze změn.

## Rozhodnutí

W6.2 má začít malou extrakcí čistého výpočtu identit/obsazenosti bez změny SQL,
zámků, termínů a oprávnění. Nevydávat ji za zrychlení bez nového měření.
W6.3 (dávkové obsazenosti/index) zůstává kandidát: nejprve profil s migrovaným
schématem, více nabídkami na týmu, kontrolou fan-out a paměťovým limitem.
Další agendy mají explicitně nezměřené profily v registru; celý K0/W6 není hotový.

Tento balík neobsahuje aplikační změnu, migraci, produkční deploy, zapnutí
provideru ani import. Původní rozpracovaný `kis_rosters_admin.php` zachován.
