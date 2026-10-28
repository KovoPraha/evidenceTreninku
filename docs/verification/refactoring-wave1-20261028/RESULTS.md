# První vlna refaktoringu — příprava, implementace a důkazy

Datum: 28. 10. 2026 (Europe/Prague). Základ:
`fa387e5302f54ca901f46233322bc6fb92a10eb9`.

## Skutečně provedeno

| Kontrola | Výsledek |
| --- | --- |
| Fetch a izolace | Nové worktree mimo webroot; původní checkout a WIP zachovány. |
| Composer install z locku | Úspěch, 92 instalovaných balíčků v novém worktree, bez změny locku. |
| `composer validate --strict` | PASS. |
| PHPUnit | **1 748 testů / 35 544 assertions, 0 selhání, 3 skipped** na PHP 8.2.12 / Windows; 03:29, 98 MB. [Log](phpunit.txt). |
| PHPStan level 5 | **0 chyb nad existující baseline**; nejde o odstranění 2 413 starých potlačených výskytů. [Log](phpstan.txt). |
| Dokumentační kontrola | `php tools/check-current-docs.php`: 40 řádků stavu, 172 lokálních odkazů, 69 archivních bloků, 0 chyb. |
| Odkazy přípravy a rozcestníku | 86 lokálních odkazů ověřeno, 0 chyb; opraven původní odkaz na neexistující pravidla shody osob. |
| Benchmark | 16 syntetických scénářů, každý třikrát; nový PDO pro každý vzorek. [Výsledky](storefront-baseline.json). |
| Syntaxe benchmarku | `php -l .../benchmark-source.txt`: PASS. |
| GitHub CI přesného základu | PHPUnit, MariaDB 10.3, PHP quality a dokumentace PASS; MariaDB 11.4 fail při inicializaci kontejneru před checkoutem. [Snapshot](github-ci.json). |

Počet místních assertions se liší od historického release reportu; uvádí se
skutečný výsledek tohoto běhu, nikoli opsané historické číslo. Přeskočené
symlink testy vyžadují Linux bránu uvedenou v plánu. Jména přeskočených testů
a návratové stavy shrnuje [checks.json](checks.json).

## Reprodukce benchmarku

Z kořene stejného checkoutu, PHP 8.2 s pdo_sqlite a mbstring:

```powershell
php docs/verification/refactoring-wave1-20261028/benchmark-source.txt .
```

[Zdroj benchmarku](benchmark-source.txt) je záměrně uložen jako textový důkaz,
ne jako nový webový PHP endpoint. Spouští se explicitně PHP CLI. Nenahrává
`db.php`, `init.php`, config, reálné osoby ani externí transport. Data vznikají
výhradně v `sqlite::memory:`. Neprovádí aplikační nákup ani platbu.

Počítá `PDO::query()` a `PDOStatement::execute()`; `prepare()` samotné se
nepočítá. Po sestavení fixtures se čítač vynuluje. Počet produktových řádků
je počet výsledků katalogového SELECT, ne počet řádků interně prohlédnutých
databázovým enginem. Kontroly schématu zahrnují sqlite_master, PRAGMA a
`SELECT variant_id FROM shop_stock_balances LIMIT 0`.

Dva datové profily mají po jedné variantě zboží: ruční původ a importovaný
Shoptet původ s existující zkratkou navigace. Programové nabídky, jejich
kapacita, členské ceny a celé HTTP requesty nejsou v tomto benchmarku měřeny.
Časy vznikly při souběžném běhu místních kontrol a nejsou stabilní výkonovou
bránou. Počty dotazů/řádků jsou opakovatelné; cílové časy až z izolovaného
MariaDB/HTTP měření po implementaci.

## Inventáře

- [Trackovaná dokumentace](documentation-inventory.json): všechny Markdown
  soubory původního základu; SHA-256, velikosti a nadpisy.
- [Místní pracovní dokumentace](local-documentation-inventory.json): 28
  netrackovaných first-party Markdown souborů původního checkoutu; bez obsahu.
