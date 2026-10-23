# Navazující kontrola členské a sportovní agendy — 23. 10. 2026

Pouze pracovní kopie `evidencePavel-finance-b1-20261022`, syntetický B1
`127.0.0.1:33426` a `http://127.0.0.1:18086`. Žádný produkční kontakt,
nasazení, import skutečných osob ani odeslání zpráv.

## Potvrzené chyby a oprava

1. **R26 — odvolané oprávnění při uzavírání docházky.** Kontrola role
   proběhla před zámkem účtu. Při čekání na souběžnou transakci mohlo být
   dokončeno odebrání vedoucího sportu nebo deaktivace účtu a následné
   uzavření přesto vytvořilo novou revizi. Skutečný baseline test vytvořil
   jednu revizi v obou scénářích. Nová kontrola nejdříve zamkne aktivní účet
   a pak konkrétní platné přidělení role nebo superadministrátora.
2. **R25/R28/R29 — souběžné odebrání přidělení role.** Samotný zámek
   `treneri` nezamykal přidělení uvnitř `EXISTS`. Správa pracovních pozic
   mění přímo `staff_user_positions` / `staff_superadmins`. Dotčené guardy
   nyní používají samostatné locking reads platného přidělení: osobní přijetí,
   sportovní činnost, provozní případy včetně zdravotních podkladů a správa
   svěřených rozsahů trenérů.
3. **Navazující finanční kontrola na zadání rodičovského agenta.** Stejný
   problém byl nejprve reprodukován v `accountingLedgerActor`,
   `accountingAdjustmentActor` a `accountingExportActor`. Oprava pouze
   doplňuje zámek živého oprávnění; nemění účetní pravidla, částky ani doklady.
4. **Osobní dořešení nedoručené zprávy.** Stejnou závadu prokázal skutečný
   `clubEventNotificationResolveManually`: po souběžném odebrání oprávnění
   zapsal událost osobního předání. Oprava zamyká konkrétní roli obsluhy
   objednávek nebo explicitního superadministrátora. Worker se neaktivoval,
   nebyl volán odesílač a nevznikl žádný e-mail.

Čtení mimo transakci zachovává dosavadní chování. Mutace drží ověřený účet
a udělené oprávnění do konce transakce. Podpora explicitního superadministrátora
zůstává tam, kde existovala; nové oprávnění se nikomu nepřidává.

## Důkazy před opravou a po ní

| Test | Před opravou | Po opravě |
|---|---|---|
| Skutečné uzavření tréninku během odebrání role / deaktivace | 2× skutečný LOCK WAIT, pak nepovolená revize | 4 scénáře včetně přímého odebrání role a superadmina: LOCK WAIT, odmítnutí, 0 revizí |
| Transakční guardy osobního přijetí, provozní evidence a sportu | 5× přijaté staré oprávnění | Odmítnutí všech 5 scénářů |
| Správa svěřených rozsahů a 3 finanční guardy | 8× přijaté staré oprávnění | Odmítnutí všech 8 scénářů |
| Osobní dořešení nedoručené zprávy | 2× skutečný LOCK WAIT a poté nepovolená událost předání | 2× LOCK WAIT, odmítnutí, 0 událostí |
| Původní souběhy uzavření / rozvrhu / poslední náhrady / zneplatnění | Existující přejímka | 12 kontrol PASS, v každém ze 4 scénářů dva skutečné LOCK WAIT |

Společný finální test guardů má **15 scénářů PASS**, každý čeká na právě
rušené přidělení a po jeho commitu odmítá autorizaci; nevznikne chráněný zápis.
Je to cílený test transakčního kontraktu guardu se syntetickou tabulkou
`protected_writes`, nikoli tvrzení o 15 kompletních účetních pracovních postupech.
Poslední dva scénáře volají skutečné osobní dořešení zprávy a počítají jeho
auditní události. R26 test volá skutečnou `trainingClosureClose` a počítá její revize.

Původní baseline: [R26](r26-authorization-before.json),
[členské guardy](membership-authorization-before.json),
[rozšíření o finance a trenérské rozsahy](financial-authorization-before.json),
[osobní dořešení zprávy](notification-authorization-before.json).
Finální důkazy: [R26](r26-authorization-after.json),
[15 autorizačních scénářů](membership-authorization-after.json),
[původní souběhy](r26-r27-race.json).

Cílená PHPUnit regrese: **99 testů / 573 assertions PASS**:
`TrainingClosureTest|SportsActivityTest|MedicalClearanceTest|MemberAdmissionPolicyTest|OperationalCase|AccountingLedger|AccountingAdjustment|AccountingExport|CoachScope`.
Dále `OperationalDeliveryTest`: **7 testů / 71 assertions PASS**; dohromady
**106 testů / 644 assertions** bez překryvu těchto dvou běhů.
Přidaný unit test zachovává podporu explicitního superadministrátora a odmítá
deaktivovaný účet i odebraného superadministrátora bez nové revize.

HTTP regrese po změně: osobní přijetí **30**, docházka **25**, sportovní činnost
**23**, zdravotní podklady **22**, celkem **100 kontrol PASS**.
Viz [osobní přijetí](r25-http.json), [docházka](r26-http.json),
[sport](r28-http.json), [zdravotní podklady](r29-http.json).

## Opakování

Z kořene izolované pracovní kopie, pouze s běžícím vlastněným B1:

```powershell
& C:/xampp/php/php.exe tests/Support/TrainingClosureAuthorizationRace.php
& C:/xampp/php/php.exe tests/Support/MembershipAuthorizationMariaDbRace.php
& C:/xampp/php/php.exe tests/Support/TrainingClosureMariaDbRace.php
& C:/xampp/php/php.exe vendor/phpunit/phpunit/phpunit --filter 'TrainingClosureTest|SportsActivityTest|MedicalClearanceTest|MemberAdmissionPolicyTest|OperationalCase|AccountingLedger|AccountingAdjustment|AccountingExport|CoachScope'
& C:/xampp/php/php.exe vendor/phpunit/phpunit/phpunit tests/Integration/OperationalDeliveryTest.php
```

Oba nové race testy kontrolují syntetický descriptor, skutečný port i datadir;
vytvářejí jen nová náhodně pojmenovaná testovací schémata na této instanci.
Nová aplikační migrace není potřeba. Reálné klubové metodiky a účetní převzetí
zůstávají odložené dle společného předání.
