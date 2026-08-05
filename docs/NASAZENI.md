# Nasazení na produkci

Produkční adresa je <https://data.kovopraha.cz/evidence/>. Nasazení se spouští
ručně na GitHubu; samotný push do `main` produkci nezmění.

## Běžné nasazení krok za krokem

1. Otevřete repozitář na GitHubu a kartu **Actions**.
2. Vlevo vyberte **Nasadit produkci**.
3. Klikněte na **Run workflow** a ponechte větev **main**.
4. Do potvrzovacího pole napište přesně `NASADIT`.
5. Klikněte na zelené **Run workflow** a počkejte, až jsou všechny kroky zelené.

Workflow před změnou produkčních souborů vždy:

1. zkontroluje PHP a automatické testy;
2. ověří identitu SSH serveru proti předem uloženému otisku;
3. ověří existující `config.php`, `AUTH_RATE_LIMIT_PEPPER`, PHP rozšíření a
   dostupnost `rsync` na serveru;
4. vytvoří konzistentní databázovou zálohu mimo webový adresář a ověří její
   kompresi, kontrolní součet a manifest;
5. zastaví se, pokud záloha není úplná nebo ověřitelná.

Teprve potom připraví celý release v neveřejném adresáři
`~/.evidence-deploy/releases/<commit>-<běh>`, dočasně do něj s právy 0600 zkopíruje
produkční `config.php`, spustí migrace z tohoto release a až po jejich úspěchu
aktivuje PHP soubory do webrootu. Nakonec provede veřejný HTTP test a kopii
konfigurace z release odstraní. Tajné údaje se neposílají v URL ani v logu.

> Aktivace do současného webrootu stále používá `rsync` bez `--delete`, protože
> podpora atomického přepnutí symlinkem není na hostingu potvrzená. Release je
> ale předem kompletní a migrace nikdy neběží z napůl nahraného webrootu. Pokud
> aktivace selže, produkční záloha existuje a stejný workflow lze po odstranění
> příčiny bezpečně zopakovat.

## Jednorázové nastavení GitHubu

V repozitáři otevřete **Settings → Secrets and variables → Actions** a vytvořte:

| Secret | Co do něj patří |
|---|---|
| `SSH_HOST` | SSH/SFTP adresa z administrace hostingu |
| `SSH_PORT` | SSH port z administrace hostingu; pokud je skutečně 22, může být `22` |
| `SSH_USER` | hlavní uživatel domény |
| `SSH_PRIVATE_KEY` | celý soukromý klíč odpovídající `authorized_keys` na serveru |
| `SSH_KNOWN_HOSTS` | ověřený řádek veřejného hostitelského klíče serveru |

`DEPLOY_TOKEN` ani `PROD_CONFIG` se už nepoužívají. `config.php` musí být na
serveru nahraný před prvním nasazením, například přes Total Commander. Workflow
jej nikdy nevytváří ani nepřepisuje, protože před první ověřenou zálohou nesmí
měnit aplikační adresář.

### Povinný pepper v produkčním `config.php`

Před nasazením auth změn musí produkční `config.php` obsahovat unikátní tajný
řetězec dlouhý nejméně 32 znaků:

```php
define('AUTH_RATE_LIMIT_PEPPER', 'sem-patří-dlouhá-náhodná-hodnota');
```

Bezpečnou hodnotu lze lokálně vygenerovat příkazem:

