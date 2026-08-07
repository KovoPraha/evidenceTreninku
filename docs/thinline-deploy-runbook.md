# Runbook: nasazování na Thinline server replikant3544

Stav: živě ověřené poznatky z prvního nasazení kis.kovopraha.cz (2026-08-06).
Platí pro všechny aplikace nasazované přes SSH na tento server — zvláštnosti
hostingu jsou vlastností serveru, ne projektu. Deploy vlákno čte tento soubor
PŘED jakoukoli úpravou `.github/workflows/deploy-production.yml`.

## Vlastnosti serveru (empiricky ověřené, ne domněnky)

SSH účet `kovopraha_cz`, host `replikant3544.thinline.cz`, port **2228**.

1. **Chroot s rozbitým `$HOME`.** Přihlášení přistane v `/` chrootu; `$HOME`
   ukazuje na neexistující `/home/www/...`. Kořen chrootu je root-owned a
   NEzapisovatelný. Důsledky:
   - v deploy skriptech nikdy `~` ani `$HOME`; všechny cesty RELATIVNĚ
     k přihlašovacímu adresáři (docrooty: `kis.kovopraha.cz`, `data.kovopraha.cz`, …),
   - pracovní stav deploye patří do soukromého `data/` (`data/.kis-deploy`,
     `data/.kis-backups`); nové adresáře v kořeni založit nejde,
   - `authorized_keys` je v `.ssh/authorized_keys` relativně od loginu.

2. **Omezený shell filtruje PHP.** Postupně živě zjištěno:
   - `php -r 'kód'` → „Passing parameters to PHP interpreter is not allowed“,
   - `php skript.php --argument` → tatáž chyba (blokované jsou VŠECHNY
     argumenty za jménem skriptu),
   - `VAR=x php skript.php` → skript běží, ale externí env proměnné jsou
     VYČIŠTĚNÉ (skript je nevidí),
   - povolené je: `php -v`, holé `php skript.php`, plný přístup k souborům.

   **Jediný spolehlivý kanál pro parametry je soubor.** Kanonický vzor
   „putenv bootstrap“: runner vygeneruje malý PHP soubor
   `<?php putenv('KLÍČ=hodnota'); … require __DIR__.'/cíl.php';`,
   nahraje ho přes scp a spustí `php bootstrap.php`. Cílové skripty čtou
   getenv() jako obvykle — putenv ve stejném procesu funguje.
   Implementace: kroky preflight/záloha/migrace v deploy workflow;
   cílové skripty `bin/deploy-preflight.php` (DEPLOY_PROBE, APP_HOST,
   APP_ROOT), `bin/migrate.php` (MIGRATE_ACTION=check|apply, MIGRATE_JSON=1)
   a `bin/db-backup.php` (BACKUP_APP_ROOT, BACKUP_TARGET_DIR — absolutní
   REÁLNÁ cesta odvozená za běhu, viz bod 3, BACKUP_KEEP, BACKUP_JSON=1) mají aditivní
   env vstupy; CLI argumenty mají přednost a jinde fungují beze změny.
   Bootstrapy nahrané do release je NUTNÉ před aktivací smazat, jinak je
   rsync zkopíruje do webrootu.

3. **PHP vidí skutečné cesty a má open_basedir.** SSH chroot `/` je ve
   skutečnosti `/home/www/kovopraha.cz/www/`; PHP procesy pracují s reálnými
   cestami a open_basedir povoluje jen
   `/home/www/tmp:/home/www/kovopraha.cz/www/:/etc/ssl/certs:/usr/share/php:/usr/share/geoip`.
   Důsledky:
   - absolutní cesty psané z pohledu chrootu (`/data/...`) PHP odmítne;
     absolutní cestu vždy odvozovat za běhu z `__DIR__`/`getcwd()`
     (vzor: `dirname(__DIR__) . '/.kis-backups'` v run-backup bootstrapu),
   - `APP_PRIVATE_STORAGE_ROOT` a podobné konfigurační cesty musí ležet
     uvnitř `/home/www/kovopraha.cz/www/` (např.
     `/home/www/kovopraha.cz/www/data/kis-private`).

4. **DB_HOST je `127.0.0.1`.** Ověřeno konfigurací i produkčním provozem
   staré evidence (včetně záloh přes SSH). phpMyAdmin sice hlásí server
   `10.5.5.237`, ale to je jeho vlastní spojení — aplikace i CLI nástroje
   na webovém uzlu používají `127.0.0.1`. Stejný host platí pro
   `mysqldump`/`mysql` v `--defaults-extra-file`.

5. **Budoucí CRONy mají stejný problém.** Adresáře `CRON.den` apod. spouštějí
   skripty ve stejném omezeném prostředí — každý plánovaný úkol (připomínky,
   expirace objednávek) musí používat putenv bootstrap, ne argumenty/env.

