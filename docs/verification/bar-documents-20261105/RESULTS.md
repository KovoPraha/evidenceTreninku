# BAR → KIS, B5: faktury, nabídky a účetní rozhraní

5. 11. 2026, větev `codex/bar-kis-integration-20261105`, draft [PR #306](https://github.com/KovoPraha/evidenceTreninku/pull/306). Pouze místní syntetická data. Produkční nasazení, skutečný převod produktů/lucky, bankovní párování ani účetní zápisy nejsou provedené ani povolené.

## Implementace

- Soukromý originál PDF s kontrolovaným SHA-256, MIME a limitem 10 MB. Parser Smalot omezuje dekódování, počet stran a velikost textu. Úzký profil LE PATIO přebírá přesná čísla; skeny a neznámé formáty vyžadují ruční přepis. Dobropisy, zálohy a cizí měna nejsou tímto příjmem řešené.
- Kontrola hlavičky a součtů, skladové/nákladové/zálohové řádky, přepočet balení, výslovné potvrzení párování a volba změnit/ponechat nákupní cenu. Uložené dodavatelské párování je pouze návrh. Duplicity stejného PDF i stejné identity dodavatel/číslo se nevytvářejí.
- Atomický příjem do předskladu, neměnný příjmový snímek a skladové pohyby. Samostatná `purchase_version` odlišuje změnu nákupní ceny od běžného pohybu zásoby a odmítá schválení zastaralé i po změně ceny tam a zpět. Storno vyžaduje dostupnou celou zásobu v předskladu, zachová katalogové ceny a vrátí fakturu k nové kontrole. Opravený příjem dostane nové ID.
- Pracovní návrhy a neměnná vydání nabídek. Zveřejněný výběr platí pro pokladnu a nabídku stánku, vlastní spotřeba má svá původní oprávnění. Obrazovka ukazuje aktuální ceny a jen veřejnou projekci katalogu; návrhy, nákupní ceny, zásoby a dodavatelé zůstávají soukromé. Fotografie lze anonymně stáhnout pouze při aktuální veřejné vazbě. Obrazovka se obnovuje po 30 sekundách. Tiskový PDF lístek má samostatný vzhled, české písmo a čas vygenerování, bez vzdálených zdrojů a spouštění skriptů.
- Provozní přehled úhrad po stornu a vratkách, oddělené zálohy, hotovost/karta/QR. Přehled zahrnuje již exportované podklady. Neměnné JSON dávky a stručné CSV, jediné přiřazení zdroje do exportu, pozdější kompenzace jako nový zdroj. [Účetní kontrakt v1](../../BAR_ACCOUNTING_CONTRACT_V1.md) vysvětluje návaznosti, neznámé náklady, chybějící prodejní rozpis DPH a ochranu před dvojí tržbou při budoucím bankovním propojení.

Nová migrace `20261105200000_bar_documents` přidává osm tabulek a dva stavové sloupce, je opakovatelná na SQLite i MariaDB. Ownership kontrakt `2026-11-05.5` zahrnuje všech 28 barových tabulek i nové sloupce. Původní migrace zůstávají neměnné. Zdrojový rozpracovaný BAR ani nesouvisející checkout Evidence se neměnily.

## Ověření a meze

- Cílené doménové testy: nahrání skutečného syntetického PDF, soukromí/otisk, přesná extrakce LE PATIO a odmítnutí záloh/dobropisu/EUR, součty, balení, nákupní ceny, příjem/storno/rollback, export a opakování, nulový součet prodej–kelímek–storno, neznámé ceny, čekající QR, role, zveřejnění a veřejná projekce. Stav úplné sady a kontrol viz CI na konkrétním commitu PR.
- MariaDB 10.4.32: opakované migrace; dva současné příjmy se stejným tokenem změní zásobu právě jednou; dva různé exporty stejného zdroje mají právě jednoho vítěze; následné storno původní export nezmění. Změna cenové verze i odebrání administrátora během skutečného čekání na barový SQL zámek příjem odmítnou. Předchozí převodový i pokladní souběhový nácvik znovu prošly.
- Skutečná izolovaná záloha a obnova: 327 vlastněných syntetických tabulek PASS, včetně nového schématu B5.
- Anonymní HTTP: 254 chráněných tras, 36 výslovných veřejných výjimek, 2 CLI, nula chyb. Nové veřejné výjimky jsou pouze menu a jeho aktuálně odkazované fotografie. Správa, faktury, PDF přílohy, tiskový lístek a účetní podklady zůstávají chráněné.
- Místní skutečný browser: nahrání neznámého testovacího PDF, ruční kontrola 100 Kč + 21 Kč DPH, příjem dvou balení po třech kusech, uložení párování. Předsklad 7 → 13, nákupní cena 11,01 → 20,17 Kč. Návrh menu zůstal neveřejný do zveřejnění; nabídka používá cenu 37,50 Kč. Export dosavadních syntetických prodejů a příjmu prošel bez účetního zápisu; následné storno tvoří nový podklad. Testovací server má vypnuté odesílání mailu.
- Vykreslený lístek PDF byl převeden do PNG a vizuálně zkontrolován: česká diakritika, cena a celý obsah bez ořezu. Zdrojový syntetický PDF soubor neobsahuje skutečnou fakturu. Místní snímky a stažené podklady jsou v ignorovaném `output/playwright/`.

## Opakování a další krok

`php tests/Support/BarDocumentsMariaDbSmoke.php` používá explicitní loopback port přes `BAR_SMOKE_DB_PORT`, vytváří vlastní náhodnou databázi `evidence_bar_test_documents_*` a po testu ji odstraní. Společné CI jej spouští na MariaDB 10.3 a 11.4. Doménové testy běží v běžné PHPUnit sadě; PDF fixture je čistě syntetická. Unixové kontroly symlinků předchozích etap se na Windows přeskakují a musí projít v Linux CI.

B6 má ještě provést úplnou přejímku rolí a provozních návazností, obnovu DB i soukromých souborů, výpadek spojení, práci s opuštěnou kasou, produkční časové zóny a návrh konkrétního účetního adaptéru. OCR, dobropisy, zálohové faktury, automatické účetní zápisy, skutečný poskytovatel plateb a produkční přechod nejsou součástí B5. Historie starého baru se nepřevádí; skutečné produkty, fotografie a lucka stále čekají na samostatný schválený převod.
