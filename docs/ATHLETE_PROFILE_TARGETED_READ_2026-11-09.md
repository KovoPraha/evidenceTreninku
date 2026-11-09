# P11-RIX — cílené čtení profilu podle skutečného schématu

9. 11. 2026. Stav: K OVĚŘENÍ, ownership YEAR implementace v draftu #425. Vlastník: vývoj/Codex.
[Registr](WORK_PLAN_CURRENT.md), [předání](SESSION_HANDOFF_ATHLETE_TARGETED_READ_2026-11-09.md),
[důkazy](verification/athlete-profile-targeted-read-20261109/RESULTS.md).

## Zadání, oprávnění a základ

Aktuální pokyn vlastníka: další optimalizace s největším přínosem podle analýzy,
striktní shoda a párové před/po; pouze při PASS commit, merge a protected deploy.
Zachovat WIP, průběžný plán i session handoff v repu. Oprávnění zahrnuje
nutnou čtecí diagnózu a syntetické testy, nikoli role/platby/importy nebo
převod skutečných dat. Produkční UAT evidovat samostatně, import/UAT false.

Base main b66cc059b7064081ec1ef5eddd8dec2ba81d55ec, poslední runtime17110a7f,
deploy42679050552 SUCCESS. Rozdíl main/runtime pouze dokumentace.
Větev codex/athlete-profile-targeted-read-20261109, izolovaný worktree
C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107.
Původní C:/xampp/htdocs/evidencePavel, větev codex/fio-auto-reconciliation-recovery,
WIP kis_rosters_admin.php SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 beze změny.

## Výběr a zjištěná nejistota

Původní analýza P11 priorit1–4 je již implementovaná v navazujících řezech;
neopakovat jejich úspory. Návaznost zde: cílené čtení/tréninkové projekce a
EXPLAIN/indexy (body4/5 původní analýzy). Současná RHMS diagnóza ukazuje
souhrn325/540ms versus detail5/6ms, parent/historie stovky ms. Malé/empty
profily přitom platí skoro stejný průchod klubem. Bootstrap jednotlivé SQL
jsou v této diagnóze nízké jednotky ms. To je nejsilnější současný kandidát,
nikoli tvrzení o návštěvnosti nebo měřeném produkčním výkonu.

Zásadní nejistota: poslední plně migrovaná syntetická fixture má pouze PK
na mereni_zaznamy a trenink_mereni, zatímco database/local-demo-schema.sql
obsahuje idx_sportovec a idx_mereni. Nelze tvrdit, že indexy chybí produkci,
ani podle neúplné fixture plánovat duplicitní produkční DDL. Dřívější
syntetické výsledky zůstávají platné pro tehdy výslovně doloženou fixture;
nejsou výkonem produkce. Všechny staré raw/schema/statistiky zachovat.

## Postup a brány přijetí

1. Čtecí metadatová inventura pěti sportovních tabulek přes chráněný workflow:
   treninky, trenink_sportovec, trenink_mereni, mereni_zaznamy a sportovec_poznamka.
   Jen definice sloupců/indexů, engine, odhady kardinality a verze serveru.
   READ ONLY transakce, žádné hodnoty osob/poznámek/měření, produkční COUNT
   historie, EXPLAIN ANALYZE, ANALYZE, DDL nebo změna nastavení/cache.
   Pomocné soubory jen do soukromého deploy adresáře a přesně vyjmenovaný úklid.
   Podpůrný CLI/workflow merge po vlastních testech/CI bez deploye aplikace;
   potom spustit z main. Příprava tohoto nástroje není výsledná optimalizace.
2. Podle metadat sladit pouze vlastněnou syntetickou fixture před OBOU
   variantami; metadata a rozdíly zveřejnit. Žádná úprava aplikace kvůli
   chybějícím fixture indexům. Nové produkční indexy zatím nejsou vybrané.
3. Zmrazit aktuální reader/parent/endpoint jako oracle. Celý bootstrap
   diagnózy na10.3/11.4 s reprezentativními indexy, small/old/empty/velký,
   header/historie/Měření. Vybrat konkrétní nejdražší SQL podle těchto dat.
   Kandidát: nejprve vlastněné klíče a potom data místo full-club EXISTS
   průchodu; zachovat přesné PHP sčítání délek i pořadí/pagination/scope.
