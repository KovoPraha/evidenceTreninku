# P11-JSZ — komprese veřejných JavaScriptů

10. 11. 2026 · ROZPRACOVÁNO · vlastník Vývoj/Codex.

## Zadání a výběr

Vlastník povolil další nejpřínosnější optimalizaci, shodu výsledků a měření
před/po; commit, merge a produkční deploy pouze po úspěšném ověření.
Původní WIP musí zůstat beze změny. Návaznost: bod 7
[analýzy](verification/performance-analysis-20261107/RESULTS.md),
[rozhodnutí JSZ](DECISION_LOG.md#d2026-11-10-jsz--přednost-skutečné-kompresi-před-novým-inline-assetem).

Skutečné produkční GET s Accept-Encoding:gzip: Bootstrap bundle 80721 B,
app-ui.js 8561 B, oba identity a max-age=30. Dekódovaný obsah shodný s main.
[Doklad](verification/shared-header-script-20261110/production-js-transport.json).
Offline gzip6 odhad 26809 B není nový HTTP výsledek. Inline přesun HJS
[nevybrán](SHARED_HEADER_SCRIPT_PERFORMANCE_2026-11-10.md); prototyp zachovaný
na codex/shared-header-assets-performance-20261110, nesmí se přimíchat do JSZ.

## Rozsah a postup před implementací

1. Přidat jen assets/.htaccess: FilesMatch pro .js a SetOutputFilter DEFLATE
   pod IfModule. Bez změny souborů JS, PHP, HTML, SQL, TTL nebo počtu requestů.
   Root sw.js, CSS a ostatní formáty zůstanou pod dosavadní politikou.
   [Apache mod_deflate](https://httpd.apache.org/docs/2.4/mod/mod_deflate.html)
   dokumentuje gzip negotiation; nevynucovat gzip pro nekompatibilní klienty.
2. Reálný izolovaný Apache před/po s kanonickými Git bytes, fyzicky oddělené
   static docrooty a dosavadní hostingová cache 30 s / CSS gzip týden.
   Ověřit identity, gzip, q=0, HEAD, Vary, ETag/304, identity range, hash po
   rozbalení; kontrolní CSS, JSON, PNG, root SW a zákaz čtení .htaccess.
   Nepoužívat PHP -S jako důkaz zpracování .htaccess.
3. Reálný Chrome přes Playwright CLI: oba původní JS, syntetická veřejná
   stránka s Bootstrap UI a app-ui, desktop/mobile, cold/warm, 4 zahřívací
   a 17 střídajících se párů. Měřit skutečné encoded/decoded bytes a časy,
   bez síťových mocků, škrcení a umělých latencí. Funkční Bootstrap a app-ui
   kontrakty a totožný DOM/style. Toto není přihlášené produkční UAT.
4. Přijetí: striktní shoda všech rozbalených bytes a kontrolních odpovědí,
   stejná cache a práva, gzip úspora obou hlavních JS nejméně 60 %, žádné
   chyby JS ani materiální regrese skutečných časů. Při malé lokální časové
   diferenci hlásit hlavně prokázanou úsporu přenosu, ne odhad uživatelského SLA.
5. Celé aktuální CI včetně existujících DB testů; nový Apache/browser CI na
   Linuxu a místní Apache na Windows. Protože se nemění PHP/SQL, nové měření
   je transportní a nevyžaduje druhou kopii DB benchmarku. Před merge zdrojová
   shoda, named staging, docs checker; chráněný deploy main s flags false.
6. Před/po anonymní produkční GET, nezávislé ověření gzip/identity/hash/cache
   a původních veřejných DOM, úplná záloha a schéma/VS/config/smoke dle gate.
   Kdyby hosting konfiguraci neuplatnil, nesmí se tvrdit dosažená úspora.
   Finální release důkazy přes docs PR bez opakování deploye.

## Výchozí stav a další krok

Větev codex/javascript-compression-performance-20261110, izolovaný worktree
C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107.
Base main 6dee3253b483522396cd9c7349dabac576e702b6, potvrzený runtime
9dbd9eaf1f78e364d1dacd1ae0e3969758b7a176 / deploy42727693090 SUCCESS.
Původní WIP kis_rosters_admin.php SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 zachovat.
Nová implementace, nativní měření, Chrome, CI, PR, merge a deploy: NOT RUN.
Další krok: minimální config a skutečný Apache kontrakt.

[Důkazy](verification/javascript-compression-20261110/RESULTS.md) ·
[předání](SESSION_HANDOFF_JAVASCRIPT_COMPRESSION_2026-11-10.md).

## Průběžná kontrola Apache a upřesnění cache kontraktu

Implementováno pouze assets/.htaccess (7 řádků); původní root .htaccess,
13 JS i všechny PHP/SQL/CSS beze změny. Test má oddělené fyzické docrooty,
načítá skutečnou root bezpečnostní konfiguraci a nový soubor pouze v after.
Izolovaný Windows Apache 2.4.58 ověřil gzip negotiation i přesné bytes prvního JS.

První přísná kontrola gzip If-None-Match správně odhalila standardní Apache
AddSuffix: gzip ETag se liší a opakovaný podmíněný gzip GET vrací plný 200,
identity nadále 304. Totéž platí už před změnou pro gzip CSS.
Nejde o změnu TTL; u JS po vypršení 30 s může místo identity304 přijít nový
komprimovaný200. Nelze slíbit úsporu pro tento konkrétní revalidační scénář.
Neoslabovat ETag pomocí NoChange ani prodlužovat cache jen kvůli výsledku.
[Primární dokumentace](https://httpd.apache.org/docs/2.4/mod/mod_deflate.html#deflatealteretag).
Kontrakt proto přijme bezpečný200 nebo304, kontroluje skutečný status i hash;
identity304, Vary, TTL a negotiation zůstávají povinné. Původní očekávání všech
komprimovaných304 se neopíralo o Apache chování; nyní je opravené před release.
Nativní úplná sada a Chrome stále běží; výkon/CI/merge/deploy zatím NOT RUN.
