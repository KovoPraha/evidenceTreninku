# Evidence Tréninků — CLAUDE.md

Projektový kontext pro Claude Code. Tento soubor slouží jako rychlý přehled pro AI asistenta.

## Projekt

Webová aplikace pro správu tréninků, sportovců a závodů cyklistického klubu. PHP procedurální styl bez frameworku.

**Nadřazená aplikace:** Tato aplikace je sub-modulem platformy **Velocota** (klubový portál Kovopraha). Viz `docs/integrace-velocota.md` pro plný integrační kontext.

## Plánovaná rozšíření — přehled záměrů

> Změny nejsou implementovány. Viz `docs/roadmapa-rozsireni.md` pro plný plán.

| Změna | Fáze | Stav |
|-------|------|------|
| Propojení sportovců s veřejnými profily (`verejni_uzivatele.sportovec_id`) | 1 | ⏳ Plánováno |
| Kreditní wallet (`verejni_uzivatele.kredit_zustatek` + tabulka `kredit_pohyby`) | 1 | ⏳ Plánováno |
| API bridge pro externí e-shop (složka `api/`, SSO tokeny) | 2 | ⏳ Plánováno |
| Jednotný profil (booking + e-shop pod jedním účtem) | 2 | ⏳ Plánováno |

**Hlavní architektonické rozhodnutí (nutno rozhodnout před Fází 2):**
- Kde je „master" identity — Evidence nebo e-shop?
- REST API nebo sdílená DB pro komunikaci s e-shopem?
- Mají kredity expiraci?

---

## Integrace Velocota — klíčový kontext pro cowork

> Čti toto VŽDY před úpravou auth, session nebo navigace.

| Oblast | Stav | Soubor |
|--------|------|--------|
| SSO bridge (auth) | ✅ připraveno | `auth/sso_bridge.php` |
| Přepínač integrace | ✅ připraveno | `config.php` (z `config.example.php`) |
| DB migrace (velo_user_id) | ✅ v 2.18.0 | `includes/auto_migrace.php` |
| Podmíněná navigace | ⏳ TODO | `hlavicka.php` |
| Booking SSO | ⏳ TODO (Fáze 2) | `booking/prihlaseni.php` |

### Standalone vs. integrovaný mód

```php
// config.php
define('VELOCOTA_INTEGRATION', false); // lokální vývoj — vlastní login.php
define('VELOCOTA_INTEGRATION', true);  // produkce — SSO z Velocoty
```

Standalone mód (`login.php`) musí vždy zůstat funkční pro lokální vývoj.

### Session klíče — KONTRAKT S VELOCOTOU

Velocota zapíše, Evidence čte přes `auth/sso_bridge.php`:

| Velocota klíč | Evidence mapuje na |
|---------------|-------------------|
| `velo_user_id` | `treneri.velo_user_id` → `$_SESSION['trener_id']` |
| `velo_role` | `trener\|hlavni_trener\|admin` → `$_SESSION['role']` |
| `velo_jmeno` | `$_SESSION['trener_jmeno']` |
| `velo_email` | lookup v `treneri.email` |

**Tyto klíče nesmíš přejmenovat bez koordinace s Velocota týmem.**

### Co neměnit bez koordinace

- `auth/sso_bridge.php` — kontrakt s Velocotou
- `treneri.velo_user_id` sloupec
- `login.php` — musí zůstat pro standalone mód
- `booking/` auth flow — čeká na rozhodnutí fáze 2

## Technologie

