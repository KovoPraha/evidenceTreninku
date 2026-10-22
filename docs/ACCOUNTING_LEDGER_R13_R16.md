# R13 / R16 — průkazné peněžní pohyby

Lokální implementace v balíku R13–R17, bez nasazení a bez ostrých dat. Vstupem obsluhy je `accounting_ledger_admin.php` v pracovní pozici **Hospodář a platby**. Každý zápis ověřuje aktuální finanční oprávnění i uvnitř transakce; HTTP navíc vyžaduje CSRF, důvod a potvrzení.

## Jediná identita peněz

`accounting_movements` zachovává účet, znaménko a celočíselné haléře, skutečné datum, čas zápisu, stabilní zdrojovou referenci a auditora. Nemá opravující UPDATE/DELETE službu. Oprava je další opačný pohyb; částka původního příjmu se nemaže po vrácení peněz. Opakování stejného požadavku je idempotentní, změněný obsah pod stejnou referencí se odmítne.

`accountingLedgerSource` sjednocuje `fio_account_movements`, `club_finance_receipts`, `member_payer_receipts` a skutečné původní `payments`. Bankovní reference má společnou identitu, všechny původní řádky mají vazby v `accounting_movement_sources`. Zaplacené požadavky odvozené z rozdělení rodinného/klubového příjmu se nevykazují jako další peníze. Datum potvrzení platby není automaticky datem přijetí. Historie bez doloženého účtu nebo data čeká na ruční ověření; žádné automatické plošné doplnění neproběhlo.

Při přiřazení zdroje se současně převezmou jeho konkrétní původní alokace na existující objednávky/předpisy se stejnou měnou. Každá má vlastní stabilní klíč zdrojového řádku. Datum původního přidělení se převezme pouze z doloženého času zdroje, jinak jej musí obsluha doplnit samostatně. Nové původní alokace po prvním převodu se objeví jako `allocation_sync_required`; opakované přiřazení doplní pouze chybějící řádky. Změna již převzaté částky zůstane rozporem k opravě.

Původní celá vratka má samostatný zdroj `legacy_refund`. Při převzetí se musí propojit s původním příjmem a auditovaně uvolnit rozdělení vraceného případu; tím se sníží i skutečně dostupná částka původního příjmu. Přesný platební alias se dohledá automaticky, rodinný příjem se volí výslovně a ověřuje se jeho původní rozdělení. Historickou vratku bez jednoznačného dostatečného příjmu nelze automaticky převzít. Dokončení původního platebního stavu z nových účetních splátek má marker `accounting-ledger:ID`; jen po ověření vazby a celkové částky se vyloučí jako odvozený stav, aby nevznikl druhý výdaj.

## Peněžní kniha, rozdělení a členění

- Účty/pokladny vznikají jen s doloženým počátečním stavem. Bankovní zdroj nelze přiřadit pokladně. Platební brána má vlastní zúčtovací účet. Žádný skutečný účet oddílu se automaticky nezakládá.
- `accounting_allocations` jsou samostatné vazby peněz na obchodní případy. Uvolnění je záporný řádek odkazující na původní kladný řádek. Uvolnění samo není výdaj. Nové přidělení nespotřebuje již vrácené peníze ani nevyplacené schválené vratky. Ruční účetní vazba nepřepisuje provozní stav objednávky/předpisu.
- `accounting_classifications` jsou nezávislé neměnné verze úplného rozpisu podle schválených `accounting_rules`. Používá se poslední verze; součet kladných velikostí částek musí odpovídat absolutní částce pohybu na haléř. Původní verze a snímky schválených pravidel zůstávají dohledatelné. I nepřiřazený přeplatek nebo poplatek lze samostatně účetně zařadit.
- Převod mezi vlastními účty tvoří spojená záporná/kladná strana a samostatný poplatek. Odeslání a připsání mohou být v různých měsících. Poplatek zúčtování se odečítá v den odeslání; odlišně datovaný poplatek lze zachytit samostatným doloženým pohybem. Připsání na banku není druhou tržbou. Importovaná protistrana se propojuje s existujícím pohybem, nevytváří se znovu.
- Odsouhlasení hotovosti/výpisu porovnává počáteční stav + pohyby s doloženým skutečným zůstatkem. Rozdíl brání uzavření. Uzavřené datum účtu nepřijme zpětný peněžní pohyb ani nové účetní členění; pozdější skutečná vratka zachová původní období. Pokladní PPD/VPD jsou nad již existujícím pohybem v modulu dokladů.

Přehled je provozní podklad pro účetní, nikoli náhrada jejího daňového posouzení a knih. Účetní metodika, reálné počáteční stavy a úplnost všech klubových zdrojů vyžadují samostatnou provozní přejímku. Import banky, schedulery ani provider integrace se tímto balíkem neaktivují.

## Lokální ověření

`AccountingLedgerTest`: identita a konfliktní replay, zdrojové aliasy, odvozené stavy, neznámá data, kanonické alokace a jejich pozdější změny, nesprávná měna/cíl, uvolnění, hranice vratek, uzavření, nezávislé verzované účetní členění, datum zápisu oproti účinnosti a meziměsíční vypořádání.

`AccountingLedgerHttpAcceptance`: 17 skutečných HTTP scénářů na portu 18086, včetně rolí, CSRF, odmítnutí neplatných peněz, dvojkliku, klasifikace, inventurního rozdílu, uzavření a odvolané session.

`AccountingLedgerMariaDbRace`: 9 kontrol na vlastní syntetické DB portu 33426; dvě souběžná přiřazení bankovního a příjmového aliasu vytvoří jediný pohyb, souběžné nadměrné alokace mají jediného vítěze. Čtyři čekání na databázový zámek jsou skutečně pozorována v `INNODB_TRX`. Skript ověřuje vlastní datový adresář a vytváří nový označený testovací schema; nemaže cizí data.

Výsledky HTTP a souběhů: `docs/verification/r13-r17/ledger-http.json`, `ledger-race.json`.
