# Předání P11-ASSET — 10. 11. 2026

NASAZENO / TECHNICKÁ PŘEJÍMKA PASS.
[Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md),
[výsledky](verification/bootstrap-assets-20261110/RESULTS.md),
[release a skutečné meze](verification/bootstrap-assets-20261110/release/RESULTS.md).

PR #425 → 9dbd9eaf, celý tree shodný s testovaným 6162e6b7.
30 CI SUCCESS, 2 424 testů / 55 908 kontrol. Oba čerstvé běhy obou DB PASS:
42725053241 a 42724887655, celé HTML/business/role/revokace/bearer/CSRF/checksumy.
544 Chrome nav + 128 zahřátí, 52 legacy Měření nav, 8/8 pixelových párů shodných.
Cold FCP profil 68→48/44 ms, Moje 64→48/60→44, Správa 60→52/56→52;
CSS 2→1, jeden požadavek a 30 829 gzip bytes méně. Benchmark není produkční SLA.

Deploy 42727693090 SUCCESS na 9dbd9eaf. Záloha 389 tabulek / 2 triggerů,
konfigurace/migrace/VS/indexy/smoke PASS. Runner HTTP 0, serverový fallback 200;
nezávislých 8 veřejných GET před/po má shodné statusy i normalizovaný DOM.
CSS/SRI živě shodné. /bin chrání hostingový 403, nativní PHP guard má 404/0.
Runtime 9dbd9eaf; uat_schvaleno=false, importovat_krouzky=false.

Aplikační diff pouze 3 smazané CDN linky; reader/db/header a další runtime
shodné s ff182971. RIX uzavřen bez přijaté SQL/index varianty, žádná nová aktivace.
WIP původní fio větve / kis_rosters_admin7a7afd75 zachovaný bez stash/clean/reset.
Doklady sdílí [PR #426](https://github.com/KovoPraha/evidenceTreninku/pull/426),
jen docs: po sloučení se runtime znovu nenasazuje. Technická práce uzavřená;
lidské přihlášené produkční UAT, fyzický mobil a obnova této zálohy NOT RUN.
