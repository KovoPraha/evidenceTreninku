# Aktuální předání vývoje na další stanici

Aktualizováno: 9. 10. 2026, Europe/Prague

Tento dokument je stabilní vstupní bod pro další počítač nebo vývojáře.
Autoritativní zdroj je vždy vzdálená větev `origin/main` repozitáře
<https://github.com/KovoPraha/evidenceTreninku>. SHA uvedené níže je kontrolní
snapshot z data aktualizace, nikoli náhrada za nový `git fetch`.

## Ověřený bod předání

| Položka | Hodnota |
|---|---|
| Větev | `main`; přesné aktuální SHA zjistí nový clone pomocí `git rev-parse origin/main` |
| `origin/main` při kontrole | `16b7d4d60883f3f4f655c097339339c68c1be07d` |
| Nasazený aplikační commit | `16b7d4d60883f3f4f655c097339339c68c1be07d` |
| Otevřené pull requesty | žádné při kontrole 9. 10. 2026 |
| CI aktuálního `main` | [běh 37886667244](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37886667244), úspěch |
| CI nasazeného aplikačního commitu | [běh 37886667244](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37886667244), úspěch |
| CodeQL nasazeného commitu | [běh 37886667406](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37886667406), Actions a JavaScript/TypeScript úspěch |
| Produkční deploy | [běh 37886677875](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37886677875), úspěch, `uat_schvaleno=true` |
| UAT připravenost | [běh 37886988895](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37886988895), blokováno vypršeným oknem `KIS_UAT_WINDOW_END` |
| Produkční invarianty | [běh 37887161804](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37887161804), 16/16 bez porušení |
| Obnova zálohy | [běh 37701691548](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37701691548), 195 tabulek a 2 triggery |
| Produkční fronta zpráv | [běh 37606107788](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37606107788), úspěch |
| CodeQL default setup | [běh 37764312992](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37764312992), Actions a JavaScript/TypeScript bez otevřeného nálezu |
| Produkce | <https://kis.kovopraha.cz/> |

UX navigace, kalendářů, e-shopu a pracovních přehledů je popsána v
[`UX-NAVIGACE-A-KALENDARE-2026-10-08.md`](UX-NAVIGACE-A-KALENDARE-2026-10-08.md).
Nová stanice musí před další změnou ověřit aktuální SHA a poslední produkční
běh; stavové hodnoty v tomto dokumentu se po každém release aktualizují
samostatným dokumentačním commitem.

CI nad aplikačním commitem ověřilo 877 testů / 11 995 kontrol a MariaDB 10.3 i
11.4. Produkce běží na tomto commitu; dokumentace může být na `main` novější.
Po deployi prošla z prohlížeče domovská stránka, e-shop, veřejný rozvrh,
klubový kalendář, velodrom a přihlášení bez fatálních chyb a bez chyb či
varování v konzoli. Databázové invarianty jsou zelené. Poslední deploy má
`uat_schvaleno=true`, ale nové zápisové UAT je blokováno vypršeným časovým
oknem z 8. 10. 2026; jeho prodloužení vyžaduje nové rozhodnutí vlastníka.

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

`main` má vynucené aktuální tři CI kontroly, strict aktualizaci větve,
zamítnutí zastaralých review a pravidla platí i pro administrátora. Povinný
počet schválení zůstává dočasně `0`, protože repozitář i organizace mají pouze
jediného člověka s přístupem (`mmmixamarek-source`); hodnota `1` by znemožnila
schválit vlastní pull request. Jakmile bude přidán druhý důvěryhodný reviewer,
zvyšte počet na `1` a zapněte stejného reviewera také pro prostředí
`production`.

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
