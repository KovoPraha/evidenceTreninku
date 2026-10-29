# W6.2 — čistý výpočet kapacity

Základ `9da70c97b3b825ae50459a8663aea5af50dc7982` (PR #220), 29. 10. 2026.
[Rozsah a brány](../../REFACTORING_WAVE6_2026-10-29.md).

## Změna

`clubProgramCapacityFromRows` v `includes/club_program_capacity.php` vyhodnocuje
již vybrané rezervace: oddělené klíče person/request/item, maximum quantity,
vyloučenou osobu, obsazenost a dostupnou kapacitu. Nemá PDO, hodiny, session
ani konfiguraci. Počítá i původní legacy fallback, kde klíče aktivních řádků
nejsou ID osob a nesmějí potlačit rezervaci podle osoby.

Původní `clubProgramOfferCapacityStateForTeam` dál provádí stejné čtení,
expirační/identitní filtry a FOR UPDATE. Fyzické týmy, výběr nejplnějšího týmu,
public context i kanonický checkout jsou zachovány. Bez migrace, nového indexu,
nové cache nebo změny ceny/oprávnění. Přínos je testovatelná doménová hranice;
tento balík neslibuje zrychlení veřejného katalogu.

## Důkazy

- Charakterizace původního kódu byla sloučena před extrakcí v PR #220: finální
  Linux CI 1 797 testů / 35 938 assertions; MariaDB 10.3/11.4 PASS.
- Po extrakci cílená sada: 35 testů / 164 assertions PASS; MariaDB 10.4
  13 charakterizačních scénářů / 43 assertions PASS.
- Nový unit test bez databáze kontroluje pořadí a opakování řádků, přednost
  person před request, nezávislé namespace, původní vstupy a legacy režim.
- [Archiv původních funkcí](../../../tests/Support/ProgramCapacityBaseline.php) je pouze CLI benchmark
  z uvedeného základního SHA, s přejmenovanými funkcemi. Není druhým runtime
  algoritmem aplikace. `ProgramCapacityComparison.php` porovnává oba kódy nad
  stejným PDO, daty a čerstvými kontexty, střídá pořadí, 5 zahřátí + 30 vzorků.
  Výsledky musejí být zcela shodné; hlídá i SQL, parametry a načtené řádky.
- CI nyní provádí toto porovnání na MariaDB 10.3/11.4 a uchovává artefakt.
  Migrované schéma a souběh checkoutu dále kontroluje stávající smoke test.

První [profil po extrakci](capacity-profile-after-concurrent.json) vznikl za
souběhu dalších lokálních kontrol po restartu lokální DB. Ukázal vyšší latence
proti staršímu měření K0; nepoužívá se jako kauzální důkaz ani se nezamlčuje.
Shodné počty SQL/řádků a struktury výsledků nestačí k odvození latence. Následuje
řízené střídavé porovnání obou implementací na stejném testovacím hostu.

[Finální klidný běh](capacity-comparison-quiet.json),
[souhrn shody](comparison-summary.json),
[první přímé porovnání](capacity-comparison-first.json).
Finální běh proběhl bez souběžných lokálních PHP testů. Oba kódy používají
stejný kontextový režim; původní K0 porovnání canonical/context není before/after.

| Profil | Medián před / po (ms) | SQL před = po | Řádky před = po | Inkrementální peak před / po (B) |
| --- | --- | --- | --- | --- |
| empty | 42,59 / 43,16 | 8 | 20 | 98 216 / 98 272 |
| small | 47,87 / 45,27 | 8 | 30 | 101 112 / 101 456 |
| dense | 72,02 / 77,55 | 8 | 1 020 | 378 208 / 378 208 |
| fanout | 57,54 / 58,00 | 10 | 2 022 | 381 904 / 381 904 |

Výsledky, SQL, parametry a načtené řádky jsou identické. Mediány kolísají mezi
-5,4 % a +7,7 %, paměť nejvýše +344 B; není podklad pro tvrzení o zrychlení.
Všechny běhy mají redukované schéma; indexové/zátěžové závěry zůstávají mimo rozsah.

První plná sada zachytila benchmarkový helper uložený v `docs`, které scanner
dostupnosti globálních funkcí záměrně neprochází. Přesunut do `tests/Support`,
bez nové výjimky; cílený scanner 3 testy / 13 assertions PASS. První lokální
Composer běh také překročil 300 s hostitelského timeoutu. Opakování používá
lokální `COMPOSER_PROCESS_TIMEOUT=900`, nemění CI limit ani obsah testů.

Finální plná sada, přímé porovnání a CI budou uzavřeny před merge. Produkční
vydání má vlastní nový protokol; staré release důkazy nejsou jeho náhradou.

## Další práce

W6.3 vyžaduje úplný profil migrovaného schématu a bounded batch/fan-out důkaz.
K0.2 pokračuje po doménách podle registru; celý komplexní plán není tímto uzavřen.
Plné lidské a účetní UAT a aktivace integrací zůstávají samostatné.
