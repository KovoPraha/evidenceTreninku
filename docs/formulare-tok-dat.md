# Formuláře — tok dat a uložení

Přehled všech formulářů v aplikaci: co každý dělá, jaká data přijímá a jak je ukládá.

---

## Tréninky

### `formular.php` → `ulozit_trenink.php`

**Účel:** Vytvoření nového tréninku trenérem.

**Měření M3.5c:** Klient odesílá JSON `mereni_json` včetně výslovné
`distance_unit`. Sdílený `sportsMeasurementRowsFromPost()` před zahájením
databázové transakce ověří typ, jednotku, striktní čas a RPE a připraví původní
i normalizované hodnoty `sports-measurement-v1`.

**POST pole:**
| Pole | Typ | Popis |
|------|-----|-------|
| `datum` | date | Datum tréninku (required) |
| `napln` | text | Náplň tréninku |
| `poznamka` | text | Poznámka |
| `delka` | float | Délka v hodinách |
| `kategorie` | enum | silnice/mtb/draha/cyklokros/posilovna/atletika/cviceni/plavani |
| `skupina_id` | int | Skupina |
| `podskupiny[]` | int[] | Podskupiny |
| `trenere[]` | int[] | Přidružení trenéři |
| `sportovci[]` | int[] | Účastnící sportovci |
| `mereni_json` | JSON | Pole měření (viz níže) |
| `obrazky[]` | file[] | Obrázky tréninku (JPEG/PNG/WEBP/GIF) |
| `csrf_token` | string | CSRF ochrana |

**Formát `mereni_json`:**
```json
[
  {"typ": "kolo", "sportovec_id": 5, "vzdalenost": 80, "cas": "2:30:00", "prevod": "53x19", "poznamka": ""},
  {"typ": "posilovna", "sportovec_id": 5, "cvik_id": 3, "vaha": 60, "opakovani": 10, "rpe": 7, "poznamka": ""}
]
```

**DB transakce:**
1. `INSERT INTO treninky` → `$treninkId`
2. `INSERT INTO trenink_trener` (aktuální trenér + výběr z formuláře)
3. `INSERT INTO trenink_skupina` (pokud skupina zvolena)
4. `INSERT INTO trenink_podskupina` (M:N)
5. `INSERT INTO trenink_sportovec` (M:N)
6. `sportsMeasurementRowsFromPost()` → sdílený v1 INSERT do `mereni_zaznamy` + `INSERT INTO trenink_mereni`
7. Upload obrázků → `finfo_file()` MIME check → cesty se sbírají do pole `$imagePaths`, pak `json_encode()` → uloženo do sloupce `treninky.obrazky` (JSON pole relativních cest, **žádná separátní tabulka**)
8. `audit_log()` — záznam akce

**Redirect:** `edit_trenink.php?id=$treninkId` s flash `flash_success`.

**Validace okna:** Nastavení `zadavani_dni_zpet` — nelze vkládat tréninky starší než N dní (volitelné, načteno z `nastaveni`).

---

### `edit_trenink.php` → `update_trenink.php`

**Účel:** Editace existujícího tréninku.

**Přístup:** Trenér musí být přiřazen k tréninku (`trenink_trener`) nebo mít roli `hlavni`. Kontrola v `edit_trenink.php` (GET) i `update_trenink.php` (POST).

**POST pole:** Stejná struktura jako `ulozit_trenink.php` + `trenink_id`.

Editace používá stejný společný parser jako vytvoření. Starší vzdálenost bez
uložené jednotky musí obsluha před uložením výslovně označit jako `m` nebo `km`.

**DB transakce (update_trenink.php):**
1. `UPDATE treninky SET ...`
2. `DELETE FROM trenink_trener WHERE trenink_id = ?` → `INSERT` nových trenérů
3. `DELETE FROM trenink_skupina WHERE trenink_id = ?` → `INSERT` nové skupiny
4. `DELETE FROM trenink_podskupina WHERE trenink_id = ?` → `INSERT` nových podskupin
5. `DELETE FROM trenink_sportovec WHERE trenink_id = ?` → `INSERT` nových sportovců
6. Měření: `DELETE FROM mereni_zaznamy` kde `id IN (SELECT mereni_id FROM trenink_mereni WHERE trenink_id = ?)` → `DELETE FROM trenink_mereni` → `INSERT` nových měření
7. Nové obrázky: upload → přidání cest do JSON pole → `UPDATE treninky SET obrazky = ?`
8. Smazané obrázky: odebrání z JSON pole + přejmenování souboru na `smazano_*` → `UPDATE treninky SET obrazky = ?`

