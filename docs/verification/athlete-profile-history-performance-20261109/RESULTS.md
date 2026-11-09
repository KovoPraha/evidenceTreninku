# P11-RHI — výsledky a měření

Zahájení9. 11. 2026, parita/měření/CI/release NOT RUN.
[Plán](../../ATHLETE_PROFILE_HISTORY_PERFORMANCE_2026-11-09.md).
Baseline d666078e2480f16fbc5f1d5b716802054501d57e. Předchozí RHA/RAS/RAP/RAH
výsledky se nepřepisují; nové páry používají aktuální produkční history().

## Kandidát — lokální checkpoint

Upraveno pouze history(): EXISTS zachovává deduplikované vlastnictví,
detail znovu ověřuje scope i rok na vybraných25id; první výsledky se čtou
postupně, drží se nejvýše dvě stránky klíčů. Výpočet minut zůstává stejnýPHP
ve stejném pořadí. PDO buffering beze změny, paměť se teprve změří.
Reference celého readeru zmrazená z d666078e (jen přejmenovaná testovací třída).
SQLite původní3testy/611assertions a nové2/261 PASS. Nativní testy ověří navíc
emulated/native prepares i stringify režimy, finální výkon zatím NOT RUN.
PSR12 dotčených6souborů PASS, PythonAST PASS. Header/měření/parent/AJAX
endpoint/bezpečnost/writery jsou byte-nezměněné. Workflow zmrazí reader,
parent i AJAX endpoint a měří celé fragmenty i skutečný Chrome.

## První úplný kandidát f9acd99f — run42621207366

Obě MariaDB10.3/11.4 a Chrome PASS (testovací gate historie splněná).
[10.3](first-complete/native-10.3/integrated-summary.json),
[11.4](first-complete/native-11.4/integrated-summary.json),
[Chrome](first-complete/browser-summary.json). Všech5source SHA256 přesné proti Git blobům.
Native nové2/513 každáDB,SQLite2/261; po drobné úpravě filtru místní7/892 PASS,
PSR12 PASS, composer validate --strict PASS. SQL cacheOFF, zmrazené3aplikační
soubory z d666078e, stejná data,4warmup+17střídavých párů,3objemy/7scénářů.
1428časovaných AJAX odpovědí na obouDB; předešlé scoped parentGET jsou mimo časy.

| Tréninky / vlastní měření | HistorieHTTP10.3 před→po ms | HistorieHTTP11.4 před→po ms |
| --- | --- | --- |
|1000 /9000|19.735→7.622|28.004→8.106|
|10000 /90000|121.669→15.994|245.404→20.237|
|30000 /270000|467.989→37.002 (−92.1%)|757.841→47.597 (−93.7%)|

Ve30k scénáři všechny roky/aktuální rok/page2/last i malé/prázdné profily
shodnébyteceléfragmenty. Malé profily324→221ms a569→339ms,
prázdné317→222ms a568→349ms na stejné globálně velkéDB.
Neexistujícírok má malou regresi6.919→20.239ms /8.150→23.999ms:
v další iteraci se levný YEAR filtr umístí před EXISTS, žádné nové DDL.
Všechny původní vzorky se zachovávají, včetně této regrese.

SQL12→12 a30282→30282vrácenýchřádků; zrychluje vykonaná práce, ne početdotazů.
Diagnostika má samostatné workers, není v HTTP časech. PHPmanagedpeak
14529656→1560488B /14529640→1560632B (−89.3%). Není to RSS/paměť DB;
PDO buffering se nemění, bounded jsou PHP kolekce id, nikoli veškerá paměť ovladače.
Původní orderedPHPsoučet minut zůstává přesný včetně nestandardních délek.

52Chrome navigací3cold+10warm/scénář/variantu. Warm default dokumentTTFB
360.75→361.85ms neutrální, historie fetch→DOM+2frames791.90→428.50ms,
celá navigace1181.30→818.45ms (−30.7%). SavedMěření celá navigace
1578.60→1728.55ms (+9.5%), souběžná AJAXčást1190.45→1340.40ms;
nezamlčet: první UI průchod nemá univerzální přínos všech záložek.
Měření a parentSQL zdroje beze změny, souběh a worker scheduling je zatím
hypotéza, nikoli potvrzená příčina. Finální opakování doplní ResourceTiming
obou skutečných fetchů bez stubů nebo měnění aplikace a tyto meze vyhodnotí.

