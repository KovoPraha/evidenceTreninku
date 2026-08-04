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
- desetiprocentní klubovou cenu prvního publikovaného produktu pro soupisku
  `LOCALHOST U15 2026`,
- kupón `LOCAL10`,
- bezplatnou i placenou událost cílenou na více soupisek,
- věkovou řadu U13 → U15 → U17, dráhovou i silniční soupisku a rollover výjimku,
- sezónu `2026/27`, tým `LOCALHOST U15` a členy testovacích soupisek.
- dnešní plánovaný trénink A07 cílený na U15 a dráhovou soupisku; po jeho
  zaevidování další spuštění seedu připraví nový čistý plán a historický trénink zachová.

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
9. Produkt otevřít nejprve odhlášený a ověřit veřejnou cenu i výzvu „Přihlásit pro
   zobrazení klubové ceny“; po přihlášení rodiče se zobrazí nižší cena a soupiska
   `LOCALHOST U15 2026`.
10. Přihlásit localhost administrátora na `login.php` a otevřít
    `eshop_member_prices_admin.php`.
11. Projít objednávky, klubové ceny, audit osoby a `kis_rosters_admin.php`.
12. Otevřít `kis_training_a07_admin.php`, zkontrolovat očekávané sportovce a přejít
    na zadání skutečné docházky. Po uložení průvodce ukáže přítomné podle plánu,
    chybějící a neočekávané; sportovec výsledek uvidí v `booking/muj_sport.php`.

Před větší změnou lokální DB používejte `bin/db-backup.php` s cílem mimo webroot.

## M2.3 – bezpečný KIS raw archiv

Archivace je zatím povolena pouze na localhostu. Výchozí příkaz pouze ukáže
metadata a nic nezapíše:

```powershell
$env:APP_HOST='localhost'
php bin\kis-archive-source.php `
  --input=C:\cesta\k\anonymnimu-kis-exportu.xlsx `
  --kind=users `
  --contract=kis-export-2026.1 `
  --archive-dir=C:\xampp\backups\evidence-local\kis-imports `
  --json
```

Teprve po kontrole hashe, velikosti a typu zdroje lze přidat
`--confirm-archive`. Archivní adresář musí předem existovat mimo
`C:\xampp\htdocs\evidencePavel`. Pro skutečná data nepoužívejte webroot,
cloudový odkaz ani sdílenou složku bez řízených oprávnění.
