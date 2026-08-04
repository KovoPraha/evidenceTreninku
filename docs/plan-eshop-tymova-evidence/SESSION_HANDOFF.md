# Session handoff

Tento soubor je stručný obnovitelný stav řídicího tasku. Architekturu ani
roadmapu neduplikuje; odkazuje na jejich kanonické dokumenty. Všechny provozní
hodnoty jsou historické, dokud je nový řídicí task živě neověří.

## Metadata

- Aktualizováno: 2026-08-04, Europe/Prague
- Poslední přijatý implementační HEAD: `95693a2`.
- Implementace `95693a2` je commitnutá; větev `main`, upstream `origin/main`,
  před navazujícím dokumentačním commitem lokálně `ahead 7 / behind 0`.
- Localhost DB je 43/43. Vzdálený repozitář se v této M2.3e session neměnil;
  produkční workflow je ruční a produkce se nemění.
- Repozitář: `C:\xampp\htdocs\evidencePavel`
- Programová brána: F0 – červená
- Aktivní integrační větev: `main`; technická část M1 je dokončená a M2.1
  zahájilo provozní localhost pilot. Vlastníkova prohlídka A01–A10 zůstává
  otevřenou produktovou branou.
- Auth kódový tip před tímto handoff commitem: `9977b4dfc3f2f6aab775825d0bdf9b629e61e217`;
  auth přírůstek tvoří
  `a3c2239` (revokace + limiter), `10c2cf9` (atomická rezervace + SSO abort) a
  `9977b4d` (HMAC pepper + sjednocené pořadí zámků)
- Původní base: `58ec8ec985d447dfe901481ac8bb24b944b03d08`
- Produkční deploy bez výslovného souhlasu: zakázán
- Produkční DB změny bez výslovného souhlasu: zakázány
- Aktuální produktová autorita pro localhost:
  [10 – Milník M2](10-milnik-m2-provozni-pilot.md); cílem je provozní pilot nad
  integrovanou Evidencí, e-shopem a členskou evidencí. Fio auto-confirm, Stripe,
  wallet a ostrý import zůstávají blokované.
