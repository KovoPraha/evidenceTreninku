# MAKEUP-AUTO-01 — ověření implementace (12. 11. 2026)

## Rozsah

Větev `codex/makeup-auto-20261112`, draft [PR #459](https://github.com/KovoPraha/evidenceTreninku/pull/459).
Schéma přidává výchozí vypnutý přepínač, děděná pravidla a výjimky; čtení
termínů je bez zápisu, první rezervace vytváří označený slot v transakci.
Staré kredity, rezervace, ruční sloty a směry zůstávají ve schématu.
Koordinátor vidí stavové počty, výjimky a stránkovaný deník všech událostí
nároků/rezervací; úprava věkových mezí zůstává v kanonické nabídce pro správce.

## Ověření

| Kontrola | Výsledek | Důkaz / mez |
| --- | --- | --- |
| `php vendor/bin/phpunit --filter ClubMakeupTest` | PASS | 18 testů, 144 assertions; automatický termín bez ručního slotu, 3/2 kapacita, plná skupina, zvýšení kapacity po vzniku slotu, věk, vyloučení plánu, výjimka směru i vypnutí přepínače, budoucí rezervace pro ochranu nákupu, migrace opakovaně, platnost a limit nároků, uložená lhůta storna, původní životní cyklus. |
| `php vendor/bin/phpunit --filter DeployWorkflowContractTest` | PASS | 14 testů, 503 assertions; seznam zálohovaných tabulek zahrnuje nové nastavení a výjimky. |
| `php vendor/bin/phpunit --filter AuthSecurityMigrationTest` | PASS | 1 test / 91 assertions po doplnění nové migrace do očekávaného registru. |
| `php vendor/bin/phpunit --filter ClubProgramTest` | PASS | 13 testů / 134 assertions po doplnění ochrany nákupu a úprav kapacity před budoucími rezervacemi náhrad. |
| Plná lokální PHPUnit | ENVIRONMENT FAIL | `--stop-on-error --no-progress`: první chyba po 248 testech je chybějící `Symfony\Component\Filesystem\Path` v původním sdíleném `vendor`; pracovní strom má odlišný `composer.lock`. Není to výsledek plné sady nad závislostmi této větve. |
| PHP syntaxe, PSR-12, dokumentace, diff | PASS | `php -l` na 10 změněných PHP souborech; PHP CS Fixer 3.95.30 `--dry-run` nad 10 soubory 0 oprav; žádný řádek přes 200 znaků; `php tools/check-current-docs.php`: 80 řádků CURRENT, 0 chyb; `git diff --check`: 0 chyb. Repo skript `tools/check-php-style.php` nelze spustit proti sdílenému `vendor`, kde chybí `vendor/bin/php-cs-fixer`; ekvivalentní kontrola PHAR prošla. |
| MariaDB 10.3/11.4, souběh posledního místa | PASS | [CI 43248400672](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43248400672): oba integrační smoke joby včetně `TrainingClosureMariaDbRace.php` PASS; test vyžaduje dva skutečně čekající souběžné požadavky, jednu uloženou rezervaci a jedno zamítnutí. |
| Plná CI PHPUnit, statická kontrola, dokumentace, CodeQL | PASS | [PHPUnit 43248400672](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43248400672): 2460 testů / 56453 assertions; PHPStan level 5 + styl 43248400701, dokumentace 43248400895, CodeQL 43248390394 PASS. |
| MariaDB čtecí profily 10.3/11.4 | PASS | [CI 43248400672](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43248400672): oba profily PASS s přesným přírůstkem schématu. |
| Rolový browser | NOT RUN | Přihlášená přejímka koordinátora, trenéra a rodiče zůstává otevřená. |
| Merge a deploy | PASS | PR #459 squash merge `428a2258c3a740b0d244f9b93c4fbaa9db2713f1`; [workflow 43269836068](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43269836068) SUCCESS na stejném SHA. |
| Zapnutí automatiky, lidská přejímka | NOT RUN | Výchozí `enabled=0`; hodnoty a skutečné kapacity musí potvrdit vedení, přihlášené rolové UAT zůstává otevřené. |

První běh PR CI na `067f0348` našel tři opravené chyby: MariaDB odmítla
`INSERT ... SELECT 1 WHERE` bez `FROM`, migrační test neznal nové ID a PHPStan
hlásil jednu další neurčenou proměnnou `$pdo` v trenérské šabloně.
Tyto opravy a následný souběžný fixture potvrdilo CI 43248400672; starší
selhání se nezapočítávají jako PASS.

MariaDB 10.3 profil porovnávající zmrazený `main` s PR narazil na kontrolu
absolutní shody počtu tabulek/migrací. Artefakt běhu ukazuje před 349/162,
po 353/163; rozdíl přesně odpovídá čtyřem novým tabulkám v jedné migraci.
Komparátory nyní přijímají pouze shodné schéma nebo tento přesný přírůstek.
Na staženém artefaktu prošly všechny tři komparátory (`medical`, `planner`,
`attendance`) s `complete=true` a `domain_results_equal=true`; kontrola
dat, HTML, vzorků a autorizace zůstala zachovaná. Opakované CI na `a7c80b1c`
prošlo na obou MariaDB verzích.

Běh 43240712174: full PHPUnit, PHPStan/styl, docs, CodeQL a oba MariaDB
čtecí profily PASS. Oba integrační smoke joby skončily ve starém fixture
`TrainingClosureMariaDbRace.php` na `makeup_last_slot`; fixture nyní aplikuje
novou migraci. Opakované CI 43248400672 potvrdilo oba smoke joby PASS.

## Známé hranice

Vlastník 12. 11. výslovně požádal o nasazení kódu. Release proběhl z `main`
na SHA `428a2258` přes chráněný workflow 43269836068 s
`uat_schvaleno=false`, `importovat_krouzky=false`. Workflow provedlo znovu
2460 testů / 56453 assertions, ověřenou zálohu 389 tabulek a 2 triggerů,
migraci s `current=true`/`pending=[]`, kontrolu nezměněných VS, aktivaci
release a přímý HTTP 200. Import produktů byl přeskočen. Výchozí přepínač
nové migrace je off; samostatný produkční SELECT pro aktuální hodnotu nebyl
proveden. Přihlášené rolové a lidské UAT zůstává NOT RUN.

- Automatický režim nabízí jen nabídky se strukturovaným věkem a číselnou
  kapacitou. Nabídky bez stropu zůstávají v ručním režimu.
- Kanonický výpočet kapacity počítá unikátní běžné účastníky a držená místa
  přes celé překrývající se období nabídky. Může proto některý jednotlivý
  termín vyhodnotit konzervativně jako plný; nepřidává místo nad strop.
- Přehled termínů koordinátora je orientační. Konkrétní cíl a volno se vždy
  vyhodnocují pro dítě a znovu při POST v transakci.

[Technická analýza a akceptace](../../TRAINING-RSVP-AND-MAKEUPS-2026-10-14.md#makeup-auto-01--automatické-cíle-a-termíny-12-11-2026).
