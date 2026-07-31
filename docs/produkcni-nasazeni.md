# Produkční nasazení jedním tlačítkem

Produkční adresa:
`https://data.kovopraha.cz/evidence/index.php`

Produkční adresář z pohledu hlavního SSH účtu:
`data.kovopraha.cz/evidence`

Nasazení se spouští ručně v GitHubu. Před změnou databáze se automaticky
vytvoří záloha a po nahrání kódu se ověří DB migrace i dostupnost webu.

## 1. Jednorázové nastavení

Tuto část stačí provést pouze jednou.

### 1.1 Připravit SSH klíč

V PowerShellu na vlastním počítači:

```powershell
ssh-keygen -t ed25519 -f "$env:USERPROFILE\.ssh\evidence_github_deploy" -C "evidence-github-deploy"
```

Při dotazu na heslo klíče stiskněte dvakrát Enter. Automatické nasazení
nemůže odemykat klíč interaktivním heslem; klíč chrání přístup k GitHub
prostředí `production`.

Soukromý klíč nikdy nikomu neposílejte a nevkládejte do repozitáře.

Obsah veřejného klíče zobrazíte:

```powershell
Get-Content "$env:USERPROFILE\.ssh\evidence_github_deploy.pub"
```

Veřejný klíč přidejte v administraci hostingu mezi povolené SSH klíče.
Pokud administrace tuto možnost nenabízí, lze jej po přihlášení přes SSH
přidat do souboru `~/.ssh/authorized_keys`.

### 1.2 Přidat GitHub Secrets

V repozitáři otevřete:

`Settings → Environments → New environment → production`

V prostředí `production` přidejte tyto secrets:

| Název | Hodnota |
|---|---|
| `PROD_SSH_HOST` | SSH adresa serveru uvedená v hostingu |
| `PROD_SSH_USER` | hlavní uživatel domény |
| `PROD_SSH_PRIVATE_KEY` | celý obsah souboru `evidence_github_deploy` bez `.pub` |
| `PROD_SSH_KNOWN_HOSTS` | veřejný otisk SSH serveru |

Hodnotu `PROD_SSH_KNOWN_HOSTS` získáte v PowerShellu:

```powershell
ssh-keyscan -p 22 ADRESA_SERVERU
```

Výstup před vložením porovnejte s SSH fingerprintem uvedeným
v administraci nebo dokumentaci hostingu.

### 1.3 Volitelné GitHub Variables

Ve stejném prostředí lze přidat variables. Pro současné umístění nejsou
potřeba, protože workflow má tyto výchozí hodnoty:

| Název | Výchozí hodnota |
|---|---|
| `PROD_SSH_PORT` | `22` |
| `PROD_APP_DIR` | `data.kovopraha.cz/evidence` |
| `PROD_URL` | `https://data.kovopraha.cz/evidence/index.php` |

Variable nastavte pouze v případě, že se údaj na hostingu liší.

### 1.4 První kontrola adresáře

V produkčním adresáři musí existovat soubor `config.php` s DB přístupem.
Workflow jej nepřenáší ani nepřepisuje.

Produkční PHP uživatel musí mít oprávnění k potřebným `CREATE TABLE`,
`ALTER TABLE`, `CREATE INDEX`, `SELECT`, `INSERT` a `UPDATE`.

Na serveru musí být dostupné příkazy:

- `php`
- `mysqldump`
- `tar`
- `sha256sum`

## 2. Běžné nasazení

1. Otevřete repozitář na GitHubu.
2. Klikněte na `Actions`.
3. Vlevo vyberte `Nasadit produkci`.
4. Klikněte na `Run workflow`.
5. Do potvrzení napište přesně `NASADIT`.
6. Klikněte na zelené `Run workflow`.
7. Počkejte na zelený výsledek.

Workflow vždy nasadí aktuální přesný commit větve `main`.

## 3. Co se děje automaticky

1. GitHub vytvoří balíček přesného commitu.
2. Přes SCP jej nahraje do neveřejného dočasného adresáře SSH účtu.
3. Server ověří kontrolní součet balíčku.
4. Do `~/.evidence-deploy/backups` uloží komprimovanou DB zálohu.
5. Nahraje kód do produkčního adresáře.
6. Spustí `php bin/migrate.php`.
7. Ověří cílovou verzi DB schématu.
8. GitHub ověří, že produkční URL odpovídá.

Adresáře s uploady, obrázky a dalšími provozními daty nejsou součástí Git
balíčku a nasazení je nemaže. Soubor `config.php` je v `.gitignore`.

## 4. Když nasazení zčervená

Klikněte na neúspěšné nasazení a otevřete červený krok. Chyba bude česky
uvedená přímo ve výpisu.

Nejčastější příčiny:

- chybí nebo nesedí některý GitHub Secret,
- SSH adresa, uživatel nebo port nejsou správné,
- produkční adresář se na hostingu jmenuje jinak,
- na serveru není dostupný `mysqldump`,
- DB uživatel nemá práva na migraci,
- produkční URL po nasazení vrací HTTP chybu.

Pokud selže záloha, kód ani migrace se nezačnou nasazovat.

Pokud selže migrace, její záloha zůstane v:

```text
~/.evidence-deploy/backups
```

V takovém případě nespouštějte nasazení opakovaně bez kontroly konkrétní
chyby.

## 5. Ruční kontrola DB na serveru

V produkčním adresáři:

```bash
php bin/migrate.php --check
```

Úspěšný výsledek:

```text
DB schéma OK: 2.20.2
```

Ruční spuštění migrace:

```bash
php bin/migrate.php
```
