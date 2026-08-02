# Roadmapa rozšíření — Evidence tréninků

*Plánované změny nad rámec aktuálního stavu aplikace. Dokument slouží pro interní koordinaci i AI cowork.*

---

## Přehled změn

| # | Změna | Stav | Priorita | Fáze |
|---|-------|------|----------|------|
| 1 | Propojení sportovců s veřejnými profily | ⏳ Plánováno | Střední | 1 |
| 2 | Kreditní wallet (zůstatek k čerpání) | ⏳ Plánováno | Střední | 1 |
| 3 | Napojení na externí e-shop (API bridge) | ⏳ Plánováno | Vysoká | 2 |
| 4 | Jednotný profil (booking + e-shop) | ⏳ Plánováno | Vysoká | 2 |

---

## Kontextový přehled — výchozí stav

### Co dnes existuje

```
sportovci              verejni_uzivatele
──────────────────     ─────────────────────────
id                     id
jmeno, prijmeni        jmeno, prijmeni
email                  email + heslo_hash
narozeni               telefon
hash (veřejná karta)   email_overeno
...                    aktivni

(ODDĚLENY — žádná vazba)
```

**Kreditní systém (stávající):**
- `sportovec_obdobi`: sportovec_id + sazba_kc + datum_od/do + pocet_treninku + castka_celkem + vyplaceno
- Funguje jako **evidence peněžních odměn** za docházku (Kč za trénink)
- Výstup: celková částka k vyplacení za dané období
- Správa: `sprava_sportovec_obdobi.php`, `prehled_kreditu.php`, `hromadne_odmeny.php`

**Veřejný booking (stávající):**
- `verejni_uzivatele`: registrace emailem, rezervace lekcí přes `booking/`
- Nemá žádnou vazbu na `sportovci` — jsou to dvě různé identity

---

## Změna 1 — Propojení sportovců s veřejnými profily

### Záměr

Každý sportovec vedený v evidenci by měl mít automaticky k dispozici veřejný uživatelský profil. Díky tomu si bude moci sám rezervovat sportoviště a lekce — bez nutnosti samostatné registrace.

### Architektura

```
sportovci                 verejni_uzivatele
──────────────────────    ─────────────────────────────
id                   ───► sportovec_id INT NULL  ← nové (FK)
jmeno, prijmeni           jmeno, prijmeni
email           ──────────► email (sdílené)
hash                       heslo_hash
                           email_overeno
                           kredit_zustatek  ← nové (viz Změna 2)
```

Vazba jde z `verejni_uzivatele → sportovci` (ne naopak), protože:
- Ne každý veřejný uživatel je sportovec (zákazníci z ulice)
- Každý sportovec *může* mít veřejný účet, ale nemusí

### DB migrace

```sql
-- verejni_uzivatele: reference na sportovce (volitelná)
ALTER TABLE verejni_uzivatele
  ADD COLUMN sportovec_id INT NULL DEFAULT NULL
    COMMENT 'Propojení s evidencí sportovců (nullable — zákazníci bez evidence)'
  AFTER id,
  ADD INDEX idx_sportovec (sportovec_id);
```

### Auto-vytvoření profilu

Profily sportovců lze vytvořit třemi způsoby:

**A) Hromadně admin akcí** (doporučeno pro start):
- Nové tlačítko v `sprava_sportovcu.php`: „Vytvořit veřejné profily"
- Pro každého sportovce s vyplněným emailem: INSERT IGNORE do `verejni_uzivatele`
- Propojení: nastaví `sportovec_id` v nově vzniklém záznamu

**B) Automaticky při importu** (sync_evidence.php):
- Po import kroku 4 (provedení): pro každého nového sportovce automaticky vytvořit profil
- Vygenerovat dočasné heslo → odeslat uvítací email

**C) Na vyžádání** (sportovec klikne na svou veřejnou kartu a zaregistruje se):
- `sportovec_treninky.php?hash=X` zobrazí „Přihlaste se nebo zaregistrujte" s předvyplněným emailem ze záznamu sportovce
- Při registraci automaticky propojit přes email

### Dopad na existující kód

