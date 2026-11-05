# Účetní podklady baru – kontrakt v1

Etapa B5 integrace BAR → KIS, 5. 11. 2026. Implementace: `includes/bar/accounting.php`, chráněná stránka `/bar/accounting.php`.

## Hranice

Export `kis.bar.accounting.v1` je neměnný kontrolní podklad se stavem `review_required`. Nezakládá účetní doklad ani pohyb v peněžní evidenci KIS, nepáruje banku a nevolá poskytovatele plateb. Přístup mají aktivní pracovní pozice `finance_manager` a `bar_admin`; obsluha jej nemá. Hospodář může číst originály faktur, ale nemůže měnit příjem, sklad ani nabídky.

Úplný obsah je JSON. CSV představuje pouze stručný přehled prodejních částek; nenahrazuje fakturu, položkové podklady, původní export ani účetní import. Textové buňky CSV jsou chráněné proti interpretaci jako tabulkový vzorec.

## Jedinečnost a opravy

Každá událost má stabilní `source_key`, typ `kind`, čas evidence `occurred_at` a měnu `CZK`. Náhled přidává SHA-256 celého normalizovaného obsahu události. Při vytvoření exportu musí stále souhlasit výslovně zvolený zdroj i jeho otisk. Nejvýše 500 zdrojů tvoří jednu dávku; přehled nabízí nejvýše 501 dosud neexportovaných záznamů každého typu a po exportu zpřístupní další.

Primární klíč `kis_bar_accounting_export_items.source_key` dovoluje přiřadit zdroj pouze jednou. Dva souběžné exporty nemohou převzít stejnou událost. Opakování stejného formuláře vrátí stejnou dávku, stažení vrátí přesné původní JSON a kontroluje otisk celé dávky. Následné storno zakládá nový kompenzační zdroj s vazbou `original_source_key`; nepřepisuje původní export.

## Typy zdrojů

| Klíč | Typ | Význam |
| --- | --- | --- |
| `bar:sale:<id>` | `sale_settlement` | Potvrzená úhrada prodeje; zboží po slevě, vratná záloha, způsob a podklad potvrzení platby |
| `bar:void:<id>` | `sale_refund` | Kompenzace zaplaceného prodeje; záporné zboží, zbývající záloha a skutečná evidovaná vratka |
| `bar:cup:<id>` | `deposit_return` | Samostatná záporná vratka zálohy; nevytváří zápornou tržbu ze zboží |
| `bar:sale:<id>` | `own_consumption` | Vlastní spotřeba s historickým odhadem nákladu, nulovým prodejem i platbou |
| `bar:void:<id>` | `own_consumption_reversal` | Vrácení vlastní spotřeby, `cost_reversal_source_key` odkazuje na původní nákladový snímek |
| `bar:receipt:<id>` | `supplier_receipt` | Neměnný příjmový snímek dodavatelské faktury včetně řádků, DPH, zaokrouhlení, balení a cenových rozhodnutí; platba nula |
| `bar:receipt-reversal:<id>` | `supplier_receipt_reversal` | Kompenzace celého konkrétního příjmu (`sign=-1`), důvod a původní snímek; platba nula |
| `bar:drawer:<id>` | `drawer_transfer` | Otevření, vklad nebo výběr hotovosti; `drawer_delta_minor` je změna kasy, prodej i platba v exportu nula |
| `bar:closure:<id>` | `cashbox_closure` | Neměnný uzávěrkový snímek, očekávaná/skutečná hotovost a rozdíl; není další tržbou ani platbou |

Zrušená nezaplacená objednávka nevytváří potvrzenou úhradu ani finanční vratku. Čekající karty/QR se zobrazují jako nevyřešené a do potvrzených úhrad se nezařadí. Původní potvrzená úhrada zůstává zdrojem i po stornu, aby následná kompenzace měla co vyrovnat. Vratka kelímku a storno zbývající zálohy se nepřekrývají.

## Částky, náklady a čas

