# Integrace Evidence → Velocota

*Dokument pro vývojáře (lidi i AI cowork). Popisuje smlouvu mezi dvěma aplikacemi.*

---

## Přehled architektury

```
┌─────────────────────────────────────────────────────┐
│  kovopraha.cz  (Velocota — klubový portál)          │
│  PHP, vlastní user management, navigace             │
│                                                     │
│  /                  Hlavní web (aktuality, profily) │
│  /evidence/    ─────────────────────────────────►  │
│                     Evidence tréninků               │
│                     (tato aplikace)                 │
└─────────────────────────────────────────────────────┘
```

**Obě aplikace:**
- PHP 8+, Apache, MariaDB/MySQL
- Sdílená doména / stejný server
- Sdílená PHP session (stejný `session.save_path`)
- Velocota = zdroj pravdy pro uživatele
- Evidence = sub-aplikace (tréninkový modul)

---

## 1. Autentizace — SSO Bridge

### Princip

Velocota přihlásí uživatele a zapíše do session. Evidence tuto session přečte přes `auth/sso_bridge.php` a namapuje na vlastní session proměnné — **bez vlastního loginu**.

```
Uživatel → Velocota login → $_SESSION['velo_*'] zapisuje Velocota
                                          │
                                          ▼
                         auth/sso_bridge.php (Evidence)
                                          │
                                          ▼
                         $_SESSION['trener_id'] = mapovaný ID
                         $_SESSION['role']      = mapovaná role
```

### Session klíče — KONTRAKT

| Velocota zapíše | Typ | Evidence čte jako |
|-----------------|-----|-------------------|
| `velo_user_id` | int | základ pro `trener_id` |
| `velo_role` | string | viz mapování rolí níže |
| `velo_jmeno` | string | `trener_jmeno` |
| `velo_email` | string | pro lookup v `treneri` |
| `velo_klub_id` | int | filtrování dat (budoucnost) |

> ⚠️ **Tyto klíče jsou kontrakt.** Velocota tým nesmí přejmenovat bez koordinace s Evidence.  
> Evidence je používá v `auth/sso_bridge.php` a nikde jinde.

### Mapování rolí

| Velocota role | Evidence role | Popis |
|--------------|--------------|-------|
| `trener` | `trener` | Trenér — zadává tréninky, vidí svůj přehled |
| `hlavni_trener` | `hlavni` | Správce — vidí vše, spravuje sportovce |
| `admin` | `admin` | Administrátor — plný přístup |
| `clen` | *(bez přístupu)* | Řadový člen — přesměrování na Velocota |
| `verejny` | *(bez přístupu)* | Nepřihlášený — přesměrování |

### Soubor `auth/sso_bridge.php`

```php
// Tento soubor NE přímo require, ale přes helper:
// v db.php: if (VELOCOTA_INTEGRATION) require 'auth/sso_bridge.php';
```

Viz `auth/sso_bridge.php` v repozitáři — kostra je připravena.

### Standalone mód (lokální vývoj)

Při vývoji bez Velocoty funguje Evidence s vlastním `login.php`.  
Přepínač: konstanta `VELOCOTA_INTEGRATION` v `config.php`.

```php
// config.php
define('VELOCOTA_INTEGRATION', false);  // lokální vývoj
define('VELOCOTA_INTEGRATION', true);   // produkce s Velocotou
```

---

## 2. Navigace

### Přepínatelná hlavička

`hlavicka.php` podmíněně includuje buď vlastní navbar nebo Velocota header:

```php
// hlavicka.php
if (defined('VELOCOTA_INTEGRATION') && VELOCOTA_INTEGRATION) {
    require VELOCOTA_ROOT . '/includes/header.php';  // Velocota navigace
} else {
    // vlastní navbar (stávající kód)
}
```

### Co Velocota header musí poskytovat

- CSS proměnné pro barvy (`--velo-primary`, `--velo-bg`)
- Bootstrap 5.3+ (nebo kompatibilní verzi)
- Bootstrap Icons
- `showToast(message, type)` globální JS funkce
- `$_VELO_APP_BASE` PHP konstanta (base URL aplikace)

### Aktivní odkaz v Velocota menu

Velocota menu musí mít odkaz na Evidence s detekcí aktivní stránky:
```php
// Velocota navigace — odkaz na Evidence
<a href="/evidence/" class="<?= str_starts_with($_SERVER['REQUEST_URI'], '/evidence') ? 'active' : '' ?>">
    Tréninky
</a>
```

---

## 3. Databázová vrstva

### Sdílená DB nebo oddělená?

**Doporučení:** Oddělená DB, synchronizace přes `sso_bridge.php`.

```
Velocota DB:  velo_users (id, jmeno, email, role, klub_id, ...)
                    │
                    │  SSO bridge synchronizuje při přihlášení
                    ▼
Evidence DB:  treneri (id, jmeno, email, heslo*, role, velo_user_id)
                         * heslo ignorováno při SSO módu
```

Sloupec `treneri.velo_user_id INT NULL` — reference na Velocota uživatele.

### Migrace pro sloupec `velo_user_id`

Přidána jako migrace 2.18.0 (viz `includes/auto_migrace.php`).

---

## 4. Veřejný booking (`booking/`)

### Současný stav

