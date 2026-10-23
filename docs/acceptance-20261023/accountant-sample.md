# Ukázkové předání účetní

Připraveno 23. 10. 2026. **Stav převzetí: NEPŘEVZATO.** Ukázka je určena pro společnou kontrolu hospodáře a účetní. Oddíl podle zadání není plátcem DPH, vede jednoduché účetnictví a předává XLSX + doklady. Účetní program a závazná metodika ještě nejsou potvrzené (D01–D02).

Všechny podklady v této ukázce pocházejí ze syntetického místního prostředí. Nevznikl produkční doklad, zápis ani odeslání. Původní XLSX a PDF nebyly přepsány ani upraveny. Z databáze se četla pouze metadata již existujícího exportu v transakci READ ONLY.

## Co otevřít

Balík `output/uat-accountant-20261023/ucetni` obsahuje:

1. `originalni-export-22.zip` – původní aplikací vytvořený archiv, převzatý beze změny.
2. `rozbaleny-original/ucetni-podklady.xlsx` – tentýž XLSX z archivu, 10 listů.
3. `rozbaleny-original/doklady/9.pdf` – ukázková faktura 250 Kč.
4. `rozbaleny-original/doklady/10.pdf` – její opravný doklad −125 Kč.
5. `rozbaleny-original/klubove-vyporadani.pdf` – podklady závazků, záloh a odměn, 13 stran.
6. `rozbaleny-original/data.json` a `manifest.json` – neměnný snímek dat a kontrolní součty. Účetní je nemusí ručně zpracovávat.

ZIP obsahuje 21 souborů: XLSX, 18 PDF a dva JSON. Mezi PDF je 10 vydaných účetních dokladů, dva skladové doklady, souhrn klubového vypořádání a pět testovacích příloh rozhodnutí. Přílohy `rozhodnuti/R-*.pdf` obsahují jen text **LOCALHOST TEST**. Nejsou podepsaným rozhodnutím ani účetním podkladem skutečné osoby.

## Identita předání

| Údaj | Hodnota |
|---|---|
| Export / revize | #22 / revize 5, předchozí export #21 |
| Období | 24. 9. – 23. 10. 2026; jde o klouzavé testovací období, nikoli kalendářní měsíc |
| Snímek | 23. 10. 2026 05:59:28 UTC = 07:59:28 Europe/Prague |
| Stav v aplikaci | NEÚPLNÝ (`incomplete`) |
| Peněžní jednotky | XLSX a PDF v Kč; JSON v celých haléřích |
| SHA-256 ZIP | `b7ca30348c812e584df076d5c8d2b6bd32f45fb430d3c2d7ae781f44c2428f66` |
| SHA-256 XLSX | `6903d4ffc7d218f4f23aa7a4c0b271c7530bb0680403c3d27c01e18340ec7850` |

Revize představuje **úplnou náhradu předchozí revize stejného období**. Nepřičítat revizi 5 k revizi 4. Tento balík dodává pouze revizi 5; změny vůči předchozí revizi jsou v Manifestu.

## Rozsah a kontrolní součty

Export je globální: obsahuje více nezávislých syntetických scénářů, opakované testy a záměrně neúplné případy. Není to vyčištěný výběr jedné rodiny ani doklad kompletního účetnictví oddílu. Pro srozumitelnou kontrolu je níže vyznačen jeden souvislý případ, ale původní soubory nebyly filtrovány.

| List XLSX | Datové řádky bez záhlaví | Význam |
|---|---:|---|
| Manifest | 197 | Období, revize, omezení, výjimky, změny a nepřevzaté zdroje |
| Doklady | 10 | 3 faktury, 5 opravných dokladů, 2 doklady k členskému příspěvku; všechny vydané |
| Polozky | 10 | Položky těchto dokladů; nesčítat znovu s Doklady |
| Penize | 78 | Skutečné peněžní pohyby; 61 v období, 17 starších referencí MIMO OBRAT |
| Parovani | 78 | Přidělení a uvolnění částek vůči případům; nepředstavuje další peníze |
| Cleneni_penez | 3 | Doložené účetní členění; v testovacích datech není úplné |
| Predpisy | 42 | Vazby 5 původních dokladů + 37 pohledávek k ověření |
| Saldo | 160 | Různé druhy stavů; nesčítat celý list do jediného zůstatku |
| Vydaje | 64 | Verze výdajů, zálohy, odměny a návraty plateb |
| Sklad | 261 | Počátky, konce, pohyby, ceny a čerpání šarží; není peněžní deník |

