# Návrh informační architektury

Aktualizováno: 8. 8. 2026, Europe/Prague · Base: `main` HEAD `5c99509`
(„Add env-driven inputs to Shoptet import for restricted hosting shell").

**Status: NÁVRH, nic z tohoto dokumentu není implementováno.** Úkol byl čistě
analytický a read-only — žádný PHP soubor nebyl změněn, nic nebylo přejmenováno
ani přesunuto. Tento dokument je vstup pro budoucí rozhodnutí vlastníka, ne
zápis hotové práce. Zdroje: [`docs/CURRENT_STATE.md`](CURRENT_STATE.md),
[`docs/plan-eshop-tymova-evidence/SESSION_HANDOFF.md`](plan-eshop-tymova-evidence/SESSION_HANDOFF.md),
[`docs/thinline-deploy-runbook.md`](thinline-deploy-runbook.md), živé čtení
`hlavicka.php`, `index.php`, `includes/ui_shell.php` a exhaustivní grep celého
repozitáře (mimo `vendor/`).

## 0. Shrnutí problému vlastníka

Vlastníkova připomínka měla tři části a všechny tři se v kódu potvrdily:

1. **„Po přihlášení trenéra chybí viditelný most na veřejný portál."** Potvrzeno.
   Trenérská navigace (`hlavicka.php`, stav přihlášen) nemá žádný trvalý odkaz
   na veřejný portál. Jediné dva mosty dnes jsou: odkaz „Veřejný kalendář" schovaný
   uvnitř rozbalovací nabídky Rezervace (vede jen na `booking/kalendar.php`,
   `target="_blank"`), a karta „Veřejný portál" na nástěnce `index.php` — tu ale
   trenér vidí, jen když je zrovna na nástěnce.
2. **„Menu Správa je přehlcené (~20 položek bez hierarchie)."** Podhodnoceno —
   je jich **39** (16 v „Správa dat" + 20 v „Administrace" + 3 ve „Firemní
   evidenci", mimo dva položky viditelné jen na localhostu). Je to jediná
   rozbalovací nabídka bez jakéhokoli druhého patra menu.
3. **„Nástěnka je rozcestníkem všeho místo podstatného."** Potvrzeno a hůř —
   `index.php` a `hlavicka.php` se navíc rozešly: **12 stránek** je jen
   v navbaru (mj. `member_charge_reminders_admin.php`, `eshop_orders_admin.php`,
   `kis_rosters_admin.php`, `provozni_prehled_admin.php` — čtyři z nejnovějších
   funkcí M3/K-řady), zatímco **17 stránek** je jen na nástěnce (mj. `cviky.php`,
   export moduly, `prehled_kreditu.php`). Kdo opustí nástěnku, ztratí k nim
   přístup, dokud se na ni nevrátí.

## 1. Tři světy aplikace — dnešní navigační vstupy

| Svět | Vstupní nav. zdroj | Rozsah top-level položek dnes |
|---|---|---|
| Veřejnost / rodič | `hlavicka.php` (odhlášený stav, 4 položky) + `includes/ui_shell.php::publicShellNav()` (vlastní lišta uvnitř `booking/*`, 4 položky + kontextové účtové tlačítko) | 2 nezávislé, mírně odlišné navigace pro stejný svět |
| Sportovec (omezený účet) | `booking/muj_sport.php` jako jediný hub | minimální, v pořádku |
| Trenér / správce / admin | `hlavicka.php` (přihlášený stav) + `index.php` nástěnka | 6 top-level + 39položková „Správa" + samostatná ~70položková karta na nástěnce |

Klíčové zjištění: **dnes existují tři navigační zdroje pravdy** pro
trenérský svět (navbar, nástěnka, a implicitně i to, co si trenér pamatuje
z URL), místo jednoho. To je kořen všech tří připomínek.

## 2. Metodika mapování

1. `hlavicka.php` a `index.php` byly grepnuty na každý `href="...php"` cíl
   (`Grep -o`), výsledek deduplikován a porovnán množinově (`sort -u` + `comm`).
2. Získán úplný seznam `.php` souborů kořene, `booking/`, `vozidla/`, `jizdy/`,
   `servis/`, `uctenky/`, `udalosti/`, `auditlog/`, `auth/`, `zatezove_testy/`,
   `bin/`, `includes/` (Glob).
3. Soubory, které nejsou cílem žádného odkazu v `hlavicka.php` ani `index.php`,
   byly rozděleny na tři dávky a předány třem nezávislým read-only Explore
   agentům, kteří pro každý soubor: přečetli účel a guard, grepli celý
   prvostranný kód na odkazy odjinud, a klasifikovali jej jako kontextovou
   stránku, osiřelou stránku, podezřele duplicitní/mrtvý soubor, nebo technický
   soubor bez vlastního UI. Nálezy jsou v oddílech 4–5.
4. `bin/*.php` (20 souborů) jsou výhradně CLI/deploy skripty popsané
   v `docs/thinline-deploy-runbook.md` — nejsou to stránky, nejsou v mapě.
   `reports/` a `evidence-deploy-ssh/` neobsahují žádné `.php`.

## 3. Úplná mapa aktivních stránek (83 z hlavicka.php + index.php)

Sloupec **Umístění** používá zkratky: `H` = `hlavicka.php`, `I` = `index.php`,
`H+I` = v obou. Sloupec **Frekvence** je vlastníkův odhad vzoru použití, ne
měření.

### 3.1 Veřejnost / rodič — `booking/` (10 stránek)

| Soubor | Role | Frekvence | Umístění dnes |
|---|---|---|---|
| `booking/eshop.php` | veřejné (nákup vyžaduje účet) | denně | H+I, + `publicShellNav()` |
| `booking/krouzky.php` | veřejné | týdně | H+I, + `publicShellNav()` |
| `booking/velodrom.php` | veřejné | týdně | H+I, + `publicShellNav()` |
| `booking/treninky.php` | veřejné | týdně | H+I, + `publicShellNav()` |
| `booking/prihlaseni.php` | veřejné (přihlášení zákazníka) | denně | H+I |
| `booking/registrace.php` | veřejné (nový účet) | týdně | I only |
| `booking/moje_osoby.php` | zákazník | denně/týdně | H only, + `publicShellNav()` |
| `booking/moje_objednavky.php` | zákazník | týdně | I only, + `publicShellNav()` |
| `booking/sportovni_prehled.php` | zákazník (rodič) | týdně | I only, + `publicShellNav()` |
| `booking/kalendar.php` | veřejné (rezervace lekcí) | denně | H (Rezervace dropdown) + I |

### 3.2 Sportovec — `booking/`, omezený účet (2 stránky)

| Soubor | Role | Frekvence | Umístění dnes |
|---|---|---|---|
| `booking/sportovec_prihlaseni.php` | veřejné (přihlášení sportovce) | týdně | I only |
| `booking/muj_sport.php` | sportovec | denně/týdně | H+I, + `publicShellNav()` |

### 3.3 Trenér/správce/admin — Domů (2)

| Soubor | Role | Frekvence | Umístění dnes |
|---|---|---|---|
| `index.php` | trenér | denně | brand-odkaz + „Domů" v H |
| `login.php` | veřejné → trenér | týdně | H (odhlášený stav) |

### 3.4 Vložit (6)

| Soubor | Role | Frekvence | Umístění dnes |
|---|---|---|---|
| `formular.php` | trenér | denně | H+I |
| `nova_cinnost.php` | trenér | týdně | H+I |
| `zatezovy_test_form.php` | trenér | výjimečně | H+I |
| `duplikovat_trenink.php` | trenér | týdně | I only |
| `edit_trenink.php` | trenér (kontext. edit) | denně | I only (odkaz z „Poslední tréninky") |
| `formular_zavod.php` | trenér\* (`canAccess`) | výjimečně | H (Správa dat) + I (Vkládání i Závodní sekce) — **3 různá místa, nekonzistentní** |

### 3.5 Přehledy + Exporty + Závodní sekce (15)

| Soubor | Role | Frekvence | Umístění dnes |
|---|---|---|---|
| `moje_treninky.php` | trenér | denně | H+I |
| `moje_skupiny.php` | trenér | týdně | H+I |
| `prehled_trenera.php` | trenér | týdně | H+I |
| `prehled_sportovcu.php` | trenér | týdně | H+I |
| `prehled_treninku_skupiny_kalendar.php` | trenér | týdně | H+I |
| `vypis_vykazu.php` | trenér | týdně | H+I |
| `prehled_popisu.php` | trenér | týdně | H+I |
| `prehled_zavodu.php` | trenér | týdně/sezónně | H+I |
| `sprava_zavodu.php` | trenér\* (`canAccess`) | týdně/sezónně | H (Správa dat) + I (Závodní sekce) |
| `hromadne_podskupiny.php` | trenér | výjimečně | I only |
| `oznameni.php` | trenér | týdně | I only |
| `export_draha.php` | trenér | výjimečně | I only |
| `export_seznam.php` | trenér | výjimečně | I only |
| `export_uci.php` | trenér | výjimečně | I only |
| `google_sheets_linky.php` | trenér | výjimečně | I only |

### 3.6 Plánovač (1)

| Soubor | Role | Frekvence | Umístění dnes |
|---|---|---|---|
| `planovac.php` | trenér\* (`canAccess`) | denně/týdně | H+I |

### 3.7 Rezervace sportovišť (4)

| Soubor | Role | Frekvence | Umístění dnes |
|---|---|---|---|
| `kalendar_sportovist.php` | trenér\* | týdně | H+I |
| `rezervovat_sportoviste.php` | trenér\* | týdně | H+I |
| `individualni_lekce_sprava.php` | trenér\* | denně/týdně | H+I |
| `individualni_lekce_form.php` | trenér\* | výjimečně | H+I |

### 3.8 Nastavení — trenérská úroveň (3)

| Soubor | Role | Frekvence | Umístění dnes |
|---|---|---|---|
| `cviky.php` | trenér | výjimečně | I only |
| `hromadne_odmeny.php` | trenér | výjimečně | I only |
| `sprava_segmentu.php` | trenér na I, ale **správce** na H | výjimečně | H (Správa dat) + I (Nastavení) — **nekonzistentní role mezi zdroji** |

### 3.9 Správa dat — správce/hlavní (16)

| Soubor | Role | Frekvence | Umístění dnes |
|---|---|---|---|
| `sprava_sportovcu.php` | správce | denně/týdně | H+I |
| `admin_dashboard.php` | správce | týdně | H+I |
| `sprava_vsech_treninku.php` | správce | týdně | H+I |
| `sprava_skupin.php` | správce | výjimečně | H+I |
| `sprava_podskupin.php` | správce | výjimečně | H+I |
| `verejny_prehled.php` | správce | výjimečně | H+I |
| `prehled_vsech_vykazu.php` | správce | týdně | H+I |
| `sync_evidence.php` | správce | výjimečně (sync okna) | H+I |
| `kis_sync_center.php` | správce | týdně (sync okna) | H+I |
| `kis_rosters_admin.php` | správce | týdně | H only |
| `kis_training_a07_admin.php` | správce, jen `JE_LOKALNE` | výjimečně | H only |
| `club_programs_admin.php` | správce | sezónně | H+I |
| `verejny_velodrom_admin.php` | správce | sezónně | H+I |
| `odeslat_emaily.php` | správce | výjimečně | I only |
| `prehled_kreditu.php` | správce | výjimečně | I only |
| `sprava_sportovec_obdobi.php` | správce | výjimečně | I only |

### 3.10 Administrace — admin (24, ve 4 přirozených shlucích)

**E-shop**

| Soubor | Frekvence | Umístění dnes |
|---|---|---|
| `eshop_admin.php` | týdně | H+I |
| `eshop_catalog_publication_admin.php` | výjimečně | H+I |
| `eshop_events_admin.php` | týdně | H+I |
| `eshop_member_prices_admin.php` | výjimečně | H only |
| `eshop_notifications_admin.php` | denně/týdně | H only |
| `eshop_order_expiry_admin.php` | výjimečně | H+I |
| `eshop_orders_admin.php` | denně/týdně | H only |

**Členové a KIS**

| Soubor | Frekvence | Umístění dnes |
|---|---|---|
| `eshop_identity_admin.php` | výjimečně | H+I |
| `kis_child_access_admin.php` | výjimečně | H+I |
| `kis_transition_admin.php` | výjimečně | H+I |
| `person_audit_admin.php` | výjimečně | H+I |
| `family_weekly_summaries_admin.php` | týdně | H only |
| `member_charge_reminders_admin.php` | týdně | H only |

**Provoz**

| Soubor | Frekvence | Umístění dnes |
|---|---|---|
| `provozni_prehled_admin.php` | týdně | H only |
| `sports_data_quality_admin.php` | výjimečně | H only |
| `sports_import_review_admin.php` | výjimečně | H only |
| `testovaci_scenare.php` (jen `JE_LOKALNE`) | výjimečně | H+I |

**Nastavení a firemní evidence**

| Soubor | Frekvence | Umístění dnes |
|---|---|---|
| `nastaveni_opravneni.php` | výjimečně | H+I |
| `nastaveni_zadavani.php` | výjimečně | H+I |
| `sprava_treneru.php` | výjimečně | H+I |
| `sprava_sportovist.php` | výjimečně | H+I |
| `vozidla/seznam.php` | výjimečně | H+I |
| `uctenky/seznam.php` | výjimečně | H+I |
| `udalosti/seznam.php` | výjimečně | H+I |

Součet 3.1–3.10: 10+2+2+6+15+1+4+3+16+24 = **83** — přesně odpovídá množině
odkazů z `hlavicka.php` ∪ `index.php` (viz oddíl 7).

## 4. Nalezeno navíc: funkční stránky bez vstupu z hlavní navigace

Toto jsou reálné, funkční stránky (agenti je otevřeli a ověřili guard i obsah),
které ale **nejsou cílem žádného odkazu v `hlavicka.php` ani `index.php`**.
U každé je uvedeno, odkud je (pokud vůbec) dosažitelná dnes.

| Soubor | Účel | Dosažitelná odkud dnes | Doporučení |
|---|---|---|---|
| `member_charges_admin.php` | Read-only přehled členských předpisů a plateb (dokumentovaný v CURRENT_STATE.md jako funkční vstup) | jen kontextově z `kis_sync_center.php`, `member_charge_reminders_admin.php`, `provozni_prehled_admin.php` | Přidat přímý odkaz do Administrace → Členové a KIS |
| `eshop_coupons_admin.php` | Administrace slevových kupónů | jen kontextově z `eshop_admin.php` | V pořádku jako podstránka `eshop_admin.php`, zvážit zmínku v tabulce E-shopu |
| `eshop_fio_admin.php` | Read-only přehled Fio shadow importu a návrhů párování | jen kontextově z `eshop_admin.php`, `provozni_prehled_admin.php` | V pořádku jako podstránka |
| `kis_rollover_a06_admin.php` | Průvodce A06 — roční věková obnova soupisek KIS | jen z `testovaci_scenare.php` (lokální testovací rozcestník) | Prakticky nedohledatelná v reálném provozu — přidat odkaz do Administrace → Členové a KIS |
| `auditlog/seznam.php` | Prohlížeč audit logu (admin, plně funkční) | **nikde, 0 odkazů v celém prvostranném kódu** | Skutečná mezera — přidat do Administrace → Nastavení a firemní evidence |
| `sportovci_hromadne.php` | Hromadné akce nad vybranými sportovci (POST akce z formuláře) | `sprava_sportovcu.php` (`action=`) | V pořádku, je to akční cíl, ne samostatná stránka |
| `sportovec_karta.php`, `sportovec_detail.php`, `sportovec_treninky.php` | Admin karta člena (novější), starší detail, veřejná karta přes hash | vzájemně provázané + `sprava_sportovcu.php`, `prehled_sportovcu.php`, `ajax_global_search.php` | V pořádku, kontextové detail stránky. `sportovec_detail.php` je dle popisku na kartě označen jako „Starý detail" — viz oddíl 5 |
| `edit_zavod_form.php`, `zavod_detail.php` | Editace / detail závodu | `prehled_zavodu.php`, `sprava_zavodu.php` | V pořádku, kontextové |
| `planovany_trenink_form.php`, `program_skupiny.php` | Formulář plánovaného tréninku; veřejný program skupiny přes hash | `planovac.php`, `sprava_skupin.php` | V pořádku, kontextové |
| `pub.php` | Veřejný přehled tréninků (filtr trenér/skupina/měsíc) | **nikde, 0 odkazů** | Osiřelá — ověřit s vlastníkem, zda je stále potřeba (možná nahrazeno `verejny_prehled.php`) |
| `list.php`, `prehled_skupin.php`, `prehled_skupina.php`, `prehled_podskupin.php` | Starší veřejný přehled skupin/podskupin — vzájemně provázaný shluk | jen samy mezi sebou; **žádný vstup z živé aplikace** | Celý shluk je izolovaný ostrov — pravděpodobně nahrazen `verejny_prehled.php`, ověřit s vlastníkem |
| `prehled_stories.php`, `nastaveni_story.php` | Galerie vygenerovaných stories; nastavení vzhledu | jen z mrtvých `index-backup.php`/`index3.php` (oddíl 5) | Funkce generování (`generuj_story.php`) se stále spouští z tréninkového accordionu, ale galerii ani nastavení dnes nejde z živé appky otevřít — rozhodnout: vrátit odkaz, nebo formálně utlumit |
| `tydenni_report.php` | Sestavení a uložení týdenního reportu skupiny | **nikde, 0 odkazů** | Osiřelá — ověřit potřebu (možný pár k `cron_report_tyden.php`) |
| `vypis_vsech_vykazu.php` | Výpis všech měsíčních výkazů trenérů | jen z mrtvých `index-backup.php`/`index3.php` | Pravděpodobně nahrazeno `prehled_vsech_vykazu.php` (to JE v menu) — ověřit a případně odstranit duplicitu |

Zbylých ~42 nalezených stránek (12 v `booking/`: `moje_programy.php`,
`moje_rezervace.php`, `nove_heslo.php`, `objednavka.php`, `overeni.php`,
`potvrdit.php`, `produkt.php`, `rezervovat.php`, `rodinny_kalendar.php`,
`verejny_kalendar.php`, `verejny_profil.php`, `zapomenute_heslo.php`; 19 v
`vozidla/jizdy/servis/uctenky/udalosti`: formuláře, mazání, ukládání,
vyúčtování; + `jizdy/seznam.php` a `servis/seznam.php`) jsou správně
kontextové — dosažitelné z vlastní účtové navigace (`publicShellNav()`),
e-mailových tokenů, nebo prokliku z mateřského `seznam.php` v témže modulu
(`vozidla/seznam.php` → tlačítka „Jízdy"/„Servis" na řádku vozidla). Nejde
o mezery, jde o očekávaný vzor detail/akce a nemají v menu co dělat.

## 5. Vyřazeno z mapy — technická vrstva a podezřelé soubory

Toto **nejsou aktivní stránky** a nepatří do žádné navigace — uvedeno kvůli
úplnosti auditu, nic z toho není v tomto řezu měněno.

**Technická vrstva (očekávaná, v pořádku):** 14 `ajax_*.php`/`nacti_*.php`/
`push_subscribe.php` JSON endpointů; POST-only handlery
(`ulozit_trenink.php`, `ulozit_zavod.php`, `update_trenink.php`,
`update_zavod.php`, `smazat_trenink.php`, `edit_zavod.php`,
`private_download.php`, `download_import.php`, `search_sportovci.php`,
`generuj_story.php`, `registrace.php` v kořeni — prázdný redirect stub);
CLI/cron (`cron_upominky.php`, `cron_report_tyden.php`, celé `bin/`);
knihovny (`csrf_helper.php`, `db.php`, `report_tyden_lib.php`,
`auth/sso_bridge.php`, `booking/waiting_list.php`,
`booking/odhlaseni.php`, `booking/sportovec_odhlaseni.php`); export
zpracování (`export.php`, `export_csv.php`, `export_xls.php`,
`analyze_excel.php`, `analyze_uci_form.php`, `import_vysledku_zavodu.php`,
`club_event_participants_export.php`); testovací skript
(`test.php` — ruční PhpSpreadsheet smoke test).

**Podezřelé duplicitní nebo mrtvé soubory (doporučeno k rozhodnutí
vlastníka, v tomto řezu NEZMĚNĚNO):**

| Soubor | Nález |
|---|---|
| `index-backup.php`, `index3.php` | Starší uložené verze `index.php` (9,7 kB a 5,6 kB proti dnešním 45 kB), leží přímo ve webrootu, nikde neodkazované |
| `sprava_treninku.php` | Starší varianta `sprava_vsech_treninku.php` (253 vs 413 řádků), stejné oprávnění, jediný odkaz je `Location:` po smazání tréninku |
| `sync.php` | Starší import sportovců z Excelu (627 vs 1448 řádků), komentář v kódu přímo odkazuje na `sync_evidence.php` jako nástupce |
| `prehled_skupiny.php` | Téměř identický s `prehled_skupina.php`, jen jiný název parametru, 0 odkazů |
| `admin_panel.php` | **Neobsahuje PHP** — celý soubor je cizí textový obsah (transkript jiné AI relace zmiňující repozitář „velocota-results"), přítomný už v commitu „Initial import with security hardening". Pravděpodobně artefakt importu, ne aktivní kód — stojí za ověření původu, ale nesouvisí s tímto řezem |
| `zatezove_testy/zatezovy_test_form.php`, `zatezove_testy/ulozit_zatezovy_test.php` | Přesměrovací stuby na kořenové soubory (kompatibilita starých záložek), samy nic nezobrazují |
| 6× `.../vypis_vykazu (3|4) - kopie.php` (`vozidla`×2, `jizdy`, `servis`, `uctenky`, `udalosti`) | Bajtově identické omylem rozkopírované starší verze kořenového `vypis_vykazu.php` (dnes 311 řádků); nesouvisí s hostitelským modulem; při přímém volání spadnou na fatální chybu (nepředponované `require`); nikde neodkazované |

Připomínka z `CLAUDE.md`: „Nic nepřejmenovávej a nenavrhuj přesuny souborů
na disku bez silného důvodu — URL jsou v produkci." Tento oddíl je nález,
ne návrh na smazání — rozhodnutí je na vlastníkovi.

## 6. Cílová informační architektura

### 6.1 Princip

Jedna navigace pro každý svět, žádný druhý nezávislý zdroj pravdy. Nástěnka
přestává být druhým menu a stává se tím, co dnes už částečně je nejlépe
funkční — přehledem „co je dnes důležité", ne katalogem všech funkcí.

### 6.2 Veřejnost/rodič a Sportovec — beze změny struktury

`publicShellNav()` (4 položky + kontextové účtové tlačítko) je už kompaktní
a nemá problém, který vlastník popsal. Jediná navržená úprava (viz 6.5,
rychlá výhra č. 5): sloučit 5 samostatných tlačítek zákaznického účtu
(Profil / Moje osoby / Moje kroužky / Rezervace / Objednávky) do jedné
rozbalovací nabídky „Můj účet", aby lišta nerostla s každou další funkcí.

### 6.3 Trenér/správce/admin navbar — max 7 položek první úrovně

| # | Položka | Gate | Obsah |
|---|---|---|---|
| 1 | Domů | trenér | beze změny |
| 2 | Vložit | trenér | beze změny (3 položky) |
| 3 | Přehledy | trenér | beze změny (8 položek + oddělovače) |
| 4 | Plánovač | `canAccess` | beze změny |
| 5 | Rezervace | `canAccess` | beze změny (5 položek) |
| 6 | **Klub** (nové jméno pro první polovinu dnešní „Správy") | správce | podnabídky **Provoz** (11 položek z 3.9) a **Členové a KIS** (4 položky z 3.9) |
| 7 | **Administrace** (beze změny jména, dnes už existuje) | admin | podnabídky **E-shop** (7), **Členové a KIS** (6), **Provoz** (4), **Nastavení a firemní evidence** (7, včetně nově přilinkovaného `auditlog/seznam.php`) |

Plus trvalý, ne-top-level prvek: odkaz „Veřejný portál" vedle přepínače
tmavého režimu (viz 6.5, rychlá výhra č. 1) — viditelný na úplně každé
stránce pro každého přihlášeného trenéra/správce/admin, ne jen na nástěnce.

**Past na oprávnění, na kterou si dát pozor:** `cviky.php`,
`hromadne_odmeny.php` a `sprava_segmentu.php` jsou dnes dostupné na úrovni
běžného trenéra (přes nástěnku, `sprava_segmentu.php` nekonzistentně i přes
hlavní menu za `is_hlavni`). Nesmí skončit uvnitř nové skupiny „Klub", která
je gatovaná na úroveň správce — to by byla tichá regrese oprávnění pro
řadové trenéry. Patří do trenérské úrovně (např. jako doplněk „Přehledy"
nebo „Vložit"), ne do „Klubu".

### 6.4 Cílová nástěnka (`index.php`, přihlášený stav)

Ponechat (funguje dobře už dnes):
- uvítací banner s datem,
- „Vyžaduje pozornost" — jediná část nástěnky, která skutečně dělá to, co
  má nástěnka dělat; navrhuji rozšířit o další signály (KIS páry čekající na
  rozhodnutí, položky na čekací listině rezervací),
- 4–6 osobních zkratek („Co chcete udělat?"),
- statistiky měsíce.

Zrušit: celou „card-wall" sekci se ~40 odkazy (dnešní karty Vkládání /
Přehledy / Závodní sekce / Rezervace / Nastavení / Správa dat / Administrace).
Všechny tyto funkce budou dostupné z navbaru podle 6.3 — jejich druhý úplný
výpis přímo na nástěnce je to, co vlastník popsal jako „rozcestník všeho
místo podstatného", a je to i zdroj rozporu mezi navbarem a nástěnkou
z oddílu 0. Nahradit dvěma souhrnnými odkazy „Otevřít Klub" a „Otevřít
Administraci" (pro roli, která na ně má nárok).

### 6.5 Dotčené soubory a odhad rozsahu

| Změna | Soubory | Rozsah |
|---|---|---|
| Trvalý odkaz „Veřejný portál" v navbaru | `hlavicka.php` | S |
| Doplnit `member_charges_admin.php`, `kis_rollover_a06_admin.php`, `auditlog/seznam.php` do Správy | `hlavicka.php`, `index.php` | S |
| Opravit `_dropActive('edit_zavod_form.php')` s `href="prehled_zavodu.php"` | `hlavicka.php` | S |
| Podnadpisy uvnitř dnešní jedné „Správy" (Provoz/Členové a KIS/E-shop/Nastavení) bez rozdělení na 2 menu | `hlavicka.php` | S/M |
| Sloučit 5 tlačítek zákaznického účtu do jedné nabídky „Můj účet" | `includes/ui_shell.php` | S |
| Rozdělit „Správu" na „Klub" + „Administrace" (2 top-level místo 1) | `hlavicka.php` | M |
| Přesunout trenérská nastavení (`cviky.php` aj.) do navbaru bez změny role | `hlavicka.php` | S |
| Sesynchronizovat `index.php` karty s `hlavicka.php` (dočasné doplnění, než se karty zruší) | `index.php` | S/M |
| Zrušit „card-wall" na nástěnce, nahradit dvěma odkazy | `index.php` | M/L |
| Rozšířit „Vyžaduje pozornost" o další signály | `index.php` | M |

Žádná z těchto změn nevyžaduje přejmenování nebo přesun souboru na disku —
mění se jen `hlavicka.php`, `index.php` a `includes/ui_shell.php`.

## 7. Rychlé výhry (realizovatelné jen úpravou hlavicka.php/index.php)

1. **Trvalý odkaz „Veřejný portál"** v navbaru `hlavicka.php`, vedle ikony
   tmavého režimu, `target="_blank"` na `booking/eshop.php` — viditelný na
   každé stránce pro každého přihlášeného, ne jen na nástěnce. Přímo řeší
   připomínku č. 1.
2. **Přidat 3 chybějící odkazy** do „Správa dat"/„Administrace":
   `member_charges_admin.php`, `kis_rollover_a06_admin.php` a
   `auditlog/seznam.php` (poslední je dnes zcela nedosažitelný kliknutím).
3. **Podnadpisy uvnitř dnešní „Správy"** (`<h6 class="dropdown-header">` pro
   Provoz / Členové a KIS / E-shop / Nastavení a firemní evidence) — nejvyšší
   poměr přínosu k rozsahu ze všech změn: řeší připomínku č. 2 vizuálně bez
   jediné změny routování.
4. **Opravit zavádějící `_dropActive('edit_zavod_form.php')`** u položky,
   jejíž `href` ve skutečnosti vede na `prehled_zavodu.php` — drobná
   nekonzistence v kódu `hlavicka.php`, jednořádková oprava.
5. **Sloučit zákaznická účtová tlačítka** (`includes/ui_shell.php`,
   `publicShellNav()`) z 5 samostatných tlačítek do jedné nabídky „Můj účet".

## 8. Větší kroky vyžadující rozhodnutí vlastníka

- **Rozdělit „Správu" na dvě top-level menu „Klub" a „Administrace"** místo
  jedné mega-nabídky (6.3) — mění strukturu, na kterou jsou trenéři zvyklí;
  vhodné oznámit předem.
- **Zrušit „card-wall" na nástěnce** (6.4) — nejviditelnější změna pro
  všechny denní uživatele, měla by být samostatně odsouhlasena, ideálně
  s krátkým přechodovým obdobím (ponechat karty a nový navbar vedle sebe).
- **Osud izolovaného shluku** `list.php` + `prehled_skupin.php` +
  `prehled_skupina.php` + `prehled_podskupin.php` + `pub.php` +
  `tydenni_report.php` + `vypis_vsech_vykazu.php` + story galerie
  (`prehled_stories.php`, `nastaveni_story.php`) — vrátit do navigace, nebo
  formálně potvrdit, že jsou nahrazené (`verejny_prehled.php`,
  `prehled_vsech_vykazu.php`) a mohou se later odstranit. Není v gesci
  tohoto řezu rozhodnout ani provést.
- **Prošetřit `admin_panel.php`** — soubor ve webrootu neobsahuje PHP, jen
  cizí AI-transkript zmiňující jiný repozitář. Bezpečnostně nekritické (není
  to spustitelný kód), ale stojí za ověření, jak se tam vzal.
- **Úklid 6 duplicitních `vypis_vykazu (N) - kopie.php` souborů** a
  `index-backup.php`/`index3.php` — čistě technický dluh, mimo rozsah
  informační architektury, ale zjištěno při stejném auditu.

## 9. Ověření pokrytí

- `hlavicka.php` obsahuje odkazy na **66** unikátních `.php` cílů.
- `index.php` obsahuje odkazy na **71** unikátních `.php` cílů.
- Sjednocení (unikátní stránky odkazované alespoň z jednoho souboru): **83**.
- Oddíly 3.1–3.10 obsahují dohromady **83** řádků (10+2+2+6+15+1+4+3+16+24),
  jeden řádek na každou unikátní stránku ze sjednocení — **pokrytí 100 %**,
  ověřeno množinovým průnikem (`comm`/`sort -u`) nad syrovým výstupem `grep`,
  ne ručním počítáním.
- Navíc zmapováno **64** dalších reálně fungujících stránek nalezených mimo
  oba soubory (oddíl 4) a **13** technických souborů, které stránkami nejsou
  (oddíl 5, mimo cca 30 CRUD akčních souborů `vozidla/jizdy/servis/uctenky/
  udalosti` a booking flow, popsaných souhrnně na konci oddílu 4).
- Součet 83 (v menu) + 42 (kontextové navíc, oddíly 4 a shrnutí na jeho konci)
  ≈ 125, což je blízko dokumentovaným „127 aktivních PHP HTML stránek"
  z `docs/CURRENT_STATE.md` (rozdíl vysvětlují okrajové případy jako
  `booking/waiting_list.php`, které mají `hlavicka.php`/`appUiAssets()`
  formálně dostupné, ale nejsou navigovatelnou stránkou) — nezávislé
  potvrzení, že mapa nic zásadního nevynechává.

## 10. Další krok

Doporučené pořadí: nejprve rychlé výhry (oddíl 7, vše `hlavicka.php`/
`includes/ui_shell.php`, žádné riziko), pak vlastníkovo rozhodnutí o rozsahu
větších kroků (oddíl 8) a teprve pak implementace jako samostatný, testovaný
řez — mimo rozsah tohoto dokumentu.
