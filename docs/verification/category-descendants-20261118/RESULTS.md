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

Samostatný index NEPŘIJAT: [první běh](rejected-first/RESULTS.md). Nový kombinovaný kandidát index+sdílené uzly pouze v jednom volání,6→3SQL; původní cíle tree500/1000 a tolerance beze změny. Nové celé ověření čeká.

Kombinovaná outsideTX sonda6→3SQL: tree500180,75→47,15ms, tree1000692,54→85,80ms, celý stav/hash shodný. [Data](local-combined-probe.json), [sonda](local-combined-probe.php.txt). Po sondě přidán transakční guard: insideTX původní6SQL, původní kompatibilitní test beze změny. Finální native/HTTP CI čeká.

Finální kombinovaný kandidát:27testů/4094kontrol PASS, původní transakční SQL sekvence PASS, outsideTX6→3SQL/insideTX6→6/standaloneMenu3/pureMenu0. Styl8PHP/0chyb, source contract a docs80/5710odkazů/108archivů/0chyb PASS. [Příkazy](local-checks.json); nové CI čeká.
