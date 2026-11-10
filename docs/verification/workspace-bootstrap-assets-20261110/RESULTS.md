# P11-ASSET2 — pracovní rozcestník

ROZPRACOVÁNO. [Plán](../../BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset2--pracovní-rozcestník-nový-řez-10-11-2026).
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
