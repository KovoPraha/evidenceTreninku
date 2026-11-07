# Předání P11-R05-H — plánovač

7. 11. 2026: K OVĚŘENÍ výsledkové hlavy, [PR #374](https://github.com/KovoPraha/evidenceTreninku/pull/374), vlastní větev.
[Plán](PLANNER_HIERARCHY_READ_PERFORMANCE_2026-11-07.md) je autoritativní rozsah a akceptace.
Base `69f49033`, větev `codex/planner-hierarchy-read-performance-20261107`, samostatný worktree stejného tématu.
Aktuální zadání dovoluje commit/merge/deploy až po shodě výsledků, přínosu a zelených kontrolách.
Původní WIP `kis_rosters_admin.php` SHA256 `7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5` zachovaný.
Produkce zatím beze změny; merge/deploy NOT RUN. Runtime `65440e3b` zmrazený.
SQLite nový kontrakt 8/1170, Linux celá sada 2370/47564, PHPStan/styl/docs PASS.
Celé HTTP/Chrome 42390969061 PASS: 1360 uložených HTTP pozorování, 104 Chrome,
celé HTML shodné, 13 checksumů a žádné DML/DDL v oddělené diagnostice, úklid PASS.
Velká historie: trenér HTTP 70,59 →47,36 ms, vedoucí116,25 →46,77 ms.
Finální nativní pár obou verzí PASS v 42390969094; 8/1170 na každé DB.
Helper36k: 10.3 medián127,23 →2,07 ms, 11.4 medián151,47 →45,56 ms; řádky72946 →167.
[Průběžné důkazy](verification/planner-hierarchy-read-performance-20261107/RESULTS.md).
Další krok: commit výsledků a zelené kontroly nové výsledkové hlavy, merge/deploy,
veřejný čtecí před/po smoke a výsledkové předání. Přihlášené produkční UAT NOT RUN.

Převzato nové main `b3d0f4bb` / #377: pouze dokumentační blokované UAT pickeru, jeho fakta a metadata zachované; žádná změna měřeného runtime. Výsledková hlava 0ac7680c měla všech12 CI PASS; integrovaná hlava se musí znovu ověřit. Veřejný baseline pěti GET a odmítnutí anonymního plánovače PASS.
