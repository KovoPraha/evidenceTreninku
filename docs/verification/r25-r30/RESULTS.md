# R25–R30 — lokální přejímka

Ověřeno 23. 10. 2026 v izolované pracovní kopii
`C:\xampp\htdocs\evidencePavel-finance-b1-20261022`, větev
`codex/membership-sports-r25-r30-20261023`.
**Bez produkčních změn, push, merge, nasazení a externího doručování.**

## Výsledek

- Společná PHPUnit regrese: výsledek a počty v [phpunit.json](phpunit.json).
- **100 skutečných HTTP přejímacích kontrol PASS:** přijetí/příspěvky 30,
  tréninky/náhrady 25, sportovní činnost 23, prohlídky/společné případy 22.
- **31 kontrol skutečného souběhu MariaDB PASS:** přijetí 5, tréninky/náhrady
  12, sportovní verze 6, prohlídky 8. Samostatné procesy prokazatelně čekaly
  na zámcích před uvolněním bariéry. Nejde o sekvenční simulaci.
- **67 změněných PHP souborů bez syntaktické chyby.**
- **130 migrací aktuálních**, bez čekajících migrací a bez změny již
  aplikovaných otisků. Pět nových migrací pouze v syntetické databázi.
- **Obnova PASS:** 304 tabulek, 2 triggery, všech 16 nových vlastněných
  tabulek; 72 soukromých souborů obnoveno ze samostatného ZIPu a 51
  databázových odkazů ověřeno vůči obnoveným souborům pomocí SHA-256.
- V obnovené kopii souhlasí 18 skladových projekcí, čtyři ukazatele na
  neměnné verze docházky, dva otisky podkladů sportovní činnosti a dvě
  přihlášky bez účtu s doloženým plátcem. Tyto počty popisují konkrétní
  syntetický snímek, nikoli produkční počty osob nebo dokumentů.
- `git diff --check` bez chyb. Původní pracovní kopie a její cizí WIP zůstaly
  mimo tento balík.

## Důkazy

| Oblast | Protokol |
|---|---|
| Společná regrese | [phpunit.json](phpunit.json) |
| Osobní přijetí, offline plátce, příspěvky | [admissions-http.json](admissions-http.json), [admissions-race.json](admissions-race.json) |
| Docházka, kolize, náhrady, zneplatnění | [r26-r27-http.json](r26-r27-http.json), [r26-r27-mariadb-race.json](r26-r27-mariadb-race.json) |
| Akce, skutečná činnost, neměnný export | [sports-http.json](sports-http.json), [sports-race.json](sports-race.json) |
| Soukromé prohlídky, platnost, odvolání přístupů | [medical-http.json](medical-http.json), [medical-race.json](medical-race.json) |
| Záloha a skutečná obnova DB/souborů | [backup-restore.json](backup-restore.json) |
| Schéma a syntaxe | [migrations.json](migrations.json), [php-lint.json](php-lint.json) |

Plný JUnit report a lokální artefakty zůstávají pod
`%LOCALAPPDATA%\EvidencePavelB1\finance-b1-20261022`.

## Co přejímka ověřila

Povinné a verzované papírové podklady, výslovný plátce mezi více rodiči,
přijetí bez online účtu, ochranu ceny a posledního místa, opakovaný požadavek,
aktivaci účasti až kanonickou platbou, schválenou částku měsíce a ochranu VS.

Docházku po skutečném konci, oddělení od RSVP a očekávané soupisky, stale
formuláře, neměnnou původní verzi, opravu po uzavřeném odměnovém období,
zákaz raw smazání, přímé URL mimo svěřený rozsah včetně osob v měřeních,
kolize přes AJAX i běžné formuláře a osobní omluvu zapsanou dodatečně.

Závodní nominaci proti skutečnému startu, změny výsledku a zdrojové vazby,
shodné ověřené součty, nevydávání neznámých historických údajů za skutečnost,
neměnný export a viditelný rozdíl po následné opravě.

Nahrání skutečného soukromého souboru přes multipart HTTP, oddělené ověření,
inklusivní poslední den platnosti, historii nahrazení, mezery, odvolání
historického dokladu, souběžné ověření/odvolání, odebrání role a rodinné vazby
v již otevřené session. Trenér ani rodina nezískají sken přímým odkazem.
Případ s odpovědnou osobou nelze uzavřít místo vyřešení zdrojové agendy.

Nezávislý review vedl k opravě vlastních rezervací při editaci plánu,
zastaralého čtení po souběžném zneplatnění lekce, přepisování právě
rezervované náhrady, kontroly odstraněného původního účastníka a přístupu
k osobám z měření. U prohlídek opravil historickou platnost a transakční
kontrolu oprávnění. Opravy mají regresní testy.

## Opakování pouze v označeném sandboxu

```powershell
& C:/xampp/php/php.exe vendor/phpunit/phpunit/phpunit
& C:/xampp/php/php.exe tests/Support/MemberAdmissionHttpAcceptance.php
& C:/xampp/php/php.exe tests/Support/TrainingClosureHttpAcceptance.php
& C:/xampp/php/php.exe tests/Support/SportsActivityHttpAcceptance.php
& C:/xampp/php/php.exe tests/Support/MedicalClearanceHttpAcceptance.php
& C:/xampp/php/php.exe tests/Support/MemberAdmissionMariaDbRace.php
& C:/xampp/php/php.exe tests/Support/TrainingClosureMariaDbRace.php
& C:/xampp/php/php.exe tests/Support/SportsActivityMariaDbRace.php
& C:/xampp/php/php.exe tests/Support/MedicalClearanceMariaDbRace.php
```

HTTP a souběhové skripty kontrolují konkrétní lokální instanci a používají
syntetické osoby a adresy `example.invalid`. Nové souběhové a obnovovací
databáze jsou výhradně na ověřené instanci portu 33426.

Obnova `tests/Support/MembershipSportsBackupRestore.php` vyžaduje předem
vytvořenou zálohu s kontraktem `2026-10-23.1` v adresáři `backups-r25-r30`.
Vytváří nový cíl a omezeného obnovovacího uživatele, původní schéma nemaže.
Soukromé soubory obnovuje do samostatného adresáře a kontroluje vazby z DB.

## Meze výsledku

Jde o implementační a syntetickou přejímku, nikoli uživatelské převzetí
skutečným oddílem. Nebyly importovány staré tréninky ani zdravotní údaje,
hromadně potvrzena docházka, schváleny reálné měsíční sazby či zdravotní
politiky ani zapnuty plánovače a odesílání.

Přestup používá navazující lidské kroky a existující finanční opravy,
nikoli automatický přesun peněz. Hromadné dohody příspěvků používají
ověřené účty; osoby bez účtu nadále obslouží individuální předpis.
Upozornění na prohlídky je pracovní případ a stav v portálu; automatická
e-mailová rozesílka ani retenční výmaz nebyly zavedeny jako zapnutý provoz.
Výplaty a sazby odměn patří R31–R34, úplné účetní předání R36 a skutečný
převod/pilot do etapy 6.

[Úplný rozsah a postupy](../../MEMBERSHIP_SPORTS_R25_R30_IMPLEMENTATION.md)
· [podrobnosti osobní obsluhy](ADMISSIONS_SCOPE.md)
· [podrobnosti tréninků a náhrad](R26_R27_SCOPE.md)