- **Backend:** PHP 8+ (procedurální, PDO, žádný framework)
- **Databáze:** MySQL / MariaDB (`utf8mb4`), připojení přes PDO v `db.php`
- **Frontend:** Bootstrap 5.3.3, Bootstrap Icons 1.11.3, vanilla JavaScript (žádný build step)
- **Exporty:** PhpSpreadsheet ^5.3 (Composer)
- **Obrázky:** GD knihovna (story generátor)
- **Server:** Apache (XAMPP), kořen: `C:\xampp\htdocs\evidencePavel\`

## Klíčové soubory

| Soubor | Účel |
|--------|------|
| `db.php` | Připojení k DB — auto-detekce localhost vs produkce + `require includes/auto_migrace.php` |
| `hlavicka.php` | Navigační panel + globální searchbar + globální CSS (mobile, req, ...) |
| `csrf_helper.php` | CSRF ochrana: `csrf_field()`, `csrf_verify()`, `csrf_token()` |
| `includes/init.php` | Session start + require db.php (pro podadresáře) |
| `includes/funkce.php` | Sdílené funkce (roleAtLeast, canAccess, roleBadge, audit log) |
| `includes/auto_migrace.php` | Auto-migrace DB schématu — `SCHEMA_VERSION`, ALTER TABLE jen pokud verze nesedí |
| `index.php` | Nástěnka — stats karty + tři sloupce: Vkládání, Závodní sekce, Administrace |
| `ajax_global_search.php` | AJAX: globální vyhledávání (sportovci, tréninky, závody) |
| `prehled_zavodu.php` | Přehled závodů — kategorie filtr+badge, stats, detail/edit tlačítka |
| `formular_zavod.php` | Nový závod — kategorie, měření panel, účastníci (chip autocomplete), soubory, URL výsledků |
| `ulozit_zavod.php` | Uložení závodu (POST) — buildMereniRowsFromPost(), zavod_mereni, fotky |
| `edit_zavod_form.php` | Formulář editace závodu — předvyplnění měření z zavod_mereni, prefillMereni() JS |
| `update_zavod.php` | Aktualizace závodu (POST) — delete/reinsert měření, soft-delete fotek |
| `zavod_detail.php` | Detail závodu — výsledky (int/ext závodníci), měření, galerie, soubory, tisk |
| `sprava_zavodu.php` | Admin přehled závodů — kategorie badge, edit+detail akce |
| `vypis_vykazu.php` | Výkaz činností — stats karty, tabulky s součty, kategorie badge |
| `sprava_vsech_treninku.php` | Správa tréninků (admin) — fulltext hledání, filtr kategorie, stats |
| `sync_evidence.php` | KIS synchronizace ze tří XLSX exportů (uživatelé, platby, soupisky) — 4-krokový wizard |
| `sprava_segmentu.php` | Správa segmentů na kole — CRUD s foto uploadem, kategorie kroužek/silnice/mtb |
| `nastaveni_opravneni.php` | Nastavení oprávnění — admin UI pro konfiguraci přístupu dle rolí |
| `nastaveni_zadavani.php` | Okno pro zadávání tréninků — rolling integer (dni_zpet), ne fixní datum |
| `sprava_sportovist.php` | CRUD správa sportovišť — admin, název/kód/veřejné/kapacita/pořadí |
| `kalendar_sportovist.php` | Týdenní kalendář obsazenosti sportovišť — barevné bloky dle trenéra, kapacita X/5; dvě akční ikonky (rezervace 🔒 / lekce 👤) pro každý den a sportoviště |
| `rezervovat_sportoviste.php` | Formulář nové interní rezervace sportoviště — dvou-sloupcový layout, sidebar s denním rozvrhem (AJAX), ghost preview vybraného času |
| `individualni_lekce_form.php` | Formulář nové individuální lekce — typ zelená/žlutá, checkbox výjimky 3denního limitu, sidebar rozvrhu; URL předvyplnění `?datum=X&sportoviste_id=Y` |
| `individualni_lekce_sprava.php` | Správa lekcí — potvrdit/zamítnout/zaplatit/zrušit; badge počtu na čekací listině; zamítnutí → volá `notifyWaitingList()` |
| `planovac.php` | Týdenní plánovač — výchozí „Jen moje", kopírování týdne, varování na nezaevidované |
| `planovany_trenink_form.php` | Formulář plánovaného tréninku — více podskupin (checkboxy, junction tabulka), opakování none/počet/do-data |
| `ajax_dostupnost_sportovist.php` | AJAX: obsazenost sportoviště v čase → `{obsazeno, max}` (JSON) |
| `ajax_denny_rozvrh.php` | AJAX: HTML sidebar s vertikální časovou osou 10:00–20:00 — rezervace, lekce, plány, ghost preview |
| `booking/registrace.php` | Registrace zákazníka — email + heslo, verifikační email s tokenem |
| `booking/overeni.php` | Ověření emailu zákazníka — GET `?token=`, start session |
| `booking/prihlaseni.php` | Přihlášení zákazníka — session `verejny_uzivatel_id` |
| `booking/odhlaseni.php` | Odhlášení zákazníka — zrušení session, redirect na přihlásit |
| `booking/kalendar.php` | Veřejný kalendář lekcí — velodrom + horní posilovna, zelené/žluté lekce, modal se sloty |
| `booking/rezervovat.php` | POST handler rezervace — zelená=ihned, žlutá=email trenérovi; `?waitlist=1` → čekací listina; respektuje `vyjimka_3_dny` |
| `booking/moje_rezervace.php` | Přehled a storno rezervací + čekací listiny; storno aktivní rezervace → volá `notifyWaitingList()` |
| `booking/potvrdit.php` | GET endpoint pro trenéra — potvrzení/zamítnutí z emailu (`?token=&akce=`); po zamítnutí volá `notifyWaitingList()` — stejně jako `individualni_lekce_sprava.php` |
| `booking/waiting_list.php` | Helper `notifyWaitingList(PDO, lekceId, slotOd)` — automatické přiřazení slotu prvnímu čekajícímu + email |
| `cron_upominky.php` | CLI/web cron — upomínky trenérům na nezaevidované plány; web vyžaduje `?secret=TOKEN` shodný s env `UPOMINKA_SECRET` |

## Databáze

- **Localhost:** DB `evidence`, user `root`, bez hesla
- **Produkce:** DB `kovoprahacz09`, user `kovoprahacz010`

### Hlavní tabulky

| Tabulka | Popis |
|---------|-------|
| `treneri` | Uživatelé (id, jmeno, email, heslo, role ENUM trener/hlavni/admin) |
| `sportovci` | Sportovci (id, jmeno, prijmeni, narozeni, hash, email, rc, telefon, adresa_ulice/cp/co/obec/psc, uci) |
| `skupiny` | Skupiny (id, nazev, poradi, hash) |
| `podskupiny` | Podskupiny (id, nazev, skupina_id, poradi) |
| `sportovec_skupina` | M:N sportovec ↔ skupina |
| `sportovec_podskupina` | M:N sportovec ↔ podskupina |
| `treninky` | Tréninky (datum, napln, poznamka, delka, kategorie, obrazky, mereni_json) |
| `trenink_sportovec` | M:N trénink ↔ sportovec |
| `trenink_skupina` | M:N trénink ↔ skupina |
| `trenink_trener` | M:N trénink ↔ trenér |
| `zavody` | Závody (id, datum, **kategorie ENUM silnice/draha/mtb**, popis, poznamka, **url_vysledky**, trener_id) |
| `zavod_sportovec` | Výsledky závodu — sportovec_id **nullable** (externí závodníci), poradi, cas, body, **jmeno_ext**, **klub**, **kategorie_start** |
| `zavod_mereni` | M:N závod ↔ měření (poradi) — jako trenink_mereni |
| `mereni_zaznamy` | Měření (typ ENUM kolo/beh/posilovna/kolo_krouzek/kolo_silnice/**kolo_mtb**, sportovec_id, vzdalenost, cas, prevod, cvik_id, segment_id, vaha, opakovani, rpe, poznamka) |
| `trenink_mereni` | M:N trénink ↔ měření (poradi) |
| `nastaveni` | Klíč-hodnota: `schema_version` (auto_migrace), `zadavani_dni_zpet` (rolling integer) |
| `segmenty` | Segmenty na kole (nazev, popis, fotografie, odkaz_1, odkaz_2, kategorie ENUM krouzek/silnice/mtb, poradi, aktivni) |
| `soupiska_mapping` | Persistentní mapování soupisek z Excelu → skupina_id + podskupina_id |
| `cviky` | Cviky pro posilovnu (nazev, popis, poradi, aktivni) |
| `opravneni` | Konfigurovatelná oprávnění (klic, nazev, popis, min_role ENUM trener/hlavni/admin, skupina, poradi) |
| `sportovist` | Sportoviště (id, kod, nazev, je_verejne, max_kapacita, poradi, aktivni) |
| `rezervace_sportovist` | Interní rezervace (sportoviste_id, trener_id, datum, cas_od, cas_do, kapacita_dilu 1-5, trenink_id?, lekce_id?) |
| `verejni_uzivatele` | Zákazníci veřejného bookingu (jmeno, prijmeni, email, heslo_hash, telefon, verifikacni_token, email_overeno, **sportovec_id** ⏳, **kredit_zustatek** ⏳) |
| `individualni_lekce` | Placené lekce (trener_id, sportoviste_id, datum, cas_od, cas_do, slot_delka_min, typ ENUM zelena/zluta, nazev, popis, cena_kc, max_osob, **vyjimka_3_dny**, stav) |
| `verejne_rezervace` | Rezervace zákazníků (lekce_id, uzivatel_id, stav ENUM **ceka/potvrzena/zamitnuta/zrusena/cekaci_listina**, zaplaceno, potvrzovaci_token, slot_cas_od, slot_cas_do) |
| `kredit_pohyby` | ⏳ *Plánováno* — Transakční log kreditního walletu (uzivatel_id, typ ENUM nabiti/cerpani/storno, castka, zustatek_po, zdroj, ref_id) |
| `sso_tokeny` | ⏳ *Plánováno* — Jednorázové SSO tokeny pro e-shop přihlášení (token, uzivatel_id, vypršení, použito) |
| `planovane_treninky` | Plánované tréninky (trener_id, nazev, kategorie, skupina_id?, podskupina_id?, datum, cas_od?, cas_do?, sportoviste_id?, rezervace_id?, popis, misto, stav ENUM planovany/zruseny/evidovany, trenink_id?, **upominka_cas**?) |
| `planovane_treninky_podskupiny` | M:N plán ↔ podskupina — více podskupin na jeden plán (plan_id, podskupina_id) |

## Autentizace a role

- Session-based: `$_SESSION['trener_id']`, `$_SESSION['trener_jmeno']`, `$_SESSION['role']`
- 3 role (hierarchické): `'trener'` (1) < `'hlavni'` / Správce (2) < `'admin'` / Administrátor (3)
- `roleAtLeast(role)` — hierarchická kontrola: admin dědí vše od správce, správce vše od trenéra
- `canAccess(klic)` — konfigurovatelná kontrola z tabulky `opravneni` (načteno do `$_SESSION['opravneni']` při loginu)
- `nastaveni_opravneni.php` — admin stránka pro nastavení minimální role u každé funkce
- Hardcoded výjimky: `sprava_treneru.php` a `nastaveni_opravneni.php` → vždy `roleAtLeast('admin')`
- **Login** (`login.php`): přihlášení jménem **nebo emailem** — `WHERE jmeno = ? OR email = ?`; automatická migrace plaintext → bcrypt při prvním úspěšném přihlášení

### Veřejný booking (`booking/`)

Zákazníci mají vlastní session namespace — nelze zaměňovat s trenérskou session:
- `$_SESSION['verejny_uzivatel_id']` — int ID zákazníka z `verejni_uzivatele`
- `$_SESSION['verejny_uzivatel_jmeno']` — "Jméno Příjmení"
- Guard: `if (!isset($_SESSION['verejny_uzivatel_id'])) { header('Location: prihlaseni.php'); exit; }`
- Soubory v `booking/` používají `require_once __DIR__ . '/../db.php'` (ne init.php)
- Email notifikace přes `mail()`, tokeny: `bin2hex(random_bytes(24))` v `verejne_rezervace.potvrzovaci_token`
- Storno / rezervace zpravidla min. 3 dny: `$minDatum = (new DateTime())->modify('+3 days')->format('Y-m-d')`
- **Výjimka 3 dní**: pokud `individualni_lekce.vyjimka_3_dny = 1`, zákazník může rezervovat i méně než 3 dny předem (nastavuje trenér při vypisování lekce)

## Vzory kódu

### Nová stránka (šablona)

```php
<?php
session_start();
if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
require_once 'db.php';
require_once 'csrf_helper.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// POST handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) { $errors[] = 'Neplatný CSRF token.'; }
    else { /* ... */ }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Titulek</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons a mobilní/validační CSS je automaticky v hlavicka.php -->
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>
<div class="container mt-4">
    <!-- obsah -->
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
```

### Admin-only modul v podadresáři

```php
<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../csrf_helper.php';
if (!isset($_SESSION['trener_id']) || ($_SESSION['role'] ?? '') !== 'hlavni') {
    header('Location: ../login.php');
    exit;
}
```

### Excel export (PhpSpreadsheet)

```php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setCellValue('A1', 'Hlavička');
// ...
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="soubor.xlsx"');
$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save('php://output');
exit;
```

### Varování na neuložené změny (formuláře)

Vzor pro formuláře s rizikem ztráty dat (viz `formular.php`, `edit_trenink.php`):

```javascript
(() => {
    const form = document.getElementById('formId');
    if (!form) return;
    let dirty = false, submitting = false;
    form.addEventListener('input',  () => { dirty = true; });
    form.addEventListener('change', () => { dirty = true; });
    form.addEventListener('submit', () => { submitting = true; });
    window.addEventListener('beforeunload', e => {
        if (dirty && !submitting) { e.preventDefault(); e.returnValue = ''; }
    });
})();
```

### Povinná pole ve formulářích

CSS třída `req` na labelu přidá červenou `*` (definováno globálně v `hlavicka.php`):

```html
<label for="datum" class="form-label req">Datum tréninku:</label>
<input type="date" name="datum" required>
```

Pro aktivaci Bootstrap validačního stylu při submitu:
```javascript
form.addEventListener('submit', e => {
    form.classList.add('was-validated');
    // ...
});
```

## Export moduly

| Soubor | Popis |
|--------|-------|
| `export_draha.php` | Export pro dráhu — ze šablony `templates/prihlasovaci_tabulka_draha.xlsx` |
| `export_uci.php` | UCI přihláška — upload šablony → výběr skupiny/sportovců (max 11) → vyplnění šablony |
| `export_seznam.php` | Seznam sportovců — výběr skupiny → výběr sportovců → čistý XLSX (příjmení, jméno, datum nar., ročník, kategorie, UCI ID) |
| `export_xls.php` | Měsíční tréninky do XLSX |
| `export_csv.php` | Měsíční tréninky do CSV |

## AJAX endpointy

| Soubor | Metoda | Popis |
|--------|--------|-------|
| `ajax_treninky.php` | GET `?rok&mesic&skupina_id&q` | HTML accordion tréninků, fulltext hledání přes `?q` |
| `ajax_sportovci.php` | GET `?q&limit` | JSON: našeptávač sportovců |
| `ajax_podskupiny.php` | GET `?skupina_id` | JSON `{ok, items:[{id,nazev}]}`: podskupiny skupiny |
| `ajax_update_poznamka.php` | POST `trenink_id, poznamka` | JSON: uložení poznámky inline |
| `ajax_global_search.php` | GET `?q` | JSON: globální hledání (sportovci, tréninky, závody) |
| `nacti_podskupiny.php` | GET `?skupina_id` | JSON: podskupiny dané skupiny (pro dynamické selecty) |
| `ajax_dostupnost_sportovist.php` | GET `?sportoviste_id&datum&cas_od&cas_do` | JSON `{obsazeno, max}`: obsazenost sportoviště v čase |
| `ajax_denny_rozvrh.php` | GET `?sportoviste_id&datum[&ghost_od&ghost_do]` | HTML: vertikální časová osa 10:00–20:00, všechny typy bloků + ghost preview |

## Globální CSS v `hlavicka.php`

`hlavicka.php` injektuje do každé stránky CSS pro:
- **Aktivní stránka v navbaru** — `.nav-link.active`, `.dropdown-item.active`
- **Povinná pole** — `.form-label.req::after` (červená `*`), `input[required]` (červený levý border), `.was-validated` stavy
- **Mobilní použitelnost** — `min-height: 44px` pro buttony/nav-linky, `font-size: 16px` pro inputy (zabrání iOS zoom), automatický `overflow-x: auto` tabulkám

## Kategorie tréninků (`treninky.kategorie`)

| Hodnota | Label | Barva |
|---------|-------|-------|
| `silnice` | Silnice | zelená |
| `mtb` | MTB | žlutá |
| `draha` | Dráha | modrá |
| `cyklokros` | Cyklokros | oranžová |
| `posilovna` | Posilovna | červená |
| `atletika` | Atletika | modrá-info |
| `cviceni` | Cvičení | šedá |
| `plavani` | Plavání | teal |

## Adresářová struktura

```
evidencePavel/
├── *.php                    # Hlavní stránky (kořenový adresář)
├── includes/                # init.php, funkce.php
├── vendor/                  # Composer (PhpSpreadsheet)
├── templates/               # XLSX šablony pro export
├── docs/                    # Dokumentace projektu
├── uploads/                 # Nahrané soubory (servis, účtenky, temp, segmenty)
├── nahrane_obrazky/         # Obrázky tréninků
├── stories/                 # Generované story obrázky
├── loga_story/              # Loga pro story
├── vozidla/                 # Admin modul – vozidla
├── jizdy/                   # Admin modul – jízdy
├── servis/                  # Admin modul – servis
├── uctenky/                 # Admin modul – účtenky
├── udalosti/                # Admin modul – události
├── auditlog/                # Prohlížeč audit logu
├── zatezove_testy/          # Zátěžové testy
└── booking/                 # Veřejný booking — zákazníci (bez trenérské session)
```

## Konvence

- **Jazyk UI:** čeština
- **Pojmenování souborů:** české, snake_case (`moje_treninky.php`, `export_seznam.php`)
- **Pojmenování tabulek/sloupců:** české, snake_case
- **CSRF:** povinné na všech POST formulářích (`<?= csrf_field() ?>` + `csrf_verify()`)
- **XSS ochrana:** `htmlspecialchars()` nebo helper `h()` na veškerý výstup
- **SQL:** vždy prepared statements (PDO)
- **Navigace:** odkazy přidat do `hlavicka.php` (navbar) a/nebo `index.php` (dashboard karty)
- **Upload validace:** `finfo_file()` pro MIME typ, oprávnění `0755`
- **Soft delete:** přejmenování souboru s prefixem `smazano_`
- **Povinná pole:** třída `req` na `<label>`, atribut `required` na poli; `was-validated` na form při submitu
- **Varování na neuložené změny:** `beforeunload` event s dirty-flag vzorem (viz formuláre s rozsáhlými daty)
- **Bootstrap Icons:** `<i class="bi bi-nazev">` — dostupné ze všech stránek přes `hlavicka.php`
- **Toast notifikace:** `showToast(message, type)` — globální JS funkce v `hlavicka.php`, typy: `success`, `danger`, `warning`, `info`
- **Flash → Toast:** `$_SESSION['flash_success/error/warning/info']` → automaticky toast v `hlavicka.php`
- **URL hash tabs:** `sportovec_detail.php` — záložky persistují přes URL hash (`#tab-treninky`)

