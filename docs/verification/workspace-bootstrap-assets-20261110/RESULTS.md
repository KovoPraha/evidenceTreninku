# P11-ASSET2 — pracovní rozcestník

**NASAZENO / TECHNICKÉ OVĚŘENÍ PASS.**

[Finální release, důkazy a meze](release/RESULTS.md).

[Plán](../../BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset2--pracovní-rozcestník-nový-řez-10-11-2026).
Čerstvý diagnostickýChromeHJS2 před optimalizací: dvě skutečnégzipCSSodpovědi
na pracovnímrozcestníku, BootstrapAPItrue/JSsíťchyby0. Novýsource/HTTP/browser/release
pár ASSET2 dosud NOT RUN. WIP a nevybranéJSprototypy zachované.


## První CI — oprava fixture a povinného stylu, bez přijetí

42784986151/201e06de obaDB skončily před HTTP měřením: chybný tuple klíč
baselines[hlavicka,pracovni] při rozšíření seznamu route. Opraven pouze test.
PHPStan/CodeQL PASS; style správně odmítl staré minifikované řádky rozcestníku.
Dotčený PHP soubor proto dostal povinné PSR12/line200 formátování uvnitř PHP
segmentů a závorky jedné dosavadní if/continue větve. Jedna prázdná PHP hranice
rozdělí dlouhý HTML řádek bez emitování newline. Nejde o nové HTML mezery.
Nový tokenový kontrakt vyžaduje stejné PHP tokeny (s explicitně pojmenovanými
závorkami právě tohoto guardu) i všechny zbývající literalHTML bytes; whitespace
uvnitř PHP je jediná formátovací výjimka. Celé nativní HTML/Chrome/pixely stále
zůstávají povinné. Normalizátor1/6 PASS; nový native/browser/CI/release čeká.


## Druhý nativní běh — 10.3 PASS, browser klávesový vstup k opravě

42786353898/8827397f: celý10.3 1k/10k/30k, HTMLparita/role/security/checksums/
cleanup PASS.11.4došlo34střídavými cold navigacemi workspace desktop, CSS2→1,
oba screenshoty dokonce PNGbyteidentické. Zastavilo se až netimed ověření '?' –
Shift+/ neotevřelo shortcutsModal. Opraven testovací klávesový vstup na explicitní
'?' a přidán skutečný key/target/API/error stav; gate modalu zůstává zachovaný.
Nic z neúplného browserového běhu se nepočítá jako kompletní přijetí.
Přihlášené production UI bylo zkusenojeninventurou připojenébrowserovérelace;
dvakrát timeout/reset nástroje, bez UI akcí/formulářů/rolí. UAT NOT RUN.


## Třetí běh — skutečná animace modalu

42786982393/6b5eb812:10.3celýpár PASS.11.4už otevřelo shortcutsModal explicitním
'?', ale Escape přišel po přidání .show a před koncem300ms animace, kdy Bootstrap
hide ignoruje _isTransitioning. Test nyní předakcí přihlásí skutečné shown/hidden
události a čeká na ně (totéž confirm); žádný umělýspánek ani změna runtime.
Neúplná měření nejsou přijatýPASS. Všechny gate včetně zavření Escape zůstávají.


## Přijatý aktuální pár — PASS

[42787905422](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42787905422),
head156a9597, sourceca7aea1e..., oběMariaDB SUCCESS. Native1k/10k/30k,
wholeHTML/role/security/domainchecksum/cleanup PASS. Chrome544samples+128warmup,
8decodedpixelpairs a všech24untimedinteractions PASS, JS/networkerrors0.
WorkspaceCSS2→1, ostatní3controls1→1, skutečné CSSpayloadhashy shodné.
[Native10.3](native-10.3/integrated-summary.json),
[native11.4](native-11.4/integrated-summary.json),
[Chrome mediány](native-11.4/workspace-browser-coach-summary.json).
ColdCSSgzip62496→31248B (−50 % BootstrapCSS), decoded465606→232803B;
HTML45094→45016B. HTML+BootstrapCSSpayload73524→42272B, nejde o všechnyassety.
ColdFCPdesktop60→52ms (−13,3 %), mobile56→52ms (−7,1 %); ready76,7→75,6 /
76,7→76,8ms, tedy výraznýreadyzisk neprokázaný. WarmCSSnetwork0obě, FCP44→44 /
40→40. Reálný Chrome nad syntetickouApache/loopbackDB, nikoli produkčníSLA.

Všech13CI SUCCESS; PHPUnit2425/55914. PR430 sloučen jako706a8d42,
[celý tested/mergedtree](release/tree-identity.json) shodný. Protecteddeploy
42790212078 běží na přesnémSHA simport/UATfalse; novýlivevýsledek zatím čeká.
PřihlášenéprodukčníUAT NOTRUN kvůli2timeoutům browserAPI bezUIzměn.


## Release uzavření

PR430/706a8d42 a protected deploy42790212078 SUCCESS. Všech13CI, oběMariaDB,
544Chrome/128warmup/8pixelpairs/24functional scénářů PASS. BootstrapCSS cold
62496→31248B, FCPdesktop60→52/mobile56→52ms vsyntetickémChrome; warmbezezměny.
[Úplné důkazy](release/RESULTS.md), backup389/2/config/VS/migrace/index/HTTP200
PASS, 8veřejnýchGET/DOM a canonicalCSS/SRI/cache shodné. AuthproductionUAT NOTRUN.
WIP hash po release shodný. Finální docsbez druhéhonasazení.
