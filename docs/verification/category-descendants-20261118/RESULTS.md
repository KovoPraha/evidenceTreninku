# P11-CATREAD / CATDESC — průběžné ověření

18. 11. 2026 Europe/Prague. [Plán](../../CATALOG_READ_PERFORMANCE_2026-11-07.md#p11-catread--další-čtení-kategorií-18-11-2026).
Lokální diagnóza obou variant nad celým výběrem a přesná shoda stavu/hash
v [datech](local-probe.json), [sonda](local-probe.php.txt), [prototyp](local-candidate.txt).
Sonda se reprodukuje kopií do output/category-read-probe.php.txt v odděleném
checkoutu baseline d92fd790, nikoli optimalizovaného main. Vybrán indexovaný
BFS veřejného shopCategoryDescendants; sdílené uzly zůstávají koncept.
Pouze tento helper, celý zbytek modulu zamčený. Žádné SQL/cache/limit/ceny/
prodejnost/writer změny. Šest SQL celého výběru zůstává. Paměť indexu zveřejnit.
Frozen strict oracle připíná původní helper i celý Select z tohoto main.

Před nativním výsledkem pevně dané cíle tree500/tree1000 při výběru kořene:
≥20% zisku celého nativního výběru a celého category HTTP anonym/zákazník
na obou MariaDB10.3/11.4; ostatní≤20%+5ms native/+20msHTTP. Chrome
FCP/ready≤20%+20ms. Deset nativních profilů,36HTTP scénářů/DB,
355checksumů,8Chrome případů/176měřených+48zahřívacích navigací/4pixelpáry.
Native/HTTP/Chrome/merge/deploy NOT RUN; přihlášené produkční UAT NOT RUN.

Lokálně26testů/4086kontrol PASS, source contract PASS, styl7PHPsouborů/0chyb PASS, kontrola dokumentace80řádků/5706odkazů/108archivů/0chyb PASS. PHPStan dobíhá, nativní/HTTP/Chrome přejímka bude v PR.