Všechny částky jsou CZK. V listu Penize činí **obrat v období**: kladné pohyby 13 400 Kč, záporné pohyby −78 500 Kč, čistý obrat −65 100 Kč. Starších 17 pohybů má čistý součet −379,97 Kč; do obratu období nepatří. Součet všech 78 řádků je −65 479,97 Kč a není konečným stavem účtů. Počáteční stavy jsou odděleně.

Dokladové součty: faktury +750 Kč, doklady k členskému příspěvku +400 Kč, opravy −475 Kč, celkem +675 Kč. **Dokladový součet se nerovná peněžnímu obratu.** Vystavení, oprava, úhrada a vrácení peněz jsou oddělené události.

Saldo obsahuje 27 účtů (16 pokladen, 11 bankovních), 40 nepřidělených příjmů, 4 rozhodnutí o vratkách, 26 závazků výdajů, 13 záloh, 13 závazků odměn a 37 pohledávek k ověření. Jednotlivé druhy se vzájemně nesčítají. `NEZNÁMÉ` se nesmí změnit na nulu.

## Souvislý příklad: účet #27

Identifikátor příkladu `LOCALHOST NEPLATIT R37 717ef5b039`, kód `r37-d1390ffc2f5a86`. Účet je v datech typu **bank**. Není to potvrzení skutečné hotovosti ani bankovní výpis. Odkazy na buňky níže platí pro nezměněný dodaný XLSX; po uživatelském seřazení vyhledávat podle ID.

| Událost | ID | Kč | Kde hledat |
|---|---|---:|---|
| Počáteční stav k 24. 9. | účet 27 | +10 000 | Saldo, řádek 28, D28 |
| Odeslaná záloha na akci | pohyb 75, záloha 13 | −5 000 | Penize, řádek 76 |
| Skutečně vrácený zbytek zálohy | pohyb 76, záloha 13 | +800 | Penize, řádek 77 |
| Jedna společná úhrada | pohyb 77 | −1 100 | Penize, řádek 78 |
| Přijatá platba za oblečení | pohyb 78, objednávka 29 | +100 | Penize, řádek 79 |
| Čistý obrat | čtyři pohyby | −5 200 | Saldo E28 |
| Konečný účetní stav | účet 27 | **4 800** | Saldo F28 |

Kontrolní rovnice: **10 000 − 5 000 + 800 − 1 100 + 100 = 4 800 Kč**.

Podrobnosti vypořádání:

- Záloha #13: vydáno 5 000 Kč, schválený výdaj #29 z ní čerpá 4 200 Kč, skutečně vráceno 800 Kč, nevyúčtovaný zůstatek 0 Kč. Výdaj je na řádku Vydaje 30, záloha na řádku 44. Nula ve sloupci „Uhrazena část“ u výdaje #29 znamená, že nebyl znovu samostatně proplacen; byl spotřebován ze zálohy.
- Pohyb #77: 600 Kč proplácí osobní výdaj #30 (příděl #76) a 500 Kč odměnu #13 (příděl #77). Dohromady právě 1 100 Kč. Podklad odměny a přidělení nepřidávají další výdaj do Penize.
- Odměna #13: skutečně uzavřený trénink #29, revize docházky #33, 120 minut dne 3. 10.; testovací pravidlo #13 stanoví 250 Kč/h, tedy 500 Kč. Schváleno i vyplaceno 500 Kč, závazek 0 Kč. Podklad je ve Vydaje na řádku 57 a v klubovém PDF na straně 10. Tato testovací sazba není navrženou klubovou sazbou ani mzdovým výpočtem.
- Objednávka #29: přijato 100 Kč, příděl #78 váže pohyb na objednávku. Varianta #49: převzaty 2 kusy do evidence, vydán 1, konec 1 kus, rezervace i blokace 0. Doložená testovací cena 60 Kč/kus, konečné ocenění 60 Kč (Sklad, řádek 71). Cena skladu není další peněžní výdaj. Stav před prvním dnem období není doložen a zůstává NEZNÁMÉ; počáteční příjem 2 kusů je pohyb uvnitř období.

