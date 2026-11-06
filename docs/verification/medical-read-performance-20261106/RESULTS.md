# P11 / R13 — optimalizace zdravotního přehledu 6. 11. 2026

## Praktický výsledek

Zdravotní přehled má implementované omezené načítání. **Při 10 000 sledovaných
podkladech načítá běžná stránka 132 místo 50 224 řádků**, filtr konkrétní osoby
36 místo 10 228. Počet nabídnutých osob klesl z 10 221 na 25, případně jednu
již vybranou osobu navíc. Všechny osoby včetně archivních lze dál vyhledat
nebo projít stránkováním.

Místní opakované porovnání: běžná stránka **368 → 60 ms**, přírůstek používané
PHP paměti **42,79 → 0,18 MiB**. Filtr osoby **86 → 25 ms**; filtr Platné
**366 → 270 ms**, **50 224 → 30 056 řádků**, paměť přibližně **0,18 MiB**.
Stavový filtr stále musí projít všechny kandidáty pro přesný počet; nezaměňovat
nižší spotřebu PHP paměti za omezený počet databází prohledaných řádků.

**Implementace, místní ověření i párové CI MariaDB 10.3/11.4 PASS.**
[Trvalé CI výsledky a oprava staršího testového rozpočtu](CI-VERIFICATION.md).
Finální stav všech povinných kontrol a začlenění určuje související PR.
[PR #352](https://github.com/KovoPraha/evidenceTreninku/pull/352).
Žádný deploy, aktivace, migrace ani změna produkčních dat či konfigurace.
Celé K0.2 není tímto uzavřené. Místní časy jsou orientační, stanice nebyla
vyhrazená pro benchmark; hlavní stálý důkaz je objem načítání, paměť a parita.

## Zadání, izolace a zdroje

Vlastník zadal autonomní optimalizaci podle [předchozího měření](../synthetic-performance-20261106/RESULTS.md),
stejná syntetická data, zachování chování a WIP, bez produkčních změn.
Větev `codex/medical-read-performance-20261106`, vlastní kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-medical-performance-20261106`.
Převzat main `f7c944e6`; následně zachován novější `1a0a1888` / #351.
Souběžné nasazení ranního auditu se zde nespouštělo ani neopakovalo;
pravdu o produkci vede výhradně [CURRENT_STATE](../../CURRENT_STATE.md).

Místní původní aplikace: checkout `fbdf622e0acadebeaf5525fb168869795a7faca2`
v předchozí vlastní měřicí kopii; její zdravotní stránka/služba jsou shodné
s převzatým main. Měřená nová implementace: `940cbcd121a01ab25ed732660936e7e1ae9d99ae`.
[Zdrojové otisky v novém souhrnu](after/summary.json) byly porovnány se současnou
pracovní kopií; všechny měřené zdroje shodné. Pozdější souborové předání samo
nemění měřenou implementaci. Původní `kis_rosters_admin.php` při převzetí má SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.

## Co se změnilo a co zůstává shodné

- Bez stavového filtru se nejprve počítá úplný počet a vybere 25 řádků.
  Teprve pro ně se načte kompletní historie stavových metadat. Žádná libovolná
  starší příloha se neobnovuje a historie se nekrátí umělým limitem.
- Stavový filtr jedním nebufferovaným SQL průchodem přenáší ID a pět stavových
  polí; kanonický PHP výpočet zůstává stejný. V paměti je historie právě jednoho
  sledování a požadovaná/poslední stránka ID a stavů. Úplné údaje a jména se
  dočtou jen pro zobrazenou stránku. PDO buffering se obnoví a kurzor uzavře
  i při výjimce. U jednoho sledování je jeho úplná historie nadále potřebná.
- Vyhledávání a stránkování osob nahrazuje kompletní rozbalovací seznam.
  K dispozici jsou všechny osoby, vybraný kontext zůstává dostupný i mimo
  shody. Tento kontext je oddělený od `person_id`, který filtruje přehled.
  Žádný nový AJAX endpoint. Pomocný skript pouze zachovává volbu v GET hledání
  a odkazech a respektuje stávající CSP nonce.
- Zachovány úplné počty, řazení podle kontroly/ID, vyhledávání, data a jejich
  chyby, ořez vysoké stránky, prázdný výsledek, účel, hranice platnosti,
  čekající náhrada, řetězce nahrazení a odvolání. Oprávnění se necachují.
  POST, rozhodování, audit a soukromé stahování mají původní chování;
  starší dotčené soubory přeformátované kvůli povinnému stylu.

## Srovnání na stejném místním serveru

MariaDB **10.4.32**, PHP **8.2.12**, Windows, vlastní loopback **33447**,
datadir `C:/xampp/tmp/kis-medperf-20261106-b81a2d90/data`, buffer pool 128 MiB,
PHP limit 512M. Obě fáze: 347 syntetických tabulek / 160 migračních záznamů;
žádný produkční config ani dump. 100/1000/10000 sledování, tři stavové záznamy
na sledování, stejný základ 221 dalších osob. Původních šest zdravotních
případů rozšířeno o tři případy s filtrem Platné nad stejnými daty.

**9 případů × 30 časových vzorků před i po = 540 vzorků**, pět zahřátí pro
každý případ. Samostatné instrumentované měření SQL, vrácených řádků a EXPLAIN:
9 před + 9 po. Každá měřicí session READ ONLY, čtyři negativní autorizační
případy odmítnuté v obou fázích. V každé fázi shodné datové i normalizované
HTML otisky běžného a instrumentovaného PDO. Mezi verzemi se porovnávají
všechna měřená doménová pole kromě záměrně omezeného počtu nabídnutých osob;
HTML výběru osob a bílé místo šablony se záměrně změnily.

Měří se skutečné GET tělo a šablona, **bez auth/DB bootstrapu, společné navigace
či načtení assetů v prohlížeči**. U nové oddělené šablony se měří stejný obsah
vložením jejího těla. Čas zahrnuje eval/kompilaci; není to celý HTTP request,
produkční OPcache nebo SLA. Časy a paměť se zachycují před kontrolním hashováním.
P95 je 29. z 30 seřazených vzorků. Řádky jsou skutečně načtené, nikoli rows examined.

| Sledování | Výběr | SQL před → po | Řádky před → po | Medián ms před → po | p95 ms před → po | Přírůstek PHP MiB před → po |
| ---: | --- | --- | --- | ---: | ---: | ---: |
| 100 | Vše | 6 → 10 | 724 → 132 | 6.09 → 6.01 | 10.97 → 8.43 | 0.605 → 0.184 |
| 100 | Osoba | 6 → 10 | 328 → 36 | 5.71 → 3.07 | 7.88 → 4.14 | 0.258 → 0.147 |
| 100 | Platné | 6 → 9 | 724 → 356 | 14.73 → 9.28 | 91.58 → 13.77 | 0.605 → 0.175 |
| 1000 | Vše | 6 → 10 | 5224 → 132 | 43.98 → 17.37 | 126.54 → 38.42 | 4.406 → 0.184 |
| 1000 | Osoba | 6 → 10 | 1228 → 36 | 24.09 → 8.18 | 34.27 → 9.96 | 0.756 → 0.147 |
| 1000 | Platné | 6 → 9 | 5224 → 3056 | 71.49 → 30.42 | 97.41 → 66.35 | 4.406 → 0.175 |
| 10000 | Vše | 6 → 10 | 50224 → 132 | 368.42 → 60.35 | 605.43 → 78.78 | 42.793 → 0.184 |
| 10000 | Osoba | 6 → 10 | 10228 → 36 | 86.00 → 25.31 | 165.33 → 40.53 | 6.086 → 0.147 |
| 10000 | Platné | 6 → 9 | 50224 → 30056 | 366.23 → 269.58 | 531.74 → 387.45 | 42.793 → 0.175 |

[Strojové porovnání](comparison.json), [původní souhrn](before/summary.json),
[nový souhrn](after/summary.json). V obou adresářích jsou všechny jednotlivé
časové vzorky, SQL a EXPLAIN; [původní úklid](before/cleanup.json),
[nový úklid](after/cleanup.json). SQL počet sám o sobě není cíl: běžná stránka
má více malých dotazů, ale zhruba o 99,7 % méně vrácených řádků při 10k.

## Regrese, browser a CI

- **32 testů / 14 132 assertions PASS**, [JUnit](targeted-tests.xml): zdravotní
  write/read modely, historie osoby, běžné seznamy, CSP a dokumentační kontrakt.
- **36 kombinací filtrů/stránek** proti zmrazenému původnímu algoritmu nad
  431 sledováními různých stavů a 57 osobami; SQLite i skutečná MariaDB 10.4 PASS.
  [MariaDB důkaz](medical-read-parity.json) zahrnuje i obnovu nastavení PDO,
  dostupnost všech osob a omezené dočtení vybrané stránky.
- Styl, plný PHPStan a cílená analýza finálního readeru PASS; baseline se nezvětšovala.
- Skutečný místní Playwright/Chromium s plným přihlášením, bootstrapem a navigací:
  anonymní 403, přihlášená stránka 200, nalezení #10100, zachování výběru při
  hledání i na druhé stránce, nativní POST 303 → správný detail #100 / osoba #10100.
  Šlo o vrácení již existujícího sledování, bez reálného podkladu či zprávy.
  [Kontroly](browser/checks.json), [strana 2](browser/person-page-2.yml),
  [detail po odeslání](browser/submitted-person.yml), [snímek výběru](browser/person-picker.png).
- CI MariaDB **10.3 / 11.4** porovnává původní a novou aplikaci na témže runneru,
  s nezměněnými daty a databázovým serverem. Celý nový profil má 24 scénářů
  (původních 21 + tři stavové případy); srovnání zahrnuje devět R13 před/po.
  Stav a artifacty určuje [PR #352](https://github.com/KovoPraha/evidenceTreninku/pull/352).
  Časové SLA na sdíleném runneru se nevynucuje; parita a datové rozpočty ano.

## Průběžná zjištění, která nesmějí zůstat skrytá

První varianta pro stavový filtr po dávkách 200 měla při 10k **108 SQL**
a místní medián 1045 ms proti původním 366 ms. Byla nahrazena streamem
minimálních metadat a dočtením pouze vybrané stránky; finálně **9 SQL**.
[Doklad nepřijaté varianty](calibration/batch-200-state-10k.json).
Místní zatížení výrazně měnilo časy i mezi opakováními, proto se hlavní nálezy
neopírají o samotný čas a CI navíc porovnává obě revize ve stejném běhu.

Při vývoji browser odhalil blokování nového pomocného skriptu CSP; chybějící
nonce byla doplněna před finálním úspěšným průchodem. Příprava browser fixture
nejprve správně odmítla chybějící registrářskou session; opravená pouze příprava
syntetického prostředí. Tyto kalibrace nejsou nasazená produkční závada.

## Opakování a další úkoly

[Zmrazený původní měřič](before-harness.php.txt), SHA256
`60d1d6cdbe7e9af433acc014992a764d30f9f6af97ec5d43f3ae2937eb5e699a`.
Oproti původnímu #349 navíc přidává stejný GET filtr Platné a omezuje matici
na R13; warmup/timing/SQL metoda a data zůstávají shodné. Nový měřič zaznamenává
skutečný checkout a otisky řadiče, šablony, služeb i pomocného skriptu.

V izolovaném starém checkoutu kopírovat zmrazený měřič do
`tests/Support/MedicalReadBefore20261106.php` a spustit `--run BEFORE_DIRECTORY`.
V nové kopii spustit `SyntheticReadPagesProfile.php --run AFTER_DIRECTORY --route R13`.
Stejný vlastní server, explicitní port/datadir a `EVIDENCE_PERFORMANCE_CONFIRM=SYNTHETIC-ONLY`.
Výsledky porovnává `CompareMedicalReadMeasurements.php BEFORE_DIRECTORY AFTER_DIRECTORY`.
Přesný párový postup je také v testovacím workflow.

| Priorita | Zbývající práce | Závislost / hranice |
| --- | --- | --- |
| Předání | Párové výsledky archivované. Finální povinné CI a začlenění jsou dohledatelné u PR #352. | Nezaměňovat začlenění za deploy. |
| Další P11 | R05 metadata/RSVP plánovače, poté R06 ověřování účastníků. | Samostatné změny s paritou a opakováním vlastní matice. |
| Další rozsah | Další role/filtry, delší historie jednoho sledování a plné HTTP/bootstrap/nav měření výkonu. | Tento browser průchod je funkční kontrola, ne plné měření výkonu HTTP. |
| Případná další R13 práce | Složitější stavový filtr musí pro přesný počet projít všechny kandidáty; případná další optimalizace až podle měření a plánů SQL. | Zachovat kanonickou platnost a neomezit řetězce historie. |
| Provoz | Produkční nasazení a skutečná přejímka. | Nynější zadání produkci výslovně nemění. P05/P07, P06 a heslo/T07 zůstávají samostatně odložené. |

[Datované předání](../../SESSION_HANDOFF_MEDICAL_READ_PERFORMANCE_2026-11-06.md),
[společný plán](../../WORK_PLAN_CURRENT.md). Žádná cizí WIP ani historie se nemaže.


## Úklid a zachování práce

[Vlastní schémata odstraněná](local-cleanup.json), server MariaDB řádně zastavený
(exec návrat 0), místní HTTP server a vlastní browser zavřené, dočasný config
nové kopie odstraněný. [Prostředí a kontrola WIP](local-environment.json):
oba porty bez posluchače, původní SHA256 `kis_rosters_admin.php` zůstává shodný.
Dočasný datadir zastaveného syntetického serveru je ponechaný pro případnou
místní diagnostiku/úklid; neobsahuje aplikovanou produkční databázi. Předchozí
runtime z #349 se nepřepisoval ani znovu nespouštěl. Neproběhla produkční operace.