**Redirect:** `edit_trenink.php?id=$treninkId` s flash `flash_success`.

---

## Sportovci

### `sprava_sportovcu.php` (inline CRUD)

**Účel:** Správa sportovců — přidání, editace, archivace.

**POST akce:**
| `action` | Popis | DB operace |
|----------|-------|-----------|
| `add` | Nový sportovec | `INSERT INTO sportovci` (jmeno, prijmeni, narozeni, email, rc, telefon, uci, adresa_*) + `UPDATE sportovci SET hash = SHA2(CONCAT(id,'-',jmeno),256) WHERE id = ?` |
| `edit` | Úprava sportovce | `UPDATE sportovci SET ...` |
| `archive` | Archivace | přesun do skupiny Archiv (poradi=9999) |

**CSRF:** povinný na všech POST akcích.

---

### `sync_evidence.php` — 4-krokový wizard

**Účel:** Hromadná synchronizace sportovců z KIS exportů: uživatelé, platby, soupisky.

**Krok 1 — Upload:**
- POST files: `users_xlsx`, `payments_xlsx`, `rosters_xlsx`
- PhpSpreadsheet parsuje tři KIS exporty přes `includes/kis_sync_lib.php`
- Uživatelé dodají kontakty a datum narození, platby se agregují na platební stav, soupisky dodají KIS aktivitu a zařazení
- Uloží do `$_SESSION['sync_data']`

**Krok 2 — Mapování soupisek:**
- POST: `mapping[soupiska_nazev]` → `skupina_id` + `podskupina_id`
- Persistentní: `INSERT INTO soupiska_mapping ... ON DUPLICATE KEY UPDATE`
- AJAX podskupiny: `ajax_podskupiny.php?skupina_id=X`

**Krok 3 — Preview:**
- Zobrazí stats: noví / aktualizovaní / mimo import / beze změn
- Barevné tabulky ukazují nové a aktualizované osoby, změny KIS stavu, dluhů a skupin
- Žádné DB zápisy

**Krok 4 — Provedení:**
- DB transakce: INSERT/UPDATE `sportovci`, aktualizace `kis_*` polí, přepsání mapovaných vazeb na skupiny/podskupiny
- Automatická archivace osob mimo import je vypnutá; chybějící osoba se pouze počítá v preview
- DB transakce:
  1. Pro každého sportovce v XLSX: `INSERT ... ON DUPLICATE KEY UPDATE sportovci`
  2. `UPDATE sportovec_skupina / sportovec_podskupina` dle mappingu
  3. Sportovci mimo XLSX → přesun do skupiny Archiv (poradi=9999) + vyloučení z našeptávače
- Flash toast s počtem provedených změn

---

## Závody

### `formular_zavod.php` → `ulozit_zavod.php`

**Účel:** Vytvoření nového závodu.

**POST pole:**
| Pole | Typ | Popis |
|------|-----|-------|
| `datum` | date | Datum závodu |
| `kategorie` | enum | silnice/draha/mtb |
| `popis` | text | Název/popis závodu |
| `poznamka` | text | Interní poznámka |
| `url_vysledky` | string | URL výsledků (volitelné) |
| `trener_id` | int | Odpovědný trenér |
| `ucastnici[]` | int[] | ID sportovců (chip autocomplete) |
| `mereni_json` | JSON | Měření závodníků |
| `fotky[]` | file[] | Fotografie závodu |
| `soubory[]` | file[] | Výsledkové soubory (PDF/XLS/XLSX/CSV) |

