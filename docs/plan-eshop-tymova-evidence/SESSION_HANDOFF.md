# Session handoff

Tento soubor je stručný obnovitelný stav řídicího tasku. Architekturu ani
roadmapu neduplikuje; odkazuje na jejich kanonické dokumenty. Všechny provozní
hodnoty jsou historické, dokud je nový řídicí task živě neověří.

## Metadata

- Aktualizováno: 2026-08-01, Europe/Prague
- Repozitář: `C:\xampp\htdocs\evidencePavel`
- Programová brána: F0 – červená
- Aktivní integrační větev: `codex/foundation`
- Base SHA: `58ec8ec985d447dfe901481ac8bb24b944b03d08`
- Produkční deploy bez výslovného souhlasu: zakázán
- Produkční DB změny bez výslovného souhlasu: zakázány
- Poslední dokončená akce: W0-A – bezpečné sjednocení repozitáře
- Další přesná akce: commitnout řídicí dokumentaci, poté otevřít izolované W0-B
  a W0-D pracovní větve z potvrzeného base

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
| `origin/main` | `58ec8ec985d447dfe901481ac8bb24b944b03d08` | 2026-08-01 | fetch + rev-parse | ano |
| integrační branch | `codex/foundation` z `58ec8ec` | 2026-08-01 | Git | ano |
| ochranný snapshot | `d2b3c56` / `codex/pre-reconcile-20260801` | 2026-08-01 | lokální Git | před mazáním větve |
| GitHub deploy | run `30668559417`, success | 2026-08-01 | GitHub CLI | ano |
| produkční runtime | schema `2.20.2`, PHP `8.2.32` | 2026-07-31 | deploy post-check | před releasem |
| lokální schema | `2.20.2` | 2026-08-01 | read-only DB dotaz | ano |
| PHP syntax | 167 viditelných PHP souborů, 0 chyb | 2026-08-01 | `php -l` | po změně kódu |
| dependencies | 12 advisories, z toho 3 HIGH | 2026-08-01 | `composer audit --locked` | po změně locku |

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
| W0-B | queued | připravit z foundation | samostatný worker | KIS matcher + jeho testy | ostrý KIS import |
| W0-C | queued | po rozdělení rozsahu | samostatný worker | dependencies/auth hardening | finanční a členské funkce |
| W0-D | návrh hotoví worker | připravit z foundation | test worker | Composer dev, tests, CI | všechny feature workstreamy |
| W0-E | queued | až po D-015 | integrační vlastník | migrace + deploy hardening | finanční schéma |
| W0-F | waiting decision | dokumentace | produkt/ekonom | D-004 až D-011 | identity a wallet |

Řídicí task aktualizuje IDs, větve, commity a testy po každém worker handoffu.

## Integrační fronta

| Pořadí | Práce | Podmínka přijetí |
|---:|---|---|
| 1 | foundation dokumentace | odkazy, UTF-8, čistý diff, samostatný commit |
| 2 | W0-D test baseline | CI bez produkčních credentials, opakovatelné testy |
| 3 | W0-B KIS safety | pořadově nezávislý matcher a regresní test |
| 4 | W0-C security | kompatibilní dependency update a auth migrační plán/test |
| 5 | W0-E deploy/migrations | backup fail-closed, migrační check, restore drill |

Skutečné pořadí 2 a 3 lze změnit podle závislosti jejich commitů. KIS oprava se
nesmí přijmout bez automatického regresního testu.

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
