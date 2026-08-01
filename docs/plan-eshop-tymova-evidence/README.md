# Program rozšíření Evidence: e-shop a týmová evidence

Stav dokumentu: návrh k rozhodnutí  
Datum auditu: 1. 8. 2026  
Implementace nových funkcí: **nezahájena**

## Účel

Tato sada převádí mluvené zadání do proveditelného programu. Má dvě hlavní
produktové větve:

1. interní a veřejný e-shop nahrazující dnešní Shoptet,
2. úplná členská a týmová evidence, která nejprve integruje a později může
   nahradit KIS.

Společným základem jsou identity, platby, kreditní ledger, audit, notifikace,
bezpečný deploy a provozní dohled.

## Stručný verdikt

**Ano, projekt lze postavit na současném základu, ale ne bezpečně tak, že se
hned paralelně začne programovat e-shop, wallet a náhrada KIS.**

Produkční infrastruktura je použitelná: GitHub Actions dne 31. 7. 2026
úspěšně vytvořily DB zálohu, nahrály commit `58ec8ec`, web vrátil HTTP 200 a
produkční kontrola potvrdila schéma `2.20.2` na PHP `8.2.32`.

Před funkční implementací musí proběhnout **Fáze 0 – foundation gate**:

- sjednotit lokální checkout se vzdáleným `main` a uzavřít rozpracované
  deploy změny,
- určit kanonickou identitu člena, rodiče, trenéra a veřejného zákazníka,
- oddělit tréninkovou odměnu od peněžně dobíjeného kreditu,
- zavést testovací infrastrukturu a staging/test DB,
- ověřit obnovu databáze ze zálohy, nejen vytvoření zálohy,
- přijmout doménové a integrační kontrakty v této dokumentaci.

Po splnění těchto bodů lze bezpečně spouštět pracovní vlákna po vertikálách.

## Dokumenty

| Dokument | Obsah |
|---|---|
| [01 – Audit připravenosti](01-audit-pripravenosti.md) | Co reálně funguje, co chybí a co blokuje start |
| [02 – Zadání a rozhodnutí](02-zadani-a-rozhodnuti.md) | Normalizované požadavky, rozsah a otevřené otázky |
| [03 – Cílová architektura](03-cilova-architektura.md) | Domény, vlastnictví dat, integrační hranice a návrh modelu |
| [04 – Roadmapa a brány](04-roadmapa-a-brany.md) | Etapy, závislosti a měřitelná akceptační kritéria |
| [05 – Řízení vláken](05-rizeni-vlaken.md) | Pravidla pro řídicí a pracovní vlákna, předávací protokol |
| [06 – Program board](06-program-board.md) | Aktuální stav, pořadí prvních úkolů a podmínky jejich startu |
| [Session handoff](SESSION_HANDOFF.md) | Obnovitelný stav řídicího tasku, aktivní práce a další krok |
| [Prompt nového řídicího tasku](PROMPT-NOVE-RIDICI-VLAKNO.md) | Text ke zkopírování při otevření pokračujícího tasku |

## Rozhodnutí doporučená ke schválení

1. Začít jako **modulární monolit v tomto repozitáři**, ne jako několik nových
   nezávislých aplikací.
2. Starý návrh „externí e-shop přes Evidence API“ označit za nahrazený;
   e-shop bude součástí Evidence, ale s vlastními doménovými tabulkami a
   službami.
3. Shoptet použít jako jednorázový, opakovatelný import produktů, ne jako
   dlouhodobý obousměrný master.
4. KIS během přechodu ponechat jako zdroj a Evidence provozovat v shadow mode;
   přepnout zdroj pravdy až po prokázané paritě.
5. Pro klubové akce vytvořit novou doménu. `ucto_udalosti` ponechat účetnictví;
   není to registrační systém členů.
6. Platby sdílet mezi e-shopem, událostmi, členskými předpisy a dobíjením, ale
   objednávku, platbu, wallet a dopravu držet jako oddělené stavové stroje.
7. Wallet postavit na neměnném ledgeru. Tréninkové odměny a peněžní dobití
   nejprve vést jako dva oddělené typy hodnoty.
8. Zásilkovnu, automatické Fio párování a kombinovanou platbu kredit + karta
   odložit až za funkční osobní odběr a jednoduchou jednu platební metodu.

## Co tento plán záměrně nedělá

- nespouští produkční DB migrace,
- nemění současné obchodní procesy,
- neruší KIS ani Shoptet,
- neurčuje právní a účetní klasifikaci dobíjeného kreditu,
- neslibuje termín bez uzavření otevřených rozhodnutí a kapacit týmu.

## Zdroj zadání

Mluvený záznam:
`C:\Users\Marek\Desktop\downloads\Vývoj e-shopu a týmové evidence s integrací KIS systému.txt`

Při nejasnostech má přednost rozhodovací log v dokumentu 02 před automatickým
doplňováním předpokladů jednotlivými pracovními vlákny.
