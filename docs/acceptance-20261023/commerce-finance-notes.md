# Přejímka obchodu, skladu a financí — podklad pro obsluhu

Datum přípravy: 23. 10. 2026. Zdroj: místní kopie `evidencePavel-finance-b1-20261022`, základ `abbb4be9`. První část vznikla čtením zdrojů. Následně byly přes izolované UI připraveny katalogové předpoklady, viz fixtures.md a fixtures.json. Lidské scénáře se nespouštěly, nevznikly objednávky, platby ani skladové pohyby. Produkce se neměnila.

`commerce-finance.json` obsahuje 20 scénářů F01–F20 s konkrétními rolemi, názvy polí/tlačítek, daty a výsledky. Všechny mají lidský stav **NEPROVEDENO**. Odhad je 485 minut čisté práce plus přihlášení, příprava fixture a řešení odchylek. Doporučené rozložení jsou tři půldny; součet není příslib termínu přijetí.

## Co znamená připravenost

`preparation_status=PŘIPRAVENO` znamená, že je sepsána cesta přes skutečné formuláře. Neznamená to, že je již založena testovací identita, běží prostředí nebo byly splněny závislosti. **Blokace předchůdce blokuje také všechny navazující scénáře**, i když samotný formulář navazujícího scénáře existuje. `BLOKOVÁNO` uvádí konkrétní chybějící přípravu; testující ji nesmí obcházet SQL ani ručním sestavováním POST požadavku.

Před prvním testem správce ověří izolaci, stav zálohy, vypnuté vnější odesílání a verzi aplikace. Pro každý průchod přidělí jedinečný alfanumerický suffix B, aby se nepletly doklady, reference a jiné syntetické automatické testy. ID se opíší z UI nebo z pracovního XLSX, nikdy neodhadují. Data jsou pouze modelová: skutečné příjmy, doklady a účetní souhlasy se jimi nenahrazují. Při potvrzování formuláře má být v důvodu vždy **SIMULACE UAT-B**.

## Doporučený průchod

1. Bez katalogových fixture lze začít F01 (dokladové nastavení), F02 (peníze), F03 (samostatná uzávěrka) a F18 (neúplný podklad). F03 má vlastní pokladnu, aby uzavření nezablokovalo další testy.
2. Připravené varianty M/L mají ID 50/51 (produkt 44), inventurní I má ID 52 (produkt 45). Jejich fyzické počátky stále provede tester; následně F04–F09. F05 je příjem dodavatele, F06–F09 souvislá objednávka dvou kusů, jeden vydaný a jeden opravený nevydaný.
3. F10 vrací jediný fyzicky vydaný kus z předchozího řetězce. F11 a F12 musí mít **vlastní nové objednávky**, protože kus F10 již byl vrácen. F13 používá vlastní variantu I. Nevydaný kus opravený F09 nevytváří fyzický příjem.
4. F14 přebírá první objednávku Anny z UAT-P06 (kurz 1 200 Kč, kapacita 1). F15/F16 mohou použít identity 326 a 327; jde o dva různé superadminy, nikoli test omezených práv. F17 potřebuje nový samostatný 60minutový trénink podle UAT-P10; nepoužít P11 opravený na 50 minut.
5. F19 kontroluje celý export, F20 nácvik revizí a předání druhému testujícímu. Před finanční částí F10/F12 lze připravit dílčí pracovní export pro získání přesného ID přídělu z listu **Parovani**; nejde o dokončení F19 ani o skutečné předání účetní.

## Otevřené přípravy a hranice UI