4. Striktní plné návratové objekty/PHPtypy a celé HTML/JS proti baseline,
   owner/foreign/orphan/duplicate/NULL/zero datum, signed64, exact typy,
   všechny filtry/stránky, čerstvé změny, role/token/revokace/CSRF a canonical
   checksumy. Žádná persistentní cache oprávnění nebo změna writerů.
5. Párové celé HTTP před/po na stejné DB: 1k/10k/30k tréninků, 17 střídavých
   párů po4warmupech, cacheOFF a společné čerstvé statistiky. Hlavní cílová
   cesta alespoň20% úspora na obou DB, kontrolní scénáře bez významné regrese
   (po< před*1.2+20ms); výsledky všech scénářů zveřejnit. SQL/EXPLAIN a PHP
   profiler oddělit od neinstrumentovaných časů. Skutečný Chrome default/
   uložené Měření desktop/390px, konzole a snímky; žádné mock requesty.
6. Všechna aktuální CI SUCCESS, exact testovaný/sloučený strom a runtime,
   pak protected deploy z main s úplnou ověřenou zálohou, migrace/VS/config/
   smoke a veřejné čtecí před/po. Finální důkazy samostatným docs PR bez
   druhého nasazení. NOT RUN zůstává viditelné, samotný support PR nestačí.

Další krok: implementovat/testovat metadatový nástroj, CI/merge a read-only
produkční inventura; poté konkrétní nativní diagnóza a výběr optimalizace.
Nový app merge/deploy: NOT RUN. Původní produkce a WIP zachované.

## Průběžný stav — metadatový support

Implementovaný CLI guard + metadata-only export a chráněný main-only workflow.
SQLite2/30 PASS, source/syntax/style a sanitizovaný invalid-target PASS.
Native2DB testy jsou přidané do standardních smoke jobů. Support PR/CI/merge
čekají; webové readery a produkční aplikace stále beze změny. Nesmí se
zaměnit dokončení tohoto nástroje za dokončení uživatelské optimalizace.

## Průběžný stav — inventura PASS, nová diagnóza

