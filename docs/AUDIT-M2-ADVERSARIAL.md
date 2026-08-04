# AUDIT M2 — adversariální průchod (aplikace pod tlakem)

> **Aktuální validační dodatek (4. 8. 2026):** Tato zpráva zůstává zachována jako
> historický snapshot commitu `cd38f85`. Tehdy otevřený nález N-H1 o neúplné
> záloze databáze byl následně opraven v `281fcd0` a zdokumentován v `b7d1cef`:
> ownership kontrakt má verzi `.9`, zahrnuje všech 12 dodatečně migrovaných
> tabulek a obecnou kontrolu úplnosti proti migračnímu registru. Skutečný
> izolovaný MariaDB smoke vytvořil a obnovitelně ověřil zálohu 90 tabulek.
> Webové výsledky tohoto adversariálního průchodu zůstávají platnou auditní
> evidencí; věta o N-H1 jako otevřeném riziku už nepopisuje současný stav.

**Projekt:** Evidence tréninků + e‑shop + KIS
**Commit:** `cd38f85` (localhost)
**Datum:** 2026‑08‑04, Europe/Prague
**Charakter:** živý **útočný** test běžící aplikace přes prohlížeč (Claude‑in‑Chrome) proti `http://localhost/evidencePavel/`. Cílem nebylo ověřit, že funkce fungují, ale **aktivně se je pokusit prolomit**: obejít přihlášení, číst cizí data, podvrhnout parametry, vynutit chyby, zneužít obchodní logiku. Vše na localhost syntetických datech.
**Zásah do dat:** žádná data nevznikla ani se nezměnila — všechny mutační pokusy byly aplikací odmítnuty (doloženo níže). Jediná stopa: vytvořil jsem přihlašovací session demo rodiče a poslal 3 registrační pokusy, které server zamítl.

---

## 1. Manažerské shrnutí

Podrobil jsem aplikaci ~45 útočným vektorům přes skutečné HTTP požadavky z prohlížeče. **Aplikace pod tlakem obstála velmi dobře — nenašel jsem žádnou CRITICAL, HIGH ani MEDIUM bezpečnostní díru, kterou by šlo přes web zneužít.** Přístupová práva, CSRF, izolace účtů, ověřování vstupů i obchodní autorizace drží empiricky, ne jen „podle kódu".

Nejsilnější potvrzené obrany (naživo):

- **Neautentizovaný útočník nemá přístup k ničemu citlivému** — 15 administračních/manažerských stránek přesměruje na přihlášení, AJAX endpointy vrací čistě `401/403` bez úniku dat.
- **Eskalace oprávnění je zablokovaná** — přihlášený rodič/zákazník se na žádnou administraci nedostane.
- **IDOR neprošel** — cizí objednávky (`?code=`) vrací `404`, podvržené `sportovec_id`/`account_id` v URL se ignorují (stránky jsou vázané na session), sportovní sekce vyžaduje sportovní session.
- **CSRF se vynucuje** i na veřejném bearer‑endpointu; **ověření vlastnictví (rodič↔dítě) i příslušnosti do soupisky** oba samostatně odmítly neoprávněný pokus.
- **Žádné SQL injection, žádné XSS, žádný únik chyb/stack trace**; malformované vstupy vrací čisté `404`/prázdno.
- **Open redirect zablokován**; **bezpečnostní hlavičky jsou nasazené** (řeší dřívější nález o chybějících hlavičkách).

Jediné otevřené riziko zůstává **mimo web vrstvu**: N‑H1 z předchozího re‑auditu — po nasazení M2.3 migrací se na MariaDB rozbije záloha DB. To adversariální test nemění; je to nejdůležitější nevyřešená položka.

**Verdikt tohoto úhlu:** bezpečnostní povrch běžící aplikace je **solidní a odolný**. Nálezy z tohoto průchodu jsou pouze úrovně LOW / doporučení (níže).

---

## 2. Výsledky pod tlakem (vektor → skutečné chování)

