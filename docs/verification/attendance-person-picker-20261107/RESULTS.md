# P11 R06 — omezený výběr osob v docházce, 7. 11. 2026

**Implementace a místní ověření HOTOVO, bez nasazení.** Místo dvou úplných
seznamů 10221 osob má každý výběr hledání a stránku po 50 výsledcích;
všechny vybrané osoby zůstávají. Hosté a ostatní dříve dostupní sportovci
nejsou omezení na vlastní soupisku. Nativní formulář funguje i bez JavaScriptu.

U trenérské docházky se 200 účastníky kleslo HTML **1369527 → 92451 bajtů
(−93,2 %)**. Medián Chrome load s již načtenými soubory **5231 → 1113 ms**;
vedoucí sportu **5208 → 457 ms**, druhý trenér **5322 → 466 ms**. Jde o nový
přímý běh před/po na stejném místním fixture, nikoli srovnání se starými časy
#363 a nikoli produkční SLA.

[Plán](../../ATTENDANCE_PERSON_PICKER_2026-11-07.md),
[předání](../../SESSION_HANDOFF_ATTENDANCE_PERSON_PICKER_2026-11-07.md),
[PR #366](https://github.com/KovoPraha/evidenceTreninku/pull/366).
Větev `codex/attendance-person-picker-20261107`, vlastní worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-attendance-picker-20261107`.

## Prostředí a metoda

- Base `d958fd6e`, po změně pouze aktuální R06 controller a nový reader/ovládání;
  [otisky zdrojů a prostředí](environment.json). Kanonický writer, zdroj formuláře,
  oprávnění a transakční zámky jsou stejné soubory jako před změnou.
- PHP 8.2.12 / MariaDB 10.4.32 / Chrome 157.0.8103.38 / Playwright CLI 0.1.23.
  Stejné native okno 1036×703 CSS px, DPR 1,25. Žádné CPU/síťové omezení.
- Nový vlastní localhost datadir, porty 33459 / 18095, **350 tabulek / 161 migrací**.
  728 plánů / 52 týdnů; 200 účastníků / 52 revizí; 10000 zdravotních sledování /
  30000 záznamů; 10221 osob. Oproti původnímu profilu feb8fe8c je schéma novější,
  obě současné varianty mají přesně stejná data i schéma. Žádný produkční dump.
- Standardní HTTP formulářové přihlášení 12 účtů. [21 případů](matrix.json) na
  variantu: 9 pozitivních / 12 odmítnutí nebo anonymní redirect; všech 11 pozic,
  vlastní/cizí trénink a propojený plán, hledání lekcí, prázdný filtr a seznam.
- **5 zahřátí + 30 prostých HTTP vzorků na pozitivní případ**, celkem **540**
  časových vzorků před/po. Bez prepend diagnostiky nebo general logu při časování.
  Normalizace pro stálost HTML jen CSP nonce a CRLF/LF. HTML mezi variantami
  záměrně jiné; porovnává se množina vybraných ID, trenéři, původ, verze, časy,
  historie a lekce. [DOM před](dom-before.json), [DOM po](dom-after.json): 9/9 PASS.
- Tři skutečné standardní UI loginy na variantu (coach, sports_lead, other_coach).
  Každý má 3 cold + 10 warm pozorování po prime, **78 browser pozorování** celkem.
  Cold znamená prázdnou browser HTTP cache, nikoli DB/OS cache. Metrika je
  Navigation Timing loadEventEnd; není to LCP/INP ani čas prvního obsahu.
  Pomocný 500ms networkidle wait není připočtený do load.
- Oddělené instrumentované HTTP diagnostiky všech 21 případů na variantu:
  shodné celé HTML oproti prostému HTTP, **0 DML/DDL**, shodné checksums 13
  doménových tabulek i vůči předchozí variantě. SQL count a PHP peak/duration
  v [souhrnu](summary.json); fetched rows/rows examined/SQL duration se tím neměří.
  EXPLAIN je odhad, ne skutečně přečtené řádky.

## Úplné HTTP srovnání

| Případ | HTTP medián před → po (ms) | p95 před → po (ms) | HTML před → po (bajty) | SQL před → po |
| --- | --- | --- | --- | --- |
| closure-coach-training | 653 → 259 | 946 → 389 | 1369527 → 92451 | 59 → 75 |
| closure-coach-plan | 604 → 217 | 984 → 538 | 1369527 → 92451 | 65 → 81 |
| closure-coach-search | 709 → 602 | 1068 → 1040 | 1373702 → 96626 | 203 → 219 |
| closure-coach-empty-search | 514 → 368 | 666 → 640 | 1369440 → 92364 | 53 → 69 |
| closure-coach-list | 425 → 139 | 713 → 280 | 39357 → 39357 | 36 → 26 |
| closure-lead-training | 362 → 234 | 552 → 489 | 1369783 → 92707 | 43 → 54 |
| closure-lead-foreign | 375 → 257 | 545 → 389 | 1343393 → 52317 | 43 → 49 |
| closure-lead-plan | 378 → 337 | 515 → 542 | 1343393 → 52317 | 49 → 55 |
| closure-other-own | 364 → 347 | 568 → 606 | 1343134 → 52058 | 58 → 64 |

[Souhrn s mediány, p95, PHP peak, browserem a konzolí](summary.json).
P95 je nearest-rank ceil(0,95 × n). Surové HTTP/browser/SQL podklady jsou
komprimované JSON soubory v tomto adresáři, jejich obsah ověřuje
[SHA256 manifest](artifact-sha256.json).

Počet SQL v některých formulářích **stoupl** (trenér 59 → 75, širší hledání
lekcí 203 → 219): nový reader a připnutý výběr mají čerstvé kontroly role
 a malé projekce. Nepřipisujeme zlepšení menšímu počtu SQL. Hlavní doložený
výsledek je omezení přenesených jmen/HTML a nižší celkový load v této zátěži.
Sort/LIKE stále mohou procházet kandidáty v DB; LIMIT omezuje výslednou projekci,
nikoli nutně rows examined. Samotný SQL count neurčuje časovou příčinu.

## Zachované chování a skutečné ovládání

- [Celý skutečný fixture](full-fixture-person-coverage.json): pro coach i
  sports_lead všech **10221 osob právě jednou přes 205 stran**, shodná preference
  a řazení jméno/ID. Jde o přímou read-only PDO kontrolu readeru, nikoli náhradu
  za HTTP přihlášení. Jména nejsou cachovaná ani posílaná všechna do JSON/DOM.
- [Samostatná MariaDB parita](mariadb-picker-parity.json): 1200 osob, preference,
  hosté, přízvuky/kolace, více slov, doslovná %/_/!, připnutí vybraných osob,
  změna dat a okamžité odebrání role PASS. Schéma samostatného testu odstraněné.
- [36 skutečných HTTP kontrol formulářů](http-functional-verification.json):
  hledání/stránky neukládají a zachovávají celý draft včetně textů, původu,
  potvrzení, záměrně prázdného výběru a původní concurrency verze. CSRF a cizí
  lekce odmítnuté. Čtecí úsek a odmítnuté zápisy mají stejné součty devíti
  tréninkových/omluvových tabulek.
- Až **po všech časových měřeních** oddělené syntetické zápisové UAT:
  kanonická revize **53**, **201 osob včetně nalezeného hosta**, původ **paper**;
  stará verze odmítnutá bez přepsání. [DB ověření](post-uat-state.json).
  Tyto záměrné změny nejsou vydávané za neměnná data časovacího úseku.
- [Skutečný browser s JS i bez JS](browser-functional.json): Enter ve hledání,
  klávesnicové stránkování, host mimo výsledky, prázdný výběr, zachované údaje
  osobní omluvy a 390px layout bez bočního přetečení PASS. Nativní tlačítko
  omluvy opravdu provedlo POST/303 a v DB zachovalo čas 06:00/source paper pro
  člena podřízené soupisky 1001: [browser zápis](browser-excuse-write.json).
- [Čerstvé odebrání role a deaktivace](http-revocation.json) PASS, původní celé
  role/aktivita obnovené. Writer a zámky nejsou měněné; standardní validační,
  souběhové a doménové regrese navíc běží v cílených testech/CI.
- [Desktop](picker-js-desktop.png), [mobilní šířka bez JS](picker-nojs-mobile.png).
  Zdravotní odkazy a 52 historických revizí zůstávají celé, proto je stránka
  se 200 lidmi stále dlouhá. Elementové výřezy picker-form-* jsou jen kalibrační;
  pevná horní lišta je překryla. Celostránkové snímky jsou vizuální důkaz.

## Kontroly, meze a předání

[35 testů / 374 assertions](targeted-tests.xml) PASS, [PHPStan](phpstan.txt) PASS,
[styl devíti PHP souborů](style.txt) PASS. Doplňková MariaDB reader parita je nově
v CI pro 10.3/11.4. Staré zmrazené before-harness se nemění: historická parita
validátoru R06 používá společnou novou stránku/picker v obou stromech a původní
validátor v before. Přísná datová i HTML parita zůstává; tuto změnu UI měří
samostatný aktuální pár. Finální CI/merge a jejich artefakty určuje [PR #366](https://github.com/KovoPraha/evidenceTreninku/pull/366).

V obou browser variantách 9 chybových záznamů pouze chybějící localhost favicon
404, žádný failed request. Login má stále externí Bootstrap CSS; nyní se neopravuje.
Nejde o nový důkaz produkční závady. Místní PC není vyhrazené, PHP development
server je jednoprocestný, bez TLS/gzip/produkční OPcache. Warm není univerzálně
rychlejší než cold. Fyzický mobil, throttling, souběh a širší R01–R16 NOT RUN.

[Úklid](cleanup.json): vlastní schéma/DB účet/config/credentials/cookies odstraněné,
všechny vlastní browsery a HTTP/MariaDB zastavené, oba sockety odmítají spojení.
Runtime ponechaný zastavený, bez rekurzivního mazání. Původní WIP SHA256 shodný.
Žádný produkční deploy, aktivace, konfigurace ani produkční data touto relací.

Dále dle priority: **hledání lekcí** (po této změně široký případ 219 SQL),
R13/R05 objemy/kandidáti, reálné rows examined, mobil/CPU/síť/souběh a ostatní
R01–R16. Celé K0.2 otevřené. Změny produkce vyžadují nové explicitní zadání.
