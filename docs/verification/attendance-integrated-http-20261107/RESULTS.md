# Celá docházka s oběma optimalizacemi — 7. 11. 2026

Místní měření a ověření HOTOVO, žádná změna aplikace nebo produkce touto relací.
[Plán](../../ATTENDANCE_PERSON_PICKER_2026-11-07.md), [session handoff](../../SESSION_HANDOFF_ATTENDANCE_HTTP_2026-11-07.md).

## Praktický výsledek

- Velká trenérská docházka v testovacím Chrome: opakované načtení **6,40 → 0,35 s**.
  Objem stránky **1,37 MB → 92,5 kB (−93,25 %)**. Výběr zůstal stejný.
- Široké hledání lekcí: **6,39 → 0,45 s** v Chrome a **203 → 72 SQL**.
  Není potřeba znovu implementovat již hotové dávkové lekce ani výběr osob.
- Obě současně rozepsané části, host, prázdný výběr, papírový původ a ochrana
  před přepsáním starší verzí prošly; funguje JavaScript i režim bez něj.

Jde o syntetické místní měření na nespecializovaném Windows PC, nikoli produkční
SLA. Výsledky ostatních devíti scénářů i p95 jsou níže. Žádné vzorky z úplných
běhů nebyly vyřazované; jedna neúplná role byla zopakovaná celá.

## Co se přesně porovnávalo

