# P11 picker — společné měření aktuálního main, 7. 11. 2026

HOTOVO v rozsahu nového společného měření/parity; nasazení má samostatný [protokol](../../attendance-picker-release-20261107/RESULTS.md). [Plán](../../../ATTENDANCE_PERSON_PICKER_2026-11-07.md),
[předání](../../../SESSION_HANDOFF_ATTENDANCE_PERSON_PICKER_2026-11-07.md),
[PR #366](https://github.com/KovoPraha/evidenceTreninku/pull/366).

Nové zadání vlastníka výslovně povoluje commit, merge a nasazení po shodě výsledků
a měření přínosu. Původní místní pár zůstává [samostatným historickým důkazem](../final/RESULTS.md).

## Metoda pokračování

Aktuální main před pickerem proti stejnému main s pickerem. Obě varianty používají
identické aktuální `training_day.php` po #367; runner tuto shodu ověřuje proti Git blobu.
Jediný nový syntetický dataset: 10221 osob, 728 plánů, 200 účastníků a 52 revizí,
zdravotní fixture 10k sledování / 30k záznamů. Bez reseedu mezi variantami.
Přepíná se jediná změněná aplikační stránka; nový helper je v before nepoužívaný.
Všechny ostatní produkční zdroje obou stromů jsou shodné.

Izolovaný Linux CI, MariaDB 11.4 service, PHP 8.2 a skutečný Chrome přes Playwright CLI 0.1.23.
21 HTTP případů na variantu, 5 warmups + 30 časů v 9 pozitivních případech;
3 role × 3 cold a 10 warm browser pozorování. Explicitní viewport 1036 × 703.
Zvlášť instrumentovaná diagnostika se striktně shoduje s prostým HTML každé varianty;
0 DML/DDL, 13 doménových checksumů. Aktuální oprávnění, negativní role, výběry,
zdroj, čas, verze a historie musí zůstat stejné; množina všech osob ověřená zvlášť.
Teprve potom explicitní syntetické writer UAT a revokace; nikdy produkční osoby.

Příkazy a kontrolovaný postup jsou verzované v
`tests/Support/AttendancePickerIntegratedProfile.py` a `.github/workflows/attendance-picker-performance.yml`.
Uploaduje se pouze whitelist bezpečných JSON, nikoli config, hesla, cookies,
HTML nebo soukromé CLI logy. Úklid vlastní DB, účtu, configu a session cookie jars
provádí původní guardovaný nástroj; PHP proces se zastaví a aplikační soubor obnoví i při chybě.
CI službu následně odstraní GitHub. Nové kompletní měření dokončené; přesný výsledek níže.

## Místní ověření a neúspěšná kalibrace

- Cílená místní sada po převzetí #369: **22 testů / 1144 assertions PASS**.
  Samostatné kanonické writer/coach/makeup testy a úplnou sadu doplňuje CI.
- Čerstvý izolovaný Windows runtime `kis-picker-integrated-20261107-b96f611b`,
  localhost 33479, schéma `p11_read_bb466b3dee0c41cc`: fixture skončilo při GRANT
  chybou Aria 176 (wrong checksum v `mysql.db`). Místní měření se vůbec nespustilo.
- Oprava pouze vlastní systémové tabulky selhala při synchronizaci Aria logu
  (bad file descriptor). Neúspěch není PASS; instance zastavená po ověření PID,
  přesného datadiru a portu. Adresář zůstal pro diagnostiku, nic se plošně nemaže.
  Žádný cizí ani produkční server se neopravoval. Nové měření proto v Linux CI.

Provozní provedení je doložené v samostatném release protokolu; benchmark a provozní UAT jsou oddělené skutečnosti. Původní WIP v htdocs i původní
picker worktree zachované; pokračování je ve vlastním worktree mimo htdocs.

PR #366 byl mezitím sloučen souběžnou relací jako `faae75e8`, přesný strom d62c3ad2, 11 kontrol PASS. Sloučení převzaté beze změny aplikačního kódu; toto pokračování doplňuje společné měření, předání a podle aktuálního oprávnění deploy. Baseline je poslední main před pickerem `2ef20aed`, který už obsahuje #367.

## Kalibrace Linux CI

První běh 42379420027 skončil před časováním: standardní login HTTP 503, DB user vytvořený původním localhost fixture byl na hostu 127.0.0.1, GitHub service ale vidí TCP zdroj 172.18.0.1. Není to PASS ani produkční chyba. Runner nyní přes stávající port/datadir/config guard zjistí skutečný zdroj root spojení, validuje IP a vytvoří další účet pouze pro tuto IP a jediné vlastní schéma se stejnými čtyřmi DML právy. Před původním úklidem jej opět odstraní. Doménová autorizace a oba porovnávané aplikační stromy beze změny.

## Dokončené nové integrované ověření

Linux CI **42379723334 SUCCESS**, měřený head `50a0c811`, osm otisků aplikačních zdrojů přesně odpovídá sloučenému `faae75e8`. [Doklad](verification.json).
MariaDB 11.4.13-MariaDB-ubu2404, PHP 8.2.35; Chrome 156.0.8078.79.
21 HTTP případů na variantu, **540 pozitivních časů / 78 Chrome pozorování**, 13 shodných doménových checksumů; žádný reseed.

| Případ | HTTP medián ms před → po | HTML bajty před → po | SQL před → po |
| --- | --- | --- | --- |
| closure-coach-training | 48.40 → 27.87 | 1368679 → 91620 | 56 → 72 |
| closure-coach-plan | 49.25 → 28.40 | 1368679 → 91620 | 62 → 78 |
| closure-coach-search | 48.58 → 27.35 | 1372854 → 95795 | 56 → 72 |
| closure-coach-empty-search | 47.97 → 26.96 | 1368592 → 91533 | 53 → 69 |
| closure-coach-list | 31.64 → 5.65 | 38577 → 38577 | 33 → 23 |
| closure-lead-training | 39.12 → 16.82 | 1368923 → 91864 | 40 → 51 |
| closure-lead-foreign | 35.92 → 14.84 | 1342534 → 51460 | 40 → 46 |
| closure-lead-plan | 36.88 → 15.26 | 1342534 → 51460 | 46 → 52 |
| closure-other-own | 44.93 → 23.97 | 1342287 → 51213 | 55 → 61 |

| Role | Chrome warm medián ms před → po | Warm p95 ms před → po |
| --- | --- | --- |
| coach | 445.65 → 82.60 | 664.20 → 92.30 |
| sports_lead | 408.75 → 76.30 | 573.40 → 82.40 |
| other_coach | 460.65 → 72.10 | 507.00 → 106.40 |

**Celý person universe 10221 osob / 205 stran pro obě role PASS**, vybrané osoby/verze/zdroj/čas/historie zachované.
Nativní HTTP funkční testy 44 PASS, potom samostatný kanonický zápis revize 53 / 201 osob / paper, stale 422 bez přepsání a legacy API omluvy PASS. Revokace/deaktivace PASS.
Obě instrumentované diagnostiky se shodují s prostým HTML každé varianty, žádné DML/DDL v měřené fázi.
Browser je skutečný Chrome přes standardní přihlášení tří rolí, shodný viewport; žádná konzolová chyba nebo failed request ve všech 78 vzorcích.
Původní JS/noJS, mobil 390 px a přenos obou draftů mají samostatné dřívější důkazy nad shodnými UI soubory; zde se nevydávají za znovu provedené mobilní UAT.

SQL count s pickerem někde roste. Přínos je menší přenos jmen a HTML/DOM; LIMIT sám nedokazuje omezené rows examined.
Mediány jsou syntetické Linux CI výsledky, nikoli produkční SLA. p95 je nearest-rank ceil(0.95*n); 10 warm a 3 cold browser vzorky nejsou spolehlivý produkční p95.
[Úklid](runner-cleanup.json): vlastní schéma/oba DB účty/config/cookie jars odstraněné, PHP server zastavený a stránka obnovená. Kontejner poté odstranil GitHub.
Všechny použité JSON jsou uložené komprimované; neobsahují raw HTML, cookies, hesla nebo konfiguraci.
