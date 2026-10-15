# UAT finančního salda v prohlížeči – 15. 10. 2026

Prostředí: `http://localhost/evidencePavel/`, výhradně syntetické identity ze `bin/seed-local-demo.php`, lokální MariaDB. Žádná z níže uvedených zkušebních plateb není skutečný bankovní pohyb. Po scénářích bylo omezení přihlášek v lokální databázi opět vypnuto.

| Cesta a role | Skutečný průchod a výsledek | UX / oprava |
| --- | --- | --- |
| Rodič – přehled a sportovní profil | Přihlášení, tři dostupné profily, otevření profilu sportovce A, zobrazení otevřeného a prodleného salda. Po částečné platbě profil správně rozlišil nominální předpis 3 000 Kč a zbývajících 2 317,50 Kč. | Doplněno vysvětlení zůstatku po započtení plateb a české tvary počtů. |
| Rodič – e-shop | Varianta produktu, členská cena, košík, kupón `LOCAL10`, výběr sportovce, objednávka `KP261015EC58FFC378` za 1 417,50 Kč. QR bylo výslovně označeno jako neplatné pro skutečnou platbu. | Aplikace kupónu původně ztratila výběr sportovce; výběr nyní přetrvá i po obnově formuláře. Po úhradě se zobrazilo „Připravuje se“ a „Zaplaceno“. Opraven tvar „1 položka“. |
| Hospodář – měsíční příspěvek | Založení plánu U15 za 500 Kč, náhled říjnového běhu, vytvoření jediného oprávněného předpisu; člen bez plátce byl výslovně vyřazen. | Stavy náhledu jsou česky a počty používají správný tvar. |
| Hospodář – roční úhrada | Pro sportovce B a období 10/2026–09/2027 byla zobrazena sleva 10 %: 12 × 450 Kč = 5 400 Kč. Po místním potvrzení reference `TEST-LOCAL-ANNUAL-001` vzniklo 12 samostatně uhrazených měsíčních předpisů. Neplatná zpětná sleva a člen bez plátce byly odmítnuty. | Roční úhrady mají kontextový vstup z plánů i ze seznamu předpisů. |
| Hospodář – přijatá platba | Platba `TEST-LOCAL-RECEIPT-001` 600 Kč se rozdělila na 500 Kč příspěvek a 100 Kč objednávka. Duplicitní reference byla odmítnuta. Dalších 2 000 Kč pod `TEST-LOCAL-RECEIPT-002` doplatilo objednávku a ponechalo kredit 682,50 Kč. | Přímý vstup „Přijatá platba“ je ze seznamu předpisů. Technické názvy zdroje a platební metody v seznamu mají čitelné české popisky. |
| Rodič – trénink a akce při dluhu | Mimořádný předpis 3 000 Kč po lhůtě s použitím kreditu vytvořil dluh 2 317,50 Kč. Při limitu nad 2 000 Kč prošla odpověď „Nezúčastním se“, ale nová účast na tréninku, bezplatné akci i placené akci byla zamítnuta. | Chyba placené akce se původně objevila u jiné karty; nyní je přímo u příslušné akce. |
| Správce – pravidla | Na lokální instanci bylo pravidlo zapnuto s lhůtou 14 dní, limitem 2 000 Kč a upozorněním od 5 000 Kč; po scénářích opět vypnuto s auditovaným důvodem. | Formulář s potvrzením a vysvětlením dopadu je srozumitelný i v mobilní šířce 390 px. |
| Hospodář – navigace | Z rozcestníku jsou tři hlavní denní cíle: platby a vratky, klubové platby, plány příspěvků. Z klubových plateb vedou přímé odkazy k roční úhradě, přijetí platby a pravidlům. | Vyřešeno první selhání jednotkového testu: hlavní nabídka opět zůstává krátká, ale nové činnosti nevyžadují hledání v rozbalených pokročilých nástrojích. |

## Hranice ověření

- Prohlížečové zápisy proběhly **jen lokálně**. Produkční testovací okno pro zápisy není touto zprávou obnoveno a produkční přihlášky, objednávky ani bankovní platby se nesmějí vytvářet pouze na základě tohoto lokálního UAT.
- Bankovní příjem byl zkušebně zadán po simulovaném ověření, nikoli pomocí reálného Fio výpisu. Fio zůstává režimem návrh–ruční potvrzení.
- Testy aplikace a migrací doplňují prohlížečový průchod; prohlížeč sám nedokazuje souběh transakcí, idempotenci ani historické hrany všech rolí.
- Před produkčním zapnutím omezení je nutný provozní audit starých objednávek společných účtů, sald a skutečně ověřených bankovních pohybů. Migrace ponechává omezení vypnuté.
