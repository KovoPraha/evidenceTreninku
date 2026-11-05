# Začlenění baru do KIS

Schváleno vlastníkem 5. 11. 2026: připravit plán a zahájit implementaci včetně potřebného refaktoringu. Produkční nasazení není povolené. Vývoj probíhá ve větvi `codex/bar-kis-integration-20261105`, v pracovní kopii `C:\Users\Marek\Documents\Codex\evidencePavel-bar-integration-20261105`, od `0ced6664`. Samostatný sedmietapový refaktoring baru nahrazuje tento plán.

## Schválený rozsah

- Jedna aplikace KIS, společné přihlášení, session, pracovní pozice, audit a nasazování. Modul pod `/bar/` na `kis.kovopraha.cz`.
- Obsluha baru (`bar_operator`) pracuje s pokladnou a vlastní kasou, uzávěrkou a zásobou stánku. Nemá trenérská ani administrátorská oprávnění.
- Administrátor baru (`bar_admin`) spravuje katalog, ceny, receptury, lístky, sklad, příjem a barové přehledy. Nezískává jiné agendy KIS ani správu systémových účtů.
- Vlastní spotřeba je samostatně přidělované oprávnění `own_consumption`. Není třetí pozicí a automaticky ji nezíská administrátor ani superadmin. Přidělení i odebrání musí být auditované; právo se ověřuje v DB také uvnitř zapisující transakce.
- Ze samostatných účtů baru se převede pouze `lucka`, s technickým e-mailem `info1@kovopraha.cz` a výhradně pozicí Obsluha baru. Zachová se platný hash hesla; případná neplatnost nebo kolize zastaví převod. Technický e-mail neslouží obnově hesla ani automatické komunikaci a není dokladem ověřené identity. Ostatní barová oprávnění se přidělí konkrétním existujícím účtům KIS; vazbu účtu `trebe` nelze odhadnout podle jména.
- Zachovat produkty, kategorie, názvy, popisy, fotografie, nákupní i prodejní ceny, receptury a nastavení katalogu. Zachovat i archivaci a příznaky nabídky. Zkušební zásoby stánku a předskladu se nepřebírají; výchozí množství jsou nula.
- Nepřevádět historické prodeje, kasy, pohyby, inventury, vlastní spotřebu, faktury, původní účty kromě lucky ani staré zálohy. Starý systém se při přípravě nemaže. Nová data patří do běžných záloh KIS.
- Přesměrování staré domény a nový počátek provozu jsou součástí budoucího samostatně schváleného nasazení. Staré offline fronty se na novém původu nikdy automaticky nepřehrávají.
- Příprava účetního propojení od začátku: peníze v celých haléřích, oddělené platby, zboží, vratné zálohy, storna, skladové náklady a vlastní spotřeba; stabilní zdrojové ID a idempotence. Aktivace integrací ani skutečné účetní zápisy nejsou součástí přípravy.

## Architektura a refaktoring

Nový modul používá `includes/bar/` pro doménové služby a `/bar/` pro tenké webové vstupy. Kód nesmí načítat samostatné přihlášení, session, konfiguraci ani HTTP hlavičky starého baru. Tabulky `kis_bar_*` patří KIS; tím se oddělí od stávajících sdílených `jidlo_*` a `bar_*`. Již aplikované migrace žádného projektu se nemění.

Ze zdroje `KovoPraha/bar.kovopraha.cz` se přebírají obchodní pravidla a vhodné existující testy. Commit `ed7c15c` řeší hromadné načítání importu; `39f17e5` rozděluje služby faktur. Rozpracované oddělení prodeje a uzávěrky v `C:\xampp\repos\bar.kovopraha.cz` je podklad k revizi, nikoli ověřené vydání. Cizí WIP se nemění. Nový modul nebude záviset na souborech tohoto checkoutu.

## Etapy a akceptace

