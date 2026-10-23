# Izolované ověření balíku B1

Tento postup zakládá výhradně syntetické prostředí mimo stávající XAMPP databáze. Nespouští produkční deploy, import původních osob, platební bránu, bankovní import ani frontu skutečných e-mailů. Běžný `config.php` odmítne přepsat. Soubory s osobními údaji ani tajemství z jiného checkoutu se nekopírují.

## Příprava

V odděleném checkoutu s daným `composer.lock`:

```powershell
composer install --no-interaction --prefer-dist --no-progress
.\bin\setup-finance-b1-sandbox.ps1
& C:\xampp\php\php.exe -c "$env:LOCALAPPDATA\EvidencePavelB1\finance-b1-20261022\php-web.ini" tests/Support/FinanceB1IsolationCheck.php
```

Instalace knihoven respektuje lock; nepoužívat `composer update`. Příprava načte pouze verzované prázdné `database/local-demo-schema.sql` a řízené migrace. Široký demo seed se nespouští. Scénáře vytvářejí vlastní označené syntetické záznamy. Spouštěč nepřepíná sdílený Apache ani stávající MariaDB. Nové procesy mají skryté okno a naslouchají pouze na loopbacku.

| Součást | Hodnota |
|---|---|
| HTTP | `http://127.0.0.1:18086` |
| MariaDB | `127.0.0.1:33426`, databáze `evidence_b1` |
| App uživatel | `b1_app`, bez hesla, omezený na tři syntetická schémata této nové instance |
| Oddělená smoke schémata | `evidence_b1_rewards`, `evidence_b1_receipts` |
| Runtime, DB soubory, důkazy | `%LOCALAPPDATA%\EvidencePavelB1\finance-b1-20261022` |
| Soukromé přílohy | podadresář `private`, mimo veřejný webroot |
| PHP sessions | podadresář `sessions`, oddělený od běžných přihlášení |
| Lokální zachycení zpráv | podadresář `outbox`, helper `localMessageOutboxSender` |
| Přesný popis prostředí | `environment.json` |

Webový proces používá vlastní `php-web.ini`: zakázané `mail`, cURL přenosy, socket klienti, URL streamy a spouštění externích procesů; nenačítá systémovou PHP konfiguraci. Konfigurace má Fio, automatické potvrzení, Stripe, SumUp a starší bridge vypnuté. Všechna příchozí data jsou syntetická. Lokální sink je explicitní testovací transport; přímé `mail()` tvrdě selže a není tiše přesměrováno do skutečné pošty. Bankovní účet není nastaven. Cron ani workers nejsou spuštěné.

`FinanceB1IsolationCheck.php` ověří skutečný serverový port a datadir, soukromé úložiště, vypnuté funkce/příznaky a uloží jednu zprávu s adresou `example.invalid` pouze do lokálního sinku. Výsledek uloží do `isolation-check.json`. Pro HTTP klienta a procesové souběžné testy se používá běžný CLI PHP; zakázané síťové funkce platí pro aplikaci obsluhující webové requesty, nikoli pro testovacího klienta. Testovací klient musí před zápisem odmítnout jiný endpoint/databázi.

## Ověření a ukončení

```powershell
& C:\xampp\php\php.exe vendor/bin/phpunit
& C:\xampp\php\php.exe tests/Support/FinanceB1HttpAcceptance.php
& C:\xampp\php\php.exe tests/Support/FinancialEventMariaDbRace.php
& C:\xampp\php\php.exe bin/finance-b1-reward-concurrency.php
.\bin\stop-finance-b1-sandbox.ps1
```

Souběžné testy vyžadují spuštěnou dedikovanou MariaDB. Jednotlivé testy smějí měnit pouze vlastní syntetické případy; za běhu ostatních testů se neresetuje celé schéma. Stop skript před zastavením ověřuje vlastníka adresáře, příkazovou řádku procesu a loopback port. MariaDB ukončuje standardně přes `mysqladmin shutdown`; data ani protokoly nemaže. Opakované spuštění zachová syntetická data. B1 ověření samo není uživatelským převzetím hospodářem ani souhlasem s produkčním nasazením.

Stop čeká také na ukončení procesu a setup ověřuje úspěšný SQL dotaz před přípravou schémat. Celý cyklus stop → setup → kontrola izolace byl lokálně ověřen. Výsledky implementace a mapování přejímky: [FINANCE_B1_IMPLEMENTATION.md](FINANCE_B1_IMPLEMENTATION.md).
