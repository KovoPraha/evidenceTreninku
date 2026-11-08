# Předání P11-R13-F — technicky hotovo a nasazeno

8. 11. 2026. [Plán](MEDICAL_FILTER_READ_PERFORMANCE_2026-11-07.md),
[parita a výkon](verification/medical-filter-read-performance-20261107/RESULTS.md),
[produkční důkazy](verification/medical-filter-release-20261108/RESULTS.md).

PR #385 sloučený jako `519ff098961943f1aa58a70e40fed3ffba37cc96` po všech15CI PASS
na2362f3a4. Celý strom a měřené zdroje shodné; převzaté #387/#388/#389 zachované.
Deploy42477866185 SUCCESS, index ověřený na skutečné MariaDB10.3.39 před aktivací,
záloha389tabulek/2triggery ověřená, VS zachované, import/UAT false.
Veřejný DOM5/5 před/po shodný; zdravotní filtry anonymně403 před i po.

V1–V4 SQL-only varianty zamítnuté pro datumové regrese; historie a profily v protokolu.
V5 měří odděleně původní stav, samotný index a kód+index na identických datech.
Nativně12testů/495assertions na každé DB,64případů/17trojic; SQLite i plná sada2389/48273 PASS.
Celé HTTP2040časovaných vzorků,48zápornýchGET,364Chrome pozorování, přesné HTML/checksumy/úklid PASS.
100k finální GET: hledání1213,91→586,46ms, datum od919,93→518,62ms, do914,99→344,27ms.
Jde o syntetická data; malé sady mají režii sondy, kontrolní cesty malou varianci.

Kanonické stavy, práva, writer, soukromé dokumenty, přímá osoba/transakce zachované.
Index neunikátní, online CLI migrace, žádné klinické DML nebo DDL ve webovém requestu.
Přihlášené produkční zdravotní UAT NOT RUN; role/picker a lidské přejímky jsou samostatné.

Původní checkout `C:/xampp/htdocs/evidencePavel` zůstal na vlastní WIP větvi;
`kis_rosters_admin.php` SHA256 `7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5` shodný.
Worktree `C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107`.
Finální důkazy: větev `codex/medical-filter-release-proof-20261108`, dokumentační PR;
po merge protokolu není potřeba další deploy. Staré výstupy/worktrees nemažou se.
Další krok: případné přihlášené čtecí UAT existujícím oprávněným účtem, bez změn rolí.