## Dokumentace

Kompletní dokumentace v `docs/`:
- `README.md` — přehled projektu
- `uzivatelska-prirucka.md` — návod pro uživatele
- `technicka-dokumentace.md` — architektura, API, endpointy
- `databazove-schema.md` — všechny tabulky a vztahy
- `vyvojarsky-pruvodce.md` — instalace, konvence, vzory kódu

---

### Stats karty (vzor)

Souhrnné karty nad tabulkami — Bootstrap `card text-center border-0 shadow-sm`:
```html
<div class="card text-center border-0 shadow-sm h-100">
    <div class="card-body">
        <div class="fs-2 fw-bold text-primary"><?= $value ?></div>
        <div class="text-muted small"><i class="bi bi-icon me-1"></i>Popis</div>
    </div>
</div>
```

Implementováno v: `prehled_trenera.php`, `prehled_zavodu.php`, `vypis_vykazu.php`, `sprava_vsech_treninku.php`, `index.php`.

### Kategorie meta (vzor)

Sdílený vzor pro mapování kategorií na barvy a ikony:
```php
$kategorieMeta = [
    'silnice'   => ['label'=>'Silnice',    'color'=>'success',  'icon'=>'bi-bicycle'],
    'mtb'       => ['label'=>'MTB',        'color'=>'warning',  'icon'=>'bi-tree'],
    // ...
];
```