6. **Databáze: MariaDB 10.3.39** (Debian 10, EOL — otevřené vlastníkovo
   rozhodnutí); aplikace se připojují přes `127.0.0.1` (viz bod 4), bez SSL.
   Server má zapnutý
   STRICT_TRANS_TABLES; lokální XAMPP ho má VYPNUTÝ — chyby typu „Field
   doesn't have a default value“ se projeví až v CI/produkci (proto CI
   testuje MariaDB matrix 10.3 + 11.4 a smoke fixtury musí odpovídat
   reálnému schématu, viz oprava AUTO_INCREMENT u `treneri`).
   Kompatibilitní podlaha SQL je 10.3 (viz docs/mariadb-10.3-compatibility-*).

7. **PHP CLI 8.2.32 a rsync k dispozici; MySQL CLI prakticky ne.**
   `mysql` klient v shellu neexistuje a `mysqldump` je stejný omezený
   wrapper jako php — odmítá parametry („Unknown/unsupported parameter“,
   včetně `--defaults-extra-file`). Databázové operace na serveru proto
   dělat buď přes phpMyAdmin (kopie DB: Operace → Zkopírovat databázi do,
   bez CREATE DATABASE pokud cíl existuje), nebo PHP skriptem přes PDO
   s putenv bootstrapem (cross-DB `CREATE TABLE cíl.t LIKE zdroj.t` +
   `INSERT ... SELECT` funguje, stejný uživatel má práva na obě DB).
   Zálohy řeší aplikace vlastním čistě-PHP `bin/db-backup.php`.

## Architektura deploy workflow (`deploy-production.yml`)

Ruční `workflow_dispatch` s potvrzením slovem **NASADIT**; job běží
v environment `production` (lze přidat povinné schválení). Cíl je
parametrizovaný GitHub **Variables** `KIS_APP_HOST`, `KIS_WEB_URL`,
`KIS_REMOTE_DIR` (fail-closed kontrola, relativní cesta bez `..`).
**Secrets**: `SSH_HOST`, `SSH_PORT`, `SSH_USER`, `SSH_PRIVATE_KEY`
(vyhrazený ed25519 deploy klíč), `SSH_KNOWN_HOSTS` (pinovaný otisk
`[host]:2228`; workflow bez shody odmítne jet).

Pořadí kroků a brány (nic destruktivního před zálohou):

1. testy + lint + composer audit na runneru,
2. preflight: docroot + config.php existují, PHP/rsync dostupné,
   putenv bootstrap ověří rozšíření a config (produkční prostředí,
   DB konstanty, `AUTH_RATE_LIMIT_PEPPER` ≥ 32 znaků) → `{"ok":true}`,
3. záloha DB přes nahraný `db-backup.php` mimo webroot; vyžaduje
   `tables > 0` — **první deploy proto předpokládá už naplněnou DB**
   (kopie `kovoprahacz09` → `kovoprahacz10` přímo na serveru),
4. kompletní release do `data/.kis-deploy/releases/<sha>-<run>`,
   `config.php` se do release jen kopíruje ze serveru (nikdy z gitu),
5. migrace z release (`run-migrate-apply/check` bootstrapy, poté smazané),
6. aktivace: rsync release → docroot, bez `--delete`, s excludy
   (`config.php`, uploады, obrázky, testy…),
7. HTTP smoke `WEB_URL/index.php` (200/302).

Kontrakt hlídá `tests/Unit/DeployWorkflowContractTest.php` — mj. zákaz
`php -r`, pořadí záloha→release→migrace→aktivace→smoke a putenv vzor.
Změny workflow VŽDY promítnout i do testu.

## Rychlá diagnostika bez spouštění CI

- preflight na přání: `ssh … 'php data/.kis-deploy/run-preflight.php'`
  → očekávané `{"ok":true,"php":"8.2.32"}`,
- config na serveru: `grep -c AUTH_RATE_LIMIT_PEPPER <docroot>/config.php`
  (0 = špatný soubor) a `grep -n DB_HOST …`,
- vzor funkčního `config.php` je `config.example.php`; ostrý soubor se
  100% drží mimo git a nahrává se jednorázově přes scp.

## Import Shoptet katalogu do produkce

`bin/shoptet-products-dry-run.php` a `bin/shoptet-products-stage.php` mají
aditivní env vstupy `SHOPTET_INPUT`, `SHOPTET_APPLY=1`, `SHOPTET_JSON=1`
(kvůli blokaci argumentů na hostingu). Vlastník používá lokální nástroj
`kis-shoptet-import.ps1`: nahraje XML do `data/shoptet-import.xml` (mimo
webroot), vygeneruje putenv bootstrapy do `data/.kis-deploy/` a spustí
dry-run → po potvrzení staging. Produkty vznikají jako DRAFT; zveřejnění
výhradně přes administraci „Aktivace katalogu“. Skripty se na server
dostávají běžným deployem (jsou součástí release v docrootu).

## Ponaučení pro další aplikace na tomto serveru

Nový deploy = zkopírovat tento workflow, změnit trojici Variables,
založit docroot + config.php + vlastní `data/.<app>-deploy` prefix a
NEPŘEDPOKLÁDAT nic, co tu není živě ověřeno. Když hosting odmítne další
konstrukci, postupovat empiricky: nejmenší možný pokus přes SSH, zapsat
výsledek sem, teprve pak měnit workflow.