**DB transakce (ulozit_zavod.php):**
1. `INSERT INTO zavody`
2. `INSERT INTO zavod_sportovec` (M:N)
  3. `sportsMeasurementRowsFromPost()` → sdílený v1 INSERT do `mereni_zaznamy` + `INSERT INTO zavod_mereni`
4. Upload fotek → `INSERT INTO zavod_fotka`
5. Upload souborů (PDF/XLS/XLSX/CSV) → `INSERT INTO zavod_import`

**Redirect:** `zavod_detail.php?id=$zavodId` s flash.

---

### `edit_zavod_form.php` → `update_zavod.php`

**Účel:** Editace závodu — stejná struktura jako tvorba, ale s prefillováním dat.

**Prefill měření:** JS funkce `prefillMereni()` načte JSON z DB a předvyplní dynamické řádky.

Nové i editované řádky vyžadují výslovnou jednotku vzdálenosti a používají stejný
striktní parser jako formulář tréninku. Neplatný vstup selže před DB transakcí.

**DB transakce (update_zavod.php):**
- DELETE + INSERT vzor pro všechny M:N vazby (zavod_sportovec, zavod_mereni, zavod_fotka)
- Soft-delete smazaných fotek: `UPDATE zavod_fotka SET smazano = 1`

---

## Rezervační systém sportovišť

### `rezervovat_sportoviste.php`

**Účel:** Nová interní rezervace sportoviště pro týmový trénink.

**POST pole:** `sportoviste_id`, `datum`, `cas_od`, `cas_do`, `kapacita_dilu` (1–5), `poznamka`, `trenink_id` (volitelné), `csrf_token`

**Validace dostupnosti:** AJAX `ajax_dostupnost_sportovist.php` live; server-side check před uložením: `SELECT SUM(kapacita_dilu) FROM rezervace_sportovist WHERE ... AND cas_od < cas_do AND cas_do > cas_od`.

**DB:** `INSERT INTO rezervace_sportovist`

**Redirect:** `kalendar_sportovist.php?datum=X`

---

### `individualni_lekce_form.php`

**Účel:** Trenér vypíše individuální lekci pro zákazníky.

**POST pole:** `sportoviste_id`, `datum`, `cas_od`, `cas_do`, `slot_delka_min`, `typ` (zelena/zluta), `nazev`, `popis`, `cena_kc`, `max_osob`, `vyjimka_3_dny` (checkbox), `csrf_token`

**DB transakce:**
1. `INSERT INTO individualni_lekce`

> **Poznámka:** Individuální lekce záměrně NEBLOKUJÍ sportoviště přes `rezervace_sportovist`. V kalendáři sportoviště se zobrazují informačně (přerušovaná linie), ale neodečítají kapacitu. Obsazenost `ajax_dostupnost_sportovist.php` dotazuje pouze interní rezervace (`lekce_id IS NULL`).

**Redirect:** `individualni_lekce_sprava.php` s flash.

---

### `individualni_lekce_sprava.php`

**Účel:** Trenér spravuje rezervace svých lekcí.

**POST akce (inline):**
| `akce` | Popis | DB + vedlejší efekt |
|--------|-------|---------------------|
| `potvrdit` | Potvrdí rezervaci zákazníka | `UPDATE verejne_rezervace SET stav='potvrzena'` + email zákazníkovi |
| `zamit` | Zamítne rezervaci | `UPDATE ... stav='zamitnuta'` + email zákazníkovi + `notifyWaitingList()` |
| `zaplatit` | Označí jako zaplaceno | `UPDATE ... zaplaceno=1` |
| `zrusit_lekci` | Zruší celou lekci | `UPDATE individualni_lekce SET stav='zrusena'` + emaily všem zákazníkům |

---

## Plánovač

### `planovany_trenink_form.php`

**Účel:** Vytvoření nebo editace plánovaného tréninku (bez okamžitého zápisu docházky).

**POST pole:** `nazev`, `kategorie`, `skupina_id`, `podskupiny[]`, `datum`, `cas_od`, `cas_do`, `sportoviste_id`, `popis`, `misto`, `opakovat` (none/pocet/do-data), `pocet_opakovani`, `opakovani_do`, `csrf_token`

