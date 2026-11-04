# Sedmý autonomní funkční průchod — 4. 11. 2026

**F08 potvrzena a opravená místně: filtr úrovní zapojení ztrácel osoby za první tisícovkou.**
Reprodukce v Chrome i HTTP; před opravou regresní testy FAIL, po opravě PASS.
Sportovní činnost a neměnné podklady prošly bez nového nálezu.
Oprava není sloučená ani nasazená. Heslo, použitý obnovovací odkaz a T07 SKIPPED.
Výchozí main `b20fb2a5b414c9f344f7b514839a0c82586fcb0c`,
větev `codex/functional-uat-round7-20261104`, časová zóna Europe/Prague.

Následné sloučení PR #297, bezpečnostní odblokování PR #298 a úspěšné nasazení dokládá
[samostatný protokol vydání](../functional-round7-release-20261104/RESULTS.md). Výše uvedený stav nenasazení je původní snapshot.

## Prostředí

Samostatné nové worktree, vlastní Apache/PHP 8.2.12 na 127.0.0.1:18422 a MariaDB 10.4.32
na 127.0.0.1:3322. Znovu použitá výhradně syntetická DB functional_round4 z dřívějších vln;
její původ je prázdné verzované schéma, migrace a syntetický seed, nikoli produkční dump.
Nová konfigurace/session/soukromé soubory patří této vlně. Webové mail/cURL transporty
blokované, platební integrace vypnuté. Testovací pomocníci hlídají checkout, descriptor,
host, jméno DB, port a skutečný datadir. Produkce se v této vlně neotevírala ani neměnila.
Původní WIP `kis_rosters_admin.php` a ostatní instance nedotčené.

## F08 — filtrování až po LIMIT 1000

Původní stránka nejprve načetla prvních 1000 nearchivních osob podle jména a teprve potom
v PHP filtrovala úroveň. Neměla stránkování ani upozornění na neúplnost. Kategorie proto
mohla vrátit „Filtru neodpovídá žádná osoba“, přestože odpovídající osoba existovala.
Součty rovněž popisovaly jen oříznutý seznam.

Fixture R7-b8058c obsahuje **1002** osob. Poslední #1022 má platnou kroužkovou soupisku #16.
Široké hledání + Kroužek / kurz před opravou v Chrome i HTTP ukázalo prázdný výsledek;
přesné hledání téže osoby ji našlo. [HTTP před](baseline.json), [snímek před](engagement-before.png).

Nový read model filtruje úroveň v DB **před stránkováním**, zachovává pravidla odvození
z platných soupisek a programů i přednost závodní soupisky. Důvody počítá existující
funkce jen pro aktuálních nejvýše **25 osob**. Počty zahrnují všechny odpovídající osoby.
Stránka ukazuje celkový počet, aktuální stránku a odkazy zachovávající hledání i úroveň.
Filtr resetuje stránku, neplatná stránka se omezí na platné rozmezí. Úroveň se nikam neukládá;
writer, oprávnění, schéma a data se nemění. Šablona je rozdělená do kratších řádků kvůli stylu.
PHPStan baseline se **zmenšila o jeden výskyt** po odstranění jednoho použití bootstrap PDO;
nevznikla nová výjimka ani potlačení chyby.

Po opravě se #1022 zobrazí s vysvětlením kroužkové soupisky. Přehled má 41 stránek,
25 řádků na běžné stránce a dva na poslední. Všech 1002 osob bylo přes HTTP skutečně
prošlo jednou, bez duplicit a vynechání. Chrome ověřil filtr, celkový počet a přechod na stránku 2.
[HTTP po](after.json), [browser](browser-engagement.json), [snímek po](engagement-after.png).

Pět nových regresních případů: hranice 1000, úplné součty a stránkování, konec platnosti
včetně a přednost závodní soupisky, archivace a program bez soupisky s platností od/do.
Čtyři případy nad extrahovaným původním oříznutým dotazem před opravou selhaly;
finální cílená sada včetně sportovní činnosti **23 testů / 86 assertions PASS**.

## Další ověřené scénáře

| Oblast | Skutečně provedené ověření |
|---|---|
| Úrovně zapojení | **16 scénářů, 52 HTTP požadavků PASS**: úplný filtr, počty, všech 41 stránek, zachování parametrů, ořez extrémní stránky, prázdný výsledek, neplatné volitelné parametry bez 500, escapování, anonymní a cizí pracovní role odmítnuté. Hash všech 1002 osob, počet veřejných účtů a online odpovědí beze změny. [Výsledek](engagement-http.json). |
| Sportovní činnost | **23 HTTP scénářů PASS**: role/rozsah/CSRF, nové potvrzení, nominace, přijetí a registrace bez falešného startu, odmítnutí budoucího startu a DNS jako startu, doložené DNF, kanonický CSV export, neměnný a deduplikovaný podklad, odmítnutí zastaralé opravy, historie po zrušení, původní export zachovaný a odvolané oprávnění účinné. [Výsledek](sports-http.json). |
| Sportovní browser | Účet round4-sports_lead otevřel syntetickou osobu #1023 / akci #6. Aktuální oprava má nula startů a starý podklad #1 je označen „ZMĚNA K POSOUZENÍ“. Skutečný download přes Chrome stáhl původní CSV stále s DNF. [Browser](browser-sports.json), [CSV](frozen-statement.csv), [snímek](sports-statement.png). |

Browser používal existující místní účty round4-registrar a round4-sports_lead,
bez superadmin kontextu. Zápisy sportovní fixture provedly samostatné vlastní HTTP relace;
browser pouze četl a stahoval. Přepnutí místních účtů není T07 ani důkaz izolace dvou přihlášení.
Nový runtime nález ve sportovní činnosti se nepotvrdil.

## Validace a předání

- Úplná místní sada **1982 testů / 38387 assertions**, bez selhání; **3 Windows skipy**.
  Finální SQL sjednocení vazeb programů navíc pokrývá cílená sada 23/86 a opakované HTTP 16/52.
  Rozhodující pro přesný předávaný head je také nové Linux CI.
- PHPStan a kontrola stylu PASS, Composer validate --strict PASS; baseline 2254 → 2253 nálezů.
- Před/po a strojový souhrn: [verification.json](verification.json). Pomocné scénáře
  [fixture](fixture.php.txt), [HTTP prefix](http-prefix.php.txt), [adresář](engagement-http.php.txt),
  [sportovní evidence](sports-http.php.txt) jsou archivované jako neexekutabilní dokumentace.
- Snímky vizuálně zkontrolované, obsahují jen vlastní syntetická data; žádné produkční kontakty,
  zdravotní údaje, cookies, CSRF nebo přihlašovací údaje se neukládaly do Gitu.
- Pomocná browser karta zavřená, vlastní služby vypnuté. MariaDB doložila Normal shutdown
  a Shutdown complete; data zůstávají zachovaná. Prohlížeč ještě zamykal vlastní staženou kopii CSV
  ve složce downloads; důkaz je bezpečně zkopírovaný sem, zámek se násilně neodstraňoval.
- Živé aplikační vydání zůstává `7e7a6364a3527bce1c6ec4b789468bd716f28fb4`
  (deploy 41870966110). Toto není produkční přejímka F08 ani úplné UAT rolí;
  E8, poslední synchronizace a lidská/účetní přejímka zůstávají otevřené.
