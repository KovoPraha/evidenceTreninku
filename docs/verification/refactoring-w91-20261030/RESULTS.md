# W9.1 — dávkové čtení skladového přehledu

Základ `46ffede1c368d33c25c524f967be02f2040cb1a9`, 30. 10. 2026.
[Plán](../../REFACTORING_W9_2026-10-30.md).

Původní výpis prováděl kontrolu tabulky a čtení zůstatku pro každou variantu.
[RED](red-budget.txt): 537 variant vyvolalo 1074 SQL proti rozpočtu 3.
Čtecí helper nyní používá jednu kontrolu tabulky a dávky nejvýše 400 ID.
Vrací stejná pole, typy, pořadí, duplicitní varianty i chybějící zůstatky.
Každé volání čte aktuální data; writeři nepoužívají tento přehled.

Filtr zachovává PHP `mb_stripos`, diakritiku a doslovné `%`, `_`, `!`.
Při čtení variant uchovává pouze odpovídající řádky. Stále projde celý
databázový výsledek, protože změna na SQL LIKE by měnila kolaci a výsledky.
POST, CSRF, čerstvé role, výběr varianty a finanční formuláře jsou zachovány.

## Celá stránka na MariaDB

`EVIDENCE_MARIADB_SMOKE_PORT=33426 php tests/Support/InventoryPageProfile.php`
vytváří vlastní náhodnou DB, aplikuje celý katalog migrací se zapnutými FK
a po dokončení ji odstraní. [Výsledky](page-profile.json): 0/100/1000/10000
variant × catalog_manager/finance_manager × bez filtru/jeden výsledek/prázdno.
Skutečný GET controller a celé tělo stránky mají shodné HTML a view data.
Pouze autentizovaný bootstrap a nezměněná sdílená hlavička jsou nahrazené;
nejde o HTTP/browser test ani lidské UAT. Nonce a náhodný request key jsou
při porovnání normalizované. Žádné produkční mutace ani externí transporty.

| Výpis | SQL před → po | Medián ms před → po | Paměť B před → po |
|---|---:|---:|---:|
| 100, katalog | 208 → 10 | 50,05 → 9,38 | 606640 → 691760 |
| 10000, katalog | 20008 → 34 | 4710,92 → 178,76 | 13770096 → 13777864 |
| 10000, finance | 20009 → 35 | 5008,33 → 165,80 | 8187152 → 8194920 |
| 10000, jeden výsledek, katalog | 10 → 10 | 73,71 → 105,97 | 6129672 → 1033728 |

Časy v tabulce jsou průzkumné, po warmupu pět střídajících se měření na obyčejném PDO;
SQL se počítá odděleně. Úzké hledání není vždy rychlejší: hlavním přínosem
je nižší PHP paměť. Menší výpis může potřebovat o 85 KiB více kvůli dávce.
Rozpočet +1 MiB odpovídá omezené dávce s dlouhým evidence polem; nejde
o neomezené duplikování všech zůstatků. Prázdný výpis nečte zůstatky.

Kvůli pomalejšímu filtrovanému vzorku bylo měření zopakováno s `--extended`:
[pět zahřátí a 30 střídavých vzorků](page-profile-extended.json), 10000 variant,
obě role a všechny tři filtry. Celý katalog 4873,58 → 180,76 ms, finance
5031,83 → 185,83 ms. Jeden výsledek katalog 97,41 → 93,40 ms, finance
101,50 → 94,98 ms. Prázdné hledání 91,72 → 94,68 / 92,13 → 97,14 ms:
mírný náklad řádkového PHP filtrování, pokles paměti 6129672 → 1033792 B.
Původní výrazné zpomalení jednoho filtru se ve větším vzorku nepotvrdilo.
Přijato pro odstranění N+1 a omezení paměti; žádný příslib univerzálního
zrychlení hledání ani produkční SLA. HTML a data zůstala shodná ve všech vzorcích.
CI ponechává kratší úplnou matici jako funkční/SQL/paměťovou regresi.

## Ověření

Behaviorální test a dosavadní navigace: 6 testů / 57 assertions PASS.
Plná Windows regrese: [1844 testů / 36729 assertions](phpunit-windows.txt),
tři očekávané POSIX skipy; [PHPStan bez nálezů](phpstan.txt).
Formátování stránky má [AST důkaz](page-ast.txt) proti
[verzi po funkční změně před formátováním](page-before-format.txt),
včetně zachování literálního HTML a SQL. Baseline stránky je samostatně
zmrazený v `tests/Support/InventoryPageBaseline.txt`.
Profil je přidán do CI MariaDB 10.3/11.4. Plná regrese, PHPStan a výsledný
CI head musí projít před sloučením. W9.2 a další části plánu zůstávají otevřené.