Vlastní CSS třídy pro nestandardní barvy:
```css
.badge-orange { background-color: #fd7e14; color: #fff; }
.badge-teal   { background-color: #20c997; color: #fff; }
```

### Toast notifikace (vzor)

Globální systém v `hlavicka.php` — automatické zobrazení flash zpráv + JS API:

```php
// PHP — nastavit flash zprávu (redirect pattern)
$_SESSION['flash_success'] = 'Uloženo.';
header('Location: stranka.php');
```

```javascript
// JS — přímo na stránce
showToast('Profil uložen.', 'success');
showToast('Chyba při ukládání.', 'danger');
```

Typy: `success` (zelená), `danger` (červená), `warning` (žlutá), `info` (modrá). Auto-dismiss po 4 s.

### Auto-migrace DB schématu (vzor)

Soubor: `includes/auto_migrace.php`. Volán z konce `db.php`.

```php
define('SCHEMA_VERSION', '2.16.0');

(function (PDO $pdo): void {
    // Zajistí tabulku nastaveni
    // Zkontroluje schema_version — 1 SELECT per request, vrátí se pokud sedí
    // Spustí ALTER/CREATE jen pokud nesedí
    // Uloží novou verzi
})($pdo);
```

**Postup přidání migrace:**
1. Přidej SQL krok do `includes/auto_migrace.php`
2. Zvyš `SCHEMA_VERSION` (např. `'2.7.0'` → `'2.8.0'`)
3. Nahraj soubory — migrace proběhne automaticky