| Etapa | Výsledek | Podmínka dokončení | Stav |
| --- | --- | --- | --- |
| B0 | Rozsah, plán a oddělená větev | Zaznamenaná rozhodnutí vlastníka, bez produkčního zásahu | Hotovo |
| B1 | Oprávnění a základ katalogu | Dvě pozice, oddělená spotřeba, aktuální DB autorizace, audit práv, idempotentní migrace, zálohovací kontrakt; normalizace zdrojového katalogu bez zásob | Implementováno a lokálně ověřeno |
| B2 | Převod katalogu a lucky | Čtecí inventura zdroje, kontrolní manifest fotografií, transakční a opakovatelný převod, ochrana před kolizí jména/e-mailu, technický účet bez obnovy e-mailem; syntetický nácvik | Implementace a syntetický nácvik hotové; skutečný zdroj zatím nepřeveden |
| B3 | Katalog a sklad | Administrace produktů, cen a receptur, dvě skladová místa, příjem/přesun/inventura, historické nákladové snímky; obsluha nedostává předsklad ani nákupní ceny | Implementováno včetně kategorií a fotografií; místní testy a browser PASS |
| B4 | Pokladna a vlastní spotřeba | Prodej, hotovost/karta/QR, kelímky, storno, vlastní a uzavíraná kasa, zásoby a audit v jedné transakci; opakování i souběh bezpečné | Implementace a místní přejímka hotové; samostatná Linux CI brána, nasazení zakázané |
| B5 | Faktury, nabídky a účetní rozhraní | Příjem PDF, obrazovky/menu, přehledy, oddělené účetní zdroje a exportní kontrakt bez zdvojení tržeb | Implementováno, místní přejímka PASS; CI v PR #306 |
| B6 | Integrační přejímka | Anonymní HTTP, matice rolí, skutečný místní browser, pokladní/skladové souběhy, ztráta spojení, fotografie a izolovaná obnova DB i souborů; zelené CI přesného headu | Čeká |
| B7 | Produkční přechod | Nový výslovný souhlas vlastníka; katalogový manifest, nulový počátek, přidělení účtů, doména a provozní UAT | Zakázáno do schválení |

## Ověření

Zápisové testy používají pouze syntetickou izolovanou databázi; nikdy konfiguraci z původního XAMPP webrootu. Základní brána zahrnuje lint změněného PHP, `composer validate --strict`, cílené testy, úplné `composer test`, styl, statickou kontrolu a `git diff --check`. Každá nová vlastněná tabulka má být v `bin/db-backup.php`. Nové webové vstupy se evidují v registru pracovních pozic a procházejí anonymním HTTP testem. MariaDB ověří opakovanou migraci i aktuální zamykající autorizaci.

## Průběh implementace

5. 11. 2026: dokončena místní implementace B1. Migrace `20261105150000_bar_foundation` zakládá pět tabulek `kis_bar_*` a dvě pozice; nepřiděluje je uživatelům a nezasahuje do původních tabulek. Nové přehledy `/bar/` a `/bar/catalog.php` jsou pouze čtecí. Správce systému má funkční auditované přidělení a odebrání práva na `/bar/permissions.php`. Samotný odběr vlastní spotřeby vznikne v B4. Čistá normalizace zdrojového katalogu zachovává konfiguraci, odděluje peníze v haléřích a seznam potřebných fotografií a vynucuje nulové výchozí zásoby; zatím nic neimportuje.

[Výsledky B1](verification/bar-foundation-20261105/RESULTS.md): PHPUnit 2044 testů / 38883 kontrol, tři unixové symlink testy přeskočeny na Windows; PHPStan celého projektu, styl a lint PASS. Anonymní HTTP 241 chráněných tras / nula chyb. MariaDB 10.4.32: opakovaná migrace a odvolání oprávnění přes dvě spojení PASS; syntetická záloha a obnova 304 tabulek PASS. Linux CI zůstává samostatná brána, zejména pro MariaDB 10.3/11.4 a unixové hranice úložiště.

