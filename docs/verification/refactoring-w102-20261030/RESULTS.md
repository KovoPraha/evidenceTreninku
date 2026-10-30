# W10.2 — paměť kompletního XLSX

Základ: W10.1 `3648e8b6fdc562e560d9ee18430e1c7327b9238c`, sloučený
PR #246 do `360355fd30238c11fc7bdd608b7c30f5a0140c0c`. Syntetická lokální data;
žádné produkční účetní operace. Tento protokol zatím není potvrzení nasazení.

## Nález a výsledek

[Původní serializace](serialization-before.json) 1000 položek: 1595,5 ms,
55,1 MB peak. Při 10 000 i 100 000 skončila na limitu 256 MiB.
[První celé měření po změně](serialization-after.json):

| Pohyby / příděly / členění | Celý XLSX, ms | Celý PHP peak, B | Paměť nad snapshot, B | XLSX, B |
| --- | ---: | ---: | ---: | ---: |
| 1000 | 522,2 | 29 257 448 | 26 479 616 | 176 186 |
| 10 000 | 3582,3 | 43 313 192 | 26 478 056 | 1 530 627 |
| 100 000 | 35 709,4 | 202 177 624 | 39 638 224 | 15 106 181 |

Jedno měření na velikost v samostatném procesu, nikoli medián ani produkční
latence. Předchozí neúspěchy nemají domýšlený čas dokončení. Výstup roste
kvůli nededuplikovaným sdíleným řetězcům; při 1000 položkách 139 151 → 176 186 B.
Úplný snapshot stále zabírá paměť úměrnou datům; tato změna není streamování DB.

## Kontrakty

- Stejné názvy a pořadí listů, všechny hodnoty a typy buněk, celé styly,
  číselné formáty, textový VS, freeze pane, filtry a šířky sloupců.
- Porovnání s původním workbookem zahrnuje záporné částky, prázdné numerické
  listy, řetězec začínající `=`, Unicode, XML znaky, řídicí znak a CRLF.
- Průběžné Saldo zachovává původní pořadí aritmetiky; array API z W10.1 zůstává.
- Chyba v generátoru se propaguje a uklidí vlastní privátní spool.
- Snapshot, oprávnění, transakce, archivační kontrolní součty a writeři se nemění.

Reprodukovatelné měření: `php tests/Support/AccountingSerializationProfile.php`.
Každý worker má 256 MiB / 180 s, profil navíc kontroluje dodatečných 64 MiB,
velikost XLSX 20 MiB, počty řádků a nulový zbytek pracovních adresářů.
Výsledky finálního skriptu: [JSON](serialization-profile.json).

Cílená regrese včetně prázdné textové buňky: 14 testů / 976 assertions PASS.
[Plný lokální PHPUnit](phpunit.txt): 1848 / 37590, tři Windows POSIX skipy;
[PHPStan](phpstan.txt) bez chyby. [Finální cílené testy](targeted.txt) potvrzují
také opravu prázdného textu na původní prázdnou buňku. Styl a dokumentace PASS.
Linux bez skipů, CI obou MariaDB a release se doplní před přijetím řezu.
W10.3 navazuje historií, ledgerem a klubovými finančními projekcemi;
W11–W15 zůstávají otevřené. Lidské účetní převzetí není součást tohoto testu.
