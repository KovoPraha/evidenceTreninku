# Převod členství — provozní důkazy

## Ověřená příprava

- [PR #288](https://github.com/KovoPraha/evidenceTreninku/pull/288), všech 11 kontrol PASS,
  **1957 testů / 38324 assertions**, MariaDB 10.3/11.4 včetně READ ONLY náhledu.
  Head `b03914ea556f97a41db3cf83dfce440e9ac88ab9` má stejný strom jako merge
  `7c31ea174dae063fc781f708b8caa105ba57feb6`.
- [Nácvik 41538817658](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41538817658)
  PASS: přesná mapa, 8 skupin / 44 hran, 435 převzatých zařazení, 560 odstraněných
  historických zařazení a 560 importních událostí, zachování dat a pilotu,
  idempotence, odebrání oprávnění technické identity a obnova 337 tabulek.
  [Souhrn](rehearsal.json) neobsahuje osobní identity.
- [Nasazení 41539094174](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41539094174)
  SUCCESS. Nové migrace nebyly potřeba, UAT false, import kroužků false.
  [Vydání a záloha](release-comparison.json); před převodem HTTP [40/40 PASS](pre-http.json).
- [Produkční náhled 41539667209](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41539667209)
  SUCCESS v READ ONLY transakci: 435/560, den 2. 11. 2026, 8 skupin.
  [Neosobní souhrn](preview.json); soukromý úplný manifest zůstává mimo webroot.

## První ostrý pokus — nic se nepřevedlo

[Běh 41539911294](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41539911294)
se zastavil ve fázi `runtime`, **před údržbou, zálohou, vytvořením identity a jakýmkoliv
zápisem členského převodu**. Výstup má `cutover_committed=false`;
[doklad pokusu a recovery](attempt-41539911294.json). Následná HTTP kontrola PASS.

Původní kontrola nerozlišovala nesoulad vydání, chybějící CLI funkci a odlišný
soubor. Diagnostika proto nově uvádí přesné nasazené SHA, dostupnost procesů/HTTP
a otisky souborů jádra; nečte ani nevypisuje přístupové údaje.

[Čtecí běh 41541533269](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41541533269)
potvrdil příčinu: **proc_open=false**, cURL a HTTPS streams dostupné. Živé SHA
odpovídá 7c31ea1 a všech devět souborů jádra má shodný SHA-256 s tímto commitem
([doklad](runtime-preflight.json)). PR #290 upravil zálohování na samostatné
SSH spuštění již používaného `db-backup.php`; hostingové omezení se nemění.
Před zálohou i aplikací musí být oba soubory údržby stále shodné s ověřeným
pozastaveným stavem. Záloha, nové přepočítání manifestu a kontrola parit zůstávají povinné.

[Izolovaná obnova 41540526914](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41540526914)
ověřila předchozí zálohu `3c699ad75305b261294f9e0974dac73eac9f49f953425e577557600cb9ae3d6d`:
342 tabulek a 2 triggery PASS. Živá databáze se neobnovovala.

Přihlášené browser ověření se neprovedlo: CDP dvakrát vypršelo na
`Emulation.setFocusEmulationEnabled`, poté computer-use ukončilo přístup kvůli
nemožnosti spolehlivě určit aktuální URL. Toto není přihlášené UAT ani jeho náhrada.

## Potvrzený produkční převod

[Běh 41542954307](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41542954307)
provedl commit 2. 11. ve 23:11:05 UTC (3. 11. v Praze). Nástroj z PR #290
`09df7a14804323393f1df923e9fa8c52af649728`; živá aplikace zůstala na 7c31ea1.
Všech 11 kontrol #290 PASS, Linux 1957 testů / 38326 assertions.

- 44 soupisek bez sezony, 8 skupin / 44 hran podle schválené mapy.
- 435 výchozích intervalů od **2. 11. 2026**, 560 historických intervalů a 560 importních událostí odstraněno.
- 560 technických odkazů na odstraněná členství odpojeno; mapy osob zachované pro poslední synchronizaci.
- Parity chráněných dat, skupinového členství a trenérského pilotu PASS; idempotence PASS.
- Technická identita neaktivní, dočasné oprávnění odebrané, konfigurace a provoz obnoveny.
- [Výsledek přípravy, commitu a recovery](cutover-41542954307.json) obsahuje pouze agregace.

**Celý workflow má výsledek FAILURE:** až následný GitHub-runner HTTP požadavek
vypršel na spojení (curl 28). Commit i recovery předtím uspěly. Převod se neopakuje.
Izolovaná obnova čerstvé zálohy byla kvůli tomuto timeoutu přeskočena; čeká na
samostatné čtecí ověření. Záloha má 342 tabulek / 2 triggery, SHA-256
`c633457ae631140bc30951f488855baf9b5251d347a107f13df894bce6725203`.

## Nezávislé kontroly po převodu

- [Anonymní HTTP](post-http.json): **40/40 PASS**, žádné mutující POST.
- [Čtecí inventura 41543567289](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41543567289):
  všech 44 soupisek a 8 skupin přesně odpovídá schválené mapě (názvy, kódy, typy, season_id NULL).
  438 intervalů = 435 převzatých + 3 dosavadní vlastní; 0 `kis_shadow`, 1507 osob beze změny.
  [Agregované srovnání](post-inventory.json), skutečná mapa ID zůstává mimo Git.
- [Čtecí kontrola 41544284892](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41544284892):
  [metadata vydání, ledger a přepínače](post-release-check.json) beze změny; integrace vypnuté.
  Historická porovnání zdrojů obecného drillu nejsou aktuálním runtime auditem.

Přihlášené UAT a poslední synchronizace nejsou dokončené. Nový export nebyl předán;
počáteční datum zůstává 2. 11. 2026. E8 zachová mapy a bod návratu do jejich vypořádání.
