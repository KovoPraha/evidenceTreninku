# Session handoff

Tento soubor je stručný obnovitelný stav řídicího tasku. Architekturu ani
roadmapu neduplikuje; odkazuje na jejich kanonické dokumenty. Všechny provozní
hodnoty jsou historické, dokud je nový řídicí task živě neověří.

## Metadata

- Aktualizováno: 2026-08-03, Europe/Prague
- Repozitář: `C:\xampp\htdocs\evidencePavel`
- Programová brána: F0 – červená
- Aktivní integrační větev: `main`; transakční kupóny před tímto stavovým
  commitem: `135803a` (`Add transactional shop coupons`)
- Auth kódový tip před tímto handoff commitem: `9977b4dfc3f2f6aab775825d0bdf9b629e61e217`;
  auth přírůstek tvoří
  `a3c2239` (revokace + limiter), `10c2cf9` (atomická rezervace + SSO abort) a
  `9977b4d` (HMAC pepper + sjednocené pořadí zámků)
- Původní base: `58ec8ec985d447dfe901481ac8bb24b944b03d08`
- Produkční deploy bez výslovného souhlasu: zakázán
- Produkční DB změny bez výslovného souhlasu: zakázány
- Poslední dokončená akce: K3 má administrační frontu neodeslaných e-mailů a
  auditované bezpečné ruční retry (`4cd0eae`). K4 má první checkout přihlášeného
  účtu pro aktivní `goods`: košík, ochranu proti tiché změně ceny, neměnný
  snapshot, idempotentní objednávku, transakční rezervaci skladu, bankovní
  předpis s lokálním QR/SPD a ruční auditované potvrzení platby (`5750dd0`).
  Navazující `1763531` přidává auditované storno, transakční a právě-jednou
  vrácení skladu, bezpečný stav `refund_required` a tok příprava → připraveno →
  osobní výdej. Celkem 186/1339 testů, 257 PHP lintů, Composer audit bez
  advisories a izolovaný MariaDB průchod dokončením i stornem prošly.
  `f8b12a4` doplňuje databázově vlastnicky filtrovaný zákaznický seznam a detail
  a auditované jednorázové potvrzení celé bankovní vratky `refunded` s referencí.
  Celkem 187/1376 testů, 259 PHP lintů a izolovaný MariaDB tok vratky i IDOR
  hranice prošly. `135803a` přidává neměnné pevné/procentní kupóny, platnost,
  minimum, celkový limit, audit aktivace, serverový checkout a snapshot slevy.
  Celkem 190/1425 testů, 262 PHP lintů a izolovaný MariaDB limit i finanční
  snapshot prošly. `f23a332` přidává read-only Fio import v shadow režimu,
  neměnnou deduplikaci a návrhy podle přesného VS, částky a měny. Návrh nemění
  stav platby; 195/1457 testů, 270 PHP lintů a izolovaný MariaDB smoke prošly.
  Automatické potvrzení platby a Stripe zatím nevznikají.
  Produkční workflow ani lokální/produkční DB se nezměnily
- Další přesná akce: doplnit GitHub Secret `SSH_KNOWN_HOSTS` a produkční
  `AUTH_RATE_LIMIT_PEPPER`, poté provést pouze autorizovaný první release.
  Potvrdit KIS identifikátor a retenci; v K4 následně navrhnout read-only import
  pohybů a bezpečné automatické párování plateb z Fio

## Stav etap podle akceptačních bran

Procenta jsou řídicí odhad dokončených akceptačních bodů, nikoliv podíl řádků
kódu. Produkční aktivace se do nich nepočítá jako hotová bez živého důkazu.

