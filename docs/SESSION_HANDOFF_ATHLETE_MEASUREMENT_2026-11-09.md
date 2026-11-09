# Předání P11-RHMS / RHM — 9. 11. 2026

**RHMS SHODA A HTTP/CHROME PASS — čeká kompletní aktuální CI a release.**

[Plán](ATHLETE_PROFILE_MEASUREMENT_PERFORMANCE_2026-11-09.md#rhms--obě-db-a-celé-httpchrome-ověřené),
[průběh](verification/athlete-profile-measurement-summary-20261109/RESULTS.md),
[aktuální měření](verification/athlete-profile-measurement-summary-20261109/validated-d8/RESULTS.md).
Draft [#422](https://github.com/KovoPraha/evidenceTreninku/pull/422), větev
`codex/athlete-measurement-summary-performance-20261109`, base main0b4592fa.
Izolovaný worktree mimo htdocs, WIP původního checkoutu beze změny.

Kandidát2: COUNT/MAX datum po binárním typu a skalární první tuple pouze
v nejnovějším datu; přesné signed64/NULL řazení malého seznamu typů v PHP.
Jeden SQL statement, původní selector/header/history/navigation a10chráněných
souborů byte-shodné. Bez DDL/cache/session/writer změn.

42674262677/d8d59d54 oba joby SUCCESS: native6/634 každý,1836časovaných AJAX,
52měřených Chrome navigací. Celé objekty/PHPtypy/HTML/JS/role/token/revokace/
CSRF/11checksumů/cleanup PASS. 30k HTTP400.914→253.962ms (−36.7 %,10.3),
672.882→393.144ms (−41.6 %,11.4),20%gate obě PASS. Starý profil +4/+5ms,
malý/prázdný neutrální. Chrome warm celá1243.60→943.50ms (−24.1 %).
Snímky před/po byte-identické a vizuálně ověřené; úzké poznámky beze změny.
SQLite9/930, PHPStan/style a Linux2418/55585 PASS. Produkční UAT NOT RUN.

GitHub dříve Internal server error nevytvořil části CI grafu. Na d8 má jen
16checků (15SUCCESS/CodeQL NEUTRAL), chybějící nejsou PASS. Retry default
CodeQL nepodporován; nový evidence checkpoint musí získat všech20aktuálních
SUCCESS. Pak čerstvý main, whole-tree/source identity, ready/merge #422,
protected deploy import/UATfalse a veřejné před/po. Závěrečné release důkazy
samostatným docs PR do main bez druhého deploye. Zatím žádný nový merge/deploy.

Níže předchozí dokončené RHM, jeho důkazy nepřepisovat.

[Plán](ATHLETE_PROFILE_MEASUREMENT_PERFORMANCE_2026-11-09.md), [měření](verification/athlete-profile-measurement-performance-20261109/RESULTS.md), [release](verification/athlete-profile-measurement-release-20261109/RESULTS.md).

#418/5cc9cf83, tested 54a2b1c1, všech20CI SUCCESS; celý testovaný/sloučený strom a5měřených runtime Gitblobů shodné. Přijaté #416/#419/#420 zachované.
Deploy42654922651 SUCCESS 2026-11-09T16:31:28Z, testy2417/55578, úplná ověřená záloha389/2, migrace/VS/config/index/smoke PASS, import=false/UAT=false. Veřejné8/8HTTP a normalizovanéDOM shodné.

Celé HTTP měření30k/270k: 590.973→424.092ms (-28.2%,10.3); 1348.186→915.044ms (-32.1%,11.4).
Chrome warm uloženéMěření: celá navigace 2191.60→1959.90ms (-10.6%), fetch→DOM+2frames 1441.80→988.35ms. Default/historie a cold/TTFB samostatně v tabulce výsledků; nepřičítat jim úsporu Měření.

Změna pouze measurement selector: total>800/page<=32, nejprve prefix64globálních posledních tréninků a pak vazby; neplná stránka vždy přejde na původní selector. Přesný typ/vlastník ověřen znovu. Původní souhrn, malé/prázdné/vzácné filtry i pozdní stránky zůstávají původní. Žádné aplikační DDL/indexy/ANALYZE/cache/settings/session/UI/writer změny.
Původní dvě agregace, nevyhovující plány/statistiky i nahrazené kandidáty jsou zachované v původních vzorcích. Jen syntetická příprava obnoví společné statistiky před OBOU variantami;20%gate zachovaná. EXPLAIN+metadata dokládají indexové lookupy.

Nativní5/624 na každéDB, SQLite5/312, místní12/1204 PASS.1836časovaných AJAX a52měřených Chrome navigací (+warmupy/interakce). Úplný návratový objekt/PHPtypy, pořadí, počty, přesné typy, PDOrežimy,900vazeb jednoho tréninku,32/33stránka a velký starý profil, signed64 a čerstvé změny PASS. Celé fragmenty/parentDOM/12staffrolí/bearer/freshrevokace/CSRF/logout/2fallbacky/11checksumů a cleanup PASS. PNG ověřené zvlášť; žádná nová UI změna. Diagnostický PHPpeak zahrnuje SQLprofiler, není RAM prostého requestu ani RSS/DBpaměť.

WIP kis_rosters_admin.php SHA2567a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 a původní checkout/větev/untracked soubory zachované.
Přihlášené produkční UAT, produkční výkon, fyzický mobil, nový zápis poznámky a obnova této konkrétní zálohy NOT RUN. Syntetické časy nejsou SLA/LCP.
Finální důkazy přes codex/athlete-measurement-release-proof-20261109 a samostatný dokumentační PR do main bez druhého deploye. Rozsah technicky dokončen.

## Návaznost převzatá novým zadáním

Dominantní zůstává původní numeric-window souhrn (cca446/911ms proti
detailu4/7ms v diagnostice). Další variantu měřit se společnými obnovenými
statistikami; staré GROUP_CONCAT pokusy nejsou univerzálním závěrem pro
jiné varianty. Browser souběh historie/Měření zkoumat odděleně, příčinu
čekání ResourceTiming sám nedokazuje. Souhrn převzat do P11-RHMS; browser souběh zůstává konceptem.