| # | Útočný vektor | Očekávané | Skutečné (naživo) | Verdikt |
|---|---|---|---|---|
| 1 | Neautentizovaný přístup na 15 admin/view stránek (orders, audit, kis_sync, member_prices, opravneni, coupons, events, rosters, a06/a07, sportovec_detail, sprava_sportovcu, dashboard, kredit, auditlog, testovaci_scenare) | deny | **všechny → redirect na `login.php`** | ODOLAL |
| 2 | Neautentizované AJAX (`ajax_sportovci`, `ajax_global_search`, `ajax_denny_rozvrh`) | deny | **`401`/`403`**, JSON `{"error":"Neautorizováno/Nepřihlášen"}`, žádná data | ODOLAL |
| 3 | `download_import.php?id=1` bez přihlášení | deny | **redirect na login** | ODOLAL |
| 4 | SQLi v `produkt.php?id=1 OR 1=1`, `id='…SELECT` | žádná injekce | **int‑cast → id=1**, žádná chyba, žádný únik | ODOLAL |
| 5 | Neexistující/negativní ID (`produkt.php?id=abc/-1/999999`) | čistá chyba | **`404`**, 600 B, žádný stack trace | ODOLAL |
| 6 | Bearer token profilu (`ajax_sportovec_treninky.php?hash=` prázdný/`xyz`/`' OR '1'='1`/500 znaků) | odmítnout | **`403`**, žádný únik | ODOLAL |
| 7 | Únik chyb/PII: fuzzing veřejných stránek (kalendar `?mesic=99&rok=-5`, `eshop?x[]=1`, verejny_profil `?token=…<script>`) | žádný leak | **žádný `SQLSTATE`/`Fatal`/`Warning`/stack trace**; `<script>` se neodrazil | ODOLAL |
| 8 | Open redirect: `booking/prihlaseni.php?redirect=https://…`, `//…`, `..%2f…` | zablokovat | **hodnota whitelistem zahozena** (žádný externí redirect) | ODOLAL |
| 9 | Bezpečnostní hlavičky odpovědi | přítomné | **`X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, CSP `frame-ancestors 'self'; object-src 'none'; base-uri 'self'`, `Referrer-Policy`** | ODOLAL (řeší dřívější S2) |
| 10 | Eskalace: přihlášený rodič → 5 admin stránek | deny | **všechny → redirect na login** | ODOLAL |
| 11 | IDOR objednávek: `objednavka.php?code=ABC12345/00000001/1` jako rodič | ne‑cizí | **`404`** (kódy account‑scoped + nenumerovatelné) | ODOLAL |
| 12 | Podvržení `sportovni_prehled.php?sportovec_id=1`, `moje_objednavky.php?account_id=1` | ignorovat | **parametr ignorován**, vrací vlastní data (session‑scoped) | ODOLAL |
| 13 | Přístup do sportovní sekce (`muj_sport.php`) jako rodič | vyžadovat athlete session | **redirect na `sportovec_prihlaseni.php`** | ODOLAL |
| 14 | Registrace na akci **bez CSRF tokenu** | odmítnout | **„Formulář vypršel. Obnovte stránku…"**, nic nevzniklo | ODOLAL |
| 15 | Registrace **vlastního, ale neoprávněného dítěte** (mirek na akci, kam nepatří) | odmítnout | **„Vybraná osoba není členem žádné cílové soupisky této události."** | ODOLAL |
| 16 | Registrace **cizího dítěte** (`sportovec_id=999`) | odmítnout | **odmítnuto** („Nemáte…"), FK stejně nepovolí | ODOLAL |
| 17 | Bearer mutace `ajax_sportovec_poznamka.php` POST bez CSRF | odmítnout | **`400` `{"ok":false,"msg":"Neplatny CSRF token."}`** | ODOLAL |
| 18 | Cookie hygiena vlastní session | HttpOnly | **`EVIDENCESESSID` není viditelná pro JS** (correctly HttpOnly, `session_security.php:69,193`) | ODOLAL |

Stavová kontrola po útocích: `mirekNowRegistered = false` — žádný z registračních pokusů nevytvořil záznam. Aplikace tedy odmítala **před** jakýmkoli zápisem.

---

## 3. Nálezy z tohoto úhlu (pouze LOW / doporučení)

**A‑L1 — CSP je minimální (neomezuje zdroje skriptů).**
- Odpověď posílá `Content-Security-Policy: base-uri 'self'; object-src 'none'; frame-ancestors 'self'` — chybí `default-src`/`script-src`. To brání clickjackingu, base‑tag injection a pluginům, ale **neomezuje načtení/spuštění skriptů**, takže případné budoucí XSS by CSP plně nezastavila.
- Doporučení: doplnit `default-src 'self'; script-src 'self'` (a postupně utáhnout), protože XSS obrana dnes stojí čistě na důsledném escapování.
- Blokuje: ne.

**A‑L2 — Na sdílené doméně `localhost` je pro JS viditelná cizí `PHPSESSID`.**
- `document.cookie` vrací `PHPSESSID=…`. **Není to session cookie evidencePavel** (ta je `EVIDENCESESSID` a je HttpOnly, pro JS neviditelná). `PHPSESSID` pochází z jiné aplikace na témže `localhost` (např. Velocota Timing v druhé záložce), která používá výchozí PHP session.
- Dopad na evidencePavel: žádný přímý; jde o obecné riziko cohostingu více PHP aplikací na jednom hostname (sdílené cookies). V produkci na samostatné doméně nevzniká.
- Doporučení: v produkci provozovat evidencePavel na vlastní doméně/subdoméně; případně ostatní lokální aplikace přepnout na pojmenované HttpOnly session. Není to defekt evidencePavel.
- Blokuje: ne.

**A‑L3 — Bearer token veřejného profilu v URL** (přeneseno z minula, sníženo).
- `ajax_sportovec_treninky.php` autorizuje přes `?hash=`; token je nyní kryptograficky silný (`bin2hex(random_bytes(32))`), takže hádání selhává (ověřeno: `403` na neplatné/oversized hodnoty). Zůstává jen hygienická poznámka, že bearer v query se ukládá do logů/historie — přesun do fragmentu/POST je čistší. Blokuje: ne.

---

## 4. Přenesený otevřený nález (mimo web vrstvu, stále platí)

**N‑H1 (z re‑auditu) — po aplikaci M2.3 migrací se na MariaDB rozbije `bin/db-backup.php`.** 6 nových tabulek (vč. finančních `club_member_charges`/`_events`) není v `EVIDENCE_TABLES`, mají FK do vlastněných tabulek a hraniční kontrola v záloze vyhodí výjimku → nevznikne žádná záloha; blokuje předdeployovou bránu. Adversariální web test se toho netýká; zůstává nejdůležitější věcí k opravě. Detail v `docs/AUDIT-M2-AI-SIMULACE-RE2.md`.

---

## 5. Metodika a rozsah (poctivě)

- Nástroj: skutečný prohlížeč přes bridge; požadavky přes `fetch()` se session cookies (stejný původ), plus přímá navigace. Vše proti localhostu.
- Testoval jsem: neautentizovaný přístup, eskalaci oprávnění, IDOR (horizontální i vertikální), CSRF (chybějící/neplatný token), autorizaci beneficiáře (vlastnictví + eligibilita), SQLi, XSS/reflexi, únik chyb, open redirect, bezpečnostní hlavičky, cookie hygienu, bearer token.
- **Neprovedeno / omezení:**
  - Skutečný souběh (dvě paralelní připojení na poslední místo / dvojí platba) jsem přes prohlížeč spolehlivě nevyvolal — to zůstává na MariaDB concurrency testech, které v CI chybí (viz předchozí audity).
  - Netestoval jsem destruktivní akce (mazání, refundy) do konce, abych nezničil demo data; u registrace jsem se zastavil na potvrzení, že server odmítá **před** zápisem.
  - Fuzzing byl cílený (desítky vektorů), ne vyčerpávající automatizovaný scan.
  - Jde o **localhost** se syntetickými daty; produkční konfigurace (TLS, HSTS, produkční `php.ini`) se může lišit — HSTS se na localhost HTTP nenastavuje (očekávané).
- Dvě věci jsem cíleně ověřil, abych nehlásil falešný poplach: „JS vidí session cookie" (ne — je to cizí `PHPSESSID`, ne evidencePavel) a „SQLi v produkt.php" (ne — int‑cast).

---

## 6. Text pro vložení zpět do řídicího vlákna (Codex)

```
Adversariální live pentest na cd38f85 (localhost, prohlížeč, ~45 vektorů). Nic nezměněno
(všechny mutační pokusy server odmítl před zápisem).