Support [#424](https://github.com/KovoPraha/evidenceTreninku/pull/424) sloučený
jako ff182971, všech11 CI SUCCESS včetně nativních metadatových testů obouDB.
[Inventura42704641179](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42704641179)
SUCCESS včetně přesného úklidu. Žádný app deploy. [Bezpečný JSON](verification/athlete-profile-targeted-read-20261109/production-schema.json)
potvrdil idx_sportovec, idx_mereni, idx_trenink_poradi, fk_ts_sportovci,
unique_note a trenink_id; treninky má jen PK. Server10.3.39, odhady1667/2495/17/17/10
řádků; nejsou přesné počty. Doménové řádky se nečetly, DDLfalse.

Nová větev codex/athlete-targeted-read-optimization-20261109 z ff182971;
původní WIP zachovaný. Další krok: cílený syntetický diagnostický harness,
indexy pěti tabulek podle inventury před oběma variantami, parent/historie/
Měření pro velký/small/old/empty profil. Zachovat původní harness i výsledky.
Fixture zůstává adversariální (VARCHAR typ proti produkční ENUM6, signed/null
hrany); samotná shoda indexů není úplnou replikou produkce. Diagnózu a
zátěžové výsledky výslovně oddělit od produkčního výkonu. SQLite průzkum
prostého UNION předvýběru ukázal regresi husté historie; není vybraným řešením.


## Výběr po nativní diagnóze c9

42705957545 SUCCESS oběDB, zdroje before/after stejné, 24 úplných bootstrap
protokolů, topologie indexů shodná, cleanup PASS. [Bezpečné raw10.3](verification/athlete-profile-targeted-read-20261109/diagnostic-c9/native-10.3/integrated-summary.json),
[raw11.4](verification/athlete-profile-targeted-read-20261109/diagnostic-c9/native-11.4/integrated-summary.json).
Jednotlivý instrumentovaný velký souhrn288/1021ms, historie241/305ms,
header268/289ms. Small/old/empty souhrny naopak 0–11ms: původní chybějící
indexy skutečně zkreslovaly sparse případ. Nelze přenášet staré absolutní časy.

SQL selector průzkum7párů/2warmupy: prostý ownedJOIN hustou all-historii
nezrychlil (249→259 a307→314ms); IN zásadně regresuje sparse profil.
Oba nevybrané. Největší zbývající náklad je velká agregace měření; další
krok porovnat přesné stejné grouped SQL s owner-first/link-first pořadím
spojování a narrow materializací. Zatím žádná app změna ani nový produkční
index. Výběr zůstává podmíněný paritou a úplným párovým HTTP gate.

Doplňující koncept k ověření: přesnou skupinu typu reprezentovat HEX(typ),
vracet UNHEX(HEX(typ)); kódování je jednoznačné pro NULL/prázdné/bajty a
trailing spaces. Nejde o změnu kolace nebo toleranci vstupu. Ověřit plnou
shodu i Created_tmp_disk_tables, aby bylo jasné, zda BINARY skupina vyvolává
diskovou temporary tabulku. Dokud nebude nativní důkaz, jde jen o hypotézu.


## SQL varianty nevybrané; koncept pokrývajícího indexu

42706823562 a42706996238 oběDB SUCCESS, úplná summary parita všech4SID.
Žádná variantní SQL změna nedává20% přínos na obouDB. HEX hypotéza vyvrácená:
324→761/532→983ms; baseline0 versus HEX2disk temporary tabulky. Links-first
zásadně regresuje sparse; narrow materializace také pomalejší. Raw uložené
v summary-orders a summary-hex, neměnit je na PASS výkonu.

Další koncept: skutečně nový index mereni_zaznamy(sportovec_id,id,typ) jako
pokrývající projekce čtení, produkce má zatím jen owner a type samostatně.
Žádný nový produkční DDL není provedený ani index vybraný. Nejprve pouze
syntetický probe: bez indexu / s indexem / bez / s, stejné úplné summary,
výsledky4SID, EXPLAIN, přidání/index footprint a INSERT+rollback cena.
Případný finální indexový gate musí porovnat starou/novou fyzickou topologii:
17párových bloků s obnovou statistik a warmupů, pořadí before/after střídat,
stejná DB a checksumy; není správné dát nový index také baseline variantě.
Gate20% i všechny kontrolní regrese zůstávají stejné. Browser má samostatné
fáze index OFF/ON, žádné produkční měření ani kopie skutečných dat.


Index-only probe42707779079 oběDB SUCCESS/parita/checksum/úklid PASS, ale
výkon NEVYHOVĚL: nový index optimizer automaticky nepoužil (EXPLAIN stále
PRIMARY na mz); summary390→394/412→429ms, opakování395→397/410→413ms.
Index tedy zatím není vybraný. Další omezený syntetický probe ověří FORCE
cover versus owner-first cover; případná aplikace musí index bezpečně
najít z metadat a ponechat původní read při jeho nepřítomnosti. Žádný webový
DDL, pevný předpoklad o názvu nebo odstranění cizího indexu. Samostatná
migrace vznikne až při přínosu. Cena zápisu z prvního probe zahrnovala
INSERT SELECT čtení; nové měření oddělí přípravu a čistý prepared INSERT VALUES.


## Index nevybraný, další kandidát: historie vybraného roku

42708406091 oběDB SUCCESS a přesná summary parita/checksum/cleanup. FORCE
cover proti baseline10.3 347→434ms,11.4 536→511ms, ownercover390/489ms;
opakování potvrdilo výsledek. Výkonové20%gate nesplněné, index NEVYBRÁN.
Čistý INSERT1000 VALUES8.7→10.0/10.9→12.1ms; všechny transakce rollback.
Žádný nový produkční index/migrace/app změna se nezavádí.

Další nejdražší čtení podle diagnózy a výchozí navigace je historie aktuálního
roku (SQL230/293ms při10529z30001tréninků). Předchozí IN varianta pomáhala
hustému roku, ale hrubě regresovala sparse a je zamítnutá. Nový koncept:
ponechat původní EXISTS a přidat do measurement ownership poddotazu join
na stejný trénink se STEJNÝM year filtrem. To dovoluje filtrovat materializaci
vlastněných měření podle roku a volný optimizer si zachová owner index pro
sparse profil. All-year SQL zůstane původní. Volitelně redundantní datumový
interval pro validní1–9999rok, včetně YYYY-00-00 a YYYY-12-31; outer YEAR
zůstává. Nejprve porovnat plné seřazené id/delka včetně chybějících let.
Pokud vyhraje, ověřit celý reader/HTML a hlavní HTTP20%gate pro výchozí rok,
all-year/page/empty/sparse/MZ kontrolní brány zachovat. Žádná schématová změna.


## Vybraný kandidát: redundantní přesný YEAR v ownership historie

42709050977/5e8d1772 oběDB SUCCESS,12scénářů přesné ordered id/delka parity,
7párů/2warmupy. Aktuální rok velký profil:382.575→160.521ms10.3 a
386.842→171.171ms11.4 (−58.0/55.8%). Sparse/empty-year bez významné regrese;
raw v history-year-probe. Datumový interval nic nepřidal, NEVYBRÁN.

Implementovat pouze JOIN stejného treninky selected_t v měřicí větvi EXISTS
při year>0 a YEAR(selected_t.datum)=:ownership_year. Outer YEAR i oba
ownership filtry zůstanou; parametry jedinečné i pro nativní prepare.
Zachovat all-year původní cestu, PHP float/minutes pořadí, všechny detaily,
MZ summary/selector, header, scope/auth/token/session/writery i schéma.
Nezavádět nový index nebo persistentní cache. Výsledek SQL probe zatím
není plný HTTP gate ani produkční přínos.

Před kompletním měřením narovnat syntetické kontroly: SID1012 má po starém
builderu1000attendances, SID1020 jednu. Jen v nové vlastněné fixture odebrat
jejich attendance před OBOU variantami; pak small historie12tréninků podle
12MZ a skutečná empty historie0. Původní raw s1001/1řádky zůstávají zachované.
Čerstvý celý oracle z ff182971; PHPtypy/filtry/stránky/null/zero/partial-zero,
signed64/freshness. Hlavní17párový HTTP gate aktuální rok, kontroly all-year,
stránky/current/absent-year/small/old/empty i MZ; každá kontrola1.2+20ms.
Stejné indexy/statistiky/cacheOFF, žádný fyzický indexový A/B už není potřeba.


## Aktuálně: draft #425 / úplná přejímka

[PR425](https://github.com/KovoPraha/evidenceTreninku/pull/425), app reader
b9b4cda0, základff182971. Nová syntetická kontrola skutečné small12/empty0
historie; společné čerstvé ANALYZE/statistiky všech5tabulek před oběma
variantami. Nezaměňovat s dřívějšími3table probe. Windows3testy/285assertions
PASS, frozen ff182971 oracle exact kromě názvu třídy. Test signed64/partial-zero/
extrémních roků je doplněný k nativnímu potvrzení v dalším checkpointu.
Statická CI/CodeQL/docs PASS na b9, celá22kontrolová sada včetně HTTP/Chrome
probíhá. První úplný výsledek42709994135 zatím není uzavřený. Nový merge/deploy
stále NOT RUN; žádná migrace a původní WIP SHA2567a7afd75 shodný.


## První celé HTTP b9 — FAIL přínosu10.3, NENASAZOVAT

42709994135:11.4 SUCCESS (239.224→106.124ms30k),10.3 FAILURE (36.221→157.399ms30k,
regrese i page2/last). Celé normalizované HTML/shoda/security/checksum/cleanup
PASS oběDB, ale to není PASS celého tasku. Na10.3 při10k116.467→59.251ms,
při1k9.062→12.985ms. 11.4 všechny gates PASS. Raw v first-b9; nejde o fluktuaci
jednoho SQL vzorku. Čerstvé společné statistiky5tabulek dovolily optimizeru
původní10.3 při30k zvolit podstatně levnější plán. Předchozí3table probe s horšími
TS statistikami nebyly dostatečnou predikcí; jejich důkazy zůstávají zachované.

Draft425 zůstává bez merge/deploy. Nová izolovaná probe větev
codex/athlete-targeted-probe-20261109 z b9b4cda0; současný reader není výkonově
přijatý. Následuje SQL probe VŠECH1k/10k/30k se statistikami VŠECH5tabulek
před variantami. Koncept: attendance OR (measurement AND NOT attendance),
v měřicí větvi vynechat již attendance-vlastněné tréninky před širokým mz join.
Porovnat anti EXISTS/LEFT JOIN a CASE bez změny významu/dvojího sčítání.
Žádné snižování20%gate nebo kontrolních limitů; native3testy signed64 atd.
se nyní přidávají. Žádné produkční DDL, WIP a runtime17110a7f zachované.


## Fresh5tables anti probe — parita PASS, další varianta CASE/IN

42711364512/3dfb601a oběDB SUCCESS, nový native3/562 každá. Všech1k/10k/30k,
36scénářů přesně shodných orderedid/delka. Anti LEFT při30k SQL10.3
324→35.9ms,11.4 227→20.0ms; sparse prakticky stejné. Samotný CASE s EXISTS
hustý profil zrychlí ještě víc (20.6/13.4ms), ale sparse130.7/79.3ms
zásadně regresuje, NEVYBRÁN. Původní10.3 plán se při ANALYZE může změnit
na levný35ms, antiLEFT35.9ms by pak neměl20% přínos: zatím nepřijímat.

Další koncept CASE s explicitním NEKORELOVANÝM IN seznamem měřicích klíčů,
s filtrem stejného roku. Docházkou pokryté řádky nemají vyvolávat čtení MZ;
pro sparse se má seznam vyrobit jednou místo závislé MZ sondy pro každý
klubový trénink. Porovnat IN-year a IN-year/antiattendance, zachovat0rok
původní. Rozšířit syntetický scope o measurement-only velký SID1002: jeho
attendance se odebere před oběma variantami, ale trenérský scope zůstává.
Native probe i celé HTTP pak musí zahrnout tento kontrolní profil. Žádný
změněný gate, index/DDL/cache nebo heuristika dle velikosti či verze DB.


## CASE/IN-year nepřijatý, explicitní odložená materializace k ověření

42711825852/0f59e7c3 oběDB SUCCESS a45scénářů přesná parita; všechny velikosti
s fresh5table stats, measurement-only1002.10.3 při30k baseline25.889ms,
CASE24.995ms, CASE-IN-year154.583ms, CASE-IN-anti37.291ms; sparseIN30ms
versus8ms a measurement-only109/135ms versus62ms.11.4 také regressuje
measurement-only (95/105 versus59ms). Žádná varianta není vybraná.

Další omezený probe: CASE s EXISTS nad odvozenou DISTINCT tabulkou měřicích
klíčů. Vnořený nezávislý DISTINCT brání opakovanému skenu MZ pro každý
klubový řádek; CASE má odložit její načtení, když docházka postačuje.
Porovnat attendance EXISTS a attendance IN (index-only seznam), stejný rok
v outer i derived MZ. Bez heuristik, nových indexů nebo změny0roku.
Rozhodne opět všech45narrow scénářů na3velikostech, potom celéHTTP; zatím
NEIMPLEMENTOVAT jako přijatý reader. Draft425 zůstává blokovaný gate.


## Odložený DISTINCT nepřijatý; datový pokrývající index jako další koncept

42712571429/c6 oběDB SUCCESS,45scénářů shodných.10.3 při30k baseline196ms,
lazy83/86ms, ale při10k baseline4ms versus23ms a measurement-only31→48ms;
11.4 measurement-only59→84ms. CASE nedává robustní přínos proti levnému
původnímu plánu. Všechny CASE/anti/derived varianty NEVYBRÁNY. Reader v
probe větvi se vrací k přesnému ff182971; b9 zůstává historicky v draftu425.

Další koncept: nový malý pokrývající index treninky(datum,id,delka) a pro
validní1–9999rok redundantní DATE interval YYYY-00-00 až YYYY-12-31 při
zachování původního YEAR/EXISTS/scope. Pokrývá původní nejužší projekci,
může omezit celoklubový průchod a řazení bez čtení napln/obrazky. Skutečná
produkční inventura potvrdila, že treninky má jen PK, žádná duplicita
existujícího datumového indexu. Jde o NEVYBRANÝ koncept, produkční DDLfalse.

Nejdřív pouze syntetické schema OFF/ON probe všech1k/10k/30k,5profilů/3let,
stejné doménové checksumy, fresh5stats před oběma fyzickými topologiemi.
Porovnat původní SQL, YEAR+range a range+CASE/anti; přesná ordered projekce,
index build/velikost a čistý INSERT+rollback. Pokud vyhraje, nová neměnná
CLI migrace s LOCK=NONE/InnoDB/verify, bez odstranění původních indexů.
Finální fyzický A/B bude17střídavých bloků s OFF/ON pouze v syntetické DB,
čerstvé statistiky a4warmupy každého bloku, nezměněné20%/kontrolní gates.
Původní WIP, public scope/auth/token/session a produkce beze změny.


Starší velké diagnostické JSON jsou lossless gzip pro přehledný code review.
[Manifest původních a gzip SHA256](verification/athlete-profile-targeted-read-20261109/ARCHIVED_JSON_MANIFEST.json)
ověřuje rozbalení všech původních canonical Git blobů; nic se nezahazuje,
původní raw navíc zůstává ve starších commitech. Metadata potřebná přímo
pro reprodukční skripty zůstávají nekomprimovaná.
