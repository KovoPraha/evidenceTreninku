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

## Původní odkazy na fotografie

Skutečné první čtení zastavilo odlišné odkazy na fotografie; nesmějí se prostě vynechat. Export proto vytvoří soukromý snímek obrázků. Přijímá běžný místní název včetně diakritiky/mezer, pevné prefixy `img/`, `./img/`, `/img/`, `../img/` a přesnou starou doménu s cestou do `img`. Cizí domény, přihlašovací údaje v URL, query/fragment, obecné cesty, symlinky a únik z adresáře odmítne. Neprovádí žádné síťové stahování obrázků.

Každý obrázek se dekóduje, zkontroluje a zkopíruje beze změny bajtů pod bezpečným názvem podle obsahu. Stejný obsah lze sdílet více produkty; každá původní reference a vazba na produkt zůstane v soukromém `source-image-map.json` vedle balíčku. Souhrn obsahuje jen otisk mapy. Druhé čtení kontroluje jak balíček, tak původní reference. Cílové soukromé úložiště a existující validátor balíčku zůstávají přísné. Chybějící obrázek zastaví převod s bezpečným popisem konkrétního produktu a místního názvu.

## Řízené produkční provedení

Workflow `Provest overeny prevod BAR` (`bar-cutover-production.yml`) běží výhradně z `main` v chráněném prostředí a společné produkční frontě. Vstupní JSON `plan` obsahuje `prepare_run`, `bundle_sha256`, přesný živý `release_sha` a jméno jediného vlastníkem schváleného existujícího správce v `administrator_name`. Bez hesel nebo jiných polí. Jméno musí jednoznačně označovat aktivní účet s aktuálním systémovým oprávněním; neodhadovat podle podobnosti. Souhrny evidují pouze jeho ID.

1. `inspect` + `OVERIT`: ověří úspěšný chráněný přípravný běh a jeho artefakt včetně skutečného nácviku konkrétního otisku. Zkontroluje živé vydání, aktuální zdroj a prázdný cíl, kolize a schválený účet. Databázové zápisy neprovádí.
2. Spustit `production-drills.yml` s operací `vytvorit-uplnou-zalohu-a-obnovit`, vyčkat úspěšného ověření všech existujících souborů i úplné obnovy DB. Přesné názvy a otisky doplnit do `plan.backup`: `restore_run`, `sql_file`, `sql_sha256`, `files_archive`, `files_sha256`. Archive musí patřit právě tomuto běhu. Před prvními změnami nesmí být starší než hodinu.
3. `apply` + `PREVEST_BAR`: API GitHubu ověří skutečný úspěch přípravy i obou kroků úplné obnovy z hlavní větve. Na hostingu se znovu ověří otisky záloh, jejich manifest a prázdný katalog v bodě obnovy. Poté se atomicky převede katalog/lucka, porovnají se všechna data a soubory a samostatně auditovaně přidá pouze schválená správa a vlastní spotřeba. Ostatní pozice a výchozí nastavení zůstávají zachované. Jiná existující barová práva se nikdy nemažou; nečekaná práva mimo určený účet operaci zastaví. Aktuální zdroj se porovná i po commitu.
4. `status` + `OVERIT`: samostatné čtení cíle po převodu nebo ztracené SSH odpovědi. Ověří přesný počáteční katalog, fotografie, lucku, nulové zásoby/historii a jediného správce/spotřebu. Pozdější skutečný provoz může tento počáteční snímek oprávněně změnit; neřešit to opětovným importem nebo obnovou zálohy.

Zápisový PHP příkaz se automaticky neopakuje. Výsledek ukládá mimo webroot do soukromého adresáře konkrétní operace ještě před odpovědí; při nejisté odpovědi nejdřív zjišťovat skutečný commit přes `status`. Výsledek odlišuje commit katalogu a commit oprávnění. Úmyslné opakování nevytváří další účet, grant ani katalog a nepřepíše původní události. Záloha chrání existující stav; nové fotografie mají neměnná obsahová jména a nepřepisují staré soubory. Produkční balíček ani SQL se nepřidávají do artefaktů, pouze necitlivý výsledek kontrol.

Nativní `BarCutoverMariaDbSmoke.php` ověřuje celý řetězec se syntetickým zdrojem, sdílenou lokální DB a skutečnou fotografií s diakritikou: přípravu, čtecí kontrolu, aplikaci, status a opakování. Jeho syntetický doklad zálohy nenahrazuje povinný skutečný chráněný obnovovací běh, který ověřuje workflow přes API.

## Jediný účet a případné nové heslo

Třetí skutečné čtení `42116008407` již prošlo katalogem i fotografiemi; zastavila ho původní příliš úzká podmínka účtu. Adaptér ověří jedinou nalezenou identitu `lucka` bez ohledu na velikost písmen, ale nepřijme jiné jméno, neznámou roli nebo neschválený účet. Zdrojový schválený admin/prodejce přejde vždy jen na obsluhu baru podle pokynu vlastníka. Původní administrátorská oprávnění se nekopírují. Podporovaný hash zůstane beze změny; zdrojový účet, heslo i schválení se nikdy nepřepisují.