Guard pro existenci sloupce: `$colExists('tabulka', 'sloupec')` (information_schema, funguje MySQL i MariaDB).
Guard pro ENUM rozšíření: `$colDef('tabulka', 'sloupec')` → `strpos($def['Type'], 'nova_hodnota') === false`.

### Kategorie závodů (`zavody.kategorie`)

| Hodnota | Label | Bootstrap barva | Ikona |
|---------|-------|-----------------|-------|
| `silnice` | Silnice | `success` | `bi-bicycle` |
| `draha` | Dráha | `primary` | `bi-stopwatch` |
| `mtb` | MTB | `warning` | `bi-tree` |

### Měření v tréninku a závodech (vzor)

Systém měření ve `formular.php`, `edit_trenink.php`, `formular_zavod.php`, `edit_zavod_form.php`:
- **Typ měření**: kolo, kolo_krouzek, kolo_silnice, kolo_mtb, běh, posilovna
- Data uložena v `mereni_zaznamy` + `trenink_mereni` nebo `zavod_mereni` (M:N s pořadím)
- JSON serializace na klientu → POST `mereni_json` → `buildMereniRowsFromPost()` v handlerech
- **Kolo/Běh**: vzdálenost, čas, převod, poznámka
- **Kolo - Kroužek/Silnice/MTB**: segment (select z `segmenty` dle kategorie), čas, poznámka
- **Posilovna**: cvik (select z `cviky`), váha, opakování, RPE, poznámka

