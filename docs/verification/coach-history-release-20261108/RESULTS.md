# P11-RHC — produkční předání

8. 11. 2026: **NASAZENO, technické ověření PASS**. Původní veřejný katalog
má mezi vzorky změněný obsah; jeho úplná původní parita se netvrdí.
[Plán](../../COACH_HISTORY_PAGE_PERFORMANCE_2026-11-08.md),
[parita a měření](../coach-history-page-performance-20261108/RESULTS.md),
[předání](../../SESSION_HANDOFF_COACH_HISTORY_2026-11-08.md).

PR [#400](https://github.com/KovoPraha/evidenceTreninku/pull/400) sloučené jako
`da152282ad4274690595c188a78dec0c90422f38` po **14 CI SUCCESS** na
`b4022c2a3b306d98ff0afdbba64f5ec342bb1e66`. [Snapshot](ci-checks.json),
[celý tested/merged tree a čtyři měřené sourcehashy](tree-identity.json) přesně shodné.
Převzaté již sloučené #396/#395 zachované. Referenční oba původní route soubory
ab60bfdb identické i se skutečnou předchozí produkcí1c828fbf/deploy42492821311.

[Deploy42494231010](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42494231010)
**SUCCESS**, dokončen15:25:47UTC. [Metadata a kroky](deploy-summary.json).
Import=false / UAT=false. Znovu **2400 testů /54217 assertions** PASS;
úplná záloha **389 tabulek /2triggery** ověřená před aktivací. Migrace current,
pending[], dosavadní VS shodné. Aktivace, standardní HTTP smoke, kontrola
soukromého klíče i odstranění vlastních diagnostických souborů PASS.
Bez nové migrace/DDL tohoto řezu, nové služby, role nebo automatizace.
Stávající zdravotní index přítomný [před](index-preflight/medical-index-before.json)
i [po](index-preflight/medical-index-after.json); pouze READ ONLY metadata,
bez klinických řádků a DDL v diagnostice.

## Měřená shoda a přínos

Syntetické MariaDB10.3/11.4 každá2testy/819assertions, úplná množina všech stránek
a vazeb shodná. Čtení10k1056→43ms /565→24ms;26→8SQL,220000→10550řádků.
Skutečný AJAX1001tréninků148→10ms,12,16MB→308kB. Chrome warm fetch→DOM+dvě
renderovací smyčky4636→44ms, malý default43,00→43,85ms neutrální.
476časovanýchHTTP,4zamítnutéGET+2revokace,78Chrome a skutečné interakce PASS.
Osm checksumů beze změny, fixture/server/účet/route cleanup PASS.
Jediný výsledek má malou režii; nejde o produkční SLA nebo LCP.
Bezpečné screenshoty desktop/390px vizuálně zkontrolované.

## Produkční čtecí kontrola a meze

[Historie před](anonymous-history-before.json) i [po](anonymous-history-after.json):
ajax_treninky.php403, moje_treninky.php302/login, **2/2 shodně odmítnuté**.
Pouze GET bez cookies/POST/přesměrování, žádné rawHTML či session v Gitu.
**Přihlášené produkční UAT trenérské historie NOT RUN.**

Původní [katalog před](before.json)15:19UTC a [po](after.json)19:25UTC:
všech5HTTP200, ale **úplná DOM/veřejná parita NESHODA**. Přibyl veřejný produkt
#298, počet nabídek222→223 a odpovídajícím způsobem se posunuly stránky/řazení.
Původní neúspěšný výsledek zůstává zachovaný, není nahrazen pozdějším PASS.
[Čtecí rozbor rozsahu](public-scope-check.json): mezi skutečnou předchozí produkcí
a naším releasem se runtime mění jen v ajax_treninky.php, moje_treninky.php a
includes/coach_history_read.php; kód katalogu beze změny. Pozorovaná neshoda
odpovídá změně živého obsahu katalogu. Aktér ani samotný publikační zápis nebyli
tímto řezem ověřováni; žádný produkt/cena nebyly měněné ani vracené zpět.

Dva nové průchody **současného stavu po vydání**:
[před opakováním](recheck/before.json), [po opakování](recheck/after.json)
**5/5HTTP200, celý normalizovaný DOM i veřejná pole shodná**. Jde o ověření
stability současného stavu, nikoli důkaz původní před/po paritní kontroly vydání.

Původní checkout zůstává na codex/fio-auto-reconciliation-recovery;
WIP kis_rosters_admin.php SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5` shodný.
Ostatní worktrees, větve a staré nezařazené výstupy zachované.
Finální důkazy sdílí větev codex/coach-history-release-proof-20261108;
po dokumentačním merge není potřeba další deploy. Osobní profil, další P11,
fyzický mobil/souběh a lidské UAT jsou samostatné otevřené kroky.