| Etapa | Hotovo | Zbývá zejména |
|---|---:|---|
| K1 – katalog a publikace | 92 % | obrázky/detail produktu, finální produktová pravidla a případný anonymní storefront |
| K2 – účty, osoby a rodič–dítě | 75 % | stabilní KIS identifikátor, bezpečné párování reálného exportu a hraniční review |
| K3 – akce a přihlášky | 88 % | rozhodnutí o ručních změnách čekací listiny, export účastníků a produkční UX |
| K4 – objednávky a platby | 75 % | ověřit Fio shadow návrhy na reálných datech, samostatně schválit automatické potvrzení a následně Stripe |
| K5 – KIS shadow mode a cutover | 15 % | paritní reporty, export změn, řízený shadow provoz a samostatně schválený cutover |

## Pořadí autority

1. živě ověřený Git, lokální DB, GitHub a produkční důkaz,
2. schválená rozhodnutí v [02 – Zadání a rozhodnutí](02-zadani-a-rozhodnuti.md),
3. aktuální stav v [06 – Program board](06-program-board.md),
4. tento handoff,
5. předchozí chat a memory pouze jako vodítko, ne jako aktuální důkaz.

Při rozporu se nejprve zastaví mutace, zaznamená drift a aktualizuje board.

## Poslední známý důkazní snapshot

| Oblast | Poslední známá hodnota | Ověřeno | Zdroj | Obnovit při resume |
|---|---|---|---|---|
| Git remote | `https://github.com/KovoPraha/evidenceTreninku.git` | 2026-08-01 | `git remote -v` | ano |
| `origin/main` | `7f48b50b128b65f7340442ba33bfb9c66c27703a` | 2026-08-02 | fetch + rev-parse | ano |
| integrační branch | K4 zákaznické objednávky/vratky `f8b12a4` + kupóny `135803a` + Fio shadow návrhy `f23a332`; následuje pouze tento stavový commit | 2026-08-03 | Git | ano |
| PR / remote CI | PR #1 až #6 merged; finální main run `30743017895` success | 2026-08-02 | GitHub | ano |
| ochranný snapshot | `d2b3c56` / `codex/pre-reconcile-20260801` | 2026-08-01 | lokální Git | před mazáním větve |
| GitHub deploy | run `30668559417`, success | 2026-08-01 | GitHub CLI | ano |
| produkční runtime | schema `2.20.2`, PHP `8.2.32` | 2026-07-31 | deploy post-check | před releasem |
| lokální schema | `2.20.2` | 2026-08-01 | read-only DB dotaz | ano |
| testy | 195/1457; K4 checkout/fulfillment/refund/vlastnický seznam, kupóny a Fio shadow import mají SQLite testy; izolovaný MariaDB návrh, deduplikace a neměnný `pending` stav prošly; poslední vzdálený main CI důkaz zůstává starší | 2026-08-03 | PHP 8.2.12 / PHPUnit 11.5.56 + lokální MariaDB | ano |
| Shoptet staging | 241 produktů / 807 variant převedeno do draft katalogu; druhé spuštění bez duplicity, 1 bookable rental, 3 free varianty, 0 veřejně aktivních | 2026-08-02 | reálný XML + SQLite/MariaDB | před veřejnou aktivací |
| dependencies | PhpSpreadsheet 5.8.1, Guzzle 7.15.2, PSR-7 2.13.0, endroid/qr-code 6.0.9; 0 advisories | 2026-08-03 | Composer audit | ano |
| lokální backup drill | 59 přítomných tabulek, 1 trigger, checksum OK; restore 253 sportovců / 455 tréninků; kontrakt `2026-08-02.3` obsahuje `auth_login_limits` i lokálně nepřítomné `ucto_gs_*` | 2026-08-02 | izolovaná XAMPP DB + code audit | zopakovat s produkčním artefaktem |
| GitHub host key | Secret `SSH_KNOWN_HOSTS` dosud chybí | 2026-08-01 | pouze seznam názvů Secrets | ano; hodnotu nikdy nevypisovat |

Lokální DB, GitHub run a produkční runtime jsou tři různé zdroje. Výsledek
jednoho se nesmí vydávat za důkaz druhého.

## Povinný resume audit bez mutací