`booking/` má vlastní auth (`verejni_uzivatele` tabulka — email + heslo).  
Zákazníci si rezervují individuální lekce na velodromu.

### Po integraci — 2 možnosti

**Varianta A — zachovat oddělené (doporučeno pro fázi 1):**
- `booking/` zůstane s vlastní auth
- Velocota přidá odkaz na `https://kovopraha.cz/evidence/booking/`
- Branding sdílený přes CSS

**Varianta B — sjednotit s Velocota uživateli (fáze 2):**
- `verejni_uzivatele` nahrazena Velocota `velo_users` tabulkou
- `booking/prihlaseni.php` přesměruje na Velocota login
- Zákazník se přihlásí jednou pro web i rezervace

> **Rozhodnutí: Velocota tým.** Evidence je připravena na obě varianty.

---

## 5. Co Evidence exponuje Velocotě

### Veřejné URL (bez auth)

| URL | Popis | Parametr |
|-----|-------|---------|
| `/evidence/sportovec_treninky.php?hash=X` | Veřejná karta sportovce | hash z `sportovci.hash` |
| `/evidence/booking/kalendar.php` | Veřejný rezervační kalendář | — |
| `/evidence/booking/registrace.php` | Registrace zákazníka | — |

### Data pro Velocota (budoucí API)

Velocota může čerpat z Evidence tato data (přes sdílenou DB nebo REST API):

| Data | Tabulka Evidence | Využití na Velocota webu |
|------|-----------------|--------------------------|
| Výsledky závodů | `zavody`, `zavod_sportovec` | Závodní sekce, profily závodníků |
| Tréninková docházka | `trenink_sportovec` | Profil člena |
| Plánované tréninky | `planovane_treninky` | Kalendář na webu |
| Individuální lekce | `individualni_lekce` | Rezervační widget |

---

## 6. URL struktura

```
kovopraha.cz/                     Velocota — hlavní web
kovopraha.cz/evidence/            Evidence — kořen (tréninkový modul)
kovopraha.cz/evidence/login.php   Fallback login (standalone mód)
kovopraha.cz/evidence/booking/    Veřejný booking
```

### .htaccess / Apache config

Evidence nepotřebuje `mod_rewrite`. Stačí alias v Apache:

```apache
# Apache VirtualHost nebo .conf
Alias /evidence /var/www/html/evidencePavel
<Directory /var/www/html/evidencePavel>
    AllowOverride All
    Require all granted
</Directory>
```

---

## 7. Sdílená konfigurace (`config.php`)

Soubor `config.php` v kořeni Evidence — **není verzován** (v `.gitignore`).  
Vzor viz `config.example.php`.

```php
<?php
// config.php — lokální konfigurace, NENÍ v gitu

// Integrace s Velocotou
define('VELOCOTA_INTEGRATION', true);
define('VELOCOTA_ROOT', '/var/www/html/velocota');
define('VELOCOTA_DB_HOST', 'localhost');
define('VELOCOTA_DB_NAME', 'velocota');
define('VELOCOTA_DB_USER', 'velocota_user');
define('VELOCOTA_DB_PASS', 'heslo');

// Standalone mód (lokální vývoj) — přepište na false
// define('VELOCOTA_INTEGRATION', false);
```

---

## 8. Change control — co koordinovat

Před úpravou těchto věcí **konzultujte s Velocota týmem:**

| Oblast | Soubor(y) | Proč |
|--------|-----------|------|
| Session klíče | `auth/sso_bridge.php` | Kontrakt s Velocotou |
| `treneri` tabulka — přidání sloupce | `auto_migrace.php` | Může rozbít SSO lookup |
| `booking/` auth flow | `booking/prihlaseni.php` | Závisí na rozhodnutí var. A/B |
| Navigace | `hlavicka.php` | Sdílený UI |
| URL struktura | Apache config | SEO + záložky uživatelů |

---

## 9. Fáze integrace

### Fáze 1 — Minimální (funkční integrace)
- [ ] `config.php` s `VELOCOTA_INTEGRATION` přepínačem
- [ ] `auth/sso_bridge.php` implementován (Velocota zapíše klíče)
- [ ] `hlavicka.php` podmíněný include Velocota headeru
- [ ] `treneri.velo_user_id` sloupec (migrace 2.18.0)
- [ ] Velocota přidá odkaz na `/evidence/`
- [ ] Apache alias nastaven

### Fáze 2 — Datové propojení
- [ ] Velocota zobrazuje závodní výsledky z Evidence DB
- [ ] Profil člena na Velocota obsahuje odkaz na sportovec kartu
- [ ] Plánované tréninky viditelné v Velocota kalendáři

### Fáze 3 — Sjednocení bookingu (volitelné)
- [ ] `booking/` přihlášení přes Velocota účet
- [ ] `verejni_uzivatele` nahrazena nebo synchronizována

---

## 10. Lokální vývoj bez Velocoty

Evidence je plně funkční bez Velocoty — standalone mód:

```bash
# config.php pro lokální vývoj
define('VELOCOTA_INTEGRATION', false);

# Přihlášení přes vlastní login
# http://localhost/evidencePavel/login.php
```

Standalone mód musí zůstat funkční — neodstraňovat `login.php`.

---

*Verze: 1.0 — červen 2026 | Aktualizovat při každé změně kontraktu*
