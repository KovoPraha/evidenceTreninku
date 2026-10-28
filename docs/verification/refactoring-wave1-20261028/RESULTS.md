# Příprava refaktoringu — důkazy a opakování

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

## Neprovedeno

Aplikační refaktoring, nový HTTP/browser průchod, vlastní MariaDB benchmark,
kontrola živé produkce, přejímka obsluhy, změna konfigurace/DB, push, PR, merge
a deploy. Provedená místní sada je ověření výchozího bodu, ne přejímka budoucích změn.

[Implementační plán](../../REFACTORING_WAVE1_2026-10-28.md) ·
[Rozsah dokumentačního čtení](../../REFACTORING_DOCUMENTATION_2026-10-28.md)
