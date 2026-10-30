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
kapacit, je ověřené a nasazené. [W6.3](REFACTORING_W63_2026-10-29.md) doplnilo
migrovaný profil a postupné čtení s úsporou paměti; je také nasazené.
[W7.1a](REFACTORING_W71_2026-10-29.md) nasadilo cílené ověření rodinného přístupu
s rozpočtem čtyř SQL. [W7.1b](REFACTORING_W71B_2026-10-29.md) opravilo
revokaci během čekání RSVP/náhrad: aktuální zamykající kontrola před zápisem,
32/32 souběhů v obou MariaDB CI. [W7.2](REFACTORING_W72_2026-10-29.md)
nasadilo cílené načítání jednotlivých profilů: celý GET pro 20 osob má
2706 → 2326 SQL a shodná data/HTML; CI medián o 10–12 % nižší.
[W7.3](REFACTORING_W73_2026-10-30.md) je také nasazené: metadata během GET
sdílená v rámci PDO, SQL 2244 → 752 a metadata 1590 → 98 pro 20 osob.
Výstup, historie a aktuální oprávnění zachovány, POST bez cache.
[W7.4](REFACTORING_W74_2026-10-30.md) je nasazena: členské dotazy 160 → 27,
celý GET 752 → 619 SQL, stejné výsledky a čerstvá autorizace.
[W7.5](REFACTORING_W75_2026-10-30.md) je nasazena: stránka 50 z 10 000 osob
načítá 53 místo 10 003 řádků; CI paměť stránky 100 klesla z 36,8 na 0,43 MB.
[Provozní důkazy](verification/refactoring-w75-release-20261030/RESULTS.md).
Navazuje W7.6 / W8.1: zbývající R03/R04 a časová osa. Vlastník autorizoval
autonomní pokračování až do technického dokončení W15 včetně sloučení a nasazení
po úspěšném ověření; nový pokyn pro každou vlnu není potřeba.
Plošné měření K0.2 zůstává otevřené;
dávkování kapacit mezi nabídkami je samostatný kandidát.

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