ObaPNG před/po byte-identické; vizuální kontrola čeká. Konzole/transport0,
filtry/stránky/roky/typy/záložky/390px PASS.12staffrolí/4povolené kontexty,
15byte-shodnýchfragmentvariant/DB, freshrevokace/expirace/scope/CSRF,
2nezávisléfallbacky parentu,11checksumů a kompletní vlastní cleanup PASS.
Přihlášené produkční UAT/produkční výkon NOT RUN. Nový head ještě potřebuje
celé vlastníCI i nový párový výkon; produkce/WIP zůstávají zachované.

## Finální integrovaný ecd47579 — run42623263818

[10.3](checked-head/native-10.3/integrated-summary.json),
[11.4](checked-head/native-11.4/integrated-summary.json),
[Chrome](checked-head/browser-summary.json). Zahrnuje přijatý main#412;
3baselineprofilové soubory z d666078e stejné jako v main/produkci4f92b225.
Všech5measured-source SHA256 ověřeno proti Git blobům ecd47579.

| Tréninky / vlastní měření | HistorieHTTP10.3 před→po ms | HistorieHTTP11.4 před→po ms |
| --- | --- | --- |
|1000 /9000|29.142→11.618|37.778→10.999|
|10000 /90000|192.198→27.345|313.875→27.133|
|30000 /270000|599.411→64.589 (−89.2%)|953.704→63.851 (−93.3%)|

Kontroly aktuálníhoroku574→49 /940→49ms, page2/last rovněž výrazně rychlejší.
Malý profil406→274 /757→443ms, prázdný405→283 /751→454ms na stejnévelkéDB.
Dřívější drobná regrese neexistujícíhoroku odstraněná:11.068→11.219ms
/10.699→10.953ms (neutrální). Pořadí levnéhoYEAR před EXISTS je doložené výsledkem.
SQL12→12, vrácenéřádky30282→30282. PHPmanagedpeak14529656→1561032B
/14529640→1561016B (−89.3%), oddělená instrumentace, ne RSS/DB paměť.
Celé fragmenty/parentHTML/JS shodné, normalizují se jen náhodné nonce/CSRF.

52Chrome navigací3cold+10warm/scénář/variantu, rawResourceTiming obou skutečných
fetchů v gzip. Warm default TTFB476.25→477.70ms a load532.45→537.15ms neutrální;
historie fetch→DOM+2frames1012.85→540.20ms (−46.7%), celá navigace1530.25→1058.25ms
(−30.8%). SavedMěření2234.60→2051.90ms (−8.2%), společnáAJAXčást1700.05→1540.50ms.
První savedMěření+9.5% zůstává zachované: výsledek záložky Měření je proměnlivý,
SQL této projekce se nemění, neslibovat její vlastní stabilní zrychlení.
Souběh/worker scheduling zůstává hypotéza; ResourceTiming sám příčinu nedokazuje.
Default historie má v obou bězích konzistentní přínos kolem31% celé navigace.
PNG v obou velikostech byte-identické před/po i vůči prvnímu vizuálně
zkontrolovanému běhu; dlouhé syntetické texty na390px nejsou novéUX ani fyzický UAT.

Native2/513 každáDB,SQLite2/261 a místnícelkem7/892, composer validate --strict,
PSR12 PASS.1428časovanýchAJAXodpovědí (714/DB) +52Chrome;4warmups oddělené,
7scénářů/3objemy/3scopedprofily, předchozí parentGET nejsou v časech.
12staffrolí/4povolené kontexty,15rawfragmentvariant/DB, freshrevokace/expirace/
scope/CSRF,2fallbacky parentu,11doménovýchchecksumů a kompletníowncleanup PASS.
Konzole/transportchyby0; filtry/roky/typy/pagery/záložky/390px PASS.
Přihlášené produkční UAT/produkční výkon/fyzický mobil NOT RUN, syntetická čísla
nejsou produkční SLA/LCP. CeléCI a produkční release mají samostatný protokol.
