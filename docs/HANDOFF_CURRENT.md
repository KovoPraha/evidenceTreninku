# Aktuální předání vývoje na další stanici

Aktualizováno: 7. 10. 2026, Europe/Prague

Tento dokument je stabilní vstupní bod pro další počítač nebo vývojáře.
Autoritativní zdroj je vždy vzdálená větev `origin/main` repozitáře
<https://github.com/KovoPraha/evidenceTreninku>. SHA uvedené níže je kontrolní
snapshot z data aktualizace, nikoli náhrada za nový `git fetch`.

## Ověřený bod předání

| Položka | Hodnota |
|---|---|
| Větev | `main`; přesné aktuální SHA zjistí nový clone pomocí `git rev-parse origin/main` |
| `origin/main` při kontrole | `f5619222402436d7a00e7e720318886bcaffb97a` |
| Nasazený aplikační commit | `49c38c72c6704826182aff7f59855f47b5818bb8` |
| Otevřené pull requesty | žádné při kontrole 7. 10. 2026 |
| CI aktuálního `main` | [běh 37636955701](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37636955701), úspěch |
| CI nasazeného aplikačního commitu | [běh 37635967824](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37635967824), úspěch |
| Produkční deploy | [běh 37636084395](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37636084395), úspěch, `uat_schvaleno=true` |
| UAT připravenost | [běh 37636447561](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37636447561), `ready=true` |
| Produkční invarianty | [běh 37636671525](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37636671525), 16/16 bez porušení |
| Produkční fronta zpráv | [běh 37606107788](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37606107788), úspěch |
| Produkce | <https://kis.kovopraha.cz/> |

CI nad aplikačním commitem ověřilo 840 testů / 11 751 kontrol a MariaDB 10.3 i
11.4. Produkce běží na tomto commitu; dokumentace může být na `main` novější.
Poslední deploy má `uat_schvaleno=true` a navazující připravenost i invarianty
prošly. Toto potvrzení se vztahuje pouze k uvedenému release a testovacímu oknu.

Automatický Fio import je dočasně vypnutý. Workflow zůstává jen ručně
spustitelné a produkční `FIO_IMPORT_ENABLED` má zůstat `false`, dokud vlastník
znovu neschválí token, účet a provozní dohled.

## Nová stanice: čisté převzetí

```powershell
cd C:\xampp\htdocs
git clone https://github.com/KovoPraha/evidenceTreninku.git evidencePavel
cd evidencePavel
git fetch --prune origin
git switch main
git pull --ff-only origin main
git rev-parse HEAD
git rev-parse origin/main
```

Poslední dva příkazy musí vrátit stejné SHA. Potom spusťte
`PRIPRAVIT_LOCALHOST_TESTOVANI.cmd`. Skript připraví ignorovaný `config.php`,
oddělenou lokální MariaDB na portu 3308, migrace a syntetická demo data. Pro
další starty slouží `START_LOCALHOST_TESTOVANI.cmd`.

## Existující clone: bezpečná synchronizace

Nejdřív zjistěte, zda stanice neobsahuje rozpracovanou práci:

```powershell
git status --short --branch
git fetch --prune origin
git branch --show-current
git rev-list --left-right --count HEAD...origin/main
```

Pokud je pracovní strom čistý a lokální `main` nemá vlastní commity:

```powershell
git switch main
git pull --ff-only origin main
```

Pokud jsou přítomné lokální změny nebo vlastní commity, nepoužívejte force,
reset ani přepis souborů. Nejdřív práci uložte do samostatné větve a teprve
potom synchronizujte `main`. Cizí rozpracované změny se nesmí přidat do nového
commitu jen proto, že leží ve stejném adresáři.

## Zahájení nové práce

```powershell
git fetch --prune origin
git switch -c codex/kratky-popis origin/main
```

Před commitem zkontrolujte změněné soubory a spusťte přiměřené testy. Úplná
základní brána je:

```powershell
composer install
composer validate --strict
composer test
$env:APP_HOST='localhost'
php bin/migrate.php --check --json
git diff --check
```

Nová migrace musí být nový neměnný soubor v `migrations/`. Již přijatá migrace
se neupravuje. Pokud přidává tabulku vlastněnou aplikací, musí se současně
aktualizovat zálohovací ownership kontrakt v `bin/db-backup.php` a jeho testy.

## GitHub pracovní postup

1. Pushnout pouze vlastní pracovní větev.
2. Otevřít pull request do `main`.
3. Počkat na PHPUnit a MariaDB 10.3/11.4 kontroly.
4. Před merge znovu ověřit, zda se `origin/main` mezitím neposunul.
5. Sloučit až po zelených kontrolách.
6. Po merge provést `git fetch --prune origin` a ověřit výsledný SHA.

Push ani merge není produkční nasazení. Produkční workflow se spouští ručně a
jen po výslovném rozhodnutí podle [`NASAZENI.md`](NASAZENI.md).

## Co není v GitHubu

- `config.php` a jakákoli tajemství;
- lokální nebo produkční databáze a obnovovací dumpy;
- skutečné osobní údaje, importy a přílohy;
- lokální outboxy, session a cache;
- `vendor/`, který obnoví Composer podle `composer.lock`.

Tyto výjimky jsou bezpečnostní hranice, nikoli chybějící část synchronizace.

## Kde hledat aktuální informace

- stav GitHubu, produkce a posledních oprav: [`CURRENT_STATE.md`](CURRENT_STATE.md);
- dokumentační rozcestník: [`README.md`](README.md);
- lokální instalace: [`localhost-testovani.md`](localhost-testovani.md);
- produkční nasazení: [`NASAZENI.md`](NASAZENI.md);
- produkční UAT: [`PRODUKCNI-UZIVATELSKE-TESTOVANI.md`](PRODUKCNI-UZIVATELSKE-TESTOVANI.md);
- pravidla migrací: [`../migrations/README.md`](../migrations/README.md).

Datované dokumenty jako `HANDOFF_2026-08-29.md`, auditní zprávy a implementační
prompty jsou historické záznamy. Při rozporu nemají přednost před aktuálním
kódem, `origin/main`, CI ani tímto dokumentem.