Nový jediný fixture, bez reseedu mezi variantami. Původní **d958fd6e** před oběma
úpravami proti společné aplikaci **faae75e8** (#366 + #367). Tento pár měří celé
integrované verze, ne oddělený příspěvek každého helperu. Aplikační soubory
se při měření neměnily, přepínal se samostatný document root na témže portu/DB.

350 tabulek / 161 migrací, 728 plánů / 52 týdnů, 200 účastníků / 52 revizí,
10221 osob, 10k zdravotních sledování / 30k záznamů. PHP 8.2.12, MariaDB 10.4.32,
Chrome 157.0.8103.38, Playwright CLI 0.1.23, viewport 1036 × 703,
DPR přibližně 1 ve všech šesti role/variant bězích. 390px zobrazení je funkční
emulace šířky, ne fyzický mobilní výkonový test.

**21 HTTP případů na variantu**, 9 kladných × 5 zahřátí + 30 časů, 12 záporných;
celkem **540 pozitivních HTTP časů**. V Chrome tentokrát všech **9 kladných
případů**, 3 cold + 10 warm po prime, celkem **234 pozorování** pro trenéra,
vedoucího a druhého trenéra. Standardní přihlášení všech 12 HTTP účtů a skutečné
UI přihlášení browser rolí; žádné podvržené session nebo změny oprávnění aplikace.

Chrome měří Navigation Timing load, DCL a zaznamenává paint/resource události.
500ms síťový klid slouží k dočkání požadavků a **není přičtený k load**. Cold
znamená vyprázdněnou HTTP cache prohlížeče, ne studený DB/OS start. P95 nearest
rank ceil(0,95 × n); u 3 cold jde o maximum, 10 warm není odhad produkčního SLA.
Opakované časování je GET stránky; hledání/stránky osob přes POST jsou ověřené
funkčně. Podíl režie automatizace ani skutečné DB rows examined zde nejsou měřené.

## Celé HTTP a samostatná SQL diagnostika

| Scénář | HTTP medián ms před → po | HTTP p95 ms před → po | HTML bajty před → po | SQL před → po |
| --- | --- | --- | --- | --- |
| Trenér – velká docházka | 945,0 → 112,6 | 1252,0 → 190,5 | 1369527 → 92490 | 59 → 72 |
| Trenér – otevření přes lekci | 1314,9 → 117,4 | 2103,9 → 168,9 | 1369527 → 92490 | 65 → 78 |
| Trenér – široké hledání lekcí | 1178,2 → 106,4 | 1841,4 → 149,8 | 1373702 → 96665 | 203 → 72 |
| Trenér – prázdné hledání | 823,0 → 121,5 | 1104,0 → 161,4 | 1369440 → 92403 | 53 → 69 |
| Trenér – seznam | 572,9 → 65,3 | 851,2 → 80,3 | 39357 → 39357 | 36 → 23 |
| Vedoucí – velká docházka | 605,8 → 93,1 | 938,2 → 121,6 | 1369783 → 92746 | 43 → 51 |
| Vedoucí – jiný trénink | 586,7 → 90,3 | 938,0 → 142,3 | 1343393 → 52341 | 43 → 46 |
| Vedoucí – jiná lekce | 697,9 → 92,4 | 981,0 → 148,4 | 1343393 → 52341 | 49 → 52 |
| Druhý trenér – vlastní trénink | 774,3 → 121,1 | 1317,4 → 163,6 | 1343134 → 52082 | 58 → 61 |

Počet SQL není všude menší: čerstvé kontroly a připnutí vybraných osob jej v
některých případech zvýší. Důležitá je menší projekce, přenos a HTML/DOM;
široký výběr lekcí a seznam mají nižší SQL. LIMIT neprokazuje rows examined.

## Celé načtení v testovacím Chrome

| Scénář | Cold medián ms před → po | Warm medián ms před → po | Warm p95 ms před → po |
| --- | --- | --- | --- |
| Trenér – velká docházka | 6615,5 → 300,9 | 6396,7 → 346,5 | 9047,1 → 542,9 |
| Trenér – otevření přes lekci | 6326,0 → 349,5 | 6780,6 → 418,0 | 8946,0 → 629,4 |
| Trenér – široké hledání lekcí | 6344,6 → 513,4 | 6393,6 → 445,9 | 8276,0 → 615,3 |
| Trenér – prázdné hledání | 7881,6 → 348,3 | 6687,6 → 389,8 | 9033,8 → 518,9 |
| Trenér – seznam | 887,9 → 184,0 | 799,8 → 152,6 | 1171,9 → 188,8 |
| Vedoucí – velká docházka | 5784,1 → 356,3 | 5423,0 → 343,9 | 6053,1 → 427,2 |
| Vedoucí – jiný trénink | 5150,4 → 224,9 | 3910,0 → 228,9 | 7089,9 → 279,4 |
| Vedoucí – jiná lekce | 4005,6 → 247,5 | 3414,5 → 232,1 | 5031,8 → 333,6 |
| Druhý trenér – vlastní trénink | 3349,2 → 358,9 | 3769,4 → 281,1 | 4808,5 → 730,4 |

V každém vzorku status 200, jedna navigace a přesná očekávaná URL. Konzole má
26/27 záznamů 404 před/po, žádné jiné zprávy nebo failed requests. Místní favicon
je samostatná drobnost; kalibrace a serverové logy jej rozlišují od selhání aplikace.

## Zachované chování a čtecí součty

- [9 DOM kontrol před](dom-before.json) a [9 po](dom-after.json): vybrané osoby,
  trenéři, časy, původ, verze, celá historie a výběr lekcí PASS. Pole vybraných ID
  se porovnávají jako množina kvůli původně neurčenému pořadí stejnojmenných osob.
- [20 tabulek před](core-pre-before.json), [po původní](core-post-before.json) a
  [po společné verzi](core-post-after.json) má stejné kontrolní součty. Zahrnuté
  osoby, tréninky/trenéři, preference/členství, docházka/historie, omluvy/náhrady,
  zdravotní podklady i oprávnění. Všechny zdrojové otisky obou document roots
  ověřené proti Git blobům; aplikace po změně přesně odpovídá faae75e8.
- Obě oddělené SQL/PHP diagnostiky se shodují s celým prostým HTML své varianty;
  **0 aplikačních DML/DDL**. General log se přepíná/čistí jen na vlastním serveru.
- [JS/noJS UI](browser-functional.json): oba současné drafty v obou směrech,
  Enter/hledání/paging, nově dohledaný host, prázdný výběr a 390 px bez přetečení PASS.
- [44 HTTP kontrol](http-functional-verification.json): role, CSRF, cizí lekce,
  neplatná find_* bez možnosti zápisu, přenos draftů, původní API omluvy PASS.
- Teprve po všech časech/diagnostikách kanonické UAT: revize **53 / 201 osob / paper**,
  host 20000, stale422 bez přepsání; legacy JSON omluva 1002/phone. Skutečné
  [browser tlačítko](browser-excuse-write.json) uložilo omluvu 1001/paper v 06:00.
  [Následná kontrola DB](post-uat-state.json) potvrdila obě omluvy a revizi.
  Tento záměrný zápisový úsek se nevydává za read-only fázi.
- [Odebrání role/deaktivace](http-revocation.json) odmítnuté, celé původní řádky
  obnovené a jejich součty shodné. Testy i zápisy pouze na syntetickém localhostu.

[Celá desktop stránka](picker-js-desktop.png), [390 px bez JS](picker-nojs-mobile.png).
Zdravotní odkazy pro 200 účastníků a historie zůstaly celé, stránka je proto dlouhá.
Výřezy picker-form-* jsou kalibrace překryté pevnou horní lištou, ne hlavní UI důkaz.

## Shoda se souběžným předáním, rozdíly a nedostatky

Mezitím přijaté **#372** obsahuje nezávislé
[Linux měření](../attendance-person-picker-20261107/integrated/RESULTS.md) a
[samostatně povolený deploy](../attendance-picker-release-20261107/RESULTS.md).
Tato relace žádný deploy/aktivaci ani produkční zápis neprováděla.

Linux porovnává 2ef20aed → faae75e8, tedy picker se stejnými už optimalizovanými
lekcemi na obou stranách; tento Windows pár d958fd6e → faae75e8 zahrnuje obě
úpravy. Proto SQL širšího hledání **203 → 72** zde a **56 → 72** v Linux páru
nejsou rozpor. Výsledek po obou úpravách má v obou případech **72 SQL**.
Linux má 78 Chrome pozorování tří detailů, zde 234 pro všech devět GET scénářů
včetně filtrů. PHP/DB/Chrome verze a výkon stroje se liší; absolutní časy se
nesčítají ani nepřenášejí mezi stroji, rozdíl surových bajtů zahrnuje platformu.
Starých 219 SQL pro samotný picker není aktuální společný výsledek.

Skutečné nasazení vlastní CURRENT a #372, nikoli tento benchmark. Dřívější
„čeká na nasazení“ je historický stav před novým pověřením druhé relace.
Přihlášené produkční UAT, fyzický mobil, řízená síť/CPU/souběh, větší objemy
R13/R05 a rows examined zůstávají otevřené. Celé K0.2 se tím neuzavírá.

## Důkazy, úklid a předání

[Souhrn](summary.json), [prostředí/zdrojové hashe](environment.json),
[manifest 55 artefaktů](artifact-sha256.json), [reprodukce](REPRODUCE.md),
[kalibrace](CALIBRATION.md), [kontrola citlivých hodnot](secret-scan.json).
Nové pomůcky: PHP syntaxe/styl, oba PowerShell parsery a Python compile PASS;
[styl](style.txt). Cílené app testy se bez změny aplikace neopakovaly; povinné
sdílené CI/merge a konkrétní hlavu určuje výsledkový PR této větve.

[Úklid](cleanup.json): vlastní schéma/účet/oba configy/credentials/cookie jars odstraněné,
HTTP/MariaDB a všechny vlastní browsery zastavené. TCP 18105/33469 odmítají spojení,
runtime e999e34b ponechaný zastavený, žádné rekurzivní mazání. Původní WIP má
SHA256 `7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.
