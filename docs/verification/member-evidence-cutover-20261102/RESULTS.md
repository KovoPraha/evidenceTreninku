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
([doklad](runtime-preflight.json)). Zálohování se proto upravuje na samostatné
SSH spuštění již používaného `db-backup.php`; hostingové omezení se nemění.
Před zálohou i aplikací musí být oba soubory údržby stále shodné s ověřeným
pozastaveným stavem. Záloha, nové přepočítání manifestu a kontrola parit zůstávají povinné.

[Izolovaná obnova 41540526914](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41540526914)
ověřila nejnovější zálohu `3c699ad75305b261294f9e0974dac73eac9f49f953425e577557600cb9ae3d6d`:
342 tabulek a 2 triggery PASS. Živá databáze se neobnovovala.

Přihlášené browser ověření se neprovedlo: CDP dvakrát vypršelo na
`Emulation.setFocusEmulationEnabled`, poté computer-use ukončilo přístup kvůli
nemožnosti spolehlivě určit aktuální URL. Toto není přihlášené UAT ani jeho náhrada.

Ostrý převod, následná kontrola a poslední synchronizace zatím nejsou dokončené.
Datum počátku zůstává 2. 11. 2026; poslední synchronizace čeká na další export.
