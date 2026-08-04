# M1.3 – propojení soupisek s evidencí tréninků

Plánovaný i skutečný trénink může být propojen s více KIS soupiskami. Stávající skupiny a podskupiny zůstávají funkční a není vytvořen druhý seznam sportovců.

## Bezpečný tok dat

1. Trenér v plánovači vybere nula až více aktivních soupisek platných v den tréninku.
2. Uložení vytvoří vazby v `training_roster_links` a snapshot očekávaných členů v `training_roster_expected`.
3. Členství se posuzuje podle `valid_from` a `valid_to` k datu tréninku. Neaktivní, neexistující nebo časově neplatná soupiska způsobí rollback celého uložení.
4. Při otevření `formular.php?plan_id=…` se snapshot očekávaných členů pouze předvyplní do stávajícího výběru účastníků. Sportovec přítomný ve více soupiskách se zobrazí jen jednou.
5. Skutečná docházka vzniká až běžným ručním odesláním evidence a zůstává v `trenink_sportovec`. Samotné plánování do této tabulky nikdy nezapisuje.
6. Při uložení skutečné docházky se historický snapshot plánu atomicky zkopíruje také ke skutečnému tréninku. Pozdější změna soupisky tím nezmění původní očekávání.
7. `kis_training_a07_admin.php` porovná očekávané a skutečné účastníky a ukáže přítomné podle plánu, chybějící i neočekávané osoby. Průvodce je dostupný pouze na localhostu.

Snapshot uchovává kód a název soupisky, identitu člena a jeho interval platnosti. Pozdější změna soupisky tak nepřepisuje historické očekávání již naplánovaného tréninku. Editace vazeb snapshot vědomě vytvoří znovu.

## Kompatibilita

- Plán bez vybrané soupisky je podporovaný legacy režim.
- `planovane_treninky_podskupiny`, legacy `podskupina_id` a výběr skupiny zůstávají beze změny.
- Při zaevidování plánu smí běžný trenér použít pouze svůj plán, hlavní trenér libovolný dostupný plán. Datum skutečného tréninku musí odpovídat plánu.
- Skutečný `treninky` záznam dostane automaticky vlastní neměnnou kopii plánovacího snapshotu; docházka se dál ukládá výhradně do legacy tabulky `trenink_sportovec`.

## Migrace

`20260804130000_training_roster_bridge` je idempotentní pro MySQL/MariaDB i SQLite a přidává jen tabulky `training_roster_links` a `training_roster_expected`.
