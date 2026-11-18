# P11-CATSEL — průběžné ověření

[Plán](../../CATALOG_READ_PERFORMANCE_2026-11-07.md#p11-catsel--výběr-katalogu-po-catread-18-11-2026).
Lokální diagnóza canonical DTO,2warmup+7střídavých trojic, celé stavy/hash
shodné. [Data](local-probe.json), [sonda](local-probe.php.txt), [prototyp](local-prototype.txt).
Reprodukce: kopie sondy do output/catalog-selection-probe.php.txt v odděleném
checkoutu base4db60635, nikoli optimalizovaného main. Výkyvy kontrol patří
do dat; tohle není MariaDB přejímka ani produkční měření.
Vybrán membership filtr: tree2500/500744,99→243,35ms,tree5000/10002740,16→706,39ms.
Řadicí klíče zůstávají samostatný koncept. Produkční implementace navíc
validuje string kategorie a pro jiné typy ponechá úplně původní array_intersect.
Pouze tento blok, SQL3outside/6insideTX a původní transakční test stejné.

Před native výsledkem pevně dané cíle5000produktů/500uzlů a10000/1000,
metadata strom s kořenem LOCALHOST-0, celé native Select i category HTTP
anonym/zákazník≥20% na obou MariaDB. Ostatní≤20%+5ms/+20msHTTP,
ChromeFCP/ready≤20%+20ms. 9native profilů/46HTTP scénářů naDB,
355checksumů/8Chrome případů/176+48navigací/4pixelpáry. Fixture před
časováním ověří skutečný počet uzlů, celé aktivní/filtered produkty i potomky.
Native/HTTP/Chrome/merge/deploy NOT RUN; přihlášené produkční UAT NOT RUN.

Lokálně33testů/4194kontrol PASS, po odstranění redundantního is_array(product) dalších6dotčených/100kontrol PASS; typy kategorií/fallback stejné, source contract PASS. Styl8PHP/0chyb PASS. PHPStan/aktuální celé CI před mergem povinné, bez potlačení pravidla.
