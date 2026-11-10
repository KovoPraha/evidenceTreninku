# BAR-DISPLAY-STOCK: původní obrazovky a převod zásob

Zadání vlastníka 10. 11. 2026: zachovat původní formát, velikosti a grafiku zobrazování lístků a převzít skladové zásoby produktů. Tím je pro tuto navazující operaci nahrazeno dřívější rozhodnutí B7 o nulovém počátku; původní převod zůstává historickým faktem. Navazuje [převod B7](verification/bar-cutover-20261106/RESULTS.md), [publikace](verification/bar-menu-publication-20261106/RESULTS.md) a [session handoff](SESSION_HANDOFF_BAR_2026-11-06.md).

Stav: TECHNICKY HOTOVO; VIZUÁLNÍ PŘEJÍMKA OTEVŘENÁ. Implementace #440/#443 sloučená, aktivní souborové předání `codex/bar-display-stock-release-20261110`, worktree `C:/Users/Marek/Documents/Codex/evidencePavel-bar-display-20261110`. Starší nedokončená přejímka ve druhém worktree a cizí WIP původního BAR jsou zachované.

## Postup a akceptace

1. Z původního canonical checkoutu BAR převzít přesné CSS a chování dvou TV obrazovek: dvousloupcový ceník, automatické přizpůsobení výšce a barevné kategorie; fotografická obrazovka se třemi kartami, desetisekundovým střídáním a původními přechody. Adaptovat pouze zdroj dat, bezpečné obrázky KIS a nonce. Zachovat oddělené vstupy obou zobrazení, mobilní chování i tiskový PDF tok.
2. Ověřit při TV rozměrech a na mobilu, že nedošlo k posunutí velikostí/rozvržení; testovat výběr publikované nabídky, bezpečné escapování, ceny v haléřích a střídání bez okamžitého opakování.
3. Připravit čtecí snímek zásob původního BAR a cíle KIS podle stabilního `source_id`, oba sklady. Žádná hesla, session nebo zdrojové dumpy do Gitu. Změna položky, nesouhlas mapy, rezervace či novější skladové pohyby musí zabránit přepsání dat.
4. Samostatný řízený převod konkrétního snímku musí být atomický, auditovaný a opakovatelný bez dvojího naskladnění. Před zápisem aktuální bod obnovy; po něm nové nezávislé čtení a shoda obou skladů. Původní katalogový import se znovu nespouští a zdrojová DB se nemění.
5. Výsledek odlišit na implementováno / CI / sloučeno / nasazeno / zásoby skutečně převedené. Zbývající lidskou přejímku Lucky a skutečné platby neprohlásit za hotové pouze převodem zásob.

## Zjištění při zahájení

Původní `public/obrazovka.php` je celoobrazovkový textový ceník; `public/obrazovka2.php` je tříkartová střídající prezentace. Současný `includes/bar/menu_view.php` oba nahradil posuvnou dvousloupcovou galerií, čímž původní vzhled nezachoval. Nová oprava vychází z původních šablon, nikoli z nového grafického návrhu.

Průběžné výsledky a další krok: [protokol](verification/bar-display-stock-20261110/RESULTS.md), [PR #440](https://github.com/KovoPraha/evidenceTreninku/pull/440). Oprava #440 nasazená, doplněk #443 sloučený a vykonaný chráněným workflow. Všech 68 produktů / oba sklady převedené, 136 auditovaných operací a nezávisle nula rozdílů. Původní TV URL i 44 HTTP kontrol PASS. Zdrojový součet stánku 1073 / předskladu 0, včetně pěti záporných zůstatků. Fyzická inventura a lidská přejímka jsou zvlášť.

## Navazující preflight zásob

Obrazovky nasazené přes #440, 2 stránky / 4 přesná aktiva / 45 fotografií živě PASS; viz protokol. První pouze čtecí inspect zásob skončil `InvalidArgumentException`, `committed=false`. Nesmí následovat odhad, vynulování nebo zaokrouhlení zdrojových hodnot.

Provedené technické doplnění v rámci schváleného kopírování: ve větvi `codex/bar-stock-preflight-20261110` byla přidaná bezpečná fázová diagnostika a souhrny skutečných celých počtů bez konfigurace/hesel. Počáteční zůstatek smí zachovat i původní záporné celé číslo v mezích původního skladu; nejde o povolení nového prodeje do mínusu. Běžné skladové služby, nulový cílový stav, rezervace, historie a úplný bod obnovy zůstávají hlídané. Neplatné, zlomkové nebo nadlimitní hodnoty nadále odmítnout. Nový konkrétní čtecí výsledek doložil pět záporných hodnot; následný zápis i nezávislá kontrola prošly. Počty se nadále vedou pouze v KIS a původní BAR se znovu nesynchronizuje. Nasazený web se kvůli diagnostice neměnil; chráněný workflow nahrál a spustil prověřeného pomocníka mimo webroot.

## Další převzetí

[Finální provozní výsledek a bezpečné důkazy](verification/bar-display-stock-20261110/RESULTS.md). Vizuální přejímka NOT RUN kvůli blokaci Computer Use ověřením URL. Následuje fyzická inventura v KIS a skutečné P02/P04 podle [handoffu](SESSION_HANDOFF_BAR_2026-11-06.md), bez opakování importu, počátečních pohybů nebo publikace nabídky. Souborové předání: `codex/bar-display-stock-release-20261110`, vlastní původní worktree; finální PR následuje bez nového deploye.