Peněžní hodnoty jsou celá čísla v haléřích, nikoli desetinné floaty. `goods_including_tax_minor` je zboží po slevě včetně daně. `deposit_liability_minor` je samostatná vratná záloha. `payment_minor` představuje úhradu/vratku, nikoli druhý výnos. Součet úhrad po kompenzacích odpovídá zboží a změně závazku ze záloh; vklady a uzávěrky se do něj nepřičítají.

U prodeje je `tax_breakdown=null`: rozdělení DPH se z dosavadního katalogu nesmí vymýšlet. `estimated_cost_minor=null` znamená neznámý náklad, nikoli nulu nebo bezplatné pořízení. Prodejní náklad vychází z původních cenových/surovinových snímků; nejde o FIFO ocenění zásob. Příjem uchovává přesné částky originálu. Zaokrouhlená jednotková cena z částky s DPH slouží jako katalogový odhad; neřeší nárok na odpočet DPH. Rozhodnutí `keep` může záměrně ponechat jinou katalogovou cenu.

B6 stanovuje `kis_bar_state.clock_contract=utc-v1`: nové finanční události mají DATETIME v UTC a dávka výslovně uvádí `source_timezone=UTC`. `database_timezone` je pouze diagnostika sdíleného spojení, neurčuje zónu událostí. Aplikace zapisuje přes `UTC_TIMESTAMP()` a nemění časovou zónu společného PDO. `generated_at` je UTC s offsetem. CSV přidává sloupec `source_timezone`; u staršího manifestu bez této informace zůstane prázdný, původní JSON ani jeho otisk se nepřepisují.

Období zadané kalendářními dny v `Europe/Prague` je polouzavřený UTC interval od místní půlnoci do půlnoci po posledním dni; při změně času má den 23 nebo 25 hodin. Zobrazení používá Europe/Prague včetně CET/CEST. Příjem má zvlášť skutečné datum `received_on` a datum faktury ve snímku, bez časové konverze. Provozní přehled filtruje podle potvrzení platby nebo vratky a zahrnuje již exportované podklady.

Migrace B6 označí existující finanční historii bez doloženého režimu jako `legacy-local`; nepřevede ji odhadem ani ji neoznačí jako UTC. V takovém stavu zablokuje nové finanční zápisy a nový účetní export, původní export lze dál stáhnout. Schválený produkční začátek BAR má nulovou historii, proto se při budoucím B7 musí režim ověřit před otevřením první kasy. Nácvik B6 prokázal UTC i při sdíleném spojení s offsetem +09:00 a nezměněné nastavení PDO.

Nouzová uzávěrka opuštěné kasy uchovává `owner_id`, skutečného `closed_by` a `closure_kind=recovery` také v neměnném uzávěrkovém snímku a zdroji `cashbox_closure`. Nejde o převod vlastnictví ani o další příjem. Zrušení čekající a neuhrazené objednávky má nulovou vratku; zdroj pro potvrzenou úhradu nevznikne.

## Navazující účetní propojení

Budoucí adaptér musí zachovat jedinečnou vazbu na `source_key`, ověřit celistvost dávky a vyřešit návaznosti na původní exporty včetně nákladových storen. Musí určit účty a pravidla DPH se správcem účetnictví, doplnit chybějící ocenění a oddělit pohyb zásob od dodavatelské úhrady.

Kartu/QR je nutné propojit s konkrétním existujícím bankovním/terminálovým pohybem, nikoli znovu založit peníze podle barového potvrzení. Ruční podklad platby není ověření poskytovatelem a shoda částky sama o sobě není důkaz. Poplatky, souhrnné vypořádání terminálu a časové rozdíly vyžadují vlastní odsouhlasení. Dokud tato pravidla nejsou schválená a otestovaná, BAR do hlavní peněžní evidence automaticky nezapisuje.

Konkrétní návrh adaptéru a zbývající rozhodnutí obsahuje [návrh návaznosti](BAR_ACCOUNTING_ADAPTER_PLAN_2026-11-05.md). Tento dokument neaktivuje zaúčtování.
