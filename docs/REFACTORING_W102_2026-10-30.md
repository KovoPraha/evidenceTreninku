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