- Poslední dokončená akce: `95693a2` dokončuje M2.3e uložený cutover paritní
  report. Každý nový běh atomicky porovnává osoby, aktivní členství, snapshot
  soupisek a agregované platební signály s cílovou Evidencí. Report používá jen
  pořadové reference, pevné kategorie, počty a fingerprinty; neobsahuje jména,
  KIS ID ani peněžní hodnoty. Run #9 pravdivě ukazuje tři blokátory: dvě nové
  demo osoby a chybějící cílový kontrakt jednotlivých členských platebních
  předpisů. Browser ověřil report a sandbox 2/2 → 0/2. Plná sada je 379/3332,
  syntaxe 401 souborů, migrace 43/43 a audit 0. Další akce: definovat cílový model
  členských předpisů a potvrdit hlavičky na reprezentativním anonymizovaném exportu.

  Předchozí dokončená akce: `2bcb346` dokončuje M2.3d stabilní field/external-ID
  kontrakt. `kis-import-field-v1` vyžaduje interní KIS ID ve všech třech exportech,
  drží jej odděleně od UCI licence, umí podle něj spojit i bezejmennou platbu a
  fail-closed blokuje chybějící, neplatné, duplicitní nebo rozporné identity.
  Non-PII report neobsahuje jména ani hodnoty KIS ID. Upload nyní archivuje všechny
  tři zdroje mimo webroot a kanonický execute vyžaduje připravený field kontrakt.
  Browser nad run #8 potvrdil 2/2, sandbox 2/2 a rollback 0/2; run #7 bez kontraktu
  nelze znovu aplikovat. Plná sada je 377/3308, syntaxe 398 souborů, migrace 42/42
  a audit 0. Další akce: potvrdit aliasy hlaviček na reprezentativním anonymizovaném
  exportu a uzavřít úplný paritní report; produkční cutover není autorizovaný.

  Předchozí dokončená akce: `5caa850` dokončuje izolovaný M2.3c sandbox promote/rollback.
  Akce je jen pro localhost administrátora, vyžaduje CSRF, potvrzení, důvod a čerstvý
  fingerprint. Promote je transakční, idempotentní a ukládá pouze neprůhledné položky
  a audit do `kis_import_sandbox_*`; kanonické osoby, soupisky, platby ani objednávky
  nemění. Rollback deaktivuje sandbox položky a funguje i při pozdějším driftu preview.
  Browser nad run #7 potvrdil 2/2 aktivní + 1 auditní událost a rollback 0/2 + 2;
  první průchod živě odhalil a opravil chybějící CSRF helper. Plná sada je 369/3254,
  syntaxe 396 souborů, migrace 41/41 a audit 0. Navázal na něj M2.3d field kontrakt.

  Předchozí dokončená akce: `26076ba` dokončuje M2.3b integritu uloženého KIS preview.
  Každý nový běh atomicky ukládá úplnou klasifikaci, verzi kontraktu, bezpečný JSON
  a stabilní fingerprint nezávislý na ID běhu. Chybějící raw archiv, neúplná identita,
  konflikt, nejednoznačnost a duplicate target jsou fail-closed blokátory. Report
  používá pouze neprůhledné pořadové reference a pevné důvody. Synchronizační centrum
  jej ukazuje a exportuje s `no-store`; stále neexistuje žádný promote ani zápis do
  profilů. Localhost demo run #7 má 2/2 klasifikované řádky, nula blokátorů a opakovaný
  seed vrací stejný run/fingerprint. Plná sada je 364/3197, syntaxe 392 souborů,
  migrace 40/40 a audit 0. Další akce: uzavřít finální external-ID/field kontrakt,
  potom implementovat výhradně izolovaný testovací promote a kompenzační rollback.

  Předchozí dokončená akce: `875c9e3` připravuje skutečnou vlastníkovu/Cowork bránu.
  Každá karta A01–A10 na localhost rozcestníku ukládá PASS/PARTIAL/FAIL/BLOCKED,
  důležitost a pozorované/očekávané chování. Zápis je admin+CSRF, omezený enumy a
  délkami, chráněný proti symlinku a zamčený; lokální JSON je ignorován Gitem.
  Markdown export je určen k ruční kontrole a vědomému commitu bez automaticky
  načtených hesel či osobních dat. Browser potvrdil save/reload/souhrn a návrat na
  čistých 0/10. Kořenový `CLAUDE.md` už neoznačuje aplikaci jako submodule Velocoty
  a nový `docs/CURRENT_STATE.md` dává Coworku krátký aktuální vstup. Plná sada je
  358/3156, syntaxe 388 souborů, migrace 39/39 a Composer audit 0. Produkce se
  nezměnila. Další konkrétní akce: vlastník nebo Cowork projde A01–A10 a uloží
  výsledky; blokující a důležité připomínky se potom vypořádají.

  Předchozí dokončená akce: `ef5ec21` přijímá jediný živě potvrzený technický nález
  nezávislé Cowork revize. CI má nový samostatný job s MariaDB 11.4 a `pdo_mysql`,
  který spouští existující child-access a KIS transition/idempotency smoke skripty na
  izolovaných testovacích databázích. Oba smokes prošly také lokálně. Cowork report je
  zachován v `docs/AUDIT-M2-AI-SIMULACE.md`, ale jeho úvod obsahuje validační dodatek:
  tvrzení o zastaralém handoffu, chybějícím detailu/klubové ceně a login chybách byla
  vyvrácena proti skutečnému stromu. Plná sada je 356/3142, syntaxe 386 souborů,
  migrace 39/39 a Composer audit 0. Produkce ani vzdálený Git se nezměnily. Další
  konkrétní akce zůstává závěrečná vlastníkova brána M2.6.

  Předchozí dokončená akce: `4ce0f17` uzavírá provozní průchod A10. Browser u demo
  sportovce spojil auditované změny přístupu, objednávek, soupisek a přihlášek
  včetně A08 registrace a jejího auditovaného resetu; aktéři a důvody odpovídají
  uloženým zdrojům. Nalezený auditní šum opravil seed: demo heslo sportovce resetuje
  jen při skutečné změně hashe. Dva po sobě jdoucí běhy zachovaly počet historických
  resetů 26 → 26. Plná sada je 355/3135, syntaxe 386 souborů, migrace 39/39 a Composer
  audit 0. Produkce ani vzdálený Git se nezměnily. Další konkrétní akce je závěrečná
  vlastníkova brána M2.6 a sepsání jeho připomínek.

  Předchozí dokončená akce: `6ae75c1` uzavírá A08 – přihlášení oprávněného dítěte na
  událost cílenou na dvě překrývající se soupisky. Rodičovské UI nabídlo dítě právě
  jednou a browser vytvořil jedinou potvrzenou přihlášku. Databáze současně doložila,
  že podmínku splnilo přes U15 i dráhovou soupisku. Seed nyní aktivní A08 přihlášku
  demo rodiče nemaže, ale auditovaně stornuje, takže je další průchod čistý a historie
  zůstává zachovaná. Plná sada je 355/3133, syntaxe 386 souborů, migrace 39/39 a
  Composer audit 0. Produkce ani vzdálený Git se nezměnily. Další konkrétní akce je
  A10 a potom závěrečná brána M2.6.

  Předchozí dokončená akce: `03774db` uzavírá A07 – plánovaný trénink ze soupisek,
  skutečnou docházku a sportovní přehled. Běžný trenér může zaevidovat pouze vlastní
  plán, hlavní trenér libovolný dostupný plán a datum nelze proti plánu změnit.
  Historický snapshot cílových soupisek a očekávaných členů se atomicky kopíruje ke
  skutečnému tréninku, zatímco docházku trenér vždy ručně potvrdí. Localhost průvodce
  rozlišuje přítomné podle plánu, chybějící a neočekávané. Browser vytvořil trénink
  528 z plánu 2, potvrdil počty 1/1/0/0 a stejný trénink zobrazil omezenému účtu
  sportovce. Následný seed zachoval historii a připravil čistý plán 3. Plná sada je
  354/3130, syntaxe 384 souborů, migrace 39/39 a Composer audit 0. Produkce ani
  vzdálený Git se nezměnily. Další konkrétní akce je A08 a potom A10 a závěrečná brána.

  Předchozí dokončená akce: `e67eed8` přidává klubové ceny e-shopu podle soupisek.
  Veřejnost vždy vidí veřejnou cenu a výzvu k přihlášení. Přihlášený účet získá
  nejvýhodnější cenu z aktuálních soupisek svých schválených osob; konkrétní cena
  produktu má uvnitř jedné soupisky přednost před procentní nebo pevnou slevou
  kategorie. Administrace je auditovaná, košík i kupón používají stejnou serverovou
  cenu a checkout ukládá neměnný snapshot. Změna pravidla zneplatní starý fingerprint.
  Localhost seed připravil desetiprocentní demo cenu produktu 1 pro `LOCALHOST U15
  2026`. HTTP průchod potvrdil veřejnou výzvu, rodičovskou klubovou cenu i admin
  přehled. Plná sada je 353/3120, syntaxe 383 souborů, migrace 39/39 a Composer audit
  0. Produkce ani vzdálený Git se nezměnily. Další konkrétní akce zůstává A07 –
  propojení plánované soupisky se skutečnou docházkou a sportovním přehledem.

  Předchozí dokončená akce: `dde0f3e` uzavírá provozní průchod A06. Nový
  localhost-only admin průvodce ukáže v jednom náhledu věkový postup U15→U17,
  přenos dráhové disciplíny a U13→U15 se zachovanou individuální výjimkou.
  Souhrnný i dílčí fingerprint se před zápisem znovu ověřují. Browser provedl
  tři auditní běhy: 3 přesuny, 2 výjimky, právě jedno aktivní členství v každém
  cíli a po dokončení žádné další tlačítko zápisu. Následný seed odstranil pouze
  syntetické A06 běhy a vrátil všechny tři kroky do čekajícího náhledu, takže A07
  začíná na čistých datech. Plná sada je 349/3081, lint 379, migrace 38/38 a audit
  0. Produkce ani vzdálený Git se nezměnily. Další konkrétní akce: A07 – propojení
  plánované soupisky se skutečnou docházkou a sportovním přehledem.

  Předchozí dokončená akce: `efa1ca8` zpřístupňuje veřejný portál bez registrace a master účet
  používá jeden login pro trenérskou Evidenci i zákaznický e-shop. Veřejné jsou
  katalog, detail produktu, kroužky/události, velodrom, rezervační kalendář a nový
  bezpečný rozvrh zveřejněných tréninků; košík, přihláška a rezervace vyžadují
  přihlášení. Veřejný rozvrh nevybírá osoby, docházku, interní popis ani poznámky.
  Trenérský login propojí nebo vytvoří právě jeden zákaznický profil, veřejný login
  umí stejnou trenérskou roli obnovit a reset hesla aktualizuje obě role. Localhost
  master `localhost-admin` je propojen bez duplicity. Browser potvrdil košík,
  objednávky, kroužky i návrat do trenérského dashboardu bez druhého loginu.
  Plná sada je 346/3063, lint 375, migrace 38/38 a Composer audit 0. Produkce ani
  vzdálený Git se nezměnily. Další konkrétní akce: provozní scénář A06.

  Předchozí dokončená akce: `8647bce` uzavírá provozní průchod A05. Reset seedu
  ponechá jedinou kanonickou identitu přechodu a bezpečně archivuje starý syntetický
  duplikát včetně jeho aktivních vazeb. Browser prošel preview, věkovou kontrolu,
  povinný důvod a auditovaný přechod stejné osoby do U17 2027. Nový náhled nad už
  aktivním cílem je nyní pravdivý no-op bez dalšího členství nebo auditního běhu.
  Demo je znovu vrácené před přechod: 1 osoba, 1 zdrojový kroužek, 0 cílových U17.
  Zaměřená sada prošla 9/80, plná 338/3025, lint 367, migrace 37/37 a audit 0.
  Produkce ani vzdálený Git se nezměnily.

  Předchozí dokončená akce: `18deb9c` uzavírá provozní průchod A02. Omezený účet
  sportovce má souhrn vlastních tréninků, soupisek, událostí a plateb, návrat na
  společnou homepage a srozumitelné české stavy, typy událostí i datumy. Browser
  potvrdil, že podvržené `sportovec_id=999999` nezmění zobrazenou osobu a homepage
  zachová sportovní režim bez trenérských práv. Zaměřená sada prošla 11/73, plná
  337/3014, first-party lint 367 souborů, migrace 37/37 a Composer audit je 0.
  Produkce ani vzdálený Git se nezměnily.

  Předchozí dokončená akce: `25830e1` přidává společnou homepage sloučeného projektu.
  Nepřihlášený návštěvník má na kořenovém `index.php` přímé cesty do e-shopu,
  kroužků/událostí, velodromu, rodinného účtu, sportovního účtu a trenérské Evidence.
  Rodičovská a sportovní relace mají své bezpečně oddělené přímé vstupy. Přihlášený
  trenér si zachovává celý původní dashboard, ale nahoře má čtyři rychlé volby:
  zadat trénink, KIS/soupisky, objednávky a veřejný portál. Veřejný HTTP průchod je
  200 se správným titulkem; přihlášený browser ověřil všechny čtyři zkratky a
  konzole byla bez chyby. Plná sada prošla 336/3004 a Composer audit je 0.
  Produkce ani vzdálený Git se nezměnily.

  Předchozí dokončená akce: druhý browser řez M2.6 proběhl bez potřeby změny kódu.
  Placená událost odmítla duplicitní aktivní přihlášku; její objednávka
  `KP2608047D5C1C6050` po stornu uvolnila kapacitu na 3/3 a samostatná vratka
  `LOCALHOST-M26-EVENT-REFUND` uzavřela platbu. Bezplatný velodrom po stornu vrátil
  kapacitu 3/3. Placený velodrom `KP260804813B7DE01C` držel kapacitu 0/1 již před
  platbou, po syntetické úhradě se potvrdil, storno ji vrátilo na 1/1 a vratka
  `LOCALHOST-M26-VELO-REFUND` uzavřela platbu. Zboží `KP26080452226EF4BA` rezervovalo
  sklad 157/MOD2 z 2 na 1 a nezaplacené storno jej vrátilo přesně na 2. Browser
  konzole byla bez chyb a MariaDB potvrdila všechny koncové stavy. Produkce ani
  vzdálený Git se nezměnily.

  Předchozí dokončená akce: `4090bdc` opravuje regresi nalezenou prvním browser
  průchodem M2.6. Bezpečnostní rotace dříve změnila odvozený A05 hash, seed vytvořil
  další osobu a následně narazil na unikátní login sportovce. A05 se nyní hledá
  stabilním interním localhost e-mailem, nový veřejný token zůstává kryptograficky
  náhodný a sportovní login upřednostní svého stávajícího vlastníka. Staré seedované
  guardian vazby A05 se auditovaně odvolají, protože A05 je administrační scénář,
  ne třetí dítě rodiče z A01. Seed proběhl dvakrát se stejnými ID. Browser potvrdil
  A01 se dvěma dětmi a A03+A04: objednávka `KP2608040ECDA87D7D`, QR NEPLATIT,
  auditovaná syntetická platba a právě jedna aktivní účast i školní soupiska.
  Plná sada před závěrečným seed cleanupem byla 334/2977; finální zaměřený test
  prošel 2/25. Produkce ani vzdálený Git se nezměnily.

  Předchozí dokončená akce: `fdbe30c` zahajuje M2.6 čerstvou ověřenou localhost
  zálohou mimo webroot. Ownership kontrakt `.8` nově zahrnuje také
  `password_reset_tokens`; manifest potvrzuje 125 tabulek a 2 triggery a SHA-256
  souboru `evidence_2026-08-04_104915_2404dfc4.sql.gz` je
  `a7382f999126595fbbabffc99c7f5e926c0a134600fcf8659f167c949a0174a9`.
  Zaměřený backup kontrakt prošel 2 testy / 41 assertions. Produkce ani vzdálený
  Git se nezměnily.

  Předchozí dokončená akce: `29f6029` uzavírá tři zbývající MEDIUM body externí
  revize. Placená událost nyní rozlišuje měnu varianty a akce a po změně varianty
  na jinou měnu selže uzavřeně. CSV export odmítne neplatné UTF-8 dříve, než by
  mohlo obejít neutralizaci tabulkových vzorců. Fio parser přijímá oficiální
  `RRRR-MM-DD+02:00`, prosté datum i kompatibilní epoch formát a neplatné datum
  odmítá. Plná sada je 333/2971, 367 PHP lintů, migrace 37/37 a Composer audit 0.
  Produkce ani vzdálený Git se nezměnily.

  Předchozí dokončená akce: M2.5 technicky uzavírá `7c1490e`. Rodičovský účet i
  samostatný účet sportovce mají společnou žádost o obnovu bez enumerace. Token je
  v DB pouze hashovaný, platí 60 minut, lze jej použít právě jednou a nová žádost
  zneplatní starší. U sportovce se odkaz doručí pouze ověřenému `self`/`guardian`
  účtu a vazba se znovu kontroluje při použití; zrušený guardian proto reset
  nedokončí. Změna hesla zvýší `session_version` a odhlásí staré relace. Trenérská
  role a tabulka oprávnění se nově načítají při každém požadavku, takže odebrání
  platí okamžitě a chyba autorizace selže uzavřeně. Localhost má 37/37 migrací,
  plná sada 330/2944, 367 PHP lintů, Composer audit 0 a HTTP smoke formulářů 200
  se stejnou odpovědí pro neznámý účet. `edc6c62` navíc pouze na localhostu po
  platné žádosti zobrazí testovací fragmentový odkaz, takže lze celý reset projít
  i bez lokálního mail serveru; produkční větev jej nikdy nezobrazuje. Produkce
  ani vzdálený Git se nezměnily.

  Předchozí dokončená akce: bezpečnostní audit legacy vrstvy byl porovnán s živým
  HEAD a potvrzené nálezy uzavřel `65a0433`. Všechny veřejné odkazy sportovců mají
  po jednorázové rotaci kryptograficky náhodný token, plaintext hesla trenérů byla
  na localhostu převedena na moderní hash a aplikace je už nepřijímá. Doplněny jsou
  CSRF hranice, oprávnění downloadu, redakce auditních dat, obecné chybové odpovědi,
  bezpečné inline JSON a Apache defense-in-depth. Root `.htaccess` už existoval;
  opačné tvrzení auditu bylo zastaralé. Katalog je 36/36, plná sada 323/2903,
  361 PHP souborů bez chyby, Composer audit bez nálezu a localhost HTTP ověřil
  hlavičky i 403 v upload cestě. Produkce ani vzdálený Git se nezměnily. Podrobný
  záznam je v [bezpečnostním auditu](../security-audit-2026-08-04.md). Navazující
  `b8ecdaa` uzavírá také enumeraci účtu při veřejné registraci: nový i existující
  e-mail dostává stejnou neutrální odpověď. Plná sada je po tomto řezu 324/2907.

  Předchozí dokončená akce: revize Claude Code byla porovnána s aktuálním HEAD.
  Její H1 vycházel ze starého snapshotu a je již uzavřený: backup kontrakt `.7`
  obsahuje `kis_import_source_artifacts`. Potvrzené H2, H3 a M1 uzavírá `b5f3f3f`:
  stornovaný a refundovaný kroužek lze znovu koupit se zachováním historie a
  databázově jedinou aktivní účastí, legacy rezervace velodromu bez
  `active_token` stále zabírá kapacitu a jeden košík nemůže překročit kapacitu
  placené události více dětmi. Nová migrace prošla reálnou localhost MariaDB;
  katalog je 35/35, plná sada 318/2774, 356 PHP souborů bez chyby a dependency
  audit bez nálezu. Produkce ani vzdálený Git se nezměnily.

  Předchozí dokončená akce: M2.4b přidalo explicitní neměnný rozsah kupónů pro
  zboží, kroužky, placené události a velodrom. Výchozí i migrační pravidlo je
  pouze zboží; služba se nezlevní bez samostatného zaškrtnutí administrátorem.
  Minimum a sleva se počítají ze způsobilého mezisoučtu a redemption ukládá jeho
  hodnotu i snapshot rozsahu. Browser potvrdil zamítnutí `LOCAL10` na samotný
  kroužek, 10% slevu na zboží, následný úklid košíku a čitelný administrační
  formulář. Katalog je 34/34, plná sada 315/2707, 355 PHP souborů bez chyby a
  dependency audit bez nálezu.

  Předchozí dokončená akce: M2.4a přidalo bezpečný zákaznický detail produktu.
  Katalog nyní sdružuje varianty, detail vrací jen aktivní publikované zboží a
  používá pouze schválený veřejný název a souhrn. Importovaný nedůvěryhodný HTML
  popis se nezobrazuje. Obrázky musí být validní HTTPS URL bez přihlašovacích
  údajů a načítají se bez referreru. Kroužková nabídka ukazuje jen svou aktivní
  variantu a nepřebírá zavádějící obrázek původního importovaného zboží.
  Přihlášený localhost browser prošel katalog, detail dvou variant, vložení do
  košíku, jeho vyčištění a samostatný detail kroužku. Katalog migrací je 33/33,
  plná sada 313/2664, 354 PHP souborů bez chyby a dependency audit bez nálezu.

  Předchozí dokončená akce: M2.3a přidalo neměnný archiv zdrojových KIS souborů
  mimo webroot. Metadata obsahují verzi kontraktu, SHA-256, velikost a storage
  key; preview běh může uložit kanonický manifest s fingerprintem. CLI je pouze
  localhost, defaultně dry-run a zápis vyžaduje `--confirm-archive`. Syntetický
  MariaDB průchod vytvořil právě jeden soubor a jeden DB řádek; druhé spuštění
  vrátilo `created_file=false` a `created_record=false`. Katalog je 33/33,
  plná sada 308/2635, 350 PHP souborů bez chyby a dependency audit bez nálezů.

  Předchozí dokončená akce: M2.1 přidalo auditovaný administrační CSV export
  účastníků jedné klubové akce. Endpoint je admin-only, POST+CSRF, soubor používá
  kontrakt `m2.event-participants.v1`, neutralizuje tabulkové vzorce a neobsahuje
  hesla ani celé texty souhlasů. Export zapisuje do auditu pouze počet řádků a
  rozpad stavů. Plná sada má 303 testů / 2603 assertions, Composer audit
  0 advisories; ověřeno bylo 345 PHP souborů a localhost browser potvrdil
  tlačítko, stažení a
  audit `export_participants`.

  Předchozí dokončená akce: placená klubová událost cílená na více soupisek je
  zapojená do standardního shop order/payment lifecycle. Checkout ukládá cenu,
  příjemce, souhlas, storno a eligibility snapshot; stav `payment_pending` drží
  kapacitu, ruční úhrada aktivuje přihlášku právě jednou a storno/expirace ji
  auditovaně uvolní. Localhost browser prošel objednávku `NEPLATIT`, QR, ruční
  syntetickou úhradu a potvrzenou účast. Seed nyní obsahuje také placenou
  událost, U13 → U15 → U17, dráhu, silnici a rollover výjimku. Katalog migrací
  je 32/32, plná sada 299/2547, 339 first-party PHP lintů a Composer audit bez
  advisories. Ověřená post-change záloha má 123 tabulek a 2 triggery.

  Historie: K3 má administrační frontu neodeslaných e-mailů a
  auditované bezpečné ruční retry (`4cd0eae`). K4 má první checkout přihlášeného
  účtu pro aktivní `goods`: košík, ochranu proti tiché změně ceny, neměnný
  snapshot, idempotentní objednávku, transakční rezervaci skladu, bankovní
  předpis s lokálním QR/SPD a ruční auditované potvrzení platby (`5750dd0`).
  Navazující `1763531` přidává auditované storno, transakční a právě-jednou
  vrácení skladu, bezpečný stav `refund_required` a tok příprava → připraveno →
  osobní výdej. Celkem 186/1339 testů, 257 PHP lintů, Composer audit bez
  advisories a izolovaný MariaDB průchod dokončením i stornem prošly.
  `f8b12a4` doplňuje databázově vlastnicky filtrovaný zákaznický seznam a detail
  a auditované jednorázové potvrzení celé bankovní vratky `refunded` s referencí.
  Celkem 187/1376 testů, 259 PHP lintů a izolovaný MariaDB tok vratky i IDOR
  hranice prošly. `135803a` přidává neměnné pevné/procentní kupóny, platnost,
  minimum, celkový limit, audit aktivace, serverový checkout a snapshot slevy.
  Celkem 190/1425 testů, 262 PHP lintů a izolovaný MariaDB limit i finanční
  snapshot prošly. `f23a332` přidává read-only Fio import v shadow režimu,
  neměnnou deduplikaci a návrhy podle přesného VS, částky a měny. Návrh nemění
  stav platby; 195/1457 testů, 270 PHP lintů a izolovaný MariaDB smoke prošly.
  Automatické potvrzení platby a Stripe zatím nevznikají.
  `fa452fd` zprovozňuje kompletní localhost demo a první KIS funkční vrstvu:
  sezóny, týmy, auditovanou soupisku, snapshot UCI ID a historické odebrání bez
  mazání. Lokálně prošel zákaznický login, dvě osoby, kroužek, košík, kupón,
  skladová ochrana, objednávka s QR i administrace soupisky. Celkem 198/1483
  testů a 276 PHP lintů prošlo; produkce se nezměnila. První paralelní přírůstek
  M1 doplnil rodinný read-only přehled, beneficiary snapshot košíku/objednávky,
  stabilní série soupisek, školní a kalendářní sezóny a bezpečný náhled rolloveru.
  Druhé kolo doplnilo M:N most plánovaných tréninků na soupisky, programy/nabídky/
  účasti a cílení událostí na více soupisek. Třetí kolo doplnilo automatickou
  aktivaci programu po úhradě a jeho storno/refund, auditované a souběžně bezpečné
  provedení rolloveru a veřejný self profil s kapacitní rezervací velodromu.
  M1.8 doplnilo localhost-only rozcestník A01–A10 s bezpečným resetem seedu,
  samostatný revokovatelný sportovní login a placený velodrom přes standardní
  shop order/payment/SPD/QR. Browser prošel A02 a celý A09 lifecycle: košík,
  objednávka, QR, paid aktivace, storno, refund a uvolnění kapacity. Migration
  catalog je lokálně 30/30. M1.9 doplnilo preview-first průvodce A05, jednotnou
  read-only osu A10 a bezpečnou expiraci nezaplacených objednávek. Browser ověřil
  deterministický A05 náhled, A10 události i admin expirace; reálná localhost
  programová objednávka expirovala právě jednou. Katalog je lokálně 31/31.
  Produkční workflow, kód ani DB se nezměnily.
