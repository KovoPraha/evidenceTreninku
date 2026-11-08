# P11-RHA — historie a měření profilu sportovce

8. 11. 2026: **parita a měření PASS, finální CI čeká; merge/deploy NOT RUN**.
[Plán](../../ATHLETE_PROFILE_READ_PERFORMANCE_2026-11-08.md),
[předání](../../SESSION_HANDOFF_ATHLETE_PROFILE_2026-11-08.md),
[PR #404](https://github.com/KovoPraha/evidenceTreninku/pull/404).

## Rozsah a metoda

Zmrazené skutečné route z69b69fa46349b0778980f3cbf3116be91b77cea6 jsou totožné
s předchozí produkcída152282. Měřené runtime3c6d139556e10c0829434b3f877e9063970dcbde;
[čtyři sourcehashy](integrated/integrated-summary.json) ověřeny proti Git blobům,
nikoli lokálním CRLF. Dokumentační/testovací následné commity runtime nemění.
[CI42519956849](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42519956849)
3/3 SUCCESS. Žádný produkční profil ani jeho přílohy nebyly čteny.

MariaDB10.3.39 a11.4.13: každá **3testy /651assertions**, SQLite3/611.
Všechny stránky rekonstruují původní hodnoty, scalar typy, pořadí, vlastní
poznámky/měření a úplné souhrny. Účast i vlastní měření bez účasti, cizí osoby,
sirotci/duplicitní vazby, poslední/prázdná stránka, roky, jednotky/null, case,
mezery i neznámé typy a čerstvé změny. Typy zůstávají v pořadí prvního výskytu.
Nezávislá původní LEFT JOIN/DISTINCT reference; nové dotazy i native prepares.

Stejná vlastní data100/1000/10000 tréninků (až90909 vlastních měřicích vazeb),
SQL result cache OFF,4zahřívací páry +17střídavých párů. Časy bez SQL profilace;
SQL/řádky/bindings a PHP peak delta samostatně. Checksumy beze změny a DROP jen
vlastních náhodných schémat. Všechny vzorky, med/p95 a malé případy jsou v důkazech:
[10.3 JSON](native/athlete-profile-native-10.3-athlete-profile-comparison.json.gz),
[10.3 JUnit](native/athlete-profile-native-10.3-athlete-profile-tests.xml.gz),
[11.4 JSON](native/athlete-profile-native-11.4-athlete-profile-comparison.json.gz),
[11.4 JUnit](native/athlete-profile-native-11.4-athlete-profile-tests.xml.gz).

## Nativní čtení10k — mediány ms

| Výběr | MariaDB10.3 před → po | MariaDB11.4 před → po |
| --- | --- | --- |
| Celá historie → prvních25 + úplné souhrny |482,55 →135,08|546,10 →202,52|
| Historie roku |187,67 →127,21|217,59 →200,19|
| Prázdný rok |11,97 →1,76|14,19 →2,70|
| Všechna měření →25 + úplné typy |350,76 →246,27|455,05 →432,04|
| Přesný typ s mezerou |361,24 →246,77|452,37 →451,01|

Historie110909→10277 načtených řádků; maxbindings10001→27 (rok28);
PHP peak delta180557768→4718696B. SQL3→4, tedy nepředstíráme méně dotazů.
Měření90909→35řádků (10souhrnů +25detailů), PHP peak delta210877960→75848B,
SQL1→2. Číselné ID/ordinal v SQL okně, binární agregace typu, úzká stránka
před širokými hodnotami; bez DDL/indexů/datové cache a změn globální DB paměti.
Typový filtr na11.4 je časově neutrální; hlavní přínos je omezená paměť/HTML.
Malý roční výběr100 tréninků má režii:2,29→2,52ms /2,84→3,57ms. Přiznáno;
velké historie a celá skutečná cesta se zlepšily, souhrny zůstávají úplné.

## Skutečné HTTP a Chrome

Vlastní plně migrovaná DB, standardní login12staff účtů a skutečné bearer303
výměny na čisté URL, nikdy podvržená session.616kladných GET včetně zahřátí,
**476časovaných HTTP** (14scénářů ×17 ×před/po), stejné doménové checksumy.
[HTTP před](integrated/profile-http-before.json.gz),
[HTTP po](integrated/profile-http-after.json.gz),
[souhrn](integrated/integrated-summary.json),
[úklid](integrated/runner-cleanup.json).

| Celý požadavek, medián | Před | Po | HTML před → po |
| --- | --- | --- | --- |
| Počáteční profilGET |129,019ms|53,066ms|6104585→12827B|
| Historie vše,1001 tréninků |111,312ms|32,711ms|8612127→208186B|
| Historie roku |37,010ms|32,496ms|2679144→208186B|
| Prázdný rok |5,888ms|4,415ms|82→82B|
| Měření vše |128,522ms plný profil|37,811ms lazy fragment|6104585→19362B|

Starý samostatný AJAX pro Měření neexistoval: jeho referencí jsou řádky plného
profilu, nikoli smyšlený endpoint. Nový profil +nová lazy odpověď jsou dva
požadavky a část práce se přesouvá na otevření záložky. Počet9000 se zachovává;
na počáteční stránce nejsou materializované skryté měřicí řádky.
Parita celého ostatního profilu po odebrání měřicího panelu a skriptů;
karty/řádky všech HTTP scénářů odpovídají příslušnému původnímu výřezu.

Chrome156: **78uložených průchodů** (3cold +10warm ×3scénáře ×před/po),
bez síťových stubů. [Před](integrated/browser-coach-before.json.gz),
[po](integrated/browser-coach-after.json.gz),
[interakce](integrated/browser-interactions.json.gz).
Warm historie Vše fetch→DOM+dvě animační smyčky1078,30→64,80ms;
default343,95→83,05ms. Dokument load default378,15→136,90ms.
Jde o konkrétní syntetickou cestu; není to LCP ani produkční SLA.
Další/předchozí bez překryvu, změna roku resetuje stránku, globální přesný typ,
filtr zachovaný na další stránce, změna typu resetuje stránku, savedtab/lazy,
read-only poznámky a390px pager PASS. Bez console errors/failed requests.
Screenshot desktop byl po reloadu zachycen během Bootstrap fade; samotné hodnoty
jsou správné, finální vizuální důkaz se zopakuje po dokončení přechodu.

## Přístupy a meze

Role přesně shodně: coach/sports_lead/registrar200, ostatních9účtů403.
Anonym3režimy403; requestid/sid nezmění bearer osobu. Expirace i revokace tokenu,
expirace grantu403 a neaktivní staff303/login PASS před i po. AJAX no-referrer /
no-store zachované; parent používá původní bootstrap policy. Note/reward writery,
CSRF a privatefiles beze změny; pozitivní nové ukládání poznámky v tomto read-only
průchodu NOT RUN, původní gate kryjí existující unit/integrační kontroly.
Přihlášené produkční UAT profilu a fyzický mobil NOT RUN. Původní WIP zachovaný.

## Zachovaná historie iterací

První úzká PHP agregace byla zamítnutá pro časovou regresi měření až23%:
[10.3](initial/athlete-profile-native-10.3.json.gz),
[11.4](initial/athlete-profile-native-11.4.json.gz).
Druhé okno s textovými typy snížilo paměť, ale90k na11.4 zůstalo pomalejší:
[10.3](window/athlete-profile-native-10.3.json.gz),
[11.4](window/athlete-profile-native-11.4.json.gz).
Finální číselné okno výše odstraňuje tuto regresi; žádný výsledek nebyl nahrazen
novým PASS. HTTP kalibrace opravila pouze fixture (explicitní grant) a očekávání
testů (parent policy, odlišení JS od skutečného writebutton, staff303/login).
Autorizace aplikace se neoslabovala. Merge a produkční release teprve po aktuálním CI.