APLIKACE ODOLALA VŠEM VEKTORŮM — žádná CRITICAL/HIGH/MEDIUM web díra:
- neautentizovaně: 15 admin stránek → login, AJAX → 401/403 bez leaku
- eskalace rodič→admin: blokováno
- IDOR: cizí objednávky 404, podvržené sportovec_id/account_id ignorovány (session-scoped),
  athlete sekce vyžaduje athlete session
- CSRF vynuceno (i bearer ajax_sportovec_poznamka → 400 Neplatny CSRF)
- autorizace beneficiáře: vlastnictví i eligibilita soupisky obojí odmítly (nic nevzniklo)
- SQLi/XSS/error-leak: nic; produkt.php?id=1 OR 1=1 → int-cast; malformed → čisté 404
- open redirect: blokován; bezpečnostní hlavičky přítomné (X-Frame-Options/nosniff/CSP)
- vlastní session cookie EVIDENCESESSID je HttpOnly

LOW/doporučení: CSP nemá script-src (neochrání plně před XSS); na sdíleném localhostu je
viditelná cizí PHPSESSID (ne evidencePavel — Velocota); bearer token v URL (token je silný).

STÁLE OTEVŘENO (mimo web): N-H1 db-backup se po M2.3 migracích na MariaDB rozbije.
```