| Soubor | Změna |
|--------|-------|
| `sprava_sportovcu.php` | Přidat akci „Vytvořit profily" |
| `sync_evidence.php` | Volitelný krok auto-vytvoření profilů |
| `sportovec_treninky.php` | Banner pro nepřihlášené sportovce |
| `booking/registrace.php` | Při shodě emailu: automaticky propojit se sportovcem |
| `booking/prihlaseni.php` | Při přihlášení: načíst `sportovec_id` do session |

### Session po přihlášení

```php
// Po úspěšném přihlášení do bookingu:
$_SESSION['verejny_uzivatel_id']      = $uzivatel['id'];
$_SESSION['verejny_uzivatel_jmeno']   = $uzivatel['jmeno'] . ' ' . $uzivatel['prijmeni'];
$_SESSION['verejny_sportovec_id']     = $uzivatel['sportovec_id'];  // může být null
```

---

## Změna 2 — Kreditní wallet

### Záměr

Stávající kreditní systém eviduje peněžní odměny za tréninky jako administrativní přehled (manažer vidí dluh, označí jako vyplacené). Chceme vytvořit **digitální wallet** — zůstatek kreditů, které sportovec může aktivně čerpat v e-shopu nebo na nákup lekcí.

### Vztah ke stávajícímu systému

```
Stávající flow:
  trenink → sportovec_obdobi (sazba × pocet = castka_celkem) → vyplaceno = 1

Nový flow:
  trenink → sportovec_obdobi (beze změny — zůstává pro administrativu)
              │
              ▼ při uzavření období (admin action)
           kredit_pohyby (typ=nabití, castka=castka_celkem)
              │
              ▼
           verejni_uzivatele.kredit_zustatek (denormalizovaný součet)
```

Stávající `sportovec_obdobi` systém zůstává beze změny. Wallet je nová vrstva navíc.

### DB migrace

```sql
-- 1. Wallet zůstatek na profilu (denormalizovaný pro rychlé čtení)
ALTER TABLE verejni_uzivatele
  ADD COLUMN kredit_zustatek DECIMAL(10,2) NOT NULL DEFAULT 0.00
    COMMENT 'Aktuální zůstatek kreditů (Kč), udržován přes kredit_pohyby';

-- 2. Transakční log pohybů kreditů (auditovatelný)
CREATE TABLE kredit_pohyby (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    uzivatel_id     INT             NOT NULL,
    typ             ENUM('nabiti','cerpani','storno') NOT NULL,
    castka          DECIMAL(10,2)   NOT NULL,  -- kladná vždy; typ určuje směr
    zustatek_po     DECIMAL(10,2)   NOT NULL,  -- snapshot po pohybu
    popis           VARCHAR(255)    NOT NULL DEFAULT '',
    zdroj           ENUM('trenink','eshop','admin','storno') NOT NULL,
    ref_id          INT             NULL,  -- odkaz na sportovec_obdobi.id, objednavka_id atd.
    vytvoreno       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_uzivatel (uzivatel_id),
    INDEX idx_vytvoreno (vytvoreno)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Tok kreditů

```
[Nabití — z tréninků]
  admin uzavře sportovec_obdobi
    → INSERT kredit_pohyby (typ=nabiti, zdroj=trenink, ref_id=obdobi_id)
    → UPDATE verejni_uzivatele SET kredit_zustatek = kredit_zustatek + castka

[Čerpání — v e-shopu]
  e-shop volá Evidence API: POST /api/kredit/cerpat
    → kontrola zůstatku
    → INSERT kredit_pohyby (typ=cerpani, zdroj=eshop)
    → UPDATE verejni_uzivatele SET kredit_zustatek = kredit_zustatek - castka

[Storno]
  e-shop vrátí objednávku → POST /api/kredit/storno
    → INSERT kredit_pohyby (typ=storno, zdroj=storno)
    → UPDATE verejni_uzivatele SET kredit_zustatek = kredit_zustatek + castka
```

### Nové soubory (Fáze 1)

| Soubor | Účel |
|--------|------|
| `prehled_kreditu_v2.php` | Přehled wallet + pohybů pro admina; rozšíří stávající `prehled_kreditu.php` |
| `muj_ucet.php` | Self-service stránka sportovce — zůstatek, historia pohybů |

---

## Změna 3 — Napojení na externí e-shop (API bridge)

### Záměr

E-shop je vyvíjen externě jako samostatná aplikace. Evidence mu poskytne API pro:
1. Ověření identity uživatele (SSO token)
2. Čtení zůstatku kreditů
3. Čerpání / stornování kreditů

### Architektura

```
E-shop (externí)              Evidence (tato aplikace)
──────────────────            ──────────────────────────
uživatel klikne               GET /api/kredit/stav
"Platit kredity"   ─────────► ?token=<api_token>&uzivatel_id=X
                              Response: { zustatek: 250.00, mena: "Kc" }
                   ◄─────────

