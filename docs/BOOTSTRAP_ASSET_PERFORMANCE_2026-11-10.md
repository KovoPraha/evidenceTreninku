# P11-ASSET — Bootstrap CSS načítaný právě jednou

Zahájení 9./10. 11. 2026, Europe/Prague. ROZPRACOVÁNO. Vlastník vývoj/Codex.
[Registr](WORK_PLAN_CURRENT.md), [předání](SESSION_HANDOFF_BOOTSTRAP_ASSETS_2026-11-10.md),
[důkazy](verification/bootstrap-assets-20261110/RESULTS.md).

## Zadání a výběr

Aktuální pokyn vlastníka: další optimalizace s největším doloženým přínosem,
shoda výsledků, před/po; při plném PASS commit/merge/protected deploy.
WIP kis_rosters_admin.php v původním htdocs SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 zachovaný.
Pracovní větev codex/athlete-targeted-probe-20261109, následně vlastní PR425
codex/athlete-targeted-read-optimization-20261109; izolovaný worktree mimo htdocs.
Baseline ff182971, skutečná produkce17110a7f/deploy42679050552; žádný nový deploy.

[Předchozí RIX diagnóza](ATHLETE_PROFILE_TARGETED_READ_2026-11-09.md) vyřadila
SQL/index varianty kvůli regresím nebo nedostatečnému zisku na obou DB.
Původní reader je obnoven přesně z ff182971. Nepřidávat spekulativní index.
Další skutečný nález původní analýzy bod7: sportovec_treninky.php,
moje_treninky.php a sprava_treninku.php načítají Bootstrap5.3.3 z CDN i lokálně.
Canonical Git lokální CSS je232803bytes a SHA384
QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH,
přesně stejný jako SRI duplicitního CDN linku. appUiAssets už poskytuje lokální
CSS na všech3cestách; profiler/body layout se nemění odstraněním první kopie.
Ověřit také skutečný lokální/CDN payload přes HTTPS a skutečný Chrome.

## Rozsah a akceptace

1. Odstranit pouze3 známé duplicitní CDN stylesheet linky, zachovat lokální
   appUiAssets a veškeré další HTML/PHP/JS/CSS. Žádné DB/SQL/writery/role/session/
   tokeny/CSRF/cache konfigurace. Po odpočtení těchto3konkrétních tagů musí být
   canonical zdroje identické s ff182971; reader byte-shodný celý.
2. Celé HTTP před/po na stejném syntetickém serveru/schema oběMariaDB10.3/11.4,
   původní business kontroly, canonical checksumy, 1k/10k/30k. Parent HTML po
   odstranění POUZE známého CDN tagu při současné právě1lokální kopii identické.
   Žádné obecné ignorování link/script atributů; negativní test normalizátoru.
   Uložit i raw bytes/hash. Oprávnění/revokace/bearer/CSRF staré security PASS.
3. Skutečný Chrome, žádné request mocky ani umělé zpomalování CDN. Přímé měření
   v1kfixture (řádově blízká produkčnímu odhadu1667tréninků),3stránky desktop/
   390px a samostatný read-only bearer profil.17střídavých párů po4warmupech
   cold/warm; reálný Resource Timing/CDP, FCP, load a hotový obsah, konzole.
   Bootstrap stylesheet2→1 a redundantní232803decoded bytes odstraněné v cold
   scénáři; žádný CDN Bootstrap request po změně. CSS payload přesně stejný.
   Časové úspory zveřejnit skutečné, neslibovat rychlejší SQL/server. Cold/warm
   FCP a hotový obsah nesmějí významně regresovat (po<před*1.2+20ms).
   Tento frontendový gate nahrazuje neúspěšný SQL20%gate: nový cíl je odstranění
   poloviny Bootstrap CSS požadavků/bajtů, nikoli vymyšlené zrychlení DB.
4. Shodný viditelný obsah, computed styles a desktop/mobil screenshoty; dropdown/
   navbar/tab/pagination fungují, Bootstrap JS se nemění. Dále skutečný Chrome
   default/uložené Měření velkého profilu jako kontrola starých UI toků.
5. Všechny aktuální CI SUCCESS, exact source/tree identity, pak squash PR425
   a protected deploy z main. Úplná záloha/migrace/VS/config/smoke a veřejné
   čtecí před/po; import/UATfalse, lidské produkční UAT samostatně NOT RUN.
   Finální důkazy docs PR bez druhého deploye. Nic nepovolovat podle HTTP200.

Další krok: přesný tag removal, úzký testovací normalizátor a nativní HTTP/
Chrome profil. Implementace/CI/merge/deploy optimalizace dosud NOT RUN.


První příprava42718340757/f9 selhala správným302 trenéra na hlavni-only stránce.
Přejímka3stránek poběží pod existujícím sports_lead, veřejný bearer samostatně;
žádná změna oprávnění. Model3/562 a normalizátor1/6 oběDB PASS, cleanup PASS.
Časová měření a app merge/deploy pořád NOT RUN. CLI bloky34vzorků zachovají
17střídavých párů/4warmupy každého cold/warm/viewport/route.

Druhý42718778899 zastaven na správném403 vedoucího sportu u trenérských Moje.
Oprava měřicího scénáře respektuje samostatné pracovní pozice: coach profil/
Moje, sports_lead Správa, samostatný public bearer; žádné slučování rolí.
Negativní302/403 se nově ověřují před/po. Stále žádné app merge/deploy.