Další krok je B2: připravit manifest katalogu a fotografií, bezpečný opakovatelný převod a technický účet lucky bez automatického zákaznického propojení, rozesílek nebo e-mailové obnovy. Oprávnění původních administrátorů a vlastní spotřeby se nepřidělují odhadem. Produkce, zdrojový katalog, účty, hesla i jejich role zůstaly při B1 beze změny. Stav nasazené aplikace dál určuje `CURRENT_STATE.md`.

5. 11. 2026: vlastník povolil pokračování B2 a navazující implementace. Větev je doplněná o aktuální `main` (`bf899521`); B1 zůstává v draft PR #306, bez sloučení. Tento balík připravuje přenositelný soukromý balíček, ověření fotografií, opakovatelný import, pracovní účet s technickou adresou a základ zápisové správy katalogu/skladu. Nácvik používá syntetické zdroje a izolované cíle; skutečný převod a produkční nasazení zůstávají mimo aktuální práci.

5. 11. 2026: dokončen syntetický převod B2 a místní browser průchod B3. [Výsledky](verification/bar-transfer-20261105/RESULTS.md) a [postup převodu](BAR_TRANSFER_RUNBOOK_2026-11-05.md) popisují bezpečné balení, lokální import, omezení technického e-mailu i další práci. PHPUnit 2065 testů / 39167 assertions, tři Windows výjimky; MariaDB převod/sklad i obnova 309 tabulek PASS. Browser ověřil přihlášení lucky, chráněné fotografie, změnu ceny, příjem a přesun; opakovaný import zachoval nové ceny a zásoby. Pokračovat dokončením B3 a poté B4; skutečný katalog a účet ještě nebyly kopírované. Produkční nasazení zůstává zakázané.

5. 11. 2026, navazující práce: vlastník povolil pokračování. Doplnit B3 o vytváření a verzované úpravy kategorií, odstranění pouze prázdné kategorie, chráněné nahrání/výměnu/odebrání fotografie jednotlivého produktu a jejich aktuální autorizaci, audit a nácvik v browseru. Základ je doplněný o `origin/main` `436b1f85` bez zásahu do produkce. Fotografie jsou neměnné soukromé soubory; nahrazení nemaže starý soubor, aby zůstala možná obnova. B4 poté naváže vlastní otevřenou kasou, cenovým potvrzením objednávky, atomickým odpisem surovin a oddělenou evidencí platby; samotné označení kartou či QR nesmí předstírat potvrzení poskytovatele.

Dokončení B3: [výsledky](verification/bar-catalog-completion-20261105/RESULTS.md). Kategorie mají samostatné verze, audit a ochranu před odstraněním použitých kategorií včetně archivovaných produktů. Nahrání fotografie ověřuje skutečný upload, formát a obsah; změna se autorizuje znovu uvnitř transakce. Nahrazení se týká pouze jednoho produktu, opakování stejného obrázku je bez další změny a staré soukromé soubory zůstávají pro obnovu. Po konfliktu formulář nepřevezme novou verzi automaticky. PHPUnit 2083 / 39467, PHPStan 838, styl, anonymní HTTP 244 chráněných tras, MariaDB a obnova 309 tabulek PASS. Původní zdroj ani produkce se neměnily.

## Konkrétní pokračování B4

Vlastník výslovně schválil implementaci B4 bez nasazení. Práce pokračuje ve stejné větvi a draft PR #306. B4 používá vlastní kasu pracovníka, globální barový zámek před zamykající autorizací, společný idempotentní registr požadavků a neměnné prodejní i surovinové snímky. Čekající bezhotovostní objednávka blokuje vydané množství; potvrzení již sklad znovu neodepisuje a zrušení vrátí přesné původní odpisy. Ruční potvrzení karty/QR bude označené jako ruční s podkladem, nikoli jako ověření poskytovatelem. Nastavení zálohy kelímku je samostatná auditovaná správa; vratka čerpá sazbu a zbývající množství původního dokladu. Žádná skutečná banka, platební služba, e-mail nebo účetní zápis se neaktivuje.

