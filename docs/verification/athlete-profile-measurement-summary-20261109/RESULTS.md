# P11-RHMS — průběžné ověření souhrnu měření

9. 11. 2026. Stav: příprava; žádný nový výkonový výsledek ani release.
[Plán](../../ATHLETE_PROFILE_MEASUREMENT_PERFORMANCE_2026-11-09.md#p11-rhms--souhrn-měření-nové-zadání-9-11-2026).

Baseline main 0b4592fa / produkční runtime 5cc9cf83. Původní WIP SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 shodný.
Parita, párové měření, CI, merge a nasazení nového řezu: NOT RUN.
Původní RHM důkazy zůstávají beze změny v sousedních protokolech.

## Kandidát 1 — místní ověření

Pevný 70znakový binární klíč: datum 9, training ID 20, nullable poradi 21,
measurement ID 20. DECIMAL(20,0) drží signed64 hodnoty přesně. Původní
selector je beze změny. Oracle zmrazen z main0b4592fa (jen název třídy).
Rozšířené hranice pořadí, 124 typů, NULL/zero/částečně zero data, fresh
změny a stávající všechny scénáře: SQLite + profil **9 testů / 930 assertions PASS**.
SQLite používá původní okno; nativní agregační větev tím není ověřená.
Lokální MariaDB není dostupná. Nativní paritu/EXPLAIN a plný bootstrap
provede vlastní syntetické CI na 10.3/11.4. Výkonový výsledek zatím NOT RUN.
Dokumentační kontrola 5076 odkazů / 0 chyb; diff check PASS.

## Kandidát 1 — nativní diagnóza, NEVYBRÁN

[42670510604](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42670510604)
na c1eb4587: obě DB SUCCESS, **6 testů / 634 assertions** na každé,
plný bootstrap 17 → 17 SQL. Surové bezpečné důkazy v `candidate1-diagnostic/`.
Jednorázový instrumentovaný souhrn: 10.3 691.229 → 815.759 ms;
11.4 587.180 → 536.766 ms. Nejde o střídavý HTTP medián ani konečný gate.
Kandidát není vybraný: per-row skládání 70znakového klíče má vysokou režii.
První automatický dlouhý measurement běh PR42670622621 se ukončuje jako
nahrazený (CANCELLED není PASS), aby se neměřil již odmítnutý kandidát.

Další kandidát: group COUNT + MAX datum po přesném typu, skalární korelovaný
výběr prvního tuple jen pro tento typ a nejnovější datum. Řazení malého
seznamu typů v PHP podle stejného tuple včetně NULL a signed64. Stále jeden
SQL statement pro souhrn, stejný stránkový selector, žádné DB nastavení/DDL.
Nativní parita a párové HTTP/Chrome musí znovu projít na aktuálním head.


### Infrastrukturní chyba diagnostického spuštění

42671198622 na 5355238b, pokusy 1/2: GitHub ukončil běh před vytvořením
jakéhokoli jobu. Oficiální stránka běhu uvádí `Internal server error`,
korelace a644b0f1-d288-4f77-a344-f32d3d856fa4. Žádný aplikační test se
nespustil; nejde o PASS ani o zjištěnou chybu kandidáta. Zadat nové
syntetické workflow_dispatch na stejný commit; žádná produkční operace.


## Kandidát 2 — částečná diagnóza

42671904703 opět bez jobů; diagnostika na vlastní PR větvi 42672189982
(pokus1) vytvořila pouze MariaDB10.3. Tento job129348793610 SUCCESS,
parita6/634 PASS, souhrn463.666 → 275.776ms, 17 → 17SQL. Bezpečné vzorky
v `candidate2-partial-diagnostic/`; číselný přínos je pouze jednorázový,
instrumentovaný, nikoli párový HTTP medián. Celý workflow FAILURE kvůli
chybějící matrici11.4, není úplným PASS. Opakování na stejném head5355238b.
Stejná infrastrukturní chyba před joby postihla dokumentaci, historii,
measurement a část CodeQL/standardních matic aktuálního PR. Nesmí se
převzít staré zelené kontroly. Bude nutná úplná aktuální sada.

Selector od `$counts` po konec measurements je byte-shodný s main0b4592fa;
reference obsahuje přesný baseline reader s jediným přejmenováním třídy.
Deset parent/endpoint/token/session/CSRF/scope/canonical souborů beze změny
ověřeno porovnáním s Git blob baseline (čtení canonical LF).


Rerun42672189982/2 zachovává pouze původně vytvořený job10.3; nevytvoří
chybějící11.4. Proto manual-only volba10.3/11.4/both; PR vždy oba backendy.
Nový checkpoint zopakuje všechny aktuální CI; žádné chybějící kontroly
se nenahrazují starým head ani jednorázovou diagnostikou.
