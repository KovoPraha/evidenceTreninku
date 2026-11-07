# Opakování celého HTTP/browser profilu

Pouze v novém izolovaném checkoutu, bez existujícího `config.php`, na novém
vlastním localhost MariaDB datadiru a volných portech. Nepoužívat produkční
konfiguraci/dump ani starší runtime adresáře. Nástroje odmítnou neshodnou DB
identitu, config hash, schéma nebo host. Výchozí data jsou relativní ke dni
běhu; pevná místní čísla ani datum původního běhu se neopakují automaticky.

1. Composer dle locku. Nový datadir inicializovat vlastním `mysql_install_db`,
   server spustit pouze na 127.0.0.1 ve vlastním procesu, nikoli jako službu.
   Uložit ignorovaný `output/full-http-performance-20261107/runtime.json`:
   `runtime`, `datadir`, `port`, `http_port`, `synthetic_only=true`. Vytvořit
   pod runtime `sessions` a `metrics`. Nepřepisovat existující adresáře/config.
2. Explicitní env `EVIDENCE_PERFORMANCE_CONFIRM=SYNTHETIC-ONLY`,
   `EVIDENCE_MARIADB_SMOKE_PORT` a `EVIDENCE_PERFORMANCE_DATADIR`.
   `php -d memory_limit=512M tests/Support/FullHttpReadFixture.php --setup OUTPUT`.
   Ověří port/datadir, migruje celé schéma, připraví data/role, vygeneruje pouze
   místní config a ignorované credentials. Doménové služby pro seed běží v CLI.
3. Prostý PHP server na daném portu/rootu, `session.save_path=RUNTIME/sessions`,
   `date.timezone=Europe/Prague`, log/display nastavení jako v protokolu.
   Bez prepend měřiče a bez DB general logu.
4. `php tests/Support/FullHttpReadProfile.php --login OUTPUT`, poté `--probe`,
   `--measure`; stejný OUTPUT za přepínačem. Měří 31 pozitivních případů a
   ověří 34 negativních. `--resume` jen při přesně stejných datech/případech.
   `php tests/Support/FullHttpReadVerify.php OUTPUT` ověří skutečné DOM.
5. Zastavit pouze vlastní prostý HTTP proces. Spustit stejný root/port
   s `-d auto_prepend_file=ABS/tests/Support/FullHttpReadInstrument.php` a env
   `P11_HTTP_METRICS=RUNTIME/metrics`, SYNTHETIC-ONLY. Spustit
   `php tests/Support/FullHttpReadDiagnostics.php OUTPUT`. General log se
   zapíná jen na vlastním serveru a po každém požadavku/finally vypíná.
   Diagnostika se musí přísně shodovat s prostým HTML, zapsat 0 DML/DDL a
   stejné součty. Počty fetched rows a SQL duration neimplikovat z EXPLAIN.
6. Zastavit vlastní diagnostický HTTP proces a vrátit prostý server bez prepend.
   Použít Playwright CLI skill/novou vlastní session. Standardním formulářem
   přihlásit coach, sports_lead, registrar, other_coach, vždy nově snapshot/ref.
   `FullHttpBrowserProfile.js` je funkce pro `playwright-cli run-code --filename`.
   V ignorované kopii nahradit pouze `JSON.parse('__P11_CONFIG__')` objektem
   `{role,base_url,cases}` z `matrix.json`, jen pozitivní případy dané role.
   Funkce měří 3 cold + 10 warm pozorování po prime; neměnit parametry aplikace.
   Vrácený JSON z `### Result` uložit do OUTPUT jako `browser-ROLE.json`.
   Použít samostatné soubory pro shell/JS quoting a nearchivovat login hesla.
7. Po vedoucím lze samostatně spustit `FullHttpBrowserCpuTrace.js`, po druhém
   trenérovi `FullHttpBrowserObserverCheck.js`. Uložit JSON jako
   `browser-cpu-diagnostic.json` / `browser-observer-check.json`.
   Neplést instrumentovanou stopu s normálními browser časy; vnořené eventy
   nejsou aditivní. Screenshoty pouze syntetických stránek v `output/playwright/`.
8. Browser zavřít. `php tests/Support/FullHttpReadRevocation.php OUTPUT`
   ověří čerstvé odebrání role a neaktivní účet pouze na syntetickém trenérovi 8
   a v finally obnoví celé původní řádky. Ověřit doménové checksums také po browseru.
9. `php tests/Support/FullHttpReadCleanup.php OUTPUT` smaže jen vlastní
   náhodné schéma/DB účet, ověřený vytvořený config a místní credentials/cookies.
   Samostatně zastavit přesně vlastní HTTP/MariaDB procesy, ověřit porty a doplnit
   cleanup.json. Runtime adresář ponechat zastavený, žádné plošné mazání.
10. `FullHttpReadReport.py INPUT PROOF --playwright-cli-version SKUTECNA_VERZE`
    archivuje pouze bezpečné JSON/gzip/snímky a vytvoří souhrn/CSV/SHA manifest.
    Potřebuje `final-core-check.json` a dokončený cleanup. Původní browser
    zdroje jsou v ignorovaném `output/playwright/full-http-performance-20261107/`.
    CLI verzi zjistit `playwright-cli --version`; zapsat skutečné okno/DPR do
    runtime, pokud starší měřicí funkce měla viewport=null. Zdroje se musí shodovat
    s application_sha zachyceným při seedu. Sekrety ani HTML do PROOF nekopírovat.

Vyžaduje běžné PHP rozšíření PDO MySQL/CURL/DOM, Python stdlib, Node/npx a Chrome.
Plné lokální měření není zatím nová automatická Linux HTTP/browser matice.
Stávající CI MariaDB 10.3/11.4 pro dílčí profily zůstává beze změny.
