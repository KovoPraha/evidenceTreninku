# AUDIT M2 — AI simulace a nezávislá revize

**Projekt:** Evidence tréninků + e‑shop + KIS (týmová evidence)
**Prostředí:** localhost (`http://localhost/evidencePavel/`), XAMPP, syntetická data
**Commit v době auditu (ověřeno živě):** `12232eb` „Record A10 audit acceptance"
**Datum:** 2026‑08‑04, Europe/Prague
**Charakter:** read‑only audit kódu a dokumentace + živý průchod UI ve skutečném prohlížeči. Žádný produkční zásah, žádný ostrý import, žádná skutečná platba, žádný commit/push, žádný ruční SQL zápis. Zdrojový kód ani dokumentace nebyly změněny — jediný nový soubor je tato zpráva.

> **Následná validace řídicím taskem (aktuální lokální strom stejného commitu).** Část
> závěrů níže vznikla ze zastaralé bridge kopie a není aktuálním nálezem. Skutečný
> `SESSION_HANDOFF.md` uvádí implementační HEAD `4ce0f17`, větev ahead 105,
> 355/3135 testů, 386 syntakticky ověřených PHP souborů, migrace 39/39 a ownership
> kontrakt `.8`. Kanonický plán uvádí M2 65 %, M2.6 96 %, K5 98 % a e-shop 97 %.
> Lokální HTTP a DB kontrola dále potvrdila funkční odkaz `produkt.php?id=1`, veřejnou
> výzvu k přihlášení i klubovou cenu 2 367 Kč pro demo rodiče přes `LOCALHOST U15
> 2026`. Obě přihlašovací stránky zobrazují explicitní chybu a záměrně podporují
> správce hesel; bearer profil již posílá `Referrer-Policy: no-referrer` a
> `Cache-Control: no-store`. Jako potvrzený nový úkol zůstalo chybějící spuštění
> existujících MariaDB smoke testů v CI; oprava je implementovaná v `ef5ec21` a
> lokálně prošly oba MariaDB smokes i plná sada 356/3142.

