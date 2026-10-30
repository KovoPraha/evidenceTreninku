# W11.1 — převzetí front a přenosné provozní kontrakty

Základ W10.4 `8ccf9d41fe336a1211bd8b992f46dae6b4d02fa9`.
Pouze vlastní náhodné localhost databáze, kompletní migrační katalog,
FK zapnuté; lokální schránka nebo testovací callback, žádný SMTP či produkční worker.

## Nález a oprava

Původní `OperationalDeliveryMariaDbRace.php` vyžadoval starý konkrétní B1
adresář, uživatele a trvalou DB. Přenos na kompletní schéma odhalil při dvou
workerech `SQLSTATE[40001] / 1213` v UPDATE převzetí klubového oznámení.
Původní skript s minimální tabulkou bez frontového indexu tento průchod nepokrýval.
Přesný pozorovaný stack je v [RED záznamu](deadlock-red.txt).

Pět claim funkcí nyní používá společný omezený retry: nejvýše tři pokusy,
jen MySQL/MariaDB deadlock 1213 se SQLSTATE 40001 a ukončenou transakcí.
Samotné původní claim operace a jejich rollback se nemění. Timeout, ztráta
spojení, jiná chyba nebo dosud aktivní transakce se neopakují. Callback
nezahrnuje autorizaci příjemce, transport ani dokončení; jejich nejasný
výsledek zůstává k ruční kontrole. Žádná změna scheduleru či transportu.
Formátování pěti legacy souborů má shodný normalizovaný AST a SQL/literály
s uloženými soubory `*-before-format.txt` (již obsahují zamýšlené wrappery).

## Důkazy

- [21 MariaDB kontrol](delivery-race.json): soupeřící procesy, jediný počáteční
  lokální artefakt a pokus, opakované zpracování, nejasná výjimka, skutečný
  exit workeru před a po transportu, stale claim a známé odmítnutí/backoff.
- [Profil front](queue-profile.json): 0/100/10 000 čekajících klubových zpráv
  plus stejně velká historie auth fronty. Stav má devět prepared/query SQL
  a nejvýše pět vrácených řádků; omezený drain dvaceti úloh 74 těchto SQL
  a dvacet řádků. Instrumentace nepočítá `exec` a řízení transakcí;
  tyto počty nejsou celkovým počtem všech DB příkazů. Status má 2 warmups /
  10 vzorků běžného PDO; drain je jediné instrumentované měření.
- [Cílená sada](targeted.txt): 36 testů / 301 assertions; zahrnuje šifrovaný
  auth outbox, read-only diagnostiku, rollback producenta, revokaci vazeb,
  rodinné souhrny, upomínky a soupiskové příjemce. Nový unit kontrakt
  vynucuje hranici retry; MariaDB souběh zde přímo prokazuje klubovou frontu.
- [PHPUnit](phpunit.txt): Windows 1855 testů / 37692 assertions, tři POSIX
  skipy. [PHPStan](phpstan.txt) a styl PASS; finální CI ověří Linux
  a nové skripty na obou MariaDB 10.3/11.4 před přijetím.

Status agreguje historii lineárně v databázi; při měřených 10 000 úlohách
není důvod zavádět cache. Není to průkaz doručení do skutečné schránky.
Oddělené doménové claim podmínky zůstávají zachované. Další W11.2 prověří
opakovaný výpočet celého publika pro jediného soupiskového příjemce;
případná optimalizace musí prokázat stejné čerstvé rozhodnutí a revokaci.

Jedna stará zdrojová aserce po formátování odmítla zalomení mezi argumenty
`'trainer'` a `$actorTrainerId`. Stejný kontrakt nyní toleruje whitespace;
žádná kontrola role/CSRF ani behaviorální test nebyly odstraněny.