- Další přesná akce: provést závěrečnou vlastníkovu bránu M2.6 a sepsat připomínky
  jako chyba, UX úprava nebo nový požadavek. Bez tohoto lidského průchodu se M2.6
  neoznačuje jako 100 %.
  Paralelně lze po dodání anonymizovaného vzorku potvrdit aliasy již implementovaného
  M2.3d field kontraktu a přijmout M2.3e paritní report nad skutečným formátem.
  Produkční
  Secret/config, deploy, Fio a ostrý import zůstávají samostatně blokované do
  pozdějšího výslovného rozhodnutí.

## Stav etap podle akceptačních bran

Procenta jsou řídicí odhad dokončených akceptačních bodů, nikoliv podíl řádků
kódu. Produkční aktivace se do nich nepočítá jako hotová bez živého důkazu.

| Etapa | Hotovo | Zbývá zejména |
|---|---:|---|
| K1 – katalog a publikace | 98 % | vlastníkova kontrola pravidel cen a finální publikace ostrého katalogu |
| K2 – účty, osoby a rodič–dítě | 92 % | self-service obnova sportovního hesla a potvrzení bezpečného párování na anonymizovaném finálním exportu |
| K3 – akce a přihlášky | 92 % | produktová kontrola exportu, rozhodnutí o ručních změnách čekací listiny a produkční UX |
| K4 – objednávky a platby | 97 % | úplný vlastnický průchod všech typů košíku včetně klubové ceny, ověřit Fio shadow návrhy a samostatně schválit automatické potvrzení/Stripe |
| K5 – náhrada starého KIS | 98 % | A05–A08 a A10 technicky i browserem uzavřeny; zbývá vlastníkova brána, finální jednorázový import a cutover |