| Položka | Co chybí před ručním testem | Bezpečný postup |
|---|---|---|
| F04/F13 | Vyřešeno přípravou variant 50/51/52 | Připravené a lokálně zveřejněné; počáteční stav teprve provede tester. Viz fixtures.md. |
| F10/F12 | Přesné ID původního finančního přídělu | Získat z listu Parovani pracovního exportu. `accounting_ledger_admin.php:48` požaduje ID; stránka neposkytuje samostatný seznam přídělů pro jeho pohodlný výběr. To je omezení obsluhy, nikoli důvod hádat ID. |
| F12 | Testovací pravidlo pro zdroj Doplatek výměny / Faktura | Založit skutečným formulářem účetních pravidel podle F01. |
| F14 | První objednávka Anny z UAT-P06 | Kurz 1 200 Kč, kapacita 1; nezakládat druhý kurz. Samotná platba neobchází souhlasy. |
| F15/F16 | Vyřešeno existujícími identitami 326 a 327 | 326 podatel/příjemce, 327 schvalovatel. Oba superadmin; tato dvojice není důkaz omezených práv. |
| F17 | Nový samostatný uzavřený 60minutový trénink dle UAT-P10 | Nepoužívat P11 opravený na 50 minut. Neodvozovat historickou činnost ani reálnou sazbu. |
| F20 | Druhý člověk, který skutečně zkontroluje kopii | Není-li přítomen, odložit krok Převzato účetní; nikdy nepotvrzovat automaticky. |

Pole skutečného data mají omezení na minulost či dnešek. Pevná modelová data jsou v říjnu 2026; při přejímce po tomto datu jsou vhodná jako historický říjnový test. Pokud se použije jiné období, změnit **společně** období dokladů, sazeb, tréninku a exportu a zapsat to do protokolu. Žádnou skutečnou účetní metodiku ani lhůtu tím nenastavovat.

## Co ověřit při práci s účetním vzorem

- Oddíl není plátcem DPH a vede jednoduché účetnictví; vzor nepředstírá schválení účetním programem ani zpracování mezd.
- Vydání faktury, přijetí platby, zařazení pohybu, fyzický výdej a vrácení peněz jsou samostatné události. Přejímka má odhalit jejich záměnu.
- Výdaj -1 000 Kč a jeho přiřazení k výdajovému případu představují jednu platbu, nikoli dva výdaje. Záloha 2 000 Kč, čerpání 1 500 Kč a vrácení 500 Kč musí být dohledatelné bez druhého fiktivního hotovostního výdaje.
- UI archivu výslovně zobrazuje **NEÚPLNÝ / Neúplný pracovní balík**. Úspěšný technický export, stažení ani simulované převzetí tento příznak neopravňují odstranit. Viz `accounting_exports_admin.php:30` a `:41`.
- Nová revize stejného období nahrazuje předchozí jako celý balík. Účetní nesmí bez kontroly sčítat obě revize.
- Starší referenční pohyby **MIMO OBRAT** nejsou běžným obratem předávaného období; přesné vysvětlení je v Manifestu (`includes/accounting_export.php:103`).
- Kvůli jiným syntetickým testům v lokální databázi nevychází součet všech řádků archivu automaticky jako součet našich dvaceti scénářů. Kontrolní součty se provádějí podle zaznamenaných ID běhu.

## Odložená rozhodnutí

D01–D02: skutečný vystavovatel, řady, členění a účetní převzetí formátu. D03: pravidla programu. D04–D06: sazby, nezávislé schvalování a titul odměn. D09: skutečná inventura. D13: skutečná obsluha a pilot. D16: první skutečné převzetí měsíce. Modelová data v tomto zadání žádné z těchto rozhodnutí neuzavírají. D15 (nasazení/produkční importy) není tímto balíkem povoleno.

Samostatná stará finanční událost (`udalosti/vyuctovat.php:133`, tlačítko Uzavřít událost) není totožná s uzávěrkou peněžního účtu F03. Tento dokument za její úplný lidský retest nevydává automatický test ochrany uzavřených událostí. Před pilotem musí správce zvolit konkrétní syntetickou událost a související účtenky pro přejímku původní agendy; chybějící fixture nepředstírat jako PASS.

Základem byly `docs/CURRENT_STATE.md`, `docs/HANDOFF_CURRENT.md`, `docs/CLUB_COMPLETION_DECISIONS.md` a citované formuláře. Nebyl použit žádný produkční export, žádné přihlašovací údaje, SQL změny ani skutečné účetní doklady.