**Co v příkladu záměrně nepovažujeme za hotové:** pohyby #75–78 nemají účetní členění, účet #27 nemá uzavřené odsouhlasení k 23. 10. a není doložen skutečný výpis. K objednávce #29 není v tomto exportu vydaná faktura. Výdaje #29 a #30 mají jen syntetický text „paper original“, nikoli přiložené dodavatelské účtenky. Nejsou dodatečně domýšleny ani podepisovány. Tento příklad ověřuje vazby částek, nikoli úplné účetní převzetí.

## Samostatná dokladová ukázka

Doklady patří k jinému testovacímu případu, objednávce #23. Nemají být spojovány s účtem #27 či objednávkou #29.

| Podklad | Částka | Ověřit |
|---|---:|---|
| Faktura #9, `TESTFA-2026-000003` | 250 Kč | Dva kusy, vystaveno 23. 10., splatnost 30. 10.; VS `2680000008`; neplátce DPH |
| Opravný doklad #10, `HTTPOPR-2026-000005` | −125 Kč | Jeden kus; výslovná vazba na fakturu #9 a důvod opravy |
| Součet původního a opravného dokladu | 125 Kč | Snížení dokladové hodnoty samo nedokládá vrácení peněz |
| Doklad #3, `HTTPCLEN-2026-000002` | 200 Kč | Členský příspěvek, účastník odlišný od odběratele/plátce, období roku 2026 |
| Opravný doklad #4 | −50 Kč | Vazba na #3; po opravě 150 Kč, bez tvrzení o skutečné úhradě |

U objednávky #23 je zdrojová platba #24 mezi nepřevzatými zdroji: chybí mapovaný účet a skutečné datum. Je tedy správné, že příklad s fakturou nedokládá kompletní peněžní vypořádání. Účet a VS na PDF jsou testovací; **neplatit**.

## Výjimky, které zůstávají otevřené

Manifest obsahuje **111 výjimek**: 75 pohybů bez úplného účetního členění, 27 účtů bez uzavřeného odsouhlasení, 7 skladových výjimek, 1 upozornění na historické stavy pohledávek a 1 chybějící převzetí zdrojů/metodiky. Vedle nich je samostatně **14 nepřevzatých platebních zdrojů** za nominálně 2 225 Kč, všechny s chybějícím účtem a datem. Těchto 2 225 Kč nelze přičíst k obratu ani přiřadit do období bez podkladů.

Přítomnost výjimky není automaticky chyba programu. U této směsi testů je neúplnost očekávaná. Pro skutečné předání musí hospodář každou relevantní výjimku doloženě vyřešit nebo s účetní výslovně popsat rozsah neúplného předání. Nepřepisovat export tak, aby problémy zmizely.

## Checklist pro účetní a hospodáře

Výsledek každého bodu doplní člověk: PROŠLO / NEPROŠLO / BLOKOVÁNO, jméno, datum a poznámku. Prázdné položky neznamenají souhlas. Technicky provedené kontroly jsou samostatně níže.