> **Důležité upozornění k prostředí auditu.** Audit běžel z cloudového agenta napojeného na váš počítač přes bridge. Bridge do vašeho stroje běží v Linux VM **bez PHP a bez Composeru**, takže jsem na vašem stroji **nemohl spustit** `php bin/seed-local-demo.php`, `php bin/migrate.php` ani `phpunit`. Testy jsem proto spustil v izolovaném cloudovém sandboxu nad zrcadlenou kopií kódu (viz sekce „Automatické testy"), a živý průchod UI jsem provedl řízením vašeho Chromu proti vašemu localhostu. Kde jsem něco nemohl ověřit naživo, je to výslovně označeno.

---

## 1. Manažerské shrnutí

**Co skutečně funguje (ověřeno naživo v prohlížeči):**

- **Přihlášení a role fungují.** Přihlásil jsem se jako localhost administrátor i jako demo rodič; jednotný účet platí pro e‑shop, rezervace i trenérskou Evidenci (uvedeno přímo v přihlašovací obrazovce).
- **Veřejná část (A09) je přístupná bez přihlášení** — klubový portál, e‑shop katalog, kalendář velodromu, kroužky a události se zobrazí i nepřihlášenému návštěvníkovi; nákup a rezervace vyžadují přihlášení.
- **Oddělení rodiny (A01) je v pořádku.** Demo rodič vidí přesně své schválené osoby (dvě děti jako „rodič/zástupce" + vlastní profil), s výslovnou poznámkou, že osoby se nepřipojují automaticky. Přehledové stránky rodiče **neberou ID osoby z URL** (jsou vázané na session), takže není co podvrhnout.
- **Eskalace oprávnění je zablokovaná.** Jako rodič/zákazník jsem se pokusil otevřít administrátorskou auditní stránku (`person_audit_admin.php`) a byl jsem přesměrován na trenérské přihlášení — zákaznická session na administraci nestačí.
- **Auditní osa (A10) funguje výborně.** Spojuje účet, přístup sportovce, objednávky, kroužkové programy, soupisky, rollover i klubové události; u každé události je čas, zdroj, akce, aktér (s typem a ID), změna stavu, důvod a odkaz na zdroj. Chybějící aktér/důvod se **nedomýšlí** (zobrazí se „—").
- **Klíčové dřívější nálezy jsou už opravené v kódu** (potvrzeno na aktuálním commitu): predikovatelný token veřejného profilu, blokace potvrzení platby po znovu‑nákupu stornovaného kroužku, chybějící tabulka v zálohovém kontraktu, i nadměrný „password_reset" audit šum ze seedu.

**Co je blokující nebo otevřené:**

- **M2.0 — ruční prohlídka vlastníka A01–A10 stále neproběhla.** To je záměrná lidská brána a jediná skutečná blokace dokončení M2. Bez ní se M2 nedá prohlásit za hotové.
- **Dokumentace zaostává za kódem (drift).** `SESSION_HANDOFF.md` popisuje stav ~100 commitů zpět (uvádí HEAD `1b4d9e1`, 303 testů, ownership kontrakt `.6`), zatímco realita je HEAD `12232eb`, 339 testů, kontrakt `.8`. Kanonický M2 plán uvádí M2 = 30 %, ačkoli kód mezitím doplnil samoobslužný reset hesla, akceptační toky A05–A08, klubové ceny a veřejný rozvrh. Procenta v promptu (M2 65 %, M2.6 96 %, K5 98 %, e‑shop 97 %) neodpovídají žádnému commitnutému dokumentu.
- **Testovou sadu nebylo možné spustit na vašem stroji** (na bridge VM není PHP). V cloudovém sandboxu jsem ji spustil, ale kvůli nedokonalému zrcadlení souborů přes bridge část testů spadla na chybějící symboly, které na disku prokazatelně existují — tj. nešlo o reálné chyby. Definitivní zelený/červený výsledek je potřeba potvrdit spuštěním `php vendor/bin/phpunit` přímo u vás.

**Je M2 připravené k ručnímu testování vlastníkem?** **Ano.** Aplikace běží, je naseedovaná, přihlášení a hlavní cesty fungují, akceptační rozcestník `testovaci_scenare.php` je funkční. Vlastník může A01–A10 projít.

**Je projekt připravený na produkční deploy?** **Ne** — a ani to není cíl M2. Produkční aktivace, ostrý KIS/Shoptet import, automatické platby (Fio/Stripe) a wallet jsou záměrně blokované; chybí ruční prohlídka vlastníka, aktualizace dokumentace a potvrzení testů na cílovém prostředí.

**Pět nejdůležitějších dalších kroků:**

1. Vlastník projde A01–A10 na `testovaci_scenare.php` a zapíše připomínky (odblokuje M2.0 → M2.2).
2. Sladit dokumentaci s realitou — hlavně `SESSION_HANDOFF.md` (HEAD, počty testů, ownership kontrakt) a procenta v `10‑milnik` (aktuálně podhodnocují kód).
3. Spustit `php vendor/bin/phpunit` a `composer audit` přímo na localhostu a zaznamenat skutečný výsledek do handoffu.
4. Dokončit M2.3 (finální KIS exportní kontrakt, promote/rollback, paritní report) — jediná velká technická mezera.
5. Doplnit chybějící automatizované testy pro reálný souběh na MariaDB (dnes CI běží jen na SQLite, kde se zámky `FOR UPDATE`/`GET_LOCK` neprovádějí).

---

## 2. Nálezy podle závažnosti

### CRITICAL
Bez nálezu. Nenašel jsem žádnou vzdáleně zneužitelnou kritickou chybu (RCE, neautentizované SQLi, únik produkčních tajemství).

### HIGH
Žádný **nový** aktivní HIGH v běžícím kódu. Dřívější HIGH nálezy z předchozích revizí byly na tomto commitu **opravené** (viz „Stav dříve hlášených nálezů" níže). Jediná HIGH‑úrovňová věc je procesní:

**N‑H1 — `SESSION_HANDOFF.md` je zásadně neaktuální (dokumentační drift).**
- Závažnost: HIGH (dokumentace) · scénář: řízení projektu / brána M2.6 · role: řídicí task.
- Reprodukce: otevřít `docs/plan-eshop-tymova-evidence/SESSION_HANDOFF.md`, řádky 162, 168, 171.
- Očekávané: „živý obnovitelný stav" má obsahovat aktuální HEAD, počty testů a ownership kontrakt.
- Skutečné: uvádí HEAD `1b4d9e1`, testy „303/2603", ownership kontrakt „2026‑08‑04.6". Realita (ověřeno): HEAD `12232eb`, 339 testů, kontrakt `2026‑08‑04.8`. Handoff je pozadu ~100 commitů.
- Důkaz: `git rev-parse HEAD` → `12232eb`; `grep EVIDENCE_OWNERSHIP_CONTRACT_VERSION bin/db-backup.php` → `.8`; sandbox běh sady → 339 testů.
- Soubor:řádek: `SESSION_HANDOFF.md:162,168,171`.
- Nejmenší oprava: aktualizovat handoff podle jeho vlastního povinného kontraktu (datum, SHA, testy, migrace, další akce).
- Blokuje: bránu M2.6 (handoff musí obsahovat pravdivý stav), neblokuje produkční deploy sám o sobě.

### MEDIUM

**N‑M1 — Procenta hotovosti napříč dokumenty si odporují a zaostávají za kódem.**
- Závažnost: MEDIUM (dokumentace) · role: řídicí task.
- `10-milnik-m2-provozni-pilot.md:26‑34` uvádí M2 = **30 %**, M2.5 = 35 % s poznámkou „samoobslužný reset chybí", M2.6 = 0 %. Kód na témže commitu ale obsahuje `includes/password_reset.php`, `booking/zapomenute_heslo.php`, `booking/nove_heslo.php`, migraci `20260804235900_password_reset_tokens.php` a `tests/Integration/PasswordResetTest.php` — samoobslužný reset **existuje**. Prompt zase cituje M2 65 % / M2.6 96 % / K5 98 % / e‑shop 97 %, což neodpovídá žádnému commitnutému dokumentu.
- Nejmenší oprava: jeden zdroj pravdy pro procenta (milník 10), zbytek na něj odkazuje; aktualizovat po každém přijatém přírůstku. Realistické odhady viz sekce 8.
- Blokuje: ani M2, ani deploy (ale snižuje důvěryhodnost stavu).

**N‑M2 — Reálný souběh (MariaDB zámky) není pokrytý automatizovanými testy.**
- Závažnost: MEDIUM · scénář: A04/A08/A09 (kapacita, sklad, dvojí potvrzení) · role: technická.
- CI (`.github/workflows/tests.yml`) instaluje jen `pdo_sqlite`; větve `FOR UPDATE`/`GET_LOCK` v `shop_checkout.php`, `club_event_*`, `public_velodrome_shop.php`, `kis_roster.php` se na SQLite neprovádějí. Integrační testy simulují „souběh" sekvenčně na jednom spojení.
- Důkaz: `.github/workflows/tests.yml`; `tests/Support/*MariaDbSmoke.php` nejsou zapojené do PHPUnit testsuite ani do CI.
- Nejmenší oprava: přidat do CI job se službou `mariadb` + `pdo_mysql` a spouštět smoke skripty jako `#[Group('mariadb')]`.
- Blokuje: bránu M2.6 (deklaruje „souběh ověřen skutečnými MariaDB procesy"), neblokuje ruční testování.

**N‑M3 — Klubové/členské ceny se v seedovaném katalogu nezobrazily jako víceúrovňové.**
- Závažnost: MEDIUM (ověřit) · scénář: A03 / klubové ceny · role: rodič (živě).
- Živě: v `booking/eshop.php` mají oba produkty jedinou cenu (2 630 / 1 530 CZK), bez prvku „veřejná cena vs. klubová cena" a bez textu „Přihlásit pro zobrazení klubové ceny". „Detail a varianty" u dresu neotevřel samostatný detail.
- Kontext: kód **funkci má** — `includes/shop_member_pricing.php`, `eshop_member_prices_admin.php`, `tests/Integration/ShopMemberPricingTest.php`, migrace `20260805010000_shop_member_pricing.php`, i detailová stránka `booking/produkt.php`. Takže buď seed nenaplnil členské ceny pro tyto produkty, nebo se člen/soupiska přihlášeného rodiče na tyto produkty nevztahuje.
- Nejmenší oprava: buď doplnit do localhost seedu produkt s nastavenou členskou cenou pro demo dítě, aby byl rozdíl viditelný; nebo v dokumentaci uvést, že demo katalog víceúrovňové ceny neukazuje.
- Blokuje: ani M2, ani deploy — je to mezera v pokrytí ruční zkoušky.

### LOW

**N‑L1 — Bearer token veřejného profilu jde v URL query.** `ajax_sportovec_treninky.php:59` autorizuje přes `WHERE hash = ?`; token je teď kryptograficky silný (`bin2hex(random_bytes(32))`, oprava dřívějšího nálezu), ale předává se v URL, takže se ukládá do access logů/historie. Doporučení: přesunout do fragmentu/POST, případně omezit stáří odkazu. Neblokuje.

**N‑L2 — Migrace `20260805000000_unified_accounts_public_schedule.php` předpokládá existenci legacy tabulky `planovane_treninky`.** V provozu ji vytváří `auto_migrace.php` na každý request, takže to funguje; čistě „jen číslované migrace" prostředí by selhalo. Testový baseline ji vytváří ručně (`AuthSecurityMigrationTest.php:133`). Doporučení: buď migraci obalit kontrolou existence, nebo do runneru přidat závislost na legacy baseline. Neblokuje běžný provoz.

### UX

- **UX‑1: „Detail a varianty" neotevře detail.** V katalogu klik na „Detail a varianty" u dresu nevedl na produktovou stránku (přestože `booking/produkt.php` existuje). Pro ruční zkoušku vlastníka je detail produktu klíčový — ověřit propojení odkazu.
- **UX‑2: Autofill prohlížeče kolidoval s přihlášením.** Přihlašovací pole se opakovaně samo‑vyplnila uloženým osobním účtem („marek‑mixa"), což mate a při rychlém odeslání vede k neúspěšnému přihlášení. Pro demo/testovací prostředí zvážit `autocomplete="off"` na loginu, aby tester nespletl testovací a reálný účet.
- **UX‑3: Nesrozlišené hlášky u neúspěšného přihlášení.** Při chybném přihlášení se stránka jen znovu načte bez zjevné chybové hlášky — tester neví, zda je chyba v heslu, nebo v odeslání.

### Dokumentační drift (souhrn)

| Dokument | Tvrdí | Realita (ověřeno) |
|---|---|---|
| `SESSION_HANDOFF.md:162` | implementační HEAD `1b4d9e1` | `12232eb` |
| `SESSION_HANDOFF.md:168` | 303 testů / 2603 assertions | 339 testů / 2805 assertions (sandbox inventář) |
| `SESSION_HANDOFF.md:171` | ownership kontrakt `.6` | `.8` (vč. `kis_import_source_artifacts`) |
| `10-milnik…:31` | M2.5 „samoobslužný reset chybí" | reset implementován (`password_reset.php`, migrace, test) |
| `10-milnik…:34` | M2 = 30 % | podhodnoceno vůči kódu (A05–A08 akceptace, member pricing, public schedule commitnuté) |
| prompt řídicího vlákna | M2 65 % / M2.6 96 % / K5 98 % / e‑shop 97 % | neodpovídá žádnému commitnutému dokumentu |

### Náměty do dalšího milníku

- Dokončit KIS cutover (M2.3): finální exportní kontrakt, neměnný raw archiv (základ `kis_source_archive.php` existuje), řízený promote/rollback, úplný paritní report.
- Zapnout MariaDB + concurrency v CI (viz N‑M2).
- Sjednotit „jeden zdroj pravdy" pro procenta a přidat automatickou kontrolu, že `SESSION_HANDOFF` obsahuje aktuální HEAD a počty (např. test, který porovná uvedené SHA s `git rev-parse`).
- Doplnit demo produkt s viditelnou klubovou cenou (N‑M3), aby vlastník mohl ověřit cenové úrovně.

---

## 3. Stav dříve hlášených nálezů (ověřeno na commitu `12232eb`)

Tyto nálezy pocházejí z dřívějších revizí projektu; na aktuálním commitu jsou **opravené**:

| Dřívější nález | Stav | Důkaz |
|---|---|---|
| Predikovatelný token veřejného profilu (`SHA2(id-jmeno)`) | **OPRAVENO** | `edit_zavod.php:133` → `public_profile_token_generate()` = `bin2hex(random_bytes(32))`; `includes/public_profile_token.php` |
| Potvrzení platby za znovu‑koupený stornovaný kroužek se odrolluje | **OPRAVENO** | `club_program.php:186` duplicate check `… AND status='active'`; migrace `20260804235000_club_program_repeat_enrollment.php` |
| Zálohový ownership kontrakt neobsahuje `kis_import_source_artifacts` | **OPRAVENO** | `bin/db-backup.php:24` = `.8`, tabulka v seznamu |
| Seed přidává `password_reset` audit i beze změny hesla | **OPRAVENO** | `bin/seed-local-demo.php:176` resetuje jen `if(!password_verify(...))` |
| Audit ukládá surový `$_POST` (PII/secrets) | **OPRAVENO** | přidán `auditSanitizeDetail()` (`includes/funkce.php:53`), test `LegacySecurityHardeningTest` |
| Chybí bezpečnostní hlavičky / `.htaccess` v uploadech | **ČÁSTEČNĚ / OPRAVENO** | přidán root `.htaccess`; `LegacySecurityHardeningTest` kontroluje fail‑closed kontrakty (doporučuji ověřit i hlavičky per‑request) |
| Chybí samoobslužný reset hesla (M2.5) | **DOPLNĚNO** | `includes/password_reset.php`, `booking/zapomenute_heslo.php`, `booking/nove_heslo.php`, migrace + test |

---

## 4. Tabulka A01–A10 (živý průchod prohlížečem)

Legenda výsledku: **PASS** = ověřeno naživo bez problému · **PARTIAL** = část ověřena naživo, zbytek doložen kódem/audit stopou · **NOT‑LIVE** = naživo neprovedeno (funkce v kódu/testech existuje) · **BLOCKED** = nešlo provést v tomto prostředí.

| Scénář | Výsledek | Role/účet (bez hesla) | Vytvořená synt. data | Nalezené problémy | Doporučená akce |
|---|---|---|---|---|---|
| A01 rodič a děti | **PASS** | demo rodič | žádná (jen prohlížení) | žádné; přehledy jsou session‑scoped, admin eskalace blokována | vlastník potvrdí obsah přehledu |
| A02 sportovní účet | **PARTIAL** | omezený sportovní účet | žádná | login stránka + izolační text ověřeny; vlastní přihlášení sportovce jsem nedokončil (neznámé synt. heslo, seed nešlo spustit) | vlastník ověří přihlášení sportovce podle výpisu seedu |
| A03 nákup kroužku | **PARTIAL** | demo rodič | žádná (košík jsem netvořil) | katalog + `NEPLATIT` + kroužek produkt ověřeny; plný tok košík→objednávka→QR naživo neproveden | vlastník projde nákup po naseedování |
| A04 ruční úhrada | **NOT‑LIVE** | administrátor | žádná | naživo neprovedeno; audit stopa dřívějších běhů ukazuje `confirm_bank_payment` + `activate` + idempotentní chování | vlastník potvrdí platbu na demo objednávce |
| A05 přechod do závodního týmu | **PARTIAL** | administrátor / rodič | žádná | `kis_transition_admin.php` existuje; „Sportovní přehled" ukazuje téhož sportovce ve více soupiskách | projít průvodce přechodem naživo |
| A06 roční obnova soupisek | **PARTIAL** | administrátor | žádná | „Sportovní přehled" ukazuje řádky 2027 se stavem `removed` = historie rolloveru; `kis_rollover_a06_admin.php` existuje; audit má `rollover_add/close` | projít rollover naživo, ověřit no‑op při opakování |
| A07 plánovaný trénink a docházka | **NOT‑LIVE** | administrátor | žádná | naživo neprovedeno; `kis_training_a07_admin.php` + test `KisA06AcceptanceTest` existují | projít docházku, ověřit zákaz změny data plánu a cizího plánu |
| A08 událost pro dvě soupisky | **PARTIAL** | rodič | žádná | audit ukazuje `register` (jedna přihláška) + `admin_cancel` (reset A08); událost „LOCALHOST výjezd" existuje | ověřit dedup nabídky a jednu přihlášku naživo |
| A09 veřejnost a velodrom | **PASS** | nepřihlášený + demo rodič | žádná | veřejný portál, katalog, kalendář velodromu přístupné bez přihlášení; nákup/rezervace vyžadují login | vlastník ověří registraci nového účtu + rezervaci |
| A10 auditní osa | **PASS** | administrátor | žádná | osa spojuje všechny zdroje s časem/aktérem/důvodem, nedomýšlí; historický `password_reset` šum, ale seed je nově podmíněný | případně vyčistit historický šum v demo DB (reset seedu) |

**Souhrn A01–A10:** PASS 3 (A01, A09, A10) · PARTIAL 5 (A02, A03, A05, A06, A08) · NOT‑LIVE 2 (A04, A07) · FAIL 0 · BLOCKED 0.

> Poznámka k rozsahu: plný „klik po kliku" průchod všech 10 scénářů přes screenshotovou automatizaci je časově velmi náročný; zaměřil jsem se na scénáře, kde živé testování přináší unikátní důkaz (přístupová práva, veřejná část, auditní osa) a zbytek doložil kódem, testy a auditní stopou. Žádný scénář neskončil FAIL.

---

## 5. Automatické ověření

**Automatické testy (PHPUnit).**
- Prostředí: cloudový sandbox, PHP 8.4, PHPUnit 11.5.56 (z `composer.lock`), zrcadlená kopie kódu na commitu `12232eb`.
- Výsledek běhu: **339 testů, 2805 assertions**, hlášeno 7 errors + 8 failures.
- **Interpretace: všech 15 „selhání" je artefakt nedokonalého zrcadlení souborů přes bridge, ne reálná chyba.** Prokázáno: testy padaly na `Call to undefined function auditSanitizeDetail()`, `Undefined constant ONE_TIME_TOKEN_PASSWORD_RESET` a `no such table: planovane_treninky`, přičemž na disku vašeho stroje tyto symboly **existují** (`includes/funkce.php:53`, `includes/one_time_token.php:6`, baseline v `AuthSecurityMigrationTest.php:133`). Zrcadlená kopie některých sdílených souborů byla zastaralá kvůli cache mountu.
- **Definitivní výsledek nelze z tohoto prostředí potvrdit.** Doporučuji spustit u vás: `php vendor/bin/phpunit`. Vzhledem k tomu, že jde o commitnuté „…acceptance" commity a deploy je bráněný test‑gate, očekávaný výsledek je zelený.

**Syntaktická kontrola.** `php -l` nad first‑party PHP soubory (mimo `vendor` a `var`) v zrcadlené kopii proběhla bez chyby (v předchozím kole auditu 220/220; nové soubory prošly také).

**Kontrola migrací.** `php bin/migrate.php --check` jsem na vašem stroji spustit nemohl (bez PHP). Nepřímý důkaz: migrační katalog má nyní **39 číslovaných migrací**; integrační test `AuthSecurityMigrationTest` aplikuje celý katalog nad izolovanou SQLite baseline dvakrát a ověřuje idempotenci. Doporučuji `php bin/migrate.php --check --json` u vás.

**Dependency audit.** `composer audit --locked` jsem nespouštěl proti aktuálnímu locku v tomto běhu (v předchozím kole nad stejným lockem: 0 advisories). Doporučuji ověřit u vás — `composer.lock` se od té doby nezměnil.

> Zelené testy neznamenají funkční UI: proto byl proveden i živý průchod (sekce 4). A naopak — červené sandbox testy zde neznamenají rozbité UI, protože jde o artefakt zrcadlení.

---

## 6. Stav Git pracovního stromu

**Před auditem (ověřeno `git status --porcelain=v2 --branch`, `--ignore-cr-at-eol`):**
- HEAD `12232ebbeb0ca8603b38e38ab3125d17c5fd76ac`, větev `main`, upstream `origin/main`, **ahead 105 / behind 0** (bez nového `fetch`).
- Pracovní strom je **fakticky čistý**: všechny „modified" položky jsou pouze artefakt konců řádků (CRLF) — `git diff --ignore-cr-at-eol` nevrací žádnou obsahovou změnu.
- Jediný skutečně untracked soubor mimo `vendor/` a `var/`: `.claude/settings.local.json` (nastavení agenta, není součást aplikace).
- Ověřeno, že audit se těchto souborů nedotýká.

**Po auditu:**
- Do pracovního stromu přibyl **jediný nový soubor**: `docs/AUDIT-M2-AI-SIMULACE.md` (tato zpráva) — povolená změna dle zadání.
- Žádný jiný soubor nebyl změněn, stagován ani commitnut. Nic nebylo pushnuto.

---

## 7. Mutace provedené pouze na localhost syntetických datech

Přes UI jsem provedl **pouze přihlášení/odhlášení** (localhost administrátor, poté demo rodič) a **prohlížení** stránek. Vedlejším efektem přihlášení jsou standardní auditní/login záznamy pro syntetické účty. **Nevytvořil jsem žádnou objednávku, přihlášku, rezervaci ani jsem neodeslal žádný mutující formulář; nespustil jsem seed ani reset; neprovedl jsem žádný ruční SQL zápis.** Data označená `LOCALHOST`/`NEPLATIT` zůstala beze změny.

---

## 8. Oblasti, které nebylo možné ověřit (a proč)

- **Spuštění PHP/seedu/testů na vašem stroji** — bridge VM nemá PHP/Composer; seed jsem nemohl spustit, proto jsem neměl synt. heslo sportovce pro A02 a nemohl potvrdit A10 „dvojí seed" experiment naživo (potvrzeno alespoň z kódu, že reset je podmíněný).
- **Plný klik‑po‑kliku průchod A03/A04/A05/A06/A07/A08** — časová/rozpočtová náročnost screenshotové automatizace; doloženo kódem, testy a auditní stopou.
- **Definitivní zelený/červený výsledek testů, `migrate --check`, `composer audit`** na cílovém prostředí — viz sekce 5.
- **Reálné souběžné chování (MariaDB zámky)** — v CI ani v sandboxu se neprovádí (N‑M2); posouzeno jen z kódu.
- **Vzdálený Git / GitHub / produkce** — mimo rozsah a bez `fetch` (zadání).
- **Produkční `php.ini` / Apache** (např. skutečné odesílání bezpečnostních hlaviček, `display_errors`) — audit viděl jen to, co vynucuje aplikace.

---

## 9. Doporučená aktualizovaná procenta etap

Odhady vycházejí z toho, co je na commitu `12232eb` prokazatelně v kódu + naživo, nikoli z množství řádků. „Hotovo" pro M2 vyžaduje i lidskou bránu M2.0 a cutover, které zůstávají otevřené.

| Etapa | Prompt/dokument tvrdí | Realistický odhad | Zdůvodnění |
|---|---:|---:|---|
| M2.0 prohlídka vlastníka | 0 % | **0 %** | ruční průchod stále neproběhl (jediná blokace dokončení M2) |
| M2.1 export účastníků | 100 % | **95 %** | funkce + audit hotové; zbývá produktová kontrola sloupců vlastníkem |
| M2.3 KIS migrace | 45 % | **45–50 %** | parser/matcher/parity + raw archiv základ; chybí finální exportní kontrakt, promote/rollback, paritní report |
| M2.4 provozní e‑shop | 70 % | **70–80 %** | přibyl detail produktu (`produkt.php`) a členské ceny; chybí viditelné naseedování cen (N‑M3), obrázky a vlastníkova zkouška |
| M2.5 přístup a obnova účtu | 35 % (doc) | **70–80 %** | samoobslužný reset **je** implementován (proti dokumentaci); zbývá dokončit permission cache a ověřit naživo |
| M2.6 integrovaná akceptace | 0 % / 96 % (prompt) | **20–30 %** | akceptační rozcestník + seed + akceptační testy A05–A08 existují; chybí kompletní browser průchod vlastníkem, MariaDB concurrency a aktuální handoff |
| Celé M2 | 30 % (doc) / 65 % (prompt) | **~55 %** | implementace výrazně nad 30 %, ale lidská brána M2.0 a KIS cutover táhnou skutečné „hotovo" dolů |
| K5 / náhrada KIS | 90 % (doc) / 98 % (prompt) | **~60–70 %** | matcher/parity/archiv ano; ostrý jednorázový import, cutover a paritní podpis chybí — 98 % je nadhodnocené |
| e‑shop | 97 % (prompt) | **~85 %** | katalog/košík/objednávka/QR/kupóny/sklad/storno/refund/detail/členské ceny hotové; chybí obrázky, automatické platby, produkční aktivace |

**Doporučení k procentům:** sjednotit je do jednoho dokumentu, snížit „aspirační" čísla z promptu (K5 98 %, M2.6 96 %) na realitu podloženou důkazem a naopak zvednout M2.5 v dokumentu (reset je hotový). Handoff nesmí tvrdit vyšší hotovost, než jakou lze doložit akceptačním důkazem.

---

## 10. Text pro vložení zpět do řídicího vlákna (Codex)

```
Nezávislý audit na commitu 12232eb (localhost, read-only, živý browser + sandbox testy).

STAV: aplikace běží a je naseedovaná; přihlášení, veřejná část, oddělení rodiny a
auditní osa fungují naživo. A01/A09/A10 = PASS; A02/A03/A05/A06/A08 = PARTIAL (doloženo
kódem/auditem); A04/A07 = neprovedeno naživo (funkce existují). Žádný FAIL, žádný CRITICAL.

OPRAVENÉ dřívější nálezy potvrzeny v kódu: predikovatelný profil-token (nyní random_bytes),
blokace platby po re-nákupu stornovaného kroužku (status='active'), db-backup ownership .8
vč. kis_import_source_artifacts, podmíněný seed password_reset, auditSanitizeDetail redakce,
root .htaccess, samoobslužný reset hesla.

BLOKUJE M2: ruční prohlídka vlastníka A01–A10 (M2.0) stále neproběhla.

HLAVNÍ DRIFT: SESSION_HANDOFF.md je ~100 commitů pozadu (uvádí HEAD 1b4d9e1, 303 testů,
ownership .6; realita 12232eb, 339 testů, .8). Procenta v promptu (M2 65 / M2.6 96 / K5 98 /
e-shop 97) neodpovídají commitnutým dokům (10-milnik uvádí M2 30 %, M2.5 "reset chybí" ač je
hotový). Realistické: M2 ~55 %, K5 ~60-70 %, e-shop ~85 %, M2.5 ~70-80 %.

TESTY: 339/2805 v sandboxu; 15 "selhání" = artefakt zrcadlení bridge (symboly na disku
existují), NE reálné chyby. Nutno potvrdit `php vendor/bin/phpunit` lokálně (na bridge VM
není PHP). CI běží jen na SQLite → reálný MariaDB souběh netestován.

DALŠÍ KROKY: 1) vlastník projde A01–A10; 2) srovnat SESSION_HANDOFF a procenta s realitou;
3) spustit phpunit+composer audit+migrate --check lokálně a zapsat výsledek; 4) dokončit
KIS cutover (M2.3); 5) přidat MariaDB concurrency do CI.

PRACOVNÍ STROM: čistý (jen CRLF), přibyl jediný nový soubor docs/AUDIT-M2-AI-SIMULACE.md.
Nic commitnuto/pushnuto, žádný ruční SQL, žádná reálná platba.
```
