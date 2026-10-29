# Převzetí vývoje na další stanici

Stav nasazení, integrací, CI a otevřené provozní body jsou výhradně v
[CURRENT_STATE.md](CURRENT_STATE.md). Dřívější snapshoty zachovává
[CHANGELOG-PROVOZ.md](CHANGELOG-PROVOZ.md). Před změnou čti také [CLAUDE.md](../CLAUDE.md).

## Nová stanice: čisté převzetí

```powershell
cd C:\Users\Marek\Documents\Codex
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

## Pull request a předání

Vlastní větev odešli, otevři PR a ověř zelené kontroly pro přesný head commit.
Před případným sloučením znovu fetchni main a ověř aktuální pravidla repozitáře.
Merge a produkční deploy jsou samostatná rozhodnutí vlastníka podle
[NASAZENI.md](NASAZENI.md); samotné předání, push ani zelené CI je nepovolují.

## Průběžný plán

Navazující [komplexní refaktoring celé aplikace](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md)
je sloučený v PR #219 a vlastník zahájil autonomní realizaci. Vlny 1–5 jsou
dokončené; [první K0/W6.1 balík](REFACTORING_K0_W6_2026-10-29.md) je sloučený
v PR #220. [W6.2](REFACTORING_WAVE6_2026-10-29.md), oddělení čistého výpočtu
kapacit, je ověřené a nasazené. Následuje W6.3: měření migrovaného schématu
před dávkovou optimalizací. Plošné měření K0.2 zůstává průběžné.

Před větší implementací ulož konkrétní plán do docs/; zaznamenej rozsah
oprávnění, větev, pracovní kopii, dokončené kroky, testy a další krok.
Před předáním/kompaktací jej obnov a při pokračování znovu přečti.
Aktuální práce dozoru: [pět balíků a jejich hranice](DOZOR_2026-10-25.md).
Navazující opravy a důkazy: [F1–F3 po nezávislé přejímce](DOZOR_F1_F3_2026-10-26.md).

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
