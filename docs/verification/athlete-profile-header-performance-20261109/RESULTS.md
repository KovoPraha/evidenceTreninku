# P11-RAH — měření a shoda

Stav 9. 11. 2026: plán zapsán, měření kandidáta NOT RUN.
[Plán](../../ATHLETE_PROFILE_HEADER_PERFORMANCE_2026-11-09.md).
Baseline2c61df1495c570a5cfd9ba8032027595ab02de3c; předchozí RAP důkazy se nepřepisují.
Bez produkčních osobních dat; pouze vlastní syntetické schéma a bezpečné výstupy.

## Pracovní checkpoint — kandidát

Implementován jeden seskupený read; původní tři metody byte-preserved.
Parent při PDOException používá původní nezávislé fallbacky.
Lokální SQLite2testy/20assertions PASS (nullable/0roky, typy, duplicity,
orphans, cizí/nepřipojená měření, čerstvé změny a1SQL/početroků řádků).
PSR12 dotčených6PHP PASS; Python AST PASS; docs4973odkazů/108archivů/0chyb.
Lokální PyYAML není instalován, samostatný YAML parser NOT RUN; workflow
ověří GitHub Actions. Native benchmark, Chrome, aktuální CI a release čekají.
Nový runner zmrazí parent i reader z2c61df14. Vlastní HTTP servery,
cacheOFF, cleanup; samostatná diagnostika neovlivňuje prosté HTTP časy.
Původní výkonové protokoly/harness RAP beze změny. Paměť bez umělého prahu.

### První CI — statická analýza

Head d220ec79: PHPStan FAIL, historický undefined-pdo baseline24 nově25.
Žádné navýšení potlačení: explicitní kontrola skutečného PDO po db.php
zaručuje bootstrap kontrakt, původní24položková výjimka odstraněna.
Doménové chování a práva bez změny; native/HTTP měření běží dále pro první
kandidát a zůstane oddělené. Širší lokální sada17/807 PASS.

### První native42580408201 — dílčí PASS, celý běh FAIL

D220ec79: oběDB2testy/30assertions PASS; všechna1k/10k/30k párová HTTP
měření dokončena, shodné celé normalizované HTML. [Raw první vzorky](first-attempt/native-10.3/parent-http-30000.json.gz).
Celý job FAIL v druhém syntetickém fallback scénáři: opakované použití cookie
header-fault-before => login302. Oprava pouze harness: unikátní cookie label
pro každou chybějící tabulku a variantu; první fallback attendance už prošel.
První vzorky a cleanup zůstávají uložené, nejde o plný PASS.
Head07d90a18 PHPStan/style PASS; CodeQL JS stažení nástroje504 (infrastruktura).
Nový head opakuje všechny aktuální kontroly a celý benchmark/Chrome.