platba proběhne    ─────────► POST /api/kredit/cerpat
                              { token, uzivatel_id, castka, ref_eshop }
                              Response: { ok, novy_zustatek }
                   ◄─────────
```

### API endpointy (nové soubory)

```
api/
├── auth.php           GET  ?token=&email=  →  { ok, uzivatel_id, jmeno, kredit_zustatek }
├── kredit_stav.php    GET  ?token=&uid=    →  { zustatek, pohyby_posledni[] }
├── kredit_cerpat.php  POST                 →  { ok, novy_zustatek } | { error }
└── kredit_storno.php  POST                 →  { ok, novy_zustatek }
```

### Autentizace API

```php
// api/auth.php — sdílený tajný klíč (v nastaveni tabulce nebo config.php)
// E-shop volá Evidence vždy s hlavičkou:
//   X-Evidence-Key: <api_key>
// nebo jako GET param:
//   ?api_key=<api_key>

define('ESHOP_API_KEY', 'kp_eshop_2026_...');  // v config.php, NENÍ v gitu
```

### SSO token pro e-shop

Při přihlášení zákazníka do Evidence:
1. Vygeneruje se jednorázový SSO token (platnost 5 min.)
2. Evidence přesměruje na e-shop s tokenem v URL
3. E-shop ověří token přes `api/auth.php`
4. E-shop nastaví vlastní session

```
booking/prihlaseni.php → ?redirect=eshop → generuj SSO token
                        → Location: https://shop.kovopraha.cz/?sso_token=<token>