Segmenty spravuje `sprava_segmentu.php` (CRUD s foto uploadem, 2 URL odkazy, kategorie kroužek/silnice/mtb).
Cviky spravuje `cviky.php`.

### Synchronizace evidence (vzor)

`sync_evidence.php` — 4-krokový wizard pro KIS synchronizaci ze tří XLSX exportů:
1. **Upload** — uživatelé, platby a soupisky; parser je v `includes/kis_sync_lib.php`
2. **Mapování soupisek** — persistentní tabulka `soupiska_mapping`, AJAX podskupiny, auto-match
3. **Preview** — stats karty, nové/aktualizované/beze změn + počet DB osob mimo import
4. **Provedení** — DB transakce, INSERT/UPDATE, `kis_*` platební a soupiskový stav, mapované vazby na skupiny/podskupiny

Archivace: automatická archivace osob mimo KIS import je vypnutá; chybějící řádek v jednom exportu není ukončené členství.

### Plánovač tréninků (`planovac.php`)

- Výchozí filtr: `jen_moje=1` — trenér vidí pouze své plány; přepínač Moje/Vše v UI
- Při přepnutí na „Vše" se zobrazí dropdown výběru trenéra
- Plánovaný trénink lze navázat na rezervaci sportoviště (`rezervace_id`) nebo ponechat samostatný
- Více podskupin: ukládáno do junction tabulky `planovane_treninky_podskupiny`; legacy sloupec `podskupina_id` zachován pro zpětnou kompatibilitu
- `planovac.php` zobrazuje i plánované tréninky bez rezervace, které mají `sportoviste_id` — stejné bloky jsou vidět v `kalendar_sportovist.php`
- Veřejná karta sportovce (`sportovec_treninky.php`) zobrazuje záložku **Plán** — dotaz přes `sportovec_skupina`, bez filtru podskupin (stejná logika jako `sportovec_detail.php`)

