# W10.1 — indexované finanční řádky účetního exportu

30. 10. 2026, základ `3b2c027a115a51bf43bc6cfac59380e2cbaf5f96`.
[Plán W10](../../REFACTORING_W10_2026-10-30.md).

`accountingExportSnapshot` pro každý pohyb procházel všechna členění,
pro každý účet všechna odsouhlasení. List Saldo opakovaně procházel všechny
pohyby, příděly, storna a výplaty. Nové čisté projekce indexují stejný již
načtený snapshot. SQL, konzistentní transakce, autorizační zámky, soubory,
otisky a finanční zápisy se nemění.

Pořadí řádků i aritmetiky zůstává původní; hodnoty se nesčítají v jiném
pořadí. Test pokrývá i přetečení integeru do floatu, duplicitní ID a legacy
NULL/0. Zachováno pozdní otevření účtu (neznámý počátek), skutečné vratky,
záporné příděly, zrušené rozhodnutí a poslední odsouhlasení v pořadí snapshotu.

## Výpočet a paměť

`php tests/Support/AccountingExportReadProfile.php`, [výsledky](read-profile.json).
Syntetický neměnný snapshot, izolované procesy, bez DB a produkčních mutací.
Nejde o výkon celé serializace XLSX ani o produkční latenci.

| Počet pohybů | Původní ms | Nové ms | Původní peak B | Nový peak B |
|---:|---:|---:|---:|---:|
| 1000 | 150,04 | 0,83 | 269160 | 546504 |
| 10000 | 24149,54 (jeden vzorek) | 9,66 | 2750320 | 5705544 |
| 100000 | nedokončeno, limit 15 s | 106,70 | nezměřeno | 63177896 |

Nová varianta a baseline 1000 mají pět zahřátí + 30 vzorků, seskupených
podle izolovaného workeru; nejde o střídavé měření obou variant. Baseline
10000 má pouze jeden vzorek: [první opakovaný běh](initial-profile-budget.json)
překročil 300 s. Baseline 100000 není extrapolována. Celé hashe se porovnávají
tam, kde baseline doběhla; pro všechny velikosti se navíc přímo ověřuje
každý finanční řádek a analytický součet. Rozdílná metodika neumožňuje SLA
ani přesný odhad p95; rozhodující je odstranění kvadratických průchodů.

Paměť navíc odpovídá lineárním indexům a výsledným řádkům. Nárůst nad 20 %
byl posouzen: seznamy hodnot uchovávají původní aritmetické pořadí, místo
levnějšího přeskupeného SUM, který by mohl změnit výsledek na mezích čísel.
Při 100000 řádcích je přírůstek 60,25 MiB, pod explicitním limitem 128 MiB.
To neprokazuje paměť celé tvorby XLSX; ta navazuje v další části W10.

## Správnost a přejímka

[13 cílených testů / 626 assertions](targeted-tests.txt): samostatné finanční
příklady, frozen baseline, celý snapshot a všechny listy/buňky/datové typy
a formáty XLSX, archivní idempotence, role, selhání storage, skladové výstupy.
Charakterizační porovnání celého snapshotu/XLSX prošlo před zapojením
nových projekcí i po něm. Formátování všech měněných legacy souborů je
doložené AST proti verzím po funkční změně, včetně literálního HTML/SQL.

[Plná Windows regrese](phpunit-windows.txt): 1845 testů / 37198 assertions
na výše uvedeném základu, tři očekávané POSIX skipy. [PHPStan](phpstan.txt)
bez nálezů, styl a dokumentace PASS. CI výsledného headu ještě navazuje.
W10.1 neuzavírá celou W10:
zbývá profil úplné serializace/exportu, osobní finanční historie, ledger
a posouzení dalších finančních přehledů. W11–W15 a lidská účetní přejímka
zůstávají oddělené. Integrace ani skutečné finanční operace se neaktivují.