- [ ] přečíst tento soubor, README a dokumenty 02, 04, 05 a 06,
- [ ] spustit `git status --porcelain=v2 --branch`,
- [ ] zaznamenat `git rev-parse HEAD`, upstream, remote URL a výchozí branch,
- [ ] provést `git fetch --prune origin` a porovnat `HEAD...origin/main`,
- [ ] inventarizovat každý modified/untracked soubor a jeho vlastníka,
- [ ] ověřit GitHub repo, workflow a poslední run,
- [ ] ověřovat jen názvy/přítomnost Secrets, nikdy jejich hodnoty,
- [ ] ověřit lokální DB identitu a schema read-only dotazy,
- [ ] označit každý fakt jako lokální, GitHub, produkční nebo neověřený,
- [ ] před změnou stručně nahlásit drift proti handoffu.

Do dokončení inventury nepoužívat pull, stash, clean, reset, rebase, bulk stage
ani jinou operaci, která by mohla skrýt nebo přepsat cizí práci.

## Preservation ledger

| Povrch | Původ | Stav | Povolená akce |
|---|---|---|---|
| starý podrobný deploy manuál | práce před `58ec8ec` | zachován v `d2b3c56`, zastaralý | pouze ručně vytěžit stále platné rady |
| `overit_config.php` | dočasná produkční diagnostika | zachován v `d2b3c56` | neobnovovat a nenasazovat |
| přesné kopie nových deploy souborů | budoucí obsah originu v tehdy starém worktree | nyní kanonicky v `origin/main` | používat upstream verzi |
| programová dokumentace | řídicí task | obnovena na `codex/foundation` | řídicí task je jediný editor boardu/handoffu |

Snapshot branch se nemaže, dokud nebude ručně potvrzeno, že žádná zachovaná rada
nebo soubor už není potřebný. Snapshot není určen k merge ani pushnutí.

## Aktivní práce a vlastnictví

| ID | Stav | Base/branch | Vlastník | Povolený rozsah | Blokuje |
|---|---|---|---|---|---|
| W0-A | accepted | `58ec8ec` | řídicí task | Git reconciliation | nic |
| W0-B | accepted | `7106930` | KIS worker | matcher + integration testy | release do main |
| W0-C | partial accepted | auth commity v `main`, včetně PR #5 | security worker | hesla, dependencies, lifecycle, revokace, limiter, tokeny, logout | permission cache, reset hesla a produkční password apply |
| W0-D | accepted | `0d50584`, run `30718185103` | test worker | Composer dev, tests, CI/deploy gate | nic |
| W0-E | code accepted / production pending | `664745e`, `cd0c0e1`, PR #6 | integrační vlastník | migrace + deploy hardening | `SSH_KNOWN_HOSTS`, produkční pepper, autorizovaný první deploy |
| W0-F | waiting decision | dokumentace | produkt/ekonom | D-004 až D-011 | identity a wallet |
| W0-G | partial accepted | `98ff91d`, `168d132`, `8f0cbe8`, `699ddd4`, `d99a79e`, `2ba0782`, `f0370a3`, `3845eab`, `b77f8c3`, `8c374a4`, `d32fc08`, `5500927`, `4ef5690`, `88f5b97`, `e5fcaa0`, `a949c38`, `fb5e137`, `4cd0eae`, `5750dd0`, `1763531`, `f8b12a4`, `135803a` | KIS/shop workeři | Shoptet katalog, K2, bezplatný K3 a K4 checkout/storno/výdej/refund/kupóny | reálný KIS vzorek a automatické platby |

Řídicí task aktualizuje IDs, větve, commity a testy po každém worker handoffu.

## Integrační fronta

