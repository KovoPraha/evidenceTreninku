# P11 — celé HTTP a prohlížeč, 7. 11. 2026

**Měření dokončené; aplikace ani produkce se neměnily.** Plánovač, docházka
a zdravotní přehled prošly skutečným přihlášením, bootstrapem, autorizací,
navigací a načtením souborů v Chrome. **65 HTTP případů** (31 povolených,
34 odmítnutí/redirectů), **930 časových vzorků HTTP**, **403 browser pozorování**.
Doplňkové dvě čerstvé revokace role/účtu PASS. Původní WIP zachované.

Praktický nález: u velké syntetické databáze vrací docházka **1,34–1,37 MB HTML**
s výběrem všech **10 221 osob**. HTTP odpověď běžné trenérské docházky má
medián **0,81 s**, dokončení načtení v Chrome **6,18 s**. Široké hledání lekcí
má **203 SQL** místo 59, ale samotný počet SQL nevysvětluje všechny časy.
Další priorita je cílený výběr osob při zachování dostupnosti všech účastníků
a samostatné posouzení kontrol lekcí. Není to důkaz stejné prodlevy na produkci.

Větev `codex/full-http-performance-20261107`, pracovní kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-full-http-performance-20261107`.
Měřená aplikace **`feb8fe8cfd31cb79b1161ca417317f9a3c9f6db2` / #354**,
[zdrojové otisky a prostředí](environment.json),
[session handoff](../../SESSION_HANDOFF_FULL_HTTP_PERFORMANCE_2026-11-07.md).

## Co je skutečně pokryté

- R05: trenér vlastní/všechny plány, tým včetně podřízeného, skupina, jiný
  trenér, prázdný filtr a předchozí týden; vedoucí vlastní/všechny plány a tým.
- R06: trenérský trénink, plánem propojená lekce, široké a prázdné hledání,
  seznam bez vybraného tréninku; vedoucí i cizí trénink/propojená lekce,
  druhý trenér vlastní trénink. Cizí skutečný trénink trenérovi odmítnutý.
- R13: registrář vše/Platné/Odvoláno/prázdné stavy, osoba/neexistující osoba,
  druhá/poslední stránka, hledání a stránka výběru osoby. 9k platných a 1k
  odvolaných sledování; pending/expired filtry jsou prázdné.
- Všech **11 aktuálních pracovních pozic** z kanonického registru: coach,
  sports_lead, registrar, program_coordinator, catalog_manager, order_operator,
  finance_manager, tv_operator, bar_operator, bar_admin, system_admin.
  Nepříslušné pozice mají 403; system_admin pozice sama neznamená superadmina
  ve všech kontextech. Další účet druhého trenéra; anonymní 302/403.
- 12 skutečných standardních HTTP loginů, čtyři standardní browser UI loginy.
  GET se měří až po loginu; login časy jsou [zvlášť](logins.json).
- [31 DOM kontrol](http-dom-verification.json): počty karet/plánů a filtry,
  200 vybraných účastníků, papírový původ, 50/0 vyhledaných lekcí,
  zdravotní stránka 25 položek / osoba 1 / prázdný filtr 0 a bounded picker.
- [Revokace](http-revocation.json): odebraná role z již přihlášené session
  okamžitě 403, neaktivní účet 303 na expired login. Původní celé řádky rolí
  syntetického trenéra 8 byly obnovené. Nedělala se žádná skutečná oprava docházky.

## Prostředí a měřicí hranice

Windows, PHP **8.2.12**, MariaDB **10.4.32**, Chrome **157.0.8103.38**,
Playwright CLI **0.1.23**. Vlastní loopback DB 33449 / HTTP 18094, nový datadir
`C:/xampp/tmp/kis-httpperf-20261107-edbb462e`, náhodné schéma
`p11_read_38a870931ab87cc3`. **348 testových tabulek / 160 migrací**, 728 plánů
za 52 týdnů, trénink 200 osob / 52 papírových revizí, 10k zdravotních sledování /
30k jejich záznamů, propojené lekce a nový podřízený tým. Žádný produkční dump.
Jde o spojený zátěžový fixture; dřívější dílčí R06 profil ještě neměl 10k osob
ze zdravotní části. **Čísla nelze odečítat od dřívějších CI časů ani označit
za regresi po #354.** Nejde o klon všech produkčních legacy dat/tabulek.

1. Skutečné nezměněné vstupní PHP stránky přes HTTP, `db.php`, živá kontrola
   session/pozice a `hlavicka.php`. Žádné nahrazení auth bootstrapu či navigace,
   eval šablon nebo ručně vyrobené přihlášené session.
2. HTTP: CURL bez proxy/redirectů, 5 zahřátí + 30 samostatných požadavků na
   pozitivní případ, TTFB a úplný příjem HTML. Bez general-log/PHP diagnostiky.
   Hashování a kontrola obsahu jsou mimo CURL interval. Stejné celé HTML ve
   všech opakováních po nahrazení pouze procesní CSP nonce a CRLF/LF.
   Poslední filtr Odvoláno byl přidán samostatnou sérií 35 GET bez změny dat;
   původní vzorky se nepřepisovaly. Negativní případy jsou po jednom ověření,
   nikoli statistické časové profily.
3. Oddělený diagnostický HTTP server s testovacím `auto_prepend_file` a general
   logem **jen vlastního DB serveru**. Všech 65 výstupů se striktně rovná prostému
   HTTP. PHP doba/paměť zahrnují celý request; hodnoty jsou instrumentované.
   SQL zahrnuje skutečný bootstrap a případné iniciační příkazy spojení.
   **0 DML/DDL**, kontrolní součty 13 doménových tabulek shodné. EXPLAIN se
   provádí dodatečně. **Vrácené řádky, skutečné rows examined ani jednotlivé
   SQL časy se zde neměří**; SQL count není přímo srovnatelný s #352–354.
4. Chrome: všechny 31 pozitivní případy, 3 pozorování s vyprázdněnou HTTP cache
   a 10 se zahřátou cache po samostatném prime. Jen browser cache, nikoli
   restart DB/OS cache. Native okno **1036×703 CSS px**, DPR **1,25**; nejde o
   fyzický mobil. Navigation/Resource Timing, TTFB, responseEnd, DCL, load,
   FCP a přenesené soubory. Čekání `networkidle` ověřuje dočtení prostředků;
   jeho povinných 500 ms **není přičteno** k metrice load.

PHP běží v jednom vývojovém procesu, bez HTTPS/gzip, nikoli v produkčním
Apache/FPM s produkční OPcache. Běžná vývojová stanice nemá rezervovaný CPU.
Měření je sekvenční, bez CPU/síťového throttlingu nebo souběžné zátěže.
Load znamená dokončení události načtení, **ne první zobrazení, LCP ani odezvu
po kliknutí**; první obsah může přijít dříve. P95 je nearest-rank, browser
warm n=10 je orientační. Cold n=3 není podklad pro SLA. Zahřátá cache není
ve všech případech rychlejší. Lokální časy nejsou produkční SLA ani párové
měření před/po nové optimalizaci. Žádné další zrychlení nyní neimplementováno.

## Naměřené případy

Časy v ms; HTTP medián / p95, browser medián load cold / warm.
[Strojový souhrn](summary.json), [CSV](cases.csv).

| Případ | HTTP ms | Chrome ms | SQL diagnostika | HTML KiB |
| --- | --- | --- | --- | --- |
| planner-coach-own | 467 / 931 | 898 / 679 | 37 | 111.3 |
| planner-coach-all | 350 / 744 | 1075 / 768 | 37 | 134.3 |
| planner-coach-root | 340 / 558 | 989 / 1030 | 46 | 111.3 |
| planner-coach-child | 418 / 746 | 1024 / 1281 | 46 | 111.3 |
| planner-coach-trainer | 392 / 1069 | 1002 / 666 | 37 | 92.8 |
| planner-coach-group | 476 / 847 | 747 / 689 | 37 | 134.8 |
| planner-coach-empty | 283 / 387 | 643 / 579 | 35 | 69.3 |
| planner-coach-previous | 356 / 644 | 620 / 646 | 37 | 113.2 |
| planner-lead-own | 271 / 644 | 348 / 402 | 25 | 69.8 |
| planner-lead-all | 304 / 472 | 484 / 483 | 37 | 153.7 |
| planner-lead-root | 391 / 936 | 333 / 527 | 47 | 153.8 |
| closure-coach-training | 805 / 1259 | 6477 / 6181 | 59 | 1337.4 |
| closure-coach-plan | 902 / 1226 | 5544 / 5636 | 65 | 1337.4 |
| closure-coach-search | 790 / 1112 | 5776 / 6870 | 203 | 1341.5 |
| closure-coach-empty-search | 904 / 2279 | 6232 / 5694 | 53 | 1337.3 |
| closure-coach-list | 536 / 977 | 698 / 662 | 36 | 38.4 |
| closure-lead-training | 663 / 1005 | 2652 / 4586 | 43 | 1337.7 |
| closure-lead-foreign | 655 / 927 | 5129 / 4170 | 43 | 1311.9 |
| closure-lead-plan | 678 / 933 | 4511 / 5065 | 49 | 1311.9 |
| closure-other-own | 745 / 1159 | 3163 / 4810 | 58 | 1311.7 |
| medical-registrar-all | 212 / 530 | 552 / 486 | 21 | 48.6 |
| medical-registrar-valid | 597 / 1288 | 763 / 1201 | 20 | 48.7 |
| medical-registrar-pending | 940 / 1330 | 836 / 981 | 19 | 44.9 |
| medical-registrar-empty | 1257 / 3853 | 629 / 898 | 19 | 44.9 |
| medical-registrar-person | 184 / 279 | 303 / 384 | 21 | 45.1 |
| medical-registrar-unknown-person | 179 / 349 | 459 / 568 | 21 | 45.0 |
| medical-registrar-page2 | 413 / 728 | 581 / 533 | 21 | 48.8 |
| medical-registrar-last-page | 503 / 1065 | 496 / 502 | 21 | 48.7 |
| medical-registrar-search | 801 / 1354 | 504 / 588 | 21 | 48.7 |
| medical-registrar-picker | 401 / 836 | 588 / 535 | 21 | 49.0 |
| medical-registrar-revoked | 1169 / 1730 | 923 / 894 | 20 | 48.7 |

Rozdíly mezi filtry nejsou izolovaný A/B experiment: mění se i výběr dat,
pořadí běhů a stav cache. Nevyvozovat kauzalitu z jediné dvojice mediánů.
Silný společný nález je vysoký počet DOM možností a velké HTML u R06 napříč
trenérem/vedoucím/druhým trenérem; prostý seznam bez formuláře má jen 39 KiB.
R13 zůstává stránkovaný po 25 a má omezený výběr osob; stavový filtr ale pro
přesný počet posuzuje všechny kandidáty. Další práce má navázat na tyto důkazy.

## Browser diagnostika a nedostatky

[Samostatná CDP stopa](browser-cpu-diagnostic.json), jeden instrumentovaný
příklad: plánovač ParseHTML 26 ms / Layout 86 ms / UpdateLayoutTree 291 ms,
docházka 308 / 240 / 452 ms; callback `app-ui.js` přibližně 4,5 / 39 ms.
Jde o **vnořené a neexkluzivní intervaly**, nesčítat na procenta CPU ani
nevydávat za úplný rozklad celého load. Není prokázané, že samotný JavaScript
je dominantní příčinou.

[Kontrola vlivu automatizace](browser-observer-check.json): nativní Chrome
`Page.navigate` bez Playwright `page.goto`, tři pozorování na případ. Velký
formulář druhého trenéra měl load **3,84 / 5,73 / 4,23 s**, responseEnd
0,56–0,69 s; více sekund tedy nevzniká pouze voláním Page.goto. Přesný podíl
režie automatizace není vyčíslený. Chrome bez `--enable-automation` nevrátil
argumenty spuštění; accessibility flags jsou **neznámé**, nic se z toho neodvozuje.

[Browser konzole](browser-console-summary.json): **93 chyb 404 v měřených
pozorováních**, všechny odpovídají chybějícímu localhost `/favicon.ico` při
cold cache; souhrnná CLI konzole má dalších 1 ze startu. Žádná jiná chybová
URL ani failed network request. Nejde o čistou síť bez chyb. Tento lokální
favicon nález se nevydává za ověřenou produkční závadu.
Plánovač/login stále načítají také externí Bootstrap CSS z jsDelivr, vedle
lokální sady; závislost a její síťová doba zůstávají ve výsledcích. Zde se neodstraňovala.

[Docházka](closure-desktop.png) / [zdravotní přehled](medical-desktop.png),
syntetické screenshoty vizuálně ověřené. Login, filtry a readonly render nejsou
lidské UAT nebo funkční přejímka zápisů/plateb.

## Trvalé podklady a ověření

- [HTTP vzorky](http-timings.json.gz), [SQL/EXPLAIN a PHP diagnostika](http-diagnostics.json.gz).
- Browser [trenér](browser-coach.json.gz), [vedoucí](browser-sports_lead.json.gz),
  [registrář](browser-registrar.json.gz), [druhý trenér](browser-other_coach.json.gz).
  Každý raw soubor má všechny vzorky a Resource Timing. Gzip je pouze komprese;
  JSON lze rozbalit standardní knihovnou Python `gzip` bez dalších balíčků.
- Přesné provedené browser funkce jsou v `browser-*-source.js.txt`; žádná hesla,
  session, cookie jars nebo HTML s CSRF nejsou archivované.
- [Manifest SHA256](artifact-sha256.json), [prostředí a app otisky](environment.json),
  [kalibrace](CALIBRATION.md). Zdroje měřidla `tests/Support/FullHttp*` a
  [reprodukce](REPRODUCE.md). Aplikační soubory shodné s base `feb8fe8c`.
- Místní relevantní regrese **42 testů / 2852 assertions PASS**, [JUnit](targeted-tests.xml).
  Styl a baseline budget PASS (497 položek / 2146 nálezů beze změny).
  Finální CI a PR/merge jsou samostatně ověřované; tento místní plný HTTP/browser
  profil se za běh Linux/MariaDB 10.3/11.4 nevydává. Předchozí párové CI zůstává zachované.

## Úklid a zbývající práce

[Úklid](cleanup.json): vlastní schéma, DB účet, přesně ověřený ignorovaný config
a lokální credentials/cookie jars odstraněné. Browser zavřený, HTTP i MariaDB
zastavené, porty 18094/33449 neposlouchají. [Finální kontrolní součty](final-core-check.json)
potvrzují shodná data po browseru i obnovené revokaci. Runtime zůstává zastavený,
žádné rekurzivní mazání starých adresářů. Původní WIP SHA256 shodný:
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.

1. **R06 výběr osob:** navrhnout omezené hledání/stránku při zachování všech
   dostupných účastníků, vybraných osob, papírového původu a nativní funkčnosti
   formuláře. Stejný syntetický objem a nativní browser kontrola před/po.
2. **R06 hledání lekcí:** posoudit 50 jednotlivých čerstvých kontrol a 203 SQL;
   případné dávkování pouze s paritou, bez cachování oprávnění a beze změn writeru.
3. **R13 / R05:** stavový průchod všech sledování a historické kandidáty při
   hierarchickém filtru dále profilovat podle objemu. Samotný SQL count či
   EXPLAIN odhad nejsou důkazem skutečně omezených přečtených řádků.
4. Další velikosti spojeného fixture, fyzický mobil/CPU/network profily,
   start bez serverových cache, souběžná zátěž a ostatní cesty R01–R16 jsou
   samostatné úkoly. Celé K0.2 není uzavřené. Rodinné/sportovní účty ani
   superadmin přepínání kontextů nebyly novou pozitivní výkonovou maticí.
5. Favicon na tomto serveru a duplicitní externí CSS ponechat jako malé
   samostatné kandidáty. Produkční nasazení/UAT pouze novým zadáním.

P05/P07, export P06, heslo/T07 a ranní audit jiné relace se neopakovaly.
Release/flags v [CURRENT_STATE](../../CURRENT_STATE.md) tímto měřením neměníme; doplňujeme odkaz P11.

## Souběžný main při integraci

Měření zůstává na `feb8fe8c` (348 tabulek / 160 migrací). Při předání převzatý
main `37d0ad68` obsahuje navíc shop migraci, dávkové klubové ceny #358 a změnu popisku navigace system_admin;
novější main ani produkční release nebyly touto relací přeměřeny. Záznamy ROLE01,
DOC01 a P07 zůstávají zachované. R13/R05/R06 už zahrnuje release ROLE01 podle
CURRENT_STATE; žádné nové nasazení tato relace neprovádí.

Předání je pushnuté v [PR #363](https://github.com/KovoPraha/evidenceTreninku/pull/363),
větev `codex/full-http-performance-20261107`; finální CI/merge se ověřují u PR.
