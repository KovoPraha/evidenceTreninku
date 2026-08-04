# AUDIT M2 — druhý průchod (re-audit)

> **Aktuální validační dodatek (4. 8. 2026):** Zpráva je historický snapshot
> commitu `cd38f85`. N-M1 byl opraven v `7c8b444` auditovaným přenosem členských
> předpisů a bezpečným rollbackem. N-H1 i N-L1 byly opraveny v `281fcd0`:
> zálohovací ownership kontrakt `.9` pokrývá všech 12 nových tabulek, obecná
> kontrola hlídá úplnost proti migracím a skutečný izolovaný MariaDB smoke ověřil
> zálohu 90 tabulek. Dokument `b7d1cef` zaznamenává ověřovací důkazy. Původní
> nálezy níže zůstávají zachované kvůli dohledatelnosti, nikoli jako seznam
> současně otevřených závad.

**Projekt:** Evidence tréninků + e‑shop + KIS
**Commit v době re‑auditu (ověřeno živě):** `cd38f85` „Record M2.3f member charge staging" (poslední implementační `d69ee4f`)
**Datum:** 2026‑08‑04, Europe/Prague
**Charakter:** read‑only. Žádná změna kódu/dokumentace/DB, žádný commit/push, žádná reálná platba. Jediný nový soubor je tato zpráva.
**Navazuje na:** `docs/AUDIT-M2-AI-SIMULACE.md` (první průchod na commitu `12232eb`).

> **Metodická poznámka (důležitá).** Bridge do vašeho počítače běží v Linux VM **bez PHP/Composeru**, takže testy a seed na vašem stroji spustit nelze; zrcadlená kopie kódu přes bridge navíc občas vrací **zastaralý obsah** (to potvrzuje i váš nový `docs/CURRENT_STATE.md`). Proto jsem každý nález ověřoval **přímo na disku přes `git`/`grep`**, ne přes zrcadlenou kopii. Dva „nálezy" z pomocné analýzy, které vycházely ze zastaralé kopie, jsem **zahodil** (viz sekce 5).

---

## 1. Co se změnilo od prvního auditu

Repozitář postoupil z `12232eb` na `cd38f85` (mezitím i `origin/main` dostal většinu práce — lokální `main` je nyní ahead 10, dříve 105). Přibyla celá vrstva **M2.3 „KIS cutover"**:

- 5 nových migrací (celkem **44**): preview integrity, sandbox promotion, field contract, parity report, member charge target.
- Nové knihovny: `kis_import_field_contract.php`, `kis_import_parity_report.php`, `kis_import_sandbox_promotion.php`, `member_charge.php`, `localhost_acceptance_feedback.php`.
- Nový CLI seed `bin/seed-kis-m23-preview.php`, přepracovaný `kis_sync_center.php`.
- Nový doc `docs/CURRENT_STATE.md` + aktualizované `SESSION_HANDOFF.md`, `10‑milnik`, `06‑board`, `kis-parity-v1.md`.
- Nový **MariaDB smoke job v CI** (`mariadb:11.4`, `pdo_mysql`).

---

## 2. Manažerské shrnutí re‑auditu

**Dobrá zpráva — dvě věci z minula jsou vyřešené:**

1. **Dokumentační drift je z velké části opravený.** `SESSION_HANDOFF.md` teď uvádí aktuální HEAD (`d69ee4f`) i aktuální počty (388 testů / 3369 assertions). Přibyl `CURRENT_STATE.md` s poctivými čísly (M2 76 %, M2.3 98 %) a s výslovným upozorněním, že „Cowork bridge kopie může být zastaralá; nepoužívej ji jako důkaz proti skutečnému lokálnímu Gitu." To je přesně správný krok.
2. **Bezpečnostní jádro nové vrstvy M2.3 je v pořádku.** Nejdůležitější invariant — že „zkušební migrace KIS" zapisuje **jen do izolovaných sandbox tabulek** a nikdy do ostrých doménových dat — v kódu **drží** (ověřeno). Paritní report je read‑only, field contract je fail‑closed, částky předpisů jsou v celočíselných haléřích a nevzniká žádná skutečná platba.

**Špatná zpráva — jeden nový, aktivní a tichý problem:**

