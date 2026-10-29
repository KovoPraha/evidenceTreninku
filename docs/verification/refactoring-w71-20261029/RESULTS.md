# W7.1a — cílené ověření rodinného přístupu

29. 10. 2026, základ `54013cdd25c0e77f6250c55e506e3e7dbaaafa36`.
[Konkrétní plán](../../REFACTORING_W71_2026-10-29.md).

## Změna

Boolean `familyPortalCanViewPerson` používá SELECT existence pro jednu osobu
s LIMIT 1. Seznam a cílená kontrola sdílejí stejný statický SQL fragment:
JOIN účtu/osoby, approved self/guardian, časová platnost, aktivní ověřený účet.
Bez cache, změn zámků, transakcí, writerů, schématu nebo UI. Seznam celé rodiny
zůstává shodný včetně membership polí, deduplikace, rolí a pořadí.
Zbytek dotčeného souboru byl pouze srovnán do povinného stylu.

## Charakterizace před a po

Zmrazená reference je `54013cdd:includes/family_portal.php`, uložená v
`tests/Support/FamilyPortalReadBaseline.php` a omezená na CLI.
Čtyři charakterizační testy před úpravou prošly na SQLite i MariaDB (78 assertions).
Nový rozpočet před úpravou selhal: **24 dotazů místo 4** u rodiny 20 osob.
Po změně: cílené testy rodiny/RSVP/papírové registrace **27 / 237 PASS**,
MariaDB charakterizace včetně rozpočtu **5 / 102 PASS**.

Pokryto: self/guardian proti payer/coach, pending/revoked/rejected, časové
hranice, cizí/neexistující/neplatná ID, aktivace/ověření účtu, membership archiv,
duplicity, sirotci na legacy schématu, DB kolace, chybějící tabulky, odvolání a
rollback na stejné PDO. Migrovaný profil přidává tři odvolání z druhé connection
před následným autocommit čtením. Nejde o race test writeru čekajícího na zámek.

## Migrovaný profil

`EVIDENCE_MARIADB_SMOKE_PORT=33426 php tests/Support/FamilyPortalReadProfile.php`
lokálně, CI standardní port 3306. `--before` je výhradně záznam původního kódu
bez nové výkonové brány; standardní běh v CI bránu vynucuje.

Náhodná localhost DB `w71_profile_*`, v finally odstraněná; checkout legacy
bootstrap + všech 140 migrací + čtyři existující legacy membership sloupce
chybějící v minimálním bootstrapu. FK zapnuté, nativní prepared statements,
ANALYZE TABLE před scénářem. Syntetické rodiny 0/1/5/20 osob, dalších 100 osob
jiného účtu; žádné produkční osobní údaje. DDL a EXPLAIN jsou v JSON.
Časy/paměť přes běžné PDO, instrumentace SQL samostatně; 3 warmupy a 15
střídavých vzorků. Vrácené řádky nejsou počet řádků prozkoumaných serverem.

[Před implementací](before.json), [přímé porovnání po implementaci](after.json),
MariaDB 10.4.32 / PHP 8.2.12. Následující čísla jsou párové mediány druhého běhu:

| Rodina / cíl | SQL před → po | Vrácené řádky včetně metadat | Čas ms před → po |
| --- | ---: | ---: | ---: |
| 0 / bez vazby | 4 → 4 | 3 → 3 | 3,55 → 3,25 |
| 1 / vlastní | 5 → 4 | 5 → 4 | 5,52 → 4,09 |
| 5 / vlastní | 9 → 4 | 13 → 4 | 7,63 → 3,54 |
| 20 / vlastní | 24 → 4 | 43 → 4 | 18,77 → 3,46 |
| 20 / cizí | 24 → 4 | 43 → 3 | 21,06 → 3,84 |

Tři řádky jsou metadata tabulek. Cílený dotaz tedy vrací nejvýše jeden datový
řádek, bez jména, narození nebo členských údajů. Přírůstek PHP špičky u 20 osob
139 912 → 43 760 B. SQL rozpočet se nezvyšuje s počtem členů rodiny.
Jde o profil konkrétní služby na syntetických datech, nikoli zrychlení celé
rodinné stránky nebo měření skutečné produkční distribuce.

## Zbývající W7

W7.1b: nejprve race charakterizace kontroly oprávnění před transakcí u RSVP
a náhrad, pak případná oprava. Volající mají zdrojově kontrolu před čekáním
na zámek; tento výkonový řez pořadí nemění. Plný rodinný přehled R02, žádosti
R03 a registrářské seznamy R04 vyžadují samostatné profily. Technické ověření
nenahrazuje lidské UAT a neaktivuje integrace/importy.
