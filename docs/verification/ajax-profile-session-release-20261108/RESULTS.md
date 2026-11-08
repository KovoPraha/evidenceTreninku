# P11-RAS — produkční předání

8. 11. 2026: **NASAZENO, technické ověření PASS**. PR[#406](https://github.com/KovoPraha/evidenceTreninku/pull/406)
sloučený jako95cb33e5676480beea12af4e9049aea9b858c820.
[Plán](../../AJAX_PROFILE_SESSION_PERFORMANCE_2026-11-08.md),
[parita a měření](../ajax-profile-session-performance-20261108/RESULTS.md),
[předání](../../SESSION_HANDOFF_AJAX_PROFILE_SESSION_2026-11-08.md).
Všech[16CI SUCCESS](ci-checks.json) na b2a6c25b14d529fc531ffaf0746f0a4d58c658fe,
[celý tested/merged tree a skutečný měřený zdroj](tree-identity.json) shodné.
Zmrazená route c548d1d3 je totožná s předchozí produkcí82efa16d.

[Deploy42531190845](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42531190845)
**SUCCESS**, dokončeno22:43:32UTC na přesném95cb33e5.
[Metadata/kroky](deploy-summary.json), [kontrolní souhrn](release-controls.json).
PotvrzeníNASADIT, import=false/UAT=false; produktový import skutečně SKIPPED.
Znovu2403testů/54847assertions PASS, záloha389tabulek/2triggery ověřena před
aktivací, migrace current/pending[], dosavadní VS shodné. Existující konfigurace
zachovaná, config_appended=false. Aktivace/HTTPsmoke/private-key HTTP guard/
jmenovaný cleanup PASS. Bez nové migrace, DDL, indexu, cache nebo aktivace role.
[Čtecí index před](index-preflight/medical-index-before.json) i
[po](index-preflight/medical-index-after.json) přítomný. Obnova zálohy NOT RUN.

[Před](before.json), [po](after.json), [porovnání](public-comparison.json):
pět veřejných katalogovýchGET200, profil bez přístupu404, oba AJAX režimy403.
**8/8 HTTP a8/8celých normalizovanýchDOM shodné.** Použita již ověřená v2metoda
z [versionovaného probe](../athlete-profile-release-20261108/public-probe-v2.php.txt),
bez cookies/POST/redirectů a bez rawHTML. Bezpečnostní nonces/CSPmarkery/CSRF a
release assetverze vynechané z porovnání, ostatní DOM zůstává. Žádné zpětné
přepsání neúspěšného staršího RHA release; ten má vlastní zachované důkazy.

## Rozsah a přínos

Jedna aplikační route, šest řádků guarded session_write_close až po všech fresh
přístupových kontrolách a sportovecNoteCanWrite; SQL/HTML/TTL/rotace/writery stejné.
Nastavení produkčního handleru nebylo čteno ani měněno. Vlastní HTTP files-handler
fixture prokázala skutečný SQL/filelock mechanismus; žádný nový route či aktivace
vypnutého globálního hledání. Úprava FullHttpReadFixture je pouze příprava testovací DB.

Native10.3/11.4:72porovnání celých fragmentů přesná,1836časovanýchHTTP,
12rolí na obou route, skutečné login/bearer303, expirace/revokace/scope,
CSRF logout souběžně s čtením bez oživení session a doménové checksumy PASS.
30k/270k fixture: skutečná HTTP dvojice834→528 /1832→1128ms (−37/38%),
krátký same-session wait499→6,9 /1076→8,2ms; samostatné SQL obdobné.
Chrome26navigací: warm oba fragmenty1882→1538ms (−18%), celá navigace4611→4277ms
(−7%); cold dvojice1877→1187ms. Source/raw vzorky a drobné kontrolní regrese
zachované v protokolu; nejde o produkční SLA/LCP ani tvrzení o rychlejším SQL.
Filtry/stránky/typy/záložky/390px/no JS errors PASS, vizuálně zkontrolováno;
oba před/po screenshoty byte-identické. Všechny vlastní workery/schema/account/
config/cookie jars a bridge alias uklizené, soukromé artefakty nevystavené.

Přihlášené produkční UAT, produkční handler/lock wait, pozitivní uložení poznámky
a fyzický mobil NOT RUN. Produkční kontroly jsou anonymní GET bez cookies/POST;
žádné změny rolí, soukromých profilů ani zdravotních příloh. WIP chráněné.

## WIP a předání

Původní checkout C:/xampp/htdocs/evidencePavel zůstává na
codex/fio-auto-reconciliation-recovery; kis_rosters_admin.php stáleSHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.
Untracked rozpracované soubory/outputy zachované. Vlastní nevybraný globální
prototyp je archivovaný lokálně; ajax_global_search.php se v release nemění.
Toto souborové předání je ve větvi codex/ajax-profile-session-release-proof-20261108,
do společného baseline patří až jejím merge do main. Kvůli samotným důkazům
se produkce znovu nenasazuje. Dokončené RHA/RHC/RAS znovu neimplementovat.
Další nejprve diagnostický námět je parent profil při extrémní fixture
(TTFB~2,7s); příčina/nová optimalizace zatím KONCEPT, nikoli implementace či SLA.

[Souborové předání PR#407](https://github.com/KovoPraha/evidenceTreninku/pull/407).
SHA256 manifest používá přesné Git bloby (kanonické LF), nikoli Windows CRLF
pracovní kopii. První lokální kontrola odhalila4odlišné textové hashe kvůli CRLF;
opraven pouze manifest, měřené gzip vzorky ani výsledky se neměnily.
