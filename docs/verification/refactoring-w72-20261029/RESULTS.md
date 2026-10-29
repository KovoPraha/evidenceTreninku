# W7.2 — celý rodinný přehled

Výchozí `d15db2d0c8ca88ae8bd54ffd9772e9f91945adbc`, 29. 10. 2026.

## Charakterizace a oprava

Původní `familyPortalPersonOverview` znovu četl všechny schválené osoby a
členská pole každé z nich. Úplný přehled 20 osob tím pouze na členská pole
vykonal 420 dotazů. [RED test](red-test.txt): 5 testů / 16 assertions,
jediné očekávané selhání rozpočtu 420 místo 40; ostatní kontrakty PASS.

Zmrazený `tests/Support/FamilyOverviewBaseline.php` obsahuje původní funkce,
s přejmenováním a upravenými include cestami, bez optimalizace. Cílená
volitelná podmínka `AND s.id=?` v `familyPortalAuthorizedPeople` umožňuje
každému profilu znovu načíst jen jeho aktuální vazby a členská pole.
Výchozí načtení rodiny, predikát oprávnění a zpracování rolí zůstávají stejné.
Žádná cache, sdílený autorizační snapshot ani změna transakcí W7.1b.

Testy zachovávají výstup všech sekcí, role a pořadí, volitelné tabulky,
archivované členství, cizí osoby, invalidní cíle, změnu členských údajů a
revokaci. Revokace druhého profilu uprostřed přehledu vyvolá stejnou výjimku
jako původní kód. Historie se neomezuje a UI se nemění.

## Metoda úplného profilu stránky

`FamilyOverviewPageProfile.php` vytváří náhodnou localhost DB, aplikuje celý
migrační katalog a doplní chybějící legacy sloupce minimálního checkout
bootstrapu. Nejde o produkční migrace. FK a nativní prepares zůstávají zapnuté.
Syntetické rodiny 0/1/5/20 osob, jiné rodině patří sentinelová osoba; její
údaje se nesmějí objevit v datech ani HTML. Fixture obsahuje soupisky,
plánovaný trénink, historickou účast a objednávky. Další sekce mají platné
prázdné stavy; nejde o reprezentativní produkční distribuci dat.

Spouští se skutečný GET controller i šablona `booking/sportovni_prehled.php`
v oddělených PHP procesech, po ověření syntetické session. Nahrazuje se pouze
DB bootstrap za izolované PDO; baseline varianta mění jediný vstupní call
na zmrazený overview. Kontroluje se shoda dat všech sekcí i celé HTML,
prázdné chybové stavy a úplnost finance/makeup sekcí. Normalizují se pouze
CSP nonce a čas pozorování finančního souhrnu v datech i jeho zobrazení.
Existující GET synchronizace náhrad běží jen nad syntetickými daty.

SQL instrumentace je samostatný běh; čas/paměť používají běžné PDO a pět
střídajících se měření každé varianty po instrumentovaném zahřívacím běhu.
Výchozí protokol před opravou má jen jeden časový vzorek; rozhodující
porovnání časů používá zmrazenou baseline i current ve stejném finálním běhu.
EXPLAIN je uložen pro autorizační dotazy. Časy nezahrnují autentizační DB
bootstrap, síť, HTTP server ani browser/assets; jde o PHP controller+render,
nikoli produkční SLA. PHP peak není RSS databázového serveru. Počet načtených
řádků není počet řádků prozkoumaných DB. Instrumentace nezapočítává exec,
neúspěšné dotazy, prepare čas ani interní transakční příkazy.

## Stav ověření

[Výchozí profil](before.json), [finální místní profil](after.json).
Šest nových PHPUnit testů / 23 assertions PASS; dosavadní cílené rodinné a
zápisové kontrakty rovněž PASS. Celá stránka pro 20 osob: **2706 → 2326 SQL**
(-14,0 %), **2953 → 2193 načtených řádků** (-25,7 %). Hydratace členství
v celém GET 540 → 160, z toho v hlavním overview 420 → 40. Rodiny 0/1 mají
stejný počet SQL; u pěti osob 711 → 691. Data i normalizované HTML shodné.

Místní pětivzorkový medián pro 20 osob 4260,77 → 5271,51 ms nebyl zrychlením;
current vzorky klesaly 6603 → 3997 ms. Paměťový peak zůstal prakticky stejný
(cca 5,6 MB). Bez dalšího ověření nelze tvrdit stabilní časový přínos.
Následuje nezávislé CI měření obou MariaDB a klidové přeměření tohoto případu.
SQL/řádkový rozpočet je deterministický; časová měření nejsou SLA.
Úplné brány a produkční ověření budou doplněny před uzavřením balíku.

[Plán](../../REFACTORING_W72_2026-10-29.md).
