# EvidencePavel

Samostatná klubová aplikace pro evidenci tréninků, osoby a soupisky KIS,
rodinné účty, e-shop, programy, události a rezervace sportovišť. Backend je PHP
8.2+ nad MariaDB; projekt se lokálně provozuje v XAMPP.

## Převzetí vývoje na novém počítači

1. Nainstalujte XAMPP s PHP 8.2+ a Composer.
2. Naklonujte repozitář do `C:\xampp\htdocs\evidencePavel`.
3. V kořeni spusťte `PRIPRAVIT_LOCALHOST_TESTOVANI.cmd`.
4. Po dokončení otevřete `http://localhost/evidencePavel/`.
5. Pro další offline spouštění používejte `START_LOCALHOST_TESTOVANI.cmd`.

První příprava vytvoří ignorovaný `config.php`, samostatnou MariaDB na portu
3308, aplikuje všechny migrace a vloží výhradně syntetická demo data. Zamčené
PHP závislosti se instalují přes Composer, takže tento první krok proveďte ještě
s připojením k internetu. Bootstrap, ikony a písma aplikace už jsou v repozitáři
a samotné testování potom internet nepotřebuje.

Podrobnosti:

- [předání na další stanici](docs/HANDOFF_2026-08-29.md),
- [výsledky úplného ověření předání](docs/VERIFICATION_2026-08-29.md),
- [localhost instalace a demo](docs/localhost-testovani.md),
- [samostatný offline testovací návod](outputs/localhost-test-2026-08-25/OFFLINE_TESTOVANI.md),
- [aktuální stav a hranice projektu](docs/CURRENT_STATE.md),
- [datové toky v kódu](outputs/data-flow-audit-2026-08-24/DATOVE_TOKY_KOD.md),
- [nálezy a rizika](outputs/data-flow-audit-2026-08-24/NALEZY_A_RIZIKA.md).

## Vývojová kontrola

```powershell
composer install
composer test
$env:APP_HOST='localhost'
php bin/migrate.php --check --json
```

`config.php`, lokální databáze, produkční tajemství, importy a obnovovací dumpy
nepatří do Gitu. Push do GitHubu sám o sobě neprovádí produkční nasazení; to je
samostatný, výslovně schvalovaný workflow.
