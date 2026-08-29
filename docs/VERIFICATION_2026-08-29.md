# Ověření předání — 29. 8. 2026

Ověření proběhlo nad kompletním pracovním stromem připraveným pro tag
`handoff-2026-08-29`. Produkční nasazení nebylo spuštěno.

## Automatické kontroly

- `composer validate --strict --no-check-publish`: bez chyby,
- `composer audit --locked`: žádná známá bezpečnostní advisory,
- `composer check-platform-reqs`: všechny požadavky splněny na PHP 8.2.12,
- `composer test`: **746 testů, 10 295 assertions, bez chyby a deprecation**,
- PHP syntaxe: **584 first-party PHP souborů, 0 chyb**,
- PowerShell parser: oba localhost skripty, 0 chyb,
- `git diff --check`: bez whitespace chyby.

## Databáze a čistá instalace

Aktuální lokální instance na `127.0.0.1:3308`:

- migration check `ok: true`, `current: true`,
- katalog migrací: **70**,
- čekající migrace: **0**.

Samostatně byla ověřena instalace do nové izolované MariaDB:

- import verzovaného schématu vytvořil **190 prázdných tabulek**,
- následný migration check hlásil všech 70 migrací jako aktuálních,
- fixture vytvořila 2 syntetické produkty a 3 varianty,
- seed vytvořil výhradně localhostové osoby a účty,
- administrátor získal všech 8 pracovních pozic a superadmin oprávnění,
- opakované spuštění seedu nezměnilo počty (`2|4|1|7|1` před i po).

## Lokální HTTP a offline prostředky

- `http://localhost/evidencePavel/`: HTTP 200,
- lokální Bootstrap CSS: HTTP 200,
- lokální Bootstrap Icons CSS: HTTP 200,
- XLSX audit: 6 listů / 6 tabulek, bez chybových formulí a bez nalezených
  tajemství; po aktualizaci byl znovu vyrenderován a vizuálně zkontrolován.

## Bezpečnostní hranice

Do předání nepatří `config.php`, lokální databázový datadir, obnovovací dumpy,
produkční tajemství, skutečné importy, session ani `vendor/`. Verzované SQL je
pouze struktura pro čisté syntetické demo. Push na GitHub spouští testy, nikoli
produkční deploy.
