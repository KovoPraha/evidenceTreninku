# Nasazení na produkci (jedním tlačítkem)

Nasazení běží přes **GitHub Actions**: GitHub → záložka **Actions** →
**Nasadit produkci** → **Run workflow**. Nic jiného není potřeba —
workflow sám zazálohuje databázi, nahraje soubory, spustí DB migrace
a ověří, že vše proběhlo. Na konci svítí zelená ✅, nebo červená ❌
s popisem, co se nepovedlo.

Produkce se **nemění při pouhém uložení kódu do GitHubu** — nasazuje se
vždy jen ručním spuštěním workflow.

Produkční adresa: <https://data.kovopraha.cz/evidence/>

## Co nasazení dělá

1. Zkontroluje PHP syntaxi všech souborů (chybný kód se vůbec nenahraje).
2. Zavolá `/bin/zaloha.php` — na serveru vznikne komprimovaná záloha
   databáze evidence, přednostně mimo webový adresář (`db_zalohy` vedle
   webu). Drží se posledních 20 záloh. (Tabulky prodejního terminálu
   `jidlo_*` a `bar_*` do zálohy nepatří, mají vlastní.)
3. Nahraje soubory větve `main` přes rsync/SSH. Na serveru **zůstávají**:
   `config.php` (hesla), `uploads/`, `nahrane_obrazky/`, `nahrane_zavody/`,
   `stories/`, `loga_story/`, `reports/`, logy a zálohy.
4. Načte `index.php` — tím se spustí auto-migrace databáze
   (`includes/auto_migrace.php`).
5. Zavolá `/bin/stav.php` a ověří, že verze schématu v databázi odpovídá
   konstantě `SCHEMA_VERSION` v kódu.

Poznámka: nasazení soubory **nemaže** (rsync běží bez `--delete`), protože
v produkční složce jsou i soubory mimo repozitář. Když nějaký soubor
z projektu odstraníte, smažte ho na serveru ručně přes Total Commander.

## Jednorázové nastavení

### 1. SSH klíč na hostingu

V administraci hostingu u domény povolit **„Používání SSH klíčů"**.
Na serveru vznikne adresář `.ssh` — do něj nahrát (Total Commanderem)
soubor **`authorized_keys`** s veřejným klíčem.

Používá se **stejný klíč i stejné přihlášení jako u projektu
bar.kovopraha.cz** — hlavní uživatel domény vidí adresáře všech subdomén,
takže stačí jeden `authorized_keys` pro obě aplikace.

### 2. GitHub Secrets

GitHub → repozitář → **Settings → Secrets and variables → Actions →
New repository secret**. Vytvořit pět:

| Secret | Hodnota |
|---|---|
| `SSH_HOST` | adresa serveru pro SFTP/SSH (např. `replikant3544.thinline.cz`) |
| `SSH_PORT` | port SSH podle zákaznické sekce hostingu (bez vyhrazené IPv4 nebývá 22) |
| `SSH_USER` | uživatelské jméno hlavního přístupu |
| `SSH_PRIVATE_KEY` | celý obsah souboru s privátním klíčem |
| `DEPLOY_TOKEN` | hodnota `DEPLOY_TOKEN` z `config.php` |
| `PROD_CONFIG` | *(nepovinné)* celý obsah `config.php` — když na serveru chybí, vytvoří se z něj |

První čtyři jsou stejné jako u projektu bar.kovopraha.cz, `DEPLOY_TOKEN`
má každý projekt vlastní.

### 3. `config.php`

`config.php` je **jeden a tentýž soubor pro localhost i produkci** — pozná
si sám, kde běží (Windows/XAMPP a adresy typu `localhost` = vývoj, cokoli
jiného = produkce), a podle toho vybere správnou databázi. Není tedy co
rozlišovat: stejný soubor leží v `C:\xampp\htdocs\evidencePavel\config.php`
i v `data.kovopraha.cz/evidence/config.php`. Vzor je `config.example.php`.

Do gitu nepatří (jsou v něm hesla) a nasazení ho nepřepisuje. Na server se
dostane jednou z těchto dvou cest:

- **ručně** — nahrát Total Commanderem, nebo
- **přes Secret `PROD_CONFIG`** — vložit do něj celý obsah `config.php`;
  když soubor na serveru chybí, nasazení ho z něj vytvoří samo.

Kdyby `config.php` na serveru chyběl a `PROD_CONFIG` nebyl vyplněný,
nasazení **se zastaví hned na začátku** a nic nezmění — dřív než by se
vyměnil `db.php`, který config vyžaduje.

### 4. Adresa a adresář

Jsou přímo ve workflow (`.github/workflows/deploy-production.yml`,
sekce `env`): `WEB_URL: https://data.kovopraha.cz/evidence` a
`REMOTE_DIR: data.kovopraha.cz/evidence`. Kdyby se lišily, upraví se tam.

## Běžný pracovní postup

1. Změny v kódu → commit → push do `main` (produkce se ještě nemění).
2. Změna databáze = nový krok v `includes/auto_migrace.php` **a** zvýšení
   `SCHEMA_VERSION` v `includes/schema_version.php`.
3. GitHub → Actions → **Nasadit produkci** → **Run workflow**.
4. Počkat na zelenou ✅.

## Když se něco pokazí

- Červený krok „Kontrola PHP syntaxe" → v kódu je chyba, na produkci se
  nic nenahrálo.
- Červený krok „Připravit SSH klíč" / „Přidat server mezi známé" → špatný
  Secret (klíč, adresa, port). Hláška v logu říká přesně co.
- Červený krok „Ověřit verzi databázového schématu" → migrace neproběhla
  nebo selhala; podrobnost je v PHP error logu na hostingu. Databáze má
  čerstvou zálohu z kroku 2 (`db_zalohy/evidence_*.sql.gz` na serveru),
  kterou lze nahrát zpět přes phpMyAdmin.
- Vrácení kódu: v GitHubu otevřít starší commit → `Revert` → push →
  spustit nasazení znovu.

## Ruční kontrola

V prohlížeči:

- stav migrací: `https://data.kovopraha.cz/evidence/bin/stav.php?token=<DEPLOY_TOKEN>`
- ruční záloha: `https://data.kovopraha.cz/evidence/bin/zaloha.php?token=<DEPLOY_TOKEN>`