E-shop: GET api/auth.php?sso_token=<token> → { uzivatel_id, jmeno, kredit }
```

### SSO tokeny — DB

```sql
CREATE TABLE sso_tokeny (
    token       VARCHAR(64) NOT NULL PRIMARY KEY,
    uzivatel_id INT         NOT NULL,
    vypršení    DATETIME    NOT NULL,
    použito     TINYINT(1)  NOT NULL DEFAULT 0,
    INDEX idx_uzivatel (uzivatel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Co e-shop musí implementovat

| Zodpovědnost | Evidence | E-shop |
|-------------|----------|--------|
| Správa profilů | ✓ (master) | čte přes API |
| Wallet zůstatek | ✓ (master) | zobrazí + čerpá přes API |
| Produkty, objednávky | — | ✓ (master) |
| Platební brána | — | ✓ |
| Design / branding | sdílí CSS třídy | ✓ |

---

## Změna 4 — Jednotný profil (booking + e-shop)

### Záměr

Pod jednou registrací (emailem a heslem) může uživatel:
- Rezervovat sportoviště a lekce (`booking/`)
- Nakupovat v e-shopu
- Vidět zůstatek kreditů a historii pohybů
- Zobrazit svou sportovní kartu (pokud je sportovec)

### Centrální entita: `verejni_uzivatele`

```
verejni_uzivatele (rozšířena o Fáze 1 a 2)
────────────────────────────────────────────────
id
jmeno, prijmeni, email, heslo_hash, telefon
email_overeno, aktivni
registrovan
sportovec_id INT NULL          ← [Změna 1] propojení s evidencí
kredit_zustatek DECIMAL        ← [Změna 2] wallet zůstatek
```

### „Můj účet" — přehled pro uživatele

Nová stránka `muj_ucet.php` v `booking/`:
- Osobní údaje (edit)
- Sportovní karta (odkaz na `sportovec_treninky.php?hash=X` pokud `sportovec_id IS NOT NULL`)
- Zůstatek kreditů + posledních 10 pohybů
- Moje rezervace (existující `booking/moje_rezervace.php`)
- Odkaz do e-shopu (s SSO tokenem)

### Registrace — rozšíření

`booking/registrace.php`:
1. Zkontrolovat email v `sportovci.email` → pokud nalezen, automaticky propojit po ověření
2. Uvítací email: „Váš účet byl propojen s evidencí sportovce"
3. Pokud sportovec nemá email: trenér mu ho přidá v `sprava_sportovcu.php`

---

## Fáze implementace

### Fáze 1 — Profil + Wallet (nezávislé na e-shopu)

```
[ ] 1a. DB migrace 2.19.0
        - verejni_uzivatele.sportovec_id
        - verejni_uzivatele.kredit_zustatek
        - CREATE TABLE kredit_pohyby
        - CREATE TABLE sso_tokeny (prep. pro Fázi 2)

[ ] 1b. Hromadné vytváření profilů sportovců
        - sprava_sportovcu.php → akce „Vytvořit profily"
        - email sportovce → INSERT IGNORE verejni_uzivatele + SET sportovec_id

[ ] 1c. booking/registrace.php — auto-propojení se sportovcem podle emailu

[ ] 1d. booking/prihlaseni.php — načíst sportovec_id do session

[ ] 1e. prehled_kreditu.php — tlačítko „Nabít kredit" (uzavření období → kredit_pohyby)

[ ] 1f. booking/muj_ucet.php — stránka „Můj účet" se zůstatkem a historií

[ ] 1g. sportovec_treninky.php — banner pro přihlášení / propojení profilu
```

### Fáze 2 — E-shop API (koordinace s e-shop týmem)

```
[ ] 2a. api/ složka s endpointy (auth, kredit_stav, kredit_cerpat, kredit_storno)
[ ] 2b. SSO token generátor (booking/prihlaseni.php + api/auth.php)
[ ] 2c. config.php: ESHOP_API_KEY, ESHOP_URL
[ ] 2d. E-shop implementuje volání Evidence API
[ ] 2e. Testování integrace (kredit flow, SSO přihlášení)
```

### Fáze 3 — Rozšíření (volitelné)

```
[ ] 3a. Kredit za lekce (zákazník zaplatí, část se vrátí jako kredit)
[ ] 3b. Push notifikace na nabití kreditů
[ ] 3c. Případné sdílení identity s jiným klubovým systémem jen po samostatném rozhodnutí
```

---

## Otevřené otázky (nutné rozhodnout před implementací)

| # | Otázka | Dopad |
|---|--------|-------|
| 1 | Kde žije „master" identity — Evidence nebo e-shop? | Ovlivní SSO směr |
| 2 | Jakým způsobem e-shop komunikuje s Evidence — REST API nebo sdílená DB? | Bezpečnost, výkon |
| 3 | Mají kredity expiraci? | DB schema, wallet logika |
| 4 | Jaká je minimální výše čerpání v e-shopu? | UI, API validace |
| 5 | Kdo spravuje zákazníky bez sportovního záznamu (čistí e-shop zákazníci)? | User flows |
| 6 | Jak se synchronizuje heslo pokud uživatel mění v obou systémech? | Auth design |

> **Doporučení**: před Fází 2 uspořádat meeting s e-shop týmem a rozhodnout body 1–3.

---

## Change control — co koordinovat

Změny v těchto oblastech dotýkají se rozšíření a vyžadují koordinaci:

| Oblast | Aktuální soubory | Fáze dotčení |
|--------|-----------------|--------------|
| `verejni_uzivatele` schéma | `booking/registrace.php`, `booking/prihlaseni.php` | 1 |
| Session pro booking | `booking/*.php` (guard `verejny_uzivatel_id`) | 1 |
| `sportovec_obdobi` — uzavírání | `prehled_kreditu.php`, `sprava_sportovec_obdobi.php` | 1 |
| API endpointy | nová složka `api/` | 2 |
| E-shop SSO token | `booking/prihlaseni.php` + `api/auth.php` | 2 |

---

## Neměnit bez koordinace (mezisystémové kontrakty)

Po implementaci Fáze 2 se přidají pouze kontrakty uvnitř Evidence a jejího
shopového modulu. Velocota není součástí této integrační roadmapy; viz
`docs/integrace-velocota.md`.

Po Fázi 2 budou přibývat:
- `api/` endpointy — kontrakt s e-shopem (URL, parametry, response formát)
- `kredit_pohyby.zdroj` ENUM — rozšíření musí být koordinováno s e-shopem
- `sso_tokeny` tabulka — délka platnosti a logika musí být synchronizována

---

*Verze: 1.0 — červen 2026 | Stav: plánováno, neimplementováno*