- [Přílohy](attachment-inventory.json): 14 cest; obsah nebyl kontrolován.
- [Zdrojové metriky](source-inventory.json): rozsah skenu, největší soubory,
  wiring soubory a počty PHPStan baseline.

## Stav na konci přípravy (před implementací)

Aplikační refaktoring, nový HTTP/browser průchod, vlastní MariaDB benchmark,
kontrola živé produkce, přejímka obsluhy, změna konfigurace/DB, push, PR, merge
a deploy. Provedená místní sada je ověření výchozího bodu, ne přejímka budoucích změn.

## Implementace a přejímka

Vlastník navázal zadáním „pokračuj dle plánu plně autonomně“. Implementace
je v [PR #209](https://github.com/KovoPraha/evidenceTreninku/pull/209), commit
`000b16a2`; bezpečnostní aktualizace locku v `f4bc1f63`. Původní checkout
zůstal na `d1221f35` se stejnou změnou `kis_rosters_admin.php`.

- `shop_storefront_read.php`: jeden přehled sloupců a nejvýše jeden probe
  skladu pro jedno čtení; detail filtruje `p.id` už v SQL. Žádná dlouhodobá
  cache cen, oprávnění, kapacity ani schématu.
- Stávající facade `shopStorefrontProducts(PDO, ?DateTimeImmutable)` zachována,
  včetně pořadí klíčů návratových řádků. Detail sdílí prezentační sestavení katalogu.
- Menu čte minimální projekci, kategorie a dávkové programové vazby; nečte
  obrázky ani atributy variant. Vazby včetně neaktivních nabídek a Shoptet výjimky
  zachovány. Programové nabídky stále používají aktuální kapacitu a podmínky.
- Povinné PSR-12 formátování změnilo celý dotčený `shop_checkout.php`.
  [AST porovnání](checkout-ast-result.json) všech 51 funkcí proti základu
  prokázalo pouze dvě očekávané významové změny; [reprodukce](checkout-ast-check.txt).

| Kontrola implementace | Skutečný výsledek |
| --- | --- |
| Regrese proti původnímu kódu | Tři výkonové testy nejprve prokazatelně selhaly: 1 000 řádků detailu, růst dotazů katalogu a menu. [Log](performance-before.txt). |
| Nové chování a upravené wiring testy | 29 testů / 4 684 assertions PASS; včetně změny schématu mezi voláními/PDO, chybějícího vs. poškozeného skladu, smíšeného menu, kapacity, souhlasů a časových hranic. [Log](targeted-after.txt). |
| Celá sada na Linux CI | 1 757 testů / 35 705 assertions PASS; 0 skipped. [CI](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40673837348), [snapshot](github-ci-implementation.json). |
| Unix soukromé úložiště | Povinný samostatný běh 3 testů / 45 assertions, bez skipů. Stejné CI. |
| MariaDB 10.3 a 11.4 | Všechny integrační smoke kroky PASS, včetně souběžného checkoutu a nového SQL rozpočtu. Stejné CI. |
| PHPStan | Bez nových baseline výjimek; 524 položek / 2 413 starých potlačených výskytů beze změny. [Log](phpstan-after.txt). První lokální Composer wrapper narazil na 300s timeout, přímý běh dokončil PASS. |
| PHPStan kontrakty | PASS, 10 přesných negativních nálezů zachováno; potvrzeno i PHP quality CI. |
| Styl | 15 dotčených PHP souborů, 0 chyb. [Log](style-final.txt). |
| Anonymní HTTP sweep | 32 veřejných, 2 CLI a 237 chráněných tras; 0 chyb. [Log](anonymous-after.txt). |
| JavaScript | Všech 14 současných smoke testů PASS. [Log](javascript-after.txt). |
| Browser | Katalog a rozbalení menu, detail 100 Kč, rychlý nákup, přihlášení rodiče, členská cena 80 Kč, zájmový program bez možnosti nákupu. [Záznam](browser-verification.json). |
| HTTP ochrany | Anonymní POST odmítnut před zápisem, neplatné CSRF bez změny košíku; po revokaci přes kanonickou službu ve stejné session zanikla cena 80 Kč. Nula objednávek/plateb. [Výsledky](http-security.json), [reprodukce](http-security-source.txt). |

První lokální celá sada měla 4 selhání pouze ve starých testech závislých na
přesných mezerách zdrojového kódu. Upraveny na formátově nezávislé kontroly,
kontrakt signatury ověřuje Reflection. Následná cílená sada i úplné Linux CI PASS.
Navazující PHPUnit běh CI se původně nespustil kvůli Composer auditu; audit
nebyl vypnut ani ignorován, opravu popisuje následující oddíl.

## Výkon a jeho meze

SQLite [před](storefront-baseline.json) / [po](storefront-after.json) používá
stejný uložený benchmark. MariaDB 10.4.32 používá izolovanou dočasnou databázi,
redukované syntetické schéma a indexy vazeb, 1/50/250/1 000 produktů, po jedné
variantě, 5 warmup + 30 měřených volání pro každý profil. Nejde o EXPLAIN
produkční databáze. [Před](mariadb-before.json), [po](mariadb-after.json),
[porovnání všech 12 profilů](mariadb-comparison.json).

| Profil, 1 000 produktů | SQL před → po | Produktové řádky před → po | Medián ms před → po |
| --- | --- | --- | --- |
| Detail | 1 008 → **6** | 1 000 → **1** | 1 900,94 → 14,31 |
| Menu ručního zboží | 9 011 → **9** | 1 000 → 1 000 | 16 145,85 → 337,21 |
| Menu importu | 1 011 → **7** | 1 000 → 1 000 | 256,79 → 204,97 |

Všech 12 výstupních SHA-256 před/po je shodných. EXPLAIN cíleného detailu
ukazuje `p` a `pub` jako `const` přes PRIMARY, variantu jako `ref` přes
`product_id`, odhad jednoho řádku. Nevznikla aplikovaná migrace/index v aplikaci.
Počet dotazů je rozhodující opakovatelná brána. Časy a peak memory jsou
orientační: sdílený Windows počítač byl během části měření zatížen dalšími
kontrolami. Velký rozptyl původních p95 nepřevádět na slib produkčního zrychlení.
Importovaný profil měl v prvním běhu p95 378,71 → 414,54 ms, proto následuje
samostatné opakování stejného profilu, popsané níže.

Opakování 1 000 importovaných produktů, znovu 5 warmup + 30 vzorků, potvrdilo
shodný výstup a 1 011 → 7 SQL. Medián **291,40 → 168,63 ms**, p95
**650,50 → 210,49 ms**. První mírné zhoršení p95 se neopakovalo; časová
měření na tomto sdíleném stroji zůstávají popisná. [Před](mariadb-imported-repeat-before.json)
/ [po](mariadb-imported-repeat-after.json). Reprodukce z kořene worktree:

```powershell
$env:EVIDENCE_MARIADB_SMOKE_PORT='33426'
php tests/Support/StorefrontReadMariaDbSmoke.php C:/Users/Marek/Documents/Codex/evidencePavel-wave1-baseline-20261028 --baseline --large-only --imported-only
php tests/Support/StorefrontReadMariaDbSmoke.php . --large-only --imported-only
```

Pro úplné měření vynechat oba filtry. Script vytváří a ve `finally` odstraní
pouze svou náhodně pojmenovanou dočasnou databázi; běží jen přes CLI na localhostu.

HTTP používá dvě PHP instance stejného ini bez OPcache, stejnou syntetickou
MariaDB a střídavé pořadí před/po, 5 warmup + 30 vzorků každé cesty.
Původní detail při 1 001 aktivních produktech překročil limit PHP 30 sekund
v `club_program.php`; nezapočítává se jako úspěšný časový vzorek. Pro párové
měření bylo v této vlastní fixture deaktivováno 950 zbožových produktů,
zůstalo 51 aktivních z celkových 1 001. [Fixture](http-fixture-source.txt),
[benchmark](http-benchmark-source.txt), [výsledky](http-benchmark.json).

| Celá HTTP cesta včetně menu, 51 aktivních produktů | Medián ms před → po | p95 ms před → po |
| --- | --- | --- |
| Detail | 3 012,13 → **501,61** | 4 606,64 → **850,75** |
| Katalog | 3 131,55 → **1 315,71** | 5 873,93 → **2 178,65** |

Menu HTML má v každém páru stejný SHA-256. Data, autentizace i obrázky jsou
syntetické; jediná chyba browser konzole byla chybějící `/favicon.ico` (404).
HTTP nepředstavuje ostrý nákup, skutečnou bankovní platbu ani lidskou UAT.
Programové profily nemají slib konstantního SQL; jejich kapacita a souhlasy
jsou pokryty behaviorálními regresními testy. Dávky programových vazeb mají
nejvýše 1 000 ID; při větších katalozích roste počet dávek.

Sandbox vznikl pomocí `setup-finance-b1-sandbox.ps1` s vlastním
`LOCALAPPDATA=C:/Users/Marek/AppData/Local/EvidenceWave1-20261028`.
Standardní demo seed vytvořil syntetického rodiče a děti, poté se zastavil
na požadavku připraveného Shoptet importu. Žádný skutečný import nebyl doplněn;
samostatný výše uvedený HTTP fixture script vytvořil vlastní testovací katalog.
Po ověření byly obě vlastní HTTP instance (18086, 18087) a MariaDB (33426)
zastaveny s kontrolou vlastnictví; syntetická data zůstala na disku. Před
opakováním revokačního testu obnovit syntetickou rodičovskou vazbu; test ji
záměrně zanechá odvolanou. Původní pracovní webroot nebyl konfigurován ani měněn.

## Nutná bezpečnostní aktualizace závislosti

První [CI 40673037844](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40673037844)
prošlo obě MariaDB větve, ale `composer audit` zastavil PHPUnit job před testy.
Zjistil 4 advisory na již přítomném PhpSpreadsheet 5.8.1. Autor knihovny uvádí
opravu v 5.10.0: [HTTP redirect SSRF](https://github.com/PHPOffice/PhpSpreadsheet/security/advisories/GHSA-2phh-27fw-q4gc),
[ODS opakované řádky](https://github.com/PHPOffice/PhpSpreadsheet/security/advisories/GHSA-j2w7-387v-m2rq),
[ISO-2022 scanner](https://github.com/PHPOffice/PhpSpreadsheet/security/advisories/GHSA-3x94-m842-937c),
[dvojí dekódování scanneru](https://github.com/PHPOffice/PhpSpreadsheet/security/advisories/GHSA-rxj4-h86g-25gc).

`composer update phpoffice/phpspreadsheet:5.10.0 --with-dependencies --minimal-changes`
změnil jediný balíček v locku; žádné další instalace/odstranění a žádná změna
Composer požadavků. Verze vyžaduje PHP 8.2, které je už minimem aplikace.
[Aktualizace](dependency-update.txt), [čistý audit](composer-audit.json),
[42 cílených testů exportů/importů a dokladů / 268 assertions](dependency-tests.txt).
Následné úplné CI je zelené. Jde o výslovně zdokumentované rozšíření přípravy
nezbytné k odstranění zjištěného bezpečnostního problému; auditní gate zůstala zapnutá.

Produkce, její konfigurace a reálná data nebyly touto prací měněny. PR není
sloučen ani nasazen. Rollback kódu nemá DB krok; bezpečnostní aktualizaci locku
nevracet automaticky spolu s refaktoringem.

[Implementační plán](../../REFACTORING_WAVE1_2026-10-28.md) ·
[Rozsah dokumentačního čtení](../../REFACTORING_DOCUMENTATION_2026-10-28.md)
