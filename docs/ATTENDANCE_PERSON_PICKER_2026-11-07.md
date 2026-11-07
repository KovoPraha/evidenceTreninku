# P11 R06 — výběr osob v docházce, 7. 11. 2026

Stav: #366 sloučené, nové integrované HTTP/Chrome měření a parita PASS; schválený deploy po dokončení CI.
[Pokračování #372](https://github.com/KovoPraha/evidenceTreninku/pull/372), [nové důkazy](verification/attendance-person-picker-20261107/integrated/RESULTS.md).
Vlastník: vývoj podle přímého zadání vlastníka tohoto chatu.
Měřený base `d958fd6e`, integrační main `269684c2`; větev
`codex/attendance-person-picker-20261107`, worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-attendance-picker-20261107`.
Původní WIP `kis_rosters_admin.php` zachovaný, SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.

## Cíl, oprávnění a volba

[Předchozí celé měření](verification/full-http-performance-20261107/RESULTS.md)
našlo 10221 osob ve dvou seznamech, 1,37 MB HTML a několik sekund načítání.
Zadání: omezit projekci/HTML, zachovat dostupnost všech osob včetně hostů,
rozepsanou práci, původ, role i souběhovou ochranu; produkci neměnit.
Plán před kódem byl commitnutý v `c0883ff2`.

Zvoleno 50 výsledků + všechny vybrané osoby, pouze id/jméno/příjmení,
čerstvá autorizace bez cache; nativní POST bez nového JS/AJAX endpointu.
Pouhé skrytí dlouhého seznamu nesníží HTML, omezení na soupisku by měnilo chování.

První návrh dvou nezávislých forem prošel prvními měřeními, ale review odhalilo
ztrátu neodeslaných polí sousední části. PR byl vrácen do draftu; původní
[předběžný pár](verification/attendance-person-picker-20261107/RESULTS.md) zůstává.
Před opravou doplněna volba: **jeden společný nativní formulář**, oddělené
prefixy omluvy, přenos obou draftů a stavu stránek při hledání v obou směrech.
Starý API payload omluvy stále přijímán. formnovalidate u zápisových tlačítek
brání blokaci required poli sousední části; původní serverové validace platí.
Kanonické funkce writeru, zámky, CSRF a role zůstávají. Důvod a varianty:
[D2026-11-07-09](DECISION_LOG.md#d2026-11-07-09).

## Dokončená akceptace

1. Nový společný seed pro finální pár: originální d958fd6e a pak finální picker,
   350 tabulek / 161 migrací, 728 plánů / 52 týdnů, 200 účastníků / 52 revizí,
   10k zdravotních sledování / 30k záznamů, 10221 osob. Mezi variantami beze změny.
2. 21 HTTP případů na variantu / celkem 540 pozitivních časů, 78 Chrome pozorování
   tří rolí, 9 DOM kontrol na variantu PASS. HTML 1,37 MB → 92 kB;
   Chrome warm trenér 4,79 → 0,59 s, vedoucí 5,80 → 0,62 s, druhý trenér 5,61 → 0,40 s.
3. Oddělené diagnostiky: celé HTML stejné jako prostý GET každé varianty,
   0 DML/DDL a 13 shodných doménových checksums. SQL trenéra 59 → 75;
   přínos HTML/DOM, nikoli plošně menší SQL nebo prokázané rows examined.
4. Všech 10221 osob právě jednou ve 205 stranách pro obě role, MariaDB parita
   celého universe/pořadí/kolací/wildcardů/pinů/čerstvé role PASS.
5. JS/noJS Enter/paging/obě současné rozepsané části/nový i prázdný výběr/390 px
   PASS; 44 HTTP kontrol včetně CSRF, cizí lekce a neplatné find_* PASS.
6. Až po časování kanonická revize 53 / 201 osob / paper, stale 422 bez přepsání,
   osobní omluva skutečným tlačítkem 1001/paper a původní JSON API 1002/phone
   ověřené v DB. Revokace role/deaktivace PASS, původní role obnovené.
7. 36 testů / 380 assertions, PHPStan, styl 9 PHP PASS. Oba vlastní runtime
   zastavené a citlivé dočasné soubory/DB/configy odstraněné, WIP shodný.

[Finální důkazy a přesné meze](verification/attendance-person-picker-20261107/final/RESULTS.md),
[session handoff](SESSION_HANDOFF_ATTENDANCE_PERSON_PICKER_2026-11-07.md).
CI doplňuje reader paritu 10.3/11.4. Historický zmrazený validator harness
se nemění; generovaná kopie importuje sdílený UI helper i v potomcích.
První CI selhání importu 42370027225 je opravené bez vypnutí přísné HTML parity.

## Integrace a zbývající práce

Převzaté cizí #347 serverové výsledky, #364 katalog/vydání a #367 dávkové lekce
se zachovávají; release/flags a automatické bloky mají zdroj v CURRENT/main.
Po #367 je training_day.php novější než měřený pár; čísla zde nejsou nové
přeměření společného main ani produkce. Integrační regrese a finální CI zvlášť.

Finální CI/review a přijetí eviduje přímo #366; při převzetí ověřit aktuální
stav PR a neopakovat dokončené kontroly. Produkční nasazení se tím nepovoluje.

1. Celé HTTP/browser kombinace s #367; starých 219 SQL není dnešní integrovaný
   výsledek. Dávkové lekce již implementované, neplánovat jejich duplikaci.
2. R13/R05 objemy/kandidáti, skutečné rows examined, fyzický mobil/CPU/síť/souběh
   a další R01–R16. K0.2 zůstává otevřené.
3. Nasazení/přejímka pickeru až na nové explicitní zadání. Žádná aktivace zde.

Pracovní rozhodnutí 06 → 07 a události 14/15 → 19/20 v dřívějších commitech
kolidovaly s mezitím přijatou prací. Při integraci 269684c2 přijatá ID zachovaná;
vlastní rozhodnutí nyní 09, události 26–29. Historické commity se nepřepisují.

Integrační ověření 9d4a7538 po převzetí #367: **50 testů / 1294 assertions PASS**,
PHP quality CI 42375011506 SUCCESS; [doklad](verification/attendance-person-picker-20261107/final/integration.json).
Celé HTTP společné verze tím není přeměřené. Finální CI/merge hlavy u #366.

## Doplnění po integračním CI, před úpravou měřicího rozpočtu

CI 42375011560 obou MariaDB potvrdilo shodné celé HTML i doménová data ve všech
9 R06 případech, selhalo pouze na starém limitu 45 SQL pro předchozí celé UI.
Aktuální bounded picker přidává v tomto fixture 14 SQL a #367 ušetří 3, tedy
nový pevný limit **45 + 14 − 3 = 56 SQL**. Pozorováno přesně 56 ve všech případech;
139–950 řádků, původní cap 1000 a požadavek úspory vůči before platí dál.
Původní samostatný validator budget 3 dotazy/200 a 5/1200 i transakční lock
parita zůstávají nezměněné v AttendanceReadProfile. Nejde o zhoršení validatoru.

Zvolená změna CI před implementací: sdílet i aktuální training_day.php v before,
aby historický pár izoloval původní validator na shodné dnešní UI/lekci.
Retargetovat pouze zastaralý celostránkový cap na 56; ponechat přísné HTML/data,
cap řádků i relativní úsporu. Změna není aplikační optimalizace ani deploy.
Znovu přehrát skutečné reporty, odmítnout poškozený HTML i 57 SQL a zopakovat CI.

Převzaté DOC03 priority #368 / 8e9cdce1, bez aplikačních změn; přijaté release
a automatické bloky zachované. Vlastní E22 přesunuté na E26 kvůli přijatému DOC03.

## Pokračování podle nového zadání vlastníka

7. 11. 2026: dokončit #366, ověřit výsledky a změřit před/po; pokud kontroly
projdou, commitnout, sloučit a nasadit. Dřívější zákaz deploye je historický.
Neaktivovat nové integrace a neměnit skutečné osoby, role ani docházku.
Izolované pokračování `codex/attendance-picker-completion-20261107`, worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-attendance-picker-completion-20261107`.
Původní picker worktree i jeho rozpracované dokumentační sloučení a výstupy zachované.

1. Převzít #369 / `2ef20aed`, zachovat přijaté release/priority a automatické bloky.
2. Na jediném novém syntetickém localhost fixture měřit aktuální main před pickerem
   a potom stejný main s pickerem; shodné #367 na obou stranách, stejné session a data.
3. Celé HTTP včetně rolí/negativních případů, skutečný Chrome před/po;
   zvlášť ověřit všechny osoby a oba drafty, původní API a writer kontrakty.
   Původní hotové testy zbytečně neopakovat, pokud jejich aplikační zdroje zůstaly stejné.
4. Finální požadované CI, commit/push do #366 bez přepsání cizí práce,
   merge aktuálního main a standardní chráněný deploy bez importu/UAT flagu.
5. Čtecí produkční kontrola a průběžný release protokol; přihlášené skutečné zápisy neprovádět.
   Výsledky a zbývající meze uložit do plánu, registru a obou handoffů.

Aktuální stav: integrační dokumentační konflikty vyřešené v izolované kopii;
nové měření a deploy NOT RUN. Přijaté E23 lekcí a E26–29 / D09 pickeru z d62c3ad2 zachované; nové pokračování E30 / D10. Dočasné pracovní přeznačení v 75801847 sjednocené.

Převzatý souběžný PR head d62c3ad2 bez změny aplikačního kódu. Nové oprávnění a pokračování vlastní [D10](DECISION_LOG.md#d2026-11-07-10) / E30.

Nové společné měření: [průběžný protokol](verification/attendance-person-picker-20261107/integrated/RESULTS.md). Izolovaná Windows kalibrace selhala v Aria, server zastavený; pokračuje verzovaný Linux CI HTTP/Chrome pár, nikoli další oprava cizí DB.

PR #366 byl mezitím sloučen souběžnou relací jako `faae75e8`, přesný strom d62c3ad2, 11 kontrol PASS. Sloučení převzaté beze změny aplikačního kódu; toto pokračování doplňuje společné měření, předání a podle aktuálního oprávnění deploy. Baseline je poslední main před pickerem `2ef20aed`, který už obsahuje #367.

Nový pár Linux CI 42379723334 SUCCESS: main 2ef20aed před pickerem a shodný aktuální #367 proti pickeru faae75e8. 540 HTTP / 78 Chrome, HTML 1368679 → 91620 B, HTTP trenéra 48,40 → 27,87 ms, Chrome warm 445,65 → 82,60 ms. Osm zdrojových hashů odpovídá sloučenému runtime. Ve 13 checksummech a výběrech shoda; role/čerstvost a oddělené writer UAT PASS. [Přesná metoda a meze](verification/attendance-person-picker-20261107/integrated/RESULTS.md). Předání pokračuje v #372, produkční nasazení stále NOT RUN.

Převzatý dokumentační #370 / 56f5ce08: P14 obnova/inventura a její přijatá E30/31 zachované. Pracovní pokračování tohoto pickeru nyní E32–34 / D10; dřívější pracovní čísla v historii nejsou nové paralelní události. Aplikační PHP/JS/workflow main proti faae75e8 shodné.