```powershell
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Výsledek vložte přes SFTP pouze do produkčního `config.php`. Nikdy jej
nevkládejte do Gitu, GitHub Secrets tohoto workflow, dokumentace ani chatu.
Workflow kontroluje pouze existenci a minimální délku; hodnotu nevypisuje.

### Jak připravit `SSH_KNOWN_HOSTS`

Tento Secret brání tomu, aby se workflow připojilo k podvrženému serveru.
Samotné spuštění `ssh-keyscan` není ověření identity. Správný SHA256 fingerprint
hostitelského klíče nejdřív potvrďte nezávisle v administraci nebo u podpory
hostingu. Teprve potom na svém počítači načtěte veřejný klíč:

```powershell
ssh-keyscan -p SSH_PORT SSH_HOST
```

Porovnejte fingerprint získaného klíče s hodnotou potvrzenou hostingem. Ověřený
celý řádek z `ssh-keyscan` vložte do `SSH_KNOWN_HOSTS`. Pro jiný port než 22 má
hostitel v řádku tvar `[server.example.cz]:2222`. Workflow si klíč samo nikdy
nestahuje a při chybějícím nebo jiném záznamu skončí před připojením.

## Co zůstává na serveru

`rsync` záměrně nepoužívá `--delete` a nepřepisuje:

- `config.php`;
- uploady, obrázky, stories a reporty;
- logy a staré zálohy;
- testy a vývojové nastavení PHPUnit.

Soubory odstraněné z Gitu proto na serveru automaticky nezmizí. Jejich ruční
mazání přes Total Commander provádějte jen podle konkrétního release pokynu.

## Databázová záloha

Před každým deployem se aktuální `bin/db-backup.php` nahraje do
`~/.evidence-deploy/` a spustí se ještě proti staré produkční aplikaci. Zálohy
jsou v `~/.evidence-backups/`, tedy mimo veřejný web, s právy 0700/0600. Každá
záloha má tři soubory:

- `evidence_*.sql.gz` – komprimovaný SQL dump;
- `evidence_*.sha256` – kontrolní součet;
- `evidence_*.manifest.json` – seznam tabulek, počty řádků, triggery a metadata.

Platná je pouze kompletní trojice se stejným názvem a shodným SHA256. Manifest
se přejmenuje jako poslední dokončovací marker; při chybě nástroj nově vzniklé
části sady uklidí a deploy zastaví.

Drží se posledních 20 sad. Databáze je sdílená: nástroj používá explicitní
seznam tabulek vlastněných Evidencí. Nezahrnuje `jidlo_*`, `bar_*`, `results_*`
ani legacy/archivní tabulky. Nová tabulka Evidence musí být přidána do konstanty
`EVIDENCE_TABLES` v `bin/db-backup.php` současně s migrací.

Záloha se zastaví, pokud narazí na view, jiný engine než InnoDB, cizí klíč přes
hranici aplikací nebo nepodporovanou definici triggeru. Triggery vlastněných
tabulek jsou součástí dumpu.

## Když workflow zčervená

- Před krokem **Připravit kompletní release**: produkční kód ani databáze se
  nezměnily. Opravte uvedený Secret, konfiguraci, test nebo problém zálohy.
- V kroku **Připravit kompletní release**: webroot ani databáze se ještě
  nezměnily a ověřená záloha existuje. Po opravě spojení spusťte workflow znovu.
- V migracích: webroot stále obsahuje předchozí PHP. Nic neobnovujte automaticky.
  Uložte log běhu, zastavte další
  deploy a postupujte podle [OBNOVA-DATABAZE.md](OBNOVA-DATABAZE.md).
- V kroku **Aktivovat ověřený release** nebo v HTTP testu: migrace už proběhly.
  Zkontrolujte PHP error log a
  nespouštějte další změnu naslepo.

Vrácení staršího commitu vrátí pouze kód. Nevrací automaticky databázové změny.
Produkční obnova databáze je vždy ruční, řízený zásah.

## Ruční CLI záloha přes SSH

Pokročilý správce ji může spustit bez URL tokenu:

```bash
umask 077
APP_HOST=data.kovopraha.cz php "$HOME/.evidence-deploy/db-backup.php" \
  --app-root="$HOME/data.kovopraha.cz/evidence" \
  --backup-dir="$HOME/.evidence-backups" \
  --keep=20 --json
```

`bin/.htaccess` zůstává `Require all denied` a `bin/zaloha.php` vrací přes web
404 i v případě, že hosting `.htaccess` nepoužije.
## Soukromé přílohy a kanonická adresa

Před dalším produkčním deployem musí konfigurace obsahovat důvěryhodnou
`APP_BASE_URL=https://data.kovopraha.cz/evidence` a absolutní
`APP_PRIVATE_STORAGE_ROOT` mimo webroot. Po záloze spusťte nejprve:

```bash
php bin/migrate-private-files.php
php bin/migrate-private-files.php --apply
php bin/migrate-private-files.php
```

Poslední dry-run smí hlásit pouze `already_private`; `missing` a `errors` musí být
nula. Produkční adresář musí být zapisovatelný pouze aplikačním uživatelem. Nový
kód se nesmí přepnout před zálohou a úspěšným převodem.
