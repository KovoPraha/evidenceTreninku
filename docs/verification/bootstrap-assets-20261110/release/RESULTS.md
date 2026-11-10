# P11-ASSET — vydání 10. 11. 2026

NASAZENO / TECHNICKÉ OVĚŘENÍ PASS.
[PR #425](https://github.com/KovoPraha/evidenceTreninku/pull/425) sloučen jako
`9dbd9eaf1f78e364d1dacd1ae0e3969758b7a176`. Celý tree je shodný s testovaným
`6162e6b7`: všech 30 CI kontrol SUCCESS, 2 424 testů / 55 908 assertions.
[Deploy 42727693090](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42727693090)
SUCCESS na tomto přesném SHA. `uat_schvaleno=false`, `importovat_krouzky=false`.

[Receipt](release-controls.json) a [stav workflow](deploy-summary.json): úplná
ověřená záloha 389 tabulek / 2 triggerů, původní konfigurace zachovaná bez
doplňování, žádné čekající migrace, původní VS beze změny. Indexové definice
před/po shodné. Testy prošly znovu i v deployi. Přímé síťové spojení runneru
vrátilo HTTP 0; předepsaný fallback provedl skutečný HTTP GET ze serveru a
vrátil 200. Oba úklidy diagnostiky a konfigurace SUCCESS. Import byl přeskočen.

[Čerstvých 8 GET před](../public-before-final.json) a [8 GET po](../public-after.json)
mají totožné stavové kódy a normalizované DOM hashe. Bez cookies, redirectů a
POST, s TLS ověřením. [Živá kontrola CSS a CLI](../live-verification.json)
potvrdila canonical CSS 232 803 bytes a stejný SHA384/SRI jako před vydáním.
HTTP do `/bin/diagnose-athlete-read-schema.php` je zakázáno hostingem: 403,
326 bytes stejného obecného Forbidden dokumentu jako u existujícího migrate.php.
Je to očekávaná stávající `.htaccess` ochrana celého /bin. Původní předpoklad
produkčního 404/0 platí pro samotný PHP guard ověřený nativně; před Apache
zákazem se PHP nespustí. Žádná výjimka v přístupu ani změna produkčního kódu.

[Úplná technická měření](../RESULTS.md): dva čerstvé běhy obou MariaDB,
544 měřených Chrome navigací + 128 zahřátí a 52 regresních navigací. Celé
HTML, výsledky, role, revokace, bearer, CSRF a doménové checksumy shodné.
Všech 8 dvojic screenshotů pixelově identických, shodné computed styles a
funkční mobilní offcanvas. Cold FCP o 7–35 % nižší; jeden požadavek a ověřených
30 829 gzip bytes méně. SQL se nemění; benchmark není produkční SLA.

Finální doklady, plán a handoff sdílí [docs PR #426](https://github.com/KovoPraha/evidenceTreninku/pull/426).
Dokumentační PR nemění runtime a nevyžaduje druhý deploy. Cizí WIP v původním
checkoutu zůstal zachovaný. Lidské přihlášené produkční UAT, fyzický mobil,
produkční výkon s přihlášením a obnova právě této zálohy: NOT RUN.