## Rychlý aktuální stav pro další task

| Oblast | Implementováno | Otevřeno / blokováno |
|---|---|---|
| M1 integrovaný prototyp | technicky dokončen; Evidence, shop, rodina, programy, soupisky, události a velodrom mají localhost řezy | vlastníkova prohlídka A01–A10 |
| M2.1 akce | admin CSV export v `1b4d9e1`, kontrakt v1, CSRF, formula ochrana a audit | produktová kontrola sloupců v tabulkovém programu |
| KIS migrace | parser, matcher, raw archiv, fingerprintovaný preview, M2.3c sandbox, M2.3d stabilní KIS ID a M2.3e uložená parita | potvrdit aliasy na reprezentativním exportu, navrhnout cílové členské předpisy a až potom ostrý cutover |
| e-shop | katalog, košík, objednávka, QR/převod, kupóny, sklad, výdej, storno/refund, program/událost/velodrom | detail/obrázky, pravidla slev pro služby, automatické platby a produkční aktivace |
| přístup | rodič–děti, sportovní účet, bezpečné session, revokace, limiter, jednorázové tokeny, samoobslužný reset a okamžitá oprávnění | produkční pepper, migrace a doručování reset e-mailu |
| finance | ruční převod a read-only Fio shadow návrhy | wallet D-009, Fio auto-confirm, Stripe, cash top-up a kombinované platby |

