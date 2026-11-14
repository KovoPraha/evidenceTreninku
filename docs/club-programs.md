# Kroužkové programy, období a účasti (M1.4)

## UX01 — doplnění celoroční nabídky ke stávajícím skupinám (8. 11. 2026)

Stav: KÓD SLOUČENÝ A NASAZENÝ; [PR #395](https://github.com/KovoPraha/evidenceTreninku/pull/395) vytvořil `1c828fbf`, [deploy 42492821311](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42492821311) na stejném SHA skončil SUCCESS. Vlastník: vývoj; ceny a zveřejnění konkrétních nabídek schvaluje vedení klubu. Pověření k nasazení kódu neuvádělo částky ani pokyn vytvořit ostré nabídky. Podrobný dokumentační [PR #401](https://github.com/KovoPraha/evidenceTreninku/pull/401) je k 12. 11. otevřený. Položka [registru](WORK_PLAN_CURRENT.md#ux01--celoroční-nabídka-stávajícího-kroužku).

Produkční čtecí kontrola 8. 11. ukázala zveřejněné Točňáky úterý, čtvrtek a 2× týdně, každý s prvním i druhým pololetím a bez celoroční nabídky. Starší společná nabídka 1× týdně je skrytá/uzavřená. Běžné administrační založení další varianty nyní vyžaduje ruční název, kód, SKU, platnost, prodejní okno, cenu, kapacitu a věk pro každou skupinu zvlášť.

Cíl: ze stránky Kroužky otevřít přehled způsobilých skupin stejného školního roku, vybrat jednu nebo více, zadat schválenou celoroční cenu pro každou z nich a před zápisem vidět přesný souhrn. Systém odvodí datum platnosti z obou pololetí, kopíruje jejich společnou soupisku/produkt/kapacitu/věk, vytvoří unikátní kód a SKU a použije stávající nákupní a platební cestu. Zápis vybraných skupin je atomický. Existující celoroční nabídku nelze vytvořit podruhé. Nejednoznačné nebo rozdílné pololetní údaje se nesmějí tiše převzít. Samotný rozvrh tréninků ani starší nabídky se nemění.

Akceptace: čitelný souhrn a výslovné potvrzení, jedna i více skupin, stejné cílové soupisky, žádná duplicita při opakování/souběhu, rollback celé dávky při chybě, žádné přebírání ceny odhadem; lokální syntetické testy a PHP lint. Produkční UAT a zveřejnění skutečných cen jsou samostatné kroky. [Rozhodnutí](DECISION_LOG.md#d2026-11-08-01).

Realizace: v `club_program_offers_admin.php` je vstup „Doplnit celý rok“. `club_program_year_offer_admin.php` ukáže způsobilé skupiny, ceny pololetí a přesný náhled před potvrzením. `includes/club_program_year_offer.php` odvozuje společná pole, blokuje nejednoznačnosti, znovu ověřuje otisk pololetí a klubových podmínek po zamčení programů a založí všechny vybrané varianty v jediné transakci přes stávající `clubProgramCreatePaymentOption()`. Schválenou celoroční cenu musí správce zadat sám. Nová databázová tabulka ani migrace nejsou potřeba.

Lokální ověření 8. 11.: syntetické integrační testy kroužků a sdílené kapacity PASS (19 testů, 180 assertions), včetně shodné soupisky/produktu, odmítnutí opakování a rollbacku celé dávky; PHP lint pěti dotčených souborů PASS, samostatný php-cs-fixer `--dry-run` PASS, limit délky řádků a `git diff --check` PASS, `tools/check-current-docs.php` PASS. Kompletní `tools/check-php-style.php` lokálně skončil pouze na chybějícím `vendor/bin/php-cs-fixer` v napojených závislostech worktree; kontrolu stylu provede CI. Přihlášené UAT nového formuláře, objednávka, platba a ostré zveřejnění NOT RUN.

CI pro kódový commit `56f1db82` v [PR #395](https://github.com/KovoPraha/evidenceTreninku/pull/395): 11/11 PASS dne 8. 11. 2026, včetně PHPStan a changed-file style, PHPUnit na PHP 8.2, obou MariaDB 10.3/11.4 integračních smoke a čtecích profilů, docs a CodeQL. První běh PHPStanu odhalil čtyři statické chyby v nových souborech, opravené v `56f1db82`; následný úplný běh prošel. Produkční vydání proběhlo 8. 11.; reálný nákup a přihlášené UAT zůstávají NOT RUN.

Release 8. 11. proběhl na přesném `1c828fbf`. Dalším krokem je přihlášené čtecí ověření nového administračního formuláře. Nevytvářet objednávky ani skutečné celoroční varianty bez schválených cen. Podrobný protokol je připraven v otevřeném [PR #401](https://github.com/KovoPraha/evidenceTreninku/pull/401); do jeho převzetí je zde potvrzen stav merge a úspěch workflow.

## Doménový model

- `club_programs` je stabilní služba, například Cyklistická škola. Nemění se při novém pololetí.
- `club_program_offers` je konkrétní nabízené období. Váže program, školní sezonu, cílovou soupisku a právě jednu produktovou variantu e-shopu.
- `club_program_enrollments` je historická účast jednoho sportovce v jednom období. Uchovává přesnou platnost a zdrojovou objednávkovou položku.
- `club_program_enrollment_events` je audit aktivace. Historie se při prodloužení nepřepisuje.

## Bezpečnost aktivace

Aktivace nepřijímá jen ID objednávky. `clubProgramActivateOrderItem()` v jedné transakci ověří:

1. objednávková položka patří přihlášenému účtu a mapuje se na konkrétní nabídku;
2. položka má množství 1 a beneficiary snapshot;
3. účet má k beneficiary právě teď schválenou vazbu `self` nebo `guardian`;
4. objednávka není stornovaná a nabídka je aktivní;
5. nenulová položka má potvrzený stav `paid`; nulová položka je aktivovatelná bez platby;
6. kapacita ještě není vyčerpána.

MySQL dotaz zamyká řádek nabídky přes `FOR UPDATE`. Kontrola kapacity a vložení účasti proto používají stejný serializační bod. Unikátní klíče na `(offer_id,sportovec_id)` a `source_order_item_id` tvoří druhou idempotentní ochranu.

Po aktivaci se sportovec právě jednou přidá do cílové školní soupisky se zdrojem `shop`. Existující aktivní členství se zachová. Ukončené členství se automaticky neobnovuje a vyžaduje kontrolu správce. Chyba vrátí zpět účast, členství i oba audity.

## Uživatelský postup

1. Správce připraví školní sezonu a soupisku v `kis_rosters_admin.php`.
2. V `club_programs_admin.php` vytvoří stabilní program a nabídku období. Vybere existující publikovanou variantu e-shopu, termíny, kapacitu a cílovou soupisku.
3. Rodič v `booking/eshop.php` přidá položku a zvolí jedno ze schválených dětí. Běžné fyzické zboží zůstává kompatibilní bez beneficiary.
4. Potvrzení bankovní platby aktivuje účast automaticky a idempotentně ve stejné transakci jako změnu stavů platby a objednávky. Ruční tlačítko zůstává jako bezpečný fallback a pro bezplatné/admin scénáře.
5. Stejná stránka zachovává přehled minulých i současných období.

## Životní cyklus platby, storna a vratky

- `shopOrderAdminConfirmBankPayment()` po přechodu objednávky do `processing/paid` aktivuje všechny její programové položky ještě před commitem. Neplatný beneficiary nebo vyčerpaná kapacita vrátí zpět i potvrzení platby; nevznikne tedy stav zaplaceno bez odpovídající účasti.
- Opakované potvrzení již zaplacené objednávky službu spustí znovu, ale unikátní zdrojová položka a účast způsobí nulový další zápis.
- `shopOrderAdminCancel()` označí související aktivní účasti jako `cancelled` a uloží `ended_at`, důvod a administrátora. Jde o auditovaný konec, nikoliv smazání historie.
- Systémem vytvořené členství v soupisce se ukončí pouze tehdy, když pro stejného sportovce a tým nezůstává jiná aktivní programová účast. Ruční členství se automaticky neukončuje.
- `shopOrderAdminConfirmRefund()` účast znovu nemění. Pouze fail-closed ověří, že stornovaná objednávka už nemá aktivní účast, a potom auditovaně potvrdí skutečné odeslání vratky.

Stavy `shop_orders`, `payments`, `club_program_enrollments` a `club_roster_members` zůstávají samostatné. Přechody jsou koordinované transakcí, ale žádný stav se neodvozuje přepisem jiného záznamu.

## Aktuální provozní model

- Veřejný rozcestník kroužků zobrazuje publikované programové nabídky a jejich
  obrázky; nákup pokračuje na kanonický produkt v KIS.
- Program lze koupit po přihlášení nebo rychlou registrací sportovce. Bezplatná
  nabídka se aktivuje bez platební brány, placená až kanonickým potvrzením
  platby.
- Jeden program může mít celoroční, první pololetní a druhou pololetní nabídku.
  Nabídky mohou používat různé ceny, ale stejnou cílovou soupisku. Kapacita se
  počítá podle unikátních sportovců, takže stejné dítě ve více obdobích stejné
  skupiny se nezapočítá dvakrát.
- Únorová nabídka druhého pololetí může být zveřejněná současně s první a má
  vlastní registrační a platnostní okno.
- Automatické storno řeší objednávkový lifecycle. Samostatné administrátorské
  ukončení účasti bez storna objednávky zatím vyžaduje řízený zásah správce.


## P11-PADMIN — dávkové čtení správy programů (13. 11. 2026)

NASAZENO / technická přejímka PASS. Původní plán před implementací: Vývoj/Codex na pokyn Marka pokračovat
v další měřené optimalizaci, zachovat WIP, aktualizovat plán/handoff a po
úplném PASS commit/merge/chráněný deploy. Větev codex/program-admin-read-20261113,
worktree C:/Users/Marek/Documents/Codex/evidencePavel-program-admin-read-20261113.
Base c5a4ca3b18265f79d740f4be7b25491132aac887; ověřený runtime b2ca9f4b,
deploy 43447880903 SUCCESS. Původní fio/d1221f35 a kis_rosters_admin.php
SHA256 7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 zachovat.

Výběrová diagnostika ASSET12: 0/2/12/100 nabídek = 1/43/253/2101 SQL
na SQLite in-memory. Aktuální clubProgramAdminOffers pro každou nabídku
opakuje metadata schématu, fyzické soupisky, kapacitu a účinné podmínky.
Stávající ClubProgramReadContext už má dávky podmínek a fyzických soupisek,
ale veřejný warmup filtruje aktivní/prodejné nabídky; správa musí zobrazit
i koncepty, uzavřené, skončené a neprodejné nabídky. Pouhé použití veřejné
variantové projekce by měnilo výsledky a není přijaté.

Zvolený řez: samostatný reader jen pro administrační seznam, přesně původní
výběrový SQL/pořadí/PDO typy. Nový lokální čtecí kontext načte metadata jednou,
fyzické soupisky a poslední aktivní podmínky v omezených dávkách pro všechny
zobrazené nabídky bez filtru prodejnosti. Kapacitu nadále počítá stejná
kanonická funkce nad čerstvými daty; její zápisové a zamykající cesty zůstanou
referencí. Dva kapacitní dotazy na nabídku tímto řezem nezmizí — jeho cíl
je odstranit většinu z 21 opakovaných SQL na nabídku bez duplikace složité
kapacitní sémantiky. Žádná persistentní cache, změna práv, schématu či writerů.

Akceptace:
1. Strict shoda kompletních řádků/typů/pořadí s nezměněným scalar API:
0/2/12/100/401 nabídek, hranice dávky 80/400, aktivní/neaktivní programy a
nabídky, deduplikace osob mezi překryvy, více fyzických soupisek, rezervace
pending/paid/expired/rejected/cancelled, anonymní placené přihlášky, null/0
kapacita, primární fallback, chybějící registry/volitelné sloupce, poslední
aktivní override a programový fallback včetně prázdného/VZOR textu.
2. Nové volání musí po změně dat znovu číst aktuální stav, lock API nesmí
čerpat tento kontext. Původní public/scalar/checkout funkce beze změny.
3. MariaDB 10.3/11.4 párově: stejné fixture, query cache off, 4 zahřátí a
17 střídavých měřených párů, čas neinstrumentovaně; SQL/řádky a paměť zvlášť.
Požadovat významné snížení SQL i měřený přínos na 100+ nabídkách.
4. Celé HTTP před/po: přehled a editory, odmítnuté role a anonymní, prázdný
přehled; přesná HTML/hlavičková shoda kromě nonce, všech tabulek checksumy,
bezpečný úklid. Skutečný Apache/Chrome desktop/mobile light/dark, shodné
pixely/formuláře, GET čas; CSS je už ASSET12 a není novou úsporou.
5. Cílené a celé CI, styl/syntaxe/docs/diff PASS; aktuální main, shodný
testovaný a sloučený strom. Chráněný deploy s UAT/import false, záloha,
config/migrace/VS/index/HTTP/úklid, anonymní živé GET před/po. Přihlášené
produkční UAT evidovat zvlášť. Současný blokátor náhradních lekcí zachovat.

[Průběžné výsledky](verification/program-admin-read-20261113/RESULTS.md).
Další krok: minimální reader/context warmup a samostatná strict parita.


### PADMIN — implementace a místní checkpoint

Nový includes/club_program_admin_read.php ponechává přesný výběrový SQL
a zbytek zpracování původního API. Mění jen napojení lokálního kontextu;
původní includes/club_program.php je byte-shodný s baseline. Stránka má
jedno nové require a volání readeru. ClubProgramReadContext má oddělený
warmAdminOffers pro všechny stavové nabídky, včetně dávkových chybějících
podmínek; veřejný warmup a zamykající API se nemění.

Místní parita 5/75 a rozšířená sada 27/196 PASS. Diagnostický SQLite počet
SQL: 0 nabídek 1→1, 2:43→14, 12:253→34, 100:2101→212, 401:8422→823.
Jde o výběrový SQL důkaz, ne nativní či produkční latenci.
Syntaxe PHP/JS/Python, changed-file styl a composer validate PASS;
statická analýza ještě běží. Nativní čas/paměť a celé HTTP/Chrome čekají na CI.

Profil má vlastní syntetický plně migrovaný server, před/po 0/2/12/100/401,
čas na obyčejném PDO a instrumentaci zvlášť. HTTP a Chrome mají 100 nabídek
s uzavřenými/koncepty/expirací, různými podmínkami a sdílenými fyzickými
soupiskami; základní skutečné placené účasti fixture zůstávají zachované.
Prázdný HTTP scénář má oddělený setup a přesnou obnovu/checksumy. Historický
ASSET12 profil zachovává všechny CSS/HTML kontroly a dovoluje jen přesné
dvouřádkové přepojení stránky na tento nový reader; jeho staré měření není
novým izolovaným CSS výsledkem. Žádná produkční změna zatím neproběhla.


První native checkpoint PR #477/670a88d2, běh 43472823698:
strict nativní řádky/typy/pořadí, 0/2/12/100/401 a checksumy/obnova PASS
na obou MariaDB. 100 nabídek: 10.3 643,741→87,283 ms,
11.4 894,890→108,677 ms, SQL 2228→262. 401 nabídek:
2610,006→333,034 / 3615,241→425,548 ms, SQL 8922→1023.
Peak PHP při401 3038080→3810408 B (+772328 B); úspora je v SQL/čase,
nikoli v paměti. Malý 2-row případ má více načtených metadat, ale méně
SQL a nižší naměřený čas. Jde o syntetický průběžný výsledek, ne SLA.

Celý profil nebyl přijat: samostatný HTTP fixture neměl přímý require
definice CLUB_PROGRAM_TERM_PURPOSES. Doplněna explicitní závislost fixture;
produkční reader beze změny. Vlastní schéma/účty/config/porty uklizené.
Nové celé CI/HTTP/Chrome měření nutné před merge. Místní composer analyse
ukončil časový limit300s bez dokončení; Linux PHPStan/styl běhu43472823347
PASS. Lokální 27/196 a ostatní předchozí kontroly zůstávají PASS.


Iterace2 / 82d79cd8, celý párový běh43473894094 PASS na obou MariaDB:
17 HTTP scénářů, 355 checksumů a cleanup; Chrome176měřených+48warmup,
čtyři pixelově shodné dvojice, stejné formuláře a geometrie. HTTP přehledu
100nabídek 10.3 621,960→69,115 ms, 11.4 903,470→121,619 ms.
Desktop cold FCP1024→236ms, mobile1036→240ms. CSS/payload beze změny.
Nativní401 3198,187→320,916 / 5023,155→609,070ms; SQL8922→1023.

Před finálním přijetím zpřesněna pouze testová evidence: fixture_offers
se nyní odečítá před čtením (100/0) a restored_fixture_offers po obnově
(100), s výslovným invariantem velikosti. Předchozí pole se počítalo až
po obnově; prázdný scénář a jeho přesná obnova už byly provedené správně.
Produkční reader ani stránka se nemění. Aktuální celý CI pár se zopakuje.


### PADMIN — dokončení

**P11-PADMIN NASAZENO:** [plán](club-programs.md#p11-padmin--dávkové-čtení-správy-programů-13-11-2026), [výsledky](verification/program-admin-read-20261113/RESULTS.md). #477/b4d5cfe2, [deploy 43478690110](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43478690110) SUCCESS, 15 CI. CI GET100 nabídek 727→91 / 890→118 ms, přesná shoda; přihlášené produkční UAT NOT RUN.

Finální párová nativní/HTTP/Chrome přejímka aktuálního headu PASS: po17HTTP
scénářích/355shodných tabulkách na MariaDB10.3/11.4, 0/2/12/100/401 strict
nativní pole/typy/pořadí, 176měřených+48warmup navigací a4pixelové dvojice.
Všechny časy, SQL/řádky, špičkový přírůstek paměti a meze jsou ve výsledcích.
Metadata/podmínky/soupisky už nejsou opakované per offer; dva kapacitní SQL
na fyzickou soupisku/nabídku zůstávají další samostatný kandidát.
Původní scalar/public/checkout/locking API beze změny. Nové volání čte čerstvá
data, neexistuje persistentní cache práv ani nová migrace. Lokální27/196 PASS.
Celý testovaný/sloučený strom 94bcfbec6d53fed9b1775dbdd8aa5e28a263d8ca shodný; deploy 43478690110
2472/56686, záloha394/2,
config/migrace/VS/index/HTTP/úklid PASS. Import/UAT false; veřejná živá
kontrola před/po a CSS hash PASS, přihlášené produkční UAT NOT RUN.

Výsledkové předání: codex/program-admin-read-evidence-20261113 ve stejném
worktree; doklady vlastním dokumentačním PR, po jeho přijetí v main.
Kvůli dokladům znovu nenasazovat. Původní fio WIP i ostatní prototypy
zachované. Celé M1.4/UX01 ani blokátor náhradních lekcí tímto nejsou uzavřené.
Další krok: nativně profilovat zbylé kapacitní roundtripy a případný objemový
správní seznam, případně zbývající CSS duplicity, vždy samostatně podle přínosu.


## P11-PCAP — dávkové kapacity správy programů (14. 11. 2026)

ROZPRACOVÁNO před implementací. Vlastník Vývoj/Codex; oprávnění vlastníka
„pokračuj ve stejném duchu stále dále“ navazuje na implementaci, paritu,
před/po, commit/merge a chráněné nasazení po PASS. Base b0d2368d08f861444883a99a11e1f4205a2d0263,
runtime b4d5cfe2 / deploy43478690110 SUCCESS ověřený živě v GitHub Actions.
Větev codex/program-capacity-read-20261114, worktree
C:/Users/Marek/Documents/Codex/evidencePavel-program-capacity-read-20261114.
Původní fio/d1221f35, kis_rosters_admin.php SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 zachovat.
Souběžné #474/#476 a ostatní PR nejsou součástí tohoto řezu.

PADMIN doložil zbylých262/1023 SQL na100/401 nabídkách: dvě kapacitní
čtení na každou fyzickou soupisku/nabídku. Je to konkrétní kandidát z analýzy,
časový přínos dalšího řezu zatím NOT RUN. Žádná produkční latence z těchto
syntetických hodnot neplyne.

Varianta: nový read-only pomocník pouze pro správní reader, omezené UNION ALL
dávky nejvýše80 cílů /480 parametrů pro aktivní účasti i čekající objednávky.
Přesné původní predikáty překryvu, fyzických soupisek, plateb, identity,
vypršení, zamítnutí a již přijatých položek; stejný pure výpočet
clubProgramCapacityFromRows. Žádné obecné načtení celých tabulek do PHP.
Aktivní osoby deduplikovat, rezervace spotřebovat po cílech proudově.
Pořadí fyzických soupisek, rozhodnutí při stejné obsazenosti i min dostupnost
zůstávají stejné. Včetně null/0 kapacity a legacy fallbacků.

Alternativa měnit canonical checkout/locking SQL by rozšířila riziko;
pro tento samostatný řez zůstává includes/club_program.php byte-shodný.
Duplikované výběrové predikáty v read-only pomocníku vyžadují diferenciální
testy proti canonical API; společný pure výpočet se neduplikuje. Žádná
persistentní cache, změna práv, schématu, indexů ani writerů. Každé nové
volání vytváří čerstvý kontext; zamykající a veřejné API zůstává původní.
Časové porovnání rezervací zachovává >= a Europe/Prague; přesné hraniční
testy použijí stejný explicitní okamžik pro scalar i dávku.

Akceptace a postup:
1. Implementace a strict srovnání celých řádků/typů/pořadí s PADMIN i
canonical scalar API; 0/2/12/79/80/81/100/401, více soupisek, překryvy
na hranách, tie, všichni žadatelé/objednávky a absence volitelných sloupců.
Kontrola čerstvého GET po změně, proudu rezervací a pevných SQL dávek.
2. Native MariaDB10.3/11.4 query cache off, obyčejné PDO pro čas/peak,
instrumentace odděleně; 4warmup/17střídavých párů, SQL/řádky/peak,
všechny checksumy a obnova. Baseline je již optimalizovaný PADMIN,
nikoliv původní scalar. Požadovat měřený přínos100+ a zásadní pokles SQL.
3. Celé HTTP17 scénářů včetně prázdného, přesné HTML/hlavičky kromě nonce,
Chrome desktop/mobile light/dark cold/warm a čtyři pixelové páry.
Zachovat existující CSS regresní profily, žádnou starou úsporu nevydávat za novou.
4. Cílené/úplné CI, styl/syntaxe/docs/diff PASS, aktuální main a shodný
testovaný/sloučený strom; chráněný deploy se zálohou/config/migracemi/VS/
indexem/HTTP/úklidem, import/UAT false. Anonymní GET před/po; skutečná
produkční přihlášená přejímka zvlášť, bez mutací objednávek/osob/rolí.
5. Výsledky a release do verzovaných dokladů, CURRENT/handoff průběžně.
Nedoložený přínos znamená nevydat optimalizaci a zapsat zjištění.

[Průběžné výsledky](verification/program-capacity-read-20261114/RESULTS.md).
Další krok implementace a místní diferenciální testy.


### PCAP — místní implementace a přejímka

Nový ClubProgramCapacityRead a tři řádky přepojení správního readeru.
Stránka, canonical includes/club_program.php, pure kapacitní výpočet
i ClubProgramReadContext byte-shodné s baseline. Každá dávka má nejvýše80
UNION cílů; aktivní osoby deduplikované, rezervace řazené interním numerickým
ordinalem a předávané generátorem společnému pure výpočtu. Pořadí fyzických
soupisek a první stav při stejné obsazenosti zachované. Dotazy parametrizované,
žádné načítání celé tabulky. Dávkový reader zachycuje jeden okamžik expirace
pro celý GET, stejný predikát >= a Europe/Prague; explicitní hrana PASS.

První nový test odhalil chybějící fallback prázdné fyzické soupisky;
opraveno stejným cílem0 jako canonical API. Po opravě 7testů/210assertions
PASS a rozšířená místní sada34/406 PASS. Zahrnuje strict celé řádky/typy/
pořadí, 79/80/81/160/161/401, 2500rezervací, překryvy včetně hran,
tie/quantity/deduplikaci, nulové/neomezené kapacity, legacy sloupce/tabulky,
změny mezi GET, cizí PDO a přesnou shodu canonical SQL predikátů pro všechny
podporované varianty schématu. Původní veřejné a locking testy PASS.
SQL diagnostika SQLite proti PADMIN: 0:1→1, 2:14→12, 12:34→12,
100:212→16, 401:823→33, strict shoda. Není to nativní časové měření.
Změněné PHP PSR12/200/braces a cílený PHPStan PASS, Python/PHP syntaxe PASS.

Nový párový native/HTTP/Chrome profil porovnává přesně zmrazený PADMIN
reader z b0d2368d (jen přejmenování testové funkce); HTTP před má původní
soubor readeru, celá stránka a canonical API jsou stejné. 4warmup/17párů,
0/2/12/100/401, 17HTTP scénářů,355checksumů a čtyři pixelové páry čekají
na CI. Existující PADMIN profil zůstává regresí celkového přínosu proti
původnímu scalar API, jeho historické výsledky se nepřepisují.
Další krok nové celé CI a párový native před/po; bez PASS nevydávat.
