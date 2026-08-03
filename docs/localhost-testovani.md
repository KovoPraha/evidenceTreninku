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
- bezplatnou i placenou událost cílenou na více soupisek,
- věkovou řadu U13 → U15 → U17, dráhovou i silniční soupisku a rollover výjimku,
- sezónu `2026/27`, tým `LOCALHOST U15` a členy testovacích soupisek.

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
4. Na `booking/krouzky.php` vložit placené soustředění `NEPLATIT` pro oprávněné dítě do košíku.
5. Na `booking/eshop.php` zkontrolovat účastníka, snapshot souhlasu a cenu.
6. Vytvořit objednávku a zkontrolovat testovací QR označené `NEPLATIT`.
7. V administraci objednávek ručně potvrdit syntetickou úhradu a ověřit aktivní přihlášku.
8. Samostatně lze vložit skladovou variantu, použít `LOCAL10` a projít skladový tok.
9. Přihlásit localhost administrátora na `login.php`.
10. Projít objednávky, audit osoby a `kis_rosters_admin.php`.

Před větší změnou lokální DB používejte `bin/db-backup.php` s cílem mimo webroot.
