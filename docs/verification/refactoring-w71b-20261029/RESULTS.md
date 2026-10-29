# W7.1b — souběhová autorizace RSVP a náhrad

29. 10. 2026, výchozí `b1db19ae6dacb29ea6cfac65261176250d91e3f3`.
Pouze syntetická localhost data; žádné produkční datové mutace.

## RED → GREEN

[Před opravou](before.json): 24 skutečných souběhů na MariaDB 10.4.32,
15 selhání kontraktu. RSVP, rezervace i storno přijaly zápis po commitnutém
odebrání rodinné role, deaktivaci účtu, odebrání ověření e-mailu, ukončení
platnosti vazby i deaktivaci samostatného sportovního přístupu. Devět
pozitivních kontrol (nezměněná práva, rollback revokace, samostatný sportovec
po odebrání rodičovské vazby) prošlo.

[Po opravě](after.json): stejných 24 scénářů PASS a dalších osm PASS pro
držení rodičovské role / aktivity účtu / ověření e-mailu / sportovní identity
do commit i rollback. Celkem **32/32 PASS**. Odmítnutí zachovává celé řádky
RSVP, kreditů, rezervací a jejich doménových auditů; transakce se uzavře.
Audit samotné autorizované revokace není součástí doménového snapshotu.

## Co test skutečně prokazuje

`tests/Support/FamilyWriteAuthorizationRace.php` vytváří náhodnou localhost
DB, aplikuje celý migrační katalog, ponechává FK a používá nativní prepares.
Minimální legacy bootstrap doplňuje existující pole `stav_clenstvi`,
`nazev` a `cas_od`; nejde o novou migraci. Dva procesy a třetí connection pro
revokaci čekají na ověřený `information_schema.INNODB_TRX: LOCK WAIT`,
nikoli na odhadovanou prodlevu. DB, pracovní procesy a bariéry se uklízejí.

RSVP čeká na pozdějším zámku existující odpovědi, po vytvoření konzistentního
snapshotu. Náhrada čeká na slotu, storno na rezervaci. Revokace se potvrdí
před uvolněním doménového zámku. Rodinná revokace a deaktivace sportovce
používají skutečné aplikační služby. Opačné pořadí testuje přímo nový helper:
revoker čeká, dokud vlastník oprávnění neukončí transakci.

## Implementace a hranice

`participantWriteCanActFor` vyžaduje otevřenou transakci a provede aktuální
`SELECT ... FOR UPDATE`. Rodinný predikát sdílí s čtecím helperem W7.1a;
sportovní účet má vlastní namespace a aktivní vazbu na stejnou osobu.
Použití je po existujících doménových zámcích a před první mutací ve třech
službách. Zámky oprávnění zůstávají do commit/rollback. Dosavadní časné
kontroly a chybové zprávy zůstávají. Čtecí helpery, UI, schéma a integrační
flags se nemění. Běžný snapshot SELECT by zejména u RSVP nestačil.

Pořadí doménových zámků se nemění. Revokace rodiny zamyká vazbu, tokeny a
svůj audit; deaktivace sportovce jeho účet. Nový helper přidává zámky řádků
účtu, vazby a osoby až za doménové zámky. Případný databázový konflikt nadále
vede k rollback, nikoli k obejití autorizace. Nejde o audit všech writerů,
všech session mechanismů ani odstranění všech historických deadlocků.
Časová platnost se posuzuje při zamykající kontrole, ne retroaktivně v commitu.

Povinný styl přeformátoval oba starší soubory. Nezávislé porovnání AST přes
PHP-Parser, s normalizací konkatenace literálů a vyjmutím tří nových guardů,
potvrdilo shodnou logiku všech 13 RSVP a 34 makeup funkcí.

## Brány

- Dotčené PHPUnit: **30 testů / 263 assertions PASS**.
- Nové čtyři testy pokrývají transakční kontrakt, namespace, predikáty,
  časové hranice, rollback a chybějící tabulky; čtecí helper dál nezamyká.
- Styl změněných PHP, `git diff --check` a dokumentační kontrola PASS.
- Kompletní sada, PHPStan a MariaDB 10.3/11.4 CI budou doplněny před merge.

[Plán](../../REFACTORING_W71B_2026-10-29.md).