1. Vlastní kasa: nejvýše jedna otevřená kasa na pracovní účet, počáteční hotovost, oddělený deník pohybů a nevratná uzávěrka se skutečně spočtenou hotovostí a zdůvodněným rozdílem. Administrátorská pozice nedává možnost zapisovat do cizí kasy.
2. Prodej: server spočítá ceny v haléřích z aktuálního katalogu; klient potvrdí konkrétní ceny a verze. Změněná cena nebo receptura vyžádá nové potvrzení. Jedinečný požadavek je svázaný s obsahem a aktorem. Prodej, položky, odpis souhrnné potřeby surovin ze stánku, nákladové snímky a audit jsou atomické. Dvojité odeslání a ztracená odpověď nesmějí vytvořit další prodej.
3. Platby, kelímky a storna: oddělit cenu zboží, vratné zálohy, slevy a platební pohyby. Hotovost se promítne do kasy právě jednou; karta a QR mají oddělený stav potvrzení, nikoli pouhý příznak zaplaceno od klienta. Storno přidá kompenzaci s vazbou na původní prodej a surovinový snímek; původní řádky nepřepisuje. Neaktivovat skutečného poskytovatele, automatické párování ani účetní zápisy.
4. Vlastní spotřeba a přejímka: explicitní grant se ověří pod zámkem ve stejné transakci jako odběr. Náklad se eviduje samostatně, nevznikne tržba. Ověřit souběžný prodej posledních kusů dvěma obsluhami, odebrání práv během čekání, opakované požadavky, uzávěrku proti prodeji/stornu, čekající platby, záporné zásoby a izolovanou obnovu. Nový modul nepřebírá starou offline frontu.

5. 11. 2026: [implementace a ověření B4](verification/bar-checkout-20261105/RESULTS.md). Dokončená vlastní kasa, hotovostní prodej, ruční evidence bezhotovostních plateb, vratné kelímky, neměnné snímky, uzávěrka, kompenzační storno a vlastní spotřeba. Bezhotovostní platba do potvrzení rezervuje dostupné množství a blokuje uzávěrku vlastní kasy; inventura rezervovaného produktu počká na vyřešení jeho platby. Společná zásoba se inventarizuje ve správě skladu, hotovostní uzávěrka uzavírá kasu pracovníka. Další etapou je B5 a následná úplná integrační přejímka B6. Žádné produkční nasazení, import skutečných dat, bankovní propojení ani účetní zápis nebyly provedené.

## Dokončení B5 a navazující B6

Na výslovné pokračování vlastníka navazuje [implementace a ověření B5](verification/bar-documents-20261105/RESULTS.md): soukromé PDF faktury, kontrolovaný příjem balení do předskladu a jeho kompenzační storno, samostatné cenové verze, pracovní a zveřejněné nabídky, veřejná obrazovka s aktuálními cenami, chráněný tiskový PDF lístek, provozní přehled a neměnný účetní export. [Kontrakt v1](BAR_ACCOUNTING_CONTRACT_V1.md) odděluje prodej, vratné zálohy, vlastní spotřebu, skladový příjem, pohyby kasy a uzávěrku. Ruční potvrzení QR/karty není druhým bankovním pohybem a nevytváří automatické zaúčtování.

B6 zůstává další etapou: úplná přejímka rolí a návazností, soukromé soubory spolu s DB při obnově, ztráta spojení, opuštěná kasa, časová zóna a hranice účetních období, konkrétní účetní propojení po schválení pravidel. OCR, dobropisy a zálohové faktury nejsou podporované tímto příjmem. Skutečné produkty, fotografie a lucka ještě nebyly převedené; produkční přechod zůstává zakázaný.
