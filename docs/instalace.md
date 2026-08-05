# Instalační příručka — Evidence tréninků

Krok za krokem od nulového serveru po funkční aplikaci.

---

## Obsah

1. [Požadavky](#1-požadavky)
2. [Instalace XAMPP (Windows)](#2-instalace-xampp-windows)
3. [Instalace na Linux (produkce)](#3-instalace-na-linux-produkce)
4. [Stažení aplikace](#4-stažení-aplikace)
5. [Databáze](#5-databáze)
6. [PHP závislosti (Composer)](#6-php-závislosti-composer)
7. [První spuštění a ověření](#7-první-spuštění-a-ověření)
8. [Vytvoření prvního administrátora](#8-vytvoření-prvního-administrátora)
9. [Web Push notifikace (volitelné)](#9-web-push-notifikace-volitelné)
10. [Cron — automatické upomínky](#10-cron--automatické-upomínky)
11. [Produkční checklist](#11-produkční-checklist)
12. [Řešení problémů](#12-řešení-problémů)

---

## 1. Požadavky

| Komponenta | Minimální verze | Poznámka |
|-----------|----------------|----------|
| PHP | 8.1+ | rozšíření: `pdo_mysql`, `mbstring`, `gd`, `openssl`, `zip` |
| MySQL / MariaDB | 8.0 / 10.6+ | utf8mb4 |
| Apache | 2.4+ | `mod_rewrite` (volitelné) |
| Composer | 2.x | správce PHP závislostí |
| HTTPS | — | vyžadováno pro Web Push (produkce) |

Ověření verzí v příkazovém řádku:
```bash
php -v
mysql --version
composer --version
```

---

## 2. Instalace XAMPP (Windows)

### 2a) Stáhnout a nainstalovat XAMPP

```powershell
# Stáhnout XAMPP installer ze:
# https://www.apachefriends.org/download.html
# Vyberte verzi s PHP 8.x

# Po instalaci spusťte XAMPP Control Panel
# a klikněte Start u Apache a MySQL
```

### 2b) Ověřit PHP

```powershell
# Otevřít PowerShell nebo CMD
C:\xampp\php\php.exe -v
# Mělo by se zobrazit: PHP 8.x.x

# Přidat PHP do PATH (jednorázově na systém):
[System.Environment]::SetEnvironmentVariable(
    "Path",
    $env:Path + ";C:\xampp\php;C:\xampp\mysql\bin",
    [System.EnvironmentVariableTarget]::Machine
)
# Restartovat PowerShell aby se PATH projevila
php -v
mysql --version
```

### 2c) Nastavit MySQL

```powershell
# XAMPP MySQL defaultně: uživatel root, bez hesla
# Pro produkci nastavte heslo přes phpMyAdmin nebo:
mysql -u root -e "ALTER USER 'root'@'localhost' IDENTIFIED BY 'vase_heslo';"
```

---

## 3. Instalace na Linux (produkce)

```bash
# Ubuntu / Debian
sudo apt update
sudo apt install -y apache2 mysql-server php8.2 php8.2-{mysql,mbstring,gd,openssl,zip,xml,curl} \
    libapache2-mod-php8.2 unzip git

# Ověření
php -v
mysql --version
apache2 -v

# Povolení Apache modulů
sudo a2enmod rewrite
sudo systemctl restart apache2

# Zabezpečení MySQL
sudo mysql_secure_installation

# Instalace Composeru
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

---

## 4. Stažení aplikace

### Windows (XAMPP)

```powershell
# Zkopírujte složku aplikace do:
# C:\xampp\htdocs\evidencePavel\

# Nebo naklonujte z git repozitáře (pokud existuje):
cd C:\xampp\htdocs
git clone https://github.com/vas-repo/evidence.git evidencePavel
```

### Linux (produkce)

```bash
# Cílová složka webového serveru
cd /var/www/html
sudo git clone https://github.com/vas-repo/evidence.git evidencePavel
sudo chown -R www-data:www-data evidencePavel
sudo chmod -R 755 evidencePavel

# Složky kam aplikace zapisuje — musí být zapisovatelné
sudo chmod -R 775 evidencePavel/nahrane_obrazky
sudo chmod -R 775 evidencePavel/uploads
sudo chmod -R 775 evidencePavel/stories
```

---

## 5. Databáze

### 5a) Vytvoření databáze

```bash
# Přihlásit se do MySQL
mysql -u root -p

# V MySQL konzoli:
CREATE DATABASE evidence
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER 'evidence_user'@'localhost' IDENTIFIED BY 'silne_heslo_zde';
GRANT ALL PRIVILEGES ON evidence.* TO 'evidence_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 5b) Základní tabulky (nutné před prvním spuštěním)

Aplikace používá auto-migraci pro většinu tabulek, ale základní struktury
(sportovci, trenéři, skupiny…) musí existovat. Importujte základní schéma:

```bash
# Pokud máte SQL dump z existující instalace:
mysql -u root -p evidence < dump_schema.sql

# NEBO vytvořte jen tabulku treneri (minimum pro login):
mysql -u root -p evidence << 'EOF'
CREATE TABLE IF NOT EXISTS treneri (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    jmeno    VARCHAR(80) NOT NULL,
    email    VARCHAR(160) NULL,
    heslo    VARCHAR(255) NOT NULL,
    role     ENUM('trener','hlavni','admin') NOT NULL DEFAULT 'trener',
    aktivni  TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nastaveni (
    klic     VARCHAR(80) PRIMARY KEY,
    hodnota  TEXT NULL,
    upraveno TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
EOF
```

### 5c) Nastavení přihlašovacích údajů k DB

Otevřete soubor `db.php` a upravte sekci s přihlašovacími údaji:

```php
// db.php — přibližně řádek 5–20
// Localhost (XAMPP):
$host = 'localhost';
$dbname = 'evidence';
$user = 'root';
$pass = '';       // nebo vaše heslo

// Produkce — upravte podle svého hostingu
```

---

## 6. PHP závislosti (Composer)

### 6a) Základní závislosti (povinné)

```bash
# Přejděte do složky aplikace
cd C:\xampp\htdocs\evidencePavel    # Windows
# nebo
cd /var/www/html/evidencePavel      # Linux

# Instalace závislostí (PhpSpreadsheet pro Excel exporty)
composer install

# Ověření — měl by existovat soubor vendor/autoload.php
ls vendor/autoload.php
```

### 6b) Web Push notifikace (volitelné)

Vyžadováno pouze pokud chcete používat push notifikace v prohlížeči:

```bash
composer require minishlink/web-push

# Ověření instalace
php -r "require 'vendor/autoload.php'; echo class_exists('Minishlink\WebPush\WebPush') ? 'OK' : 'CHYBA';"
```

---

## 7. První spuštění a ověření

### 7a) Spuštění (Windows XAMPP)

1. Otevřete XAMPP Control Panel
2. Klikněte **Start** u Apache a MySQL
3. Otevřete prohlížeč: `http://localhost/evidencePavel/`

### 7b) Spuštění (Linux)

```bash
# Apache by měl již běžet, ověřte:
sudo systemctl status apache2

# Aplikace dostupná na:
# http://vas-server/evidencePavel/
# nebo přes doménu pokud máte VirtualHost
```

### 7c) Databázové migrace

Před prvním přístupem spusťte explicitní migrační kontrolu a aplikaci. Webový
request není instalační ani nasazovací mechanismus.

```bash
php bin/migrate.php --check --json
php bin/migrate.php --apply
php bin/migrate.php --check --json
```

Výsledek musí mít `current: true`, legacy baseline `2.20.2` a prázdné `pending`.

---

## 8. Vytvoření prvního administrátora

```bash
# Spusťte PHP skript pro vytvoření admin účtu
php -r "
require 'db.php';
\$heslo = password_hash('VaseHeslo123', PASSWORD_BCRYPT);
\$pdo->prepare(\"INSERT INTO treneri (jmeno, email, heslo, role) VALUES (?,?,?,'admin')\")
    ->execute(['Admin', 'admin@kovopraha.cz', \$heslo]);
echo 'Admin vytvořen. ID: ' . \$pdo->lastInsertId() . PHP_EOL;
"

# Windows XAMPP — spusťte z adresáře aplikace:
cd C:\xampp\htdocs\evidencePavel
C:\xampp\php\php.exe -r "..."
```

Nyní se přihlaste na `http://localhost/evidencePavel/login.php`:
- **Jméno:** `Admin` (nebo email `admin@kovopraha.cz`)
- **Heslo:** `VaseHeslo123`

> ⚠️ Ihned po přihlášení změňte heslo přes Správa trenérů.

---

## 9. Web Push notifikace (volitelné)

Vyžaduje HTTPS (produkce). Na localhostu SW se zaregistruje, ale push nepřijde.

### 9a) Vygenerování VAPID klíčů

```bash
# Možnost 1 — přes Composer knihovnu (po instalaci minishlink/web-push):
vendor/bin/web-push generate-vapid-keys

# Možnost 2 — online generátor:
# https://vapidkeys.com/
# Zkopírujte Public Key a Private Key (Base64url formát)

# Možnost 3 — přes OpenSSL:
openssl ecparam -name prime256v1 -genkey -noout -out vapid_private.pem
openssl ec -in vapid_private.pem -pubout -out vapid_public.pem
# (pak je třeba převést do Base64url — doporučuji varianty 1 nebo 2)
```

### 9b) Uložení klíčů do databáze

```bash
mysql -u root -p evidence << 'EOF'
INSERT INTO nastaveni (klic, hodnota) VALUES
    ('push_vapid_public',  'BxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxX'),
    ('push_vapid_private', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'),
    ('push_vapid_subject', 'mailto:evidence@kovopraha.cz')
ON DUPLICATE KEY UPDATE hodnota = VALUES(hodnota);
EOF
```

### 9c) Ověření

```bash
# Zkontrolujte uložení klíčů:
mysql -u root -p evidence -e "SELECT klic, LEFT(hodnota,20) AS zacatek FROM nastaveni WHERE klic LIKE 'push%';"
```

---

## 10. Cron — automatické upomínky

Skript `cron_upominky.php` posílá emaily trenérům za nezaevidované tréninky.

### Linux (doporučeno)

```bash
# Otevřít crontab pro www-data nebo root
crontab -e

# Přidat řádek — spustit každý den v 7:00:
0 7 * * * php /var/www/html/evidencePavel/cron_upominky.php >> /var/log/evidence_upominky.log 2>&1
```

### Windows (Task Scheduler)

```powershell
# Vytvořit naplánovanou úlohu přes PowerShell:
$action  = New-ScheduledTaskAction -Execute "C:\xampp\php\php.exe" `
           -Argument "C:\xampp\htdocs\evidencePavel\cron_upominky.php"
$trigger = New-ScheduledTaskTrigger -Daily -At 7:00AM
Register-ScheduledTask -TaskName "EvidenceUpominky" `
    -Action $action -Trigger $trigger -RunLevel Highest
```

### Manuální test

```bash
# Otestovat skript ručně (CLI):
php /cesta/k/evidencePavel/cron_upominky.php

# Nebo přes webový prohlížeč (se secret tokenem):
curl "https://data.kovopraha.cz/evidence/cron_upominky.php?secret=$UPOMINKA_SECRET"
```

> Pred nasazenim na produkci nastavte `UPOMINKA_SECRET` mimo webroot, napr. v prostredi Apache/cron konfigurace.

---

## 11. Produkční checklist

```bash
# ── Bezpečnost ────────────────────────────────────────────────────────────────
# 1. Nastavit silné DB heslo (viz krok 5a)
# 2. Nastavit UPOMINKA_SECRET mimo webroot (Apache/cron prostredi)
# 3. Nastavit HTTPS (Let's Encrypt):
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d vase-domena.cz

# 4. Vypnout zobrazení chyb v PHP (php.ini nebo .htaccess):
echo "display_errors = Off" >> /etc/php/8.2/apache2/php.ini
echo "log_errors = On"      >> /etc/php/8.2/apache2/php.ini
sudo systemctl restart apache2

# ── Oprávnění souborů (Linux) ─────────────────────────────────────────────────
sudo find /var/www/html/evidencePavel -type d -exec chmod 755 {} \;
sudo find /var/www/html/evidencePavel -type f -exec chmod 644 {} \;
sudo chmod -R 775 /var/www/html/evidencePavel/{nahrane_obrazky,uploads,stories}
sudo chown -R www-data:www-data /var/www/html/evidencePavel

# ── Databáze ──────────────────────────────────────────────────────────────────
# 5. Zálohy (přidat do crontab):
# 0 3 * * * mysqldump -u root -pHESLO evidence > /backup/evidence_$(date +\%Y\%m\%d).sql

# ── Composer ──────────────────────────────────────────────────────────────────
# 6. Produkční install (bez dev závislostí):
composer install --no-dev --optimize-autoloader

# ── Ověření všeho ─────────────────────────────────────────────────────────────
php -r "
require 'db.php';
\$v = \$pdo->query('SELECT hodnota FROM nastaveni WHERE klic=\"schema_version\"')->fetchColumn();
echo 'DB schema: ' . \$v . PHP_EOL;
echo 'Composer: ' . (file_exists('vendor/autoload.php') ? 'OK' : 'CHYBI') . PHP_EOL;
echo 'Uploads:  ' . (is_writable('uploads') ? 'OK' : 'CHYBI write') . PHP_EOL;
echo 'Obrazky:  ' . (is_writable('nahrane_obrazky') ? 'OK' : 'CHYBI write') . PHP_EOL;
"
```

---

## 12. Řešení problémů

### Aplikace zobrazí prázdnou stránku nebo 500

```bash
# Zkontrolujte PHP error log:
tail -f /var/log/apache2/error.log        # Linux
# nebo:
Get-Content C:\xampp\apache\logs\error.log -Tail 50  # Windows PowerShell

# Zapněte dočasně zobrazení chyb:
php -r "require 'db.php';" 2>&1
```

### Auto-migrace nefunguje

```bash
# Ověřte připojení k DB:
php -r "
require 'db.php';
echo \$pdo ? 'DB OK' : 'DB CHYBA';
"

# Zkontrolujte tabulku nastaveni:
mysql -u root -p evidence -e "SELECT * FROM nastaveni WHERE klic='schema_version';"

# Bezpečná diagnostika katalogu a čekajících kroků:
php bin/migrate.php --check --json
```

Nikdy nenastavujte `schema_version` ručně na `0` a nespouštějte migrace návštěvou
webu. Pokud kontrola selže, obnovte ověřenou zálohu nebo chybu vyřešte v novém
číslovaném migračním kroku.

### Composer: „Class not found"

```bash
cd /cesta/k/evidencePavel
composer install
# nebo přegenerovat autoloader:
composer dump-autoload
```

### PHP rozšíření chybí

```bash
# Zjistit nainstalovaná rozšíření:
php -m | grep -E "pdo|mbstring|gd|openssl|zip"

# Doinstalovat (Ubuntu):
sudo apt install php8.2-{mysql,mbstring,gd,openssl,zip}
sudo systemctl restart apache2
```

### Nahrávání souborů nefunguje

```bash
# Zkontrolovat upload_max_filesize v php.ini:
php -i | grep upload_max_filesize
# Pokud je příliš malé, upravte php.ini:
# upload_max_filesize = 20M
# post_max_size = 25M
```

---

*Verze příručky: 2.17.0 — červen 2026*
