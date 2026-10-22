# R39 – finanční a obchodní převodníky

Implementace je připojena ke společnému aditivnímu převodníku R38/R41, pouze pro označené syntetické CLI zkoušky. Produkční režim ani přepínač pro jeho zapnutí neexistuje. Nic nenahrazuje současné cílové osoby, objednávky ani peníze.

## Povolené typy

| Typ | Doložený dopad |
|---|---|
| `finance_account_opening` | Založí nový účet/pokladnu s odsouhlaseným počátečním stavem. Nevytvoří peněžní pohyb. Existující počáteční stav nelze přepsat. |
| `finance_stock_opening` | Otevře dosud nevedenou kusovou variantu podle fyzické inventury, zvlášť blokací a úplného explicitního soupisu otevřených cílových objednávek. Zachovává neznámé ocenění. Nevydává zboží ani nemění úhradu objednávky. |
| `finance_order_link` | Propojí původní stabilní ID s ověřenou existující objednávkou. Kontroluje protistranu, částku, měnu, stavy, přesný seznam VS včetně nul, položky a rezervace. Vyžaduje systém dokončení a konkrétního odpovědného pracovníka. |
| `finance_document_link` | Propojí existující vydaný doklad podle původního čísla, částky, měny, otisku obsahu a skutečných soukromých PDF bytes. Nevydá nový doklad ani neposune číselnou řadu. |
| `finance_member_charge_link` | Propojí existující předpis, příjemce/plátce, kód, částku, stav a VS bez vytvoření další platby. |
| `finance_expense_link` | Ověří explicitního plátce/věřitele, číslo dokladu, uznanou část, skutečně vypořádané peníze a zůstatek všech verzí. |
| `finance_advance_link` | Ověří konkrétního příjemce, schválený limit, skutečná předání, vratky, čerpání, přesuny a saldo. Samotná deklarovaná stará záloha není nový výdaj. |
| `finance_reward_link` | Propojí schválený nárok, osobu, příjemce, původní období, částku a doložené výplaty. Nepřepočítá historickou odměnu podle nově importovaných tréninků. |

Pro typy `*_link` neexistuje automatické vytváření chybějícího aktivního obchodního případu. Takový řádek musí být v karanténě s původním ID/VS, systémem a osobou doběhu a stavem rezervací. Dokud nemá doložený bezpečný postup dokončení, má `legacy_completion.stop_flag=true` a blokuje převzetí agendy. Tato implementace se neoznačuje za kompletní převod skutečné historie nebo připravenost ostrého přechodu.

## Kontrakt kontroly

Zdrojová dávka používá `club-migration.synthetic.v1`, `synthetic_only=true`, `timezone=Europe/Prague`, namespace a přesné časové okno. Každý řádek nese stabilní `source_id`. `proposal` obsahuje `mapping_evidence` a `counterparty_evidence`; počáteční účty navíc `opening_approval_evidence`, sklad `inventory_approval_evidence`.

U propojení musí `data.expected` obsahovat všechny kontrolní hodnoty daného typu. V `data.counterparties` jsou původní stabilní identifikátory osob podle rolí (např. `buyer`, `payer`, `creditor`, `recipient`). `proposal.counterparties[role]` obsahuje `source_id`, explicitní `target_kind`, `target_id` a `evidence`. Systém porovná zdrojové ID, přesný typ cílové protistrany a aktuální existující identitu. Žádná shoda čísla nebo názvu napříč instalacemi se nepovažuje za mapu. Předané společné `identity_maps` se také znovu ověří proti aktuálnímu otisku cíle.

Doložený rozpor v `data.conflicts` vyžaduje konkrétní `resolution_evidence`; rozdílné kontrolní částky přesto nejde ignorovat. Neshoda Harrachova 0/2 000 Kč nemá automatické pravidlo „novější vyhrává“.

U skladu `proposal.variant_id` určuje ověřené SKU a `proposal.reservations` mapuje cílové ID položky na počet kusů. Součet musí odpovídat `data.reserved_quantity` a všem právě otevřeným cílovým objednávkám. Fyzický počet musí pokrýt rezervované a blokované kusy. Stará dostupnost se nikdy nevydává za fyzický počet.

Náhled a aplikace ověřují stav pod kanonickým finančním mutexem a podle entity i zámkem varianty/objednávky. Otisk obsahuje řádky, jejich vazby, saldo a aktuální protistrany. Schválení je vázáno na obsah stagingu, návrh mapování i otisk kódu převodníků a schématu. Změna zdroje, cíle, oprávnění nebo kódu vyžaduje nový náhled. Opakování stejného source ID se stejným obsahem propojí jediný již převzatý cíl; nikdy nevytvoří druhý počátek.

## Ověření

- `ClubMigrationFinanceTest`: 11 testů / 103 assertions PASS. Počátky bez nových příjmů, převzetí rezervací, idempotence přes více snímků, zachování VS, změněný cíl, neodsouhlasený Harrachov, odvolaná role, chybějící PDF, zálohy a historické odměny, nesprávná protistrana se stejnou částkou a změněná mapa osoby.
- `ClubMigrationFinanceMariaDb.php`: 21 kontrol PASS nad novým vlastním schématem `evidence_migration_test_*`. Skutečná aplikace účtu a fyzického skladu 5/2/1 → dostupné 2, původní VS `0000123456` a `pending` zachovány. Počáteční stav nevytvořil žádný `accounting_movements`.
- Dvě skutečné konkurenční zkoušky ověřily `INNODB_TRX LOCK WAIT`: novější účetní počátek 777 Kč se nepřepsal starým 500 Kč; novější varianta zůstala zachována a neplatný náhled nevložil ani částečný skladový počátek.

Výsledky jsou syntetické technické důkazy. Ověření skutečných exportů, osobních map, čísel dokladů, fyzické inventury a zůstatků musí proběhnout v samostatně schváleném datovém převodu.
