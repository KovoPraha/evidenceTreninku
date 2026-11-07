# P11 R06 — finální výběr osob, 7. 11. 2026

Implementace a nové úplné místní ověření HOTOVO; **bez našeho deploye**.
[Plán](../../../ATTENDANCE_PERSON_PICKER_2026-11-07.md),
[předání](../../../SESSION_HANDOFF_ATTENDANCE_PERSON_PICKER_2026-11-07.md),
[PR #366](https://github.com/KovoPraha/evidenceTreninku/pull/366) vede finální CI a přijetí.

## Praktický výsledek a opravený nedostatek

Výběr místo všech 10221 jmen nabízí hledání a stránky po 50. Všechny již vybrané
osoby zůstávají připnuté, včetně hosta mimo preferovanou soupisku; počet účastníků
není omezený na 50. Seznam bez otevřeného formuláře zbytečně nenačítá jména.
Hledání nic neukládá a funguje i bez JavaScriptu.

Review první verze odhalilo, že dvě samostatné formy mohou při hledání zahodit
neodeslanou druhou část. Proto nyní oba výběry patří do **jednoho nativního
formuláře**: oba drafty, výběry a stránka druhého pickeru se přenesou společně.
Pole omluvy mají vlastní prefix; původní API názvy zůstávají přijímané.
Zápisová tlačítka používají formnovalidate, aby required pole sousední části
neblokovala zvolenou akci; kanonická serverová validace zůstává beze změny.
Writer, transakce, zámky a oprávnění nebyly oslabeny. Nový JS ani endpoint nevznikl.
[První pár](../RESULTS.md) je předběžná historická evidence, nikoli tato finální verze.

## Stejná data a způsob měření

Na novém jediném schématu byl nejprve změřen originální `d958fd6e` z odděleného
checkoutu, potom finální společný formulář. 350 tabulek / 161 migrací,
728 plánů / 52 týdnů, 200 účastníků / 52 papírových revizí, 10k zdravotních
sledování / 30k záznamů a 10221 dostupných osob. Mezi variantami žádný reseed.
Standardní HTTP/UI přihlášení; žádné podvržené session. PHP 8.2.12,
MariaDB 10.4.32, Chrome 157.0.8103.38, Playwright CLI 0.1.23.
Řízený desktop viewport 1036 × 703, DPR přibližně 1 v obou variantách;
mobilní šířka 390 px je funkční UAT, nikoli fyzický mobilní benchmark.

21 HTTP případů na variantu: 9 pozitivních × 5 warmups + 30 časů, 12 negativních.
Celkem **540 pozitivních HTTP časů a 78 browser pozorování** (3 role × 3 cold
a 10 warm na variantu; warm po prime). Browser zahrnuje bootstrap/navigaci,
skripty, celou stránku i vykreslení. p95 = nearest rank ceil(0.95 × n);
u tří cold vzorků jde o maximum, nejde o spolehlivý odhad produkčního p95.

## HTTP a samostatná SQL diagnostika

| Případ | HTTP medián ms před → po | HTTP p95 ms před → po | HTML bajty před → po | SQL před → po |
| --- | --- | --- | --- | --- |
| `closure-coach-training` | 468.3 → 240.6 | 612.2 → 300.4 | 1369527 → 92466 | 59 → 75 |
| `closure-coach-plan` | 587.1 → 274.6 | 741.3 → 617.7 | 1369527 → 92466 | 65 → 81 |
| `closure-coach-search` | 672.0 → 355.4 | 1010.3 → 554.7 | 1373702 → 96641 | 203 → 219 |
| `closure-coach-empty-search` | 559.6 → 239.4 | 718.5 → 297.8 | 1369440 → 92379 | 53 → 69 |
| `closure-coach-list` | 386.2 → 88.5 | 645.3 → 109.5 | 39357 → 39357 | 36 → 26 |
| `closure-lead-training` | 432.6 → 157.4 | 605.6 → 219.8 | 1369783 → 92722 | 43 → 54 |
| `closure-lead-foreign` | 441.4 → 141.2 | 623.7 → 190.8 | 1343393 → 52317 | 43 → 49 |
| `closure-lead-plan` | 313.3 → 129.1 | 489.9 → 170.5 | 1343393 → 52317 | 49 → 55 |
| `closure-other-own` | 492.2 → 209.3 | 595.8 → 327.5 | 1343134 → 52058 | 58 → 64 |

Velká docházka: 1369527 → 92466 bajtů, **−93,25 %**. Původně 10221 participant
options a 10222 excuse options včetně prázdné volby; po změně 250 (200 vybraných
+ 50 výsledků) a 51. Vybrané osoby jsou nad rámec hledané stránky.
SQL count není plošně nižší: čerstvé kontroly a připnutí vybraných přidávají dotazy.
Přínos je omezená projekce a HTML/DOM. LIMIT sám neprokazuje omezení rows examined.

## Celé načtení v Chrome

| Role | Cold medián ms před → po | Warm medián ms před → po | Warm p95 ms před → po |
| --- | --- | --- | --- |
| coach | 4922.2 → 654.7 | 4791.1 → 594.1 | 6033.9 → 665.5 |
| sports_lead | 5834.7 → 586.7 | 5799.0 → 619.5 | 7582.3 → 800.9 |
| other_coach | 5683.6 → 487.7 | 5610.9 → 403.8 | 5952.0 → 551.6 |

Každý vzorek má HTTP 200 a jednu navigaci. V obou variantách 9 konzolových 404
pouze místního faviconu a žádný failed request. Externí Bootstrap loginu zůstává.
Všechny úplné finální vzorky zůstávají v reportu; kalibrace popsána samostatně.

## Parita a chování

- [HTTP/doménová kontrola](http-functional-verification.json): **44 PASS**, hledání,
  další stránka, nové i prázdné výběry, obě současně rozepsané části v obou směrech,
  CSRF 403, cizí lekce 403, neplatné find_* 400 bez možnosti spustit writer.
- [JS/noJS browser](browser-functional.json): Enter/hledání/paging, připnutý host
  20000, prázdná docházka, obě rozepsané části a 390 px bez přetečení PASS.
- [Celá množina](full-fixture-person-coverage.json): všech 10221 osob právě jednou
  ve 205 stránkách pro trenéra i vedoucího. Toto je přímý domain test;
  autentizované HTTP role/negativní případy jsou samostatná kontrola.
- [MariaDB parita](mariadb-picker-parity.json): 1200 osob, celá množina/pořadí,
  preference, hosté, diakritika, wildcardy, připnutí a čerstvá role PASS.
- Obě oddělené SQL/PHP diagnostiky: celé HTML shodné s prostým HTTP každé varianty,
  **0 DML/DDL a všech 13 doménových checksums shodných před/po**. 9 DOM kontrol
  na variantu PASS; počet lekcí, historie, trenéři, verze a původ zachované.
- Teprve **po měření** kanonický zápis revize 53 / 201 osob / paper včetně hosta,
  stale write 422 bez přepsání. Původní JSON API omluvy přijalo osobu 1002/phone.
  [Skutečné browser tlačítko](browser-excuse-write.json) zapsalo osobu 1001/paper,
  čas 06:00. [Stav v DB](post-uat-state.json) ověřil obě omluvy i revizi.
  Konečná záměrná zápisová UAT se nevydává za read-only stav po časování.
- [Odebrání role/deaktivace](http-revocation.json) PASS; původní role obnovené.
- [36 testů / 380 assertions](targeted-tests.xml), [PHPStan](phpstan.txt),
  [styl 9 PHP souborů](style.txt) PASS.

[Desktop](picker-js-desktop.png), [390 px bez JS](picker-nojs-mobile.png).
Úplná zdravotní upozornění a historie 52 revizí zůstávají, stránka je proto dlouhá.
Elementové výřezy picker-form-* jsou kalibrace překryté pevnou horní lištou.

## Reprodukovatelnost, integrace a další krok

[Souhrn](summary.json), [prostředí a zdrojové otisky](environment.json),
[manifest artefaktů](artifact-sha256.json), [opakování](REPRODUCE.md),
[kalibrace](CALIBRATION.md), [úklid](cleanup.json). Raw JSON jsou komprimované;
bez hesel, cookies, configu a HTML. Archiv původního páru zůstává beze změny.

Při finálním převzetí main `269684c2` přibyly **cizí** #364 katalog/nasazení,
#347 serverový audit a #367 dávkové lekce. Jejich změny se zachovávají.
Měřený picker je totožný, `training_day.php` po #367 je novější než uložené
otisky: uvedené časy/SQL jsou výsledek izolovaného picker páru před touto
integrací, **nikoli nový celý integrovaný main nebo produkční SLA**.
Integrované cílené testy a finální CI se evidují zvlášť v PR/předání.

První CI 42370027225 odhalilo chybějící import helperu v potomcích starého
měřidla. Oprava vkládá import jen do generované kopie CI harnessu; verzovaný
zmrazený harness zůstává neměnný. Obě porovnávané strany sdílejí aktuální UI,
původní kanonický validátor zůstává v before. Přísná HTML/doménová parita trvá.

Oba vlastní runtime jsou zastavené; schémata/účty/configy/credentials/cookies
odstraněné, TCP 18095/33459 odmítají spojení, browsery zavřené. Adresáře zůstávají.
Původní WIP má shodný SHA256. Žádná produkční operace tohoto PR.

Zbývá společné HTTP přeměření s #367 (starých 219 SQL není dnešní integrovaný
počet), R13/R05 objemy/kandidáti, rows examined, fyzický mobil/síť/CPU/souběh
a další R01–R16. K0.2 zůstává otevřené. Nasazení tohoto pickeru nebylo povoleno.
