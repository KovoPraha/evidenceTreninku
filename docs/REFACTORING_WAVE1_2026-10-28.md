# První vlna refaktoringu a optimalizace

Připraveno a implementováno 28. 10. 2026 (Europe/Prague).
Stav: **W1-1 až W1-3 implementovány a ověřeny; PR #209, bez merge a nasazení**.

Navazující zadání vlastníka 28. 10. 2026: „pokračuj dle plánu plně autonomně“.
Provádí se W1-1 až W1-3, místní a CI ověření a předání v PR. Produkční vydání
a merge zůstávají podle tohoto plánu samostatné rozhodnutí. Historický text
přípravy níže popisuje výchozí stav, nikoli konečný stav implementace.

## Výsledek implementace

[PR #209](https://github.com/KovoPraha/evidenceTreninku/pull/209) obsahuje cílený
detail, metadata platná pro jediné čtení a lehkou navigaci s dávkovými vazbami.
Na MariaDB při 1 000 produktech: detail **1 008 → 6 SQL, 1 000 → 1 produktový
řádek**, menu ručního zboží **9 011 → 9 SQL**, importované menu **1 011 → 7 SQL**.
Výstupy všech 12 měřených scénářů jsou shodné. Programové kapacity, termíny,
souhlasy a kontroly zápisů zůstaly v původních kanonických službách.

CI aplikačního kódu a opraveného locku na `f4bc1f63`: **1 757 testů / 35 705
assertions**, Linux symlink brána 3/3, MariaDB 10.3 i 11.4, PHP quality a
dokumentace PASS. [Snapshot](verification/refactoring-wave1-20261028/github-ci-implementation.json).
Browser/HTTP ověřil katalog, detail, rychlý nákup, zájmový program, členskou
cenu a její zánik po revokaci. [Důkazy a limity měření](verification/refactoring-wave1-20261028/RESULTS.md#implementace-a-přejímka).

Výjimka proti původnímu předpokladu beze změny locku: první CI odhalilo čtyři
nově hlášené bezpečnostní advisory pro stávající PhpSpreadsheet 5.8.1.
Samostatný commit aktualizuje pouze tento balíček na **5.10.0**, kompatibilní
s projektovým PHP 8.2; audit je čistý. Žádná nová závislost ani migrace.

## Rozhodnutí a výsledek přípravy

První vlna zrychlí veřejné čtení e-shopu a oddělí jeho dotazy od zápisového
checkoutu. Má tři malé navazující balíky: cílený detail produktu, omezený
kontext metadat pro čtení a lehčí navigaci. Zachová modulární monolit,
procedurální veřejné funkce, URL, pravidla prodeje i transakční služby.

Důvod pořadí je měření, nikoli délka souborů: detail jediného zboží dnes načte
všechny prodejné varianty katalogu a pro každou znovu ověří skladové schéma.
Navigace znovu sestavuje celý katalog a u ručně založeného zboží navíc provádí
dotazy v cyklech. Změna samotného detailu proto ještě nevyřeší celou stránku.

| Položka | Ověřený výchozí bod |
| --- | --- |
| Repozitář / základ | KovoPraha/evidenceTreninku, `fa387e5302f54ca901f46233322bc6fb92a10eb9` po `git fetch origin` |
| Větev přípravy | `codex/refactoring-wave1-preparation-20261028` |
| Worktree | `C:/Users/Marek/Documents/Codex/evidencePavel-refactoring-wave1-20261028`, mimo webroot |
| Původní checkout | `C:/xampp/htdocs/evidencePavel`, `d1221f35`, vlastní 4 / chybějících 121 commitů proti main; WIP zachováno |
| Dokumentace | Inventář všech 222 trackovaných Markdown souborů, 2 357 465 bajtů; rozsah podrobného čtení a limity v [mapě dokumentace](REFACTORING_DOCUMENTATION_2026-10-28.md) |
| PHP inventář | 611 trackovaných PHP mimo tests/migrations/output/outputs/vendor, včetně CLI/tools a zmrazeného baseline |
| Statický dluh | PHPStan level 5; 524 baseline položek / 2 413 potlačených výskytů; 61 souborů s Wiring v názvu |
| Místní testy základu | 1 748 testů / 35 544 assertions, bez selhání, 3 přeskočené Windows symlink testy |

Zadání „připrav první vlnu“ zde znamená analýzu, měření, rozsah změn a přejímací
podmínky. Příprava nemění aplikační kód, konfiguraci, DB, produkci ani vzdálené
větve. Následující PR jsou návrhy, dosud nebyly otevřeny.

## Potvrzené nálezy

| ID | Důkaz v základu | Dopad / závěr |
| --- | --- | --- |
| R1 | `includes/shop_storefront.php:71` volá v detailu `shopStorefrontCatalog()`; `includes/shop_checkout.php:52` načítá všechny prodejné varianty. | Práce detailu roste s celým katalogem. Filtrovat v SQL před načtením a vyhodnocením variant. |
| R2 | `shopCheckoutVariantIsSaleable()` na `includes/shop_checkout.php:1268` volá pro každé zboží `shopInventoryTable()`; `includes/shop_inventory.php:6` vykoná SQL `LIMIT 0`. | Dotaz na existenci schématu roste s počtem variant. Veřejné čtení může používat jeden explicitní kontext pro danou operaci. |
| R3 | `includes/shop_public_navigation.php:15` sestavuje celý katalog, pak dotazuje nabídky a klasifikaci každého produktu/varianty. `booking/produkt.php:214` vykresluje navigaci i po načtení detailu. | Zbytečné načítání obrázků a atributů pro menu, opakované dotazy a druhý průchod katalogem. |
| R4 | `includes/shop_checkout.php` má 1 291 řádků / 51 funkcí; `includes/club_program.php` 1 003 / 52. Soubory se navzájem načítají. | Oddělit jen čtecí katalogovou část s kompatibilními fasádami; úplné rozdělení zápisových služeb nyní nepomůže hlavnímu problému. |
| R5 | `ShopPublicNavigationWiringTest::testEveryPublicShopPageUsesSharedNavigation()` hledá přesný zdrojový výraz `return !$isProgram && $offer===false;`. | Tento dílčí test váže přejímku na implementaci. Nahradit příslušnou aserci testem výsledného seznamu a vykresleného menu ve stejném balíku. |

Historická inventura [DOZOR_UKLID](DOZOR_UKLID_2026-10-25.md) obsahuje 39 helperů
introspekce a 396 volání. Tyto historické počty nejsou nové runtime měření.
Návrh nepovažuje všechny fallbacky za mrtvé: některé chrání neúplné fixtures,
diagnostiku a různé databázové enginy.

## Měření před změnou

SQLite v paměti, redukované syntetické schéma, jeden aktivní kusový produkt
s jednou variantou; skladová tabulka existuje, kategorie a cena jsou známé.
Každý scénář běžel třikrát nad novým PDO. Počítají se vykonané dotazy,
nikoli pouze `prepare()`. Příprava fixtures se do měření nezapočítává.

| Produkty | Detail: SQL | Detail: načtené produktové řádky | Navigace: ruční zboží SQL | Navigace: importované zboží SQL |
| ---: | ---: | ---: | ---: | ---: |
| 1 | 9 | 1 | 20 | 12 |
| 50 | 58 | 50 | 461 | 61 |
| 250 | 258 | 250 | 2 261 | 261 |
| 1 000 | 1 008 | 1 000 | 9 011 | 1 011 |

U 1 000 produktů tvoří 1 005 dotazů detailu kontroly metadat/schématu.
Importovaný katalog má existující zkratku, proto se navigace měří zvlášť pro
ruční a importované zboží. Nelze automaticky přenášet horší výsledek na dnešní
produkční skladbu katalogu. Naměřené časy jsou pouze orientační; úplné vzorky
jsou v [storefront-baseline.json](verification/refactoring-wave1-20261028/storefront-baseline.json).
To není měření HTTP, MariaDB plánů, hostingové odezvy ani kapacity kroužků.

## W1-1 — cílený detail a oddělení čtecích dotazů

Priorita vysoká; velikost malá až střední; závisí jen na základních regresích.

- Zavést `includes/shop_storefront_read.php` pro sestavení a provedení
  katalogového dotazu. Stávající `shopStorefrontProducts(PDO, ?DateTimeImmutable)`
  ponechat jako kompatibilní fasádu, aby se nezměnila volání mimo storefront.
- Detail předá validované ID do parametrizovaného `WHERE p.id = ?`; hodnotu
  nevkládat konkatenací. Neznámé/nekladné ID zachová `null` a HTTP chování volajícího.
- Sdílet skládání výsledku detailu a katalogu. Zachovat přesné pořadí, typy
  hodnot, veřejný název, atributy, obrázkový allowlist a kategorie.
- Obrázky/kategorie načítat jen pro vybraný produkt; prodejnost programu se
  nadále posoudí aktuální doménovou funkcí se stejným časem a podmínkami.
- Přesunout pouze čtecí funkci, nerozplétat současně celý graf `require_once`.
  Zápisový checkout, skladové zámky a price/member-price výpočet zachovat.

Dotčené existující soubory: `includes/shop_checkout.php`,
`includes/shop_storefront.php`, integrační `ShopStorefrontTest` a
`ClubProgramStorefrontTest`. `booking/produkt.php`, `booking/rychly_nakup.php`
a `booking/rychla_prihlaska.php` jsou regresní spotřebitelé stejného API.

**Přejímka:** při přidání 999 nesouvisejících produktů se detail s jednou variantou
načte stále z jediného produktového řádku, stejným počtem dotazů a vrátí stejný
výsledek. Na zdejší fixture nejvýše 12 SQL. Ověřit i skrytou publikaci, neaktivní
variantu, chybějící produkt, nulu na skladu, `NULL`, importované zájmové nabídky,
program před/po termínu a neúplné podmínky. Celá stránka bude ještě zatížena
původní navigací do W1-3; nenazývat to hotovou optimalizací stránky.

## W1-2 — metadata jednou pro jednu čtecí operaci

Priorita vysoká; velikost malá až střední; navazuje na W1-1.

- V novém čtecím modulu vytvořit explicitní kontext spojený s konkrétním PDO
  a jedním voláním katalogu/detailu. Před cyklem zjistit dostupnost skladového
  schématu a potřebných sloupců produktu; používat je po dobu tohoto čtení.
- Oddělit čisté vyhodnocení strukturální prodejnosti zboží od zjištění schématu.
  Kanonická veřejná kontrola používaná zápisem musí nadále sama zjistit aktuální
  stav; interní čtecí predikát se nesmí stát zkratkou pro objednání.
- Sloupce jedné tabulky číst jednou a porovnávat s množinou. Zachovat SQLite
  i MySQL větev, validaci identifikátorů a existující fallbacky jednotlivých polí.
- Zachovat rozlišení chybějící tabulky a ostatních SQL chyb v `shopInventoryTable`.
  Výpadek DB, chybějící sloupec či zamítnutí oprávnění nesmějí znamenat „sklad
  se nespravuje“. Pouhé odstranění guardu není optimalizace.
- Žádná session/persistentní cache, žádné cachování oprávnění, cen, kapacity nebo
  příjemce. Kontext se nepřenáší přes mutaci, transakci, změnu PDO ani migraci.

**Přejímka:** stejné výsledky na plném i minimálním schématu; nový kontext po
syntetickém DDL vidí změnu, dvě PDO se vzájemně neovlivňují. Katalog čistého
zboží má pro 1 i 1 000 variant konstantní počet kontrol schématu; žádný dotaz
na `shop_stock_balances LIMIT 0` uvnitř smyčky. Nemění se chování zápisových
regresí `ShopCheckoutTest`, `ShopInventoryTest` a guest checkoutu.

## W1-3 — navigace bez celého zobrazovacího katalogu

Priorita vysoká; velikost střední; navazuje na W1-1 a W1-2.

- Zavést úzký čtecí výstup pro menu: identita produktu, pole pro klasifikaci,
  příslušné varianty/nabídky a kategorie. Menu nepotřebuje obrázky ani formátování
  detailních atributů. Pro výpis e-shopu zůstává plný zobrazovací model.
- Vazby produkt–nabídka a kategorie načíst po dávkách; klasifikaci zboží vůči
  sportovci rozdělit na načtení dat a jeden čistý sdílený predikát. Wrapper
  `shopProductRequiresAthlete()` musí zachovat dosavadní signaturu a chování.
- Zachovat Shoptet výjimku, ručně založený program bez nabídky, přímou zájmovou
  stránku, vazby neaktivních nabídek a pravidla skrytých kategorií. Kontrolu
  prodejnosti programů nenahrazovat pouhou existencí `club_program_offers`.
- `shopPublicNavigationItems()` a `shopPublicNavigation()` ponechat kompatibilní.
  Existující argument `$items` umožňuje znovu předat již spočtené menu na jedné
  stránce; nezavádět globální cache.
- Nahradit jen dotčené zdrojové aserce wiring testu integračními datovými
  scénáři a kontrolou vykreslených odkazů. Ponechat testy escaping, menu,
  explicitních tras, deploye a anonymního přístupu.

Dotčené soubory: `includes/shop_public_navigation.php`, nový čtecí modul,
`includes/shop_purchase_mode.php`, případně sdílený čistý predikát v samostatném
include; `ShopStorefrontTest`, `ShopPurchaseModeTest`,
`ShopPublicNavigationWiringTest`, nové integrační testy menu.

**Přejímka:** shodné kategorie, počty a pořadí na smíšené fixture; ruční i
importované zboží bez programových vazeb při 1 000 produktech nejvýše 20 SQL,
nezávisle na počtu variant v této fixture. Menu nečte obrázky a nepřevádí
atributy. U programů porovnat časové hranice, kapacitu a podmínky; dokud nebude
změřen programový profil, neslibovat konstantní SQL pro všechny typy nabídek.

## Ověření a dokončení vlny

Pro každý balík: cílené behaviorální testy, PHP syntaxe, `composer style:check`,
`composer analyse` bez nových baseline výjimek a `git diff --check`. Při přesunu
existující funkce přesunout jen odpovídající staré baseline záznamy; nesmí
narůst počet potlačených výskytů ani vzniknout široké ignore. Nové rozhraní má
konkrétní PHPDoc tvary, nepřekrývá chyby přes `mixed` nebo náhradní výchozí data.

Před předáním celé vlny:

1. `composer validate --strict`, celá PHPUnit sada a
   `php tools/check-phpstan-contract.php`.
2. `php tests/Support/AnonymousSweep.php` a současné JavaScript smoke testy
   z `.github/workflows/tests.yml`. Případná nová PHP cesta nesmí rozšířit veřejný povrch.
3. Linux CI nad přesným headem, včetně všech tří Windows-přeskakovaných testů,
   MariaDB 10.3 i 11.4. Případný environmentální fail uvést odděleně.
4. MariaDB měření stejné fixture: počet SQL, řádků, `EXPLAIN`, medián/p95,
   peak memory; nejméně 5 zahřívacích + 30 měřených požadavků. HTTP porovnat
   na stejném izolovaném prostředí, datech a nastavení OPcache, včetně celé navigace.
5. Browser průchod veřejného katalogu/detailu, přihlášeného rodiče, členské ceny,
   rychlého nákupu a zájmového programu; anonymní přímý POST, CSRF a odvolaná vazba.
   Zápisy jen synteticky a se zablokovanými externími transporty.

Rozhodující výkonová brána jsou počty SQL a načtených řádků, shodné výstupy
a neporušené zápisové kontrakty. Zlepšení času potvrdit až po měření MariaDB/HTTP;
neslibovat předem procento zrychlení produkce. P95 nesmí vykázat reprodukovatelnou
regresi proti stejné základně; při šumu zopakovat párové měření a uvést rozptyl.

**Aktuální CI základ není plně zelený:** [Testy 40333016697](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40333016697)
má úspěšné PHPUnit i MariaDB 10.3, ale MariaDB 11.4 skončila při `Initialize containers`
před checkoutem a testy. PHP quality a dokumentace jsou zelené. [Snapshot](verification/refactoring-wave1-20261028/github-ci.json).
Před implementačním vydáním znovu zajistit běh obou DB větví; tento starý
environmentální fail není dokázaná vada aplikace ani prominutí přejímky.

## Hranice a navazující backlog

První vlna nemá migraci, novou závislost ani změnu doménových stavů. Identita
osoby, rodinná autorizace, rozsah trenéra, haléřové částky, VS, neměnné doklady,
stock P/R/B/A, audit a kontrola oprávnění pod zámkem zůstávají invarianty.
Vypnuté integrace a ostrý import se touto vlnou nezapínají.

Odloženo do další vlny:

- `kisRosterPersonTargetIds()` opakuje načítání grafu pro každý cíl; nejprve
  změřit a doplnit časové/právové regrese, potom sdílet graf pro jediné čtení.
- Členské ceny se počítají pro každou variantu; dávkování vyžaduje samostatný
  test revokace rodičovské vazby i kontroly ceny uvnitř checkoutu.
- Plošné sjednocení 39 introspekčních helperů a odstraňování fallbacků až po
  důkazu migračních předpokladů; diagnostické a migrační funkce mají jiné potřeby.
- Velké tréninkové formuláře, `hlavicka.php`, KIS synchronizace a dlouhé služby
  dělit po pracovních cestách, ne podle limitu řádků. V původním checkoutu navíc
  existuje WIP `kis_rosters_admin.php`.
- Plošný převod wiring testů, přepis frameworku, hromadné formátování a nové
  indexy bez `EXPLAIN` nejsou součástí vlny.

Rollback: jednotlivé lokální změny vracet po balících v opačném pořadí,
zachovat facade API; není důvod obnovovat DB. Případné pozdější produkční vydání
i návrat se řídí [NASAZENI](NASAZENI.md) a konkrétním souhlasem vlastníka.

## Stav pro pokračování

- [x] Ověřen aktuální main a oddělené worktree; původní WIP zachováno.
- [x] Inventář dokumentace, kontrakty, relevantní zdrojový kód a testy.
- [x] Reprodukovatelné syntetické měření detailu a menu, 16 scénářů × 3 opakování.
- [x] Lokální PHPUnit a kontrola aktuálních GitHub CI jobů.
- [x] PHPStan level 5 bez nových chyb, kontrola dokumentačních odkazů a syntaxe benchmarku.
- [x] Implementace W1-1, W1-2 a W1-3, behaviorální a výkonové regrese.
- [x] MariaDB/HTTP/browser měření a Linux CI aplikačního kódu s opraveným lockem.
- [x] Předání v PR #209; samostatná evidence změn, testů a metodických limitů.
- [ ] Merge a nasazení — nejsou součástí autorizované první vlny.

Další krok: review PR #209 a samostatné rozhodnutí o vydání podle NASAZENI.
Následující optimalizace programových nabídek nebo členských cen vyžaduje
vlastní měření; tato vlna neslibuje konstantní počet dotazů u programové kapacity.
