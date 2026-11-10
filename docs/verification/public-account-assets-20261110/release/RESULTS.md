# P11-ASSET3 — produkční vydání

10. 11. 2026. NASAZENO / CÍLOVÉ TECHNICKÉ OVĚŘENÍ PASS.
[PR432](https://github.com/KovoPraha/evidenceTreninku/pull/432), runtime
beead42591c1f84c66f42c411ce6f1681c02a16a, identický celý tree s58e97f55;
[13CI SUCCESS](../app-checks.json), PHPUnit2425/55924 a obě MariaDB matice PASS.
[Testovaný řez a měření](../RESULTS.md), [tree](../tree-identity.json).

[Deploy42810948814](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42810948814)
SUCCESS na přesném merged SHA; skutečný checkout SHA a všechny4 runtime soubory
v rsync logu ověřené. Import a UATfalse, import přeskočený. Testy znovu2425/55924
PASS. Ověřená záloha389tabulek/2triggerů, SHA
ec738b60f3dffa91a415033f290c774b9345962c0db9dcb5b91f83e95e9aa634.
Config zachovaný bez doplnění; soukromý klíč záloha/webcopy PASS; migrace pending[],
dosavadní VS i indexové definice/inventory shodné; soukromý HTTPklíč403.
Tentokrát přímý GET z runneru HTTP200, serverfallback nebyl potřeba.
Oba úklidy SUCCESS. [Receipt](release-controls.json), [workflow](deploy-summary.json).

## Živá kontrola výsledku

Skutečný Windows Chrome před/po,5cílových variant (4stránky včetně obou obnov)
a1control: všechny HTTP200, CSS2→1 u cílových a1→1control. Žádné JS/networkchyby,
jen oddělená volitelná favicon404. Produkční cold Bootstrap encoded payload
53957→31248B (−22709B / −42,09%), decoded465606→232803B. Finální CI má
odlišnou CDNbr reprezentaci a své měření64454→31248B; hodnoty se neslévají.
Produkční časové zrychlení z těchto jednorázových GET neměříme jako SLA.

První přísný HTMLhash nesouhlasil i na nezměněné control. Nasazení obnovilo
mtime všech pěti společných assetů1794302581→1794311532. Pravidlo podle filemtime
je dosavadní chování appUiAssets(), runtime soubor se tímto řezem neměnil.
Při přesném nahrazení pouze těchto5známých href/src verzí zachycenou before
hodnotou se všech6celých HTMLhashů vrací přesně k původnímu before; ostatní
HTML se dál nemaskuje. Odstranění známého CDNtagu a nonce/CSRF allowance
jsou stejné jako v nativním testu. [Before](../live-before.json),
[původní after](../live-after-initial.json), [přesný replay proof](../live-after-version-proof.json)
a [reprodukce](../live-chrome-version-proof.js.txt).

Navíc10samostatných HTTPSGET pěti přesných starých/nových verzovaných URL:
Bootstrap CSS/JS, iconsCSS, app-uiCSS/JS mají byte-for-byte canonicalGit payload
a stejný cache/contentencoding header. [Důkaz](../versioned-assets.json).
[CSSbefore](../production-css-before.json) a[after](../production-css-after.json):
232803canonical decodedB/SRI, gzip31248B, max-age604800; workspace stále302.
JSmax-age30 zachované. Žádná produkční registrace, login nebo obnova se neodeslala.

## Doplňkový katalogový probe a meze

Starší doplňkový8GETprobe má[8/8stejných stavů](../public-after.json), ale jen
5/8stejných DOMhashů proti[before](../public-before.json). Catalog/price/page2
hashy jsou odlišné;[druhý after](../public-after-repeat.json) je všech8stabilní.
Prázdné hledání, search a tři odmítnuté profilové GET jsou shodné. Původní
bezpečný probe neukládá raw beforeHTML, proto přesný původ této doplňkové
odlišnosti nelze zpětně doložit; **katalogovou DOMparitu neoznačujeme PASS**.
Kód katalogu a jeho závislosti se mezi oběma runtime SHA nezměnily. Přísná
shoda všech6cílových accountHTML, sourcecontract,350checksumů a skutečná
pixelová/funkční přejímka cílového řezu prošly. Před dalším katalogovým řezem
zachytit novou strukturovanou baseline s bezpečnými dílčími otisky a kontextem dat.
Toto je omezení doplňkové produkční evidence, nikoliv prohlášení katalogové regrese za opravenou.

Přihlášené produkční/lidské UAT, fyzický mobil a obnova právě této zálohy NOT RUN.
Syntetické816+192Chrome,12pixelpairs/48UIscénářů a obě nativní DB PASS.
WIP kis_rosters_admin.php SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 a původní
větev codex/fio-auto-reconciliation-recovery zachované. HJS/RIX prototypy nepřevzaté.
Finální důkazy jsou v samostatné docs větvi codex/public-account-assets-release-docs-20261110,
bez druhého nasazení. [Plán](../../../BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset3--veřejné-vstupy-k-účtu-10-11-2026),
[předání](../../../SESSION_HANDOFF_BOOTSTRAP_ASSETS_2026-11-10.md).
