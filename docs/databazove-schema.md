# Databázové schéma — Evidence Tréninků

Databáze: MariaDB 10.3+ / MySQL, kódování `utf8mb4_general_ci`.

## Obsah

1. [Jádro (uživatelé, sportovci, skupiny)](#1-jádro)
2. [Tréninky a měření](#2-tréninky-a-měření)
3. [Závody](#3-závody)
4. [Kreditní systém](#4-kreditní-systém)
5. [Google Sheets](#5-google-sheets)
6. [Story systém](#6-story-systém)
7. [Oznámení](#7-oznámení)
8. [Účetní modul](#8-účetní-modul)
9. [Zátěžové testy](#9-zátěžové-testy)
10. [Miniresult systém](#10-miniresult-systém)
11. [Ostatní](#11-ostatní)
12. [Rezervační systém sportovišť](#12-rezervační-systém-sportovišť) *(přidáno v 2.8.0)*
13. [Plánovač tréninků](#13-plánovač-tréninků) *(přidáno v 2.10.0, rozšiřováno v 2.11.0–2.16.0)*
14. [Web Push notifikace](#14-web-push-notifikace-přidáno-v-2170) *(přidáno v 2.17.0)*
15. [Plánované tabulky](#15-plánované-tabulky-fáze-12-neimplementováno) *(Fáze 1–2)*

---

## 1. Jádro

### `treneri`
Uživatelské účty trenérů.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `velo_user_id` | int NULL | Legacy nullable reference ze starého SSO experimentu; cílová architektura ji nepoužívá |
| `jmeno` | varchar(100) | Přihlašovací jméno (nebo email) |
| `email` | varchar(255) | |
| `heslo` | varchar(255) | Heslo (moderní password hash) |
| `role` | enum('trener','hlavni','admin') | Role: trenér / správce / administrátor |
| `aktivni` | tinyint(1) DEFAULT 1 | Aktivní účet |

### `sportovci`
Evidence sportovců.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `jmeno` | varchar(100) | Křestní jméno |
| `prijmeni` | text | Příjmení |
| `hash` | varchar(64) | Unikátní hash pro veřejný profil |
| `narozeni` | date | Datum narození |
| `email` | text | |
| `rc` | varchar(20) | Rodné číslo |
| `telefon` | varchar(50) | Telefon |
| `adresa_ulice` | varchar(200) | Ulice |
| `adresa_cp` | varchar(20) | Číslo popisné |
| `adresa_co` | varchar(20) | Číslo orientační |
| `adresa_obec` | varchar(100) | Obec |
| `adresa_psc` | varchar(10) | PSČ |
| `uci` | int | Interní UCI kód |
| `uciid` | varchar(80) NULL | Externí UCI ID |
| `oddil` | varchar(160) NULL | Oddíl / klub |
| `category` | varchar(100) NULL | Kategorie sportovce |
| `odmena_za_trenink` | decimal(10,2) | Odměna za trénink (Kč) |
| `obdobi_start` | date | Začátek kreditního období |
| `kis_aktivni` | tinyint(1) | Aktivní podle posledního KIS soupiska exportu |
| `kis_platebne_aktivni` | tinyint(1) | Má alespoň jednu uhrazenou KIS platbu |
| `kis_neuhrazeno` | decimal(10,2) | Součet otevřených KIS plateb |
| `kis_posledni_uhrada` | date NULL | Poslední datum úhrady v KIS exportu plateb |
| `kis_posledni_sync` | timestamp NULL | Poslední aktualizace z KIS synchronizace |
| `kis_soupisky` | text NULL | Textový seznam soupisek z posledního KIS importu |

### `skupiny`
Skupiny (závodní tým, kroužek, ...).

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `nazev` | varchar(100) | Název skupiny |
| `hash` | varchar(200) | Unikátní hash |
| `poradi` | int | Pořadí řazení |

### `podskupiny`
Podskupiny v rámci skupin.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `nazev` | varchar(100) | Název podskupiny |
| `skupina_id` | int FK → skupiny | Nadřazená skupina |
| `hash` | varchar(64) | Auto-generovaný (trigger) |
| `poradi` | int | Pořadí řazení |

**Trigger:** `trg_podskupiny_hash` — automaticky generuje SHA256 hash při INSERT.

### `sportovec_skupina`
Přiřazení sportovce ke skupině (M:N).

| Sloupec | Typ |
|---------|-----|
| `sportovec_id` | int FK → sportovci |
| `skupina_id` | int FK → skupiny |

### `sportovec_podskupina`
Přiřazení sportovce k podskupině (M:N).

| Sloupec | Typ |
|---------|-----|
| `sportovec_id` | int FK → sportovci |
| `podskupina_id` | int FK → podskupiny |

---

## 2. Tréninky a měření

### `treninky`
Hlavní tabulka tréninků.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `datum` | date | Datum tréninku |
| `napln` | text | Textový popis tréninku |
| `poznamka` | text | Poznámka (viditelná jen trenérovi) |
| `delka` | decimal(5,2) | Délka v hodinách |
| `kategorie` | enum | silnice/mtb/draha/cyklokros/posilovna/atletika/cviceni/plavani |
| `obrazky` | text | Čárkami oddělené názvy souborů |
| `mereni` | text | Textový záznam měření (legacy) |
| `mereni_json` | longtext | JSON měření (nový formát) |
| `updated_at` | timestamp | Auto-update |

### `trenink_sportovec`
Účast sportovce na tréninku (M:N).

| Sloupec | Typ |
|---------|-----|
| `trenink_id` | int FK → treninky |
| `sportovec_id` | int FK → sportovci |

### `trenink_skupina`
Přiřazení tréninku ke skupině (M:N).

| Sloupec | Typ |
|---------|-----|
| `trenink_id` | int FK → treninky |
| `skupina_id` | int FK → skupiny |

### `trenink_podskupina`
Přiřazení tréninku k podskupině (M:N).

| Sloupec | Typ |
|---------|-----|
| `trenink_id` | int FK → treninky |
| `podskupina_id` | int FK → podskupiny |

### `trenink_trener`
Přiřazení trenéra k tréninku (M:N).

| Sloupec | Typ |
|---------|-----|
| `trenink_id` | int FK → treninky |
| `trener_id` | int FK → treneri |

### `trenink_tag`
Přiřazení tagu k tréninku (M:N).

| Sloupec | Typ |
|---------|-----|
| `trenink_id` | int FK → treninky |
| `tag_id` | int FK → tagy |

### `tagy`
Štítky/tagy tréninků.

| Sloupec | Typ |
|---------|-----|
| `id` | int PK |
| `nazev` | varchar(255) |

### `mereni`
Starší formát měření (vzdálenost + čas).

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `trenink_id` | int FK → treninky | |
| `sportovec_id` | int FK → sportovci | |
| `vzdalenost` | varchar(100) | Vzdálenost (text) |
| `cas` | varchar(50) | Čas (text) |
| `poznamka` | text | Poznámka (převod aj.) |

### `mereni_zaznamy`
Nový formát měření s typem.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `typ` | enum('kolo','beh','posilovna','kolo_krouzek','kolo_silnice','kolo_mtb') | Typ měření — `kolo_mtb` přidáno v 2.7.0 |
| `sportovec_id` | int FK → sportovci | |
| `vzdalenost` | decimal(10,2) | km (kolo/běh) |
| `cas` | varchar(50) | Čas (kolo/běh/kolo_krouzek/kolo_silnice) |
| `prevod` | varchar(100) | Převod (pouze kolo) |
| `cvik_id` | int FK → cviky | Cvik (pouze posilovna) |
| `segment_id` | int FK → segmenty | Segment (pouze kolo_krouzek/kolo_silnice) |
| `vaha` | decimal(10,2) | kg (pouze posilovna) |
| `opakovani` | int | Počet opakování (pouze posilovna) |
| `rpe` | varchar(50) | RPE hodnocení (pouze posilovna) |
| `poznamka` | text | |
| `created_at` | timestamp | |

### `trenink_mereni`
Vazba tréninku na záznamy měření.

| Sloupec | Typ |
|---------|-----|
| `trenink_id` | int FK → treninky |
| `mereni_id` | int FK → mereni_zaznamy |
| `poradi` | int | Pořadí řádku měření |

### `cviky`
Cviky pro posilovnu.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `nazev` | varchar(150) | Název cviku |
| `popis` | text | |
| `poradi` | int | Řazení |
| `aktivni` | tinyint(1) | 1 = aktivní |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `segmenty`
Segmenty tras na kole (pro měření kolo_krouzek, kolo_silnice a kolo_mtb).

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `nazev` | varchar(200) | Název segmentu |
| `popis` | text | Popis trasy |
| `fotografie` | varchar(500) | Relativní cesta k foto (uploads/segmenty/) |
| `odkaz_1` | varchar(500) | URL odkaz 1 (mapy.cz, Strava) |
| `odkaz_2` | varchar(500) | URL odkaz 2 |
| `kategorie` | enum('krouzek','silnice','mtb') | Kategorie segmentu |
| `poradi` | int | Řazení |
| `aktivni` | tinyint(1) | 1 = aktivní |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

Správa: `sprava_segmentu.php` (CRUD s foto uploadem do `uploads/segmenty/`).

### `soupiska_mapping`
Persistentní mapování soupisek z importního XLSX na skupiny/podskupiny.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `soupiska_text` | varchar(255) UNIQUE | Přesný text soupisky z Excelu |
| `skupina_id` | int FK → skupiny | Přiřazená skupina |
| `podskupina_id` | int FK → podskupiny | Přiřazená podskupina |

Používáno v `sync_evidence.php` pro opakovaný import sportovců.

### `opravneni`
Konfigurovatelná oprávnění — minimální role potřebná pro přístup ke každé funkci.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `klic` | varchar(100) UNIQUE | Identifikátor funkce (např. `sprava_sportovcu`) |
| `nazev` | varchar(200) | Zobrazovaný název |
| `popis` | varchar(500) | Popis funkce |
| `min_role` | enum('trener','hlavni','admin') | Minimální požadovaná role |
| `skupina` | varchar(100) | Skupina pro UI seskupení |
| `poradi` | int | Řazení |

Spravuje `nastaveni_opravneni.php`. Načteno do `$_SESSION['opravneni']` při loginu. Kontrola přes `canAccess('klic')`.

### `dalsi_cinnosti`
Netréninové aktivity.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `trener_id` | int FK → treneri | |
| `nazev` | varchar(255) | Název aktivity |
| `delka` | decimal(5,2) | Délka v hodinách |
| `poznamka` | text | |
| `datum` | date | |

---

## 3. Závody

### `zavody`
Evidence závodů. Od verze 2.7.0 rozšířeno o kategorii a URL výsledků.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `datum` | date | Datum závodu |
| `kategorie` | enum('silnice','draha','mtb') | Kategorie závodu — **přidáno v 2.7.0** |
| `popis` | text | Popis závodu (veřejný) |
| `poznamka` | text | Interní poznámka (viditelná jen trenérům) |
| `url_vysledky` | varchar(500) NULL | URL odkaz na výsledky — **přidáno v 2.7.0** |
| `trener_id` | int FK → treneri | Vytvořil |

**Vizuální mapa kategorií:**

| Hodnota | Label | Bootstrap barva | Ikona |
|---------|-------|-----------------|-------|
| `silnice` | Silnice | `success` (zelená) | `bi-bicycle` |
| `draha` | Dráha | `primary` (modrá) | `bi-stopwatch` |
| `mtb` | MTB | `warning` (žlutá) | `bi-tree` |

### `zavod_skupina`
| Sloupec | Typ |
|---------|-----|
| `zavod_id` | int FK → zavody |
| `skupina_id` | int FK → skupiny |

### `zavod_podskupina`
| Sloupec | Typ |
|---------|-----|
| `zavod_id` | int FK → zavody |
| `podskupina_id` | int FK → podskupiny |

### `zavod_trener`
| Sloupec | Typ |
|---------|-----|
| `zavod_id` | int FK → zavody |
| `trener_id` | int FK → treneri |

### `zavod_sportovec`
Účast sportovce na závodě s výsledky. Od verze 2.7.0 podporuje i externí závodníky.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `zavod_id` | int FK → zavody | |
| `sportovec_id` | int FK → sportovci **NULL** | NULL pro externího závodníka — **změněno v 2.7.0** |
| `poradi` | int NULL | Pořadí v závodě |
| `cas` | varchar(50) NULL | Čas výsledku |
| `body` | decimal NULL | Body za závod |
| `jmeno_ext` | varchar(200) NULL | Jméno externího závodníka — **přidáno v 2.7.0** |
| `klub` | varchar(200) NULL | Klub závodníka — **přidáno v 2.7.0** |
| `kategorie_start` | varchar(100) NULL | Startovní kategorie (Elite, U23, Jun…) — **přidáno v 2.7.0** |

Interní závodníci mají vyplněno `sportovec_id` (odkaz na profil). Externí závodníci mají `sportovec_id = NULL` a jméno v `jmeno_ext`.

### `zavod_mereni`
Junction tabulka pro měření u závodů. Identická struktura jako `trenink_mereni`. **Přidáno v 2.7.0.**

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `zavod_id` | int FK → zavody ON DELETE CASCADE | |
| `mereni_id` | int FK → mereni_zaznamy ON DELETE CASCADE | |
| `poradi` | int DEFAULT 0 | Pořadí záznamu měření |

PK: (`zavod_id`, `mereni_id`)

### `zavod_fotka`
| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `zavod_id` | int FK → zavody | |
| `soubor` | varchar(255) | Název souboru v `nahrane_zavody/` |

### `zavod_import`
Importované výsledky závodů (soubory XLS/XLSX/PDF).

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `zavod_id` | int FK → zavody | |
| `soubor` | varchar(255) | Cesta k souboru |
| `typ` | enum('pdf','xls','xlsx') | |
| `import_dt` | datetime | |

---

## 4. Kreditní systém

### `sportovec_obdobi`
Kreditní (platební) období sportovce.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `sportovec_id` | int FK → sportovci | |
| `datum_od` | date | Začátek období |
| `datum_do` | date / NULL | Konec (NULL = otevřené) |
| `sazba_kc` | decimal(10,2) | Sazba za trénink v Kč |
| `pocet_treninku` | int | Snapshot počtu |
| `castka_celkem` | decimal(10,2) | Snapshot celkové částky |
| `vyplaceno` | tinyint(1) | 0 = neuhrazeno, 1 = uhrazeno |

### `sportovec_poznamka`
Poznámky ke sportovci (viditelné ve veřejném profilu).

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `sportovec_id` | int FK → sportovci | |
| `text` | text | |
| `created_at` | timestamp | |

### `sportovec_interni_poznamka`
Interní poznámky ke sportovci (pouze pro trenéry).

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `sportovec_id` | int FK → sportovci | |
| `text` | text | |
| `created_at` | timestamp | |

---

## 5. Google Sheets

### `gs_kategorie`
Kategorie odkazů na Google Sheets.

| Sloupec | Typ |
|---------|-----|
| `id` | int PK |
| `nazev` | varchar(120) |
| `created_at` | timestamp |

### `gs_linky`
Odkazy na Google Sheets.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `kategorie_id` | int FK → gs_kategorie | |
| `url` | text | URL odkazu |
| `nazev` | varchar(255) | Název |
| `popis` | text | |
| `datum` | date | |
| `viditelnost` | enum('treneri','verejny','cilene') | |
| `vlozil_trener_id` | int FK → treneri | |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `gs_link_targets`
Cílení odkazů na skupiny/podskupiny/sportovce.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `link_id` | int FK → gs_linky | |
| `target_type` | varchar(30) | 'sportovec' / 'skupina' / 'podskupina' |
| `target_id` | int | ID cíle |
| `created_at` | timestamp | |

---

## 6. Story systém

### `story_nastaveni`
Nastavení vzhledu story obrázků pro skupiny/podskupiny.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `typ` | enum('skupina','podskupina') | |
| `entita_id` | int | ID skupiny/podskupiny |
| `barva` | varchar(10) | Barva pruhu (hex) |
| `barva_textu` | varchar(10) | Barva textu (hex) |
| `hlavicka` | varchar(255) | Text horního pruhu |
| `paticka` | varchar(255) | Text dolního pruhu |
| `logo` | varchar(255) | Soubor loga |

PK: (`typ`, `entita_id`)

### `story_vygenerovane`
Log vygenerovaných story obrázků.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `trenink_id` | int FK → treninky | |
| `soubor` | varchar(255) | Název souboru |
| `created` | datetime | |

---

## 7. Oznámení

### `oznameni`
Oznámení a zprávy.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `nazev` | varchar(255) | Titulek |
| `obsah_html` | mediumtext | HTML obsah |
| `datum` | date | |
| `vlozil_trener_id` | int FK → treneri | |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `oznameni_targets`
Cílení oznámení.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `oznameni_id` | int FK → oznameni | |
| `target_type` | enum('skupina','podskupina','sportovec') | |
| `target_id` | int | ID cíle |

---

## 8. Účetní modul (admin-only)

> Všechny tabulky s prefixem `ucto_` patří do firemní evidence přístupné pouze administrátorům (`role = 'hlavni'`).

### `ucto_vozidla`
Vozidla.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `znacka_model` | varchar(100) | Značka a model |
| `spz` | varchar(20) | SPZ |
| `rok_vyroby` | year | |
| `palivo` | varchar(20) | Typ paliva |
| `stk_datum` | date | Platnost STK |
| `dalnicni_znamka_datum` | date | Platnost dálniční známky |
| `poznamka` | text | |

### `ucto_jizdy`
Jízdy s vozidly.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `vozidlo_id` | int FK → ucto_vozidla | |
| `datum_start` | datetime | Začátek jízdy |
| `datum_konec` | datetime | Konec jízdy |
| `tachometr_start` | int | Stav km na začátku |
| `tachometr_konec` | int | Stav km na konci |
| `poloha_start` | varchar(255) | |
| `poloha_konec` | varchar(255) | |
| `ridic_id` | int FK → treneri | |
| `ridic_text` | varchar(100) | Jméno řidiče (textově) |
| `poznamka` | text | |

### `ucto_servis`
Servisní záznamy.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `vozidlo_id` | int FK → ucto_vozidla | |
| `popis` | text | Popis prací |
| `provedeno_dne` | date | |
| `planovana_kontrola` | date | Další plánovaná kontrola |
| `dokument` | varchar(255) | Cesta k příloze |
| `vytvoril_id` | int FK → treneri | |
| `vytvoreno` | datetime | |

### `ucto_tankovani`
Záznamy o tankování.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `vozidlo_id` | int FK → ucto_vozidla | |
| `datum` | datetime | |
| `mnozstvi_litru` | float | |
| `cena` | decimal(10,2) | |
| `uctenka_path` | varchar(255) | Cesta k foto účtenky |
| `ridic_id` | int FK → treneri | |
| `ridic_text` | varchar(100) | |

### `ucto_uctenky`
Účtenky a doklady.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `castka` | decimal(10,2) | Částka (Kč) |
| `platba` | enum | hotove_tym / kartou_tym / vlastni_karta / vlastni_hotovost |
| `kategorie` | enum | zavodni_oddil / velodrom_areal / cyklo_krouzek / ostatni |
| `vozidlo_id` | int FK → ucto_vozidla / NULL | |
| `udalost_id` | int FK → ucto_udalosti / NULL | |
| `poznamka` | text | |
| `nahrano_kym` | varchar(255) | ID trenéra |
| `nahrano_jmenem` | varchar(255) | |
| `datum` | date | |
| `obrazek_path` | varchar(255) | Cesta k foto |
| `vytvoreno` | datetime | |

### `ucto_udalosti`
Události (závody, soustředění, ...).

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `nazev` | varchar(255) | |
| `popis` | text | |
| `datum_od` | datetime | |
| `datum_do` | datetime | |
| `typ` | enum | zavod / soustredeni / trenink / jine |
| `zalohova_castka` | decimal(10,2) | |
| `zalohu_predal` | varchar(255) | |
| `stav` | enum('otevrena','uzavrena') | |
| `vytvoril_id` | int FK → treneri | |
| `vytvoreno` | datetime | |

### `ucto_audit_log`
Audit log.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `uzivatel_id` | int FK → treneri | |
| `akce` | varchar(100) | Typ akce |
| `tabulka` | varchar(50) | Název tabulky |
| `zaznam_id` | int | ID záznamu |
| `detail` | text | JSON detail |
| `ip_adresa` | varchar(45) | |
| `user_agent` | text | |
| `datum` | datetime | |

### `ucto_dokumenty`
Dokumenty vozidel.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `vozidlo_id` | int FK → ucto_vozidla | |
| `typ` | varchar(50) | |
| `platnost_do` | date | |
| `nazev_souboru` | varchar(255) | |
| `cesta_k_souboru` | varchar(255) | |
| `poznamka` | text | |
| `nahrano_kym` | int FK → treneri | |
| `nahrano_datum` | datetime | |

### `ucto_gs_kategorie`, `ucto_gs_linky`, `ucto_gs_link_targets`
Google Sheets pro účetní modul — struktura analogická k `gs_*` tabulkám.

---

## 9. Zátěžové testy

### `zatezove_testy`
Záznamy zátěžových testů.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `sportovec_id` | int FK → sportovci | |
| `datum` | date | |
| `vek` | int | Věk v době testu |
| `vaha_kg` | decimal(5,2) | |
| `vyska_cm` | decimal(5,2) | |
| `popis_interni` | text | Poznámky pro trenéry |
| `popis_sportovec` | text | Text viditelný sportovci |
| `created_at` | datetime | |
| `updated_at` | datetime | |

### `zatezove_testy_soubory`
Soubory zátěžových testů.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `test_id` | int FK → zatezove_testy | |
| `typ` | enum('public_img','internal_img','other') | Úroveň přístupu |
| `nazev` | varchar(255) | Původní název souboru |
| `cesta` | varchar(255) | Cesta na disku |
| `created_at` | datetime | |

---

## 10. Miniresult systém

Samostatný systém pro registraci a výsledky závodů.

### `miniresult_athletes`
| Sloupec | Typ |
|---------|-----|
| `id` | int PK |
| `first_name`, `last_name` | varchar(100) |
| `first_name_norm`, `last_name_norm` | varchar(100) — normalizované |
| `birth_year` | int |
| `email` | varchar(255) |
| `created_at` | timestamp |

### `miniresult_categories`
| Sloupec | Typ |
|---------|-----|
| `id` | int PK |
| `name` | varchar(100) |

### `miniresult_events`
| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `name` | varchar(255) | |
| `event_date` | date | |
| `registration_open` | tinyint(1) | |
| `payment_enabled` | tinyint(1) | |
| `created_at` | timestamp | |

### `miniresult_event_categories`
| Sloupec | Typ | Popis |
|---------|-----|-------|
| `event_id` | int FK | |
| `category_id` | int FK | |
| `is_open` | tinyint(1) | Registrace otevřena |
| `fee_cents` | int | Startovné v haléřích |

### `miniresult_registrations`
| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `event_id` | int FK | |
| `category_id` | int FK | |
| `athlete_id` | int FK | |
| `start_number` | int | Startovní číslo |
| `paid` | tinyint(1) | |
| `pay_method` | varchar(10) | |
| `paid_at` | datetime | |
| `stripe_session_id` | varchar(255) | Stripe platba |
| `created_at` | timestamp | |

### `miniresult_results`
| Sloupec | Typ |
|---------|-----|
| `id` | int PK |
| `event_id` | int FK |
| `category_id` | int FK |
| `registration_id` | int FK |
| `position` | int |
| `created_at` | timestamp |

### `miniresult_series`
Seriály závodů.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `name` | varchar(150) | |
| `best_of` | int | 0 = všechny, jinak TOP X |
| `created_at` | timestamp | |

### `miniresult_series_events`, `miniresult_series_points`
Vazby seriálů na závody a bodovací tabulky.

### `miniresult_startlist_generation`, `miniresult_stripe_events`, `miniresult_users`
Pomocné tabulky pro startovní listiny, Stripe webhook a uživatele miniresult systému.

---

## 11. Ostatní

### `fotky`
Profilové fotky sportovců.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `jmeno` | varchar(100) | Jméno sportovce |
| `kategorie` | enum | žáci/žákyně/kadeti/kadetky/junioři/juniorky/trenéři |
| `obrazek` | varchar(255) | Název souboru |

### `jidlo_kategorie`, `jidlo_produkty`, `jidlo_custom_listky`, `jidlo_custom_listky_produkty`
Systém pro správu jídel a nápojů (občerstvení na akcích).

### `projects_categories`, `projects_grid`, `projects_notes`
Jednoduchý projektový/poznámkový systém (kanban grid).

### `results_*` tabulky
Starší systém výsledků (results_udalosti, results_vysledky, results_sportovci, results_kategorie, results_registrace, results_zebricek_body, results_zebricek_vysledky, results_administratori).

### `aa_treneri`
Legacy tabulka trenérů (stará verze bez role).

### `sportovci-backup1911`
Záloha tabulky sportovci.

### `mikulas_registrations`
Jednorázová registrace na Mikuláše.

### `nastaveni`
Systémová nastavení (klíč-hodnota).

| Klíč | Popis |
|------|-------|
| `schema_version` | Aktuální verze DB schématu (spravuje `includes/auto_migrace.php`) |
| `zadavani_dni_zpet` | Počet dní zpět, o kolik mohou běžní trenéři zadávat tréninky (integer) |
| `push_vapid_public` | VAPID veřejný klíč (Base64url) — Web Push, přidáno v 2.17.0 |
| `push_vapid_private` | VAPID soukromý klíč (Base64url) — **nikdy nezobrazovat uživatelům** |
| `push_vapid_subject` | VAPID subject (`mailto:evidence@kovopraha.cz`) |

---

## 12. Rezervační systém sportovišť

Přidáno ve verzi **2.8.0**. Dvě vrstvy:
- **Interní** — trenéři rezervují sportoviště pro tréninky (kapacita 1–5/5)
- **Veřejný booking** — zákazníci si rezervují individuální lekce na veřejných sportovištích

### `sportovist`
Evidovaná sportoviště (velodrom, posilovna, tělocvičny, sauna, nahrávací místnost).

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `kod` | varchar(40) UNIQUE | Strojový identifikátor (např. `velodrom`) |
| `nazev` | varchar(120) | Zobrazovaný název |
| `je_verejne` | tinyint(1) | 1 = zákazníci mohou rezervovat online |
| `max_kapacita` | int DEFAULT 5 | Maximum souběžně obsaditelných dílů |
| `poradi` | int | Řazení v UI |
| `aktivni` | tinyint(1) | 1 = aktivní |

**Výchozí seed:** velodrom (veřejné), posilovna_horni (veřejné), telocvicna_horni, telocvicna_spodni, sauna, nahravaci_mistnost.

Správa: `sprava_sportovist.php` (admin CRUD).

### `rezervace_sportovist`
Interní rezervace sportoviště trenéry. Jedna řádka = jeden časový blok.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `sportoviste_id` | int FK → sportovist | |
| `trener_id` | int FK → treneri | |
| `datum` | date | |
| `cas_od` | time | |
| `cas_do` | time | |
| `kapacita_dilu` | tinyint 1–5 | Kolik dílů z max_kapacita je blokováno |
| `trenink_id` | int FK → treninky NULL | Propojení s tréninkem (pokud vzniklo z formuláře tréninku) |
| `lekce_id` | int FK → individualni_lekce NULL | Propojení s individuální lekcí (auto-blokace 5/5) |
| `poznamka` | text NULL | |
| `vytvoreno` | timestamp | |

**Kontrola obsazenosti:** `SELECT SUM(kapacita_dilu) WHERE datum=? AND cas_od < ? AND cas_do > ?` — pokud výsledek + nová kapacita > max_kapacita, rezervace je zamítnuta.

### `verejni_uzivatele`
Zákaznické účty pro veřejný booking systém. Odděleni od `treneri`.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `jmeno` | varchar(80) | |
| `prijmeni` | varchar(80) | |
| `email` | varchar(160) UNIQUE | |
| `heslo_hash` | varchar(255) | `password_hash()` / `password_verify()` |
| `telefon` | varchar(20) NULL | |
| `verifikacni_token` | varchar(64) NULL | Token pro ověření emailu |
| `email_overeno` | tinyint(1) DEFAULT 0 | 1 = email ověřen, lze se přihlásit |
| `aktivni` | tinyint(1) DEFAULT 1 | |
| `registrovan` | timestamp | |
| `sportovec_id` | int FK → sportovci NULL | ⏳ Plánováno (Fáze 1) — propojení zákazníka s interním profilem sportovce |
| `kredit_zustatek` | decimal(10,2) DEFAULT 0 | ⏳ Plánováno (Fáze 1) — kreditní wallet; transakce v `kredit_pohyby` |

Session klíč: `$_SESSION['verejny_uzivatel_id']` (oddělený od `$_SESSION['trener_id']`).

### `individualni_lekce`
Lekce vypsané trenérem pro veřejnou rezervaci.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `trener_id` | int FK → treneri | Trenér, který lekci vypsal |
| `sportoviste_id` | int FK → sportovist | Pouze veřejná sportoviště |
| `datum` | date | |
| `cas_od` | time | Začátek časového okna lekce |
| `cas_do` | time | Konec časového okna lekce |
| `slot_delka_min` | smallint DEFAULT 60 | Délka jednoho rezervovatelného slotu v minutách — **přidáno v 2.9.0** |
| `typ` | enum('zelena','zluta') | Zelená = auto-potvrzení, žlutá = trenér musí potvrdit |
| `nazev` | varchar(200) | Název viditelný zákazníkům |
| `popis` | text NULL | Popis pro zákazníky |
| `cena_kc` | decimal(8,2) | Cena lekce v Kč |
| `max_osob` | int DEFAULT 1 | Maximální počet zákazníků na lekci |
| `vyjimka_3_dny` | tinyint(1) DEFAULT 0 | Povoluje rezervaci méně než 3 dny předem — **přidáno v 2.12.0** |
| `stav` | enum('aktivni','zrusena') | |
| `vytvoreno` | timestamp | |

> **Pozn. k rezervaci kapacity (od v2.9.0):** Individuální lekce již **nevytváří** záznam v `rezervace_sportovist`. V kalendáři sportovišť jsou lekce zobrazeny jako informační (přerušovaný rámeček), ale nezabírají kapacitu dílů. Kapacita sportoviště je tedy vyhrazena výhradně pro interní týmové rezervace.

### `verejne_rezervace`
Zákazníkovo zarezervování individuální lekce (nebo konkrétního slotu v rámci lekce).

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `lekce_id` | int FK → individualni_lekce | |
| `uzivatel_id` | int FK → verejni_uzivatele | |
| `stav` | enum('ceka','potvrzena','zamitnuta','zrusena','cekaci_listina') | `ceka` = žlutá čeká na trenéra; `cekaci_listina` = slot plný, zákazník čeká — **přidáno v 2.14.0** |
| `zaplaceno` | tinyint(1) DEFAULT 0 | Manuálně označí trenér |
| `poznamka_klienta` | text NULL | |
| `poznamka_trenera` | text NULL | |
| `potvrzovaci_token` | varchar(64) NULL | Token v emailu trenérovi pro one-click potvrzení/zamítnutí |
| `slot_cas_od` | time NULL | Začátek konkrétního slotu zákazníka — **přidáno v 2.9.0** (NULL = celá lekce) |
| `slot_cas_do` | time NULL | Konec konkrétního slotu zákazníka — **přidáno v 2.9.0** (NULL = celá lekce) |
| `cas_rezervace` | timestamp | |
| `cas_potvrzeni` | timestamp NULL | Kdy trenér potvrdil |

**Flow zelená lekce:** zákazník rezervuje → `stav=potvrzena`, trenér dostane info email.  
**Flow žlutá lekce:** zákazník rezervuje → `stav=ceka`, trenér dostane email s odkazem `booking/potvrdit.php?token=&akce=potvrdit|zamit` → zákazník dostane potvrzovací email.  
**Storno:** možné nejpozději 3 dny před lekcí.

---

---

## 13. Plánovač tréninků

Přidáno ve verzi **2.10.0**. Umožňuje trenérům plánovat tréninky dopředu bez okamžité evidence docházky. Plánovaný trénink se po zadání evidence propojí s tabulkou `treninky` přes `trenink_id`.

### `planovane_treninky`
Vrstva plánování oddělená od evidovaných tréninků.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `trener_id` | int FK → treneri | Trenér, který plán vytvořil |
| `skupina_id` | int FK → skupiny NULL | Plánovaná skupina (volitelné) |
| `podskupina_id` | int FK → podskupiny NULL | Plánovaná podskupina (volitelné) |
| `datum` | date NOT NULL | Datum plánovaného tréninku |
| `cas_od` | time NULL | Čas začátku (volitelné) |
| `cas_do` | time NULL | Čas konce (volitelné) |
| `sportoviste_id` | int FK → sportovist NULL | Plánované sportoviště (volitelné) |
| `rezervace_id` | int NULL | Odkaz na `rezervace_sportovist.id` (pokud byl plán vytvořen z rezervace) |
| `trenink_id` | int FK → treninky NULL | Propojení s evidovaným tréninkem (NULL = čeká na evidenci, vyplněno = evidováno) |
| `nazev` | varchar(200) DEFAULT '' | Název/popis plánu |
| `kategorie` | enum('silnice','mtb','draha','cyklokros','posilovna','atletika','cviceni','plavani') NULL | Kategorie tréninku |
| `popis` | text NULL | Detailní popis |
| `stav` | enum('planovany','evidovany','zruseny') DEFAULT 'planovany' | Životní cyklus plánu |
| `upominka_cas` | timestamp NULL | Kdy byla odeslána upomínka trenérovi — **přidáno v 2.13.0** |
| `serie_id` | int NULL | ID série (sdílené mezi instancemi opakujícího se plánu) — **přidáno v 2.16.0** |
| `vytvoreno` | timestamp | |

**Životní cyklus stavu:**
- `planovany` — vytvořeno, čeká na zadání evidence
- `evidovany` — `trenink_id` je vyplněno, evidence byla zadána přes `formular.php?plan_id=X`
- `zruseny` — plán byl zrušen (bez vazby na trénink)

**Veřejný program skupiny:** Soubor `program_skupiny.php` zobrazuje záznamy se stavem `planovany` nebo `evidovany` ve zvoleném časovém horizontu — přístup bez přihlášení přes URL `?hash=<skupinaHash>`.

### `planovane_treninky_podskupiny`
Junction tabulka — více podskupin na jeden plánovaný trénink. *(přidáno v 2.11.0)*

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `plan_id` | int FK → planovane_treninky | |
| `podskupina_id` | int FK → podskupiny | |

PK je složený `(plan_id, podskupina_id)`. Legacy sloupec `planovane_treninky.podskupina_id` zachován pro zpětnou kompatibilitu.

---

## 14. Web Push notifikace *(přidáno v 2.17.0)*

### `push_subscriptions`
Web Push odběry trenérů (endpoint + VAPID klíče).

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | int PK | |
| `trener_id` | int NULL | FK → treneri |
| `endpoint` | text NOT NULL | Push endpoint URL (z prohlížeče) |
| `p256dh` | varchar(255) | Veřejný klíč prohlížeče |
| `auth` | varchar(255) | Auth secret |
| `user_agent` | varchar(255) NULL | Identifikátor prohlížeče |
| `created_at` | timestamp | |

**Aktivace:** trenér klikne na 🔔 v navbaru → `push_subscribe.php` uloží subscription. Notifikace posílá `includes/push_helper.php` přes `sendPushNotification()`.

**Závislost:** `composer require minishlink/web-push`. VAPID klíče se ukládají do `nastaveni` tabulky (`push_vapid_public`, `push_vapid_private`, `push_vapid_subject`).

---

## 15. Plánované tabulky *(Fáze 1–2, neimplementováno)*

Viz `docs/roadmapa-rozsireni.md` pro plný plán.

| Tabulka | Verze | Popis |
|---------|-------|-------|
| `kredit_pohyby` | 2.19.0 | Transakční log kreditního walletu (nabití z tréninků, čerpání v e-shopu) |
| `sso_tokeny` | 2.19.0 | Jednorázové tokeny pro přihlášení zákazníka do e-shopu |

---

## ER diagram — hlavní vztahy

```
treneri ──┬── trenink_trener ──── treninky
          │                        ├── trenink_sportovec ── sportovci
          │                        ├── trenink_skupina ──── skupiny
          │                        ├── trenink_podskupina ─ podskupiny
          │                        ├── trenink_tag ──────── tagy
          │                        ├── trenink_mereni ───── mereni_zaznamy ──┬── cviky
          │                        │                                        └── segmenty
          │                        ├── mereni (legacy)
          │                        └── story_vygenerovane
          │
          ├── dalsi_cinnosti
          ├── zavody ──┬── zavod_skupina
          │            ├── zavod_podskupina
          │            ├── zavod_trener
          │            ├── zavod_sportovec  (sportovec_id nullable, +jmeno_ext, +klub, +kategorie_start)
          │            ├── zavod_mereni ──── mereni_zaznamy
          │            ├── zavod_fotka
          │            └── zavod_import
          │
          ├── rezervace_sportovist ── sportovist
          │    └── trenink_id → treninky (nullable)   [lekce_id odstraněno v 2.9.0]
          │
          ├── individualni_lekce ──┬── sportovist
          │                       └── verejne_rezervace ── verejni_uzivatele
          │                            ├── slot_cas_od (nullable)
          │                            └── slot_cas_do (nullable)
          │
          ├── planovane_treninky ──┬── skupiny (nullable)
          │                       ├── podskupiny (nullable, legacy)
          │                       ├── sportovist (nullable)
          │                       ├── treninky (nullable, trenink_id — po evidenci)
          │                       └── planovane_treninky_podskupiny ── podskupiny  [M:N, 2.11.0]
          │
          ├── push_subscriptions  [Web Push odběry, 2.17.0]
          │
          └── ucto_audit_log

sportovci ──┬── sportovec_skupina ──── skupiny
            ├── sportovec_podskupina ─ podskupiny ── skupiny
            ├── sportovec_obdobi
            ├── sportovec_poznamka
            ├── sportovec_interni_poznamka
            └── zatezove_testy ─── zatezove_testy_soubory

soupiska_mapping ──┬── skupiny
                   └── podskupiny

verejni_uzivatele ─── verejne_rezervace ─── individualni_lekce
```

---

## Dodatek 2.20.0 - clenska evidence

`sportovci` ma nove provozni sloupce `stav_clenstvi`, `stav_duvod`, `stav_manualni`, `stav_aktualizovan`, `kis_identity_key`, `kis_match_confidence` a `kis_last_seen_at`.

Nove tabulky: `kis_import_runs`, `kis_import_rows`, `kis_import_matches` a `sportovec_history`.

*Verze dokumentace: 2.20.0 — červen 2026*