**Opakování:**
- `opakovat = 'pocet'` → vytvoří N kopií s `datum + 7*i` dní
- `opakovat = 'do-data'` → vytvoří kopie po týdnech do `opakovani_do`
- Všechny instance dostávají `serie_id = ID prvního záznamu`

**DB transakce:**
1. `INSERT INTO planovane_treninky` (pro každou instanci)
2. `INSERT INTO planovane_treninky_podskupiny` (M:N, pro každou instanci)
3. Pokud `sportoviste_id` a `cas_od/cas_do`: `INSERT INTO rezervace_sportovist` s `plan_id`

**Redirect:** `planovac.php?tyden=YYYY-WW` s flash.

---

## Booking (zákazníci)

### `booking/registrace.php`

**Účel:** Registrace nového zákazníka.

**POST pole:** `jmeno`, `prijmeni`, `email`, `telefon`, `heslo` (+ potvrzení), `csrf_token`

**Validace:** Unikátnost emailu, délka hesla ≥ 8 znaků, shoda hesel.

**DB:**
1. `INSERT INTO verejni_uzivatele` (heslo jako `password_hash()` bcrypt, `verifikacni_token = bin2hex(random_bytes(24))`)
2. `mail()` — verifikační email s tokenem, URL `overeni.php?token=X`

**Redirect:** Stránka s potvrzením "zkontrolujte email".

---

### `booking/rezervovat.php`

**Účel:** POST handler rezervace individuální lekce (nebo přidání na čekací listinu).

**POST pole:** `lekce_id`, `slot_cas_od`, `slot_cas_do`, `csrf_token`; URL `?waitlist=1` přepne do čekací listiny.

**Logika:**
1. Zkontroluje přihlášení zákazníka (`verejny_uzivatel_id`)
2. Zkontroluje `vyjimka_3_dny`: pokud lekce není s výjimkou, musí být datum ≥ dnes + 3 dny
3. Zkontroluje obsazenost slotu (`slot_cas_od` + `slot_cas_do`)
4. Pokud plný slot bez `?waitlist`: redirect na `?waitlist=1`
5. `INSERT INTO verejne_rezervace`:
   - zelená lekce → `stav = 'potvrzena'` + email trenérovi (informační)
   - žlutá lekce → `stav = 'ceka'` + email trenérovi s potvrzovacím odkazem
   - `?waitlist=1` → `stav = 'cekaci_listina'`

**Redirect:** `moje_rezervace.php` s flash.

---

### `booking/moje_rezervace.php`

**Účel:** Zákazník vidí své rezervace a může stornovat.

**POST akce:** `zrusit` s `rezervace_id`

**Validace storna:** 3 denní limit (datum lekce ≥ dnes + 3 dny), pokud `vyjimka_3_dny = 0`. Čekací listina lze zrušit vždy.

**DB:** `UPDATE verejne_rezervace SET stav = 'zrusena'`

**Vedlejší efekt:** Pokud byla rezervace potvrzená → `notifyWaitingList(pdo, lekce_id, slot_cas_od)` — první čekající na listině dostane slot.

---

### `booking/potvrdit.php`

**Účel:** GET endpoint — trenér klikne na odkaz v emailu a potvrdí/zamítne rezervaci.

**GET parametry:** `?token=<potvrzovaci_token>&akce=potvrdit|zamit`

**DB:** `UPDATE verejne_rezervace SET stav = 'potvrzena'|'zamitnuta'`

**Vedlejší efekt při zamítnutí:** `notifyWaitingList()` — první čekající dostane nabídku.

---

## Správa dat

### `sprava_sportovist.php`

**Účel:** CRUD admin správa sportovišť.

**POST akce:** `add`, `edit`, `toggle` (aktivní/neaktivní), `reorder`

**DB:** `INSERT/UPDATE sportovist` (kod, nazev, je_verejne, max_kapacita, poradi, aktivni)

---

### `nastaveni_opravneni.php`

**Účel:** Admin nastavení minimální role pro jednotlivé funkce.

**POST:** `klic=hodnota[]` — mapa klíč → min_role pro každou funkci

