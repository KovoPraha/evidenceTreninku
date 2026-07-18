# Technická dokumentace — Evidence Tréninků

## Obsah

1. [Architektura](#1-architektura)
2. [Adresářová struktura](#2-adresářová-struktura)
3. [Databáze a auto-migrace](#3-databáze)
4. [Autentizace a autorizace](#4-autentizace-a-autorizace)
5. [CSRF ochrana](#5-csrf-ochrana)
6. [AJAX endpointy](#6-ajax-endpointy)
7. [Export systém](#7-export-systém)
8. [Upload souborů](#8-upload-souborů)
9. [Audit logging](#9-audit-logging)
10. [Google Sheets integrace](#10-google-sheets-integrace)
11. [Sync mechanismus](#11-sync-mechanismus)
12. [Kreditní systém](#12-kreditní-systém)
13. [Story generátor](#13-story-generátor)
14. [Email systém](#14-email-systém)
15. [UX a frontendové vzory](#15-ux-a-frontendové-vzory)
16. [Rezervační systém](#16-rezervační-systém)
17. [Plánovač tréninků](#17-plánovač-tréninků) *(2.10.0)*
18. [Rozšíření plánovače](#18-rozšíření-plánovače-2110216) *(2.11.0–2.16.0)*
19. [Čekací listina](#19-čekací-listina-2140) *(2.14.0)*
20. [UX balík](#20-ux-balík-2160) *(2.16.0)*
21. [Web Push notifikace](#21-web-push-notifikace-2170) *(2.17.0)*
22. [Velocota SSO integrace](#22-velocota-sso-integrace-2180) *(2.18.0)*

---

## 1. Architektura

- **Backend:** PHP 8+ (procedurální styl, bez frameworku)
- **Databáze:** MySQL / MariaDB přes PDO
- **Frontend:** Bootstrap 5.3.3 + Bootstrap Icons 1.11.3 + vanilla JavaScript
- **Exporty:** PhpSpreadsheet ^5.3 (Composer, aktuálně 5.8.0)
- **Web Push:** minishlink/web-push ^9.0 (Composer)
- **Server:** Apache (XAMPP)

Každá stránka je samostatný PHP soubor. Společné komponenty:
- `db.php` — připojení k databázi (PDO)
- `hlavicka.php` — navigační panel
- `includes/init.php` — inicializace session + db
- `includes/funkce.php` — pomocné funkce (audit log)
- `csrf_helper.php` — CSRF ochrana

---

## 2. Adresářová struktura

```
evidencePavel/
├── db.php                          # Připojení k DB (PDO)
├── hlavicka.php                    # Navigační panel (Bootstrap navbar)
├── csrf_helper.php                 # CSRF ochrana
├── login.php                       # Přihlášení
├── logout.php                      # Odhlášení
├── index.php                       # Nástěnka (dashboard)
│
├── includes/
│   ├── init.php                    # Session start + require db.php
│   ├── funkce.php                  # Audit log funkce (roleAtLeast, canAccess, roleBadge)
│   ├── auto_migrace.php            # Auto-migrace DB schématu (SCHEMA_VERSION)
│   └── push_helper.php             # Web Push helper (sendPushNotification, VAPID wrapper)
│
├── sw.js                           # Service Worker — Web Push (v kořeni, HTTPS only)
├── push_subscribe.php              # Uloží/smaže Web Push subscripci trenéra
├── config.php                      # Lokální konfigurace (DB konstanty, VAPID klíče) — NENÍ v gitu
├── config.example.php              # Šablona konfigurace — bezpečný vzor pro nasazení
│
├── vendor/                         # Composer závislosti
│   ├── phpoffice/phpspreadsheet/   # Excel export/import
│   └── minishlink/web-push/        # Web Push notifikace
│
├── templates/                      # XLSX šablony pro export
│
├── uploads/                        # Nahrané soubory
│   ├── servis/                     # Servisní dokumenty
│   ├── uctenky/                    # Foto účtenek
│   └── zatezove_testy/             # Soubory zátěžových testů
│
├── nahrane_obrazky/                # Obrázky tréninků
├── nahrane_zavody/                 # Obrázky závodů
├── stories/                        # Vygenerované story obrázky
├── loga_story/                     # Loga pro story
│
├── auditlog/                       # Prohlížení audit logu
│   └── seznam.php
├── jizdy/                          # Správa jízd (admin-only)
│   ├── seznam.php, formular.php, uloz.php, smazat.php
├── servis/                         # Servisní záznamy (admin-only)
│   ├── seznam.php, formular.php, uloz.php, smazat.php
├── uctenky/                        # Účtenky (admin-only)
│   ├── seznam.php                  # Seznam účtenek s filtrem
│   ├── formular.php                # Formulář přidání/úpravy
│   ├── uloz.php                    # Uložení (AJAX, MIME validace)
│   └── smazat.php                  # Smazání (POST + CSRF)
├── udalosti/                       # Události (admin-only)
│   ├── seznam.php                  # Seznam událostí
│   ├── formular.php                # Formulář přidání/úpravy
│   ├── uloz.php                    # Uložení (AJAX)
│   ├── smazat.php                  # Smazání (POST + CSRF)
│   ├── vyuctovat.php               # Vyúčtování události (finanční přehled)
│   └── uzavrit.php                 # Uzavření události (AJAX → JSON)
├── vozidla/                        # Vozidla (admin-only)
│   ├── seznam.php                  # Seznam vozidel
│   ├── formular.php                # Formulář přidání/úpravy
│   ├── uloz.php                    # Uložení (AJAX)
│   └── smazat.php                  # Smazání (POST + CSRF)
├── zatezove_testy/                 # Zátěžové testy
│   ├── interni/                    # Interní soubory (trenéři)
│   ├── ostatni/                    # Ostatní soubory (FIT, XLS, PDF)
│   └── sportovec/                  # Soubory viditelné sportovci
│
├── reports/
│   └── weekly/                     # Generované týdenní reporty
│
├── formular.php                    # Nový trénink
├── ulozit_trenink.php              # Uložení tréninku (POST handler)
├── edit_trenink.php                # Editace tréninku
├── update_trenink.php              # Aktualizace tréninku (POST handler)
├── smazat_trenink.php              # Smazání tréninku
├── duplikovat_trenink.php          # Duplikace tréninku
│
├── ajax_sportovci.php              # AJAX: vyhledávání sportovců
├── ajax_podskupiny.php             # AJAX: načtení podskupin
├── ajax_treninky.php               # AJAX: seznam tréninků (HTML) + fulltext ?q
├── ajax_update_poznamka.php        # AJAX: aktualizace poznámky k tréninku
├── ajax_global_search.php          # AJAX: globální hledání (sportovci, tréninky, závody)
├── ajax_update_plan.php            # AJAX: drag-drop přesun/přejmenování plánovaného tréninku
├── ajax_nova_oznameni.php          # AJAX: vytvoření nového oznámení skupiny
├── ajax_sportovec_treninky.php     # AJAX: tréninky sportovce — veřejný profil, auth via hash
├── ajax_sportovec_poznamka.php     # AJAX: uložení poznámky sportovce k tréninku, auth via hash
├── nacti_podskupiny.php            # AJAX: podskupiny (alternativa)
├── nacti_skupiny.php               # AJAX: skupiny
│
├── moje_treninky.php               # Moje tréninky (seznam)
├── moje_skupiny.php                # Moje skupiny
├── prehled_trenera.php             # Měsíční přehled trenéra
├── prehled_sportovcu.php           # Přehled sportovců
├── sportovec_detail.php            # Detail sportovce
├── sportovec_treninky.php          # Veřejný profil sportovce
│
├── sprava_skupin.php               # Správa skupin (admin)
├── sprava_podskupin.php            # Správa podskupin (admin)
├── sprava_treneru.php              # Správa trenérů (admin)
├── sprava_sportovcu.php            # Správa sportovců — CRUD (jmeno, prijmeni, narozeni, uciid, rc, telefon, adresa)
├── sprava_vsech_treninku.php       # Správa všech tréninků (admin) — fulltext, filtr kategorie
├── sprava_zavodu.php               # Správa závodů — přehled s kategorií badge, edit/detail akce
├── formular_zavod.php              # Nový závod — kategorie, měření, účastníci, soubory, URL výsledků
├── ulozit_zavod.php                # Uložení závodu (POST handler) — měření, účastníci, fotky, soubory
├── edit_zavod_form.php             # Formulář editace závodu (GET, zobrazení) — předvyplnění měření
├── update_zavod.php                # Aktualizace závodu (POST handler) — smazání/přidání měření, fotky
├── zavod_detail.php                # Detail závodu — výsledky, měření, galerie, soubory
├── sprava_sportovec_obdobi.php     # Správa kreditních období (admin)
├── nastaveni_zadavani.php          # Nastavení okna pro zadávání — počet dní zpět (rolling, integer)
│
├── sprava_sportovist.php           # CRUD správa sportovišť (admin)
├── kalendar_sportovist.php         # Týdenní kalendář obsazenosti sportovišť (trenér) — interní rezervace + informační bloky lekcí
├── rezervovat_sportoviste.php      # Formulář nové interní rezervace sportoviště (live AJAX dostupnost)
├── individualni_lekce_form.php     # Formulář nové individuální lekce (trenér) — slot_delka_min, typ zelená/žlutá
├── individualni_lekce_sprava.php   # Správa lekcí + rezervací zákazníků (trenér)
├── ajax_dostupnost_sportovist.php  # AJAX: obsazenost sportoviště — pouze interní rezervace (lekce_id IS NULL)
├── ajax_denny_rozvrh.php           # AJAX: HTML denní rozvrh sportoviště (10:00–20:00, ghost preview)
│
├── planovac.php                    # Plánovač tréninků — přehled plánovaných tréninků, filtr, sdílení programu skupiny
├── planovany_trenink_form.php      # Formulář nového/editace plánovaného tréninku
├── program_skupiny.php             # Veřejný program skupiny — bez přihlášení, URL ?hash=<skupinaHash>
├── cron_upominky.php               # CLI/web cron — upomínky trenérům na nezaevidované plány
├── cron_report_tyden.php           # CLI cron — generování týdenních reportů dle skupiny
├── report_tyden_lib.php            # Sdílená knihovna pro týdenní reporty (buildWeekReport, saveWeekReport)
│
├── booking/                        # Veřejný booking — zákazníci (bez trenérské session)
│   ├── registrace.php              # Registrace zákazníka (email + heslo)
│   ├── overeni.php                 # Ověření emailu tokenem (GET ?token=)
│   ├── prihlaseni.php              # Přihlášení zákazníka
│   ├── odhlaseni.php               # Odhlášení zákazníka
│   ├── kalendar.php                # Veřejný kalendář lekcí (velodrom + horní posilovna)
│   ├── rezervovat.php              # POST handler rezervace lekce (?waitlist=1 → čekací listina)
│   ├── moje_rezervace.php          # Přehled a storno rezervací + čekací listiny zákazníka
│   ├── potvrdit.php                # GET endpoint pro potvrzení/zamítnutí (z emailu trenéra)
│   └── waiting_list.php            # Helper notifyWaitingList() — přiřazení slotu 1. čekajícímu
│
├── export_draha.php                # Export dráha (XLSX)
├── export_uci.php                  # Export UCI (XLSX)
├── export_seznam.php               # Export seznam sportovců (XLSX)
├── export_xls.php                  # Export tréninků (XLSX)
├── export_csv.php                  # Export CSV
│
├── generuj_story.php               # Generování story obrázku
├── nastaveni_story.php             # Nastavení story vzhledu
├── odeslat_emaily.php              # Odesílání emailů (admin)
├── oznameni.php                    # Oznámení
├── google_sheets_linky.php         # Google Sheets integrace
├── sync.php                        # Import sportovců z XLSX (legacy)
├── sync_evidence.php               # KIS synchronizace: uzivatele + platby + soupisky (4-krokovy wizard)
├── sprava_segmentu.php             # Správa segmentů na kole (CRUD + foto upload)
│
└── composer.json                   # Composer konfigurace
```

---

## 3. Databáze

### Připojení

Soubor `db.php` volí připojení v tomto pořadí priorit:

1. **`config.php` (prioritní)** — pokud soubor existuje a definuje `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, použijí se tyto konstanty. Na produkci je to povinné.
2. **Automatický localhost fallback** — pokud `config.php` chybí a `SERVER_NAME` je `localhost` nebo `127.0.0.1`, použije se místní DB (`evidence`, `root`, bez hesla).
3. **Chyba na produkci** — pokud `config.php` chybí na serveru bez localhost detekce, skript selže s chybovou hláškou (záměrné chování — produkce bez `config.php` nesmí běžet s výchozími hodnotami).

| Prostředí | Konfigurace |
|-----------|-------------|
| Localhost | fallback: `evidence` / `root` / bez hesla |
| Produkce | `config.php` s `DB_*` konstantami (není v gitu) |

Připojení: `PDO("mysql:host=...;dbname=...;charset=utf8mb4")`

Nastavení: `PDO::ATTR_ERRMODE → PDO::ERRMODE_EXCEPTION`

Na konci `db.php` se volá `require_once __DIR__ . '/includes/auto_migrace.php'` a podmíněně `auth/sso_bridge.php` (pokud `VELOCOTA_INTEGRATION === true` v `config.php`).

### Auto-migrace schématu

Soubor: `includes/auto_migrace.php`

Systém automatické migrace DB schématu — spouští se při každém requestu jako součást `db.php`. Nevyžaduje žádný manuální SQL zásah na produkci; stačí nahrát soubory.

**Princip:**

1. Zajistí existenci tabulky `nastaveni` (klíč-hodnota)
2. Přečte `nastaveni.schema_version` — **1 SELECT** per request
3. Pokud verze odpovídá konstantě `SCHEMA_VERSION` → okamžitý `return` (nulový overhead)
4. Pokud verze nesedí → spustí potřebné `ALTER TABLE` / `CREATE TABLE` (guard přes `information_schema`)
5. Uloží novou verzi do `nastaveni`

**Aktuální verze:** `SCHEMA_VERSION = '2.20.0'`

**Souběžná ochrana (race condition):** Mezi přečtením verze a provedením migrací se získá MySQL advisory lock (`GET_LOCK('evidence_auto_migrace', 0)`). Pokud se lock nepodaří získat (jiný request migruje), funkce okamžitě vrátí. Po dokončení se lock uvolní (`RELEASE_LOCK`). Pokud DB engine `GET_LOCK` nepodporuje, pokračuje bez locku (tolerovaná degradace).

**Pomocné closures uvnitř auto_migrace.php:**

| Closure | Popis |
|---------|-------|
| `$colExists(table, col)` | `true` pokud sloupec existuje (`information_schema.COLUMNS`) |
| `$tableExists(table)` | `true` pokud tabulka existuje (`information_schema.TABLES`) |
| `$colDef(table, col)` | Vrátí definici sloupce (`SHOW COLUMNS ... LIKE ?`) nebo `null` |
| `$exec(sql)` | Spustí SQL; idempotentní chyby (1050 tabulka existuje, 1060 sloupec existuje, 1061 index existuje) jsou tiché; ostatní se logují přes `error_log()`, script pokračuje |

Guard pattern pro ENUM rozšíření (funguje na MySQL i MariaDB):
```php
$defKat = $colDef('segmenty', 'kategorie');
if ($defKat && strpos($defKat['Type'] ?? '', 'mtb') === false) {
    $exec("ALTER TABLE segmenty MODIFY COLUMN kategorie ENUM('krouzek','silnice','mtb') ...");
}
```

**Postup přidání nové migrace:**
1. Přidej SQL krok do `includes/auto_migrace.php` (sekce Migrace)
2. Zvyš `SCHEMA_VERSION` (sem-ver: major.minor.patch)
3. Nahraj soubory na produkci — migrace proběhne automaticky při prvním requestu

### Schéma

Kompletní popis tabulek viz [Databázové schéma](databazove-schema.md).

---

## 4. Autentizace a autorizace

### Přihlášení (`login.php`)

1. Načtení uživatele z tabulky `treneri` podle **jména nebo emailu** (`WHERE jmeno = ? OR email = ?`)
2. Ověření hesla — `password_verify()` (bcrypt); při prvním přihlášení po migraci se plaintext heslo automaticky přepíše na bcrypt hash
3. Regenerace session ID: `session_regenerate_id(true)`
4. Uložení do session: `trener_id`, `trener_jmeno`, `role`, `login_time`, `opravneni`

### Role systém

Tři hierarchické role — každá dědí oprávnění nižší:

| Role | Hodnota | Přístup |
|------|---------|---------|
| Trenér | `'trener'` | Základní — zadávání tréninků, vlastní přehled |
| Správce | `'hlavni'` | + správa skupin, závodů, tréninků, plánovač |
| Administrátor | `'admin'` | + správa trenérů, oprávnění, nastavení |

`roleAtLeast('hlavni')` — vrátí `true` pro roli `hlavni` i `admin`. `canAccess('klic')` — kontrola konfigurovatelného oprávnění z tabulky `opravneni`.

### Session proměnné

```
$_SESSION['trener_id']      // int — ID trenéra
$_SESSION['trener_jmeno']   // string — Jméno trenéra
$_SESSION['role']           // string — 'trener' | 'hlavni' | 'admin'
$_SESSION['login_time']     // int — Unix timestamp přihlášení
$_SESSION['csrf_token']     // string — CSRF token (64 znaků hex)
$_SESSION['opravneni']      // array — konfigurovatelná oprávnění z tabulky opravneni
$_SESSION['flash_success']  // string — Dočasná úspěšná zpráva
$_SESSION['flash_error']    // string — Dočasná chybová zpráva
$_SESSION['flash_warning']  // string — Dočasná varovná zpráva
$_SESSION['flash_info']     // string — Dočasná informační zpráva
```

### Autorizace na stránkách

```php
// Kontrola přihlášení (běžné stránky)
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}

// Kontrola hierarchické role (preferovaný vzor)
if (!roleAtLeast('hlavni')) {
    header('Location: index.php');
    exit;
}

// Kontrola konfigurovatelného oprávnění
if (!canAccess('sprava_zavodu')) {
    header('Location: index.php');
    exit;
}

// Kontrola vlastnictví tréninku
$stmt = $pdo->prepare('SELECT 1 FROM trenink_trener WHERE trenink_id = ? AND trener_id = ?');
$stmt->execute([$treninkId, $_SESSION['trener_id']]);
$hasAccess = $stmt->fetchColumn();
```

### Veřejní zákazníci — booking (`booking/`)

Paralelní session namespace pro zákazníky veřejného bookingu. Nevyžaduje trenérské přihlášení.

```php
// Session proměnné zákazníka (booking/*)
$_SESSION['verejny_uzivatel_id']     // int — ID zákazníka (verejni_uzivatele.id)
$_SESSION['verejny_uzivatel_jmeno']  // string — "Jméno Příjmení"
```

Tok registrace/přihlášení:
1. `booking/registrace.php` — POST: jmeno, prijmeni, email, telefon, heslo → INSERT + `verifikacni_token` → email
2. `booking/overeni.php?token=` — SET `email_overeno=1`, clear token, start session → redirect kalendar
3. `booking/prihlaseni.php` — POST: email + heslo → `password_verify()` → session, redirect (`?redirect=`)
4. `booking/odhlaseni.php` — `unset($_SESSION[...])` → redirect kalendar

Guard na stránkách, kde je potřeba login zákazníka:
```php
if (!isset($_SESSION['verejny_uzivatel_id'])) {
    header('Location: prihlaseni.php?redirect=moje_rezervace.php');
    exit;
}
```

Trenérská session (`$_SESSION['trener_id']`) a zákaznická session koexistují beze konfliktů — používají různé klíče.

### Admin-only sandbox (firemní evidence)

Moduly `vozidla/`, `jizdy/`, `servis/`, `uctenky/`, `udalosti/` jsou přístupné **správcům a administrátorům** (`roleAtLeast('hlavni')`). Každý soubor v těchto modulech obsahuje:

```php
require_once __DIR__ . '/../includes/funkce.php';

// Redirect stránky (seznam.php, formular.php, smazat.php)
if (!isset($_SESSION['trener_id']) || !roleAtLeast('hlavni')) {
    header('Location: ../login.php');
    exit;
}

// JSON endpointy (uloz.php, uzavrit.php)
if (!isset($_SESSION['trener_id']) || !roleAtLeast('hlavni')) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Přístup odepřen.']);
    exit;
}
```

> **Pozor:** Přímé `=== 'hlavni'` by zablokovalo uživatele s rolí `admin`. Vždy používej `roleAtLeast()` z `includes/funkce.php`.

Navigační odkazy na tyto moduly se v `hlavicka.php` a `index.php` zobrazují pouze v bloku `$is_hlavni`.

---

## 5. CSRF ochrana

Soubor `csrf_helper.php` poskytuje tři funkce:

### `csrf_token(): string`
Vrátí CSRF token. Vygeneruje nový token `bin2hex(random_bytes(32))` při prvním volání v session.

### `csrf_field(): string`
Vrátí HTML hidden input: `<input type="hidden" name="csrf_token" value="...">`

### `csrf_verify(string $token): bool`
Ověří token pomocí `hash_equals()` (ochrana proti timing attackům).

### Použití

```php
// Ve formuláři
<form method="POST">
    <?= csrf_field() ?>
    ...
</form>

// Při zpracování POST (s flash zprávou)
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Neplatný CSRF token.';
    header('Location: seznam.php');
    exit;
}
```

### Pokrytí

CSRF ochrana je implementována ve všech modulech:
- Správa skupin, podskupin, trenérů — save i delete formuláře
- Všechny CRUD moduly (vozidla, jízdy, servis, účtenky, události)
- Smazání tréninků
- AJAX formuláře (CSRF token v `FormData`)
- Uzavření události (`udalosti/uzavrit.php`)

---

## 6. AJAX endpointy

Většina endpointů vyžaduje session (`$_SESSION['trener_id']`). Výjimky jsou `ajax_sportovec_treninky.php` a `ajax_sportovec_poznamka.php` — ty jsou veřejné a autorizují se přes `sportovci.hash`. Odpovědi jsou v JSON nebo HTML (uvedeno u každého).

### `ajax_sportovci.php` — Vyhledávání sportovců

```
GET ?q=<hledaný text>&limit=<max výsledků>
```

| Parametr | Typ | Výchozí | Popis |
|----------|-----|---------|-------|
| `q` | string | — | Hledaný text (min. 2 znaky) |
| `limit` | int | 20 | Max. výsledků (5–50) |

**Odpověď:** `[{id, label, jmeno, prijmeni, uciid, narozeni}, ...]`

Hledá v tabulce `sportovci` podle `jmeno`, `prijmeni`, `uci` pomocí `LIKE`. Poznámka: DB sloupec se jmenuje `uci`, v JSON výstupu je aliasován jako `uciid` pro zpětnou kompatibilitu s volaným JS kódem.

### `ajax_podskupiny.php` — Podskupiny podle skupiny

```
GET ?skupina_id=<id>
```

**Odpověď:** `{ok: true, items: [{id, nazev}, ...]}`

Vrací podskupiny seřazené podle `poradi`, `nazev`.

### `ajax_treninky.php` — Seznam tréninků

```
GET ?rok=<rok>&mesic=<1-12>&skupina_id=<id>&q=<hledaný text>
```

| Parametr | Typ | Popis |
|----------|-----|-------|
| `rok` | int|'' | Rok filtr |
| `mesic` | 1–12|'' | Měsíc filtr |
| `skupina_id` | int|'' | Filtr skupiny |
| `q` | string | Fulltext hledání v `napln` a `poznamka` (LIKE, min. 1 znak) |

**Odpověď:** HTML (accordion fragmenty) — není JSON, vrací přímo HTML pro vložení do stránky.

Obsahuje optimalizované batch dotazy (skupiny, podskupiny, trenéři, účastníci, tagy) pro všechny tréninky najednou.

### `ajax_update_poznamka.php` — Aktualizace poznámky

```
POST trenink_id=<id>&poznamka=<text>
```

**Autorizace:** Trenér musí být přiřazen k tréninku nebo mít roli `hlavni`.

**Odpověď:** `{ok: bool, message: string, poznamka: string}`

### `ajax_global_search.php` — Globální vyhledávání

```
GET ?q=<hledaný text>
```

| Parametr | Typ | Popis |
|----------|-----|-------|
| `q` | string | Hledaný text (min. 2 znaky) |

**Autorizace:** Session `trener_id` vyžadována (HTTP 403 jinak).

**Odpověď:**
```json
{
  "sportovci": [{"label": "Novák Jan", "url": "sportovec_detail.php?id=5", "icon": "bi-person"}],
  "treninky":  [{"label": "2026-02-15 – Silniční trénink…", "url": "edit_trenink.php?id=42", "icon": "bi-calendar-event"}],
  "zavody":    [{"label": "2026-03-10 – Závod Brno", "url": "prehled_zavodu.php", "icon": "bi-trophy"}]
}
```

Limity: max 6 sportovců, 5 tréninků, 5 závodů. Tréninky jsou omezeny na záznamy přihlášeného trenéra.

**Použití:** Voláno z globálního searchbaru v `hlavicka.php`. Endpoint funguje z kořenového i podadresářového kontextu díky PHP detekci `__DIR__` vs `DOCUMENT_ROOT`.

### `nacti_podskupiny.php` — Podskupiny (alternativa)

```
GET ?skupina_id=<id>
```

**Odpověď:** `[{id, nazev}, ...]`

Jednodušší verze `ajax_podskupiny.php` s validací přes `FILTER_VALIDATE_INT`.

### `ajax_dostupnost_sportovist.php` — Obsazenost sportoviště

```
GET ?sportoviste_id=<id>&datum=<YYYY-MM-DD>&cas_od=<HH:MM>&cas_do=<HH:MM>[&excl_id=<rezervace_id>]
```

| Parametr | Typ | Popis |
|----------|-----|-------|
| `sportoviste_id` | int | ID sportoviště |
| `datum` | string | Datum (YYYY-MM-DD) |
| `cas_od` | string | Čas začátku |
| `cas_do` | string | Čas konce |
| `excl_id` | int | Volitelné — vyjme tuto rezervaci z výpočtu (pro edit formuláře) |

**Odpověď:** `{"obsazeno": int, "max": int}`

Logika: `SELECT COALESCE(SUM(kapacita_dilu),0) FROM rezervace_sportovist WHERE sportoviste_id=? AND datum=? AND cas_od < ? AND cas_do > ? AND lekce_id IS NULL` (standardní interval-overlap podmínka; **individuální lekce se do kapacity nepočítají** — zobrazují se v kalendáři informačně).

Volá se z `rezervovat_sportoviste.php` (live indikátor dostupnosti) a z `individualni_lekce_form.php` (jen informačně — upozornění na překryv s interní rezervací, nezablokuje uložení).

### `ajax_denny_rozvrh.php` — Denní rozvrh sportoviště

```
GET ?sportoviste_id=<id>&datum=<YYYY-MM-DD>[&ghost_od=<HH:MM>&ghost_do=<HH:MM>]
```

| Parametr | Typ | Popis |
|----------|-----|-------|
| `sportoviste_id` | int | ID sportoviště |
| `datum` | string | Datum (YYYY-MM-DD) |
| `ghost_od` | string | Volitelné — ghost preview nového bloku (začátek) |
| `ghost_do` | string | Volitelné — ghost preview nového bloku (konec) |

**Odpověď:** HTML fragment — vertikální časová osa 10:00–20:00 s barevnými bloky pro interní rezervace, lekce a plánované tréninky. Volitelný `ghost_od`/`ghost_do` zobrazí poloprůhledný preview plánovaného bloku (debounce 350 ms na straně klienta).

Sdílený sidebar v `rezervovat_sportoviste.php` i `individualni_lekce_form.php`. Na mobilu collapsible panel.

### `ajax_update_plan.php` — Drag-drop přesun / přejmenování plánu

```
POST akce=move|rename, plan_id=<id>, ...
```

| `akce` | Parametry | Popis |
|--------|-----------|-------|
| `move` | `plan_id`, `datum` | Přesune plán na nové datum (drag & drop v plánovači) |
| `rename` | `plan_id`, `nazev` | Přejmenuje plán (inline editace dvojklikem) |

**Autorizace:** Trenér musí být vlastníkem plánu (`trener_id = $_SESSION['trener_id']`) nebo mít roli `hlavni`.

**Odpověď:** `{ok: bool, message: string}`

Volá se z `planovac.php` při HTML5 Drag API drop eventu a při blur/Enter v inline editaci.

### `ajax_nova_oznameni.php` — Nové oznámení skupiny

```
POST titulek=<text>&text=<text>&datum=<YYYY-MM-DD>&skupiny[]=<id>...
```

**Autorizace:** Session `trener_id` vyžadována. `roleAtLeast('trener')`.

**Odpověď:** `{ok: bool, id: int}`

Vytvoří záznam v `oznameni` + záznamy v `oznameni_targets` pro každou vybranou skupinu. Volá se z Bootstrap modalu v `planovac.php` (nástěnka skupiny v plánovači, widget od v2.17.0).

### `ajax_sportovec_treninky.php` — Tréninky sportovce (veřejný profil)

```
GET ?hash=<sportovci.hash>&rok=<YYYY>&typ=<kategorie>
```

| Parametr | Typ | Popis |
|----------|-----|-------|
| `hash` | string | SHA-256 hash sportovce (`sportovci.hash`) — nahrazuje session auth |
| `rok` | int | Rok filtr (volitelný) |
| `typ` | string | Kategorie tréninku (volitelný) |

**Autorizace:** Žádná session — veřejný endpoint. Auth přes `hash` z URL (public athlete profile).

**Odpověď:** HTML fragment — seznam tréninků pro veřejný profil sportovce.

Volá se ze stránky `sportovec_treninky.php` (veřejná karta sportovce bez přihlášení).

### `ajax_sportovec_poznamka.php` — Uložení poznámky k tréninku (veřejný profil)

```
POST hash=<sportovci.hash>&trenink_id=<id>&poznamka=<text>
```

**Autorizace:** Žádná session — veřejný endpoint. Auth přes `hash` z POST body. Bez CSRF (hash auth je postačující pro tento use case).

**Odpověď:** `{ok: bool}`

Umožňuje sportovci přidat poznámku ke svému tréninku přímo z veřejného profilu.

---

## 7. Export systém

Exporty využívají knihovnu **PhpSpreadsheet** (`phpoffice/phpspreadsheet ^5.3`).

### `export_xls.php` — Měsíční export tréninků

```
GET ?mesic=<YYYY-MM>
```

Výstup: `treninky_{trenerJmeno}_{mesic}.xlsx`

Sloupce: Datum, Skupiny, Náplň, Poznámka, Počet sportovců, Délka (hod), Měření časů

### `export_draha.php` — Export pro dráhu

```
POST: skupinu_id, sportovci[] (pole ID), export (checkbox)
```

Proces:
1. Načte šablonu z `templates/prihlasovaci_tabulka_draha.xlsx`
2. Vyplní data sportovců (UCI ID, Jméno, Tým, Kategorie, Rok narození)
3. Stáhne jako `export_draha_{YmdHis}.xlsx`

### `export_uci.php` — Export UCI přihlášky

Třístupňový export (upload šablony → výběr skupiny/sportovců → generování):

1. Upload UCI Enrollment Form (.xlsx) — validace MIME, max 5 MB, temp soubor v `uploads/temp/`
2. Výběr skupiny a sportovců (max 11: 8 titular + 3 substitute)
3. Vyplnění šablony: sloupce K–O (příjmení, jméno, CZE, datum nar. DD/MM/YYYY, UCI ID)

Automaticky doplňuje Sport Directors (Mixa + Papík z DB).

Výstup: `UCI_enrolment_{YmdHis}.xlsx`

### `export_seznam.php` — Export seznamu sportovců

```
POST: skupina_id, sportovci[] (pole ID), export (flag)
```

Jednoduchý export vybraných sportovců do nového XLSX souboru (bez šablony):

1. Výběr skupiny z dropdown
2. Zaškrtnutí sportovců (bez limitu)
3. Generování XLSX s formátovanou hlavičkou

Sloupce: Příjmení, Jméno, Datum narození (dd-mm-yyyy), Ročník (yyyy), Kategorie, UCI ID

Funkce:
- Stylovaná hlavička (tmavé pozadí, bílý tučný text, ohraničení)
- UCI ID uloženo jako text (zachování případných vedoucích nul)
- Název souboru: `Seznam_{nazev_skupiny}_{Ymd}.xlsx`
- Řaditelná tabulka (příjmení, jméno, datum nar., kategorie)
- Hromadný výběr/zrušení výběru

### Vzor použití PhpSpreadsheet

```php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setCellValue('A1', 'Hlavička');
// ... naplnění dat

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="soubor.xlsx"');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
```

---

## 8. Upload souborů

### Adresáře

| Adresář | Účel |
|---------|------|
| `uploads/servis/` | Servisní dokumenty (PDF, JPG, PNG) |
| `uploads/uctenky/` | Fotografie účtenek |
| `uploads/zatezove_testy/` | Soubory zátěžových testů |
| `nahrane_obrazky/` | Obrázky tréninků |
| `nahrane_zavody/` | Obrázky závodů |
| `stories/` | Vygenerované story obrázky |
| `loga_story/` | Loga pro story generátor |

### Konvence pojmenování

| Typ | Formát názvu |
|-----|-------------|
| Účtenky | `uctenka_{id}_{timestamp}.{ext}` |
| Zátěžové testy | `test_{id}_{timestamp}_{index}.{ext}` |
| Story | `story_{id}_{timestamp}.jpg` |
| Servis (smazaný) | `smazano_{puvodni_nazev}` (přejmenování místo smazání) |
| Účtenky (smazaný) | `smazano_{puvodni_nazev}` (přejmenování místo smazání) |

### MIME validace uploadů

Nahrávané soubory se validují přes `finfo_file()` (ne jen rozšířením souboru):

```php
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $_FILES['soubor']['tmp_name']);
finfo_close($finfo);

$allowed = ['image/jpeg', 'image/png', 'application/pdf'];
if (!in_array($mime, $allowed)) {
    // Odmítnutí souboru
}
```

Upload adresáře se vytváří s oprávněním `0755` (ne `0777`):
```php
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}
```

### Soft delete souborů

Při smazání záznamu se přiložené soubory nepermanentně mažou, ale přejmenují s prefixem `smazano_`:

```php
$old = $upload_dir . $record['soubor'];
$new = $upload_dir . 'smazano_' . $record['soubor'];
if (file_exists($old)) rename($old, $new);
```

### Obrázky tréninků

Ukládají se jako čárkami oddělený seznam názvů souborů v poli `treninky.obrazky`. Obrázky jsou přístupné přes `/nahrane_obrazky/{nazev_souboru}`.

---

## 9. Audit logging

### Funkce `zapisAuditLog()`

Soubor: `includes/funkce.php`

```php
function zapisAuditLog(
    PDO $pdo,
    int $uzivatel_id,    // ID trenéra
    string $akce,        // 'Přidání', 'Úprava', 'Smazání'
    string $tabulka,     // Název tabulky
    int $zaznam_id,      // ID záznamu
    string $detail        // Popis změny
)
```

Automaticky zaznamenává:
- `$_SERVER['REMOTE_ADDR']` — IP adresa
- `$_SERVER['HTTP_USER_AGENT']` — prohlížeč
- `current_timestamp()` — čas (v DB)

Tabulka: `ucto_audit_log`

Sloupce: `uzivatel_id`, `akce`, `tabulka`, `zaznam_id`, `detail`, `ip_adresa`, `user_agent`, `datum`

---

## 10. Google Sheets integrace

Soubor: `google_sheets_linky.php`

### Tabulky

- `gs_kategorie` — kategorie odkazů (id, nazev)
- `gs_linky` — odkazy na Google Sheets (id, kategorie_id, url, nazev, popis, datum, viditelnost, vlozil_trener_id)
- `gs_link_targets` — cílení odkazů (link_id, target_type, target_id)

### Viditelnost

| Hodnota | Popis |
|---------|-------|
| `treneri` | Viditelné pouze pro trenéry |
| `verejny` | Veřejně přístupné |
| `cilene` | Cílené na konkrétní skupiny/podskupiny/sportovce |

### AJAX endpoint

```
GET ?ajax=podskupiny&skupina_id=<id>
```

Odpověď: `{ok: true, items: [{id, nazev}, ...]}`

---

## 11. Sync mechanismus

### 11a. Legacy import — `sync.php`

Import dat sportovců ze dvou Excel souborů (licence + KIS). Matching dle normalizovaného jména.

### 11b. Synchronizace evidence — `sync_evidence.php`

4-krokovy wizard pro synchronizaci s KIS. Upload vyzaduje tri exporty:

1. **Uzivatele z KIS** — clenske udaje, kontakt, datum narozeni a volitelny sloupec `Soupisky`
2. **Export plateb** — stav platby, castka, zbyva zaplatit, datum splatnosti/uhrady
3. **Soupisky z KIS** — radek osoba+soupiska vcetne priznaku `Aktivni`

Prubeh:

1. **Upload** — `includes/kis_sync_lib.php` nacte tri XLSX pres PhpSpreadsheet, slouci osoby a agreguje platby
2. **Mapovani soupisek** — tabulka `soupiska_mapping`, AJAX podskupiny, auto-match dle nazvu
3. **Preview** — nove osoby, aktualizace, beze zmen a pocet DB osob mimo aktualni KIS import
4. **Provedeni** — DB transakce, INSERT/UPDATE sportovcu, KIS stav, platebni stav a mapovane vazby na skupiny/podskupiny

**Matching**: primarne jmeno + prijmeni + datum narozeni. Fallback na jmeno+prijmeni jen pri jedine shode.
**Archivace**: automaticka archivace chybejicich lidi je vypnuta. Chybejici radek v jednom z KIS exportu neni povazovan za ukoncene clenstvi.
**KIS stav**: uklada se do `sportovci.kis_aktivni`, `kis_platebne_aktivni`, `kis_neuhrazeno`, `kis_posledni_uhrada`, `kis_posledni_sync`, `kis_soupisky`.
**Persistentni mapovani**: tabulka `soupiska_mapping` uchovava prirazeni soupisek pro opakovane importy.

## 11c. Správa segmentů — `sprava_segmentu.php`

CRUD admin stránka pro segmenty cyklistických tras. Segmenty se používají v měření typu `kolo_krouzek`, `kolo_silnice` a `kolo_mtb`.

**Funkce:**
- Filtr dle kategorie (kroužek / silnice / MTB) a stavu (aktivní / vše)
- Upload fotografie (MIME validace, safe naming, soft-delete staré)
- Dva URL odkazy (mapy.cz, Strava)
- Kontrola referenční integrity při mazání (segment použitý v měření nelze smazat)

## 11d. Měření v tréninku a závodech

Systém měření ve `formular.php`, `edit_trenink.php`, `formular_zavod.php`, `edit_zavod_form.php`. Data v tabulkách `mereni_zaznamy` + `trenink_mereni` (resp. `zavod_mereni`).

**Typy měření:**

| Typ | Pole | Lookup |
|-----|------|--------|
| `kolo` | vzdálenost, čas, převod, poznámka | — |
| `kolo_krouzek` | segment, čas, poznámka | `segmenty` (kat=krouzek) |
| `kolo_silnice` | segment, čas, poznámka | `segmenty` (kat=silnice) |
| `kolo_mtb` | segment, čas, poznámka | `segmenty` (kat=mtb) |
| `beh` | vzdálenost, čas, poznámka | — |
| `posilovna` | cvik, váha, opakování, RPE, poznámka | `cviky` |

**Data flow**: JSON na klientu → POST `mereni_json` → `buildMereniRowsFromPost()` → INSERT do `mereni_zaznamy` + link `trenink_mereni` nebo `zavod_mereni`.

## 11e. Evidence závodů (v2.7.0)

Rozšířený systém závodů přidaný ve verzi 2.7.0.

### Stránky

| Soubor | Přístup | Popis |
|--------|---------|-------|
| `prehled_zavodu.php` | `prehled_zavodu` (min. trener) | Přehled závodů — kategorie filtr, stats karty, detail tlačítko |
| `formular_zavod.php` | `formular_zavod` (min. trener) | Formulář nového závodu — kategorie, měření, účastníci, URL výsledků |
| `ulozit_zavod.php` | POST handler | Uložení závodu — `buildMereniRowsFromPost()`, závod_mereni, fotky, soubory |
| `zavod_detail.php` | `zavod_detail` (min. trener) | Detail závodu — výsledky (int/ext), měření, galerie, soubory |
| `edit_zavod_form.php` | `sprava_zavodu` (min. hlavni) | Formulář editace — předvyplnění měření z `zavod_mereni` |
| `update_zavod.php` | POST handler | Aktualizace závodu — delete+reinsert měření, soft-delete fotek |
| `sprava_zavodu.php` | `sprava_zavodu` (min. hlavni) | Admin přehled se stránkováním, kategorie badge, edit/detail akce |

### Kategorie závodu

`ENUM('silnice','draha','mtb')` — silnice=success/bi-bicycle, draha=primary/bi-stopwatch, mtb=warning/bi-tree.

### Interní vs. externí závodníci

`zavod_sportovec.sportovec_id` je **nullable**. Interní závodníci mají vyplněné `sportovec_id` (hypertextový odkaz na `sportovec_detail.php`). Externí závodníci mají `sportovec_id = NULL` a jméno v `jmeno_ext`.

V `zavod_detail.php`:
```sql
COALESCE(CONCAT(sp.prijmeni, ' ', sp.jmeno), zsp.jmeno_ext) AS zobrazit_jmeno
```

### Nastavení zadávání tréninků — rolling window

`nastaveni_zadavani.php` ukládá integer offset (počet dní zpět) do `nastaveni.zadavani_dni_zpet`. Datum se vypočítává dynamicky:

```php
$allowedFrom = date('Y-m-d', strtotime('-' . (int)$offset . ' days'));
```

Tím se okno automaticky posouvá každý den bez jakéhokoli zásahu.

## 11f. Role a oprávnění

### 3 úrovně rolí (hierarchické)

| Úroveň | DB hodnota | UI label | Popis |
|--------|-----------|----------|-------|
| 1 | `trener` | Trenér | Základní — vlastní tréninky, přehledy, exporty |
| 2 | `hlavni` | Správce | + správa sportovců, skupin, závodů, tréninků, kredity |
| 3 | `admin` | Administrátor | + správa trenérů/rolí, firemní evidence, nastavení systému |

### Funkce v `includes/funkce.php`

- `roleAtLeast($role)` — hierarchická kontrola: admin dědí vše od správce
- `canAccess($klic)` — konfigurovatelná kontrola z tabulky `opravneni` (session cache)
- `roleBadge($klic)` — HTML badge pro dashboard (barva dle min. role)

### Tabulka `opravneni`

40+ konfigurovatelných oprávnění seskupených do 7 kategorií (Vkládání, Přehledy, Závodní sekce, Správa dat, Nastavení, Administrace, Firemní evidence). Admin je mění v `nastaveni_opravneni.php`. Načteno do `$_SESSION['opravneni']` při loginu. Výchozí záznamy spravuje `includes/auto_migrace.php` (INSERT IGNORE — ručně upravené hodnoty se nepřepisují).

**Nové oprávnění v 2.10.0:**

| Klíč | Výchozí min. role | Skupina | Popis |
|------|-------------------|---------|-------|
| `planovac` | trener | Přehledy | Přístup do Plánovače tréninků (`planovac.php`, `planovany_trenink_form.php`) |

### Hardcoded výjimky

`sprava_treneru.php` a `nastaveni_opravneni.php` — vždy `roleAtLeast('admin')`, nelze konfigurovat.

---

## 12. Kreditní systém

### Tabulka `sportovec_obdobi`

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int | PK |
| `sportovec_id` | int | FK → sportovci |
| `datum_od` | date | Začátek období |
| `datum_do` | date / NULL | Konec období (NULL = otevřené) |
| `sazba_kc` | decimal | Sazba za trénink (Kč) |
| `pocet_treninku` | int | Snapshot počtu tréninků |
| `castka_celkem` | decimal | Snapshot celkové částky |
| `vyplaceno` | tinyint | 0 = neuhrazeno, 1 = uhrazeno |

### Výpočet (`sprava_sportovec_obdobi.php`)

Funkce `getPeriodStats()`:

```php
function getPeriodStats(PDO $pdo, int $sportovec_id, string $datum_od, string $datum_do, float $sazba_kc): array
```

Dotaz:
```sql
SELECT COUNT(DISTINCT t.id) FROM treninky t
JOIN trenink_sportovec ts ON ts.trenink_id = t.id
WHERE ts.sportovec_id = ? AND t.datum >= ? AND t.datum <= ?
```

Vrací: `['pocet' => int, 'kredit' => float]` (kredit = pocet × sazba)

### Workflow

1. **Vytvoření období:** datum_od + sazba_kc
2. **Průběžný přehled:** živý výpočet počtu tréninků × sazba
3. **Uzavření:** snapshot dat (pocet_treninku, castka_celkem, datum_do)
4. **Vyplacení:** nastavení vyplaceno = 1

---

## 13. Story generátor

Soubor: `generuj_story.php`

### Rozměry

Výstupní obrázek: **1080 × 1920 px** (Instagram story formát)

### Struktura

- **Horní pruh:** 120 px — barva + text hlavičky + logo
- **Střed:** obrázky sportovců (center-crop s `drawCover()`)
- **Dolní pruh:** 120 px — barva + text patičky
- **Gradientní přechody:** `drawGradient()` — plynulý přechod barvy pruhu přes fotografie (alpha blending)
- **Drop-shadow text:** `drawTextShadow()` — text s černým stínem (offset 2px, alpha 40) pro čitelnost přes fotky
- **České datum:** `datumCesky()` — automatické formátování (např. „15. března 2026")
- **Jména sportovců:** Seznam účastníků tréninku vykreslený na obrázku

### Lookup stylu

1. Hledá nastavení pro podskupiny tréninku v `story_nastaveni`
2. Pokud nenajde, hledá pro skupiny
3. Fallback: černé pozadí, bílý text, bez hlavičky/patičky

### Nastavení (`story_nastaveni`)

| Sloupec | Popis |
|---------|-------|
| `typ` | 'skupina' nebo 'podskupina' |
| `entita_id` | ID skupiny/podskupiny |
| `barva` | Barva pruhu (hex) |
| `barva_textu` | Barva textu (hex) |
| `hlavicka` | Text v horním pruhu |
| `paticka` | Text v dolním pruhu |
| `logo` | Název souboru loga |

### Podporované formáty obrázků

JPEG, PNG, WebP, HEIC/HEIF (s Imagick)

Výstup: JPEG kvalita 90 %, uloženo do `stories/story_{id}_{timestamp}.jpg`

---

## 14. Email systém

Soubor: `odeslat_emaily.php`

### Šablona

Proměnné:
- `{jmeno}` — křestní jméno sportovce
- `{prijmeni}` — příjmení sportovce
- `{odkaz}` — URL veřejného profilu
- `{email}` — emailová adresa sportovce

URL profilu: `http(s)://{HOST}/{DIR}/sportovec_treninky.php?hash={HASH}`

### Logování

Tabulka: `email_log`

| Sloupec | Popis |
|---------|-------|
| `sportovec_id` | ID sportovce |
| `email` | Emailová adresa |
| `subject` | Předmět emailu |
| `status` | `odeslano` / `chyba` / `bez_emailu` |
| `timestamp` | Čas odeslání |

---

## 15. UX a frontendové vzory

### Globální CSS v `hlavicka.php`

`hlavicka.php` injektuje sdílené CSS bloky dostupné na všech stránkách:

| Blok | Selektor | Popis |
|------|----------|-------|
| Aktivní stránka | `.navbar .nav-link.active` | Podtržení aktivní položky navbaru |
| Povinná pole | `.form-label.req::after` | Červená `*` u labelu |
| Povinná pole | `input[required]`, `select[required]` | Červený levý border |
| Bootstrap validace | `.was-validated input:valid/invalid` | Zelený/červený border po submitu |
| Mobilní UX | `@media (max-width: 991.98px)` | 44px touch targets, font-size 16px (iOS), tabulky scrollable |

### Globální searchbar

Implementace v `hlavicka.php` + `ajax_global_search.php`:

- Endpoint URL se generuje PHP z `__DIR__` a `DOCUMENT_ROOT` — funguje z kořene i podadresářů
- Debounce 300 ms, `AbortController` pro zrušení předchozího requestu
- Skupinové výsledky (Sportovci / Tréninky / Závody) pomocí funkce `buildHtml()`
- Klávesová navigace: ↑↓ pohyb, Enter = klik, Escape = zavřít

### Varování na neuložené změny

Vzor implementovaný v `formular.php` a `edit_trenink.php`:

```javascript
let dirty = false, submitting = false;
form.addEventListener('input',  () => { dirty = true; });
form.addEventListener('change', () => { dirty = true; });
form.addEventListener('submit', () => { submitting = true; });
window.addEventListener('beforeunload', e => {
    if (dirty && !submitting) { e.preventDefault(); e.returnValue = ''; }
});
```

### Kategorie tréninků

Sloupec `treninky.kategorie` (VARCHAR, nullable). Povolené hodnoty a jejich CSS třídy badge:

| Hodnota | CSS třída | Barva |
|---------|-----------|-------|
| `silnice` | `badge-silnice` | `#198754` (zelená) |
| `mtb` | `badge-mtb` | `#ffc107` (žlutá) |
| `draha` | `badge-draha` | `#0d6efd` (modrá) |
| `cyklokros` | `badge-cyklokros` | `#fd7e14` (oranžová) |
| `posilovna` | `badge-posilovna` | `#dc3545` (červená) |
| `atletika` | `badge-atletika` | `#0dcaf0` (info) |
| `cviceni` | `badge-cviceni` | `#6c757d` (šedá) |
| `plavani` | `badge-plavani` | `#087990` (teal) |

CSS badge třídy jsou definovány lokálně v `prehled_trenera.php`, `sprava_vsech_treninku.php`, `vypis_vykazu.php`, `sportovec_detail.php` a `duplikovat_trenink.php` pomocí `$kategorieMeta` pole:

```php
$kategorieMeta = [
    'silnice'   => ['label'=>'Silnice',    'color'=>'success',  'icon'=>'bi-bicycle'],
    'mtb'       => ['label'=>'MTB',        'color'=>'warning',  'icon'=>'bi-tree'],
    'draha'     => ['label'=>'Dráha',      'color'=>'primary',  'icon'=>'bi-stopwatch'],
    // ...
];
```

### Stats karty (vzor)

Souhrnné statistiky nad tabulkami — implementovány na stránkách:
- `prehled_trenera.php` — počet tréninků, hodiny, průměr, sportovci
- `prehled_zavodu.php` — počet závodů, trenérů, rozsah dat, nejbližší závod
- `vypis_vykazu.php` — hodiny celkem, tréninky, činnosti, položky
- `sprava_vsech_treninku.php` — počet tréninků, hodiny, dynamické kategorie

Vzor: Bootstrap karty s `card text-center border-0 shadow-sm`, `fs-2 fw-bold` pro číslo, `text-muted small` pro popis.

### Barevné odznaky trenérů

V `prehled_zavodu.php` jsou trenéři zobrazeni jako barevné Bootstrap badges. Barvy jsou přiřazovány dynamicky z palety: `primary`, `success`, `danger`, `warning`, `info`, `secondary`, `dark`.

### Fulltext hledání v tabulkách

Dva přístupy:
1. **Server-side** — GET parametr `?q=text`, SQL `LIKE '%text%'` (použito v `sprava_vsech_treninku.php`, `ajax_treninky.php`)
2. **Client-side** — JS filtrování řádků přes `data-search` atribut (použito v `sprava_vsech_treninku.php` jako real-time doplněk)

### Toast notifikace

Globální systém v `hlavicka.php`:

**PHP flash zprávy:**
- `$_SESSION['flash_success']`, `flash_error`, `flash_warning`, `flash_info`
- Automaticky zpracovány v `hlavicka.php` — zobrazeny jako Bootstrap toast
- Stránky nemusí řešit `unset()` ani HTML pro flash zprávy

**JavaScript API:**
```javascript
showToast('Zpráva', 'success');  // success | danger | warning | info
```

Toasty se zobrazují vpravo nahoře (fixed, z-index 1080), auto-dismiss po 4 s.

### URL hash tabs

V `sportovec_detail.php` jsou záložky persistovány přes URL hash:
- Odkaz `sportovec_detail.php?id=5#tab-treninky` otevře přímo záložku Tréninky
- Změna záložky aktualizuje hash přes `history.replaceState()`

---

## 16. Rezervační systém

Přidáno ve verzi **2.8.0**. Systém má dvě vrstvy: interní rezervace sportovišť trenéry a veřejný booking zákazníků.

### 16a. Sportoviště (`sportovist`)

Centrální číselník sportovišť. Každé sportoviště má:
- `max_kapacita` (default 5) — celková kapacita v dílech 1–5
- `je_verejne` — pokud `1`, zobrazuje se ve veřejném bookingu
- `aktivni` — soft-disable (nevytváří rezervace, ale historické zůstávají)

**Seed data** (vložena při migraci H):

| kod | nazev | je_verejne |
|-----|-------|------------|
| `velodrom` | Velodrom | 1 |
| `posilovna_horni` | Posilovna (horní) | 1 |
| `telocvicna_horni` | Horní tělocvična | 0 |
| `telocvicna_spodni` | Spodní tělocvična | 0 |
| `sauna` | Sauna | 0 |
| `nahravaci_mistnost` | Nahrávací místnost | 0 |

### 16b. Interní rezervace (`rezervace_sportovist`)

Trenéři rezervují sportoviště pro týmové tréninky. Od verze 2.9.0 individuální lekce **nevytvářejí** záznamy v `rezervace_sportovist` — oddělení kapacity.

**Kapacitní systém:**
- `kapacita_dilu` (1–5): kolik dílů sportoviště rezervace zabírá
- Max kapacita: `sportovist.max_kapacita` (standardně 5)
- Více rezervací může koexistovat, dokud `SUM(kapacita_dilu) ≤ max_kapacita`
- Výpočet obsazenosti: `AND lekce_id IS NULL` — lekce se do součtu nezahrnují

**Vazbové sloupce:**
- `trenink_id` — vyplněno, pokud vzniklo z formuláře tréninku (integrace `formular.php`)
- `lekce_id` — **zastaralý sloupec** (ponechán pro historická data), od v2.9.0 se nové lekce nevytváří

**Duální vrstva v kalendáři sportovišť (`kalendar_sportovist.php`):**
- Interní rezervace → plné barevné bloky s kapacitou (X/5)
- Individuální lekce → informační bloky s přerušovaným rámečkem (nepočítají se do kapacity)
- Navigace prev/next týden, tlačítko Nová rezervace, smazání bloku (vlastní / správce)

**Stránky:**
- `kalendar_sportovist.php` — dvojitá vizualizace (interní rezervace + lekce)
- `rezervovat_sportoviste.php` — formulář s live indikátorem dostupnosti (AJAX `ajax_dostupnost_sportovist.php`); pokud plán existuje (`plan_id` v GET), formulář je předvyplněn z `planovane_treninky`

### 16c. Individuální lekce (`individualni_lekce`)

Trenér vypisuje placené lekce ve dvou typech:

| Typ | Barva | Chování |
|-----|-------|---------|
| `zelena` | zelená | Automaticky potvrzena při rezervaci zákazníka |
| `zluta` | žlutá | Čeká na potvrzení trenéra; trenér dostane email s one-click linkem |

**Slot-based booking (v2.9.0):** Lekce definuje časové okno (`cas_od`–`cas_do`) a délku slotu (`slot_delka_min`, default 60 min). Zákazník rezervuje konkrétní slot v rámci okna — slot je uložen do `verejne_rezervace.slot_cas_od` / `slot_cas_do`. Pokud `slot_delka_min = 0` nebo není relevantní, zákazník rezervuje celé okno lekce.

Od v2.9.0 lekce **nevytváří** zááznam v `rezervace_sportovist` — sportoviště není kapacitně blokováno, ale lekce je zobrazena informačně v `kalendar_sportovist.php`.

**Stránky:**
- `individualni_lekce_form.php` — nová lekce (sportoviště, datum, čas od-do, slot_delka_min, typ, název, popis, cena, max osob); zobrazuje případný překryv s interní rezervací (upozornění, nezablokuje uložení)
- `individualni_lekce_sprava.php` — přehled lekcí, detail rezervací, akce: Potvrdit/Zamítnout (žluté), Označit zaplaceno, Zrušit lekci

### 16d. Veřejné rezervace (`verejne_rezervace`)

Zákazníci si rezervují individuální lekce (nebo konkrétní sloty) přes `booking/`.

**Slot fields (přidáno v 2.9.0):**
- `slot_cas_od` (TIME NULL) — začátek rezervovaného slotu; NULL = zákazník rezervuje celou lekci
- `slot_cas_do` (TIME NULL) — konec rezervovaného slotu

**Stavy rezervace:**

| Stav | Popis |
|------|-------|
| `ceka` | Žádost odeslána (žlutá lekce) |
| `potvrzena` | Potvrzena (automaticky pro zelenou, manuálně pro žlutou) |
| `zamitnuta` | Trenér zamítl |
| `zrusena` | Zákazník stornoval |

**Tok zelené lekce:**
1. Zákazník rezervuje → `stav=potvrzena` okamžitě
2. Trenér dostane email (informační): "Nová rezervace na Vaši lekci"

**Tok žluté lekce:**
1. Zákazník rezervuje → `stav=ceka`
2. Trenér dostane email s `potvrzovaci_token`, linky `potvrdit.php?token=...&akce=potvrdit` a `akce=zamit`
3. Trenér klikne → `stav=potvrzena|zamitnuta`, zákazník dostane email

**Storno pravidlo:** zákazník může stornovat min. 3 dny před konáním lekce:
```php
$minDatum = (new DateTime())->modify('+3 days')->format('Y-m-d');
$muzeStornovat = ($lekce['datum'] >= $minDatum) && in_array($stav, ['ceka','potvrzena']);
```

### 16e. Email notifikace

Všechny emaily rezervačního systému jsou odesílány přes `mail()`. Žádná závislost na SMTP knihovně.

| Událost | Příjemce | Obsah |
|---------|----------|-------|
| Registrace zákazníka | Zákazník | Verifikační link `booking/overeni.php?token=` |
| Zelená rezervace | Trenér | Informace o nové rezervaci |
| Žlutá rezervace | Trenér | Žádost o potvrzení + two one-click linky (potvrdit/zamítnout) |
| Potvrzení | Zákazník | Lekce potvrzena |
| Zamítnutí | Zákazník | Lekce zamítnuta |

### 16f. Oprávnění rezervačního systému

Přidána 4 nová oprávnění (konfigurovatelná v `nastaveni_opravneni.php`):

| Klíč | Výchozí min. role | Skupina |
|------|-------------------|---------|
| `kalendar_sportovist` | trener | Rezervace |
| `rezervace_sportovist` | trener | Rezervace |
| `individualni_lekce` | trener | Rezervace |
| `sprava_sportovist` | admin | Administrace |

---

## 17. Plánovač tréninků

Přidáno ve verzi **2.10.0**. Dvoustupňový systém: trenér nejprve *naplánuje* trénink, poté zadá *evidenci*.

### 17a. Architektura

Tabulka `planovane_treninky` je plánová vrstva oddělená od `treninky`. Propojení vznikne vyplněním `trenink_id` po uložení evidence.

```
planovane_treninky
  stav=planovany  →  formular.php?plan_id=X  →  treninky  →  trenink_id vyplněno, stav=evidovany
  stav=zruseny    →  plan byl ručně zrušen
```

### 17b. Stránky plánovače

| Soubor | Popis |
|--------|-------|
| `planovac.php` | Přehled plánovaných tréninků — filtr skupiny/trenéra/stavu, sdílení programu skupiny tlačítkem "Sdílet program" |
| `planovany_trenink_form.php` | Formulář nového nebo upraveného plánovaného tréninku (datum, čas od-do, skupina, podskupina, sportoviště, kategorie, název, popis) |
| `program_skupiny.php` | Veřejná stránka bez přihlášení — zobrazuje plánované tréninky skupiny na 2–8 týdnů dopředu; URL `?hash=<skupinaHash>&tydny=6` |

### 17c. Předvyplnění z plánu

Formulář evidence `formular.php` přijímá GET parametr `?plan_id=X`. Pokud je vyplněn:
1. Načtou se data z `planovane_treninky` (datum, skupina, podskupina, kategorie, název jako základ náplně, sportoviště)
2. Formulář je předvyplněn
3. Po uložení tréninku se `planovane_treninky.trenink_id` nastaví na nový trénink a `stav` se změní na `evidovany`

### 17d. Propojení s rezervací

Z formuláře rezervace sportoviště (`rezervovat_sportoviste.php?plan_id=X`) lze vytvořit plan zároveň s rezervací:
- Rezervace uloží `rezervace_sportovist.id` do `planovane_treninky.rezervace_id`
- V `planovac.php` je pak zobrazena vazba na rezervaci

### 17e. Veřejný program skupiny

`program_skupiny.php` — přístup bez trenérské session, pouze `require_once db.php`:
- Hledá záznamy `stav IN ('planovany','evidovany')` pro danou skupinu v zadaném horizontu
- Evidované tréninky (`trenink_id` != NULL) jsou označeny odznakem "Evidováno"
- Tréninky jsou barevně odlišeny dle kategorie, seskupeny po dnech a týdnech (ISO week arithmetic)
- Filtr horizontu: `?tydny=2|4|6|8` (default 6 týdnů dopředu)
- Odkaz na tuto stránku je v `sprava_skupin.php` (kalendářová ikona) a v `planovac.php` (tlačítko "Sdílet program")

---

## 18. Rozšíření plánovače *(2.11.0–2.16.0)*

### 18a. Více podskupin na jeden plán (2.11.0)

Junction tabulka `planovane_treninky_podskupiny` umožňuje přiřadit více podskupin k jednomu plánu. Legacy sloupec `planovane_treninky.podskupina_id` zachován.

Formulář `planovany_trenink_form.php`: checkboxy podskupin (AJAX `ajax_podskupiny.php`).  
Rezervace sportoviště (`rezervovat_sportoviste.php`): checkboxy podskupin místo `<select>`.

### 18b. Upomínky na nezaevidované tréninky (2.13.0)

Sloupec `planovane_treninky.upominka_cas TIMESTAMP NULL` — kdy byla odeslána upomínka.

`cron_upominky.php` — CLI nebo web (`?secret=TOKEN`):
- Hledá `stav='planovany'`, `datum < dnes`, `datum >= dnes-14 dní`, `upominka_cas IS NULL`
- Skupiny emaily po trenérech, nastaví `upominka_cas = NOW()`
- Doporučený cron: `0 7 * * * php /cesta/cron_upominky.php`

`planovac.php` zobrazí varování s počtem nezaevidovaných plánů. Tlačítko „Zaslat upomínky" (jen `roleAtLeast('hlavni')`).

### 18c. Série opakujících se tréninků (2.16.0)

Sloupec `planovane_treninky.serie_id INT NULL` — sdílené ID mezi instancemi série.

`planovany_trenink_form.php`: volby opakování none/počet/do-data → při ukládání nastaví `serie_id = ID prvního záznamu` pro všechny instance.

`planovac.php`: badge „Série" na kartách; tlačítko „Zrušit celou sérii" (`zrusit_serii=1` POST) — `UPDATE planovane_treninky SET stav='zruseny' WHERE serie_id = ?`.

### 18d. Drag & Drop a inline editace (2.16.0)

`planovac.php` + `ajax_update_plan.php`:
- **Drag & drop**: HTML5 Drag API, karty `draggable="true"`, drop zóny na denní sloupce; AJAX `akce=move` → update `datum`
- **Inline editace názvu**: dvojklik → input, Enter/blur → uložit, Esc → zrušit; AJAX `akce=rename`

### 18e. Kopírování týdne (2.16.0)

`planovac.php` → tlačítko „Kopírovat týden" → Bootstrap modal (počet plánů k okopírování + cílový týden) → POST `action=kopirovat_tyden`:
- Duplicates `planovane_treninky` s `datum + 7`, `stav=planovany`
- Kopíruje podskupiny z `planovane_treninky_podskupiny`
- Nekopíruje `rezervace_id`, `trenink_id`

### 18f. Nástěnka skupiny (2.17.0)

Collapsible widget v `planovac.php` nad týdenním gridem — 5 posledních oznámení pro viditelné skupiny.

Modal „+ Nové oznámení": titulek, text, datum, skupiny (checkboxy) → POST `ajax_nova_oznameni.php` → INSERT do `oznameni` + `oznameni_targets`.

---

## 19. Čekací listina *(2.14.0)*

Zákazník se může zapsat na čekací listinu pro plný slot individuální lekce.

### 19a. Stav rezervace

`verejne_rezervace.stav` rozšířen o `'cekaci_listina'`:
- Zákazník na čekací listině → `stav='cekaci_listina'`
- Při uvolnění slotu → `stav` se změní na `'ceka'` nebo `'potvrzena'` (dle typu lekce)

### 19b. `booking/waiting_list.php` — helper

```php
function notifyWaitingList(PDO $pdo, int $lekceId, string $slotOd): void
```
- Najde prvního v pořadí na `cekaci_listina` pro daný slot
- Změní `stav` → `potvrzena` (zelená) nebo `ceka` (žlutá)
- Pošle email zákazníkovi; u žluté také email trenérovi s potvrzovacím odkazem

**Triggery `notifyWaitingList()`:**
1. Zákazník zruší aktivní rezervaci (`booking/moje_rezervace.php`)
2. Trenér zamítne rezervaci (`individualni_lekce_sprava.php`)
3. Trenér zamítne z emailu (`booking/potvrdit.php`)

### 19c. Booking flow

`booking/rezervovat.php` s parametrem `?waitlist=1`:
- Slot plný + chybí `?waitlist=1` → přesměruje na `?waitlist=1`
- Slot volný + přítomno `?waitlist=1` → přesměruje bez `waitlist`
- Uloží `stav='cekaci_listina'`

`booking/kalendar.php`: plný slot zobrazí „Čekací listina (N čeká)"; zákazníkova pozice zobrazena jako badge „Čekací listina #N".

---

## 20. UX balík *(2.16.0)*

Globální vylepšení přidána do `hlavicka.php`.

### 20a. Tmavý režim

Bootstrap `data-bs-theme` toggle, uložen v `localStorage`. Init skript před CSS (žádné blikání). Tlačítko 🌙/☀️ v navbaru.

### 20b. Klávesové zkratky

Globální zkratky (ignoruje fokus v inputu):

| Zkratka | Akce |
|---------|------|
| `N` | Nový trénink (`formular.php`) |
| `P` | Plánovač |
| `K` | Kalendář sportovišť |
| `/` | Globální hledání |
| `D` | Dark/light mode |
| `?` | Nápověda zkratek (`#shortcutsModal`) |
| `Esc` | Zavřít otevřený modal |

### 20c. Sticky záhlaví tabulek

CSS třídy `.table-responsive .table thead th` a `.table-sticky` — fixní záhlaví při scrollu.

### 20d. Empty state CSS

Třída `.empty-state` s `.empty-icon` — přátelský prázdný stav v tabulkách.

---

## 21. Web Push notifikace *(2.17.0)*

### 21a. Infrastruktura

| Soubor | Účel |
|--------|------|
| `sw.js` | Service Worker — zpracuje `push` event → `showNotification()`; klik → navigate na URL |
| `push_subscribe.php` | POST: uloží/smaže subscripci trenéra; `action=subscribe\|unsubscribe` |
| `includes/push_helper.php` | `sendPushNotification(PDO, payload, trenerIds[])` wrapper kolem minishlink/web-push |

Bell ikona 🔔 v navbaru: skrytá dokud SW nezkontroluje stav; toggle activate/deactivate.

### 21b. VAPID klíče

Uloženy v `nastaveni` tabulce:

| Klíč | Popis |
|------|-------|
| `push_vapid_public` | Veřejný klíč (Base64url) |
| `push_vapid_private` | Soukromý klíč (Base64url) |
| `push_vapid_subject` | `mailto:evidence@kovopraha.cz` |

Generátor: `vendor/bin/web-push generate-vapid-keys` nebo https://vapidkeys.com/

### 21c. Integrační body

- `booking/rezervovat.php` → push trenérovi při nové rezervaci lekce
- `cron_upominky.php` → push hlavnímu trenérovi (budoucí rozšíření)

**Poznámka:** Web Push vyžaduje HTTPS (produkce ✓, localhost bez HTTPS — SW se zaregistruje, push nepřijde).

---

## 22. Velocota SSO integrace *(2.18.0)*

Viz `docs/integrace-velocota.md` pro kompletní integrační spec.

### 22a. Přepínač integrace

`config.php` (z `config.example.php`, není v gitu):
```php
define('VELOCOTA_INTEGRATION', false);  // standalone mód (lokální vývoj)
define('VELOCOTA_INTEGRATION', true);   // produkce s Velocotou
```

### 22b. SSO bridge

`auth/sso_bridge.php` — volán z `db.php` při každém requestu (podmíněně):
- Čte Velocota session klíče (`velo_user_id`, `velo_role`, `velo_jmeno`, `velo_email`)
- Mapuje na Evidence session (`trener_id`, `role`, `trener_jmeno`)
- `_syncTrener()` — najde nebo vytvoří shadow account v `treneri`
- Cache: přeskočí DB lookup pokud `velo_user_id_cached` odpovídá

### 22c. Session kontrakt

| Velocota klíč | Typ | Evidence mapuje na |
|---------------|-----|-------------------|
| `velo_user_id` | int | `treneri.velo_user_id` → `$_SESSION['trener_id']` |
| `velo_role` | string | `trener\|hlavni_trener\|admin` → `$_SESSION['role']` |
| `velo_jmeno` | string | `$_SESSION['trener_jmeno']` |
| `velo_email` | string | lookup v `treneri.email` |

**Tyto klíče nesmíš přejmenovat bez koordinace s Velocota týmem.**

### 22d. DB migrace

`treneri.velo_user_id INT NULL` + index `idx_velo_user` — přidáno v migraci 2.18.0.

---

## Dodatek 2.20.0 - KIS provozni centrum

Verze `2.20.0` přidává provozní vrstvu nad členskou evidencí: `sportovec_karta.php` jako administrační karta člena, `kis_sync_center.php`, `sportovci_hromadne.php`, `admin_dashboard.php` a sdílené helpery pro KIS párování, importní runy, stavy členů a historii. Veřejná karta sportovce zůstává oddělená na `sportovec_treninky.php?hash=...`.

Import KIS zustava bez automaticke archivace. Radky s nejasnou shodou se oznacuji jako `ambiguous` a pri potvrzeni importu se preskakuji.

*Verze dokumentace: 2.20.0 — červen 2026*
