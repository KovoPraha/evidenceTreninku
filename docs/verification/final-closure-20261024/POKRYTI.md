# Technické pokrytí 43 přejímacích scénářů

U každého scénáře je uveden konkrétní technický důkaz. Nejde o nové provedení všech 188 kroků v prohlížeči ani o lidské převzetí. Původní lidské stavy zůstávají NEPROVEDENO.

Původních 25 HTTP sad má [uložené výsledky](../final-system-20261024/http-acceptance.json); jejich zdrojové SHA byly nyní znovu porovnány. Nová sada náhrad má [vlastní výsledek](makeup-http.json).

| ID | Scénář | Technický důkaz | Rozhodnutí |
|---|---|---|---|
| UAT-S01 | Vstup do testu a přepínání pracovních pozic | [ClubFoundation](../../../tests/Support/ClubFoundationHttpAcceptance.php) | D13 |
| UAT-S02 | Omezený pracovní účet a odebrání přístupu | [ClubFoundation](../../../tests/Support/ClubFoundationHttpAcceptance.php), [PersonLifeDocuments](../../../tests/Support/PersonLifeDocumentsHttpAcceptance.php) | D13 |
| UAT-S03 | Předání odpovědnosti neznamená vyřešení případu | [ClubFoundation](../../../tests/Support/ClubFoundationHttpAcceptance.php), [DailyCoachRegistrar](../../../tests/Support/DailyCoachRegistrarHttpAcceptance.php) | D05, D13 |
| UAT-S04 | Verzovaný registr přechodu a jediný zapisující systém | [ClubCutover](../../../tests/Support/ClubCutoverHttpAcceptance.php), [SeasonOperations](../../../tests/Support/SeasonOperationsHttpAcceptance.php) | D10, D12, D13, D15 |
| UAT-S05 | Kontrolní snímek není záloha ani povolení nasazení | [ClubCutover](../../../tests/Support/ClubCutoverHttpAcceptance.php) | D14, D15, D16 |
| UAT-S06 | Soukromé týdenní PDF a přístup podle role | [WeeklyReportPrivacy](../../../tests/Support/WeeklyReportPrivacyHttpAcceptance.php) | D11, D13 |
| UAT-S07 | Převzetí technických důkazů obnovy a mezí migrace | [ClubCutover](../../../tests/Support/ClubCutoverHttpAcceptance.php) | D07, D08, D09, D10, D14, D15, D16 |
| UAT-P01 | Založení osoby, kontrola shod a stabilní ID | [ClubFoundation](../../../tests/Support/ClubFoundationHttpAcceptance.php) | D07 |
| UAT-P02 | Neznámá identita se odloží, ověření nepřiděluje účet | [ClubFoundation](../../../tests/Support/ClubFoundationHttpAcceptance.php) | D07 |
| UAT-P03 | Rodič, účet a plátce jsou samostatné vztahy | [ClubFoundation](../../../tests/Support/ClubFoundationHttpAcceptance.php), [PersonLifeDocuments](../../../tests/Support/PersonLifeDocumentsHttpAcceptance.php) | D03, D07 |
| UAT-P04 | Vlastní zkušební kroužek, cena a kapacita | [MemberAdmission](../../../tests/Support/MemberAdmissionHttpAcceptance.php) | D03 |
| UAT-P05 | Papírová přihláška a souhlasy, ověření a oprava | [ClubFoundation](../../../tests/Support/ClubFoundationHttpAcceptance.php), [PersonLifeDocuments](../../../tests/Support/PersonLifeDocumentsHttpAcceptance.php) | D03, D11 |
| UAT-P06 | Osobní přijetí bez účtu a poslední volné místo | [MemberAdmission](../../../tests/Support/MemberAdmissionHttpAcceptance.php) | D03 |
| UAT-P07 | Členství a datovaná soupiska se evidují samostatně | [ClubFoundation](../../../tests/Support/ClubFoundationHttpAcceptance.php), [PersonLifeDocuments](../../../tests/Support/PersonLifeDocumentsHttpAcceptance.php) | D03 |
| UAT-P08 | Příspěvek: výslovný plátce, schválená částka a jediný běh | [ClubFoundation](../../../tests/Support/ClubFoundationHttpAcceptance.php) | D03 |
| UAT-P09 | Rozvrh: vytvoření, kolize, přesun a auditované zrušení | [TrainingClosure](../../../tests/Support/TrainingClosureHttpAcceptance.php), [DailyCoachRegistrar](../../../tests/Support/DailyCoachRegistrarHttpAcceptance.php) | D13 |
| UAT-P10 | Papírová docházka: zápis není uzavření skutečnosti | [TrainingClosure](../../../tests/Support/TrainingClosureHttpAcceptance.php) | — |
| UAT-P11 | Oprava docházky zachová historii a změní podklad k posouzení | [TrainingClosure](../../../tests/Support/TrainingClosureHttpAcceptance.php), [ClubRewards](../../../tests/Support/ClubRewardsHttpAcceptance.php) | — |
| UAT-P12 | Osobní omluva, nárok a kapacita náhrad | [MakeupJourney](../../../tests/Support/MakeupJourneyHttpAcceptance.php) | D03 |
| UAT-P13 | Nominace není start; skutečný výsledek a historický export | [SportsActivity](../../../tests/Support/SportsActivityHttpAcceptance.php) | D07 |
| UAT-P14 | Prohlídky: čekání na ověření, datum platnosti a odvolání | [MedicalClearance](../../../tests/Support/MedicalClearanceHttpAcceptance.php) | D11 |
| UAT-P15 | Svěřený rozsah a soukromé doklady po odvolání | [TrainingClosure](../../../tests/Support/TrainingClosureHttpAcceptance.php), [PersonLifeDocuments](../../../tests/Support/PersonLifeDocumentsHttpAcceptance.php) | D13 |
| UAT-P16 | Navazující období vznikne k posouzení, ne jako úhrada | [SeasonOperations](../../../tests/Support/SeasonOperationsHttpAcceptance.php) | D03 |
| UAT-F01 | Syntetický vystavovatel, členění a oddělené dokladové řady | [AccountingDocuments](../../../tests/Support/AccountingDocumentsHttpAcceptance.php), [ClubAccounting](../../../tests/Support/ClubAccountingHttpAcceptance.php) | D01, D02 |
| UAT-F02 | Doložená pokladna, příjem a převod mezi vlastními účty | [AccountingLedger](../../../tests/Support/AccountingLedgerHttpAcceptance.php) | D02 |
| UAT-F03 | Uzávěrka samostatné pokladny a blokace zpětného zápisu | [AccountingLedger](../../../tests/Support/AccountingLedgerHttpAcceptance.php), [FinanceB1](../../../tests/Support/FinanceB1HttpAcceptance.php) | D02 |
| UAT-F04 | Počáteční sklad podle kusů a rezervací | [ShopInventory](../../../tests/Support/ShopInventoryHttpAcceptance.php) | D02, D09 |
| UAT-F05 | Dodavatelský příjem, chybějící ocenění a doložení ceny | [ShopInventory](../../../tests/Support/ShopInventoryHttpAcceptance.php), [AccountingStock](../../../tests/Support/AccountingStockHttpAcceptance.php) | D02, D09 |
| UAT-F06 | Osobní prodej veřejnosti bez e-mailu a oddělení platby | [ShopFulfillment](../../../tests/Support/ShopFulfillmentHttpAcceptance.php) | — |
| UAT-F07 | Přijetí hotovosti, účetní zařazení a částečný fyzický výdej | [ShopFulfillment](../../../tests/Support/ShopFulfillmentHttpAcceptance.php), [AccountingLedger](../../../tests/Support/AccountingLedgerHttpAcceptance.php) | D02 |
| UAT-F08 | Faktura: koncept bez čísla, odlišný plátce a neměnné PDF | [AccountingDocuments](../../../tests/Support/AccountingDocumentsHttpAcceptance.php) | D01, D02 |
| UAT-F09 | Oprava nevydaného kusu a uvolnění rezervace bez předstírání vratky | [ShopFulfillment](../../../tests/Support/ShopFulfillmentHttpAcceptance.php), [AccountingAdjustments](../../../tests/Support/AccountingAdjustmentsHttpAcceptance.php) | D01, D02 |
| UAT-F10 | Vrácení vydaného kusu: blokace, posouzení, opravný doklad a peníze | [ShopReturns](../../../tests/Support/ShopReturnsHttpAcceptance.php), [AccountingAdjustments](../../../tests/Support/AccountingAdjustmentsHttpAcceptance.php) | D02 |
| UAT-F11 | Dobrovolná výměna stejné ceny se samostatným výdejem nové velikosti | [ShopReturns](../../../tests/Support/ShopReturnsHttpAcceptance.php) | D02 |
| UAT-F12 | Zrušení výměny s doplatkem nesmí skrýt přijaté peníze | [ShopReturns](../../../tests/Support/ShopReturnsHttpAcceptance.php) | D01, D02 |
| UAT-F13 | Inventura s rozdílem a odděleným schválením | [ShopInventory](../../../tests/Support/ShopInventoryHttpAcceptance.php) | D09 |
| UAT-F14 | Kurz: objednávka, úhrada a místo bez skladového výdeje | [MemberAdmission](../../../tests/Support/MemberAdmissionHttpAcceptance.php) | D01, D03 |
| UAT-F15 | Výdaj z osobních peněz: uznaná část, nezávislé schválení a proplacení | [ClubExpenses](../../../tests/Support/ClubExpensesHttpAcceptance.php) | D02, D05 |
| UAT-F16 | Svěřená záloha: předání, čerpání dokladu a návrat zbytku | [ClubExpenses](../../../tests/Support/ClubExpensesHttpAcceptance.php), [ClubOperatingMonth](../../../tests/Support/ClubOperatingMonth.php) | D02, D05 |
| UAT-F17 | Trenérský výkaz: skutečná činnost, sazba, jiné schválení a výplata | [ClubRewards](../../../tests/Support/ClubRewardsHttpAcceptance.php), [ClubOperatingMonth](../../../tests/Support/ClubOperatingMonth.php) | D04, D05, D06 |
| UAT-F18 | Chybějící účetní podklad a neměnné verze rozhodnutí | [ClubAccounting](../../../tests/Support/ClubAccountingHttpAcceptance.php) | D02, D06 |
| UAT-F19 | XLSX + PDF: kontrola částek, vazeb a otevřených nedostatků | [AccountingExport](../../../tests/Support/AccountingExportHttpAcceptance.php), [ClubAccounting](../../../tests/Support/ClubAccountingHttpAcceptance.php) | D01, D02, D16 |
| UAT-F20 | Revize exportu a rozlišení stažení, předání, vrácení a převzetí | [AccountingExport](../../../tests/Support/AccountingExportHttpAcceptance.php), [ClubAccounting](../../../tests/Support/ClubAccountingHttpAcceptance.php) | D01, D13, D16 |

Další důkazy: [Linux a MariaDB](../linux-browser-20261024/RESULTS.md), [skutečné souběhy](../final-system-20261024/concurrency.json), [obnova](../final-system-20261024/backup-restore.json), [rodinný nákup a účetnictví](../final-system-20261024/family-flow.txt), [stažení a předání účetní](../accountant-handover-20261024/RESULTS.md).

S07 vyžaduje lidské posouzení cíle obnovy a mezí migrace; jeho technickou část dokládá skutečná obnova do nové izolované databáze, nikoli samotný kontrolní snímek. F19/F20 navíc mají nové ověření ZIP/XLSX/PDF a simulace předání z Chrome. F14 navíc pokrývá souvislý nákup stejného kurzu pro dvě děti.