Aktuální M2 autorita je
[10 – Milník M2](10-milnik-m2-provozni-pilot.md). Nezávislá read-only revize má
kanonický prompt v
[PROMPT-CLAUDE-CODE-REVIZE-M2.md](PROMPT-CLAUDE-CODE-REVIZE-M2.md).

## Povinný kontrakt údržby tohoto souboru

`SESSION_HANDOFF.md` je jediný stručný živý stav projektu pro navázání dalšího
řídicího tasku. Board zůstává historickým programovým ledgerem a jednotlivé
milníkové dokumenty popisují cíle; nesmějí nahrazovat tento aktuální snapshot.

Řídicí task aktualizuje tento soubor:

1. po každém přijatém implementačním nebo integračním commitu,
2. po změně migrací, testovacích výsledků, brány nebo blokátoru,
3. před předáním do jiného tasku a před ukončením delší pracovní session,
4. po externí revizi, pokud její nález změní pořadí nebo stav M2.

Povinně zapíše datum, poslední implementační SHA, vlastněné dirty soubory,
aktuální migrace/testy/lint, dokončený výsledek, otevřené riziko a právě jednu
další konkrétní akci. Hodnoty GitHubu a produkce musí být označené jako historické,
dokud nejsou znovu živě ověřené. Soubor nesmí tvrdit 100 % jen podle množství
kódu; stav se mění pouze podle akceptačního důkazu.

## Pořadí autority

1. živě ověřený Git, lokální DB, GitHub a produkční důkaz,
2. schválená rozhodnutí v [02 – Zadání a rozhodnutí](02-zadani-a-rozhodnuti.md),
3. aktuální stav v [06 – Program board](06-program-board.md),
4. tento handoff,
5. předchozí chat a memory pouze jako vodítko, ne jako aktuální důkaz.

Při rozporu se nejprve zastaví mutace, zaznamená drift a aktualizuje board.

## Poslední známý důkazní snapshot

| Oblast | Poslední známá hodnota | Ověřeno | Zdroj | Obnovit při resume |
|---|---|---|---|---|
| Git remote | `https://github.com/KovoPraha/evidenceTreninku.git` | 2026-08-01 | `git remote -v` | ano |
| `origin/main` | `7f48b50b128b65f7340442ba33bfb9c66c27703a` | 2026-08-02 | fetch + rev-parse | ano |
| integrační branch | lokální `main`; M1 `9c4c3e1`, M2.3a `851288c`, M2.3b `26076ba`, M2.3c `5caa850`, M2.3d `2bcb346`, M2.3e `95693a2`, M2.4a `b4898f1`, M2.4b `838793d`, M2.4c `b5f3f3f`, M2.6 backup `fdbe30c`, seed/browser `4090bdc`, A02 `18deb9c`, A05 `8647bce`, A06 `dde0f3e`, A07 `03774db`, A08 `6ae75c1`, A10 `4ce0f17`, feedback `875c9e3` | 2026-08-04 | lokální Git, M2.3e před docs commitem ahead 7 / behind 0 | ano |
| PR / remote CI | PR #1 až #6 merged; finální main run `30743017895` success | 2026-08-02 | GitHub | ano |
| ochranný snapshot | `d2b3c56` / `codex/pre-reconcile-20260801` | 2026-08-01 | lokální Git | před mazáním větve |
| GitHub deploy | run `30668559417`, success | 2026-08-01 | GitHub CLI | ano |
| produkční runtime | schema `2.20.2`, PHP `8.2.32` | 2026-07-31 | deploy post-check | před releasem |
| lokální schema | legacy `2.20.2` + 42/42 číslovaných migrací; `sportovci.kis_external_id`, field report i cutover parity snapshot jsou aplikované; reset tokeny jsou hashované a indexed; 255/255 profilových tokenů je silných a 44/44 hesel trenérů hashovaných | 2026-08-04 | migration apply/check + živá localhost MariaDB metadata | ano |
| testy | 379/3332; browser M2.3e run #9 report 3 blokátory a sandbox 2/2→0/2, oba MariaDB smokes, 401 first-party syntaxí, 43/43 a audit 0 | 2026-08-04 | PHP 8.2.12 / PHPUnit 11.5.56 + localhost browser/MariaDB | ano |
| Shoptet staging | 241 produktů / 807 variant převedeno do draft katalogu; druhé spuštění bez duplicity, 1 bookable rental, 3 free varianty, 0 veřejně aktivních | 2026-08-02 | reálný XML + SQLite/MariaDB | před veřejnou aktivací |
| dependencies | PhpSpreadsheet 5.8.1, Guzzle 7.15.2, PSR-7 2.13.0, endroid/qr-code 6.0.9; 0 advisories | 2026-08-03 | Composer audit | ano |
| lokální backup drill | M2.6 `evidence_2026-08-04_104915_2404dfc4.sql.gz`: 125 tabulek, 2 triggery, SHA-256 `a7382f999126595fbbabffc99c7f5e926c0a134600fcf8659f167c949a0174a9`; ownership kontrakt `.8` včetně `password_reset_tokens` | 2026-08-04 | XAMPP DB backup mimo webroot + ověřený manifest/hash | zopakovat s produkčním artefaktem až před autorizovaným deployem |
| GitHub host key | Secret `SSH_KNOWN_HOSTS` dosud chybí | 2026-08-01 | pouze seznam názvů Secrets | ano; hodnotu nikdy nevypisovat |

Lokální DB, GitHub run a produkční runtime jsou tři různé zdroje. Výsledek
jednoho se nesmí vydávat za důkaz druhého.

## Povinný resume audit bez mutací

