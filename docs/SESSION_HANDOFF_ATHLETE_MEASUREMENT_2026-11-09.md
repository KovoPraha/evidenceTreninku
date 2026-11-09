# Předání P11-RHM — 9. 11. 2026

**RHM HOTOVO / NASAZENO; návazný P11-RHMS ROZPRACOVÁNO**

Aktuální zadání: optimalizovat souhrn měření, ověřit úplnou shodu a změřit
před/po; při PASS commit/merge/protected deploy. [Postup a akceptace](ATHLETE_PROFILE_MEASUREMENT_PERFORMANCE_2026-11-09.md#p11-rhms--souhrn-měření-nové-zadání-9-11-2026),
[nové důkazy](verification/athlete-profile-measurement-summary-20261109/RESULTS.md).
Větev `codex/athlete-measurement-summary-performance-20261109`, base main
`0b4592fa`; stejný izolovaný worktree mimo htdocs. Další krok: kandidát,
SQLite 9/930 PASS, native diagnóza čeká; žádný nový merge/deploy. WIP zachovaný.

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
