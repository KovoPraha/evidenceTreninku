# W10.3 — klubové finanční projekce a čtecí posouzení

Základ W10.2 `48a2b9fa8f7b33e2d46f5fbab8c5900c854e60dc`, sloučený PR #247.
Syntetické údaje, bez produkčních finančních zápisů nebo externích transportů.
Samostatný release bude doložen po CI; tento protokol jej nepředjímá.

## Projekce

Původní funkce je uložená v `AccountingClubBalanceBaseline.php`.
Nový iterátor indexuje příděly, spotřeby, převody a výplaty podle identity;
částky sčítá a odečítá v původním pořadí. Zachovává výběr poslední verze,
explicitně doložená salda, neznámé počátky i datové hranice. Array API pro PDF
zůstává, XLSX čte řádky postupně. Neprovádí SQL a nemění účetní zápisy.

První pokus překročil paměťový rozpočet u 100 000 záloh:
[160 069 104 B navíc](advances-initial.json). Šlo o zbytečné indexy spotřeb
a protistran, které ve výstupu nejsou. Konkrétní vlastněné identity nyní
omezují indexy; rozpočet 128 MiB nebyl navýšen. [První výdaje](expenses-initial.json)
byly pod limitem. [Původní selhání brány](initial-profile-failure.txt) není PASS.

Reprodukovatelný profil: `php tests/Support/AccountingClubReadProfile.php`;
1k/10k/100k pro výdaje, zálohy, odměny a pohledávky. Každý worker 512 MiB,
current 5 warmups / 30 vzorků, baseline jeden vzorek s limitem 15 s u 10k/100k.
Nedokončená baseline není extrapolovaná. Výsledek obsahuje shodu celého hashe,
kde baseline doběhla, a nezávislé očekávané identity/součty u každého řádku.
[Finální měření](club-read-profile.json).

| Doména | 1000 baseline, ms (1 vzorek) | 1000 current, ms (medián) | 100 000 current, ms | 100 000 navíc, B |
| --- | ---: | ---: | ---: | ---: |
| Výdaje | 462,79 | 7,47 | 734,67 | 91 187 224 |
| Zálohy | 883,99 | 9,58 | 1796,10 | 110 581 744 |
| Odměny | 2629,03 | 23,30 | 1813,10 | 121 564 936 |
| Pohledávky | 236,94 | 6,08 | 291,26 | 61 181 664 |

Baseline 10k/100k nedokončila žádnou doménu do 15 s. Současný výsledek všech
domén splnil 128 MiB navíc. Indexy mají větší paměť než kvadratická baseline;
jde o měřenou výměnu prostoru za čas. Profily běžely v různých procesech,
část při souběhu lokálních kontrol; nejsou střídavým izolovaným mikrobenchmarkem.

## Databázové posouzení

[FinancialReadAssessment](financial-read-assessment.json) používá celý katalog
migrací, zapnuté FK a vlastní náhodnou localhost DB odstraněnou v `finally`.
Dotazy/řádky se měří zvlášť od latence; 2 warmups / 10 vzorků s běžným PDO.

| Scénář | SQL / výsledné řádky | Medián ms | Dodatečná paměť B |
| --- | ---: | ---: | ---: |
| Ledger 1000 | 2 / 2 | 1,26 | 44 720 |
| Ledger 10 000 | 2 / 2 | 5,72 | 44 720 |
| Ledger 100 000 | 2 / 2 | 45,82 | 44 720 |
| Ledger 100 000 s hranicí evidence | 2 / 2 | 50,33 | 44 992 |
| Historie, 500 záznamů osoby / 500 cizích | 12 / 1509 | 16,08 | 997 448 |
| Historie, 5000 záznamů osoby / 5000 cizích | 12 / 15009 | 83,20 | 9 615 272 |
| Čekající zdroje 200 z 1000 | 2204 / 800 | 471,58 | 657 256 |
| Čekající zdroje 200 z 10 000 | 2204 / 800 | 490,53 | 657 256 |

Ledger používá index `account_id`, EXPLAIN je uložený v JSON. Ověřena hranice
účinnosti, okamžik evidence i oddělení druhého účtu. SQL zůstává beze změny:
přenáší jen dva řádky a nová migrace není doloženě potřebná.
Historie zachovává úplnost a cizí osoby nevrací. Zde jsou plně naplněné
předpisy/příjmy/příděly; objednávky jsou prázdné. Jejich funkční pokrytí zůstává
v předchozích R04 a doménových testech, ne v tomto měření.
Čekající zdroje předávají konkrétní metadata kandidát do [W10.4](../../REFACTORING_W104_2026-10-30.md).

## Ověření a hranice

Cíleně 37 testů / 1130 assertions PASS; [výstup](targeted.txt).
Porovnání kontroluje data i typy, duplicitní rozhodnutí/verze, cizí případy,
pozdější zápisy, explicitní záporné saldo a převod stejné zálohy s PHP_INT_MAX.
Přesuny řádků výdaje nejsou nový peněžní obrat. Formátování původního souboru
se ověřuje AST včetně literal HTML/SQL, nikoli jen PHP syntaxí.

Profil je čistá projekce, nikoli vykreslení velkého PDF. Dompdf nadále sestavuje
celý dokument v paměti; nezavádíme změnu obsahu ani neověřený alternativní renderer.
Tuto mez je nutné zachovat v závěrečné přejímce skutečně používaných objemů.
[Plný PHPUnit](phpunit.txt): 1850 / 37641, tři Windows POSIX skipy.
[PHPStan](phpstan.txt) bez chyby; styl a aktuální dokumentace PASS.
Linux bez skipů a CI obou MariaDB se doplní před přijetím. W11–W15 navazují;
lidské účetní převzetí zůstává samostatné.
