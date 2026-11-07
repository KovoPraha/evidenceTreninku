# Opakování celého páru docházky

Pouze vlastní syntetický localhost, nový datadir a neobsazené porty. Výchozí
kód d958fd6e proti faae75e8, oddělené čisté checkouty. Nevytvářet produkční dump.
Nástroje odmítají jiný port/datadir/config; taskové Windows pomůcky mají úzké
cesty/porty a datum studie, pro jinou studii je upravit explicitně před začátkem.

1. Composer install dle locku v obou checkoutech. Nový runtime pod
   C:/xampp/tmp/kis-attendance-http-20261107-RANDOM8HEX; sessions a metrics.
   mysql_install_db.exe s oddělenými argumenty --datadir=OWN/data --port=33469
   --silent. Vlastní my.ini: datadir OWN/data, bind127.0.0.1, port33469.
   Ignorovaný OUTPUT/runtime.json: runtime/datadir/port33469/http_port18105,
   synthetic_onlytrue. StartDatabase pomocí AttendanceHttpRuntime.ps1.
2. V baseline d958fd6e env EVIDENCE_PERFORMANCE_CONFIRM=SYNTHETIC-ONLY,
   EVIDENCE_MARIADB_SMOKE_PORT=33469, EVIDENCE_PERFORMANCE_DATADIR=OWN/data;
   php -d memory_limit=512M tests/Support/FullHttpReadFixture.php --setup ABS_OUTPUT.
   Vygenerovaný config zkopírovat do after až po kontrole shodného SHA256;
   nesmí existovat cizí config. Seed neopakovat mezi variantami.
3. AttendanceHttpRuntime.ps1 -Action StartWeb -Root BASELINE -Phase before;
   FullHttpReadProfile.php --login OUTPUT, AttendanceHttpCoreCheck.php OUTPUT pre-before.
   AttendancePickerHttpProfile.php OUTPUT before, FullHttpReadVerify.php OUTPUT;
   DOM report uložit jako dom-before.json. Standardní login, žádné forged session.
4. Přečíst čerstvý browser login snapshot, ověřit lokátory. AttendanceHttpBrowser.ps1
   -Role coach/sports_lead/other_coach -Variant before -Output OUTPUT, role po sobě.
   Script dělá každý případ samostatným run-code, všech 13 pozorování musí být
   kompletních; ukládá bezpečné per-case a aggregate JSON. Žádné měření souběžně.
5. StopWeb, StartWeb -Phase before-diag -Instrument na stejném rootu/portu.
   http-before.json zkopírovat do http-timings.json; FullHttpReadDiagnostics.php OUTPUT.
   Uložit diagnostics-before.json, CoreCheck OUTPUT post-before. StopWeb.
6. StartWeb na AFTER s Phase after bez prepend/general log. Profil OUTPUT after,
   DOM po jako dom-after.json. Stejná data a session cesta. Všechny role jako krok4
   s Variant after; žádný reseed. Aktuální app zdroje musí odpovídat faae75e8.
7. Zopakovat krok5 jako after-diag a diagnostics-after.json. Potom prostý server
   after-uat, FullHttpReadRevocation.php OUTPUT, CoreCheck OUTPUT post-after.
   Všech 20 checksumů před/po musí souhlasit a diagnostiky mít0 aplikačních DML/DDL.
8. Mimo časování bezpečné functional-template.js/excuse-template.js pro CLI run-code,
   credential marker nahrazovat jen v ignorované dočasné kopii. JS/noJS drafty,
   host/prázdný výběr/Enter/paging/390px; poté AttendancePickerHttpVerify.php OUTPUT.
   Teprve nyní skutečné syntetické zápisy. Browser omluva1001/paper a legacy1002/phone,
   revize53/201/paper ověřit v DB; uložit post-uat-state.json. Datum šablon pro jiný
   den zkontrolovat vůči seedu, žádné implicitní použití starých čísel.
9. Zavřít jen vlastní browsery; StopWeb ještě před odstraněním configů.
   FullHttpReadCleanup.php OUTPUT odstraní vlastní schéma/účet/afterconfig/cookies.
   Hashově ověřený baselineconfig odstranit samostatně; StopDatabase a nezávisle
   ověřit oba TCP sockety a původní WIP. Do cleanup.json zapsat ověřenou skutečnost.
10. Před odstraněním hesel ověřit, že žádná testhesla/DB klíče/cookies nejsou ve
    whitelistu evidence. Po úklidu AttendanceIntegratedHttpReport.py OUTPUT
    docs/verification/attendance-integrated-http-20261107 vytvoří bezpečný archiv,
    55 hashů, summary a zdrojové otisky. Vyžaduje všechny kompletní reporty a úklid.

Verzované pomůcky v tests/Support/; jejich kopie jsou i v tomto archivu.
Žádná produkční URL, deploy, aktivace, fyzický mobil ani řízená souběžná zátěž.
