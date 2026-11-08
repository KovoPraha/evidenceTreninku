# Předání P11-RHC — Moje tréninky

8. 11. 2026: HOTOVO v technickém rozsahu / NASAZENO.
[Plán](COACH_HISTORY_PAGE_PERFORMANCE_2026-11-08.md),
[parita a měření](verification/coach-history-page-performance-20261108/RESULTS.md),
[produkční protokol a meze](verification/coach-history-release-20261108/RESULTS.md).

#400 sloučené jako da152282ad4274690595c188a78dec0c90422f38 po14CI SUCCESS na
b4022c2a3b306d98ff0afdbba64f5ec342bb1e66;2400testů/54217assertions PASS.
Celý tested/merged tree i čtyři měřené sourcehashy přesně shodné.
Deploy42494231010 SUCCESS15:25:47UTC, import/UAT false. Záloha389tabulek/2triggery,
migrace current/pending[], VS shodné, aktivace/smoke PASS.
Převzaté #396/#395, Q2 cílené přihlášené UAT PASS a jeho meze zachované.

Trenérská AJAX historie vybírá tenké kandidáty a historický filtr před stránkou25;
plné detaily a pět vazeb jen stránky, úplný počet a celá historie zachované.
Filtry/násobnost JOIN/CSRF/writery stejné; nové filtry začínají na1.
Parita obě MariaDB2/819 PASS včetně duplicity přes hranici25/26.
476časovanýchHTTP,4zamítnutéGET+2revokace,78Chrome, další/předchozí/filtry/
hledání/accordion/otevření-zrušení poznámky a390px stránkování PASS.
10k čtení1056→43ms /565→24ms,26→8SQL,220000→10550řádků.
Celý AJAX1001tréninků148→10ms,12,16MB→308kB;
Chrome warm fetch→DOM+2frames4636→44ms, malý default43,00→43,85ms neutrální.

Produkční anonym AJAX403 /Moje tréninky302login stejné před/po.
Původní veřejný katalog není shodný: mezi15:19 a19:25UTC produkt298 a222→223
nabídek. Kód katalogu beze změny; žádná naše mutace produktu ani rollback.
Nový dvojí průchod současného stavu5/5DOM shodný; nenahrazuje původní neshodu.
Přihlášené produkční UAT trenérské historie NOT RUN.

Vlastní původní větev codex/coach-history-page-performance-20261108;
worktree C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107.
Finální důkazy předává větev codex/coach-history-release-proof-20261108 do main;
po dokumentačním merge další deploy neopakovat. Osobní profil, další P11,
fyzický mobil/souběh a lidské UAT zůstávají samostatné.
Původní checkout codex/fio-auto-reconciliation-recovery a WIP neměnit;
kis_rosters_admin.php SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5` shodný.
