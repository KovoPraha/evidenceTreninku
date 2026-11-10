# P11-JSZ — finální vydání 10. 11. 2026

NASAZENO / TECHNICKÉ OVĚŘENÍ PASS.
[PR #427](https://github.com/KovoPraha/evidenceTreninku/pull/427) sloučen jako
`f682cc46de7d6dbb6876f79187b069589b134614`. Celý tree je identický s testovaným
`d27b82cd96997d72c549a5b3d2bd5d0fdfad47b9`, tree `a7fa5de144b961446dc35d4a9969eddd555409c2`.
[Všech 12 CI SUCCESS](app-checks.json), PHPUnit 2424 testů /55908 assertions,
obě MariaDB integrační i read-profile matice PASS; PHPStan, CodeQL a docs PASS.

[Protected deploy42774356221](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42774356221)
SUCCESS na přesném SHA, skutečný checkout ověřený z logu.
[Stav workflow](deploy-summary.json) a [release receipt](release-controls.json):
importovat_krouzky=false, uat_schvaleno=false, import přeskočený. Testy prošly
znovu i v deployi. Úplná ověřená záloha389tabulek/2triggerů, původní konfigurace
zachovaná bez doplňování, privatekey záloha/webcopy PASS. Čekající migrace[],
původní VS shodné; [covering index před/po](index-preflight/medical-index-after.json)
a definice shodné. Přímý runner HTTP smoke200, privatekey HTTP403, oba úklidy SUCCESS.

## Skutečná produkce před/po

[Čerstvé TLS GET před](../production-before-final.json) a [po](../production-after.json)
jsou bez cookies, redirectů a zápisů. U všech13 veřejných JS gzip/identity200,
rozbalený SHA256 shodný s kanonickým Gitem i baseline; Cache-Control:max-age=30
zachovaný. Nově Content-Encoding:gzip a Vary:Accept-Encoding. U2 hlavních také
q=0→identity a HEAD PASS. CSS i root sw.js mají stejné bytes, encoding a cache;
.htaccess/config/bin HTTP403. Skutečný přenos payloadu, bez TCP/TLS/hlaviček:

| Rozsah | Před | Po | Úspora |
| --- | ---: | ---: | ---: |
| Bootstrap bundle | 80721 B | 23799 B | 56922 B |
| app-ui.js | 8561 B | 2819 B | 5742 B |
| Hlavní2 JS | 89282 B | 26618 B | 62664 B /70,19 % |
| Všech13 veřejných JS | 147587 B | 45775 B | 101812 B /68,98 % |

[Osm anonymních GET před](../public-before-final.json) a [po](../public-after.json)
má shodné HTTP statusy a normalizované DOM otisky, ověřeno po jednotlivých případech.
Žádné raw HTML, cookies, relace, dumpy nebo konfigurace nejsou v těchto dokladech.

## Reprodukovatelné měření a meze

[Čerstvý Linux2.4.66 Apache](native.json), [Chrome mediány](browser-summary.json),
[136 měřených +32 zahřívacích navigací](browser-samples.json.gz),
[ukončení vlastních procesů](cleanup.json) PASS. Stejné2 requesty a89282 decodedB,
DOM/style/pixely i Bootstrap/app-ui funkční kontrakty shodné, JS/síťové chyby0.
Linux cold FCP24→24ms, load desktop20,6→21,1ms /mobile20,4→21,3ms;
warm load desktop13,5→13,4ms /mobile13,9→13,7ms. **Časové zrychlení stránky
neprokázané**; prokázaná je úspora přenosu. Windows2.4.58 a první Linux běh jsou
samostatně v [průběžném protokolu](../RESULTS.md), nepřepisují se na tento běh.

Doba cache zůstala30s. Default Apache gzip ETag AddSuffix může při revalidaci
po expiraci vrátit plný200 místo identity304, proto se pro tento scénář úspora
netvrdí. Zachováno bezpečné rozlišení reprezentací a identity304/range;
žádné NoChange, force-gzip nebo prodlužování TTL.

Syntetický Chrome není přihlášené produkční/lidské UAT ani fyzický mobil.
Obnova právě této produkční zálohy a přihlášené produkční UAT: NOT RUN.
Samotné PHP/SQL, původní JS, root bezpečnostní pravidla a SW se nemění;
jediný aplikační diff je7řádků assets/.htaccess. [Zdrojová/WIP shoda](tree-identity.json).
HJS prototyp4c6ffea0 zůstává pouze ve vlastní publikované větvi, nebyl sloučen.

[Plán](../../../JAVASCRIPT_COMPRESSION_PERFORMANCE_2026-11-10.md) a
[předání](../../../SESSION_HANDOFF_JAVASCRIPT_COMPRESSION_2026-11-10.md)
se uzavírají v dokumentační větvi codex/javascript-compression-release-docs-20261110,
bez druhého nasazení. Další výběr optimalizace vyžaduje nový waterfall aktivní stránky.