### Rezervační systém sportovišť — architektura

| Typ záznamu | Tabulka | Kdo vytváří | Zobrazení |
|-------------|---------|-------------|-----------|
| Interní rezervace týmu | `rezervace_sportovist` | Trenér | `kalendar_sportovist.php` |
| Plánovaný trénink | `planovane_treninky` | Trenér | `planovac.php`, `kalendar_sportovist.php` (dotted) |
| Individuální lekce (veřejná) | `individualni_lekce` | Trenér | `kalendar_sportovist.php` (dashed), `booking/kalendar.php` |
| Rezervace zákazníka | `verejne_rezervace` | Zákazník | `booking/moje_rezervace.php`, `individualni_lekce_sprava.php` |

**Sidebar rozvrhu** (`ajax_denny_rozvrh.php`): sdílený pro `rezervovat_sportoviste.php` a `individualni_lekce_form.php`. Ghost preview se aktualizuje live při změně `cas_od`/`cas_do` (debounce 350 ms). Na mobilu collapsible panel.

### Upomínky na nezaevidované tréninky

- **`cron_upominky.php`** — CLI nebo web (`?secret=TOKEN` shodný s env `UPOMINKA_SECRET`), zasílá email trenérům za každý plán s `stav='planovany'` a `datum < dnes` (max 14 dní zpětně, jen pokud `upominka_cas IS NULL`)
- `planovane_treninky.upominka_cas TIMESTAMP NULL` — kdy byla upomínka odeslána
- **`planovac.php`** zobrazuje varování s počtem nezaevidovaných; ruční odeslání patří do CLI/web cronu s env tokenem
- Doporučený cron: `0 7 * * * php /cesta/cron_upominky.php`
- Secret token: env `UPOMINKA_SECRET`, nikdy hardcoded v PHP ani v odkazu

### Kopírování týdne v plánovači

- Tlačítko „Kopírovat týden" v hlavičce `planovac.php` — otevře Bootstrap modal
- Modal zobrazí počet tréninků k okopírování (dle aktuálního filtru Moje/Vše/trenér/skupina) + cílový týden
- POST `action=kopirovat_tyden` — duplicates `planovane_treninky` s `datum + 7 dní`, stav `planovany`
- Kopíruje i podskupiny z `planovane_treninky_podskupiny`; nekopíruje `rezervace_id` ani `trenink_id`
- Po dokončení přesměruje na cílový týden s flash toastem (počet zkopírovaných)

### Čekací listina pro individuální lekce

- **`booking/waiting_list.php`** — helper `notifyWaitingList(PDO, lekceId, slotOd)`: prvnímu v pořadí na `cekaci_listina` přiřadí slot a zašle email; zelená → ihned potvrzena, žlutá → předána trenérovi
- `verejne_rezervace.stav` ENUM rozšířen o `'cekaci_listina'`
- **`booking/kalendar.php`** — plný slot zobrazí tlačítko „Čekací listina (N čeká)"; vlastní pozice „Čekací listina #N"
- **`booking/rezervovat.php`** — `?waitlist=1` přepne do waitlist módu; POST uloží `stav='cekaci_listina'`; plný slot bez `waitlist` auto-přesměruje na waitlist verzi
- **`booking/moje_rezervace.php`** — badge „Na čekací listině"; storno z listiny vždy povoleno (bez 3denního limitu); storno aktivní rezervace volá `notifyWaitingList()`
- **`individualni_lekce_sprava.php`** — zamítnutí rezervace volá `notifyWaitingList()`; sloupec `na_listine` v přehledu lekcí

Triggery `notifyWaitingList()`:
1. Zákazník zruší aktivní rezervaci (`booking/moje_rezervace.php`)
2. Trenér zamítne rezervaci (`individualni_lekce_sprava.php`)
3. Trenér zamítne z emailu (`booking/potvrdit.php`)

### UX balík (2.16.0)

