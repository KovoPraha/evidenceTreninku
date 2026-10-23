# Přejímka objednávek, účetních dokladů a exportů

Datum: 23. 10. 2026. Výchozí commit: `c9724de9c100766f4a6f4c90fb69abfacbb8c8c8`.
Všechny zápisy proběhly v dočasných testovacích databázích a dočasném soukromém
úložišti. Žádné produkční nasazení, migrace, změna nastavení, skutečná platba ani
odeslání účetní. B1 aplikační data nebyla upravována přes CLI.

## Nalezené a opravené chyby

1. **XLSX ztrácel vazby uložené v dokladu.** List `Polozky` neobsahoval účastníka
   ani období kurzu; `Doklady` neobsahoval původní referenci případu, VS a splatnost.
   Nové sloupce se přidávají na konec existujících listů. Čerpají výhradně z neměnného
   snapshotu dokladu, nikoli z dnešních osob či katalogu. Identifikátory a uživatelský
   text zůstávají textovými buňkami, částky čísly. Zachovány jsou počáteční nuly VS
   a ochrana před vzorci vloženými do textu. Verze nových exportů je
   `club-accounting-order-links-v4`; dříve vytvořené archivy se nepřepisují.
2. **Ochrana před druhou vratkou neviděla příjem propojený pouze přídělem.**
   Příjmy dílčích plateb nemají vždy přímé `case_id` ani alias řádku `payments`.
   Oba postupy vracení i přímý účetní zápis nyní zohledňují původní kladné příděly.
   Vazba zůstává pro kontrolu zachována také po doloženém uvolnění přídělu.
3. **Úplná vratka z více příjmů nedokončila stav objednávky.** Kanonické dokončení
   nyní sečte skutečné výplaty výslovně přiřazené objednávce přes původní doklad,
   případně přímý obchodní případ. Samostatná vratka nepřiřazeného přeplatku se do
   dokončení objednávky nezapočítává. Dokončení nepřidává další peněžní pohyb a při
   dalším exportu se nepřevezme podruhé jako nová historická vratka.

Regresní testy před opravami reprodukovaly chybějící XLSX reference a všechny tři
obcházené cesty ochrany vratek. Změny nemění vystavené doklady, původní VS,
metodiku účetní, zapnutí integrací ani schema databáze.

## Konkrétní průchod

Rozšířený MariaDB smoke nyní používá skutečné aplikační funkce pro:

1. Společnou objednávku dvou kurzů po 150 Kč a dvou triček po 125 Kč.
2. Kupón 10,01 Kč jen na kurzy: objednávka **539,99 Kč**.
3. Úhrady 145 Kč, 100 Kč a 300 Kč: skutečné příjmy **545 Kč**, příděly
   **539,99 Kč**, nepřidělený přeplatek **5,01 Kč**. Odvozený stav „zaplaceno“
   nelze převzít jako čtvrtý příjem.
4. Vystavení a opakování faktury bez nové faktury nebo dalšího čísla.
5. Převzetí každého skutečného příjmu a opakování bez dvojích peněz či přídělů.
6. Export do XLSX/ZIP včetně PDF; ověření každé položky archivu proti manifestu.
7. Cenovou opravu 10 Kč, uvolnění odpovídajícího přídělu a skutečnou vratku 10 Kč.
   Zůstává dokladová hodnota **529,99 Kč**, čisté skutečné peníze **535 Kč**
   a původní přeplatek **5,01 Kč**. Kurzy obou dětí zůstávají aktivní.
8. Druhou revizi exportu, která nahrazuje první. První ZIP a původní PDF/snapshot
   se nemění. Opakovaná výplata nevytváří další peníze.

Samostatný regresní test úplné vratky ověřuje dva příjmy 45 Kč a 60 Kč k objednávce
100 Kč: nejprve samostatná vratka přeplatku 5 Kč, poté 40 Kč a 60 Kč přiřazených
dokladu. Objednávka se dokončí jako vrácená až po poslední úhradě; 5 Kč ji nemůže
předčasně uzavřít. Test ověřuje také opakování a ochranu před druhým převzetím výplaty.

## Ověření

- Cílená účetní sada: **73 testů / 456 assertions PASS**.
- Nativní MariaDB průchod nad plným migračním katalogem: PASS; nový helper
  `tests/Support/FamilyAccountingAcceptance.php` je součástí běžného checkout smoke.
- CI nyní instaluje závislosti pro PDF/XLSX také pro MariaDB joby, aby se průchod
  ověřoval na obou podporovaných verzích 10.3 a 11.4.
- PHP lint a kontrola diffu: PASS.
- Vygenerované XLSX bylo načteno druhým nástrojem a vykresleno; zkontrolovány
  oba upravené listy. PDF vykresleno přes Poppler: jeden čitelný list, dvě děti,
  období, sleva, VS a shodný součet. Ukázka obsahuje pouze syntetická data.
- Průběžná plná Windows sada: 1667 testů, 33292 assertions, 3 přeskočené testy
  a jediné známé selhání scanneru `posix_geteuid`; nejde o zelený Windows výsledek.
  Následné testy jsou doloženy cílenou sadou; finální Linux výsledek je v kontrolách PR.
- Lokální MariaDB vypisuje známé upozornění starší migrace
  `20260922190000_member_fee_standing_orders.php:13` (`columns`). Nasazená migrace
  se nemění; celý průchod končí exit 0.

Lokální syntetická ukázka: `output/accounting-followup-20261023/` obsahuje původní
XLSX, fakturu, ZIP revize 1 a 2, kontrolní JSON a renderované náhledy. Nejde o data
určená k zaúčtování. Testovací databáze a soukromé pracovní soubory se uklízejí.

## Co zůstává otevřené

- Prohlížečová přejímka a zápis výsledků do B15/B18 přes standardní UI jsou odložené.
- Export zůstává označen NEÚPLNÝ do převzetí metodiky, zdrojů a počátečních stavů
  odpovědnými osobami. Technické testy nenahrazují převzetí účetní.
- Součet vratek bez výslovné vazby na doklad/případ se nesmí automaticky domýšlet.
- Změna je připravena v kódu a testech; tento úkol nezahrnuje produkční nasazení.
