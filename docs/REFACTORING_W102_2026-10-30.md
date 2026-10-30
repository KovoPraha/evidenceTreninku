# W10.2 — celá serializace a finanční přehledy

Příprava před měřením 30. 10. 2026. W10.1 řeší pouze čisté finanční řádky.
Změřit nyní skutečnou tvorbu XLSX 1000/10000/100000 pohybů s příděly
a členěním, čas, PHP peak, velikost souboru a počet řádků v každém listu.
Izolované procesy, limit 256 MiB a 180 sekund; překročení je nález, nikoli PASS.
Úplná shoda buněk a datových typů z W10.1 zůstává povinná.

Pokud paměť roste nepřijatelně, prověřit dočasné privátní úložiště buněk
podporované používanou knihovnou, s návratem globálního nastavení a úklidem
v úspěchu i při výjimce. Nejprve změřit, nezavádět nový formát ani závislost
bez důvodu. Zachovat formáty, textové VS, ochranu proti formulím a archive checksums.

Doplnit migrované měření ledgeru 1000/10000/100000 a osobní finanční historie
včetně cizích osob. Prověřit zbývající finanční projekce a zdrojové fronty;
rozhodnutí o změně podložit skutečnou složitostí/limity a doménovými kontrakty.
Žádné produkční účetní operace, provider aktivace nebo lidské UAT.

## Změna po měření

Původní celá serializace překročila 256 MiB při 10 000 i 100 000 pohybech.
Samotná cache buněk stále ponechává souřadnice všech buněk v paměti knihovny.
Proto tento řez používá malý workbook se styly z téže knihovny a průběžný
zápis dat listů a sdílených řetězců. Tím nezavádí nový formát ani závislost.
Sdílené řetězce se nededuplikují: výměnou za větší soubor nevzniká další
neomezený index v paměti. Typ text, včetně počátečních nul a řetězců začínajících
rovnítkem, zůstává zachován. Původní normalizaci řetězců poskytuje knihovna.

Pracovní soubory vznikají v náhodném adresáři s oprávněním 0700 vedle cílového
privátního exportu; uzavření v `finally` uklízí úspěch i výjimku. Není to
garance úklidu po násilném ukončení procesu nebo havárii hostu. Zápis účetních
dat a transakční snapshot se nemění. Rollback je návrat kódu, bez DDL.

Rozpočty automatického testu: proces 256 MiB, dodatečná paměť serializace
64 MiB, 180 s na velikost, XLSX do 20 MiB pro syntetických 100 000 pohybů.
Počty řádků a úplná shoda buněk/stylů na doménové fixture jsou povinné.
Výsledky a omezení: [protokol](verification/refactoring-w102-20261030/RESULTS.md).
Finanční historie, ledger a zbývající projekce pokračují samostatným W10.3.