| ID | Konkrétní úkon a očekávaný výsledek | Výsledek / poznámka |
|---|---|---|
| UC01 | Otevřít ZIP, XLSX a PDF ve skutečně používaném programu. 10 listů a české texty jsou čitelné. | __________ |
| UC02 | Potvrdit období, revizi 5 a náhradu revize 4. Starší revizi znovu nepřičítat. | __________ |
| UC03 | Ověřit počet 10 vydaných dokladů a 10 položek proti seznamu PDF. | __________ |
| UC04 | Na faktuře #9 ověřit vystavovatele, odběratele, datum, splatnost, položky, 250 Kč, neplátce, VS. Testovací vystavovatel nesmí přejít do ostrého provozu. D01. | __________ |
| UC05 | Na opravě #10 ověřit −125 Kč, důvod a původní číslo. Rozlišit opravu dokladu od skutečné výplaty vratky. | __________ |
| UC06 | Na příspěvku #3 ověřit plátce, odběratele, účastníka a období; potvrdit vhodný typ dokladu pro klubovou praxi. D01–D03. | __________ |
| UC07 | Ve Penize filtrovat „V OBDOBÍ“: 61 řádků, +13 400 / −78 500 Kč. Vysvětlit zbývajících 17 řádků MIMO OBRAT. | __________ |
| UC08 | U účtu #27 ověřit 4 pohyby a rovnici 10 000 −5 200 =4 800 Kč. Potvrdit, že stále chybí skutečný výpis a uzavřené odsouhlasení. | __________ |
| UC09 | U zálohy #13 ověřit 5 000 =4 200 +800, nulu nevyúčtovaného zůstatku a chybějící skutečné účtenky. D02. | __________ |
| UC10 | U pohybu #77 ověřit 600 +500 =1 100 Kč. Odměnu ani příděly nezaúčtovat jako další peníze. | __________ |
| UC11 | U odměny #13 ověřit 120 minut ×250 Kč/h =500 Kč a oddělené schválení/výplatu. Potvrdit právní titul, případné odvody a částku k výplatě až podle D04–D06. | __________ |
| UC12 | Rozlišit sedm druhů salda; nesčítat stavy účtů, pohledávek, záloh a závazků dohromady. `NEZNÁMÉ` nepřevádět na nulu. | __________ |
| UC13 | Na variantě #49 ověřit konec 1 kus /60 Kč; skladové ocenění se nepřičítá k Penize. Skutečná inventura čeká na D09. | __________ |
| UC14 | Projít 111 výjimek a 14 nepřevzatých zdrojů; určit odpovědnosti a konkrétní doklady pro uzavření skutečného období. | __________ |
| UC15 | Potvrdit členění příjmů, výdajů, záloh, vratek a převodů v jednoduchém účetnictví. D02; testovací kódy nejsou odsouhlasená metodika. | __________ |
| UC16 | Ověřit, jak cílový účetní program převezme XLSX. Potvrdit ruční převzetí nebo konkrétní importní mapu; kompatibilita s neznámým programem zatím není prokázána. D01. | __________ |
| UC17 | Potvrdit schvalovatele, zastupování, archivaci podkladů a odpovědnost za měsíční předání. D05, D11, D13. | __________ |
| UC18 | Zapsat otevřené vady a požadované opravy do protokolu. Převzetí formátu není převzetím úplnosti skutečných dat ani povolením nasazení. D15–D16. | __________ |

## Technická kontrola provedená při přípravě

- SHA-256 původního ZIP odpovídá uloženému `archive_sha256` exportu #22 i reportu syntetického měsíce. Kopie má stejný otisk.
- Ověřeny délky a SHA-256 všech 20 položek manifestu; samotný manifest je 21. soubor. Datový snímek odpovídá uloženému `snapshot_sha256`.
- XLSX se načítá jako platný ZIP/XML. Zkontrolováno všech 10 listů, počty řádků a konkrétní hodnoty výše. Neobsahuje vzorce ani chybové buňky; je to statický export, nikoli přepočítávaný model. Kontrola nenahrazuje otevření v účetní používaném Excelu/programu (UC01, UC16).
- Všech 18 PDF, celkem 30 stran, načteno, vyrenderováno Popplerem a vizuálně zkontrolováno: české texty, tabulky, částky a stránkování jsou čitelné, bez zjevného překrytí či ořezu. Pět příloh tvoří záměrné testovací stránky.
- Nedošlo ke změně aplikace, databáze, účetního stavu ani původních souborů; vytvořeny byly pouze kopie a průvodní podklady. Účetní nebylo nic odesláno.

## Záznam lidského převzetí

**Výchozí stav: NEPŘEVZATO.** Automatizované kontroly tento stav nemění.

| Pole | Vyplní odpovědná osoba |
|---|---|
| Hospodář / datum | ______________________________ |
| Účetní / datum | ______________________________ |
| Cílový účetní program a verze | ______________________________ |
| Převzatý rozsah: formát / konkrétní skutečné období | ______________________________ |
| Stav: převzato / převzato s výhradami / nepřevzato | ______________________________ |
| Výhrady, čísla vad, termíny a odpovědnosti | ______________________________ |
| Podpis hospodáře | ______________________________ |
| Podpis účetní | ______________________________ |

Rozhodnutí D01–D02, D04–D06, D09, D11, D13, D15–D16 zůstávají odložená. Ukázka neurčuje za účetní účtování, daňový režim odměn ani povinnost vystavit doklad v konkrétní ostré situaci. Provozní nasazení vyžaduje samostatné povolení.
