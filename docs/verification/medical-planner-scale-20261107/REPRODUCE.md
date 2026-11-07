# Opakování P11-SCALE

Pouze vlastní syntetický localhost. Zmrazená aplikace `69f49033`; pro přesné
opakování primární fáze použít její čtyři LF zdrojové snapshoty `.txt` v tomto
adresáři (zkopírovat bez koncovky `.txt`). Pozdější R05 seeder v PR #375 je dávkový
a patří doplnění aktuálního plánovače. Pro jiný den/adresář/port explicitně upravit úzké guardy.
Nespouštět na produkční konfiguraci ani připojení. Nepoužít produkční dump.

1. Vlastní čistý worktree `C:/Users/Marek/Documents/Codex/evidencePavel-medical-planner-scale-20261107`,
   `composer install` přes stejný lock; žádný existující config.php. Zachovat původní WIP.
2. Nový náhodný `C:/xampp/tmp/kis-medical-planner-scale-20261107-RANDOM8HEX`,
   podadresáře data/sessions/metrics. Předem ověřit volné porty 33479/18115.
   `mysql_install_db.exe` předat tři oddělené argumenty
   `--datadir=OWN/data`, `--port=33479`, `--silent`.
   Vlastní my.ini: datadir OWN/data, bind-address127.0.0.1, port33479,
   innodb_buffer_pool_size128M, max_allowed_packet64M, skip-name-resolve,
   tmpdir OWN/mysql-tmp. Vlastní mysql-tmp vytvořit před spuštěním serveru.
3. Do ignorovaného OUTPUT/runtime.json zapsat runtime/datadir/port33479/http_port18115
   a synthetic_only:true. `MedicalPlannerScaleRuntime.ps1 -Action StartDatabase -Output OUTPUT`.
   Env EVIDENCE_PERFORMANCE_CONFIRM=SYNTHETIC-ONLY, EVIDENCE_MARIADB_SMOKE_PORT=33479,
   EVIDENCE_PERFORMANCE_DATADIR=OWN/data; potom
   `php -d memory_limit=512M tests/Support/FullHttpReadFixture.php --setup OUTPUT`.
   Jde o minimální legacy fixture a celý katalog migrací, nikoli kopii produkční DB.
4. Před časováním uložit environment.json se skutečným runtime, verzemi a LF SHA256
   16 aplikačních souborů uvedených v [archivovaném prostředí](environment.json).
   Každý musí být shodný s git blobem 69f49033. Zachytit SHOW CREATE TABLE a SHOW INDEX
   devíti tabulek uvedených v [metadatech](schema-metadata.json); export pouze z ověřeného portu/datadir.
   Výsledky mysql --xml lze převést na objekt se jmény polí. Žádné datové řádky do metadat nepatří.
5. `tests/Support/MedicalPlannerScaleRun.ps1 -Output OUTPUT` postupně založí sedm
   pevných sad, ověří jejich přesné počty a spustí web na stejném portu/session cestě.
   Runtime nastaví session.gc_maxlifetime14400; původní fázový rozdíl viz kalibrace.
   První sada provede všech 12 běžných formulářových loginů, potom REVOKE DML web účtu.
   Ostatní sady stejné session používají dál. Žádné forged session ani role-cache.
6. Každá sada: úplná doménová/DOM shoda, READ ONLY projekce, prosté HTTP
   5 zahřátí + 30 měření každého kladného případu a čtyři záporné přístupy.
   Potom zastavit prostý web a spustit oddělený prepend diagnostický server.
   General log a slow log jen na vlastním DB serveru, long_query_time0,
   min_examined_row_limit0, výstup TABLE. Každý případ má samostatně vyprázdněné logy;
   filtrovat pouze vlastní web DB účet. Zachovat celé SQL, rows_examined/sent a PHP peak.
   Slow log může navíc evidovat Quit s nulovým počtem řádků.
   Všechny změněné logovací globální hodnoty se po diagnostice vracejí.
7. Každá sada uloží seed/http/diagnostics/verification/projections.json.
   Opakování nesmí tiše přepsat existující časování. Každý úplný časový soubor
   obsahuje všech 30 vzorků, bez odstraňování odlehlých hodnot. Žádné jiné místní
   benchmarky během časování; síť/CPU/OS cold režim touto studií není řízený.
8. Po všech sadách ověřit, že whitelist 35 reportů, prostředí, metadata,
   loginy bez hesel a čtyři bezpečné zdrojové snapshoty neobsahují žádná skutečná
   testovací hesla, DB heslo/pepper ani cookie hodnoty. Uložit secret-scan.json
   pouze s počtem kontrol a případnými názvy souborů; nikdy se secret hodnotou.
9. Web musí být zastavený. `php tests/Support/FullHttpReadCleanup.php OUTPUT`
   odstraní vlastní schéma, účet, přesný hashově ověřený config a credentials/cookies.
   `MedicalPlannerScaleRuntime.ps1 -Action StopDatabase -Output OUTPUT` ověřuje PID a my.ini
   před shutdownem. Nezávisle ověřit odmítnuté TCP spojení na obou portech a původní WIP SHA256.
   Přidat do cleanup.json database_stopped/http_stopped/ports_closed/original_wip_unchanged:true
   až podle skutečnosti. Zastavený runtime se rekurzivně nemaže.
10. `python tests/Support/MedicalPlannerScaleReport.py OUTPUT docs/verification/medical-planner-scale-20261107`
    vyžaduje kompletní sady, 21 shodných součtů, paritu, úklid a čistý secret scan.
    Vytvoří bezpečné gzip JSON, souhrn, výsledky a SHA256 manifest.
    Ověřit manifest znovu po stagingu, PHP syntax/styl, oba PS parsery, Python compile,
    dokumentační kontrolu, diff a PR CI. Nasazení není součástí této práce.

Nepřenášet absolutní časy mezi stroji ani starými studiemi. MariaDB 10.3/11.4,
prohlížeč, fyzický mobil, řízená síť/CPU a souběh jsou další samostatné kroky.
