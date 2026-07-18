# Vývojářský průvodce

Průvodce pro vývojáře pracující na projektu **Evidence tréninků**. Obsahuje instalaci, konvence kódu, vzory pro přidávání funkcionality a nasazení.

---

## Obsah

1. [Požadavky](#1-požadavky)
2. [Instalace](#2-instalace)
3. [Struktura projektu](#3-struktura-projektu)
4. [Konvence kódu](#4-konvence-kódu)
5. [Přidání nové stránky](#5-přidání-nové-stránky)
6. [Přidání AJAX endpointu](#6-přidání-ajax-endpointu)
7. [Práce s měřeními](#7-práce-s-měřeními)
8. [Export do Excelu](#8-export-do-excelu)
9. [Upload souborů](#9-upload-souborů)
10. [Bezpečnost](#10-bezpečnost)
11. [Správa DB schématu (auto-migrace)](#11-správa-db-schématu-auto-migrace)
12. [Nasazení](#12-nasazení)

---

## 1. Požadavky

| Komponenta | Verze |
|------------|-------|
| PHP | 8.0+ |
| MySQL / MariaDB | 5.7+ / 10.4+ |
| Apache | 2.4+ (XAMPP) |
| Composer | 2.x |
| GD knihovna (PHP) | pro generování stories |

PHP rozšíření: `pdo_mysql`, `mbstring`, `gd`, `zip` (pro PhpSpreadsheet).

---

## 2. Instalace

### 2.1 Klonování / kopírování

Zkopírujte projekt do kořenového adresáře Apache:

```
C:\xampp\htdocs\evidencePavel\
```

### 2.2 Instalace závislostí

```bash
cd C:\xampp\htdocs\evidencePavel
composer install
```

Composer závislosti:
- `phpoffice/phpspreadsheet ^5.3` — Excel exporty (aktuálně 5.8.0)
- `minishlink/web-push ^9.0` — Web Push notifikace (přidáno ve v2.17.0)

PHP rozšíření potřebná pro Web Push: `openssl`, `mbstring` (obvykle dostupné).

### 2.3 Import databáze

1. Spusťte MySQL/MariaDB (přes XAMPP Control Panel)
2. Vytvořte databázi `evidence`:
   ```sql
   CREATE DATABASE evidence CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
   ```
3. Importujte SQL dump:
   ```bash
   mysql -u root evidence < "kovoprahacz09 (12).sql"
   ```
   Nebo přes phpMyAdmin — importujte soubor `kovoprahacz09 (12).sql`.

### 2.4 Konfigurace db.php a config.php

`db.php` načítá `config.php` **před** připojením k databázi. Pořadí priorit:

1. Konstanty `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` z `config.php` — mají přednost
2. Localhost výchozí hodnoty (`root` / `''` / `evidence`) — fungují bez `config.php`
3. Produkce bez `config.php` → záměrné `die()` s informací o chybějící konfiguraci

**Lokální vývoj** — žádná konfigurace DB není potřeba, `root` bez hesla funguje automaticky.

**Produkce** — zkopírujte `config.example.php` jako `config.php` a doplňte:

```php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'nazev_db');
define('DB_USER', 'db_uzivatel');
define('DB_PASS', 'db_heslo');
```

> ⚠️ `config.php` je v `.gitignore` — nikdy nepřidávejte hesla do gitu.

### 2.5 Výchozí přihlašovací údaje

Závisí na importovaném SQL dumpu. Ověřené v lokálním vývojovém prostředí:

| Jméno | Heslo | Role |
|-------|-------|------|
| trener1 | heslo456 | trenér |

> Účet `hlavni / heslo123` byl součástí staršího dumpu — pokud v aktuální DB neexistuje,
> přidejte ho ručně v phpMyAdmin nebo přes `sprava_treneru.php` po přihlášení jako admin.

### 2.6 Oprávnění složek

Web server potřebuje právo zápisu do těchto složek:

```
nahrane_obrazky/
loga_story/
stories/
zatezove_testy/
uploads/
reports/
uctenky/
```

---

## 3. Struktura projektu

```
evidencePavel/
├── index.php                  # Nástěnka (dashboard)
├── login.php                  # Přihlášení
├── logout.php                 # Odhlášení
├── hlavicka.php               # Navigační lišta (include)
├── db.php                     # Připojení k databázi (PDO)
├── csrf_helper.php            # CSRF ochrana
├── composer.json              # Závislosti (PhpSpreadsheet)
│
├── formular.php               # Nový trénink (formulář)
├── ulozit_trenink.php         # Uložení tréninku (POST handler)
├── edit_trenink.php           # Editace tréninku
├── update_trenink.php         # Aktualizace tréninku (POST)
├── smazat_trenink.php         # Smazání tréninku
├── duplikovat_trenink.php     # Duplikace (soustředění)
│
├── moje_treninky.php          # Přehled tréninků trenéra
├── moje_skupiny.php           # Přehled skupin trenéra
├── prehled_trenera.php        # Měsíční přehled tréninků
├── prehled_sportovcu.php      # Přehled sportovců
├── prehled_treninku_skupiny_kalendar.php  # Kalendář
│
├── ajax_sportovci.php         # AJAX – vyhledávání sportovců
├── ajax_podskupiny.php        # AJAX – podskupiny podle skupiny
├── ajax_treninky.php          # AJAX – data tréninků (HTML) + fulltext ?q
├── ajax_update_poznamka.php   # AJAX – uložení poznámky
├── ajax_global_search.php     # AJAX – globální hledání (sportovci, tréninky, závody)
├── nacti_podskupiny.php       # AJAX – načtení podskupin
├── nacti_skupiny.php          # AJAX – načtení skupin
│
├── export_draha.php           # Export – dráha (XLSX)
├── export_uci.php             # Export – UCI přihláška (XLSX)
├── export_seznam.php          # Export – seznam sportovců (XLSX)
├── export.php / export_csv.php / export_xls.php  # Další exporty
│
├── sprava_*.php               # Administrace (skupiny, trenéři, závody, sportoviště…)
├── sprava_vsech_treninku.php  # Správa tréninků (admin) — fulltext, filtr kategorie, stats
├── prehled_zavodu.php         # Přehled závodů — kategorie filtr, stats, badge trenérů
├── formular_zavod.php         # Nový závod — kategorie, měření, účastníci, soubory
├── ulozit_zavod.php           # Uložení závodu (POST)
├── edit_zavod_form.php        # Formulář editace závodu (GET)
├── update_zavod.php           # Aktualizace závodu (POST)
├── zavod_detail.php           # Detail závodu — výsledky, měření, galerie
├── nova_cinnost.php           # Další činnost (mimotréninková)
├── vypis_vykazu.php           # Výkaz činností — stats, tabulky s součty, kategorie
│
├── kalendar_sportovist.php    # Týdenní kalendář obsazenosti — interní rezervace + informační bloky lekcí
├── rezervovat_sportoviste.php # Formulář interní rezervace sportoviště (live AJAX dostupnost; accept ?plan_id)
├── individualni_lekce_form.php  # Formulář individuální lekce — slot_delka_min, typ zelená/žlutá
├── individualni_lekce_sprava.php # Správa lekcí a rezervací zákazníků (potvrdit/zamítnout/zaplatit/zrušit)
├── ajax_dostupnost_sportovist.php # AJAX: obsazenost sportoviště (pouze lekce_id IS NULL)
│
├── planovac.php               # Plánovač tréninků — přehled, filtr skupiny/stavu, sdílení programu
├── planovany_trenink_form.php # Formulář nového/editovaného plánovaného tréninku
├── program_skupiny.php        # Veřejný program skupiny bez přihlášení (?hash=, ?tydny=)
│
├── booking/                   # Veřejný booking — zákazníci (oddělená session)
│   ├── registrace.php         # Registrace zákazníka (email + heslo, verifikační email)
│   ├── overeni.php            # Ověření emailu tokenem (GET ?token=)
│   ├── prihlaseni.php         # Přihlášení zákazníka
│   ├── odhlaseni.php          # Odhlášení zákazníka
│   ├── kalendar.php           # Veřejný kalendář lekcí s výběrem slotů
│   ├── rezervovat.php         # POST handler rezervace (slot_cas_od/do, zelená=ihned, žlutá=email)
│   ├── moje_rezervace.php     # Přehled a storno rezervací zákazníka (storno min. 3 dny)
│   └── potvrdit.php           # GET endpoint pro potvrzení/zamítnutí z emailu trenéra
│
├── includes/
│   ├── init.php               # session_start() + require db.php
│   ├── funkce.php             # Sdílené funkce (audit log, roleAtLeast, canAccess)
│   └── auto_migrace.php       # Auto-migrace DB schématu (SCHEMA_VERSION)
│
├── templates/
│   ├── prihlasovaci_tabulka_draha.xlsx
│   └── prihlasovaci_tabulka_uci.xlsx
│
├── vozidla/                   # Modul vozidel (admin-only)
│   ├── seznam.php / formular.php / uloz.php / smazat.php
├── jizdy/                     # Modul jízd (admin-only)
│   ├── seznam.php / formular.php / uloz.php / smazat.php
├── servis/                    # Modul servisu (admin-only)
│   ├── seznam.php / formular.php / uloz.php / smazat.php
├── uctenky/                   # Modul účtenek (admin-only)
│   ├── seznam.php / formular.php / uloz.php / smazat.php
├── udalosti/                  # Modul událostí (admin-only)
│   ├── seznam.php / formular.php / uloz.php / smazat.php
│   ├── vyuctovat.php          # Finanční vyúčtování události
│   └── uzavrit.php            # Uzavření události (AJAX → JSON)
├── auditlog/
│   └── seznam.php             # Prohlížeč audit logu
│
├── stories/                   # Generované story obrázky
├── nahrane_obrazky/           # Nahrané obrázky tréninků
├── loga_story/                # Loga pro story generátor
├── zatezove_testy/            # Soubory zátěžových testů
├── uploads/                   # Další uploady (servis, účtenky, segmenty)
├── reports/                   # Generované reporty
│
├── vendor/                    # Composer závislosti
├── docs/                      # Dokumentace
└── kovoprahacz09 (12).sql     # SQL dump databáze
```

---

## 4. Konvence kódu

### 4.1 Obecné

- **Jazyk:** PHP procedurální (bez frameworku, bez OOP)
- **Databáze:** PDO s prepared statements (`:named` nebo `?` placeholdery)
- **Kódování:** UTF-8 všude (PHP, HTML, databáze `utf8mb4`)
- **Pojmenování souborů:** české názvy, snake_case (`moje_treninky.php`, `sprava_skupin.php`)
- **Pojmenování proměnných:** české, camelCase nebo snake_case (`$currentTrener`, `$skupina_id`)
- **Pojmenování tabulek/sloupců:** české, snake_case (`trenink_sportovec`, `trener_id`)

### 4.2 Session pattern

Každá stránka vyžadující přihlášení začíná:

```php
<?php
session_start();
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'db.php';
```

Nebo pomocí sdíleného initu (pro podadresáře):

```php
<?php
require_once __DIR__ . '/../includes/init.php';
```

### 4.3 Autorizace rolí

Kontrola role hlavního trenéra (admin stránky v kořenovém adresáři):

```php
$isAdmin = (($_SESSION['role'] ?? '') === 'hlavni');

if (!$isAdmin) {
    header('Location: index.php');
    exit;
}
```

### 4.4 Admin-only modul pattern

Pro admin-only moduly v podadresářích (vozidla, jízdy, servis, účtenky, události):

```php
<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../csrf_helper.php';

// Přihlášení + admin role
if (!isset($_SESSION['trener_id']) || ($_SESSION['role'] ?? '') !== 'hlavni') {
    header('Location: ../login.php');
    exit;
}
```

Pro JSON endpointy těchto modulů (`uloz.php`, `uzavrit.php`):

```php
<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../csrf_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['trener_id']) || ($_SESSION['role'] ?? '') !== 'hlavni') {
    echo json_encode(['status' => 'error', 'message' => 'Přístup odepřen.']);
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    echo json_encode(['status' => 'error', 'message' => 'Neplatný CSRF token.']);
    exit;
}
```

### 4.5 Flash zprávy

Pro zobrazení úspěchu/chyby po přesměrování:

```php
// Nastavení
$_SESSION['flash_success'] = 'Trénink byl uložen.';
header('Location: moje_treninky.php');
exit;

// Zobrazení (na cílové stránce)
<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>
```

### 4.6 HTML výstup

- Vždy escapovat: `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`
- Zkratka používaná v některých souborech:
  ```php
  function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
  ```
- Bootstrap 5.3.3 (CDN) + Bootstrap Icons 1.11.3
- Hlavička: `<?php include 'hlavicka.php'; ?>` — obsahuje navbar, globální CSS (mobilní UX, validace, povinná pole) a globální searchbar

### 4.7 Povinná pole ve formulářích

Pro vizuální označení povinných polí (červená `*` u labelu):

```html
<label for="datum" class="form-label req">Datum:</label>
<input type="date" id="datum" name="datum" required>
```

CSS třída `req` je definována globálně v `hlavicka.php`. Automaticky přidá `::after` pseudoelement s `*`.

Pro aktivaci Bootstrap validačního feedbacku při submitu:

```javascript
form.addEventListener('submit', e => {
    form.classList.add('was-validated');
    // ... vlastní validace ...
});
```

### 4.8 Varování na neuložené změny

Pro formuláře s rizikem ztráty dat přidejte `beforeunload` handler:

```javascript
(() => {
    const form = document.getElementById('mojeFormId');
    let dirty = false, submitting = false;
    form.addEventListener('input',  () => { dirty = true; });
    form.addEventListener('change', () => { dirty = true; });
    form.addEventListener('submit', () => { submitting = true; });
    window.addEventListener('beforeunload', e => {
        if (dirty && !submitting) { e.preventDefault(); e.returnValue = ''; }
    });
})();
```

Implementováno v: `formular.php`, `edit_trenink.php`.

### 4.9 Toast notifikace

Globální systém pro zobrazování zpráv uživateli. Implementováno v `hlavicka.php`.

**PHP flash zprávy** (redirect pattern):
```php
$_SESSION['flash_success'] = 'Trénink uložen.';
$_SESSION['flash_error']   = 'Chyba při ukládání.';
$_SESSION['flash_warning'] = 'Pozor: neúplná data.';
$_SESSION['flash_info']    = 'Informace pro uživatele.';
header('Location: stranka.php');
exit;
```

Flash zprávy jsou automaticky zpracovány v `hlavicka.php` — stránky nemusí řešit `unset()` ani HTML pro flash zprávy.

**JavaScript API** (pro inline akce bez redirectu):
```javascript
showToast('Profil uložen.', 'success');
showToast('Chyba sítě.', 'danger');
```

Typy: `success`, `danger`, `warning`, `info`. Auto-dismiss po 4 s.

### 4.10 Booking auth pattern (veřejní zákazníci)

Pro stránky v `booking/` platí jiný autentizační vzor — zákaznická session je oddělena od trenérské:

```php
<?php
session_start();
require_once __DIR__ . '/../db.php';

// Kontrola přihlášení zákazníka
if (!isset($_SESSION['verejny_uzivatel_id'])) {
    header('Location: prihlaseni.php?redirect=moje_rezervace.php');
    exit;
}

$uzivatelId = (int)$_SESSION['verejny_uzivatel_id'];
```

Klíče zákaznické session: `verejny_uzivatel_id` (int), `verejny_uzivatel_jmeno` (string "Jméno Příjmení"). Trenérská session (`trener_id`) a zákaznická session koexistují beze konfliktu.

Email notifikace v booking systému se posílají přes `mail()` — žádná SMTP knihovna není potřeba. Token pro potvrzení rezervace trenérem: `bin2hex(random_bytes(24))` uložen do `verejne_rezervace.potvrzovaci_token`.

### 4.11 URL hash tabs

Pro záložkové stránky použijte URL hash persistence:
```javascript
var hash = window.location.hash;
if (hash) {
    var el = document.querySelector('#tabs button[data-bs-target="' + hash + '"]');
    if (el) bootstrap.Tab.getOrCreateInstance(el).show();
}
document.querySelectorAll('#tabs button[data-bs-toggle="tab"]').forEach(function(btn) {
    btn.addEventListener('shown.bs.tab', function(e) {
        history.replaceState(null, '', e.target.getAttribute('data-bs-target'));
    });
});
```

Implementováno v: `sportovec_detail.php` (`#tab-profil`, `#tab-treninky`, `#tab-mereni`, `#tab-testy`).

---

## 5. Přidání nové stránky

### Krok za krokem

#### 1. Vytvořte soubor `nova_stranka.php`

```php
<?php
session_start();
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'db.php';
require_once 'csrf_helper.php';

$currentTrener = (int)$_SESSION['trener_id'];
$isAdmin       = (($_SESSION['role'] ?? '') === 'hlavni');

// --- Zpracování POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        die('Neplatný CSRF token.');
    }
    // ... zpracování dat ...

    $_SESSION['flash_success'] = 'Uloženo.';
    header('Location: nova_stranka.php');
    exit;
}

// --- Načtení dat pro zobrazení ---
$data = $pdo->query("SELECT * FROM tabulka ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nová stránka – Evidence tréninků</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include 'hlavicka.php'; ?>

    <div class="container py-4">
        <h2>Nová stránka</h2>

        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            <!-- pole formuláře -->
            <button type="submit" class="btn btn-primary">Uložit</button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
```

#### 2. Přidejte odkaz do navigace

V `hlavicka.php` přidejte položku do příslušného dropdown menu, nebo na `index.php` do příslušné sekce.

#### 3. Přidejte audit log (volitelné)

```php
require_once __DIR__ . '/includes/funkce.php';
zapisAuditLog($pdo, $_SESSION['trener_id'], 'vytvoreni', 'tabulka', $noveId, 'Popis akce');
```

---

## 6. Přidání AJAX endpointu

### Vzor: `ajax_novy.php`

```php
<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Autorizace
if (!isset($_SESSION['trener_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Neautorizováno']);
    exit;
}

require_once __DIR__ . '/db.php';

// Pomocná funkce pro odpověď
function out(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Zpracování parametrů
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    out(['error' => 'Neplatné ID'], 400);
}

try {
    $stmt = $pdo->prepare("SELECT * FROM tabulka WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        out(['error' => 'Nenalezeno'], 404);
    }

    out(['data' => $row]);

} catch (Exception $e) {
    out(['error' => 'Chyba serveru'], 500);
}
```

### Volání z JavaScriptu

```javascript
fetch('ajax_novy.php?id=' + encodeURIComponent(id))
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            console.error(data.error);
            return;
        }
        // zpracování data.data
    });
```

### Existující AJAX endpointy

| Endpoint | Metoda | Parametry | Odpověď | Auth |
|----------|--------|-----------|---------|------|
| `ajax_sportovci.php` | GET | `q`, `limit` | JSON — našeptávač sportovců | session |
| `ajax_podskupiny.php` | GET | `skupina_id` | JSON `{ok, items}` | session |
| `ajax_treninky.php` | GET | `rok`, `mesic`, `skupina_id`, `q` | HTML accordion + fulltext `?q` | session |
| `ajax_update_poznamka.php` | POST | `trenink_id`, `poznamka` | JSON `{ok, message, poznamka}` | session + vlastnictví |
| `ajax_global_search.php` | GET | `q` (min 2 znaky) | JSON `{sportovci, treninky, zavody}` | session |
| `ajax_dostupnost_sportovist.php` | GET | `sportoviste_id`, `datum`, `cas_od`, `cas_do`, `excl_id?` | JSON `{obsazeno, max}` | session |
| `ajax_denny_rozvrh.php` | GET | `sportoviste_id`, `datum`, `ghost_od?`, `ghost_do?` | HTML — vertikální osa 10:00–20:00 s ghost preview | session |
| `ajax_update_plan.php` | POST | `akce` (move/rename), `plan_id`, `datum`/`nazev` | JSON `{ok, message}` | session + vlastnictví |
| `ajax_nova_oznameni.php` | POST | `titulek`, `text`, `datum`, `skupiny[]` | JSON `{ok, id}` | session |
| `ajax_sportovec_treninky.php` | GET | `hash`, `rok?`, `typ?` | HTML — seznam tréninků sportovce | **hash** (bez session) |
| `ajax_sportovec_poznamka.php` | POST | `hash`, `trenink_id`, `poznamka` | JSON `{ok}` | **hash** (bez session, bez CSRF) |
| `nacti_podskupiny.php` | GET | `skupina_id` | JSON pole `[{id, nazev}]` | session |
| `nacti_skupiny.php` | GET | — | HTML `<option>` skupin | session |

---

## 7. Práce s měřeními

### Typy měření

| Typ | Relevantní sloupce | Popis |
|-----|--------------------|-------|
| `kolo` | vzdalenost, cas, prevod, poznamka | Silniční/obecná jízda na kole |
| `kolo_krouzek` | segment_id (kat=krouzek), cas, poznamka | Jízda na kroužku — vybere se segment |
| `kolo_silnice` | segment_id (kat=silnice), cas, poznamka | Silniční segment |
| `kolo_mtb` | segment_id (kat=mtb), cas, poznamka | MTB segment |
| `beh` | vzdalenost, cas, poznamka | Běh |
| `posilovna` | cvik_id (z `cviky`), vaha, opakovani, rpe, poznamka | Posilovnová série |

Segmenty spravuje `sprava_segmentu.php` (CRUD s foto a 2 URL odkazy, kategorie kroužek/silnice/mtb). Cviky spravuje `cviky.php`.

### Architektura ukládání

Měření **nejsou** ukládána přímo s `trenink_id`. Používá se junction vzor:

```
treninky ──── trenink_mereni (m:n, pořadí) ──── mereni_zaznamy
závody   ──── zavod_mereni   (m:n, pořadí) ──── mereni_zaznamy
```

Tabulka `mereni_zaznamy` obsahuje vlastní záznamy; tabulky `trenink_mereni` / `zavod_mereni` propojují trénink/závod s měřeními a nesou sloupec `poradi`.

### Tok dat (formulář → DB)

**Na frontendu** (`formular.php`, `edit_trenink.php`, `formular_zavod.php`, `edit_zavod_form.php`):
- Dynamické JS řádky měření; při submitu serializovány do skrytého pole `mereni_json` jako JSON pole:

```json
[
  {"typ":"kolo","sportovec_id":5,"vzdalenost":"80","cas":"2:30:00","prevod":"53x19","poznamka":""},
  {"typ":"posilovna","sportovec_id":5,"cvik_id":3,"vaha":60,"opakovani":10,"rpe":7,"poznamka":""}
]
```

**Na serveru** (`ulozit_trenink.php`, `update_trenink.php`, `ulozit_zavod.php`, `update_zavod.php`):

```php
// Sdílená funkce v souboru handleru
function buildMereniRowsFromPost(array $post): array { ... }

// Použití v transakci
$rows = buildMereniRowsFromPost($_POST);
foreach ($rows as $i => $m) {
    $stmtM = $pdo->prepare("
        INSERT INTO mereni_zaznamy
            (typ, sportovec_id, vzdalenost, cas, prevod, segment_id, cvik_id, vaha, opakovani, rpe, poznamka)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtM->execute([
        $m['typ'], $m['sportovec_id'], $m['vzdalenost'], $m['cas'],
        $m['prevod'], $m['segment_id'], $m['cvik_id'],
        $m['vaha'], $m['opakovani'], $m['rpe'], $m['poznamka']
    ]);
    $mId = $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO trenink_mereni (trenink_id, mereni_id, poradi) VALUES (?, ?, ?)")
        ->execute([$treninkId, $mId, $i]);
}
```

**Při UPDATE** se stará měření smažou celá (DELETE FROM `mereni_zaznamy` kde `id IN (SELECT mereni_id FROM trenink_mereni WHERE trenink_id = ?)`) a vloží se nová. Nikdy se nemění individuální řádky.

### Formulář měření

Na frontendu se měření přidávají dynamicky — tlačítko „Přidat měření" vytvoří nový blok polí dle zvoleného typu. Typ řídí viditelnost polí (JS `show/hide`): kolo_krouzek/silnice/mtb zobrazí select segmentů, posilovna zobrazí select cviků.

---

## 8. Export do Excelu

### Závislost

```json
{
    "require": {
        "phpoffice/phpspreadsheet": "^5.3"
    }
}
```

### Vzor exportu

```php
<?php
require_once 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Vytvoření nového sešitu
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Export');

// Hlavička
$headers = ['Datum', 'Skupina', 'Trenér', 'Délka (h)'];
foreach ($headers as $col => $header) {
    $sheet->setCellValue([$col + 1, 1], $header);
}

// Data
$row = 2;
foreach ($data as $item) {
    $sheet->setCellValue([1, $row], $item['datum']);
    $sheet->setCellValue([2, $row], $item['skupina']);
    $sheet->setCellValue([3, $row], $item['trener']);
    $sheet->setCellValue([4, $row], $item['delka']);
    $row++;
}

// Stažení
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="export.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
```

### Export ze šablony

Pro formátované exporty (dráha, UCI) se používají šablony z `templates/`:

```php
use PhpOffice\PhpSpreadsheet\IOFactory;

$spreadsheet = IOFactory::load('templates/prihlasovaci_tabulka_draha.xlsx');
$sheet = $spreadsheet->getActiveSheet();

// Doplnění dat do existující šablony
$sheet->setCellValue('B3', $hodnota);
// ...

// Odeslání
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
```

---

## 9. Upload souborů

### Adresáře

| Adresář | Obsah |
|---------|-------|
| `nahrane_obrazky/` | Obrázky k tréninkům |
| `loga_story/` | Loga skupin pro story generátor |
| `stories/` | Vygenerované story obrázky |
| `zatezove_testy/` | Soubory zátěžových testů |
| `uploads/servis/` | Přílohy k servisním záznamům |
| `uploads/uctenky/` | Nahrané účtenky |
| `uploads/segmenty/` | Fotografie segmentů na kole |

### Konvence pojmenování

Soubory se typicky pojmenovávají s časovým razítkem, aby nedocházelo ke kolizím:

```php
$ext      = pathinfo($_FILES['soubor']['name'], PATHINFO_EXTENSION);
$filename = time() . '_' . uniqid() . '.' . $ext;
$cil      = 'nahrane_obrazky/' . $filename;

move_uploaded_file($_FILES['soubor']['tmp_name'], $cil);
```

### MIME validace

Pro bezpečnou validaci typů souborů používejte `finfo_file()` (ne `$_FILES['soubor']['type']`, který lze podvrhnout):

```php
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $_FILES['soubor']['tmp_name']);
finfo_close($finfo);

$allowed = ['image/jpeg', 'image/png', 'application/pdf'];
if (!in_array($mime, $allowed)) {
    // Odmítnout soubor — neplatný typ
}
```

Upload adresáře vytvářejte s oprávněním `0755`:

```php
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}
```

### Soft delete

Při smazání záznamu přejmenujte přiložený soubor místo permanentního smazání:

```php
$old = $upload_dir . $record['soubor'];
$new = $upload_dir . 'smazano_' . $record['soubor'];
if (file_exists($old)) rename($old, $new);
```

---

## 10. Bezpečnost

### 10.1 CSRF ochrana

Každý formulář musí obsahovat CSRF token:

```php
require_once 'csrf_helper.php';

// Ve formuláři:
<form method="POST">
    <?= csrf_field() ?>
    ...
</form>

// Při zpracování (doporučený vzor s flash zprávou):
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Neplatný CSRF token.';
    header('Location: seznam.php');
    exit;
}
```

Funkce z `csrf_helper.php`:
- `csrf_token()` — vrátí/vygeneruje token (uložen v `$_SESSION`)
- `csrf_field()` — vrátí `<input type="hidden" ...>`
- `csrf_verify($token)` — ověří token pomocí `hash_equals()`

### 10.2 SQL injection

Vždy používejte prepared statements:

```php
// SPRÁVNĚ
$stmt = $pdo->prepare("SELECT * FROM sportovci WHERE id = ?");
$stmt->execute([$id]);

// ŠPATNĚ — nikdy nepoužívat!
$pdo->query("SELECT * FROM sportovci WHERE id = $id");
```

### 10.3 XSS ochrana

Veškerý výstup do HTML escapujte:

```php
<?= htmlspecialchars($hodnota, ENT_QUOTES, 'UTF-8') ?>
```

### 10.4 Session fixation

Při přihlášení se regeneruje session ID:

```php
session_regenerate_id(true);
```

### 10.5 Autentizace hesel

Hesla jsou uložena jako **bcrypt hash** a ověřována přes `password_verify()`:

```php
$authenticated = password_verify($heslo, $storedHash);
```

Při prvním přihlášení s plaintext heslem (starší účty před migrací) dojde k automatickému přepsání bcrypt hashem. Nové účty mají bcrypt vždy. Sloupec `treneri.heslo` je `varchar(255)`.

### 10.6 Upload bezpečnost

- MIME validace přes `finfo_file()` (ne `$_FILES['type']`)
- Upload adresáře: oprávnění `0755` (ne `0777`)
- Soft delete: přejmenování s prefixem `smazano_`
- Kontrola `move_uploaded_file()` návratové hodnoty

### 10.7 Chybové zprávy

- Na login stránce: neurčitá zpráva „Neplatné jméno nebo heslo" (neprozrazuje existenci účtu)
- PDO chyby: logovat přes `error_log()`, nezobrazovat uživateli
- V produkci: `display_errors = Off`

---

## 11. Správa DB schématu (auto-migrace)

### Princip

Projekt používá systém auto-migrací v `includes/auto_migrace.php`. Migrace se spouštějí automaticky při každém PHP requestu jako součást `db.php` — **žádné manuální SQL na produkci není potřeba**.

### Jak přidat novou migraci

1. **Otevřete** `includes/auto_migrace.php`
2. **Zvyšte** konstantu `SCHEMA_VERSION` (sem-ver, např. `'2.10.0'` → `'2.11.0'`)
3. **Přidejte SQL krok** do sekce Migrace před část "P/Q/... Uložení verze schématu":

```php
// ── X. Popis změny ────────────────────────────────────────────────────────
if (!$colExists('tabulka', 'novy_sloupec')) {
    $exec("ALTER TABLE tabulka ADD COLUMN novy_sloupec VARCHAR(100) NULL");
}
```

4. **Nahrajte soubory** na produkci — migrace proběhne automaticky při prvním requestu

### Dostupné helper funkce

```php
$colExists('tabulka', 'sloupec')   // bool — existuje sloupec?
$tableExists('tabulka')            // bool — existuje tabulka?
$colDef('tabulka', 'sloupec')      // array|null — definice sloupce (SHOW COLUMNS)
$exec('ALTER TABLE ...')           // void — spustí SQL, chyby loguje, nekončí
```

### Guard pattern pro ENUM rozšíření

```php
$def = $colDef('zavody', 'kategorie');
if ($def && strpos($def['Type'] ?? '', 'nova_hodnota') === false) {
    $exec("ALTER TABLE zavody MODIFY COLUMN kategorie ENUM('silnice','draha','mtb','nova_hodnota') NOT NULL DEFAULT 'silnice'");
}
```

### Verze schématu

Verze je uložena v `nastaveni.schema_version`. Při každém requestu proběhne jediný `SELECT` — pokud verze sedí, žádné ALTER se nespouští.

### Výchozí záznamy `opravneni`

Výchozí záznamy pro tabulku `opravneni` jsou v poli `$defaultOpravneni` v `auto_migrace.php`. Vkládají se přes `INSERT IGNORE` — ručně upravené hodnoty v produkci se **nepřepisují**.

---

## 12. Nasazení

### 12.1 Příprava produkčního prostředí

1. **Nahrání souborů** na server (FTP/SFTP), bez:
   - `vendor/` (nainstalovat přes `composer install --no-dev` na serveru)
   - `.sql` dumpů
   - `.claude/`, `docs/` (volitelné)
   - testovacích souborů (`test.php`)

2. **Databáze** — při prvním nasazení importovat SQL dump; při aktualizacích stačí nahrát soubory — auto-migrace se spustí automaticky při prvním requestu.

3. **db.php** — produkční větev se aktivuje automaticky (detekce `SERVER_NAME != localhost`)

4. **Oprávnění** — nastavit zápis do upload složek:
   ```bash
   chmod 755 nahrane_obrazky/ loga_story/ stories/ zatezove_testy/ uploads/ reports/
   ```

5. **Composer**:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

### 12.2 Checklist nasazení

- [ ] `db.php` — produkční údaje nastaveny
- [ ] `config.php` vytvořen z `config.example.php` (Velocota přepínač, API klíče)
- [ ] `composer install --no-dev` provedeno
- [ ] Upload složky mají správná oprávnění (`chmod 755`)
- [ ] `display_errors = Off` v php.ini
- [ ] Testovací soubory odstraněny
- [ ] Hesla trenérů změněna z výchozích
- [ ] VAPID klíče vygenerovány a uloženy do `nastaveni` tabulky (pokud chcete Web Push)
- [ ] Cron pro upomínky nastaven: `0 7 * * * php /cesta/cron_upominky.php`
- [ ] ~~Manuální SQL migrace~~ — **není potřeba**, `includes/auto_migrace.php` se spustí automaticky

> **Viz také:** `docs/instalace.md` pro detailní shell příkazy krok za krokem.

---

## 13. config.php — lokální konfigurace

Soubor `config.php` v kořeni aplikace (není v gitu, vzor v `config.example.php`):

```php
<?php
// Integrace s Velocotou
define('VELOCOTA_INTEGRATION', false);  // true = produkce, false = standalone
define('VELOCOTA_ROOT', '/var/www/html/velocota');
define('VELOCOTA_EVIDENCE_BASE_URL', 'https://kovopraha.cz/evidence');

// Session klíče z Velocoty (NEMĚNIT bez koordinace s Velocota týmem)
define('VELO_SESSION_USER_ID',  'velo_user_id');
define('VELO_SESSION_ROLE',     'velo_role');
define('VELO_SESSION_JMENO',    'velo_jmeno');
define('VELO_SESSION_EMAIL',    'velo_email');
```

Bez `config.php` aplikace funguje normálně v standalone módu — soubor je volitelný.

---

*Verze dokumentace: 2.19.2 — červen 2026*
