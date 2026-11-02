# Sloučení a nasazení druhé funkční vlny — 2. 11. 2026

Vlastník nyní výslovně povolil sloučení a nasazení dokončené vlny PR #274.
Heslo a T07 zůstávají vynechány. Nejde o schválení plného lidského/účetního
UAT ani aktivaci importů a integrací. Localhost seed se v produkci nespouští.

Pracovní kopie `evidencePavel-functional-round2-release-20261102`, větev
`codex/functional-round2-release-20261102`; původní WIP i testovací kopie zachovány.

## Postup a stav

1. Znovu ověřen přesný head PR #274 `b76e8c8c9cd5ecb94b45bdbc630caf867158a49f`,
   base/main `25085e4e`, všech 11 kontrol SUCCESS. Linux 1905/38263 bez skipů,
   obě MariaDB a profily PASS. Ochrana main strict/admins zůstává aktivní.
   Oprava se týká CLI přípravy lokálního dema; veřejné formuláře ani migrace se nemění.
2. Před nasazením čtecí audit `41436805053` SUCCESS a HTTP 26/26. Potvrzeno c1686b48,
   integrační flags false, canonical writes false a ledger 143.
3. Squash merge `51796fcafa16823f6c84208406a5c75682023409`; sloučený strom je shodný s ověřeným.
4. Chráněný deploy `41437064975` SUCCESS, SHA 51796fca. UAT/import false.
   Deploy 1905/38263, ověřená záloha 337/2, schema current/pending [], VS zachovány.
5. Čtecí audit po `41437714419` SUCCESS potvrdil živé SHA/run, nezměněný hash
   konfigurace, integrační flags, kandidáty příloh, canonical writes false a ledger 143.
   HTTP před/po 26/26, webové guardy 3/3 a veřejný Chrome katalog/řazení/hledání PASS.
   Vlastní karta zavřena; seed se v produkci nespouštěl, žádné nové uživatelské záznamy.
6. [Protokol](verification/functional-round2-release-20261102/RESULTS.md) a bezpečné doklady
   uloženy. Starý CURRENT_STATE přesně archivován, aktuální stav/předání aktualizovány.
   Dokumentační kontroly PASS: 60 řádků stavu, 498 odkazů a 94 neporušených
   archivních bloků; PHPUnit 2/14, Composer validate/audit a git diff --check.
   Dokumentační dokončení probíhá samostatným PR přes CI a merge; stav určuje GitHub.
   Další produkční nasazení kvůli dokumentaci není potřeba.

CI main 41437040472 při prvním pokusu narazilo na síťový timeout Packagistu
v composer audit. Zopakovaný je pouze selhaný job, bez oslabení kontroly;
jeho aktuální stav určuje GitHub. Všechny databázové joby už prošly.

Merge, deploy a produkční ověření dokončeny. Dokumentační předání má vlastní
PR a CI; heslo/T07 a lidská/účetní přejímka zůstávají oddělené a neprovedené.