**DB:** `UPDATE opravneni SET min_role = ? WHERE klic = ?` pro každý klíč

**Vedlejší efekt:** Při příštím přihlášení se `$_SESSION['opravneni']` přepočítá z aktuální DB.

---

### `nastaveni_zadavani.php`

**Účel:** Nastavení rolling okna pro zadávání tréninků (počet dní zpět).

**POST:** `dni_zpet` (int, výchozí 30)

**DB:** `INSERT INTO nastaveni (klic, hodnota) VALUES ('zadavani_dni_zpet', ?) ON DUPLICATE KEY UPDATE hodnota = ?`

---

## Zátěžové testy

### `zatezovy_test_form.php` → `ulozit_zatezovy_test.php`

**Účel:** Záznam zátěžového testu sportovce s přílohami.

**POST pole:** `sportovec_id`, `datum`, `vek`, `vaha_kg`, `vyska_cm`, `popis_interni`, `popis_sportovec`, `public_img[]`, `internal_img[]`, `other_files[]`, `csrf_token`

**Upload typy:**
| Input | Přístup | DB typ |
|-------|---------|--------|
| `public_img[]` | Veřejný (sportovec vidí) | `public_img` |
| `internal_img[]` | Interní (jen trenéři) | `internal_img` |
| `other_files[]` | Ostatní (FIT, PDF, XLS) | `other` |

**MIME validace:** Blocklist (PHP, shell skripty) — povoleny veškeré ostatní typy.

**DB transakce:**
1. `INSERT INTO zatezove_testy` → `$testId`
2. Pro každý soubor: `INSERT INTO zatezove_testy_soubory (test_id, typ, nazev, cesta)`

**Redirect:** `sportovec_detail.php?id=$sportovecId`

---

## Story generátor

### `nastaveni_story.php`

**Účel:** Nastavení vzhledu story obrázků (logo, barvy, font).

**POST:** `logo` (file), `barva_pozadi`, `barva_text`, `font_size`, plus JSON konfigurace

**Upload:** `finfo_file()` → povoleno pouze image/png, image/jpeg, image/gif, image/webp. Uloženo do `loga_story/`.

**DB:** `INSERT INTO nastaveni (klic, hodnota) ON DUPLICATE KEY UPDATE` pro každý konfigurační klíč.

---

## Správa segmentů

### `sprava_segmentu.php`

**Účel:** CRUD správa segmentů pro kolo (kroužek, silnice, MTB).

**POST akce:** `add`, `edit`, `delete`, `reorder`

**POST pole:** `nazev`, `popis`, `kategorie` (krouzek/silnice/mtb), `odkaz_1`, `odkaz_2`, `fotografie` (file), `aktivni`, `poradi`

**Upload foto:** MIME allowlist (JPEG/PNG/WEBP/GIF), uloženo do `uploads/segmenty/`.

**Soft delete:** `UPDATE segmenty SET aktivni = 0`

---

## Cviky (posilovna)

### `cviky.php`

**Účel:** CRUD správa cviků pro posilovnová měření.

**POST akce:** `add`, `edit`, `toggle`, `reorder`

**DB:** `INSERT/UPDATE cviky (nazev, popis, poradi, aktivni)`

---

## Dodatek 2.20.0 - clenska evidence a KIS

### `sportovec_karta.php` - administrační karta člena

Administrační karta člena pro přihlášené trenéry/správce. POST akce `set_status`, `clear_manual_status` a `add_note` používají CSRF a zapisují `sportovec_history`. Veřejná karta sportovce bez přihlášení zůstává `sportovec_treninky.php?hash=...`.

### `kis_sync_center.php`

Prehled importnich runu z tabulek `kis_import_runs`, `kis_import_rows` a `kis_import_matches`. Slouzi pro kontrolu preview, konfliktu a provoznich problemu pred potvrzenim importu.

### `sportovci_hromadne.php`

Preview a potvrzeni hromadnych akci ze `sprava_sportovcu.php`. Provedeni bezi v transakci a zapisuje `sportovec_history`.

---

*Dokument generován při revizi funkčnosti aplikace — červen 2026.*
