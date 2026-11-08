# P11-RAS — výsledky

8. 11. 2026: plán před implementací, baselinec548d1d3 a produkce82efa16d.
[Plán](../../AJAX_PROFILE_SESSION_PERFORMANCE_2026-11-08.md),
[předání](../../SESSION_HANDOFF_AJAX_PROFILE_SESSION_2026-11-08.md).
RGS nevybrané, jeho input je vypnutý. Aktivní dvě profilová AJAX čtení drží
session do konce; skutečný native souběh, parita a přínos zatím NOT RUN.
Bez produkční změny, WIP hash7a7afd75… shodný. Handler produkce neověřený.

## První CI pokus — FAIL infrastruktury, žádný release

[Run42529811126](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42529811126),
head498a6cca. 10.3 odmítlo fixture ALTER AUTO_INCREMENT na referencovaném ID
(1833); 11.4 prošlo první měřicí dvojice, potom opakovaný login už přihlášené
session správně vrátil302. Žádná úplná měřicí zpráva, nepovažovat za PASS.
Vlastní fixture flag FOREIGN_KEY_CHECKS se pouze při přípravě dočasně vypne
v try/finally a obnoví1; žádná produkční DDL/migrace. Každý testovací login
použije nový vlastní cookie jar a skutečný formulář. Failed artifact ponechaný.
Při 10.3 selhání před configem nebyl standardní cleanup dokončen; celý vlastní
service container byl následně ukončen GitHub Actions. 11.4 standardní cleanup PASS.
Lokálně cílený PHPUnit33testů/819assertions PASS; vlastní PHP style PASS,
Python/JS syntax PASS, docs4926odkazů/108archivů PASS. Produkce beze změny.
PR[406](https://github.com/KovoPraha/evidenceTreninku/pull/406), merge/deploy NOT RUN.

## Průběžný native výsledek na b2a6c25b

[Run42530087243](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42530087243).
MariaDB10.3 PASS; [syrové bezpečné vzorky a summary](native-10.3/integrated-summary.json),
[nezávislý SQL/filelock důkaz](native-10.3/lock-diagnostics.json),
[úplný vlastní cleanup](native-10.3/runner-cleanup.json).
1000/10000/30000 přidaných tréninků vedle malé základní fixture,
9000/90000/270000 vlastních měření; cizí měření/poznámky se nezpřístupní.
Mediány stejné session / skutečné dvojice Měření+Historie aktuálního roku:
33,704→22,720ms, 274,179→172,770ms, 834,058→528,475ms.
Krátký prázdný rok při30k: 498,745→6,886ms; jiná session6,642→8,892ms.
Samotné Měření531,707→506,212ms: stejný SQL plán/práce, neoznačovat za SQL optimalizaci.
Dvanáct celých fragmentů přesně shodných pro každý objem, 12staff rolí,
pravý bearer303, expiry/revokace/scope/guest, CSRF logout303 a následná403 PASS.
Při30k logout490,190→0,700ms a staré čtení session neoživí.
Obě varianty skutečně běží stejnou společnou aplikaci s jediným rozdílným route.
Baseline route blob155b72fc je totožný s předchozí produkcí82efa16d.
11.4/Chrome a celé finální CI zatím čekají. Bez merge/deploy.

## Kompletní výsledek — native / HTTP / Chrome PASS

Headb2a6c25b, všech16CI SUCCESS. [Native11.4](native-11.4/integrated-summary.json),
[lock důkaz](native-11.4/lock-diagnostics.json), [cleanup](native-11.4/runner-cleanup.json).
Každá DB:3objemy ×17měřených střídavých párů ×2varianty ×4souběžné kontroly
=408párů/816HTTP +102samostatných Měření; dohromady918časovanýchHTTP,
1836naobouDB. Warmup4kola mimo tyto počty, bez SQL/HTTP instrumentace.
Parita12celých fragmentů na objem/DB +každá měřená odpověď přesně proti hashům
celého fragmentu, 12staff rolí/obě route, guest/retarget/expirace/scope/fresh revokace
+CSRF logout303/po dokončení starého čtení403 a doménové checksumy PASS.
Role/TTL/rotace/rodinný note gate zůstávají před close; existující cílené testy33/819 PASS.
Syntetická databáze měla350tabulek/162migrací; žádné produkční DDL.

Medián celé skutečné dvojice Měření+Historie aktuálního roku (same session):

| Přidané tréninky / vlastní měření | MariaDB10.3 před→po ms | MariaDB11.4 před→po ms |
| --- | --- | --- |
|1000 /9000|33,704→22,720 (−32,6%)|63,970→36,869 (−42,4%)|
|10000 /90000|274,179→172,770 (−37,0%)|595,585→367,799 (−38,2%)|
|30000 /270000|834,058→528,475 (−36,6%)|1832,202→1128,470 (−38,4%)|

Vedle přidaných tréninků má fixture1původní trénink; screenshot ukazuje30001.
Při30k krátká kontrola same session498,745→6,886 /1075,784→8,215ms;
different session6,642→8,892 /8,530→8,585ms. Samostatné Měření531,707→506,212 /
1067,979→1068,954ms. Nejde o zrychlení SQL: sdílení DB znamená i konkurenci,
long v actualpair506,520→528,416 /1080,041→1128,412ms, ale celá dvojice se zkrátí.
Different-session actualpair10.3 při1k22,591→27,259ms je malá regresní variace;
30k532,654→541,954ms, 11.4 viz raw summary. Neskrývat tyto kontrolní náklady.

[Chrome raw před](native-11.4/browser-coach-before.json.gz),
[po](native-11.4/browser-coach-after.json.gz), [souhrn](browser-summary.json).
Skutečné standardní přihlášení, uložená záložka Měření, původní dva fetch a původní
UI:3cold+10warm navigací/variantu; žádné mocky či zapínání skrytého hledání.
Warm oba fragmenty fetch→DOM+dvě animační smyčky1882,000→1538,200ms (−18,3%),
celá navigace4611,200→4277,150ms (−7,2%). Cold1877,400→1186,600ms (−36,8%),
celá navigace4610,200→3941,400ms (−14,5%). Teplé vzorky jsou proměnlivé,
nepřepisovat je silnějším cold nebo izolovaným HTTP přínosem.
Parent document TTFB warm2701,550→2707,600ms, load2738,200→2745,700ms:
počáteční profil zůstává další rezervou při této extrémní fixture; není součástí RAS.
Filtry/typ přesně včetně case a trailing space, page/year reset, next/previous,
rychlé záložky, uložená záložka, žádné JS/transportní chyby a note ovládání PASS.
[Desktop](native-11.4/profile-session-after-desktop.png) a
[390px pager](native-11.4/profile-session-after-mobile.png) vizuálně zkontrolované;
před/po obě velikosti dokonce byte-identické. Mobilní dlouhý syntetický text
zůstává úzký, screenshot nedokládá nové UX úpravy ani fyzický mobil.

Mechanismus samostatně: SHOW FULL PROCESSLIST skutečné Měření SQL současně
s neblokujícím flock na vlastním souboru skutečně přihlášené session;
before lockheldtrue /afterfalse, shutdown active true/false, zachovaná identity+CSRF.
Handler explicitněfiles,4PHPCLI workers/server, skutečné HTTP na dvou clusterech.
Produkční handler/produkční concurrency a přihlášené UAT NOT RUN; žádné SLA/LCP.
Celý tested/merged tree a měřený zdroj shodné; [release](../ajax-profile-session-release-20261108/RESULTS.md).

Reprodukce: workflow .github/workflows/profile-session-performance.yml na přesném
SHA; Python runner kontroluje Linux, explicitní SYNTHETIC-ONLY, localhost identity/
owned schéma/config hash,2clustery a cleanup. Lokální cílený příkaz:
`php vendor/bin/phpunit --colors=never --filter "SessionSecurityTest|PublicProfile|SportovecNote|AthleteProfileRead"`.
Lokální native DB není dostupná; native důkaz pochází z obou CI služeb.
