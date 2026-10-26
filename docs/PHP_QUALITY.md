# PHPStan a styl změněných souborů

Balík dozoru 3, společný stav nad main `d12e7c79`, 26. 10. 2026. Aplikační soubory se v tomto
balíku hromadně neformátují a aplikované migrace se nemění.

## Statická analýza

`composer analyse` spouští PHPStan na úrovni 5, s cílovým PHP 8.2, nad aplikací,
CLI a migracemi. Vynechává vendor, testy, výstupy, docs, lokální config a cache.
Procedurální symboly z includes/ objevuje staticky; bootstrap načítá pouze
Composer autoload, nikdy init.php/db.php/config.php ani skutečnou databázi.
Cache používá výchozí `sys_get_temp_dir()/phpstan` (ověřeno v PHPStan 2.3.2),
nikoli webroot. Případnou starou `.phpstan-cache/` blokuje obecné Apache pravidlo
pro tečkové cesty a inventura sweepu ji ignoruje. Exclude v konfiguraci zůstává
pro staré lokální zbytky; jejich existence nesmí ovlivnit analýzu ani testy.

Původní společná baseline měla 555 položek pro 2 539 dosavadních výskytů chyb.
Aktualizace F3 je cílená, bez automatické regenerace: sedm položek pro `$pdo`
(92 výskytů) nahrazuje omezený bootstrap kontrakt a starý počet `$order` klesá
z 58 na 51 podle skutečného kódu po #203. Žádná nová chyba se nepřidává do baseline.
Správný popis stavu PDO navíc odstranil 24 starých položek / 27 výskytů falešně
mrtvých rollback větví. Jednotlivé dotčené transakce byly zkontrolované; jejich
kód ani neměnné migrace se nepřepisovaly. Výsledná baseline má **524 položek /
2 413 výskytů**.
Baseline je počítaná podle souboru, zprávy a počtu výskytů, nikoli plošným ignore.
Nové chyby nad tyto počty shodí CI. Odstraněná chyba vyžaduje zúžit baseline
(výchozí reportUnmatchedIgnoredErrors); nepřidávat automaticky nové potlačení.
Samotná baseline neznamená opravu starého kódu ani plnou typovou bezpečnost.

### Procedurální bootstrap a proměnlivý stav PDO (F3)

`tools/phpstan/BootstrapPdoExtension.php` rozpoznává výhradně chybu
`variable.undefined` pro `$pdo` v globálním scope devíti konkrétních prověřených
vstupů. AST musí obsahovat před použitím nepodmíněný `require`/`require_once`
přesného `db.php` nebo `includes/init.php` uvedeného pro daný vstup. Tyto bootstrapy
vytvoří PDO, nebo ukončí request. Podmíněný import, jiné jméno souboru či proměnné,
lokální funkce, closure, použití před importem, `unset($pdo)` a nové neprověřené
stránky zůstávají hlášené. Nejde o globální ignore všech chyb `$pdo`; původní
baseline ostatních legacy souborů zůstává samostatná a počítaná.

`Pdo.stub` označuje pouze `PDO::inTransaction()` jako `@phpstan-impure`, protože
stav skutečně mění begin/commit/rollback. Tím se nezamlčí kontrola mrtvého kódu
v ostatních metodách a neodstraní se potřebné rollback větve. Dva nové template
fragmenty mají dokumentované vstupy dodané volajícím; nedostávají náhradní data.

`php tools/check-phpstan-contract.php` spouští skutečný PHPStan na syntetických
souborech v systémovém tempu. Vyžaduje čisté platné importy a proměnlivý stav
transakce, ale současně přesně deset negativních nálezů včetně nové chyby návratového
typu. Kontroluje je také quality CI. Fixture ani bootstrap se runtime nespouštějí;
analýza se nepřipojuje k databázi.

Podklady: [Ignore Error Extensions](https://phpstan.org/developing-extensions/ignore-error-extensions),
[stavové metody](https://phpstan.org/writing-php-code/phpdocs-basics#impure-functions),
[stub files](https://phpstan.org/user-guide/stub-files).

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
- Lokálně je seznam diff pracovní kopie proti merge-base s origin/main plus
  nové neignorované PHP soubory. CI používá úplnou Git historii a skutečnou
  základní větev PR přes STYLE_BASE_REF (také u navázaných PR). Lokálně lze
  stejným nastavením zvolit základ navázaného PR.
- Změna, smazání nebo přejmenování existující PHP migrace shodí kontrolu;
  nový migrační soubor projde stejným stylem. README migrační pravidlo doplňuje.

CI workflow `php-quality.yml` spouští analýzu vždy a styl jen v PR. Nový projektový
kód této změny prošel formátovačem; z aplikačních souborů F3 doplňuje pouze
dokumentaci vstupů dvou šablon a formát tabulkového fragmentu.
Po analýze CI ve stejném checkoutu ověří, že nevznikla `.phpstan-cache`, spustí
anonymous sweep a regresní testy inventury a deploy ochrany. Tím kontroluje
také souběh těchto nástrojů, nikoli pouze jejich oddělené běhy.
Inventura testů SharedUiShellTest rovněž používá trackované PHP soubory bez
tečkových cest. Již existující stará cache tedy nevyvolává falešné UI nálezy.
[Použití PHP-CS-Fixer](https://cs.symfony.com/doc/usage.html).

Konfigurace/baseline/cache/formátovač jsou vyloučené z obou rsync bloků;
PHPStan neon soubory navíc blokuje Apache, včetně historicky ponechaných kopií.
Dev závislosti zůstávají v require-dev; všech 31 produkčních verzí a source/dist
referencí v composer.lock je beze změny proti main. U tří záznamů se liší pouze
metadata autorů (Unicode), nikoli knihovny; F1–F3 samotný lock neupravují.
