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

## První úplný kandidát1a0e7ff4 — PASS, run42581044288

[10.3](first-complete/native-10.3/integrated-summary.json),
[11.4](first-complete/native-11.4/integrated-summary.json),
[Chrome](first-complete/browser-summary.json). Obě native2testy/30assertions,
SQLite2/20, širší místní17/807 PASS. Všechny4source SHA256 porovnané s Git bloby.
Zmrazené parent i reader z2c61df14, obě verze na stejných vlastních datech,
SQL cacheOFF;4warmup+17střídavých párů,1k/10k/30k,3profily/objem.
612časovanýchHTTP (306/DB), diagnostické požadavky oddělené od časování.

| Tréninky / vlastní měření | ParentHTTP10.3 před→po ms | ParentHTTP11.4 před→po ms |
| --- | --- | --- |
|1000 /9000|43.329→21.537|46.499→20.924|
|10000 /90000|280.188→99.627|415.808→143.368|
|30000 /270000|980.180→328.687 (−66.5%)|1282.707→430.537 (−66.4%)|

Malé/prázdné profily jsou na téže globálně velkéDB, raw controls v gzip;
žádná podstatná regrese. Celé normalizovanéHTML/JS shodné, jen náhodné
nonce/CSRF normalizované. SQL17→15/vrácenéřádky15→13. PHPmanagedpeak
1249920→1261976B /1249904→1261960B (+12056B); žádná podstatná paměťová
úspora se neslibuje. Není to RSS ani paměť DB. Nový read vrací jen roky+součty.

52Chrome navigací: default i uloženáMěření,3cold+10warm/variantu.
Warm default TTFB1312.25→452.20ms, load1367.70→507.85ms,
celá navigace do historie DOM+2frames2331.80→1469.10ms (−37.0%).
Warm savedMěření TTFB1313.35→454.10ms, celá navigace3251.30→2019.20ms (−37.9%).
Samotný default AJAX981.15→979.70ms neutrální; savedAJAX1897.60→1528.25ms
je proměnlivá souběžná komponenta, SQL obou AJAX zdrojů byte-nezměněné.
Cold hodnoty uložené zvlášť v JSON, nepovažovat za warm ani za LCP/SLA.
Filtry/roky/typy/pagery/záložky/390px PASS, konzole/transport chyby0.
[Desktop](first-complete/native-11.4/athlete-header-after-desktop.png),
[390px pager](first-complete/native-11.4/athlete-header-after-mobile.png):
vizuálně zkontrolované; oba PNG před/po byte-identické. Dlouhý syntetický
text na úzkém viewportu není tímto změněn, není to úplné mobilní UX/UAT.

12staffrolí,4povolené kontexty3staff+bearer,15rawfragmentvariant/DB shodné.
Čerstvá revokace/expirace/scope/inactive303, invalid/validCSRF/logout PASS.
Oba skutečné chybové scénáře (chybějící attendance či measurement link table)
vrací200 a shodný celý DOM, tabulka obnovena finally.11doménovýchchecksumů
shodných; vlastníschema/user/config/cookies/workers/bridgealias cleanup PASS.
Žádné privátní configy, cookie, tokeny, rawbody ani logy v artefaktech.

CodeQL actions na tomto headu FAIL výhradně při stažení CLI HTTP504;
GitHub odmítá opakování managed workflow (403/re-request404). Nedělá se
výjimka: tento výsledkový checkpoint vytvoří nový head se všemi novýmiCI.
Původní neúplné měření a opravné commity zůstávají zachované.
Aktuální merge/deploy čeká, produkční výkon/přihlášené UAT NOT RUN.

## Finální b8f91037 — opakované native/HTTP/Chrome PASS

[Run42582085946](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42582085946),
[10.3](checked-head/native-10.3/integrated-summary.json),
[11.4](checked-head/native-11.4/integrated-summary.json),
[Chrome](checked-head/browser-summary.json). Předchozí vzorky se nepřepisují.
Všechny4measured-source a2baseline-source SHA256 přesně odpovídají Git blobům;
baseline parent/reader2c61df14 je byte-identický s předchozí produkcí ca5fc219.
Původní reader po vyjmutí nového header() je byte-identický s baseline.

| Tréninky / vlastní měření | ParentHTTP10.3 před→po ms | ParentHTTP11.4 před→po ms |
| --- | --- | --- |
|1000 /9000|44.184→22.312|50.848→23.804|
|10000 /90000|293.043→101.964|439.730→152.285|
|30000 /270000|1009.892→326.150 (−67.7%)|1349.797→455.233 (−66.3%)|

Kontrolní malý/prázdný profil stále ve stejné velkéDB; vzorky v rawgzip.
Žádná podstatná regrese. SQL17→15, vrácenéřádky15→13; PHPmanagedpeak
1249920→1262032B /1249856→1261960B (asi+12kB). Nejde o paměťovou optimalizaci.
SQL diagnostika má oddělené instrumentované workers a není součástí HTTP časů.

Finální warm Chrome default TTFB1376.60→475.35ms, load1437.50→532.40ms,
navigace do historie2424.55→1520.05ms (−37.3%). SavedMěření TTFB1365.45→478.25ms,
load1412.10→531.10ms, celá navigace3010.15→2110.40ms (−29.9%).
Default AJAX1004.10→1006.70ms a savedAJAX1583.95→1573.25ms neutrální:
nevydávat variabilitu prvního běhu za zrychlení SQL fragmentů.
Oba screenshoty před/po i vůči vizuálně zkontrolovanému prvnímu úplnému běhu
byte-identické; kontroly UI/390px, konzole/transport0 PASS.

Opakování612časovanýchHTTP+52Chrome, přesný normalizovanýHTML/JS,
15byte-shodnýchfragmentvariant/DB,12staffrolí,4povolené kontexty,
revokace/expirace/scope/CSRF,2skutečné nezávislé chybovéfallbacky a11checksumů PASS.
Vlastníschema/user/config/cookies/bridgealias/workergroups cleanup PASS.
Native2/30 každáDB, širší místní17/807; samotný SQLite header2/20.
CodeQL na finálním headu všechny3jazyky i souhrnná kontrola SUCCESS.
Úplné obecné CI a release mají samostatný produkční protokol; produkční
výkon/přihlášené UAT/fyzický mobil NOT RUN. Syntetická čísla nejsou SLA/LCP.
