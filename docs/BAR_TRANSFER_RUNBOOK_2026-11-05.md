# Příprava převodu katalogu baru

Tento postup připravuje soukromý balíček a jeho izolovaný nácvik. **Nepovoluje produkční import ani nasazení.** Zdroj se pouze čte; cílový příkaz odmítá jiné databáze než explicitně pojmenované lokální testy. [Plán](BAR_INTEGRATION_PLAN_2026-11-05.md), [důkazy a omezení](verification/bar-transfer-20261105/RESULTS.md).

## Balíček

Zdrojové připojení se předá pouze proměnnými prostředí `BAR_SOURCE_DSN`, `BAR_SOURCE_USER`, `BAR_SOURCE_PASSWORD`. DSN má tvar `mysql:host=HOST;port=PORT;dbname=DATABAZE;charset=utf8mb4` (port lze vynechat). Použít čtecí účet, neukládat přihlašovací údaje do příkazů, Git ani výsledků kontrol. CLI nenačítá produkční konfiguraci KIS. Adresář fotografií musí odpovídat stejnému zdrojovému katalogu a po dobu balení zůstat stabilní.

```text
php bin/bar-transfer.php --export --images=ZDROJ_FOTOGRAFII --package=NOVY_SOUKROMY_ADRESAR
php bin/bar-transfer.php --inspect --package=SOUKROMY_ADRESAR
```

Rodič balíčku musí existovat, vlastní cílový adresář nesmí existovat a nesmí ležet v pracovní kopii Gitu ani webrootu. Na Windows zvolit adresář s omezenými přístupovými právy pro aktuálního uživatele; režim POSIX sám nezajišťuje Windows ACL. `bundle.json` obsahuje přihlašovací hash lucky, patří proto mezi citlivé soukromé podklady. Neukládat ho jako veřejný CI artefakt. Dokončený manifest se zapisuje až za fotografiemi. Kontrolní příkaz vypisuje pouze počty, nikoli heslo, hash nebo řádky zdroje.

Převod zachovává jediný schválený účet `lucka` typu `prodejce`, podporovaný hash a příznak schválení. Technický e-mail je pevně `info1@kovopraha.cz`. Jiné účty ani administrátorské pozice se nepřebírají. Nestandardní účet či hash musí být před případným skutečným převodem vyřešen samostatně; nástroj ho neobchází.

## Izolovaný nácvik

Připravit oddělenou prázdnou KIS databázi s aplikovanými migracemi a existujícím aktivním správcem systému. Produkční DB ani zdrojová DB nesmí být cílem. `BAR_TARGET_DSN` musí mít přesný tvar `mysql:host=127.0.0.1;port=PORT;dbname=evidence_bar_test_NAZEV;charset=utf8mb4` (přípustné také `evidence_bar_local_NAZEV`), port 1024–65535. Přihlašovací údaje předat v `BAR_TARGET_USER` a `BAR_TARGET_PASSWORD`. `APP_PRIVATE_STORAGE_ROOT` musí mířit na samostatné soukromé testovací úložiště.

```text
php bin/bar-transfer.php --apply-local --package=SOUKROMY_ADRESAR --actor=ID_SPRAVCE_SYSTEMU
```

Ověřit počty, vazby, přesné ceny, receptury a otisky fotografií; nulové zásoby, pouze jednu novou identitu s jedinou pozicí, žádné zákaznické propojení či e-mailové doručení. Přihlásit lucku i samostatného správce, ověřit omezení obsluhy a fotografii. Po testovací změně ceny a skladové operaci zopakovat stejný příkaz: musí vrátit `repeated: true` a změny zachovat. Kolize či jiná importní dávka se řeší v novém prázdném testovacím cíli, nikoli vymazáním existující evidence.

Pro automatický čistě syntetický nácvik slouží `tests/Support/BarTransferMariaDbSmoke.php` s `BAR_SMOKE_DB_PORT`; založí a odstraní pouze vlastní náhodně pojmenovanou testovací databázi. Browser fixture `tests/Support/BarBrowserFixture.php` vytvoří syntetickou plnou KIS DB a soukromé soubory, odmítne existující konfiguraci a je určená výhradně localhostu. Nové barové tabulky i technický příznak jsou součástí zálohovacího kontraktu KIS; fotografie musejí být zahrnuté do souborové zálohy privátního úložiště.

## Skutečný zdroj po nasazení modulu

Ruční workflow `Pripravit skutecny prevod BAR` (`bar-transfer-prepare.yml`, `main`, prostředí `production`, potvrzení `PRIPRAVIT`) používá ověřené SSH a společnou produkční frontu KIS. Pomocník načte zdrojovou neveřejnou konfiguraci pouze na hostingu, nepřenáší přístupové údaje na runner. Cílovou konfiguraci ověří proti `https://kis.kovopraha.cz` a lokálnímu příznaku false; nenahrává ani neaktivuje aplikaci a neprovádí žádné databázové zápisy.

Balíček zůstává v soukromém `data/.kis-backups/bar-transfer-ID_BEHU`, se soukromým archivem vedle. Obsahuje jen katalog, nutné fotografie a hash lucky. Export ověřuje transakční tabulky, konzistentní čtecí snímek a shodu nového zdrojového snímku po kopírování. Existující dokončený balíček se přijme pouze při úplné shodě; jiný obsah se nepřepisuje. Chybějící fotografie, neplatný účet nebo změna zdroje zastaví přípravu.

Runner ověří otisk archivu a bezpečně rozbalí jen manifest a běžné fotografie. `bar-transfer-rehearsal.php` přijímá výhradně prázdnou izolovanou lokální databázi, vytvoří syntetické schéma KIS s migracemi a provede skutečný import balíčku. `barVerifyInitialTransfer()` porovná katalog, receptury, obsah a metadata souborů, technický účet a jeho jedinou roli, nulové zásoby a prázdnou provozní historii. Druhý import musí být bez změn; další opakování zachová testovací změnu ceny a zásob. Po běhu se soukromé kopie runneru odstraní. Artefakt obsahuje výhradně `prepare.json` a `rehearsal.json`; balíček ani SQL se do něj nepřidávají.

Příprava neprovádí produkční import ani přesměrování domény. Před importem použít konkrétní ověřený balíček a čerstvou cílovou zálohu; neoslabovat ochranu `--apply-local`. Současné přepínače plateb, členství a pošty se tímto postupem nemění.
