# Kompletní localhost demo

Tento postup je pouze pro lokální XAMPP databázi. Produkci, GitHub ani externí
KIS/Fio nemění.

## Obnovení prostředí

```powershell
$env:APP_HOST='localhost'
php bin/migrate.php --apply --json
php bin/shoptet-products-dry-run.php --input=var/imports/shoptet-products.csv --json
php bin/shoptet-products-stage.php --input=var/imports/shoptet-products.csv --apply --json
php bin/seed-local-demo.php
```

Seed je idempotentní a mimo localhost skončí chybou. Připraví:

- ověřený účet rodiče se dvěma schválenými osobami,
- localhost administrátora,
- reálný Shoptet katalog v pracovním stavu a jeden publikovaný skladový produkt,
- kupón `LOCAL10`,
- bezplatný kroužek s kapacitou dvě místa,
- sezónu `2026/27`, tým `LOCALHOST U15` a dva členy soupisky.

Aktuální testovací přístupy vypíše přímo seed. Výchozí hodnoty jsou:

```text
Zákazník: rodic@localhost.test / Localhost123!
Administrátor: localhost-admin / LocalhostAdmin123!
```

Lokální checkout musí mít v ignorovaném `config.php` syntetické bankovní údaje.
Použitý účet má neexistující bankovní kód `9999` a označení
`LOCALHOST TEST - NEPLATIT`. QR slouží jen pro kontrolu obrazovky; nesmí se
skenovat pro skutečnou platbu. Fio import zůstává vypnutý.

## Doporučený průchod

1. Přihlásit zákazníka na `booking/prihlaseni.php`.
2. Zkontrolovat dvě osoby na `booking/moje_osoby.php`.
3. Přihlásit dítě na `booking/krouzky.php`.
4. Na `booking/eshop.php` vložit skladovou variantu do košíku.
5. Použít `LOCAL10` a vytvořit objednávku.
6. Zkontrolovat označení testovacího účtu, částku, VS a QR.
7. Přihlásit localhost administrátora na `login.php`.
8. Projít objednávky, Fio shadow přehled a `kis_rosters_admin.php`.

Před větší změnou lokální DB používejte `bin/db-backup.php` s cílem mimo webroot.