- **Zálohování databáze se po nasazení M2.3 migrací na MariaDB rozbije.** Šest nových tabulek (včetně finančních `club_member_charges` a `club_member_charge_events`) není zapsáno do „ownership" seznamu v `bin/db-backup.php`, přitom mají cizí klíče do vlastněných tabulek. Kontrola hranic v záloze proto vyhodí výjimku a **záloha se vůbec nevytvoří**. V CI se to neprojeví (test kontroluje jen text souboru, ne skutečný běh zálohy). Detail = nález **N‑H1** níže.

**Je M2 připravené k ruční prohlídce vlastníka?** Ano (beze změny oproti minule). **Je připravené na produkci?** Ne — a nově navíc platí, že **před produkcí je nutné opravit zálohu**, protože předdeployová záloha je fail‑closed brána, kterou tento problém zablokuje.

**Pět dalších kroků:**
1. Opravit `bin/db-backup.php` (N‑H1) — doplnit 6 tabulek + bumpnout ownership verzi + přidat je do `DeployWorkflowContractTest`.
2. Vlastník projde A01–A10 (stále jediná lidská brána M2.0/M2.6).
3. Doplnit test, který skutečně spustí `db-backup` proti MariaDB po všech migracích (dnes chybí).
4. Dokončit M2.3: napojit staging jednotlivých předpisů na `club_member_charges` (cílový model existuje, wiring do finální tabulky ještě ne) a získat reprezentativní anonymizovaný KIS export.
5. Snížit `member_charge` paritu peněz z float na integer end‑to‑end (N‑L1).

---

## 3. Nálezy

### HIGH

**N‑H1 — Po aplikaci M2.3 migrací na MariaDB `bin/db-backup.php` selže a nevytvoří žádnou zálohu.**
- Závažnost: HIGH · scénář: provoz/záloha/deploy · role: provozovatel.
- Ověřeno přímo na disku:
  - `bin/db-backup.php:24` = `EVIDENCE_OWNERSHIP_CONTRACT_VERSION = '2026-08-04.8'`; seznam `EVIDENCE_TABLES` má 133 položek a **neobsahuje** žádnou z 6 nových: `club_member_charges`, `club_member_charge_events`, `kis_import_payment_rows`, `kis_import_sandbox_events`, `kis_import_sandbox_items`, `kis_import_sandbox_promotions`.
  - Nové tabulky mají cizí klíče do vlastněných tabulek — např. `migrations/20260804234950_member_charge_target.php:37‑39`:
    ```php
    CONSTRAINT fk_member_charge_sportovec FOREIGN KEY(sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,
    CONSTRAINT fk_member_charge_payer     FOREIGN KEY(payer_account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT,
    CONSTRAINT fk_member_charge_import    FOREIGN KEY(source_import_run_id) REFERENCES kis_import_runs(id) ON DELETE RESTRICT
    ```
  - `bin/db-backup.php:358‑380` — hraniční kontrola najde FK, kde nevlastněná tabulka odkazuje na vlastněnou, a **vyhodí výjimku**:
    ```php
    if ($crossBoundaryFk) {
        throw new RuntimeException('Cizí klíč překračuje hranici Evidence: '
            . $crossBoundaryFk['TABLE_NAME'] . ' -> ' . $crossBoundaryFk['REFERENCED_TABLE_NAME'] . '.');
    }
    ```