| Pořadí | Práce | Podmínka přijetí |
|---:|---|---|
| 1 | foundation dokumentace `6c7956c` | přijato |
| 2 | W0-D test baseline `0d50584` | přijato; pozdější main CI běhy zelené |
| 3 | W0-B KIS safety `7106930` | přijato; 10 testů / 61 assertions v tomto kroku |
| 4 | W0-C passwords `2ed5278` | přijato; produkční `--apply` výslovně neprovedeno |
| 5 | W0-C dependencies `1a9af03` | přijato; 0 advisories, celkem 16 testů / 75 assertions |
| 6 | W0-E migrations `664745e` | přijato lokálně; 29 testů / 119 assertions |
| 7 | W0-E deploy/backup `cd0c0e1` | přijato lokálně; restore drill prošel, GitHub/produkce čekají |
| 8 | W0-G Shoptet katalog `98ff91d` | přijato lokálně; provisional CSV-only dry-run, 2 produkty / 3 varianty |
| 9 | W0-G KIS parity `0537adf` | přijato lokálně; matcher/preview hardening + syntetický read-only kontrakt |
| 10 | Audit fixes `82eac98`, `b4207be` | přijato lokálně; jedno-snapshot CSV a pouze silné KIS identity signály |
| 11 | W0-G realistic KIS `168d132` | přijato lokálně; 10 opaque scénářů, 9 blockerů, missing nikdy nearchivuje |
| 12 | W0-G shop matrix `8f0cbe8` | přijato lokálně; varianty, kolize SKU, exact money/VAT a scope hranice |
| 13 | W0-C session lifecycle `dfce1ea`, `af49d57` | přijato lokálně; 102 entrypointů, bezpečné cookie/timeout/rotace/logout |
| 14 | W0-C DB revokace + rate limit `a3c2239`, `10c2cf9`, `9977b4d` | přijato; 103/569, MariaDB apply/runtime, dva finální audity ACCEPT a run `30740138748` success |
| 15 | W0-C one-time tokeny + booking lock `4b683ee` | přijato v PR #5; 110/626, SQLite + MariaDB, finální re-audit ACCEPT |
| 16 | W0-E release ordering `7361e48` | přijato v PR #6; 112/647, migrace před aktivací PHP, run `30743017895` success |
| 17 | Shoptet XML a katalogový staging `699ddd4`, `d99a79e`, `2ba0782`, `f0370a3` | přijato lokálně; reálných 241/807, 1 ruční kontrola, idempotentní SQLite/MariaDB staging, 127/762 |
| 18 | Auditovaný shop admin + KIS plán `3845eab` | přijato lokálně; admin-only, CSRF, audit změn, reálný MariaDB review smoke, 131/787 |
| 19 | Kanonický draft katalog `b77f8c3` | přijato lokálně; single-use transakce, collision rollback, reálných 241/807, 3 free varianty, 134/826 |
| 20 | K2 účet–osoba `8c374a4` | přijato lokálně; admin-only `self`/`guardian`, ověřený e-mail, revoke + audit, SQLite/MariaDB, 138/859 |
| 21 | K2 veřejný claim `d32fc08` | přijato lokálně; bez enumerace osob, admin review, atomické schválení, idempotence a limit, SQLite/MariaDB, 145/907 |
| 22 | Řízená aktivace katalogu `5500927` | přijato lokálně; pouze `goods`, explicitní potvrzení, plain-text snapshot, audit, K3 fail-closed, SQLite/MariaDB, 151/956 |
| 23 | K3 pracovní akce `4ef5690` | přijato lokálně; cílová skupina, termíny, kapacita, cena, produktová vazba a audit; bez přihlášek/KIS, SQLite/MariaDB, 158/1008 |
| 24 | K3 bezplatný kroužek `88f5b97` | přijato lokálně; schválené dítě z K2, transakční kapacita, unikátní přihláška, storno a audit; bez objednávky/platby/soupisky/KIS, SQLite/MariaDB, 165/1058 |
| 25 | K3 souhlasy a storno `e5fcaa0` | přijato lokálně; neměnný registr verzí, snapshot přihlášky a fail-closed deadline; SQLite/MariaDB, 166/1085 |
| 26 | K3 čekací listina `a949c38` | přijato lokálně; FIFO, K2 recheck, atomické povýšení a skutečný souběh dvou MariaDB procesů; 168/1122 |
| 27 | K3 oznámení + správní storno `fb5e137` | přijato lokálně; transakční outbox, retry/karanténa, CRON worker a auditovaná výjimka po termínu; SQLite/MariaDB, 173/1192 |
| 28 | K3 provozní fronta `4cd0eae` | přijato lokálně; admin přehled, CSRF, auditované retry a zákaz zásahu do `processing`/`sent`; SQLite/MariaDB |
| 29 | K4 bankovní checkout `5750dd0` | přijato lokálně; serverová cena + fingerprint, snapshot, idempotence, skladový pohyb, QR/SPD a ruční paid; SQLite/MariaDB, 182/1287 |
| 30 | K4 fulfillment/storno `1763531` | přijato lokálně; transakční restock právě jednou, `refund_required`, příprava/výdej a audit; SQLite/MariaDB, 186/1339 |
| 31 | K4 zákaznické objednávky/vratky `f8b12a4` | přijato lokálně; account-scoped seznam/detail, `refunded`, bankovní reference a idempotentní audit; SQLite/MariaDB, 187/1376 |
| 32 | K4 kupóny `135803a` | přijato lokálně; fixed/percentage, platnost/minimum/limit, audit, fingerprint, snapshot a souběžně bezpečný counter; SQLite/MariaDB, 190/1425 |
| 33 | K4 Fio shadow import `f23a332` | přijato lokálně; pouze GET `/periods`, kontrola IBAN, minimální bankovní data, neměnná deduplikace a návrh VS + částka + měna bez změny platby; SQLite/MariaDB, 195/1457 |

