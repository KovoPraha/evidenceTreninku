# BAR-DISPLAY-STOCK: původní obrazovky a převod zásob

## BAR-PHOTO-SCREEN-01 — chybějící fotografie na živé obrazovce (23. 11. 2026)

**Stav: kód nasazen, TV UAT otevřené.** Vlastník hlásil, že na fotografické obrazovce BAR jsou po migraci prázdné karty a zobrazí se jen menší fotografie tyčinky. [PR #521](https://github.com/KovoPraha/evidenceTreninku/pull/521) a navazující [#522](https://github.com/KovoPraha/evidenceTreninku/pull/522) byly sloučeny; [deploy 45233456861](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45233456861) na `7918ca05` SUCCESS. Vývoj BAR: `codex/bar-photo-screen-20261123` a `codex/bar-photo-screen-preload-20261123` v oddělených worktree; dokumentační větev `codex/bar-photo-screen-release-20261123`. Žádný produkční zápis do katalogu ani opakování migrace. [Registr](WORK_PLAN_CURRENT.md#bar-photo-screen-01--fotografie-barové-obrazovky-23-11-2026), [ověření](verification/bar-photo-screen-20261123/RESULTS.md).

Nález: veřejná nabídka nyní obsahuje 45 produktů a 45 URL fotografií; kontrolované originály odpovídají typu a dekódují se. Tříkartová obrazovka mění karty po 10 s. Například obraz Bolognese má 2 585 938 B a při čtecím GET trval 11,55 s; tyčinka má 479 217 B a trvala 2,23 s. Prázdné karty byly reprodukovány v živém prohlížeči. Pravděpodobnou příčinou je, že větší přenosy nedoběhnou před výměnou karet; závěr podložit srovnáním po opravě.

Záměr: pro fotografickou TV obrazovku poskytovat z již autorizovaných soukromých originálů omezené obrazové varianty vhodné pro rozměr karty, se zachováním originálu pro ostatní cesty. Zvolena JPEG varianta do 960 px, kvalita 72, generovaná při požadavku bez dalších souborů a bez nových DB dat; [rozhodnutí D2026-11-23-BAR02](DECISION_LOG.md#d2026-11-23-bar02). Varianta prodloužit desetisekundový cyklus by měnila původní chování a při více souběžných přenosech nemusí pomoci; opakovat katalogovou migraci by zasáhlo skutečná data. Akceptace: zveřejněné obrázky karet mají výrazně menší přenos, alespoň tři různé karty se během 10 s vykreslí na živé stránce, nepovolené obrázky zůstanou nepřístupné, původní obrázky a data se nemění. Závislosti: produkční rychlost, soukromé úložiště, GD a kontrola na skutečné TV po vydání. Místní náhled tří fotografií a testy PASS; živé malé obrázky a původní nepovolený přístup ověřeny. Fyzická TV přejímka zůstává samostatně otevřená.

Po prvním nasazení živá obrazovka vrací 45/45 variant a tři karty se v prohlížeči načtou, ale při okamžité výměně DOM byl zachycen krátký stav dvou prázdných karet. Nasazený druhý řez drží předchozí karty do dokončení načtení/dekódování dalších, poté zachová původní 720ms přechod a 10s střídání. Po 8 s při nedostupném obrázku zobrazí textový zástupný obsah; nečeká trvale. Nový dotazový klíč `bar-photos.js?v=20261123` zajistí, že TV nenačte týden uložený starý skript; živá stránka tento klíč potvrdila. [Rozhodnutí D2026-11-23-BAR03](DECISION_LOG.md#d2026-11-23-bar03). Místní test s 3s zpožděním každé fotografie: staré karty během načítání zůstaly, další trojice se zobrazila plně načtená. Dlouhé měření rotace na živé stránce se zaseklo v prohlížečové relaci; další krok je přejímka na skutečné TV.

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

[Finální provozní výsledek a bezpečné důkazy](verification/bar-display-stock-20261110/RESULTS.md). Vizuální přejímka NOT RUN kvůli blokaci Computer Use ověřením URL. Následuje fyzická inventura v KIS a skutečné P02/P04 podle [handoffu](SESSION_HANDOFF_BAR_2026-11-06.md), bez opakování importu, počátečních pohybů nebo publikace nabídky. Souborové předání: `codex/bar-display-stock-release-20261110`, vlastní původní worktree; [PR #444](https://github.com/KovoPraha/evidenceTreninku/pull/444), aktuální CI a stav sloučení u PR, bez nového deploye.
