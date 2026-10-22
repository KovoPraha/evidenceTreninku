# Účetní zařazení před zveřejněním produktu

## Rozsah a autorizace

Vlastník 22. 10. 2026 schválil doplnění kontroly před zveřejněním produktu.
Produkty #274, #243 a #245 mají zůstat beze změny; historické a stálé
příspěvkové VS se nesmějí přepisovat. Implementace je v odděleném worktree
`evidencePavel-publication-accounting-20261022`, založeném z `origin/main`
`b6b4251548fc6a99a4c654dc00a70589cc8d2386`. Tento řez zatím není nasazený.

Samostatně schválené produkční mapování bylo před touto implementací uloženo
přes administraci: 2 nadkategorie, 12 nových řad, 13 katalogových mapování,
9 produktových výjimek a 2 změny výchozího druhu (38 auditovaných změn).
Nedocházelo k přepočtu existujících objednávek. Tři odložené produkty byly
porovnány před/po a zůstaly stejné; pět VS ve finančním přehledu zůstalo stejné,
včetně `0000000013`. Čítače zůstaly na 1, nevzniklo žádné nové číslo objednávky.
Jde o rozsah browser kontroly, nikoli nový úplný databázový snapshot plateb.

## Chování kontroly

- Společná připravenost produktu používá stejný klasifikátor jako checkout:
  produktová výjimka → výchozí katalogová kategorie a nejbližší nastavený
  předek → výchozí druh nabídky.
- Publikaci zablokuje řada 8/xx Nezařazeno, chybějící/neaktivní účetní
  kategorie, nepovolená smíšená řada 9/00, nejednoznačná výchozí kategorie,
  cyklus v rodičích nebo nepřipravené účetní tabulky/nastavení.
- Chyba popisuje důvod a odkazuje pracovníka na hospodáře a agendu
  Číselné řady a účetní kategorie. Existující UI blokátory již zobrazuje.
- Platí pro detail produktu, samostatnou publikační administraci, zveřejnění
  importu a hromadnou aktivaci; neúspěšná hromadná aktivace vrací zpět i
  předchozí položky. Odmítnutý import vrací zpět i normalizaci typu/ceny.
- Průvodce kroužkem kontroluje také převod již zveřejněného produktu,
  který nepoužívá běžnou reaktivaci. Odložený převod konceptu ponechá produkt
  i variantu v konceptu.
- Při aktivaci se účetní nastavení zamkne ve stejné transakci. Kontrola
  pouze čte zařazení: nepřiděluje VS ani číslo, nespotřebuje čítač a nemění
  účetní mapování. Dosavadní aktivní produkty se automaticky nestahují.

## Ověření

Regresní testy pokrývají dědění, produktovou výjimku, jednotlivé účetní
blokátory, rollback jednotlivé/hromadné aktivace a průvodce, odložený koncept,
neměnnost tří sporných produktů i historických a obou druhů stálých VS.
Úplná sada `php vendor/bin/phpunit --configuration phpunit.xml.dist` prošla:
**1 214 testů / 26 960 kontrol**, PHP 8.2.12, SQLite. PHP lint všech změněných
PHP souborů a `git diff --check` prošly.

`tests/Support/ShopNumberSeriesMariaDbSmoke.php` prošel na lokální MariaDB
(loopback port 3319, samostatná syntetická databáze následně odstraněna).
Ověřil odmítnutí Nezařazeno, přijetí explicitní řady v transakci bez
přidělení čísla, souběžnou alokaci, idempotenci, ochranu před dvěma editory
a kontrolní otisky VS. Matice CI MariaDB 10.3/11.4 pro tento řez zatím neběžela.

Neobsahuje migraci ani produkční datovou opravu. Vlastník následně 22. 10.
2026 výslovně schválil push, PR, merge po zelených kontrolách a chráněné
produkční nasazení. Ověření blokace bude provedeno bez zápisu produktů;
nasazení tohoto řezu se teprve připravuje.
