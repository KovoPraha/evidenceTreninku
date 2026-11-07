# P11 R06 — výběr osob v docházce, 7. 11. 2026

Stav: implementace a finální místní měření/UAT HOTOVO; finální CI/přijetí vede
[PR #366](https://github.com/KovoPraha/evidenceTreninku/pull/366). **Bez našeho deploye.**
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

Převzatý produkční důkaz lekcí #369 / 2ef20aed: provozní release jiného vlákna
zachovaný, picker #366 v něm není. Vlastní E26–E29 a D09; žádná změna aplikace.

## P11-R06-HTTP-COMBINED — celé načtení společné verze, 7. 11. 2026

Stav ROZPRACOVÁNO; vlastník vývoj podle nového přímého zadání tohoto chatu.
Měřená společná aplikace `faae75e8` (#366 + #367), kontrolní aplikace `d958fd6e`
před oběma optimalizacemi. Větev `codex/attendance-integrated-http-20261107`,
worktree `C:/Users/Marek/Documents/Codex/evidencePavel-attendance-http-20261107`.
Žádná změna aplikace, produkce ani přepínačů; původní WIP a cizí pracovní kopie zachovat.
[Předání této etapy](SESSION_HANDOFF_ATTENDANCE_HTTP_2026-11-07.md).

Volba před měřením: nový párový HTTP/browser běh na jediném novém syntetickém
schématu, nejprve nezměněný d958fd6e, poté společná verze faae75e8. Staré časy
nejsou přímá kontrolní skupina. Výsledek srovnává celé integrované aplikace;
příspěvek každého helperu jednotlivě z tohoto páru neodvozovat.

1. Samostatný localhost datadir/porty, kompletní původní kombinovaný seed:
   728 plánů / 52 týdnů, 200 účastníků / 52 revizí, 10k zdravotních sledování /
   30k záznamů, 10221 osob; žádný reseed mezi variantami. Bez ostrých dat/configu.
2. Stejných 21 R06 HTTP případů na variantu: 9 pozitivních × 5 warmups + 30 časů,
   12 negativních; role coach, sports_lead, other_coach a ostatní pracovní pozice.
   Datum/plán, široké/prázdné hledání lekcí, vlastní/zastupovaná lekce a seznam.
3. Skutečné standardní přihlášení přes HTTP/UI. Chrome pro všech 9 pozitivních
   případů: každý 3 cold + 10 warm po prime, společný desktop viewport/DPR;
   cílem 234 browser pozorování celkem. Nezasahovat do aplikace kvůli měřidlu.
4. Oddělit prosté časování od SQL/PHP diagnostiky. Celé HTML každé varianty
   musí souhlasit s vlastním prostým GET; 0 DML/DDL a nezměněné doménové součty.
   Srovnat vybrané osoby jako množinu, trenéry, původ, verzi, historii a nabídku lekcí.
5. JS/noJS regrese obou současných draftů, hledání/stránek, hosta, prázdného výběru,
   čerstvé revokace. Záměrné kanonické zápisové UAT jen na syntetických datech
   a až po všech read-only měřeních; výsledky odděleně.
6. Bezpečné důkazy do `docs/verification/attendance-integrated-http-20261107/`.
   Uklidit jen vlastní DB/účty/configy/credentials/služby, otisky původního WIP
   shodné. Aktualizovat registr/CURRENT/handoff/katalog/logy, PR/CI bez deploye.

Zbývá nyní provést celý pár a úklid. Fyzický mobil, řízená síť/CPU a souběžná
zátěž, R13/R05 objemy/rows examined a další K0.2 jsou navazující samostatné etapy.
Rozhodnutí [D2026-11-07-10](DECISION_LOG.md#d2026-11-07-10).
