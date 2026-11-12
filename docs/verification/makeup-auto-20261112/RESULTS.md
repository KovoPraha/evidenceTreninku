# MAKEUP-AUTO-01 — ověření implementace (12. 11. 2026)

## Rozsah

Větev `codex/makeup-auto-20261112`, draft [PR #459](https://github.com/KovoPraha/evidenceTreninku/pull/459).
Schéma přidává výchozí vypnutý přepínač, děděná pravidla a výjimky; čtení
termínů je bez zápisu, první rezervace vytváří označený slot v transakci.
Staré kredity, rezervace, ruční sloty a směry zůstávají ve schématu.

## Ověření

| Kontrola | Výsledek | Důkaz / mez |
| --- | --- | --- |
| `php vendor/bin/phpunit --filter ClubMakeupTest` | PASS | 18 testů, 140 assertions; automatický termín bez ručního slotu, 3/2 kapacita, plná skupina, věk, vyloučení plánu, výjimka směru i vypnutí přepínače, migrace opakovaně, platnost a limit nároků, uložená lhůta storna, původní životní cyklus. |
| `php vendor/bin/phpunit --filter DeployWorkflowContractTest` | PASS | 14 testů, 503 assertions; seznam zálohovaných tabulek zahrnuje nové nastavení a výjimky. |
| `php vendor/bin/phpunit --filter AuthSecurityMigrationTest` | PASS | 1 test / 91 assertions po doplnění nové migrace do očekávaného registru. |
| Plná lokální PHPUnit | ENVIRONMENT FAIL | `--stop-on-error --no-progress`: první chyba po 248 testech je chybějící `Symfony\Component\Filesystem\Path` v původním sdíleném `vendor`; pracovní strom má odlišný `composer.lock`. Není to výsledek plné sady nad závislostmi této větve. |
| PHP syntaxe, PSR-12, dokumentace, diff | PASS | `php -l` na 10 změněných PHP souborech; PHP CS Fixer 3.95.30 `--dry-run` nad 10 soubory 0 oprav; žádný řádek přes 200 znaků; `php tools/check-current-docs.php`: 80 řádků CURRENT, 0 chyb; `git diff --check`: 0 chyb. Repo skript `tools/check-php-style.php` nelze spustit proti sdílenému `vendor`, kde chybí `vendor/bin/php-cs-fixer`; ekvivalentní kontrola PHAR prošla. |
| MariaDB 10.3/11.4, dvě souběžné rezervace, rolový browser | NOT RUN | Čeká na izolované CI/UAT; zde nebyla měněna produkce ani skutečná data. |
| Merge, deploy, zapnutí automatiky, lidská přejímka | NOT RUN | Samostatné kroky až po potvrzení věkových hranic, pravidel a skutečných kapacit vedením. |

První běh PR CI na `067f0348` našel tři opravené chyby: MariaDB odmítla
`INSERT ... SELECT 1 WHERE` bez `FROM`, migrační test neznal nové ID a PHPStan
hlásil jednu další neurčenou proměnnou `$pdo` v trenérské šabloně.
Nový běh po opravě čeká na výsledek; první selhání se nezapočítává jako PASS.

## Známé hranice

- Automatický režim nabízí jen nabídky se strukturovaným věkem a číselnou
  kapacitou. Nabídky bez stropu zůstávají v ručním režimu.
- Kanonický výpočet kapacity počítá unikátní běžné účastníky a držená místa
  přes celé překrývající se období nabídky. Může proto některý jednotlivý
  termín vyhodnotit konzervativně jako plný; nepřidává místo nad strop.
- Přehled termínů koordinátora je orientační. Konkrétní cíl a volno se vždy
  vyhodnocují pro dítě a znovu při POST v transakci.

[Technická analýza a akceptace](../../TRAINING-RSVP-AND-MAKEUPS-2026-10-14.md#makeup-auto-01--automatické-cíle-a-termíny-12-11-2026).
