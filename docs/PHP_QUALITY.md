# PHPStan a styl změněných souborů

Balík dozoru 3, základ `3b164e91`, 25. 10. 2026. Aplikační soubory se v tomto
balíku hromadně neformátují a aplikované migrace se nemění.

## Statická analýza

`composer analyse` spouští PHPStan na úrovni 5, s cílovým PHP 8.2, nad aplikací,
CLI a migracemi. Vynechává vendor, testy, výstupy, docs, lokální config a cache.
Procedurální symboly z includes/ objevuje staticky; bootstrap načítá pouze
Composer autoload, nikdy init.php/db.php/config.php ani skutečnou databázi.

`phpstan-baseline.neon` obsahuje 556 položek pro 2 540 dosavadních výskytů chyb.
Baseline je počítaná podle souboru, zprávy a počtu výskytů, nikoli plošným ignore.
Nové chyby nad tyto počty shodí CI. Odstraněná chyba vyžaduje zúžit baseline
(výchozí reportUnmatchedIgnoredErrors); nepřidávat automaticky nové potlačení.
Samotná baseline neznamená opravu starého kódu ani plnou typovou bezpečnost.

Při záměrné aktualizaci PHPStanu nejprve prohlédni rozdíl nálezů. Teprve po
revizi lze spustit `php vendor/bin/phpstan analyse --generate-baseline --memory-limit=1G`
a zkontrolovat každý nový záznam. CI baseline negeneruje.

Zdroje: [baseline PHPStan](https://phpstan.org/user-guide/baseline),
[konfigurace](https://phpstan.org/config-reference).

## Styl pouze změněných PHP souborů

- `composer style:check`: PSR-12 dry-run, 200 znaků na řádek a složené závorky
  u if/elseif/foreach/for; alternativní PHP syntaxe HTML šablon je povolená.
- `composer style:fix`: opraví PSR-12 pouze v témže seznamu. Dlouhé řádky je
  potřeba smysluplně rozdělit ručně, kontrola je automaticky nepřepisuje.
- Seznam je diff pracovní kopie proti merge-base s origin/main plus nové
  neignorované PHP soubory. CI používá úplnou Git historii. Pro jiný základ
  lze výslovně nastavit STYLE_BASE_REF.
- Změna, smazání nebo přejmenování existující PHP migrace shodí kontrolu;
  nový migrační soubor projde stejným stylem. README migrační pravidlo doplňuje.

CI workflow `php-quality.yml` spouští analýzu vždy a styl jen v PR. Nový projektový
kód této změny prošel formátovačem; žádné cizí aplikační soubory se nepřepisovaly.
[Použití PHP-CS-Fixer](https://cs.symfony.com/doc/usage.html).

Konfigurace/baseline/cache/formátovač jsou vyloučené z obou rsync bloků;
PHPStan neon soubory navíc blokuje Apache, včetně historicky ponechaných kopií.
Dev závislosti zůstávají v require-dev; produkční balíky composer.lock beze změny.
