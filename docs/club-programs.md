# Kroužkové programy, období a účasti (M1.4)

## UX01 — doplnění celoroční nabídky ke stávajícím skupinám (8. 11. 2026)

Stav: IMPLEMENTOVÁNO VE VĚTVI, [PR #395](https://github.com/KovoPraha/evidenceTreninku/pull/395) před merge; uživatel 8. 11. výslovně požádal pokračovat a nasadit kód. Vlastník: vývoj; ceny a zveřejnění konkrétních nabídek schvaluje vedení klubu. Větev `codex/year-offer-ux-20261108` nyní zahrnuje novější `origin/main` `ab49947d`; cizí WIP zachované. Původní pověření implementovat zjednodušení neobsahovalo ostré ceny, nabídky ani platby; nové pověření se vztahuje na nasazení kódu, neuvádí částky ani pokyn zveřejnit nabídky. Položka [registru](WORK_PLAN_CURRENT.md#ux01--celoroční-nabídka-stávajícího-kroužku).

Produkční čtecí kontrola 8. 11. ukázala zveřejněné Točňáky úterý, čtvrtek a 2× týdně, každý s prvním i druhým pololetím a bez celoroční nabídky. Starší společná nabídka 1× týdně je skrytá/uzavřená. Běžné administrační založení další varianty nyní vyžaduje ruční název, kód, SKU, platnost, prodejní okno, cenu, kapacitu a věk pro každou skupinu zvlášť.

Cíl: ze stránky Kroužky otevřít přehled způsobilých skupin stejného školního roku, vybrat jednu nebo více, zadat schválenou celoroční cenu pro každou z nich a před zápisem vidět přesný souhrn. Systém odvodí datum platnosti z obou pololetí, kopíruje jejich společnou soupisku/produkt/kapacitu/věk, vytvoří unikátní kód a SKU a použije stávající nákupní a platební cestu. Zápis vybraných skupin je atomický. Existující celoroční nabídku nelze vytvořit podruhé. Nejednoznačné nebo rozdílné pololetní údaje se nesmějí tiše převzít. Samotný rozvrh tréninků ani starší nabídky se nemění.

Akceptace: čitelný souhrn a výslovné potvrzení, jedna i více skupin, stejné cílové soupisky, žádná duplicita při opakování/souběhu, rollback celé dávky při chybě, žádné přebírání ceny odhadem; lokální syntetické testy a PHP lint. Produkční UAT a zveřejnění skutečných cen jsou samostatné kroky. [Rozhodnutí](DECISION_LOG.md#d2026-11-08-01).

Realizace: v `club_program_offers_admin.php` je vstup „Doplnit celý rok“. `club_program_year_offer_admin.php` ukáže způsobilé skupiny, ceny pololetí a přesný náhled před potvrzením. `includes/club_program_year_offer.php` odvozuje společná pole, blokuje nejednoznačnosti, znovu ověřuje otisk pololetí a klubových podmínek po zamčení programů a založí všechny vybrané varianty v jediné transakci přes stávající `clubProgramCreatePaymentOption()`. Schválenou celoroční cenu musí správce zadat sám. Nová databázová tabulka ani migrace nejsou potřeba.

Lokální ověření 8. 11.: syntetické integrační testy kroužků a sdílené kapacity PASS (19 testů, 180 assertions), včetně shodné soupisky/produktu, odmítnutí opakování a rollbacku celé dávky; PHP lint pěti dotčených souborů PASS, samostatný php-cs-fixer `--dry-run` PASS, limit délky řádků a `git diff --check` PASS, `tools/check-current-docs.php` PASS. Kompletní `tools/check-php-style.php` lokálně skončil pouze na chybějícím `vendor/bin/php-cs-fixer` v napojených závislostech worktree; kontrolu stylu provede CI. Přihlášené UAT nového formuláře, objednávka, platba a ostré zveřejnění NOT RUN.

CI pro kódový commit `56f1db82` v [PR #395](https://github.com/KovoPraha/evidenceTreninku/pull/395): 11/11 PASS dne 8. 11. 2026, včetně PHPStan a changed-file style, PHPUnit na PHP 8.2, obou MariaDB 10.3/11.4 integračních smoke a čtecích profilů, docs a CodeQL. První běh PHPStanu odhalil čtyři statické chyby v nových souborech, opravené v `56f1db82`; následný úplný běh prošel. Produkční vydání a reálný nákup zůstávají NOT RUN.

Release postup po novém pověření: vyřešit konflikt s aktuálním main, znovu ověřit CI přesného PR head, sloučit PR, na přesném main commitu spustit `deploy-production.yml` s `potvrzeni=NASADIT`, `uat_schvaleno=false` a `importovat_krouzky=false`. Po úspěšném deployi ověřit veřejnou stránku a přístup k novému administračnímu formuláři pouze čtením. Nevytvářet objednávky ani skutečné celoroční varianty bez schválených cen. Nasazení s sebou může zahrnovat již sloučené změny v main; před spuštěním přesně porovnat release commit a poslední nasazený stav.

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
