# Aktuální stav projektu

Nejnovější vydání 21. 10. 2026: [PR #156](https://github.com/KovoPraha/evidenceTreninku/pull/156)
opravil výdejové akce Zásilkovny a oddělil skutečné systémové stavy od vlastních
zákaznických upřesnění. Správa stavů nyní obsahuje editor šesti existujících
systémových e-mailů, syntetický náhled, historii a zmrazený obsah/verzi ve frontě.
Nasazeno SHA `574328421fff748b48f7d744a3ce454b9b50dd1e`,
[deploy 39603396933](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39603396933)
prošel zálohou 236 tabulek / 2 triggerů, 110 migracemi bez čekajících kroků a
HTTP 200. PHPUnit 1 178/26 530, MariaDB 10.3/11.4 a CodeQL zelené. Živě ověřen
přehled stavů, editor/náhled bez zápisu a fronta; starší zpráva nezměněna,
3 pending / 0 pokusů / 0 sent. Simulace Zásilkovny vypnuta; ostrá doprava
neaktivní do zadání ceny, veřejného klíče a povolení. Fakturace je odložená,
`uat_schvaleno=false`, `importovat_krouzky=false`. Podrobnosti a hranice v
[produkčním reportu](ORDER_STATUS_EMAIL_RELEASE_2026-10-21.md).
Následující záznamy jsou historická vydání.

Navazující produkční ověření 21. 10. 2026: **PASS** pro celý TEST cyklus
převod → hotovost → příjem → storno → vratka. Nová TEST objednávka
`KP2610218B7C69C006` za 1 Kč je Stornováno/Vráceno, TEST produkt je stažený
a sklad se vrátil na původní 1 kus. Skutečné peníze ani objednávky se neměnily.
[Protokol a důkazy](ORDER_CASH_REFUND_PRODUCTION_2026-10-21.md) zachycují
audit, přesný rozsah, úklid a vztah k souběžnému navazujícímu deployi.
Scénář je uzavřený, žádná TEST vratka již nečeká na dokončení. Dokumentace
testu je v [PR #157](https://github.com/KovoPraha/evidenceTreninku/pull/157),
commit `a0e2bac1`, s úspěšnými PHPUnit, MariaDB 10.3/11.4 a CodeQL.
[Provozní postup příjmu a vratky](ORDER_PAYMENTS_2026-10-21.md) je dostupný
také z [rozcestníku dokumentace](README.md). Plné UAT tím není schválené.

Nejnovější doplnění: 21. 10. 2026. [PR #152](https://github.com/KovoPraha/evidenceTreninku/pull/152)
přidal možnost změnit **název registračního dokumentu**, i bez změny jeho těla.
Koncept a zveřejněná verze uchovávají název spolu s textem; přihlášky a historie
zobrazují název příslušné verze. Registrář má nové pole přímo v editoru.
Nasazený aplikační SHA `f5d6276838239ace6426aa8c326c01a213730a36` má úspěšný
[deploy 39593524611](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39593524611),
zálohu 234 tabulek / dvou triggerů, 108 migrací bez čekajících kroků a živé
ověření nového pole. PHPUnit, MariaDB 10.3/11.4 i CodeQL prošly; produkční
texty se při ověření neměnily. Podrobnosti jsou v
[reportu názvů](REGISTRATION_TEXT_TITLES_2026-10-21.md). Plné UAT zůstává otevřené.
Následující release jsou historické body.

Nejnovější produkční doplnění: 21. 10. 2026, hotovostní úhrady objednávek z
[PR #150](https://github.com/KovoPraha/evidenceTreninku/pull/150).
Aplikační SHA `f813264b16ffd49224bd41a908adc33d9687af88` má úspěšný
[deploy 39590481900](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39590481900):
ověřená záloha, 107 aktuálních migrací, aktivace a HTTP 200. Hospodář může
společně změnit převod na hotovost a zaevidovat příjem celé částky; součástí
je audit, navazující aktivace, zobrazení způsobu úhrady a ruční vratka.
CI: 1 155 testů / 26 040 kontrol, MariaDB 10.3/11.4 a CodeQL.
[Postup a ověřovací hranice](ORDER_PAYMENTS_2026-10-21.md) obsahují dokončené
přihlášené ověření formuláře z 21. 10.: přechod od objednávky, volbu hotovosti,
povinná pole, CSRF, vizuální kontrolu a konzoli bez chyb. Formulář platby nebyl
odeslán a následná kontrola potvrdila nezměněnou objednávku. Kontrola proběhla
na navazujícím release `f5d6276`. Plné UAT aplikace zůstává samostatné.
Následující produkční záznamy jsou historické.

Nejnovější doplnění: 21. 10. 2026. PR [#148](https://github.com/KovoPraha/evidenceTreninku/pull/148)
přidal **Registrační texty a souhlasy** pod pozici **Registrář členů a KIS**.
Samostatně se ukládá neměnný koncept a zveřejňuje jeho přesné znění; historie
obsahuje předchozí verze, autora a důvod. Nasazený aplikační commit je
`3c64dc530fa8025854f2cc8be07e74bdc1a51aab`,
[deploy 39588673226](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39588673226)
prošel zálohou 233 tabulek / dvou triggerů, 107 migracemi bez čekajících kroků
a HTTP smoke. CI ověřilo PHPUnit i MariaDB 10.3/11.4; živý browser potvrdil
menu a všech pět typů textů. Dosavadní schválená znění a staré přihláškové
snapshoty se neměnily. Návod a přesné důkazy jsou v
[reportu registračních textů](REGISTRATION_TEXTS_ADMIN_2026-10-21.md).
Plné UAT zůstává otevřené. Následující release jsou historické body.

Nejnovější produkční oprava: 21. 10. 2026. PR
[#146](https://github.com/KovoPraha/evidenceTreninku/pull/146) opravil chybějící
načtení bankovního nastavení v rychlé přihlášce a rychlém nákupu bez účtu.
Nasazený aplikační SHA je `e935950ed5be7181687dadba4b1fc2daf8dd5095`;
[deploy 39585582515](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39585582515)
dokončil ověřenou zálohu 233 tabulek, kontrolu 106 migrací bez čekajících kroků,
aktivaci a HTTP smoke. [CI main](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39585403375)
ověřilo PHPUnit a MariaDB 10.3/11.4, CodeQL také prošel. Celá testovací sada
včetně dvou nových regresních testů: 1 140 testů / 25 706 kontrol.
Po nasazení byla rychlá přihláška Aligátoři (`product_id=247&variant_id=813`)
ověřena v prohlížeči; přihláška, detail produktu, e-shop a homepage vracejí
HTTP 200 bez PHP fatální chyby. Ověření nevytvářelo produkční přihlášku ani
objednávku. Nasazení má `uat_schvaleno=false` a `importovat_krouzky=false`.
Podrobnosti jsou v [aktuálním předání](HANDOFF_CURRENT.md); následující
produkční záznamy zachycují historické body.

Nejnovější doplnění administrace: 20. 10. 2026. PR [#144](https://github.com/KovoPraha/evidenceTreninku/pull/144) sjednotil zbývající starší formuláře, ukládací tlačítka, navazující karty a opravil vnořené akce hlavičky i ztrátu vyplněného tréninku při chybějící soupisce. Release `246dee2a4df5dc3152c1a769a9af128b87a91092` má úspěšný [deploy 39496342888](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39496342888) a nezávislé potvrzení SHA v produkčním prohlížeči. Lokálně prošlo 1 138 testů / 25 708 kontrol, 105 adres v osmi rolích a konkrétní syntetické zápisy; produkčně osm formulářových stránek a přepnutí role z podadresáře. Přesný rozsah a omezení popisuje [report formulářů](REMAINING_ADMIN_FORMS_2026-10-20.md). Plné UAT zůstává otevřené. Následující release jsou historické body.

Produkční doplnění administrace: 20. 10. 2026. Sjednocený vzhled a správa produktů, importovaných variant, cen a skladu z PR #140–142 jsou nasazené v release `175f92c7a59294acff7e15fe09cbed258707fa2e`; [deploy 39483597831](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39483597831) prošel. Chrome ověřil přidání a editaci skryté varianty importovaného zboží, ceny, sklad 0 → 2 → 0 s auditem, archivaci konceptu, veřejné skrytí testovacích dat, mobilní navigaci i kontrast. CI: 1 137 testů / 25 679 kontrol, MariaDB 10.3/11.4 a CodeQL. Přesné důkazy a stav testovacích dat obsahuje [produkční report](UNIFIED_ADMIN_SHOP_PRODUCTION_2026-10-20.md). Nejde o schválení plného UAT ani zapnutí plateb, Fio nebo rozesílek. Níže uvedené starší SHA jsou historické body svých částí.

Produkční doplnění: 20. 10. 2026, Europe/Prague, po nasazení hierarchie soupisek.
PR [#138](https://github.com/KovoPraha/evidenceTreninku/pull/138) byl sloučen jako
`4991c91f966eb65852876fd0084dd6ad041e3b69` a tento přesný SHA nasadil
[běh 39472709660](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39472709660).
Povinné PHPUnit a MariaDB 10.3/11.4 kontroly nad `main` prošly v
[CI 39472026706](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39472026706),
CodeQL v [běhu 39472026146](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39472026146).
Deploy ověřil zálohu, 106 aktuálních migrací bez čekajících kroků, aktivaci
release, ochranu klíče archivu a HTTP 200. Samostatný živý test potvrdil HTTP
200 na homepage, HTTP 403 na soukromém klíči a načtení nové administrační
stránky v roli Registrář členů a KIS. První pokus o deploy
[39472178866](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39472178866)
skončil po úspěšné aktivaci na síťovém timeoutu bezpečnostního HTTP testu;
opakovaný běh nad stejným SHA dokončil všechny kroky.

Nasazený kód zatím sám nepřiřadil soupisky k nadřazeným skupinám ani nezapnul
opakování nabídek či automatické párování plateb. Živá administrace ukázala
43 soupisek bez skupiny; read-only porovnání starých názvů ukázalo
0 přesných shod a 46 položek bez vazby. Jejich rozhodnutí a časově platné
zařazení je další datový krok. Plné produkční UAT tohoto releasu nebylo
schváleno (`uat_schvaleno=false`). Rozsah a předání popisuje
[implementační protokol](ROSTER_HIERARCHY_IMPLEMENTATION_HANDOFF_2026-10-20.md).

Níže uvedené dřívější produkční doplnění je historický stav před PR #138.

Produkční doplnění: 20. 10. 2026, Europe/Prague

Rodičovská cesta z PR #91 je nasazená včetně ověření 94 migrací. Poslední
aplikační release po opravě příslušenství z PR #92 má SHA
`fd6ccc1584beb3a3266e8147497263cb8a38044f`:
[produkční běh 39402251446](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39402251446)
i [CI 39402250654](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39402250654)
prošly. Rozsah živého ověření a úplný seznam
52 nepropojených účastnických variant uvádí
[produkční report rodičovské cesty](PARENT_JOURNEY_PRODUCTION_2026-10-20.md).
Podrobnosti implementace a syntetického akceptačního testu zachycuje
[lokální předání](PARENT_JOURNEY_2026-10-20.md).

V prohlížeči prošlo přihlášení testovacího rodiče, zobrazení jeho rodiny
a stránka Moje kroužky. Novou žádost o dítě blokuje chybějící platná informace
o zpracování osobních údajů. Všech 52 kontrolovaných účastnických variant
správně brání nákupu bez návazností. Současně existuje 26 zveřejněných programů
a 74 aktivních nabídek se soupiskami: scházejí vazby importovaných variant,
platný souhlas se storno podmínkami všech 74 nabídek a potvrzení jejich
neomezené kapacity.
Pumptrack má rozporné ceny, Točňáci 1× týdně nevyřešený výběr dne
a Kunratice neúplný čas / rozdělení věkových skupin.

Úplný produkční průchod novou registrací, platbou a zařazením do soupisky
ani doručení e-mailů zatím nejsou ověřené. Fio, Stripe a SumUp se nezapínaly
a skutečné produktové vazby se neměnily. Lokální sada po opravě příslušenství
v PR #92: **1 019 testů / 23 532 kontrol**, bez selhání a chyb.
Po posledním deployi prohlížeč ověřil správné rozlišení Triček / Uskladnění
od kroužku Aligátoři. Veřejná prezentace TEST programu je koncept;
rodičovský katalog jej nenabízí. Pozitivní produkční nákup
nebyl dokončen.

Následující původní stavový přehled je **historický snapshot z 11. 10. 2026**,
včetně SHA, CI a produkčních výsledků. Nepopisuje aktuální změny ani
nepotvrzuje současný produkční stav.

Tento soubor je autoritativní stavový rozcestník. Historické audity, předávací
záznamy a implementační prompty zachycují stav v datu svého vzniku a nesmí se
používat jako důkaz aktuální verze. Před každou novou prací má přednost čerstvý
`git fetch`, větev `origin/main`, aktuální GitHub Actions a skutečný kód.

## GitHub a produkce

- Repozitář: <https://github.com/KovoPraha/evidenceTreninku>
- Výchozí větev: `main`
- Stav vzdálené větve `origin/main` při této kontrole:
  `16b7d4d60883f3f4f655c097339339c68c1be07d`
- Otevřené pull requesty při této kontrole: žádné
- Produkční aplikace: <https://kis.kovopraha.cz/>
- Poslední ověřené nasazení: GitHub Actions běh
  [`37886677875`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37886677875)
  nasadil přesně commit `16b7d4d60883f3f4f655c097339339c68c1be07d`.
- Nasazení vytvořilo a ověřilo databázovou zálohu, připravilo úplný release,
  aplikovalo migrace, aktivovalo kód a dokončilo HTTP smoke test.
- Po nasazení byly z prohlížeče ověřeny domovská stránka, e-shop, veřejný
  rozvrh tréninků, klubový kalendář, velodrom a přihlášení. Žádná z těchto
  cest neobsahovala fatální chybu ani chybu či varování v konzoli.
- Veřejná domovská stránka a bezpečný vstup k veřejnému profilu po nasazení
  odpovídají HTTP 200 a posílají nonce CSP bez povolených inline skriptů.
  Soubor `var/deployment.json` není veřejný a správně odpovídá HTTP 403; důkaz
  release se proto čte z chráněného workflow, nikoli z veřejné URL.

Publikování tohoto dokumentu vytvoří novější dokumentační commit na `main`, ale
nemění nasazený aplikační commit. Přesné aktuální SHA `main` se proto vždy čte
pomocí `git fetch --prune origin` a `git rev-parse origin/main`, nikoli opisem z
dokumentu. Samotný push ani merge do `main` produkci nemění. Produkční nasazení
je vždy samostatný ručně spuštěný workflow podle
[`NASAZENI.md`](NASAZENI.md).

Bezpečnostní nastavení repozitáře bylo 8. 10. 2026 zpřísněno: je zapnuté
secret scanning, push protection, Dependabot alerts i automatické bezpečnostní
aktualizace. GitHub CodeQL default setup běží v rozšířeném režimu pro Actions a
JavaScript/TypeScript; první běh
[`37764312992`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37764312992)
prošel bez otevřeného nálezu. PHP závislosti navíc kontroluje `composer audit`
v hlavním CI jobu.

## Poslední automatické ověření

GitHub Actions běh
[`37886667244`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37886667244)
nad aktuálním `origin/main` a současně nasazeným commitem
`16b7d4d60883f3f4f655c097339339c68c1be07d` prošel v tomto rozsahu:

- PHPUnit na PHP 8.2: **877 testů / 11 995 kontrol**;
- integrační smoke na MariaDB 10.3: úspěch;
- integrační smoke na MariaDB 11.4: úspěch;
- kontrola Composer konfigurace: úspěch.

CodeQL běh
[`37886667406`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37886667406)
nad stejným commitem prošel pro GitHub Actions i JavaScript/TypeScript.

Migrační katalog obsahuje 84 verzovaných PHP migrací včetně bezpečnostních
migrací připravených v pracovní větvi a zmrazený legacy baseline 2.20.2.
Aktuální zálohovací ownership kontrakt zdrojového kódu je `2026-10-08.1`.
Počet souborů není důkazem stavu konkrétní databáze; ten se na každé stanici
ověřuje pomocí `php bin/migrate.php --check --json` s nastaveným `APP_HOST`.

## Funkční stav po změnách z 9. 10. 2026

Pull requesty
[#34](https://github.com/KovoPraha/evidenceTreninku/pull/34),
[#35](https://github.com/KovoPraha/evidenceTreninku/pull/35),
[#36](https://github.com/KovoPraha/evidenceTreninku/pull/36),
[#38](https://github.com/KovoPraha/evidenceTreninku/pull/38) a
[#39](https://github.com/KovoPraha/evidenceTreninku/pull/39),
[#53](https://github.com/KovoPraha/evidenceTreninku/pull/53) a
[#55](https://github.com/KovoPraha/evidenceTreninku/pull/55) jsou sloučené do
`main`. Aktuální release mimo jiné obsahuje:

- srozumitelnější registraci a práci s heslem;
- předletovou kontrolu klubové akce před otevřením přihlašování;
- dvoukrokové ruční založení sportovce;
- bezpečné opakování nebo návrat při nedostupné platební bráně;
- auditované odebírání pracovních pozic a aktivaci/deaktivaci účtů;
- povinnou vazbu plánovaného tréninku na soupisku, nebo výslovné označení
  interního tréninku bez účastníků;
- potvrzení účasti na tréninku rodičem či sportovcem a přehled odpovědí pro
  trenéra;
- zachování vazeb na soupisky při kopírování týdne;
- opravené produkční spouštění fronty zpráv soupiskám přes neveřejný PHP
  bootstrap;
- opravy druhé vlny testerových bodů a pravdivé zobrazení neomezené kapacity
  ve zrychlené přihlášce;
- centralizované a validované ukládání souborů mimo veřejný webroot;
- jednorázové časově omezené odkazy pro veřejný přístup k profilu místo
  přímého přenosu interního identifikátoru;
- limity těla Stripe a SumUp webhooků, fail-closed ověření SumUp odpovědi,
  transakční zápisy a odstranění runtime DDL z obsluhy požadavků;
- nonce CSP pro inline skripty a styly a zúžený přístup k servisním endpointům.
- opravy druhé iterace prohlížečového UAT: bezpečný přechod mezi účtem
  sportovce a rodiče, funkční našeptávač poplatků, QR fallback, lepší mobilní
  správa trenérů, filtrování a stránkování administrativních seznamů a
  idempotentní lokální demonstrační data.

Produkční běh fronty zpráv
[`37606107788`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37606107788)
nad commitem `623e161` skončil úspěšně. Workflow je naplánovaný každých pět
minut a lze jej spustit také ručně.

Podrobný uživatelský dopad testerových oprav popisuje
[`UAT-OPRAVY-2026-10-07.md`](UAT-OPRAVY-2026-10-07.md).

Navazující UX release sjednocuje veřejné akce a kalendář, přidává skutečný
měsíční rozvrh tréninků, hierarchickou navigaci e-shopu, kratší anonymní
nákupní cestu a prioritní přehledy rodiče a trenéra. Rozsah a ověřovací hranice
jsou popsány v
[`UX-NAVIGACE-A-KALENDARE-2026-10-08.md`](UX-NAVIGACE-A-KALENDARE-2026-10-08.md).

## Důležitá provozní hranice

Poslední deploy má `uat_schvaleno=true`, což označuje schválení přesného
commitu pro řízené produkční UAT, nikoli neomezené povolení zápisů. Read-only
kontrola připravenosti
[`38093377833`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/38093377833)
potvrdila `ready=true` ve všech deseti oblastech: release, rodiče, děti,
správce, TEST data, technické vlastnictví, Stripe sandbox, banka, inbox i
úklidové okno. Časově omezené účty a data byly předtím idempotentně obnoveny
během
[`38093332413`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/38093332413).
Schválené okno končí 12. 10. 2026 ve 20:00 Europe/Prague. Reálné e-maily,
bankovní pohyby a provozní účty se používají jen v rozsahu schváleného scénáře
podle
[`PRODUKCNI-UZIVATELSKE-TESTOVANI.md`](PRODUKCNI-UZIVATELSKE-TESTOVANI.md).

Navazující read-only kontrola databázových invariantů
[`38093408270`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/38093408270)
skončila `ok=true`: 16/16 kontrol bez porušení. Deploy před aktivací vytvořil
ověřenou komprimovanou zálohu 196 tabulek a 2 triggerů mimo webroot.

Obnova poslední generace produkční zálohy v izolované MariaDB byla ověřena
během [`37701691548`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37701691548):
195 tabulek a 2 databázové triggery.

Fio import je od 7. 10. 2026 záměrně provozně pozastavený. Automatický plán byl
odstraněn; kód zůstává připravený pro budoucí ruční obnovení po samostatném
schválení read-only tokenu a provozního postupu.

## Převzetí práce na jiné stanici

Aktuální postup je v [`HANDOFF_CURRENT.md`](HANDOFF_CURRENT.md). Základní pravidla:

1. vždy začít `git fetch --prune origin`;
2. ověřit čistý pracovní strom a shodu lokálního `main` s `origin/main`;
3. novou práci založit z čerstvého `origin/main` na větvi `codex/<téma>`;
4. nemíchat do commitu `config.php`, tajemství, produkční data, lokální importy
   ani cizí rozpracované změny;
5. změnu dostat do `main` přes pull request a zelené kontroly;
6. produkční deploy považovat za samostatné, výslovně schválené rozhodnutí.

## Autorita dokumentace

Pro pokračování ve vývoji platí toto pořadí:

1. skutečný `origin/main`, migrace, testy a workflow;
2. tento stavový soubor a [`HANDOFF_CURRENT.md`](HANDOFF_CURRENT.md);
3. provozní runbooky, zejména [`NASAZENI.md`](NASAZENI.md);
4. tematické dokumenty k jednotlivým funkcím;
5. datované audity, staré handoffy, roadmapy a implementační prompty pouze jako
   historický kontext.

Starší soubory nebyly mazány, protože zachovávají rozhodovací a auditní stopu.
Jejich stará SHA, počty testů a tvrzení typu „plánováno“ nejsou aktuálním
stavem, pokud jsou v rozporu s výše uvedenými autoritami.