- [ ] přečíst tento soubor, README a dokumenty 02, 04, 05 a 06,
- [ ] spustit `git status --porcelain=v2 --branch`,
- [ ] zaznamenat `git rev-parse HEAD`, upstream, remote URL a výchozí branch,
- [ ] provést `git fetch --prune origin` a porovnat `HEAD...origin/main`,
- [ ] inventarizovat každý modified/untracked soubor a jeho vlastníka,
- [ ] ověřit GitHub repo, workflow a poslední run,
- [ ] ověřovat jen názvy/přítomnost Secrets, nikdy jejich hodnoty,
- [ ] ověřit lokální DB identitu a schema read-only dotazy,
- [ ] označit každý fakt jako lokální, GitHub, produkční nebo neověřený,
- [ ] před změnou stručně nahlásit drift proti handoffu.

Do dokončení inventury nepoužívat pull, stash, clean, reset, rebase, bulk stage
ani jinou operaci, která by mohla skrýt nebo přepsat cizí práci.

## Preservation ledger

| Povrch | Původ | Stav | Povolená akce |
|---|---|---|---|
| starý podrobný deploy manuál | práce před `58ec8ec` | zachován v `d2b3c56`, zastaralý | pouze ručně vytěžit stále platné rady |
| `overit_config.php` | dočasná produkční diagnostika | zachován v `d2b3c56` | neobnovovat a nenasazovat |
| přesné kopie nových deploy souborů | budoucí obsah originu v tehdy starém worktree | nyní kanonicky v `origin/main` | používat upstream verzi |
| programová dokumentace | řídicí task | obnovena na `codex/foundation` | řídicí task je jediný editor boardu/handoffu |

Snapshot branch se nemaže, dokud nebude ručně potvrzeno, že žádná zachovaná rada
nebo soubor už není potřebný. Snapshot není určen k merge ani pushnutí.

## Aktivní práce a vlastnictví

| ID | Stav | Base/branch | Vlastník | Povolený rozsah | Blokuje |
|---|---|---|---|---|---|
| W0-A | accepted | `58ec8ec` | řídicí task | Git reconciliation | nic |
| W0-B | accepted | `7106930` | KIS worker | matcher + integration testy | release do main |
| W0-C | partial accepted | auth commity v `main`, včetně PR #5 | security worker | hesla, dependencies, lifecycle, revokace, limiter, tokeny, logout | permission cache, reset hesla a produkční password apply |
| W0-D | accepted | `0d50584`, run `30718185103` | test worker | Composer dev, tests, CI/deploy gate | nic |
| W0-E | code accepted / production pending | `664745e`, `cd0c0e1`, PR #6 | integrační vlastník | migrace + deploy hardening | `SSH_KNOWN_HOSTS`, produkční pepper, autorizovaný první deploy |
| W0-F | waiting decision | dokumentace | produkt/ekonom | D-004 až D-011 | identity a wallet |
| W0-G | partial accepted | `98ff91d`, `168d132`, `8f0cbe8`, `699ddd4`, `d99a79e`, `2ba0782`, `f0370a3`, `3845eab`, `b77f8c3`, `8c374a4`, `d32fc08`, `5500927`, `4ef5690`, `88f5b97`, `e5fcaa0`, `a949c38`, `fb5e137`, `4cd0eae`, `5750dd0`, `1763531`, `f8b12a4`, `135803a` | KIS/shop workeři | Shoptet katalog, K2, bezplatný K3 a K4 checkout/storno/výdej/refund/kupóny | reálný KIS vzorek a automatické platby |

Řídicí task aktualizuje IDs, větve, commity a testy po každém worker handoffu.

## Integrační fronta

