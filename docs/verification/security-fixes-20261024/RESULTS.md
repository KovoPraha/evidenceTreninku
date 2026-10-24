# Opravy bezpečnostního auditu — 24. 10. 2026

**Všechny čtyři nálezy opraveny lokálně; produkce nebyla nasazena ani změněna.**
Základ: `origin/main` / `f2626b86b714ffd1ec90f572170dac839857dfcc`, ověřen po fetch.
Větev: `codex/security-audit-fixes-20261024`.
Checkout: `C:\Users\Marek\Documents\Codex\evidencePavel-security-fixes-20261024`.
Původní checkout a jeho rozpracovaný `kis_rosters_admin.php` zůstaly zachované.
Bez migrací, změn konfigurace, aktivace integrací, push, merge a deploye.

## Opravy

1. **SEC-01: aktuální oprávnění před první akcí.** U všech 27 nalezených vstupních
   bodů se `db.php` s `auth_session_validate()` načte před `canAccess()`. Patří sem
   formuláře a zápisy závodů, plánovač, lekce, sportoviště, přehledy, oznámení,
   import a bankovní nastavení. Samotné požadované oprávnění, CSRF, vlastnictví
   záznamu a pravidla pracovních pozic se nemění. Kontrakt je vysvětlen také u
   `canAccess()` v `includes/funkce.php`.
2. **SEC-02: textové buňky UCI exportu.** `export_uci.php` zapisuje jména,
   příjmení a UCI identifikátory přes `setCellValueExplicit(..., TYPE_STRING)`
   pro hlavní jezdce, náhradníky i všechny řádky doprovodu. Řetězec `=1+1`
   zůstane doslovným textem, identifikátor zachová počáteční nuly. Ostatní
   vzorce původní šablony zůstávají zachované.
3. **SEC-03: bezpečné JSON v HTML.** Obě serializace účastníků v `formular.php`
   používají `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`.
   Uložené `</script>` už nemůže ukončit skript. Původní text, diakritika,
   uvozovky a ampersand se po JSON dekódování zachovají.
4. **SEC-04: přehled skupin pod sportovním vedoucím.** `list.php` má vlastníka
   `sports_lead`, stejně jako `prehled_skupin.php`. Kontrola proběhne centrálně
   v DB bootstrapu před dotazem na skupiny. Výjimka této stránky z testu
   inventury interních tras byla odstraněna.

## Regresní důkazy

Nový soubor: `tests/Integration/SecurityAuditRegressionTest.php` (8 testových
případů). Pouze syntetická data, paměťová SQLite, dočasné soubory a loopback HTTP.

- Skutečná POST obsluha zrušení vlastní lekce: stará session má právo, databáze
  je již zpřísněná. Po opravě první POST ponechá lekci, rezervaci i audit beze
  změny. Pozitivní protějšek s ponechaným právem lekci zruší a vytvoří audit.
  Nahrazena je pouze hranice připojení MySQL; autorizace, CSRF, SQL, vlastnictví
  a transakce používají skutečný aplikační kód.
- Plošný test všech kořenových stránek s přímým DB bootstrapem a `canAccess()`
  odmítne kontrolu práva před obnovením session.
- Skutečný blok UCI exportu naplní sešit, uloží XLSX a znovu jej načte.
  **67 buněk** pro 11 jezdců a tři řádky doprovodu zachová text i typ.
  Zkoušeny jsou vzorce, `+`, `-`, `@`, počáteční nuly a česká jména.
- Oba skutečné JSON výrazy šablony zachovají vstup a HTML parser nevytvoří
  vložený element mimo skript.
- Skutečný centrální guard v PHP web SAPI: aktivní `sports_lead` projde (200),
  ostatních sedm pozic dostane 403, a to i u účtu oprávněného přepínat všechny
  pozice. Jde o izolovaný HTTP test guardu pro trasu `list.php`, ne o načtení
  produkčních skupin či přihlášenou produkční přejímku.

[Běh před opravou](regressions-before.txt): **7 případů, 6 očekávaných selhání**;
pozitivní zrušení lekce prošlo. Neoprávněný první POST skutečně zrušil lekci,
XLSX obsahoval vzorec, obě serializace vložily HTML a `list.php` neměl vlastníka.
HTTP test byl doplněn následně.

[Cílené kontroly po opravě](targeted-after.txt): **38 testů / 2 017 assertions PASS**,
včetně existujících testů session, registru pozic, soupisek, lekcí a exportů.
[Kontrola syntaxe](lint.json): **1 088 PHP/PHP3 souborů PASS**.

## Plná sada a meze ověření

[Plná sada na Windows / PHP 8.2.12](phpunit.txt): **1 685 testů / 33 580 assertions,
1 failure, 3 skipped**, čas 2:57. Jediné selhání je stejný scanner
`GlobalFunctionAvailabilityTest` kvůli chybějící `posix_geteuid` jako před
opravami; tři přeskočené testy se týkají symlinků. Aplikační volání POSIX je
chráněné `function_exists()`. Tento nesouvisející scanner ani bezpečnostní
testy nebyly oslabeny. Plnou lokální sadu proto neoznačujeme za zelenou;
žádné nové selhání proti auditovanému základu nepřibylo.

Linux CI nových změn nebylo spuštěno, protože větev nebyla odeslána. Starší zelené
CI základního commitu není důkazem těchto nových změn. Neproběhl nový produkční
test, čtení živé databáze, zaslání zpráv, skutečná objednávka ani platba.

## Opakování

V uvedeném checkoutu po `composer install --no-interaction --prefer-dist`:

```powershell
php vendor/bin/phpunit --configuration phpunit.xml.dist tests/Integration/SecurityAuditRegressionTest.php
php vendor/bin/phpunit --configuration phpunit.xml.dist
git diff --check
```

Další krok je revize změn. Nasazení vyžaduje samostatné zadání vlastníka;
aktuální pokyn výslovně říká zatím nenasazovat.
