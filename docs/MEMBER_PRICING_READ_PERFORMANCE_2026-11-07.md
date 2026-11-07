# P11 — optimalizace čtení klubových cen 7. 11. 2026

## Zadání, rozsah a pracovní kopie

Vlastník zadal optimalizaci klubových cen podle [analýzy](verification/performance-analysis-20261107/RESULTS.md),
ověření shodných výsledků a měření před/po. Zachovat WIP, průběžně aktualizovat
tento plán a session handoff, nic nenasazovat na produkci.

Výchozí main `feb8fe8c` již obsahuje R06 #354. Větev
`codex/member-pricing-read-performance-20261107`, vlastní pracovní kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-member-pricing-performance-20261107`.
Původní `C:/xampp/htdocs/evidencePavel` se nepřepíná; výchozí SHA256 cizího
`kis_rosters_admin.php`: `7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.

Optimalizuje se výhradně čtení cen pro katalog/detail. Veřejné ceny,
prodejnost, filtry, řazení, stránkování, tarify a cenová pravidla zůstávají
shodné. Checkout a zápisové služby zachovají původní samostatné čtení.
Žádná migrace, config, skutečná data, integrace, pošta ani deploy.

## Etapy a průběžný stav

| Etapa | Stav | Podmínka dokončení |
| --- | --- | --- |
| A: aktuální zdroje a izolace | HOTOVO | Čerstvý main, instrukce přečtené, WIP otisk zapsaný. Původní analýza přenesena do této větve jako historický podklad. |
| B: původní algoritmus a měřicí data | HOTOVO | Zmrazený původní algoritmus a katalogový loop v tests/Support/MemberPricingReadBaseline.php; společná syntetická fixture a střídavý měřič. |
| C: dávkový čtecí reader | IMPLEMENTOVÁNO | Reader bez statické datové cache, společné soupisky, dávkové ceny/kategorie a sdílená pravidla. Zapojený katalog/detail; transakce stále původní cestou. |
| D: shoda chování | PROBÍHÁ | Prvních 19 testů / 239 assertions PASS: celé varianty, vazby/členství, remízy, měny, tarify, dávky a přesná transakční SQL cesta. MariaDB kolace dosud NOT RUN. |
| E: měření přínosu | PROBÍHÁ | Měřič připravený, 12/100/500 variant jednoho či více produktů a tři negativní účty; 5 warmup / 30 střídavých vzorků, samostatné SQL/řádky/paměť. |
| F: regrese a předání | PROBÍHÁ | Cílené testy PASS; běží styl/PHPStan, připravuje se vlastní izolovaná MariaDB. Úplné testy a finální dokumentace zbývají. |

## Návrh a přejímací podmínky

- Samostatný reader pro jednu operaci; členství a účet se ověří znovu při
  každém volání, i mezi dvěma GET v jednom procesu. Transakce používá původní cestu.
- Zachovat databázové porovnávání kategorií/měn, neodvozovat shody v PHP
  přesným porovnáním řetězců tam, kde původně rozhodovala kolace SQL.
- Způsobilé soupisky jednou, produktové ceny a kategorie dávkově; pravidla
  sdílet mezi produkty se shodnými kategoriemi. Výpočet pro každou variantu
  zachová veřejnou cenu, produktovou výjimku v týmu a pořadí remíz.
- Před výběrem stránky zůstanou přepočtené všechny ceny potřebné k řazení.
  Přesun stránkování katalogu je samostatný budoucí zásah, nyní se nedělá.
- Srovnávací data zahrnou neověřený/neaktivní/odvolaný účet, datum začátku
  a konce vazby i členství, neaktivní tým/pravidlo, více týmů a kategorií,
  nulovou cenu a záporné výsledky slevy ořezané na nulu, měnu a různé ceny variant.
- SQL rozpočet pro 500 variant jednoho produktu: nejvýše 5 včetně metadat;
  pro různé produkty růst jen podle počtu omezených dávek, nikoli po variantách.
- Změny po readu: nové volání vrátí čerstvou cenu; transakční čtení/checkout
  nesmějí převzít dřívější slevu. Parita původního a nového výsledku je nutná.

## Důkazy a návaznosti

[Výsledkový protokol](verification/member-pricing-read-performance-20261107/RESULTS.md)
se aktualizuje s postupem. [Session handoff](SESSION_HANDOFF_MEMBER_PRICING_READ_PERFORMANCE_2026-11-07.md)
a [P11 registr](WORK_PLAN_CURRENT.md) odkazují na tento plán.
Budoucí etapy: samostatný katalogový výřez, přihlášené celé HTTP/bootstrap/nav,
browser metriky a širší profil podle [komplexního plánu](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md).
Začlenění, nasazení a provozní přejímka jsou samostatné skutečnosti;
aktuální produkci určuje pouze [CURRENT_STATE](CURRENT_STATE.md).
