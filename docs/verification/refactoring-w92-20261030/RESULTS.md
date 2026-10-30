# W9.2 — programový košík a skladové souběhy

30. 10. 2026. [Plán](../../REFACTORING_W92_2026-10-30.md).
Původní implementace načítala podmínky a metadata znovu pro každou položku.
[RED](cart-red.txt): 50 programů bez nároku na členskou cenu, 1762 SQL,
1108 dotazů na metadata a 400 čtení podmínek, proti rozpočtu dvou dávek.
Původní fixture tohoto RED ještě neobsahovala schválenou vazbu účtu 10;
následná matice ji výslovně doplnila a ověřuje skutečný cenový výsledek.

`shopCartDetail` používá jeden existující `ClubProgramReadContext` pouze
pro aktuální čtení aktivních programových položek. Zachovává celý výstup,
typy, pořadí, terms, availability_error, součty a fingerprint. Kontext se
nepředává do checkoutu ani do zápisů; zamčené kontroly zůstávají čerstvé.
Goods-only košík nezískává další SQL. Čas programového čtení je jednotný
pro daný průchod v Europe/Prague, jako u katalogové čtecí projekce.

## Migrovaná MariaDB

[Celá matice](cart-counts.json): 1/10/50 položek × goods/program × skutečná
členská cena/bez nároku. Vlastní náhodná DB, celý katalog migrací, FK zapnuté,
žádné produkční mutace. První vytvoření košíku je zvláštní zápis; profil
začíná až nad existujícím košíkem. Cena 10001 vs 12500 minor units se ověřuje
přímo, stejně jako prodejnost. Nové čtení po odvolání role, podmínek a varianty
se shoduje se zmrazeným baseline.

| 50 položek | SQL před → po | Řádky před → po | Podmínky před → po |
|---|---:|---:|---:|
| Program, člen | 1862 → 275 | 1609 → 539 | 400 → 2 |
| Program, bez nároku | 1762 → 175 | 1509 → 439 | 400 → 2 |
| Goods, člen | 362 → 362 | 309 → 309 | 0 → 0 |
| Goods, bez nároku | 262 → 262 | 209 → 209 | 0 → 0 |

Cross-offer kapacita byla posouzena: zůstává 100 cílených čtení pro 50
nabídek. Každá nabídka může mít jiné období, fyzické týmy a odpočet držení;
nevytváří se obecná cache sdílená se zápisy. Tato vlna přijímá odstranění
opakovaných metadat a terms; další sdílení kapacity se nyní nezavádí.
Stávající shared-capacity a checkout souběhy nadále kryjí deduplikaci,
expiraci a poslední místo. Členské cenové oprávnění se stále čte čerstvě.

První pokus s 30 opakováními všech baseline cest byl po překročení časového
rozpočtu přerušen ukončením pouze vlastního syntetického DB spojení;
`finally` odstranilo vlastní DB. [Záznam](interrupted-timing.txt) není
časový výsledek ani chyba produkce. CI používá kratší matici; SQL a shoda
dat jsou určující, nezaměňují se za HTTP/browser latenci.

[Dokončený profil](cart-profile.json), jedno zahřátí a pět střídavých vzorků
na obyčejném PDO: 50 programů s členskou cenou 6699,46 → 231,27 ms,
bez nároku 6861,69 → 202,56 ms. Max. přírůstek PHP paměti 487216 → 523424 B
(člen), 485216 → 523424 B (bez nároku), pod rozpočtem baseline +1 MiB.
Goods člen 356,94 → 329,10 ms, bez nároku 283,42 → 264,22 ms, paměť +504 B.
Malý vzorek je orientační; rozhodující je celý shodný výstup a počet SQL.
30 vzorků se po doloženém překročení rozpočtu původní varianty neopakuje.

## Skutečné souběhy

- [Sklad](inventory-race.json): 9 kontrol, dvě skutečná LOCK WAIT, poslední
  kus, opakovaný příjem a fyzická vratka.
- [Vratky](returns-race.json): 19 kontrol, nadlimitní požadavek odmítnut po
  odemčení, opakovaný příjem vrací tentýž pohyb, žádné nové peníze.
- [Výdej](fulfillment-race.json): 6 kontrol, opakovaný částečný výdej a výdej
  versus storno; celý katalog migrací a aktivní FK. Původní kopie `evidence_b1`
  nahrazena vlastní migrovanou DB. Sklad/vratky zachovávají menší doménové
  fixtures a jejich rozsah není vydáván za celý katalog.

Přenosný runtime používá pouze localhost a explicitně povolené náhodné
prefixy, uklízí vlastní DB, soubory a procesy. Žádné granty ani závislost
na lokálním B1 účtu. Formátování má AST důkazy v této složce, doménové
asercí souběhů zůstaly zachované.

## Brány

Cíleně 47 testů / 831 assertions PASS. [Plná Windows regrese](phpunit-windows.txt):
1844 testů / 36729 assertions, tři POSIX skipy; [PHPStan](phpstan.txt) bez nálezů.
Styl a dokumentace PASS. CI výsledného headu ještě navazuje.
Profily čtení jsou přesunuty do samostatné MariaDB matice, protože
dosavadní smoke job se s dalšími profily blížil desetiminutovému limitu.
Povinné scénáře ani obě verze DB se nevynechávají; jde o časnější část W14.
W10–W15 a browser přejímka zůstávají otevřené.
