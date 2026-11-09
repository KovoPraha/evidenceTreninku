# Předání P11-RHMS / RHM — 9. 11. 2026

**RHMS K OVĚŘENÍ — blokuje GitHub CI. RHM zůstává nasazené.**

Aktuální [plán a přesný další postup](ATHLETE_PROFILE_MEASUREMENT_PERFORMANCE_2026-11-09.md#rhms-aktuální-předání--9-11-2026-1825-utc),
[protokol a identity](verification/athlete-profile-measurement-summary-20261109/RESULTS.md).
Draft [#422](https://github.com/KovoPraha/evidenceTreninku/pull/422), větev
`codex/athlete-measurement-summary-performance-20261109`, base main0b4592fa.
Stejný izolovaný worktree mimo htdocs; cizí WIP zachovaný.

Kandidát2: COUNT/MAX date po přesném typu a skalární první tuple jen v
nejnovějším datu. Pořadí filtrů v PHP nad přesnými signed64 a NULL hodnotami,
jediný SQL statement pro souhrn. Původní stránkový selector a10bezpečnostních/
canonical souborů byte-shodné. Kandidát1 nevhodný, raw zachované.

PASS: SQLite/profil9/930, PHPStan, explicitní style4souborů, MariaDB10.3
6/634, aktuální Linux PHPUnit2418/55585. Jedna instrumentovaná SQL diagnóza
463.666→275.776ms,17→17SQL. Není to HTTP medián ani produkční přínos.
MariaDB11.4, celé párové HTTP/Chrome/security a všech20aktuálních CI čekají.
GitHub opakovaně vrací Internal server error při vytváření jobů; chybějící
joby nejsou PASS. Dočasná probe větev nevyřešila infrastrukturu.

Další akce: na aktuální PR větvi spustit `athlete-measurement-performance.yml`
s `diagnostic_only=true`, `mariadb_version=11.4`; potom celý workflow obouDB.
Uložit důkazy, přepočítat canonical Gitblob hashy a dokončit aktuální CI.
Teprve při PASS podle pokynu vlastníka merge/protected deploy/import=false/
UAT=false a veřejné čtecí ověření. Žádný nový merge/deploy zatím neproběhl.

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
