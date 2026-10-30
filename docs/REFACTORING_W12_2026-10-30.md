# W12 — importy, exportní snapshot a izolovaná obnova

Implementace a místní ověření dokončeny, CI/sloučení/nasazení se ověřují
samostatně. [Výsledky a limity](verification/refactoring-w12-20261030/RESULTS.md).
Navazuje na W11. Syntetická localhost data, žádný import skutečných osob.
Nejprve měřit parser a preview na 100/1000/10 000 řádcích; zachovat celé
výsledky, metadata, fingerprint, mapy a karanténu. Rozlišit načtení XLSX,
klasifikaci a DB uložení. Kandidát změny musí mít prokázaný přínos.
Současný `kis_read_rows` používá celý workbook; prověřit zejména uvolnění
jeho zdrojů v úspěšném i chybovém průchodu před změnou strategie čtení.

Zpřenosnit `AccountingExportSnapshotMariaDbTest.php`: vlastní náhodná DB,
dva skutečné commity, negativní READ COMMITTED kontrola, následné uklizení.
Ověřit skutečný exportní ZIP včetně PDF a XLSX na vymezeném objemu;
100 000 řádků čistého XLSX z W10 není důkaz stejně velkého PDF/archivu.

Nový profil zálohy použije celý migrovaný katalog a naplněné syntetické
ledger/historii. Spustí skutečné CLI zálohy, ověří manifest a checksum,
obnoví jen do nově vytvořené náhodné DB, porovná obsah, FK a triggery.
Privátní soubory zkopíruje do odděleného kořene a ověří každý referencovaný
hash. Měřit zálohu, kompresi/obnovu a velikost; cleanup jen vlastních zdrojů.
Historické B1 skripty s pevnými cestami nespouštět nad původním prostředím.

Preview rollback a opakované potvrzení musí pokrýt existující behaviorální
testy i MariaDB; aktuální produkční data a integrace zůstávají mimo test.
Povinné CI, dokumentace a standardní technické nasazení. Nezávislé ověření
W13–W14 probíhá souběžně; release lze spojit po úspěšných kontrolách všech změn.

Měření doplnilo jediný index stagingové tabulky `(run_id,row_id)`: běžící
EXPLAIN prokázal opakované skenování shod celého importu. Nejde o změnu
matching pravidel, kanonických dat ani aktivaci importu. Parser má explicitní
uvolnění workbooku, exportní snapshot a úplná obnova jsou přenosné v CI.