| Pořadí | Práce | Podmínka přijetí |
|---:|---|---|
| 1 | foundation dokumentace `6c7956c` | přijato |
| 2 | W0-D test baseline `0d50584` | přijato; pozdější main CI běhy zelené |
| 3 | W0-B KIS safety `7106930` | přijato; 10 testů / 61 assertions v tomto kroku |
| 4 | W0-C passwords `2ed5278` | přijato; produkční `--apply` výslovně neprovedeno |
| 5 | W0-C dependencies `1a9af03` | přijato; 0 advisories, celkem 16 testů / 75 assertions |
| 6 | W0-E migrations `664745e` | přijato lokálně; 29 testů / 119 assertions |
| 7 | W0-E deploy/backup `cd0c0e1` | přijato lokálně; restore drill prošel, GitHub/produkce čekají |
| 8 | W0-G Shoptet katalog `98ff91d` | přijato lokálně; provisional CSV-only dry-run, 2 produkty / 3 varianty |
| 9 | W0-G KIS parity `0537adf` | přijato lokálně; matcher/preview hardening + syntetický read-only kontrakt |
| 10 | Audit fixes `82eac98`, `b4207be` | přijato lokálně; jedno-snapshot CSV a pouze silné KIS identity signály |
| 11 | W0-G realistic KIS `168d132` | přijato lokálně; 10 opaque scénářů, 9 blockerů, missing nikdy nearchivuje |
| 12 | W0-G shop matrix `8f0cbe8` | přijato lokálně; varianty, kolize SKU, exact money/VAT a scope hranice |
| 13 | W0-C session lifecycle `dfce1ea`, `af49d57` | přijato lokálně; 102 entrypointů, bezpečné cookie/timeout/rotace/logout |
| 14 | W0-C DB revokace + rate limit `a3c2239`, `10c2cf9`, `9977b4d` | přijato; 103/569, MariaDB apply/runtime, dva finální audity ACCEPT a run `30740138748` success |
| 15 | W0-C one-time tokeny + booking lock `4b683ee` | přijato v PR #5; 110/626, SQLite + MariaDB, finální re-audit ACCEPT |
| 16 | W0-E release ordering `7361e48` | přijato v PR #6; 112/647, migrace před aktivací PHP, run `30743017895` success |
| 17 | Shoptet XML a katalogový staging `699ddd4`, `d99a79e`, `2ba0782`, `f0370a3` | přijato lokálně; reálných 241/807, 1 ruční kontrola, idempotentní SQLite/MariaDB staging, 127/762 |
| 18 | Auditovaný shop admin + KIS plán `3845eab` | přijato lokálně; admin-only, CSRF, audit změn, reálný MariaDB review smoke, 131/787 |
| 19 | Kanonický draft katalog `b77f8c3` | přijato lokálně; single-use transakce, collision rollback, reálných 241/807, 3 free varianty, 134/826 |
| 20 | K2 účet–osoba `8c374a4` | přijato lokálně; admin-only `self`/`guardian`, ověřený e-mail, revoke + audit, SQLite/MariaDB, 138/859 |
| 21 | K2 veřejný claim `d32fc08` | přijato lokálně; bez enumerace osob, admin review, atomické schválení, idempotence a limit, SQLite/MariaDB, 145/907 |
| 22 | Řízená aktivace katalogu `5500927` | přijato lokálně; pouze `goods`, explicitní potvrzení, plain-text snapshot, audit, K3 fail-closed, SQLite/MariaDB, 151/956 |
| 23 | K3 pracovní akce `4ef5690` | přijato lokálně; cílová skupina, termíny, kapacita, cena, produktová vazba a audit; bez přihlášek/KIS, SQLite/MariaDB, 158/1008 |
| 24 | K3 bezplatný kroužek `88f5b97` | přijato lokálně; schválené dítě z K2, transakční kapacita, unikátní přihláška, storno a audit; bez objednávky/platby/soupisky/KIS, SQLite/MariaDB, 165/1058 |
| 25 | K3 souhlasy a storno `e5fcaa0` | přijato lokálně; neměnný registr verzí, snapshot přihlášky a fail-closed deadline; SQLite/MariaDB, 166/1085 |
| 26 | K3 čekací listina `a949c38` | přijato lokálně; FIFO, K2 recheck, atomické povýšení a skutečný souběh dvou MariaDB procesů; 168/1122 |
| 27 | K3 oznámení + správní storno `fb5e137` | přijato lokálně; transakční outbox, retry/karanténa, CRON worker a auditovaná výjimka po termínu; SQLite/MariaDB, 173/1192 |
| 28 | K3 provozní fronta `4cd0eae` | přijato lokálně; admin přehled, CSRF, auditované retry a zákaz zásahu do `processing`/`sent`; SQLite/MariaDB |
| 29 | K4 bankovní checkout `5750dd0` | přijato lokálně; serverová cena + fingerprint, snapshot, idempotence, skladový pohyb, QR/SPD a ruční paid; SQLite/MariaDB, 182/1287 |
| 30 | K4 fulfillment/storno `1763531` | přijato lokálně; transakční restock právě jednou, `refund_required`, příprava/výdej a audit; SQLite/MariaDB, 186/1339 |
| 31 | K4 zákaznické objednávky/vratky `f8b12a4` | přijato lokálně; account-scoped seznam/detail, `refunded`, bankovní reference a idempotentní audit; SQLite/MariaDB, 187/1376 |
| 32 | K4 kupóny `135803a` | přijato lokálně; fixed/percentage, platnost/minimum/limit, audit, fingerprint, snapshot a souběžně bezpečný counter; SQLite/MariaDB, 190/1425 |
| 33 | K4 Fio shadow import `f23a332` | přijato lokálně; pouze GET `/periods`, kontrola IBAN, minimální bankovní data, neměnná deduplikace a návrh VS + částka + měna bez změny platby; SQLite/MariaDB, 195/1457 |
| 34 | Localhost demo + KIS sezóny/týmy/soupisky `fa452fd` | přijato lokálně; fail-closed seed, zákaznický/admin browser průchod, historická soupiska a audit bez externího KIS zápisu; SQLite/MariaDB/browser, 198/1483 |
| 35 | M1 rodinný sportovní portál `d42a30c` | přijato lokálně; guardian/self rozsah, IDOR ochrana, soupisky, akce a docházka |
| 36 | M1 příjemce shop položky `82b42a3` | přijato lokálně; autorizovaný beneficiary na košíku a neměnný snapshot v objednávce, běžné zboží smí mít NULL |
| 37 | M1 série a rollover preview `18b81a3` | přijato lokálně; školní/kalendářní sezony, čtyři politiky a read-only preview bez zápisu |
| 38 | Integrace prvního paralelního přírůstku | migration catalog 22/22, plná sada 213/1583, audit 0 advisories a browser průchod rodiče i KIS admina |
| 39 | M1.3 tréninkový most `9f7e531` | M:N vazba plánů/soupisek, snapshot a deduplikace; SQLite/MariaDB/browser |
| 40 | M1.4 kroužkové programy `e6fad8e` | nabídka, období, účast, kapacita, beneficiary, audit a ruční bezpečná aktivace |
| 41 | M1.6 cílené události `218bfd3` | M:N cíle, transakční oprávnění, snapshot a kompatibilita s čekací listinou |
| 42 | Integrace druhého paralelního přírůstku | migration catalog 25/25, plná sada 236/1779, audit 0 advisories, 296 lintů, backup 109/1 a browser smoke |
| 43 | M1.4 automatický lifecycle `15cd57b`, `589d79b` | paid aktivace, storno/refund, audit a bezpečné pořadí zámků; SQLite/MariaDB |
| 44 | M1.5 provedení rolloveru `8cf6774`, `94ab4a2` | fingerprint, výjimky, auditovaný přesun, idempotence a dvouprocesový concurrency smoke |
| 45 | M1.7 veřejný profil a velodrom `9d8cee5` | self profil, DOB, kapacita/exkluzivita, storno/rebook, ruční paid confirm; SQLite/MariaDB/browser |
| 46 | Integrace třetího paralelního přírůstku | migration catalog 28/28, plná sada 258/2029, audit 0 advisories, 310 lintů, backup 117/2 a autentizovaný browser smoke |
| 47 | M1.8 akceptační hub `bf3caa2` | A01–A10, localhost-only, admin+CSRF reset, bez zobrazení hesel |
| 48 | M1.8 sportovní přístup `91b105f` | 1:1 účet sportovce, revokace, izolovaná session a DB-scoped read-only přehled |
| 49 | M1.8 placený velodrom `664f855` | standardní shop order/payment/QR, snapshot, paid/cancel/refund lifecycle |
| 50 | Integrace M1.8 | 30/30, 281/2251, 327 lintů, backup 121/2 `.5`, opakovaný seed a browser A02/A09/reset |
| 51 | M1.9 A05 `d3e7e96` | preview-first přechod stejné osoby, kontrola věku, stale fingerprint, audit, idempotence a MariaDB smoke |
| 52 | M1.9 A10 `e363d86` | admin-only read-only osa devíti auditních zdrojů, omezené stránkování a pravdivě chybějící metadata |
| 53 | M1.9 expirace `c50e572` | dry-run default, explicitní admin potvrzení, společný storno lifecycle a payment-first pořadí zámků |
| 54 | Integrace M1.9 | 31/31, 298/2435, audit 0, deterministický A05 seed, browser A05/A10/expiry a backup 121/2 `.5` |
| 55 | Dokončení technické části M1 | placená soupiska/událost přes shop lifecycle, 32/32, 299/2547, 339 lintů, audit 0, browser paid flow a backup 123/2 `.6` |
| 56 | M2.1 provozní export účastníků | admin POST+CSRF, CSV kontrakt v1, izolace akce, formula neutralizace, audit; 303/2603, 345 PHP souborů a localhost browser |
| 57 | M2.3a KIS raw archiv `851288c` | externí storage, hash/size/contract metadata, preview manifest, localhost dry-run a explicit write, idempotence; 33/33, 308/2635, 350 lintů, backup 124/2 `.7` |
| 58 | M2.4a detail produktu `b4898f1` | seskupené varianty, schválené veřejné texty, sklad, bezpečné HTTPS obrázky a oddělený detail kroužku; 33/33, 313/2664, 354 lintů a browser průchod |
| 59 | M2.4b rozsah kupónů `838793d` | explicitní neměnná maska čtyř kategorií, výchozí pouze zboží, způsobilý mezisoučet a redemption snapshot; 34/34, 315/2707, 355 lintů a browser |
| 60 | M2.4c hardening `b5f3f3f` | H1 revize byl již zastaralý; H2/H3/M1 uzavřeny migrací aktivní účasti, legacy kapacitou a součtem dětí v košíku; 35/35, 318/2774, 356 lintů, audit 0 |
| 61 | bezpečnost legacy `65a0433` | náhodné profilové tokeny, hash všech hesel trenérů, CSRF/oprávnění/redakce/chybové odpovědi a Apache defense-in-depth; 36/36, 323/2903, 361 lintů, audit 0 |
| 62 | registrace bez enumerace `b8ecdaa` | nový i existující e-mail má stejnou veřejnou odpověď; 324/2907 |
| 63 | M2.5 recovery a oprávnění `7c1490e` | rodič i sportovec, guardian recheck, single-use token, session revokace a request-scoped permission refresh; 37/37, 330/2944, 367 lintů |
| 64 | localhost reset UX `edc6c62` | lokální testovací fragmentový odkaz bez mail serveru, produkce pouze e-mail; 330/2946 |
| 65 | MEDIUM kompatibilita `29f6029` | oddělená měna varianty/akce, fail-closed UTF-8 CSV a oficiální Fio datum; 333/2971, 367 lintů, 37/37 |
| 66 | M2.6 backup `fdbe30c` | ownership kontrakt `.8` včetně reset tokenů; ověřená záloha mimo webroot 125 tabulek / 2 triggery, zaměřený kontrakt 2/41 |
| 67 | M2.6 seed/browser `4090bdc` | stabilní A05 identita po rotaci tokenů, seed 2× se stejnými ID, A01 dvě děti a A03+A04 od QR přes syntetickou platbu po účast a soupisku |
| 68 | M2.6 lifecycle bez změny kódu | událost a velodrom od kapacitního hold přes platbu po storno/refund; skladové zboží 2→1→2; DB/UI shoda a konzole bez chyb |
| 69 | M2.2 homepage `25830e1` | společný veřejný vstup e-shop/rodina/sportovec/trenér a čtyři rychlé trenérské volby; HTTP 200, browser bez chyb, 336/3004 |
| 70 | M2.2/A02 `18deb9c` | sportovní souhrn, české stavy/datumy, společná homepage a browser IDOR důkaz jediné identity; 337/3014, 367 lintů, 37/37, audit 0 |
| 71 | M2.6/A05 `8647bce` | kanonický demo sportovec, auditovaný přechod a pravdivý no-op nového náhledu; reset zpět před přechod; 338/3025, 367 lintů, 37/37, audit 0 |
| 72 | M2.2/M2.5 veřejnost + jednotný účet `efa1ca8` | veřejný read-only katalog a rozvrhy, akce po loginu, jedna trenérská/zákaznická identita a společný reset; 346/3063, 375 lintů, 38/38, audit 0 |
| 73 | M2.6/A06 `dde0f3e` | společný preview/fingerprint, 3 auditní běhy, 3 přesuny, 2 zachované výjimky, žádná duplicita a reset zpět před A06; 349/3081, 379 lintů, 38/38, audit 0 |
| 74 | M2.4d klubové ceny `e67eed8` | veřejná cena a login výzva, ceny aktivních soupisek rodiny, kategorie/procento/pevná sleva, přesná cena produktu, audit a checkout snapshot; 353/3120, 383 parse, 39/39, audit 0 |
| 75 | M2.6/A07 `03774db` | vlastnická/datová ochrana plánu, neměnná kopie očekávání ke skutečnému tréninku, localhost porovnání docházky a sportovní přehled; browser 1/1/0/0, 354/3130, 384 parse, 39/39, audit 0 |
| 76 | M2.6/A08 `6ae75c1` | jedna přihláška oprávněného dítěte přes dvě cílové soupisky, UI bez duplicit a auditovaný opakovatelný seed reset; 355/3133, 386 parse, 39/39, audit 0 |
| 77 | M2.6/A10 `4ce0f17` | browser auditní osy objednávka/soupiska/přihláška/přístup, pravdiví aktéři a důvody, seed bez falešných password-reset událostí; 355/3135, 386 parse, 39/39, audit 0 |
| 78 | Cowork validace + MariaDB CI `ef5ec21` | zastaralé bridge závěry označeny, potvrzený CI nedostatek opraven jobem MariaDB 11.4 pro dva smoke skripty; 356/3142, 386 parse, 39/39, audit 0 |
| 79 | feedback + aktuální AI kontext `875c9e3` | admin+CSRF výsledky A01–A10, zamčený ignorovaný JSON, Markdown export, opravený samostatný rozsah projektu v CLAUDE a nový CURRENT_STATE; 358/3156, 388 parse, 39/39, audit 0 |
| 80 | M2.3b preview integrita `26076ba` | migrace preview reportu, úplná klasifikace, stabilní non-PII fingerprint, UI JSON export a idempotentní localhost seed; browser #7 2/2/0, 364/3197, 392 parse, 40/40, audit 0 |
| 81 | M2.3c sandbox promote/rollback `5caa850` | localhost admin+CSRF+fingerprint, transakční idempotentní promote, audit a rollback i při driftu bez kanonických zápisů; browser 2/2→0/2, 369/3254, 396 parse, 41/41, audit 0 |
| 82 | M2.3d field/external-ID kontrakt `2bcb346` | stabilní interní KIS ID oddělené od UCI, spojení tří exportů, archivace všech zdrojů, non-PII fingerprint a fail-closed execute; browser #8 2/2→0/2, legacy #7 blokován, 377/3308, 398 parse, 42/42, audit 0 |
| 83 | M2.3e cutover parity `95693a2` | uložené porovnání osob, členství, soupisek a platebních signálů bez PII; run #9 má 2 nové osoby + chybějící payment-prescription target, sandbox 2/2→0/2, 379/3332, 401 parse, 43/43, audit 0 |

