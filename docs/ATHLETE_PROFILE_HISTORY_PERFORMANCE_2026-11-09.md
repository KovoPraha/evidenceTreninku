# P11-RHI — načítání historie profilu sportovce

9. 11. 2026, vlastník vývoj/Codex. Stav: HOTOVO / NASAZENO; technické ověření PASS, produkční UAT NOT RUN.
[Registr](WORK_PLAN_CURRENT.md), [předání](SESSION_HANDOFF_ATHLETE_HISTORY_2026-11-09.md),
[důkazy](verification/athlete-profile-history-performance-20261109/RESULTS.md).

## Rozsah a oprávnění

Aktuální pokyn vlastníka výslovně povoluje optimalizaci historie a po úspěšné
paritě/přínosu commit, merge a protected deploy. Import/UATfalse; žádné
produkční osobní zápisy, nové DDL/indexy nebo cache práv/dat.
Baseline main d666078e2480f16fbc5f1d5b716802054501d57e; runtime odpovídá
produkčnímu c47d4008/deploy42587406908. P11-RAH dokončené, znovu nepřipisovat úspory.
Větev codex/athlete-history-performance-20261109, izolovaný worktree
C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107.
Původní C:/xampp/htdocs/evidencePavel/codex/fio-auto-reconciliation-recovery
WIP kis_rosters_admin.php SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 zachovaný.
Stage pouze vlastní cesty, ostatní untracked artefakty a větve ponechat.

## Diagnóza a varianty

history() načte všechny id/délky a znovu buduje celý UNION pro25detailů.
Kandidát: korelované EXISTS zachová unique membership, vlastní/cizí měření
bez násobení duplicit a ověřuje vlastnictví i rok znovu při detailu.
První průchod číst postupně a držet jen požadovanou/poslední stránku klíčů.
Součet minut ponechat v PHP v původním seřazeném pořadí: SQL SUM by měnil
převod čárek, prázdných/nečíselných hodnot i floating-point pořadí.
Alternativa: optimalizovat pouze detail25ids, pokud EXISTS celé historie
na native DB regreduje. Rozhodnou páry celéHTTP, nikoli jen počet SQL.
PDO buffering neměnit globálně; reportovat skutečný PHP peak, ne slib O(1)
celkové paměti ovladače/DB. Auth/session/TTL/CSRF/poznámkové writery, měření,
header agregace i HTML zachovat. Změna pouze history readmodelu.

## Akceptace a postup

1. Plán commitnout před implementací. Přesná reference history() zmrazená z main.
2. Parita celého návratového objektu/typů: všechny stránky, žádná/prázdná/poslední,
   záporný/velký požadavek, rok0/konkrétní/nenalezený/null datum/zero datum,
   duplicate attendance/measurement, orphan/foreign/measurement-only, poznámky,
   čerstvé změny a délky0/null/čárky/scientific/float. Bez SQL SUM či cache.
3. MariaDB10.3/11.4 a SQLite. Dvě oddělenéHTTP verze, zmrazený reader i endpoint
   z d666078e, stejné vlastní syntetické schéma, cacheOFF,1k/10k/30k historie.
  4warmups+17střídavých párů, hlavní/malý/prázdný profil a několik roků/stránek.
   Celé fragmenty byte-shodné, parentHTML/JS shodné pouze po nonce/CSRF normalizaci.
4. Samostatná SQL/řádková/PHPpeak diagnostika AJAX historie mimo prosté časování.
   Cílově alespoň20% mediánové úspory historie30k a žádná podstatná regrese kontrol.
   Původní/nezdařené vzorky zachovat; performance gates nenutit změnou baseline.
5. Skutečný Chrome přes PlaywrightCLI: default a uložené Měření,3cold+10warm
   na variantu; fetch→DOM+2frames a celá navigace zvlášť, filtry/stránky/390px,
   chyby0. Původní JS/UI neměnit a nepoužívat request stuby/zpoždění.
6.12staffrolí, bearer303/retarget/expiry/revokace/scope/CSRF/logout, nezávislé
   fallbacky parentu,11doménovýchchecksumů a kompletní own cleanup PASS.
7. Celé aktuální CI, measured-source Git SHA256 a whole tested/merged tree,
   poté protected deploy a záloha/migrace/VS/config/index/smoke; veřejnéGET/DOM
   před/po. Přihlášené produkční UAT a produkční výkon samostatně NOT RUN.
8. Průběžně plán/handoff/registr/deníky; finální bezpečné důkazy do main
   samostatným dokumentačním PR bez druhého deploye dokumentace.