Pouze při nepodporovaném zdrojovém hesle lze použít již vlastníkem povolenou změnu: `nove_heslo=true` a `verejny_klic` v přípravném workflow. Příjemce si místně vytvoří RSA-4096 pár a soukromý klíč uchová v chráněném adresáři mimo Git; vstup obsahuje jen base64 veřejného SPKI klíče. Podporované původní heslo se ani v tomto režimu zbytečně nemění. Nepodporované heslo bez výslovného režimu přípravu zastaví s diagnostickými booleany, nikoli s hashem nebo heslem.

Nové náhodné heslo vznikne právě jednou na hostingu, mimo webroot, pouze pro lucku. Soukromý stav dávky uchová jeho hash a otisk původního zdrojového údaje; opakované kontroly nevyrobí jiné heslo a změnu původního údaje odmítnou. Dočasný předávací soubor putuje přes ověřené SSH do soukromého runneru, kde ho Node zašifruje RSA-OAEP-SHA256. Artefakt obsahuje pouze `lucka-credential.enc`, veřejný otisk klíče a souhrn kontrol. Dočasný otevřený text se odstraní na runneru i hostingu také při selhání; nikdy se nevypisuje. Příjemce ověří otisk obálky/klíče a rozšifruje ji vlastním klíčem do místního soukromého souboru. Teprve poté lze považovat nové heslo za připravené k předání. Neposílat ho do chatu, logů, Gitu ani e-mailem na technickou adresu.

`lucka.source_password_preserved` odlišuje původní a nové heslo. `verification.credential_hash_matches_package` dokládá přesné převzetí hashe z připraveného balíčku; neznamená automaticky zachování původního zdrojového hesla. Po neúspěšné přípravě s odstraněným předávacím souborem založit nový přípravný běh, nikoli regenerovat tajemství v původní dávce. Úspěšná příprava a její šifrované předání jsou podmínkou produkčního importu. Nativní test ověřuje jak původní schválenou administrátorskou identitu s velikostí písmen, tak nepodporovaný formát a jednorázovou náhradu bez změny zdroje.

## Omezení hostingu a velké fotografie

Čtvrtý běh `42119972642` skutečně vytvořil stabilní snímek: 68 produktů, 5 kategorií, 0 receptur a 51 jedinečných fotografií / 107625689 B. Jediná schválená lucka má podporované původní heslo, které zůstane zachované; rozdíl byl ve velikosti písmen jména. Cíl je prázdný, bez kolizí a s `utc-v1`. První pokus zastavil nečistý technický výstup; druhý už získal souhrn a archiv, ale hosting nemá příkaz `sha256sum`. Nejde o důkaz dokončeného nácviku nebo produkčního importu.

Kontrolní součet soukromého archivu proto počítá PHP a po stažení ho nezávisle porovnává Python. Průběžné fáze a počty jdou odděleně do stderr, neočekávaný výstup nesmí poškodit JSON ani odhalit obsah chyby. Vlastní čtecí PDO mají patnáctisekundový limit připojení a třicetisekundový limit SQL/čekání na metadata; neovlivňují globální nastavení nebo časovou zónu KIS.

Hostovací zapisovač přijímá pouze nový adresář dávky v soukromém `data/.kis-backups`, kontroluje Git v dostupném rozsahu `open_basedir` a nevstupuje nad povolenou hranici. Místní CLI si zachovává původní přísné kontroly Gitu a lokální DB. Zapisují se pouze předem ověřené bajty pod neměnnými názvy, manifest dokončení až poslední. Testy ověřují skutečně omezený proces PHP i odmítnutí Git předka.

Na hostingu se každá nová fotografie dekóduje jednou. Další čtení ověří obsahový SHA-256 a velikost; změněný obsah musí projít novým dekódováním. Volitelné `image_cache_run` + `image_cache_sha256` umožní použít metadata již vytvořeného soukromého snímku s přesným otiskem. Aktuální zdrojové řádky, odkazy a všechny obrazové bajty se čtou znovu; nejde o převzetí starého katalogu. Nová dávka má vlastní manifest a skutečný runner následně všechny fotografie znovu nezávisle dekóduje při validaci a nácviku. Před produkčním zápisem je nutný úspěch právě této nové přípravy. Při kontrole stejné existující dávky se ověřují shodná zdrojová data i každý uložený soubor; pozdější změna zdroje nikdy nepřepíše hotový balíček.

## Následný provoz a zveřejnění nabídky

Po dokončení převodu následovala [technická provozní přejímka](verification/bar-acceptance-20261106/RESULTS.md)
a [zveřejnění nabídky 1 / vydání 1](verification/bar-menu-publication-20261106/RESULTS.md), 45 produktů s fotografiemi.
Jde o legitimní nová data KIS; počáteční nulový status se nesmí vynucovat mazáním nabídky nebo nové historie.
Převod se znovu nespouští. Zbývající postup je v [session handoffu](SESSION_HANDOFF_BAR_2026-11-06.md).

## Dokončený skutečný převod

6. 11. 2026: nová příprava/nácvik `42127720339`, čtecí kontrola `42127994924`, jednorázový import `42128255522` a následný samostatný status `42128603361` SUCCESS. [Protokol a necitlivé doklady](verification/bar-cutover-20261106/RESULTS.md): přesně 68 produktů / 5 kategorií / 51 fotografií, podporované původní heslo Lucky, pouze obsluha; vlastník dostal schválenou správu a vlastní spotřebu. Před zápisem úplná skutečná obnova `42126301391` PASS. Import znovu nespouštět jako běžný provozní postup; následné skutečné změny cíle se zachovávají.
