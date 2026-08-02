# Session handoff

Tento soubor je stručný obnovitelný stav řídicího tasku. Architekturu ani
roadmapu neduplikuje; odkazuje na jejich kanonické dokumenty. Všechny provozní
hodnoty jsou historické, dokud je nový řídicí task živě neověří.

## Metadata

- Aktualizováno: 2026-08-02, Europe/Prague
- Repozitář: `C:\xampp\htdocs\evidencePavel`
- Programová brána: F0 – červená
- Aktivní integrační větev: `main`; K2 claim kódový tip před tímto stavovým commitem:
  `d32fc08` (`Add reviewed account person claims`)
- Auth kódový tip před tímto handoff commitem: `9977b4dfc3f2f6aab775825d0bdf9b629e61e217`;
  auth přírůstek tvoří
  `a3c2239` (revokace + limiter), `10c2cf9` (atomická rezervace + SSO abort) a
  `9977b4d` (HMAC pepper + sjednocené pořadí zámků)
- Původní base: `58ec8ec985d447dfe901481ac8bb24b944b03d08`
- Produkční deploy bez výslovného souhlasu: zakázán
- Produkční DB změny bez výslovného souhlasu: zakázány
- Poslední dokončená akce: veřejný účet může poslat claim bez zpřístupnění seznamu
  sportovců; admin jej ručně spáruje, zamítne nebo schválí. Commit `d32fc08`,
  145/907 testů a izolovaná MariaDB prošly. Schválení claimu a vazby je atomické,
  duplicity jsou idempotentní a cizí účet žádost nezruší. Produkční workflow ani
  lokální/produkční DB se nezměnily
- Další přesná akce: doplnit GitHub Secret `SSH_KNOWN_HOSTS` a produkční
  `AUTH_RATE_LIMIT_PEPPER`, poté provést pouze autorizovaný první release.
  Potvrdit KIS identifikátor, retenci a pravidla publikace; následně připravit
  řízenou aktivaci draft produktů a model klubových akcí K3

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
| integrační branch | K2 claim kódový tip `d32fc08`; následuje pouze tento stavový commit | 2026-08-02 | Git | ano |
| PR / remote CI | PR #1 až #6 merged; finální main run `30743017895` success | 2026-08-02 | GitHub | ano |
| ochranný snapshot | `d2b3c56` / `codex/pre-reconcile-20260801` | 2026-08-01 | lokální Git | před mazáním větve |
| GitHub deploy | run `30668559417`, success | 2026-08-01 | GitHub CLI | ano |
| produkční runtime | schema `2.20.2`, PHP `8.2.32` | 2026-07-31 | deploy post-check | před releasem |
| lokální schema | `2.20.2` | 2026-08-01 | read-only DB dotaz | ano |
| testy | 145/907; staging + review + promotion i K2 identity/claim SQLite a izolovaná MariaDB OK; poslední vzdálený main CI důkaz zůstává zelený | 2026-08-02 | PHP 8.2.12 / PHPUnit 11.5.56 + GitHub | ano |
| Shoptet staging | 241 produktů / 807 variant převedeno do draft katalogu; druhé spuštění bez duplicity, 1 bookable rental, 3 free varianty, 0 veřejně aktivních | 2026-08-02 | reálný XML + SQLite/MariaDB | před veřejnou aktivací |
| dependencies | PhpSpreadsheet 5.8.1, Guzzle 7.15.2, PSR-7 2.13.0; 0 advisories | 2026-08-01 | Composer audit | ano |
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
| W0-G | partial accepted | `98ff91d`, `168d132`, `8f0cbe8`, `699ddd4`, `d99a79e`, `2ba0782`, `f0370a3`, `3845eab`, `b77f8c3`, `8c374a4`, `d32fc08` | KIS/shop workeři | fixture matice + reálný Shoptet staging, review, draft katalog a K2 claim | reálný KIS vzorek, veřejná aktivace a platby |

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

PR #1 až #6 jsou sloučené do `main`. Produkční migrace, migrace hesel ani deploy
se v této session nespustily. Pořadí migrace před aktivací PHP je opravené;
workflow stále nesmí být spuštěno bez ověřeného `SSH_KNOWN_HOSTS`, externího
`AUTH_RATE_LIMIT_PEPPER` a výslovného souhlasu vlastníka.

Shop přírůstek zůstává bezpečným F0-enabling krokem: má staging, kontrolní UI,
kanonický katalog pouze ve stavu `draft` a administrátorské K2 vazby účtů na
sportovce. Nemá veřejnou publikaci, checkout, rezervaci, platbu ani produkční
import. Veřejný claim je implementovaný, ale KIS párování zůstává výhradně ruční.

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