**`hlavicka.php` — globální vylepšení:**
- **Tmavý režim** — Bootstrap `data-bs-theme` toggle, uložen v `localStorage`, init skript před CSS (žádné blikání); tlačítko 🌙/☀️ v navbaru
- **Klávesové zkratky** — `N` nový trénink, `P` plánovač, `K` kalendář sportovišť, `/` hledání, `?` nápověda, `D` dark/light, `Esc` zavřít modal; ignoruje fokus v inputu
- **Sticky záhlaví tabulek** — CSS pro `.table-responsive .table thead th` a `.table-sticky`
- **Empty state CSS** — třída `.empty-state` s `.empty-icon` pro přátelské prázdné stavy
- **Shortcuts modal** — `#shortcutsModal` s přehledem zkratek

**`planovac.php` + `ajax_update_plan.php` — interakce:**
- **Drag & drop** — HTML5 Drag API, karty `draggable="true"`, drop zóny na denní sloupce; AJAX `akce=move` → `ajax_update_plan.php`; vizuální feedback (opacity + rotate + blue highlight)
- **Inline editace názvu** — dvojklik na název → input, Enter/blur → uložit, Esc → zrušit; AJAX `akce=rename`
- Gripper ikona `⠿` na přetahovatelných kartách

**Opakující se tréninky (`planovane_treninky.serie_id`):**
- `serie_id INT NULL` — migrace 2.16.0 krok V
- `planovany_trenink_form.php` — při opakování nastaví `serie_id = ID prvního záznamu` pro všechny instance
- `planovac.php` — badge „Série" na kartách patřících do série; tlačítko „Zrušit celou sérii" (`zrusit_serii=1` POST)
- `ajax_update_plan.php` — nový endpoint: `akce=rename|move`, ověří vlastnictví plánu

| Zkratka | Akce |
|---------|------|
| `N` | Nový trénink (`formular.php`) |
| `P` | Plánovač |
| `K` | Kalendář sportovišť |
| `/` | Globální hledání |
| `D` | Dark/light mode |
| `?` | Nápověda zkratek |

### Nástěnka skupiny v plánovači (2.17.0)

- **`planovac.php`** — collapsible widget nad týdenním gridem; zobrazí posledních 5 oznámení pro vyfiltrované skupiny
- Tlačítko **„+ Nové"** otevře Bootstrap modal s formulářem (titulek, text, datum, skupiny checkboxy)
- **`ajax_nova_oznameni.php`** — POST endpoint, vytvoří `oznameni` + `oznameni_targets` pro vybrané skupiny
- Využívá stávající `oznameni` + `oznameni_targets` tabulky a `oznameni.php` pro plnou správu

### Web Push notifikace (2.17.0)

**Infrastruktura:**
- `push_subscriptions` tabulka (migrace 2.17.0 krok W) — endpoint, p256dh, auth, trener_id
- **`sw.js`** — Service Worker v kořeni; zpracuje `push` event → `showNotification()`; klik → naviguje na URL
- **`push_subscribe.php`** — uloží/smaže subscripci trenéra; `action=subscribe|unsubscribe`
- **`includes/push_helper.php`** — `sendPushNotification(PDO, payload, trenerIds[])`: wrapper kolem `minishlink/web-push`; graceful fallback pokud knihovna chybí
- Bell ikona 🔔 v navbaru (skrytá dokud SW nezkontroluje stav); toggle activate/deactivate

**Závislost:** `composer require minishlink/web-push` (~ 1 MB)

**VAPID klíče** — vygenerovat jednou, uložit do `nastaveni`:
```
push_vapid_public   ← veřejný klíč (Base64url)
push_vapid_private  ← soukromý klíč (Base64url)  
push_vapid_subject  ← mailto:evidence@kovopraha.cz
```
Generátor: `vendor/bin/web-push generate-vapid-keys` nebo https://vapidkeys.com/

**Integrační body:**
- `booking/rezervovat.php` — push trenérovi při nové rezervaci lekce
- Další: přidat `sendPushNotification()` do `cron_upominky.php`, `waiting_list.php` apod.

**Poznámka:** Web Push vyžaduje HTTPS (produkce ✓, localhost ✗ — SW se zaregistruje ale push nepřijde bez HTTPS)

## Dodatek 2.20.0

- `sportovec_karta.php` - administrační karta člena s historií `sportovec_history`; veřejná karta sportovce zůstává `sportovec_treninky.php?hash=...`.
- `kis_sync_center.php` - KIS importni runy, rows a matches.
- `sportovci_hromadne.php` - hromadne akce s preview a transakci.
- `admin_dashboard.php` - provozni dashboard pozornosti.
- SQL zmeny jsou v `includes/auto_migrace.php`, `SCHEMA_VERSION = '2.20.0'`.
- KIS parovani je v `includes/kis_match_lib.php`; volny text historickych jmen je `ambiguous`, ne automaticka shoda.

*Verze: 2.20.0 — červen 2026*