PR #1 až #6 jsou sloučené do `main`. Produkční migrace, migrace hesel ani deploy
se v této session nespustily. Pořadí migrace před aktivací PHP je opravené;
workflow stále nesmí být spuštěno bez ověřeného `SSH_KNOWN_HOSTS`, externího
`AUTH_RATE_LIMIT_PEPPER` a výslovného souhlasu vlastníka.

Shop přírůstek zůstává lokálním a dosud nenasazeným krokem: má staging,
kontrolní UI, kanonický katalog, řízenou aktivaci zboží, K2 vazby účtů na
sportovce, checkout, objednávku, ručně potvrzenou bankovní platbu, storno,
fulfillment, refundaci, kupóny, beneficiary snapshot položky a první programovou
účast/prodloužení včetně automatického navázání na paid/storno/refund. Placený
velodrom používá stejnou shop objednávku, QR, paid/storno/refund lifecycle a
kapacitní rezervaci; chybí expirace pending objednávek, automatická platba,
Stripe, produkční import a externí KIS zápis.

Session increment používá vlastní cookie `EVIDENCESESSID`; jeho budoucí deploy
jednorázově odhlásí existující relace. DB revokace a atomický HMAC rate limit jsou
lokálně hotové, ale deploy je fail-closed bez externího `AUTH_RATE_LIMIT_PEPPER`.
Evidence je samostatný produkt. `VELOCOTA_INTEGRATION` musí zůstat `false`;
širší provozní nebo doménová integrace s Velocotou není plánovaná. Výhledově lze
samostatným rozhodnutím řešit pouze sdílenou/federovanou identitu uživatele.
Expirované hashované tokeny a POST+CSRF logout jsou hotové v `main`.
Permission cache, reset hesla a produkční ověření zůstávají otevřené, takže
W0-C ani F0 nejsou uzavřené.

## Stop podmínky

- nejasný vlastník dirty souboru,
- rozpor živého stavu s boardem,
- dvě větve mění stejný sdílený soubor nebo migraci,
- chybějící produktové rozhodnutí,
- požadavek na produkční změnu bez výslovného pověření,
- riziko vypsání tajemství nebo osobních údajů,
- worker nedodal base/commit SHA, testy a rozsah změn.

## Checklist před ukončením řídicího tasku

- [ ] aktualizovat stavy workerů a integrační frontu,
- [ ] měnit board pouze podle ověřeného důkazu,
- [ ] zapsat přesné SHA a všechny dirty soubory,
- [ ] uvést nedokončený proces nebo čekající rozhodnutí,
- [ ] zapsat jednu další konkrétní akci,
- [ ] aktualizovat čas tohoto handoffu,
- [ ] ověřit, že prompt nového řídicího tasku stále odpovídá procesu.
