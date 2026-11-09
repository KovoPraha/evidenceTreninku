# P11-RHMS — průběžné ověření souhrnu měření

9. 11. 2026. Shoda a párový přínos obou DB/Chrome PASS; aktuální úplné CI a release čekají.
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


## Statické a integrační ochrany

`composer validate --strict` PASS. `php vendor/bin/phpstan analyse
--memory-limit=1G --no-progress` PASS bez chyb. Explicitní dry-run
php-cs-fixer pro čtyři změněné PHP cesty PASS, 0 oprav. Výchozí místní
check-php-style zahrnul také staré untracked output diagnostiky a skončil
na jejich formátu/délce; tyto nesouvisející soubory se neupravují ani
nestagují. Čistý CI checkout musí prokázat standardní changed-file check.

Aktuální a297d4d7 opět: workflow42672778594 (manual11.4) a42672788552
(PR measurement) Internal server error před vytvořením jobů, korelace
3e4b1775-b68d-4fe4-b2fb-865f75f6fa06 / d8258124-a5d9-4097-83bc-e955defff3e6.
Nejde o aplikační FAIL/PASS. Superseded header42672197174 aTesty42672197338
zastavené; nespočítat jako zelené aktuální CI. Zachovat pouze aktuální běhy.
Produkce ověřena stále5cc9cf83/deploy42654922651 SUCCESS; žádný nový deploy.


## Aktuální hranice přijetí

Linux PHPUnit129349525837 na a297d4d7 SUCCESS: **2418 testů / 55585 assertions**,
navazující3/45 také PASS. 42673435146 manual11.4 opět FAILURE bez jobu.
Souhrnné `ci-infrastructure.json` zachovává skutečné stavy a chybějící joby.
`runtime-identity.json`: aktuální reader exact proti native10.3 kandidátu2,
nezměněný selector/header/history/navigation a10chráněných souborů; frozen
reference přesná kromě názvu třídy. Bezpečné JSON artefakty prošly kontrolou.

**K OVĚŘENÍ / BLOKUJE CI:** shoda11.4 a finální párové HTTP/Chrome/role,
celá aktuální sada20CI, merge a deploy nejsou dokončené. Žádné tvrzení o
produkčním výkonu ani přihlášeném UAT. Podmínky přijetí se nesnižují.
Původní WIP a nasazený5cc9cf83 zůstávají zachované. Přesný další krok
v [plánu](../../ATHLETE_PROFILE_MEASUREMENT_PERFORMANCE_2026-11-09.md#rhms-aktuální-předání--9-11-2026-1825-utc).


### Aktuální pokračování po checkpointu d8d59d54

[42674262677](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42674262677)
nyní vytvořil oba celé measurement joby: 10.3/129351851122 a11.4/129351854232.
Nechat doběhnout, neukončovat kvůli již uloženému blokujícímu stavu.
Kandidát/runtime je shodný s5355238b/a297d4d7; změnil se jen dokumentační checkpoint.
Část aktuální CI opět chybí (docs bez jobu, default CodeQL nemá actions,
standardní Testy nemají celou matici), takže merge/deploy zůstávají nepovolené.
Pokud celé measurement testy projdou, uložit aktuální raw, Chrome snímky a
párové tabulky a dokončit všechny chybějící aktuální CI před releasem.


### Celé MariaDB 10.3 PASS — d8d59d54

Job129351851122 SUCCESS. Raw v `validated-d8/native-10.3/`, reader SHA256
přesně odpovídá současnému Git blobu. 918 časovaných AJAX a všech9scénářů,
17 střídavých párů po4warmupech: úplné fragmenty a11checksumů shodné.
Primary1k14.961→12.333ms (−17.6 %),10k111.860→80.260ms (−28.2 %),
30k400.914→253.962ms (−36.7 %). Hlavní20%gate PASS. Malý311.850→297.108,
prázdný295.615→295.443; starý306.782→310.550ms (+3.768ms/+1.2 %),
původní no-regression gate PASS. Není správné tvrdit stejnou procentní
úsporu všem profilům nebo stránkám. Nativní6/634, security a cleanup PASS.
11.4/Chrome a úplná aktuální CI sada stále čekají.


## Finální párové měření kandidáta2 — obě DB PASS

[42674262677](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42674262677),
head d8d59d54: oba joby SUCCESS, nativní6/634 každý. [Celé tabulky, raw,
source identity a metoda](validated-d8/RESULTS.md).
1k/10k/30k ×9scénářů ×17párů po4warmupech,1836časovaných AJAX celkem.
30k/270k HTTP:10.3 **400.914→253.962ms (−36.7 %)**,
11.4 **672.882→393.144ms (−41.6 %)**. Původní20%gate PASS na obou.
Starý900profil +3.768/+5.460ms (+1.2/+0.9 %), gate PASS;
malý/prázdný prakticky neutrální (11.4 +11.125/+3.625ms). Neplatí stejná
procentní úspora pro všechny profily/stránky.

Chrome warm uložené Měření: celá navigace1243.60→943.50ms (−24.1 %),
fetch→DOM+2frames841.50→549.70ms (−34.7 %); cold celá1476.20→1201.70ms
(−18.6 %). Default warm782.05→789.75ms (+1.0 %), dokument TTFB neutrální.
52měřených navigací pluswarmupy/interakce, konzole/transport0chyb.
Desktop i390px screenshot byte-identický a nové snímky vizuálně ověřené;
stávající clipping dlouhých poznámek beze změny. Fyzický mobil NOT RUN.

Celé HTML/JS, role/token/revokace/CSRF,11checksumů a cleanup PASS na obou DB.
Source SHA256 přesné proti současnému Gitblobu. SQLite/profil9/930,
PHPStan/style a Linux2418/55585 PASS. Všechny historické pokusy zachované.
Chybějící docs/default-CodeQL-actions/část Testy nelze vydávat za zelené;
403 retry failed-jobs bez vytvořeného jobu a zákaz retry default CodeQL
potvrzené. Aktuální evidence checkpoint spustí novou úplnou CI sadu;
merge/protected deploy až po všech20aktuálních SUCCESS. Produkce stále5cc.
