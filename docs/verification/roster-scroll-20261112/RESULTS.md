# ROSTER-SCROLL-01 — ověření 12. 11. 2026

Stav: místní implementace a syntetické ověření; [PR #462](https://github.com/KovoPraha/evidenceTreninku/pull/462) otevřen, CI běží, merge/deploy/přihlášené provozní UAT NOT RUN. Větev `codex/roster-scroll-position-20261112`. [Plán](../../ROSTER_SCROLL_POSITION_2026-11-12.md).

| Kontrola | Výsledek | Důkaz / mez |
| --- | --- | --- |
| Syntaxe změněných PHP | PASS | `php -l kis_roster_hierarchy_admin.php`, `php -l includes/member_roster_view.php` |
| Doménové operace členství | PASS | `php C:/xampp/htdocs/evidencePavel/vendor/bin/phpunit --configuration phpunit.xml.dist tests/Integration/MemberRosterAdminTest.php`: 12 testů, 134 assertions, SQLite in-memory. Použitá nezměněná vendor instalace z původního checkoutu. |
| CSP nonce pro nový skript | PASS po opravě | První běh CI #462 odhalil chybějící nonce na inline skriptu. Doplněno `app_csp_nonce()`; místní `ContentSecurityPolicyTest`: 6 testů, 14203 assertions PASS. [Původní běh](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43233311052) s jedním CSP selháním patří před opravu. |
| POST/303/GET, více akcí a chybový POST | PASS synteticky | Chrome / Playwright CLI nad izolovanou lokální stránkou se skriptem načteným přímo z cílového PHP. Přidání, převod a chyba v řádku 50 dlouhého seznamu: `scrollY=2700`, `team_id=42`, `on=2026-11-12`, `person_search=Novak` zachovány; `scroll_y` po obnovení zmizí z URL. |
| Mobil 390 × 844, ukončení | PASS synteticky | Po potvrzení řádku 50 `scrollY=2739`, stejné parametry výběru a hledání, bez `scroll_y` v URL. Hodnota 2739 je skutečná pozice po automatickém dovinutí kliknutého tlačítka v prohlížeči. |
| Přihlášené uživatelské UAT na skutečné stránce | NOT RUN | Žádný zápis do skutečného členství, přihlášené účty ani produkční mobilní zařízení nebyly v tomto průchodu použity. Syntetická stránka ověřuje chování skriptu, nikoli úplné vykreslení KIS. |
| Kontrola PHP CS Fixer | PASS v CI; místně NOT RUN | Místní `php tools/check-php-style.php` nemohl spustit chybějící `vendor/bin/php-cs-fixer`; `composer install` skončil timeoutem při stahování PHPStan. [PHP quality workflow](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43234241139) včetně PSR-12 a PHPStan prošel. |
| Plná místní sada PHPUnit | NELZE HODNOTIT | Sdílené `vendor` z původního checkoutu neodpovídá současnému `composer.lock`: běh `--stop-on-error` skončil na chybějící třídě `Symfony\Component\Filesystem\Path` v `FilesystemDependencyContractTest` po 248 testech. Není to selhání opravy; linuxové CI instaluje závislosti z aktuálního locku. |
| Plný PHPUnit na PHP 8.2 v CI | PASS | [Testy #1399](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43234241186), úloha „PHPUnit na PHP 8.2“ dokončena úspěšně po doplnění CSP nonce. Ostatní MariaDB úlohy téhož běhu ještě pokračují. |
| Propojení dokumentace a whitespace | PASS | `php tools/check-current-docs.php`: 80 řádků CURRENT, 5404 lokálních odkazů, 0 chyb; `git diff --check` bez nálezu. |

Izolovaná fixture v ignorovaném `output/playwright/roster-scroll-fixture.php` není součástí Gitu. POST používá stávající serverové CSRF, kontrolu oprávnění, náhled a mutační službu beze změny. Další krok: PR/CI, potom přihlášená přejímka správy osob u registráře.