- Reálný scénář selhání: na vašem localhostu (MariaDB, migrace 44/44 aplikované, běh #12 existuje) `php bin/db-backup.php` skončí chybou `Cizí klíč překračuje hranici Evidence: club_member_charges -> sportovci.` — a **nevznikne žádná záloha celé DB**, nejen těch nových tabulek. Protože záloha je fail‑closed brána před `migrate`/`activate`, zablokuje se i budoucí deploy.
- Proč je to tiché: `tests/Unit/DeployWorkflowContractTest.php` kontroluje jen **text** `db-backup.php` (verzi `.8` a přítomnost `kis_import_source_artifacts`), ne skutečný běh; nové tabulky nevyjmenovává. Nový MariaDB CI job `db-backup` vůbec nespouští. Kontrola tedy zůstává zelená.
- Soubor:řádek: `bin/db-backup.php:24,358‑380`; `migrations/20260804234950_member_charge_target.php:37‑39,61‑63,81,95,117‑118`.
- Nejmenší oprava: doplnit všech 6 tabulek do `EVIDENCE_TABLES` (abecedně), bumpnout `EVIDENCE_OWNERSHIP_CONTRACT_VERSION` (`.8` → `.9`) a přidat je do `DeployWorkflowContractTest`.
- Regresní test: test, který proti MariaDB po aplikaci **všech** migrací spustí `db-backup` (nebo aspoň jeho FK‑boundary dotaz) a ověří, že neselže — tj. každá tabulka s FK do vlastněné tabulky je vlastněná. (Ideální doplnit do `mariadb-smoke`.)
- Blokuje: produkci (předdeployová záloha) a běžné zálohování. Neblokuje ruční testování vlastníka.
- Pozn.: Do těchto tabulek v revidovaném kódu zatím reálně nic doménového nezapisuje (finální staging do `club_member_charges` ještě není napojený), takže dnes nehrozí ztráta reálných finančních dat obnovou — problém je **rozbitá záloha jako taková** a latentní riziko, jakmile se staging napojí.

### MEDIUM

**N‑M1 — Cílový model členských předpisů zatím není napojený na finální tabulku (M2.3 „staging", ne „promote do ostrého").**
- `includes/member_charge.php:55‑80` (`memberChargeProjection`) produkuje jen `source_*`, `status`, `amount_minor`, `outstanding_minor`, `currency`, `due_on`, `paid_on`; cílová `club_member_charges` má ale `public_code NOT NULL UNIQUE`, `charge_type NOT NULL`, `title_snapshot NOT NULL` (`migrations/20260804234950:19‑21,33`). Skutečný INSERT do `club_member_charges` v revidovaném kódu není. To odpovídá deklaraci (M2.3 je zkouška/staging), ale znamená to, že „98 % M2.3" zahrnuje ještě nedokončené napojení na cílovou tabulku.
- Nejmenší oprava/akce: buď dopsat staging INSERT s doplněním NOT NULL sloupců, nebo v `CURRENT_STATE`/`10‑milnik` explicitně uvést, že finální zápis do `club_member_charges` je zbývající krok.
- Blokuje: ne.

### LOW

**N‑L1 — Parita porovnává peníze přes float.** `includes/kis_import_parity_report.php:17‑20` — `kisImportParityMoneyMinor` dělá `(int)round((float)$value*100)` nad REAL sloupcem `kis_neuhrazeno`. Obě strany používají stejnou funkci, takže riziko divergence je nízké, ale doporučuji nést haléře jako integer end‑to‑end (jako už dělá staging `amount_minor`). Neblokuje.

**N‑L2 — Migrace M2.3 předpokládají existenci legacy tabulek (`sportovci`, `verejni_uzivatele`, `kis_import_*`).** V provozu je vytváří `auto_migrace.php` na každý request, takže to funguje; „jen číslované migrace" prostředí by bez legacy baseline selhalo. Stejná poznámka jako v prvním auditu. Neblokuje běžný provoz.

---

## 4. Co je ověřeno jako v pořádku (nová M2.3 vrstva)

- **Izolace sandboxu (kritický invariant M2.3) DRŽÍ.** V celém M2.3 kódu není jediný `INSERT/UPDATE/DELETE` do ostrých doménových tabulek (`sportovci`, `club_*`, `shop_*`, `verejni_uzivatele`). Promote/rollback zapisuje výhradně do `kis_import_sandbox_promotions|items|events`; `finalize` dělá jen `UPDATE kis_import_runs` (metadata běhu); `member_charge.php` a `kis_import_field_contract.php` do DB nezapisují vůbec.
- **Idempotence / právě jednou.** `UNIQUE(import_run_id, preview_fingerprint)`, větev „applied → vrať `idempotent=true`", reapply z `rolled_back`, `FOR UPDATE` na MySQL, transakce/savepoint s rollbackem; field/parity fingerprint = hash kanonického JSON.
- **Paritní report je read‑only** vůči doménovým datům (jen SELECT nad `sportovci`).
- **Field contract je fail‑closed** — neznámé/chybějící hlavičky, neplatné/duplicitní/konfliktní external ID → `blocked`, žádné tiché přijetí; normalizace přes whitelist regex.
- **Member charge (finance) je konzervativní** — částky integer haléře, ISO měna (`^[A-Z]{3}$`), `payment_separate` (nevzniká skutečná platba), konzistenční kontroly (`outstanding ≤ amount`, `paid ⇒ outstanding=0` a datum úhrady).
- **SQL** je parametrizované; dynamické fragmenty jen int‑cast; `db-backup` identifikátory přes whitelist.
- **Migrace** mají per‑DDL guardy a obě větve ovladače; testy je aplikují dvakrát a ověřují `verify()`.

*(Ověřeno čtením kódu na disku; kritický invariant izolace potvrzen i grepem přes celou M2.3 vrstvu.)*

---

## 5. Zahozené „nálezy" z pomocné analýzy (byly to artefakty zastaralé bridge kopie)

Abych nešířil falešné poplachy, tyto dvě věci jsem **ověřil proti disku a zamítl**:

- „Chybí implementace funkcí `kisImportBuildPreviewReport`, `kisImportStoredPreviewReport`, `kisImportFinalizePreview`, `kisImportTableExists`." → **Nepravda.** Všechny čtyři jsou definované v `includes/kis_import_run_lib.php` (ověřeno `grep` na disku). Pomocná analýza četla zastaralou zrcadlenou kopii.
- „Ownership verze je `.6`, test čeká `.7`." → **Nepravda.** Na disku je `bin/db-backup.php` = `.8` a `DeployWorkflowContractTest` čeká `.8` (shodné, test na verzi prochází). Opět artefakt zastaralé kopie.

Reálný a potvrzený problém je pouze N‑H1 (chybějící tabulky v seznamu + throw v hraniční kontrole).

---

## 6. Stav dříve hlášených nálezů

| Nález (1. audit / dřívější) | Stav na `cd38f85` |
|---|---|
| Dokumentační drift `SESSION_HANDOFF` (stará HEAD/testy) | **VYŘEŠENO** — handoff uvádí `d69ee4f`, 388/3369; přidán `CURRENT_STATE.md` |
| Procenta nekonzistentní / zastaralá | **VYŘEŠENO/ZLEPŠENO** — `CURRENT_STATE.md` sjednocuje (M2 76 %, M2.3 98 %, M2.6 98 %, K5 98 %, e‑shop 97 %) a varuje před bridge kopií |
| db‑backup ownership neobsahuje `kis_import_source_artifacts` | **VYŘEŠENO** — je v seznamu (`bin/db-backup.php:77`), verze `.8` |
| Predikovatelný profil‑token / re‑nákup kroužku / seed password_reset šum | **VYŘEŠENO** (potvrzeno v 1. auditu, beze změny) |
| Nový: 6 M2.3 tabulek mimo ownership + throw v záloze | **NOVÝ — otevřený (N‑H1)** |

---

## 7. Testy, migrace, git

- **Automatické testy:** dokumentace/handoff uvádí **388 testů / 3369 assertions** (localhost). Nezávislý čistý běh jsem v sandboxu **nezískal** — kvůli zastaralé bridge kopii část souborů chybí/nesouhlasí (stejné omezení jako v 1. auditu; pomocný běh proto nebyl směrodatný). Doporučuji potvrdit `php vendor/bin/phpunit` u vás.
- **Migrace:** katalog **44/44** (5 nových M2.3). `php bin/migrate.php --check` na vašem stroji spustit nelze (bez PHP); integrační testy aplikují katalog nad izolovanou baseline.
- **Dependency audit:** `composer.lock` beze změny od 1. auditu (tehdy 0 advisories); doporučuji `composer audit --locked` u vás.
- **CI:** přibyl `mariadb-smoke` job (`mariadb:11.4`, `pdo_mysql`) — dobrý krok; ale **nespouští `db-backup`**, proto N‑H1 nezachytí.
- **Git:** HEAD `cd38f85`, větev `main`, ahead 10 / behind 0 vůči cache `origin/main` (bez `fetch`). Pracovní strom fakticky čistý (jen CRLF). Untracked mimo `vendor/var`: `.claude/settings.local.json` + tato zpráva. Pozn.: první auditní report `docs/AUDIT-M2-AI-SIMULACE.md` byl mezitím commitnut do repa.

---

## 8. Aktualizovaná procenta (můj realistický odhad vs. `CURRENT_STATE.md`)

| Etapa | CURRENT_STATE tvrdí | Můj realistický odhad | Poznámka |
|---|---:|---:|---|
| Celé M2 | 76 % | **65–70 %** | lidská brána M2.0/M2.6 stále otevřená; N‑H1 sráží provozní připravenost |
| M2.3 KIS migrace | 98 % | **80–85 %** | sandbox/parita/field/archiv hotové a bezpečné, ale finální staging do `club_member_charges` není napojený (N‑M1) a záloha se s M2.3 rozbila (N‑H1) |
| M2.6 akceptace | 98 % | **25–35 %** | technicky připraveno, ale kompletní browser průchod vlastníka neproběhl |
| K5 / náhrada KIS | 98 % | **65–70 %** | ostrý import a cutover chybí (deklarováno); 98 % je optimistické |
| e‑shop | 97 % | **~85 %** | beze změny oproti 1. auditu |

Doporučení k procentům: `98 %` u M2.3 a M2.6 je vzhledem k otevřené bráně vlastníka a k N‑H1 nadhodnocené; navrhuji je mírně snížit a u M2.3 explicitně uvést „finální zápis do `club_member_charges` a oprava zálohy zbývají".

---

## 9. Oblasti, které nebylo možné ověřit (a proč)

- **Spuštění PHP/seed/testů/`db-backup`/`migrate --check` na vašem stroji** — bridge VM nemá PHP. N‑H1 je odvozen deterministicky z kódu; potvrďte prosím `php bin/db-backup.php` lokálně (očekávám výjimku „Cizí klíč překračuje hranici Evidence").
- **Živý browser průchod A01–A10** — v tomto re‑auditu jsem se soustředil na nový M2.3 kód a delta oproti 1. auditu; živé UI výsledky A01/A09/A10 z 1. auditu (PASS) zůstávají v platnosti, nová vrstva M2.3 je administrátorská/CLI a UI se pro ni nezměnilo.
- **Reálný souběh na MariaDB** — nový `mariadb-smoke` job existuje, ale nepokrývá `db-backup`; jinak stejné omezení jako v 1. auditu.

---

## 10. Text pro vložení zpět do řídicího vlákna (Codex)

```
Re-audit na commitu cd38f85 (M2.3 KIS cutover), read-only, ověřeno přímo přes git/grep
(bridge kopie je místy zastaralá, proto jen disk).

VYŘEŠENO od minula: doc drift (SESSION_HANDOFF nyní d69ee4f, 388/3369; přidán CURRENT_STATE.md
s varováním o zastaralé bridge kopii); db-backup ownership .8 vč. kis_import_source_artifacts.

M2.3 BEZPEČNOST OK: izolace sandboxu drží (promote píše jen do kis_import_sandbox_*, parita
read-only, member_charge/field_contract do domény nezapisují); idempotence, fail-closed field
contract, integer haléře, žádná reálná platba.

NOVÝ HIGH (N-H1): 6 nových M2.3 tabulek (vč. finančních club_member_charges/_events) NENÍ v
bin/db-backup.php EVIDENCE_TABLES, ale mají FK do vlastněných tabulek → hraniční kontrola v
db-backup vyhodí výjimku → po aplikaci M2.3 migrací na MariaDB se NEVYTVOŘÍ žádná záloha a
zablokuje se předdeployová brána. Tiché v CI (DeployWorkflowContractTest kontroluje jen text,
mariadb-smoke db-backup nespouští). Oprava: doplnit 6 tabulek + bump verze + assert v testu +
skutečný db-backup test proti MariaDB.

ZAHOZENO jako artefakt zastaralé kopie: "chybí funkce kisImportBuildPreviewReport atd." (jsou
v kis_import_run_lib.php) a "verze .6/.7" (disk je .8).

DALŠÍ: 1) opravit N-H1; 2) vlastník A01–A10; 3) db-backup test na MariaDB; 4) napojit staging
do club_member_charges (N-M1); 5) parita peněz na integer (N-L1).

Realistická procenta: M2 ~65-70 %, M2.3 ~80-85 %, M2.6 ~25-35 %, K5 ~65-70 %, e-shop ~85 %.
Pracovní strom čistý (jen CRLF); přidán jediný nový soubor docs/AUDIT-M2-AI-SIMULACE-RE2.md.
```
