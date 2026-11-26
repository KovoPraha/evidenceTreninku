# SERVER-STORAGE-01 — výsledky

[PR #544](https://github.com/KovoPraha/evidenceTreninku/pull/544) sloučen po zeleném CI; šestý [izolovaný import 45867275748](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45867275748) a opakování [45867441762](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45867441762) **FAIL** bez SQL chyby a bez výpisu počtů. Omezená větev `codex/hosting-restore-counts-20261126` po dokončení importu vypíše pouze počty tabulek a triggerů před původními prahovými kontrolami; tím určí, zda zastavení způsobila neúplná záloha. Produkce beze změny, mazání **NOT RUN**.

[PR #542](https://github.com/KovoPraha/evidenceTreninku/pull/542) sloučen po zeleném CI; pátý [izolovaný import 45865416694](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45865416694) **FAIL** bezpečně před importem. Diagnostika ukázala přesně známou veřejnou direktivu `/*M!999999\\- enable the sandbox mode */` s jednou koncovou ASCII mezerou. Následující větev `codex/hosting-restore-header-space-20261126` povolí pouze koncové vodorovné mezery této úvodní řádky, SQL data nemění. Obnova DB, soukromých souborů a mazání **NOT RUN**.

[PR #541](https://github.com/KovoPraha/evidenceTreninku/pull/541) sloučen po všech zelených kontrolách; čtvrtý [izolovaný import 45862562073](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45862562073) **FAIL**: přísná kontrola neočekávala skutečnou první řádku SQL exportu a zastavila proud před importem. `gzip -t` prošel, produkce zůstala beze změny. Větev `codex/hosting-restore-header-diagnostic-20261126` připravuje omezenou diagnostiku formátu hlavičky: vypíše pouze veřejnou sandbox direktivu, pokud ji rozpozná, jinak jen délku a SHA-256 první řádky; SQL obsah se do logu nevypisuje. Úplná obnova databáze, soukromých souborů a mazání **NOT RUN**.

## Třetí izolovaný import DB 26. 11. — FAIL na direktivě klienta

[PR #540](https://github.com/KovoPraha/evidenceTreninku/pull/540) sloučen jako `242f7098`; [běh 45849009795](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45849009795) s MariaDB 10.3 **FAIL** na prvním řádku: `Unknown command '\-'`. Moderní `mariadb-dump` vkládá direktivu sandbox režimu, kterou starší klient neumí načíst; [výrobce to dokumentuje](https://mariadb.com/docs/server/clients-and-utilities/backup-restore-and-import-clients/mariadb-dump). Nejde o poškození gzip ani o změnu produkce. Po opětovném přihlášení byla tatáž DB generace z 25. 11. 22:33 znovu připojena 26. 11. v **17:37:40** na hodinu. Větev `codex/hosting-restore-sandbox-header-20261126` připravuje kontrolované odstranění pouze přesně očekávané první direktivy v dočasném importním streamu; neznámou hlavičku odmítne. Lokální syntetická zkouška: známá direktiva odstraněna, neznámá hlavička odmítnuta **PASS**. Úplný DB import a mazání stále **NOT RUN**.

## Druhý izolovaný import DB 26. 11. — FAIL na nekompatibilitě enginu

[PR #539](https://github.com/KovoPraha/evidenceTreninku/pull/539) sloučen jako `726ce630`; [běh 45845940744](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45845940744) **FAIL** na `ERROR 1901` při `CREATE TABLE projects_notes`: MariaDB 11.4 odmítla historickou uloženou generovanou hodnotu založenou na cizím klíči s `ON DELETE SET NULL`. [Dokumentace MariaDB](https://mariadb.com/docs/release-notes/enterprise-server/old-releases/10.4/10.4.31-21) popisuje omezení novějších řad. Nejde o selhání čtení gzip ani o zápis do produkce. Větev `codex/hosting-restore-mariadb-compat-20261126` připravuje opakování v izolované MariaDB 10.3, kterou projekt používá v kompatibilitním CI; chybové SQL se nebude tisknout do logů. Úplná obnova **FAIL / čeká na opakování**, mazání **NOT RUN**.

## První izolovaný import DB 26. 11. — FAIL, oprava prostředí

[PR #538](https://github.com/KovoPraha/evidenceTreninku/pull/538) sloučen jako `851615a8` po zelených CI. [Běh 45843451119](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45843451119) **FAIL** při importu hostingového `kovoprahacz09.sql.gz` do dočasného kontejneru: na řádku 23 dump přepnul na `kovoprahacz09`, která v kontejneru zatím neexistovala (`ERROR 1049`). Přenos souboru a `gzip -t` prošly; produkční DB nebyla cílem importu a nedošlo k žádnému mazání. Větev `codex/hosting-backup-restore-schema-20261126` připravuje vytvoření stejnojmenného schématu výhradně v izolovaném kontejneru a opakování zkoušky. Obnovitelnost **NOT RUN / FAIL**, `apply` nadále blokováno.

## Databázová generace a příprava izolované obnovy 26. 11.

Klientská sekce připojila nejnovější dostupnou databázovou generaci **25. 11. 22:33:00** dne 26. 11. v 15:34:41 do čtecího `backups/`. Chráněný [běh 45840458944](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45840458944) z `main` **PASS**: 11 souborů `.sql.gz`, dohromady 11 364 KiB; KIS podle produkční dokumentace odpovídá `kovoprahacz09.sql.gz`. Generace je připojená nejvýše hodinu. Větev `codex/hosting-backup-restore-20261126` připravuje izolovaný import této SQL zálohy do dočasného kontejneru MariaDB a samostatné stažení soukromých souborů z WWW generace do dočasného runneru. Izolovaná obnova **NOT RUN**; produkční DB a soubory se nemění. Úklid **NOT RUN**.

Chráněný [běh WWW 45835629606](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45835629606) **PASS**: živý KIS `657 532 KiB / 4 592 souborů`, hostingový snapshot `657 528 KiB / 4 592`; `data/kis-private` v obou `105 940 KiB / 57`; `uploads` v obou `143 456 KiB / 386`; `nahrane_obrazky` v obou `405 172 KiB / 68`. V žádné z těchto zálohovaných složek nebyl soubor nad 1 GiB. [PR #537](https://github.com/KovoPraha/evidenceTreninku/pull/537) sloučen jako `9588e255`. Shodná velikost a počet nejsou úplným kryptografickým srovnáním; zkušební stažení a databázová obnova následují.

## Čtecí struktura hostingové WWW generace 26. 11.

[PR #536](https://github.com/KovoPraha/evidenceTreninku/pull/536) po zelených kontrolách sloučen jako `3f93f696`. Chráněný [běh 45832051776](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45832051776) z `main` **PASS**: v připojené generaci z 26. 11. 02:59 jsou adresáře `backups/kis.kovopraha.cz/` a `backups/data/kis-private/`, včetně KIS `uploads/`, `nahrane_obrazky/` a markerů `var/deployment.json`. Šlo o čtení struktury; soubory a DB ještě nebyly obnoveny. Obsahová kontrola WWW a připojení DB generace **NOT RUN**. Chráněné fáze `verify_www` a `inspect_db` se připravují na větvi `codex/hosting-backup-content-20261126`.

## Připojení WWW generace 26. 11. 2026

Přihlášená správa domény `kovopraha.cz` vypsala nejnovější WWW generaci **26. 11. 02:59:07**. Po volbě `Připojit zálohu` panel potvrdil, že je od **26. 11. 14:26:12** připojená do čtecího adresáře `backups/` na serveru a bude automaticky odpojena po hodině. Obsah souborů, úplnost soukromých příloh a obnova dat **NOT RUN**; následuje chráněná SSH kontrola připojené generace. DB generace dosud nepřipojena.

## Produkční čtecí plán 26. 11. 2026

[PR #534](https://github.com/KovoPraha/evidenceTreninku/pull/534) s odděleným chráněným workflow byl sloučen jako `b7dbcb2c` po všech zelených kontrolách. [Běh preview 45819148629](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45819148629) na stejném SHA **PASS** a neprovedl žádné mazání. [Přesný JSON plán](cleanup-plan-45819148629.json) obsahuje 279 starších release o součtu 14 484 304 KiB a 78 záložních položek o součtu 4 923 136 KiB. Celková možná úspora je 19 407 440 KiB / 18,51 GiB. Aktivní release `dabb1d7176b0d99590aadfa596d138990a237fac-45602767579` je mimo dávku; ponechány jsou také `data/.kis-backups/config`, `keys` a `private-storage`. Digest `37b589ae14a7a5a93f5e86f4707ddcc151ed00971754d06a8ba1c74b4f0d31f9` odpovídá nezávislému přepočtu kanonického JSON a součty odpovídají položkám. Toto je čtecí důkaz pro návrh, **nikoli skutečná úspora**. Hostingová WWW/DB obnova, `apply` a produkční smoke po smazání **NOT RUN**.

## Příprava chráněného úklidu 26. 11. 2026

Vlastník po inventuře výslovně požádal odstranit historická vydání KIS i vlastní aplikační zálohy. Připravená implementace samostatného workflow `server-storage-cleanup.yml` je zatím jen na větvi `codex/server-storage-cleanup-20261126`. Čtecí `preview` má v produkci sestavit přesný plán a otisk; `apply` vyžaduje doloženou obnovu hostingového snapshotu ve verzovaném `hosting-restore-proof.json`, shodu otisku, potvrzení a ochranu prostředí. Bez souboru s důkazem je `apply` blokováno. Čtyři syntetické testy plánovače **PASS**: přesná dávka ponechává aktuální vydání a malé podpůrné složky, změněná velikost release, neznámý záložní adresář nebo starý aktivní marker operaci zastaví. Produkční `preview`, ověření hostingové obnovy a jakékoli mazání **NOT RUN**.

## Úplný manifest vydání 26. 11. 2026

[PR #532](https://github.com/KovoPraha/evidenceTreninku/pull/532) byl sloučen jako `eac47e90`. Rozšířený chráněný [čtecí běh 45807461553](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45807461553) **PASS**: vypisuje všech 280 adresářů `data/.kis-deploy/releases/`, všechny děti `data/.kis-backups/` a hlavní adresáře subdomén. Součet release je 14 542 752 KiB (13,87 GiB); dalších 36 KiB představuje režii nadřazeného adresáře. Součet účtu v tomto běhu je 23 552 116 KiB. Rozdíl oproti běhu 25. 11. je 32 KiB; pro kvótu nadále platí administrace hostingu ze dne 25. 11.

[Strojově čitelný manifest](release-manifest-20261126.json) obsahuje všech 280 přesných cest, velikost každé kopie a návrh `keep`/`candidate`. Prvních deset podle ID běhu odpovídá posledním deseti úspěšným produkčním deployům v historii GitHub Actions. Tyto ponechané kopie mají 581 704 KiB (568 MiB). Zbývajících **270 historických kopií má 13 961 048 KiB (13,31 GiB)**. Živý `kis.kovopraha.cz/` se aktivuje samostatným rsyncem; adresáře release nejsou jeho webroot. Potenciální úspora výrazně převyšuje překročení WWW kvóty 2 773 MB, ale není ještě skutečně uvolněná. Manifest je **KONCEPT** dávky, ne příkaz k mazání. Před zásahem je nutné znovu porovnat stav produkce, potvrdit aktivní verzi a prokázat obnovu ze záloh.

Rozpis všech subdomén a skrytých položek potvrzuje, že podstatná část místa je v `data/`. Hlavní web má 1 851 496 KiB, z toho `wp-content/` 1 762 804 KiB; `data.kovopraha.cz/` 794 032 KiB, z toho `evidence/` 677 412 KiB. Živý KIS web má 657 532 KiB, z toho `nahrane_obrazky/` 405 172 KiB a `uploads/` 143 456 KiB. Jsou to obsahová a uživatelská data, nikoli doložené odpady. Ostatní jednotlivé subdomény jsou výrazně menší. Pět úplných KIS ZIP záloh v `.kis-backups/files/` má dohromady 3 312 640 KiB; další kopie/převodní snapshoty a DB soubory tvoří zbytek 4 923 288 KiB. Vlastní zálohy zatím nejsou součástí dávky.

Pokus o kontrolu novější hostingové generace 26. 11. skončil na přihlašovací stránce: dřívější relace klientské sekce vypršela. Připojení generace, kontrola soukromých příloh a izolovaná obnova DB z hostingového snapshotu **NOT RUN**. Stejně tak úklid, nové měření kvóty a produkční smoke po úklidu **NOT RUN**.

## Správa celé domény 25. 11. 2026

Přihlášená [správa hostingu kovopraha.cz](https://muj.cesky-hosting.cz/index.php?page=domeny-statistiky&id_domain=70155) ukázala oddělené kvóty. Údaj WWW byl ručně aktualizován tlačítkem „Aktualizovat údaje“ a stránka potvrdila aktualizaci; ostatní údaje jsou poslední hodnoty z administrace.

| Segment domény | Obsazeno / limit | Závěr |
| --- | --- | --- |
| WWW prezentace (FTP), všechny subdomény a další adresáře domény | **22 773 / 20 000 MB**, 996 361 souborů | **2 773 MB nad limitem**; vlastní soubory a release kopie hledat zde. |
| E-maily | 5 669 / 9 330 MB, 36 418 / 93 300 souborů | Mimo limit WWW; samostatná kvóta má 3 661 MB rezervu. |
| Databáze | 70 / 500 MB | Mimo limit WWW; přibližně 430 MB rezervy. |

[Hlavní SFTP/SCP/SSH účet](https://muj.cesky-hosting.cz/index.php?page=domeny-ftp&id_domain=70155) dle administrace dosáhne do adresářů všech subdomén domény. [Webserver](https://muj.cesky-hosting.cz/index.php?page=domeny-webserver&id_domain=70155) uvádí hlavní web a 13 subdomén včetně KIS, baru, registrace, výsledků a závodů. Nový drill proto vypíše součet dostupného účtu, všechny položky první úrovně včetně skrytých a detail `data/`; po běhu se musí porovnat s kvótou hostingu. Fyzické disky sdíleného serveru ani jiné domény téhož zákazníka nejsou součástí této kvóty.

V [administraci hostingových záloh](https://muj.cesky-hosting.cz/index.php?page=domeny-zalohy&id_domain=70155) jsou pro tuto doménu skutečně dostupné seznamy 11 WWW generací (31. 10.–24. 11. 2026, nejnovější 24. 11. 23:31) a 11 MariaDB generací (28. 10.–24. 11. 2026, nejnovější 24. 11. 22:14). Připojení generace a izolované ověření souborů, soukromých příloh i DB **NOT RUN**; samotný seznam nedokazuje úplnost ani obnovitelnost.

[PR #530](https://github.com/KovoPraha/evidenceTreninku/pull/530) byl po zelených povinných kontrolách sloučen jako `6ab46ab9`. První čtecí [běh 45655069369](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45655069369) z `main` **FAIL**: SSH krok skončil návratovým kódem 1 ještě před prvním vzdáleným výpisem; nic nesmazal, žádnou velikost nevrátil. Následující diagnostika prokázala chybějící `df` v chrootu. Místní přístup bez příslušného produkčního klíče byl odmítnut; není zdrojem měření.

## Úplný čtecí rozpis dostupného účtu

Oprava v [PR #531](https://github.com/KovoPraha/evidenceTreninku/pull/531) byla sloučena jako `a9bd46a1`; druhý chráněný [drill 45658314928](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45658314928) **PASS**. Potvrdil pracovní kořen SSH `/`, přítomnost KIS webu, release i záloh a nepřítomnost nástroje `df` v chrootu; právě povinná kontrola `df` zastavila první běh. `du` je dostupné. Součet čtecího `du -sk .` je **23 552 148 KiB = 22,46 GiB**; administrace kvóty hlásí 22 773 MB. Rozdíl cca 227 MiB může zahrnovat chrootové systémové adresáře a odlišnou metodu účtování; pro limit platí administrace hostingu.

| Položka v dostupném účtu | KiB | Přibližně GiB | Podíl součtu účtu |
| --- | ---: | ---: | ---: |
| `data/` celkem | 19 625 408 | 18,72 | 83,3 % |
| `data/.kis-deploy/` | **14 562 028** | **13,89** | **61,8 %** |
| `data/.kis-backups/` | **4 923 288** | **4,70** | **20,9 %** |
| z toho `data/.kis-backups/files/` (5 úplných ZIPů) | 3 312 640 | 3,16 | 14,1 % |
| hlavní `kovopraha.cz/` | 1 851 496 | 1,77 | 7,9 % |
| `data.kovopraha.cz/` | 794 032 | 0,76 | 3,4 % |
| živý web `kis.kovopraha.cz/` | 657 532 | 0,63 | 2,8 % |
| `team.kovopraha.cz/` | 158 992 | 0,15 | 0,7 % |
| `bar.kovopraha.cz/` | 142 824 | 0,14 | 0,6 % |
| `data/kis-private/` | 105 940 | 0,10 | 0,4 % |

Číslo pro `data/.kis-backups/files/` je podmnožina záloh, nesčítat jej znovu. Ostatní subdomény jsou každá pod 44 000 KiB; obě skryté KIS složky dohromady představují 82,7 % součtu dostupného účtu. Drill našel **280 release adresářů** (proti historickým 184 dne 30. 10.). Největší jednotlivé mají 58–77 MiB. K 25. 11. ještě chyběl součet samotného `releases/` a explicitní ponechané verze; doplnil je čtecí běh 26. 11. výše. Celých 13,89 GiB `.kis-deploy` nelze bez dalšího označit k smazání.

## Čtecí průběh 25. 11. 2026

| Kontrola | Výsledek | Omezení |
| --- | --- | --- |
| [Existující provozní drill 45607308483](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45607308483), `overit-dopad-kluboveho-vydani` na `main` | PASS; pouze čtení, dočasné diagnostické skripty uklizeny | Při úspěchu nevypisuje inventuru disku; žádné dnešní velikosti z něj nelze odvodit. |
| [Diagnostický drill 45607924536](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45607924536) z pracovní větve | FAIL před spuštěním jobu: ochrana prostředí `production` nepovoluje tuto větev | Ochranu neobcházet; nový čtecí krok musí projít PR/merge do `main`. |
| Místní worktree `origin/main` `dabb1d71` | `docs/` 147,3 MiB; `assets/` 15,0 MiB; `includes/` 4,1 MiB; 246 souborů v kořeni 12,25 MiB | `docs/` se v obou krocích produkčního rsync explicitně vylučuje; místní worktree není měření produkce. Místní `vendor/` v jiném checkoutu 117,4 MiB obsahuje i vývojové závislosti, neslouží jako velikost nasazení. |
| Historický [diagnostický JSON z 30. 10.](../refactoring-final-release-20261030/post-failure-diagnostics.json) | 184 release adresářů; 9 z nich změřeno na 290,9 MiB dohromady, medián 28,6 MiB; 60 souborů přímo v `.kis-backups` 116,0 MiB | Inventura release byla `inventory_complete=false` a zálohy počítala jen na první úrovni. Celkovou velikost tehdejších ani dnešních záloh z ní nelze odvodit. |
| Úplné souborové zálohy KIS | [Archiv 5. 11.](../bar-release-20261105/RESULTS.md) 613 288 913 B, [archiv 6. 11.](../bar-cutover-20261106/post-cutover-backup.json) 720 738 853 B | ZIP se ukládá do `.kis-backups/files/` a obsahuje aplikaci i soukromé soubory. Starší diagnostika tuto podsložku vůbec nepočítala; dnešní počet pěti archivů je změřen výše. |
| Deploy workflow | Každý deploy připravuje úplný release v `data/.kis-deploy/releases/<sha>-<run>`; v obou rsync krocích vylučuje `docs/`; záloha používá `BACKUP_KEEP=20` | V workflow není doložená pravidelná retence release adresářů. Deset nejnovějších úspěšných kopií je v manifestu výše označeno k ponechání. |

Český hosting uvádí [noční zálohování souborů, databází a e-mailů](https://www.cesky-hosting.cz/napoveda/zalohovani-a-obnoveni-dat/), 7 denních a nejméně 3 týdenní generace. Výjimky: adresáře `cache`, `sessions`, `temp` a soubory větší než 1 GB. Zálohu lze připojit v klientské sekci pro obnovu. [Kvóty WWW, e-mailů a DB jsou oddělené](https://www.cesky-hosting.cz/napoveda/objednavky-a-zrizeni-sluzeb/doplnkove-sluzby/); přihlášená administrace nyní potvrdila, že nad limitem je WWW. Dostupnost generací je ověřena výpisem, jejich obsah a obnova **NOT RUN**.

**Předběžní kandidáti:** staré release kopie podle manifestu 26. 11.; úplné ZIP archivy i DB zálohy až po ověřené hostingové obnově; prokazatelně dočasné skripty. Soukromé přílohy, produkční DB a novější release nejsou kandidáti bez samostatného důkazu. Dnešní kvóta i hlavní WWW adresáře jsou změřené; uvolněné místo a produkční funkční kontrola **NOT RUN**.
