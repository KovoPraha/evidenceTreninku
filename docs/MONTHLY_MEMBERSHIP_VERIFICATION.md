# Ověření měsíčních členství — 5. 11. 2026

Lokální implementace na základu `97b85bb5`; produkční nasazení ani aktivace neproběhly.

- Celá regresní sada: **2 046 testů, 39 141 assertions, bez selhání, 3 přeskočené testy prostředí**.
- Po závěrečném doplnění ochrany zastaralého produktu a návratu po přihlášení: **39 cílených testů / 629 assertions PASS**. Tyto testy zahrnují 18 scénářů nového měsíčního toku, účetní návaznost, bezpečné návraty a workflow kontrakty.
- Celý PHPStan na úrovni projektu: **PASS**, bez rozšíření výjimek. Dvě existující výjimky pro nedostupné DB připojení odstraněny přidáním skutečné kontroly připojení; počet potlačených hlášení klesl o 20. Kontrola baseline budget PASS.
- Povinná kontrola PHP stylu: **36 změněných PHP souborů, 0 chyb**. Existující dotčené soubory proto obsahují i požadované sjednocení formátu.
- MariaDB 10.4.32, nově vytvořená izolovaná DB: **PASS** pro skutečnou novou migraci a její opakování, více dětí, částečnou platbu, opakování pohybu, další měsíc, soukromé PDF a ukončení. Dva oddělené současné procesy přijaly tentýž pohyb právě jednou; dva generátory vytvořily tentýž měsíční předpis právě jednou.
- Kompletní nácvik migračního katalogu, zálohy a obnovy v izolované MariaDB: **307 tabulek PASS**.
- Kontrola aktuální dokumentace a lokálních odkazů: **PASS**.
- Playwright: desktop sjednání a mobilní přehled ze synteticky vyrenderované aktuální PHP stránky; mobilní předpisy změněny na karty, tlačítka QR/PDF nepřetékají.

Testy používaly syntetická data a testovací poštovní funkci/outbox. Neproběhl živý bankovní převod, produkční vydání nového dokladu, skutečné doručení nové upomínky ani přihlášený produkční zápisový průchod v prohlížeči. MariaDB 10.3/11.4 jsou přidané do CI matice, jejich vzdálený výsledek není součástí místního důkazu.

Nová migrace a tabulky nejsou aplikovány na produkci. Soukromá konfigurace, produkční data a přepínače plánovače nebyly změněny. Aktivace viz [provozní návod](MONTHLY_MEMBERSHIP_RUNBOOK.md).
