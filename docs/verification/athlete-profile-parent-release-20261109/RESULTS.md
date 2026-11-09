# P11-RAP — produkční předání

9. 11. 2026: **NASAZENO / technické ověření PASS**. PR[#408](https://github.com/KovoPraha/evidenceTreninku/pull/408)
sloučený jako ca5fc219ab9d32e0b5ea9a50c669436d39d50ab9.
[Plán](../../ATHLETE_PROFILE_PARENT_PERFORMANCE_2026-11-09.md),
[měření a přesná parita](../athlete-profile-parent-performance-20261109/RESULTS.md),
[předání](../../SESSION_HANDOFF_ATHLETE_PARENT_2026-11-09.md).
[Všech16CI SUCCESS](ci-checks.json) na95305c2a4f2a4569a92da03e3199da7e34e32f34.
[Celý tested/merged tree a4sourcehashy](tree-identity.json) shodné, starý parent
baseline02904936 totožný s předchozí produkcí95cb33e5.

[Deploy42574460894](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42574460894)
**SUCCESS**, dokončeno06:45:44UTC na přesném ca5fc219.
[Metadata/kroky](deploy-summary.json), [kontrolní souhrn](release-controls.json).
Potvrzení NASADIT, import=false/UAT=false, produktový import skutečně SKIPPED.
2405testů/54909assertions PASS; ověřená záloha389tabulek/2triggery před aktivací,
migrace current/pending[], původní VS beze změny. Konfigurace zachovaná,
config_appended=false. Aktivace/HTTPsmoke/private-key HTTP guard a cleanup PASS.
[Čtecí index před](index-preflight/medical-index-before.json) i
[po](index-preflight/medical-index-after.json) přítomný, žádný nový DDL/index.
Obnova této konkrétní zálohy NOT RUN, nikoli převzatá z předchozího release.

[Před](before.json), [po](after.json), [porovnání](public-comparison.json):
pět veřejných katalogovýchGET200, profil bez přístupu404, oba AJAX režimy403.
**8/8 očekávaných HTTP stavů i8/8celých normalizovanýchDOM shodné.**
Čtení bez cookies/POST/redirectů; [versionovaný v2probe](../athlete-profile-release-20261108/public-probe-v2.php.txt),
vynechané pouze skripty/bezpečnostnínonce/CSPmarker/CSRF a release CSSverze.
Žádné soukromé profily nebo rawHTML do Gitu. Žádný zpětný přepis starých RHA
neúspěšných normalizací; tento release má vlastní správné before/after vzorky.
Úplný canonical sports_activity.php, training_closure.php,readmodel historie,
AJAX endpoint i security/CSRF/note gate mají stejné Git bloby jako předchozí produkce.
Změněn pouze parent (include+volání) a nový read-only3součtový model.

## Ověřený přínos a meze

Při30k/270kfixture parentHTTP1890→864 /3034→1356ms (−54/55%),
PHPpeak96,6→1,26MB, SQL22→17 a94771→15vrácených řádků.
Chrome warm default4126→2433ms (−41%), savedMěření5074→2944ms (−42%).
Přesné normalizované celéHTML/JS,12staff rolí,bearer303,expiry/revokace/scope,
invalid/validCSRF/logout,byte-parita15fragmentů/DB a11doménovýchchecksumů PASS.
Nový reader2/54 nativně každáDB iSQLite, širší místní49/324,612časovanýchHTTP,
52Chrome navigací, filtry/typy/záložky/pagery/390px/noerrors a všechny own cleanupy PASS.
Snímky po dokončeném fade před/po jsou byte-identické a vizuálně zkontrolované.

Syntetické časy nejsou produkční SLA/LCP; nové měření vlastní latency AJAX se
touto změnou neslibuje (SQL beze změny, variabilní komponenta v prohlížeči).
Přihlášené produkční UAT a pozitivní uložení poznámky/fyzický mobil NOT RUN.
Žádná produkční změna rolí/osob, providerů, importů nebo automatizací.
Původní checkout/WIP/historické worktrees zůstávají zachované.

## Předání a návaznost

Původní checkout C:/xampp/htdocs/evidencePavel zůstává na
codex/fio-auto-reconciliation-recovery, kis_rosters_admin.php SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5` shodný.
Vlastní worktree i všechny původní untracked soubory/outputy zachované.
Souborové důkazy předává větev codex/athlete-parent-release-proof-20261109
samostatným dokumentačním PR do main; samotná dokumentace nevyžaduje druhý deploy.
Výchozí společný stav vždy ověřit čerstvým main, GitHub PR a jeho CI.
RHA/RHC/RAS/RAP hotové. Další diagnostický KONCEPT: sdílet výpočet roků a
celkového počtu historie, které stále opakují stejný UNION. Aktuální3header
agregace trvají při této fixture zhruba0,4–0,5s každá; příští zásah musí mít
novou baseline, přesnou paritu null/0dat a PHPtypů, žádnou cache čerstvých práv.