PR #1 až #6 jsou sloučené do `main`. Produkční migrace, migrace hesel ani deploy
se v této session nespustily. Pořadí migrace před aktivací PHP je opravené;
workflow stále nesmí být spuštěno bez ověřeného `SSH_KNOWN_HOSTS`, externího
`AUTH_RATE_LIMIT_PEPPER` a výslovného souhlasu vlastníka.

Shop přírůstek zůstává bezpečným F0-enabling krokem: má staging, kontrolní UI,
kanonický katalog, řízenou aktivaci běžného zboží a K2 vazby účtů na sportovce.
Implementována je jen veřejná stránka izolovaného bezplatného kroužku; nasazena
zatím není. Nemá checkout,
objednávku, platbu, soupisku, produkční import ani KIS zápis.

Session increment používá vlastní cookie `EVIDENCESESSID`; jeho budoucí deploy
jednorázově odhlásí existující relace. DB revokace a atomický HMAC rate limit jsou
lokálně hotové, ale deploy je fail-closed bez externího `AUTH_RATE_LIMIT_PEPPER`.
Evidence je samostatný produkt. `VELOCOTA_INTEGRATION` musí zůstat `false`;
širší provozní nebo doménová integrace s Velocotou není plánovaná. Výhledově lze
samostatným rozhodnutím řešit pouze sdílenou/federovanou identitu uživatele.
Expirované hashované tokeny a POST+CSRF logout jsou hotové v `main`.
Permission cache, reset hesla a produkční ověření zůstávají otevřené, takže
W0-C ani F0 nejsou uzavřené.

## Stop podmínky

- nejasný vlastník dirty souboru,
- rozpor živého stavu s boardem,
- dvě větve mění stejný sdílený soubor nebo migraci,
- chybějící produktové rozhodnutí,
- požadavek na produkční změnu bez výslovného pověření,
- riziko vypsání tajemství nebo osobních údajů,
- worker nedodal base/commit SHA, testy a rozsah změn.

## Checklist před ukončením řídicího tasku

- [ ] aktualizovat stavy workerů a integrační frontu,
- [ ] měnit board pouze podle ověřeného důkazu,
- [ ] zapsat přesné SHA a všechny dirty soubory,
- [ ] uvést nedokončený proces nebo čekající rozhodnutí,
- [ ] zapsat jednu další konkrétní akci,
- [ ] aktualizovat čas tohoto handoffu,
- [ ] ověřit, že prompt nového řídicího tasku stále odpovídá procesu.
