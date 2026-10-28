# Druhá vlna — programy ve veřejném katalogu a navigaci

28. 10. 2026, [PR #211](https://github.com/KovoPraha/evidenceTreninku/pull/211).
Výchozí main `4215eac93dd207142a16dfef57cda1464717cba3`; jeho aplikační kód
odpovídá nasazenému `20ab8637`. Implementační commit `1463da73bbb1e49816eb632fa536a24d1c7f91c2`.
Při dokončení implementace produkce nebyla měněna; následující měření používají
syntetická data. Následné [produkční vydání a měření](../refactoring-wave2-release-20261028/RESULTS.md)
dokládá nasazení sloučeného PR #211 dne 28. 10. 2026.

## Zjištění a změna

Menu pro 50 programů provedlo 2 608 SQL dotazů, z toho 1 801 dotazů na schéma.
Stejná prodejnost se navíc opakovaně počítala při načtení a filtrování výsledku.
Nový `ClubProgramReadContext` žije pouze po dobu jedné veřejné čtecí operace
a sdílí metadata, nabídky, prodejnost a zveřejněné podmínky. Nová operace
vytváří nový kontext; objekt se neukládá do session, statické nebo trvalé cache.

Kapacita, překryv období, placená registrace čekající na identitu, přednost
podmínek konkrétní nabídky i prodejní okna se dál počítají v původních službách.
Zamykající čtení předaný kontext vždy ignoruje. Checkout si jej nevytváří
ani nepřebírá z katalogu. Cizí PDO je odmítnuto. Žádná nová migrace,
závislost, změna členských cen nebo aktivace integrací.

## Výkon a shoda výstupů

MariaDB 10.4.32 na Windows, stejná izolovaná fixture, 1/10/50 programů,
3 profily, vždy 5 zahřívacích a 30 měřených průchodů. Přidány pouze fixture
indexy odpovídající vztahovým klíčům; aplikace žádný nový index nepotřebuje.
[Před](mariadb-before.json), [po](mariadb-after.json), [porovnání](mariadb-comparison.json).
Všech **9 výstupních SHA-256 je shodných**. Počty pro 50 programů:

| Čtecí operace | SQL před → po | Dotazy na schéma před → po |
| --- | ---: | ---: |
| Navigace | 2 608 → 417 | 1 801 → 10 |
| Katalog | 2 607 → 416 | 1 802 → 11 |
| Detail včetně navigace | 2 639 → 439 | 1 821 → 21 |

Původní regresní test selhal na limitu 25 metadatových dotazů hodnotou 1 801;
po změně prochází. Kapacitní dotazy se stále provádějí pro jednotlivé nabídky:
celkový počet SQL není konstantní. `EXPLAIN`, všechny vzorky a peak memory
jsou v JSON. CI stejný kontrakt a ignorování starého kontextu při zamčení
ověřuje také na MariaDB **10.3 a 11.4**.

HTTP: plné schéma lokální aplikace, 71 aktivních produktů (20 programů),
stejné PHP nastavení bez OPcache, síťové a e-mailové transporty zablokované.
Párové střídání před/po, 5 zahřátí + 30 měřených požadavků pro každou verzi:

| Celá stránka | Medián před → po | p95 před → po |
| --- | ---: | ---: |
| Detail zboží | 7,31 → 0,53 s | 9,34 → 0,81 s |
| Detail programu | 6,53 → 0,86 s | 7,98 → 1,16 s |
| Katalog | 8,41 → 2,49 s | 9,25 → 2,69 s |

[Vzorky, shodné menu a shodný viditelný text](http-benchmark.json),
[měřicí skript](http-benchmark-source.txt), [příprava fixture](http-fixture-source.txt)
a [souhrn fixture](http-fixture.json). HTML se porovnává přes menu a viditelný
text po odstranění script/style a sjednocení whitespace; nejde o byte-for-byte
shodu nonce, CSRF tokenů a celé HTML odpovědi.

**Metodický limit:** místní stroj je sdílený. Část základního MariaDB měření
probíhala souběžně s HTTP, browserem a kontrolami; izolované časy funkcí proto
nejsou čistý hardwarový benchmark. HTTP obě verze střídá pod stejnou zátěží.
Rozhodující jsou SQL počty, shodné výsledky a funkční kontrakty. Tato čísla
neprokazují konkrétní procento zrychlení dosud nezměněné produkce.

## Funkční a bezpečnostní ověření

- Nové testy: 7 testů / 40 assertions. Sdílené fyzické skupiny, deduplikace
  osob, zaplacená registrace čekající na identitu a odmítnutý požadavek,
  odvolané/konceptové podmínky, kapacita a stav programu, hranice prodeje
  a expirace rezervace, nové schéma mezi operacemi, různé PDO a zamykání.
- [Linux Testy 40710578357](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40710578357):
  **1 764 testů / 35 753 assertions**, bez skipů; samostatně POSIX 3/45.
  MariaDB 10.3/11.4, 14 JavaScript testů, Composer audit a anonymní sweep
  (32 veřejných, 2 CLI-only, 237 chráněných cest, 0 chyb) PASS.
- [PHP quality 40710578667](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40710578667)
  a [dokumentace 40710578583](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40710578583), PASS.
  Lokálně PHPStan level 5, jeho negativní kontrakt, Composer validace,
  style a diff check PASS. Windows plná sada měla 3 známé POSIX skipy a
  odhalila dva whitespace wiring testy; po jejich úpravě cílené 2/15 PASS
  a úplné Linux CI výše potvrzuje konečný aplikační commit.
- [HTTP bezpečnostní kontroly](http-security.json), 7/7 PASS:
  anonymní POST a chybný CSRF neprovedou změnu košíku; přihlášený rodič má
  členskou cenu a její nárok zanikne ve stejné session ihned po revokaci vazby.
  [Reprodukce](http-security-source.txt) obnovuje a odvolává jedinou přesně
  určenou syntetickou vazbu v databázi LOCALHOST. Nevznikla objednávka ani platba.
- [Browser průchod](browser-checks.json): katalog, detail zboží, rychlý nákup,
  programová kategorie a detail s kapacitou, přihlášený rodič 80 Kč → po revokaci
  100 Kč, zájmový kurz bez objednávky/platby. Zjištěna pouze lokální favicon 404
  a doporučení autocomplete u přihlášení; žádná JavaScript výjimka aplikace.

## Formátování a přezkoumatelnost

Repozitář vyžaduje PSR-12, závorky a maximální délku řádku v celém změněném PHP
souboru. Dva starší soubory proto mají rozsáhlejší formátovací diff.
[AST kontrola](ast-result.json) porovnává 65 funkcí proti výchozímu main:
změnilo se pouze 7 explicitně uvedených funkcí programu a 4 funkcí podmínek,
ostatní mají shodný normalizovaný syntaktický strom. Samostatná kontrola
před/po formátování těchto souborů potvrdila totožný AST. [Zdroj kontroly](ast-check-source.txt).
Dva wiring testy byly upraveny pouze tak, aby tolerovaly whitespace a zachovaly
kontrolu transakce a pořadí zamykání.

## Reprodukce a další krok

```powershell
$env:EVIDENCE_MARIADB_SMOKE_PORT='33426'
php tests/Support/ProgramStorefrontMariaDbSmoke.php C:/cesta/k/baseline --baseline
php tests/Support/ProgramStorefrontMariaDbSmoke.php
php vendor/bin/phpunit tests/Integration/ProgramStorefrontReadCompatibilityTest.php
php vendor/bin/phpunit tests/Integration/ProgramStorefrontReadPerformanceTest.php
```

MariaDB skript povoluje pouze localhost, zakládá náhodnou vlastní databázi
a odstraňuje ji ve `finally`. HTTP skripty jsou svázané s konkrétním syntetickým
prostředím (port 33426, DB evidence_b1, JE_LOKALNE); nejsou produkční diagnostika.
Baseline HTTP běží na 18087, nová verze na 18086.

Po ověření byly obě vlastní HTTP instance a MariaDB na 33426 zastaveny,
browser zavřen. Syntetická data zůstávají mimo webroot; pracovní browser
artefakty jsou v `C:\Users\Marek\Documents\Codex\evidencePavel-wave2-local-artifacts-20261028`.
Původní checkout s WIP nebyl změněn.

Další samostatný kandidát: dávkové načítání prezentačních informací programů
v samotné stránce katalogu; katalog po této vlně ještě není konstantní čtení.
Členské ceny a osobní oprávnění nebyly optimalizovány. Ověření finálního headu,
CI a rozsahu produkčního vydání je v navazujícím protokolu výše; technická
optimalizace nenahrazuje lidské a účetní UAT.