Výchozí krok při zahájení splněný; aktuální uzavření níže.

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

## První nativní měření — checkpoint

f9acd99f/run42621207366 oběDB2/513 a celýHTTP/Chrome prošly, historie30k
468→37 /758→48ms, PHPpeak14.5→1.56MB. Wholefragmenty/role/CSRF/cleanup přesné.
První Chrome default1181→818ms, savedMěření1579→1729ms (+9.5%); netvrdit
zrychlení všech záložek. Neexistujícírok7→20 /8→24ms. Další iterace přesune
levný YEAR před EXISTS a přidá browser ResourceTiming pro rozlišení souběhu.
[První bezpečné důkazy a meze](verification/athlete-profile-history-performance-20261109/RESULTS.md).
Místní7/892 i composer validate --strict PASS. Nový head potřebuje vlastní
celéCI/native/Chrome před merge a podmíněným release; WIP a produkce zachované.

## Převzetí souběžného main #412

Převzato origin/main4f92b225 (odolnost rozepsaného tréninku) včetně kódu,
testů a Q2-TIME dokumentace, beze změny přijatého obsahu. Konflikty jen
CURRENT/EVENT; oba plány zachované, vlastní eventy nověE08/09 kvůli Q2-TIME E07.
Baseline d666078e pro3dotčené profilové soubory zůstává byte-identická s
main4f92b225. Nový integratedhead znovu ověří celéCI i native/HTTP/Chrome.
První benchf9acd99f zůstává pouze prvním měřením, ne důkazem nového headu.

## Uzavření 9. 11. 2026

#413/c3d34b87, tested ecd47579 všech18CI SUCCESS; celý tested/merged strom
shodný a5measured-source SHA256 přesné. Přijatá oprava #412 zachovaná.
Deploy42625502461 SUCCESS 2026-11-09T13:30:32Z, testy2411/55204, backup389/2,
migrace/VS/config/index/smoke PASS, import=false/UAT=false. Veřejné8/8HTTP/DOM shodné.
[Provozní důkazy](verification/athlete-profile-history-release-20261109/RESULTS.md).

Finální30k/270k historieHTTP599.411→64.589ms (−89.2%,10.3),
953.704→63.851ms (−93.3%,11.4), PHPmanagedpeak14.53→1.56MB (−89.3%).
SQL12→12/vrácenéřádky30282→30282: méně práce a PHP kolekcí, stejná data.
Chrome warm default historie doDOM+2frames1012.85→540.20ms,
celá navigace1530.25→1058.25ms (−30.8%); dokumentTTFB neutrální.
SavedMěření v prvním běhu+9.5%, ve finálním−8.2% (2234.60→2051.90ms);
neslibovat stabilní přínos Měření, jehoSQL se nezměnilo. První vzorky zachované.
Dřívější regrese neexistujícího roku odstraněná:11.07→11.22 /10.70→10.95ms.

Native2/513 každáDB,SQLite2/261, místní7/892;1428časovanýchAJAX+52Chrome
ve finálním běhu. Přesné součty/typy/řazení/stránky i celé fragmenty,
12staffrolí/bearer/freshrevokace/CSRF/2fallbacky/11checksumů a cleanup PASS.
Desktop/390px snímky byte-identické/vizuálně ověřené, konzole/transport0.
Žádné DDL/cache/změny session, TTL nebo profilových writerů. Mimo history()
je celý reader byte-preserved; endpoint/parent/header/měření zůstávají stejné.
WIP kis_rosters_admin.php SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 zachovaný,
původní checkout/větev i untracked soubory nedotčené.

Přihlášené produkční UAT, produkční výkon, obnova této konkrétní zálohy,
nový zápis poznámky a fyzický mobil NOT RUN. Syntetická čísla nejsou SLA/LCP.
Finální důkazy: codex/athlete-history-release-proof-20261109, samostatný
PR do main bez opakovaného deploye dokumentace. Rozsah dokončen.
Další pouze KONCEPT: změřit zbývající rozdíl mezi samotným HTTP readmodelem
a browser fetchTTFB (bootstrap/čerstvé ověření/fronta). ResourceTiming sám
příčinu nedokazuje; žádné cachování práv či další implementace tímto povolené.

## Převzetí dokumentačního #414

Převzato main fa8315eb, úplné produkční předání Q2-TIME včetně jeho přejímky.
Oba release záznamy zachované, vlastní uzavření nově eventE11 kvůli přijatémuE10.
Změna jen dokumentace; runtime stále c3d34b87. Nový společný proof head
znovu ověřuje celéCI před merge.61artifacthashů zůstává kanonicky shodných.
