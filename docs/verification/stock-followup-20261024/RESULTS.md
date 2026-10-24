# Sklady, výdeje, vratky a účetní návaznosti — 24. 10. 2026

Rozsah: zdrojová kontrola a automatická přejímka bez prohlížeče. Všechny testovací
zápisy proběhly v izolovaných syntetických databázích / dočasném soukromém úložišti.
Produkce v tomto kole nebyla měněna ani nově čtena. Žádné nasazení, skutečné platby,
odesílání zpráv, vystavování produkčních dokladů či exportů.

## Nalezené a opravené chyby

1. Výdej mohl pokračovat přes rozhodnutou úplnou účetní vratku, jestliže příjem
   nebyl přímo označen objednávkou ani aliasem `payments`, ale byl propojen pouze
   kladným přídělem. Kontrola nyní zachovává historii přídělů také po jejich uvolnění
   a sčítá platná rozhodnutí z dílčích příjmů. Příklad: vratky 100 Kč + 150 Kč
   blokují výdej objednávky 250 Kč. Zrušená rozhodnutí se nezapočítávají.
2. Sdílený příjem mohl zablokovat výdej jiné objednávky, přestože vratka měla
   výslovnou vazbu na její vlastní účetní doklad. Rozhodující je nyní původní
   vydaný doklad. Vrácení druhé objednávky neblokuje první jen kvůli společnému
   zdroji peněz. Bez dokladové vazby se uplatní přímá vazba, alias nebo historie
   přídělu; historicky nejednoznačnou vazbu automaticky nepřerozdělujeme.

Oba regresní testy na původním `origin/main` selhaly a po opravě prošly.
Při odmítnutém výdeji zůstávají fyzické kusy, rezervace a počet výdejek beze změny.
Kontrola zůstává uvnitř finančního zámku a transakce; oprava nemění původní
objednávky, částky, číslování ani schéma databáze.

## Přejímací scénáře

| Oblast | Ověřeno |
| --- | --- |
| Počáteční stavy a příjem | Neznámý stav není neomezený sklad; přesné převzetí otevřených rezervací; dodavatel a částečný příjem; opakování nepřidá druhý příjem. |
| Rezervace a výdej | Rezervace nemění fyzický stav; částečné výdeje; zákaz přečerpání a cizí položky; storno uvolní pouze nevydané kusy; dopravní událost sama nenaskladňuje. |
| Fyzická vratka | Nejvýše skutečně vydané množství; nejprve blokovaný kus; samostatné posouzení a uvolnění/vyřazení; cizí věc v péči nenavyšuje vlastní zásoby. |
| Výměna | Jiná varianta má vlastní rezervaci, původní objednávka se nepřepíše; doplatek musí být doložen; návazná vratka náhradního kusu respektuje původní cenu a doplatek. |
| Ocenění a inventura | FIFO včetně smíšených pořizovacích cen; neznámá cena zůstává NULL; oprava ocenění má historii; druhý schvalovatel; odmítnutí zastaralé inventury a zásahu do rezervací. |
| Peníze a účetnictví | Pohyb skladu nevytváří peníze; uzavření vratky vyžaduje skutečnou výplatu; příděly a opravné doklady se shodují; skladová PDF jsou v účetním ZIP. |
| Souběh | Poslední kus, opakovaný příjem, opakovaný výdej, výdej proti stornu, souběžné vratky a opakované převzetí. |
| Export | Historický sklad se rekonstruuje z pohybů/dávek, nikoli z dnešního součtu; konzistentní snapshot při souběžném příjmu; peníze se skladovými částkami nesčítají. |

## Nový celý průchod na MariaDB

Rozšířen stávající smíšený rodinný nákup se dvěma kurzy a dvěma tričky.
Po původní cenové opravě 10 Kč se vrací **jedno tričko za 125 Kč**:

- Sleva pouze na kurzy nezkracuje cenu vráceného trička.
- Převzetí přidá jeden fyzický a jeden blokovaný kus; rezervace jiné objednávky
  se nemění. Opakované převzetí vrací původní skladový pohyb.
- Po posouzení je kus znovu dostupný. Samotná vratka ani posouzení nevytvoří peníze.
- Opravný doklad -125 Kč, uvolněný příděl a skutečná vratka se shodují.
  Před výplatou se případ nesmí uzavřít; opakovaná výplata nepřidá další peníze.
- Zůstatek dokladů/přídělů **404,99 Kč**, čisté peníze **410 Kč**;
  rozdíl je původní doložený přeplatek **5,01 Kč**.
- Konečný sklad **3 fyzicky / 1 rezervováno / 0 blokováno / 2 dostupné**.
  Účetní export obsahuje odpovídající skladové PDF ověřené hashem.
- Oba kurzy dětí zůstávají aktivní. Vrácení trička jejich účasti neruší.

## Výsledky a důkazy

- Cílené účetní/skladové testy: **80 testů / 834 assertions PASS**.
- [CI 40075754458](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40075754458)
  na implementačním commitu `b1dbbd6f9ae18606ffa580cb9a90819bf8683675`:
  **1676 testů / 33403 assertions PASS**, MariaDB **10.3 a 11.4 PASS** včetně
  výše uvedeného úplného průchodu.
- [CodeQL 40075753499](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40075753499): PASS.
- Lokální nativní souběhy: sklad **9 kontrol**, výdej **6**, vratky **19** — PASS;
  důkazy v `inventory-race.json`, `fulfillment-race.json`, `returns-race.json`.
- Konzistence skladového exportu: **11 kontrol PASS**, `export-snapshot.json`.
  Negativní kontrola READ COMMITTED skutečně odhalila smíchané pohyby a dávky.
- Starší snapshot test původně selhal kvůli neindexované testovací tabulce
  oprávnění a chybějícím sloupcům objednávek. Fixture opravena podle nynějšího
  schématu; produkční oprávnění se nemění ani neoslabují.
- PHP lint a `git diff --check`: PASS. Lokální checkout smoke stále vypisuje
  dříve známé upozornění staré migrace na klíč `columns`, ale končí exit 0.

## Stav předání

Oprava a testy: PR #187. **Zatím nenasazeno.** Poslední produkční ověření
zůstává v [protokolu z 23. 10.](../accounting-production-20261023/RESULTS.md);
jeho počty nejsou novým zjištěním z tohoto kola. Neprovedena prohlížečová přejímka
ani skutečná fyzická inventura; schválení metodiky/počátečních stavů a převzetí
účetní nadále náleží odpovědným osobám.
