# Etapa 6 — přejímka, rozhodovací brány a zdrojové podklady

> Historický návrhový a přejímací podklad z 22. 10. 2026, sdílený v Gitu od 7. 11.
> Původní formulace „plánováno“ a „neimplementováno“ níže popisují datum vzniku.
> Technické výsledky R01–R44 a provozní meze ověřujte v [současném přehledu](../README.md).
> Tento přenos nepovoluje nové produkční operace a není novým datovým auditem.


Příloha k [plánu etapy 6](00_ETAPA_6_PLAN.md) a [migračnímu postupu](01_MIGRACE_PILOT_A_PRECHOD.md). Scénáře jsou zadáním budoucího ověření; při přípravě nebyly spouštěny.

## 1. Rozhodovací brány

| Brána | Potřebný důkaz | Kdo přebírá | Co ještě nepovoluje |
|---|---|---|---|
| G0 Rozsah připraven | Seznam systémů/agend, odpovědnosti, požadavky etap 1–5, způsob naložení s historií | Vedení a správci agend | Import nebo vypnutí systému |
| G1 Připraveno ke zkušební migraci | Izolované prostředí, ověřené sady a obnova, schválené mapy a náhled konkrétního balíku | Technický správce, registrář, hospodář | Produkční zápis či aktivaci účtů |
| G2 Připraveno k živému pilotu | Přejatá generální zkouška, bez blokátorů pilotního rozsahu, návratový postup, obsluha a jediný zapisující systém | Vedení, správci agend | Plošný přechod všech uživatelů |
| G3 Připraveno k ostrému přepnutí | Výsledky pilotu, aktuální release/mapy, finanční a skladové počátky, provozní okno, poslední dávka a konkrétní rozhodnutí | Vedoucí přechodu s vedením | Zrušení starých služeb ani automatické zapnutí dalších integrací |
| G4 Nové zápisy mohou být otevřeny | Protokol finálního převodu, shoda agend, potřebných souborů a práv, zvládnuté čekající události | Vedoucí přechodu a správci agend | Tvrzení, že celá stabilizace a účetní předání už proběhly |
| G5 Přechod provozně převzat | Stabilní běžný provoz, první převzatý účetní balík, kritické vady uzavřeny, doběhy pod kontrolou | Vedení, obsluha a účetní | Automatické smazání zdrojových dat |
| G6 Starý systém lze odstavit | Kompletní dostupný archiv, vyřízené či převzaté doběhy, veřejné a integrační adresy, odpovědnost za pozdější požadavky | Vlastník služby a vedení | Likvidaci originálů bez příslušných pravidel uchování |

Brány platí pro přesně uvedený rozsah. Přijatý pilot jedné soupisky není převzetím všech soupisek a hotový katalog není hotovou migrací objednávek. Odložená nekritická historie musí být výslovně vymezena, dostupná a nesmí být nutná pro aktivní nároky; jinak blokuje převzetí agendy.

## 2. Přejímací scénáře

Protokol pro každý scénář: ID, rozsah, přesná verze kódu/schématu/převodníku, ID a otisk dávky, prostředí, zkoušející, očekávaný/skutečný výsledek, důkaz a závada. Skutečná osobní data zkoušet jen v chráněném prostředí se schváleným rozsahem; provozní souhrny anonymizovat. Obnova a import jsou zápisy, i když se nazývají zkouška nebo staging.

| ID | Úkol | Scénář | Očekávaný výsledek |
|---|---|---|---|
| P01 | E6-01 | Inventura Shoptetu, Evidence, externího KIS, papíru a dalších skutečně používaných webů | Každá agenda má zdroj, cílové místo, vlastníka, rozsah historie a podmínku odstavení; neznámé zdroje nejsou označené za vyřešené. |
| P02 | E6-02 | Obnovená kopie obsahuje konfiguraci pro produkční platby, zprávy či plánovače | Prostředí neprovede ostrý účinek; konfigurace, cíle i oprávnění jsou izolované a ověřené před prvním spuštěním aplikace. |
| P03 | E6-02 | Rozdílná verze `main`, produkce a migračního nástroje; změna schématu po zkoušce | Protokol určí skutečné verze; změna dotčeného kontraktu vyžádá opakování kontrol, nikoli použití starého zeleného výsledku. |
| P04 | E6-03 | Přehled neukazuje osiřelé vazby, export neobsahuje přílohy nebo část historie | Rozdíl je zachycen inventurou a kontrolními počty; neúplný export nemůže projít jako úplná sada. |
| P05 | E6-03/10 | Změna, nový záznam a výmaz mezi první a poslední dávkou; zdroj bez spolehlivého času změny | Všechny rozdíly se dohledají podle stabilních ID/otisků či průkazného seznamu změn; chybění v dílčím exportu nevymaže cíl. |
| P06 | E6-04 | Obnova s chybějícím klíčem, souborem nebo tabulkou mimo běžné vlastnictví zálohy | Selhání je viditelné a blokuje dotčený přechod; platný otisk SQL sám nezaručuje úplnou obnovu. |
| P07 | E6-04 | Obnova kompletní sady do izolovaného prostředí včetně triggerů a privátních příloh | Sedí počty a otisky, zašifrované údaje lze oprávněně přečíst, role platí a je změřen čas bez zásahu do cizích tabulek. |
| P08 | E6-05 | Stejné jméno, sdílený e-mail a stejné číselné ID ve dvou instalacích | Návrh shody není automatické sloučení; zdroj a typ entity zůstávají součástí identity. |
| P09 | E6-05 | Kontakt rodiče bez doloženého vztahu; existující schválená rodinná role v cíli | Kontakt nevytvoří schválený přístup; existující práva nejsou přepsána starším exportem. |
| P10 | E6-05 | Zástupné narození, neznámá osoba s historií a člověk s otevřeným dluhem | Narození ani členství se nedopočítá; nejasná aktivní finanční vazba blokuje převod daného případu a má vlastníka. |
| P11 | E6-05 | Staré skupiny, otevřené současné členství a historický účet trenéra | Časové vazby zůstanou správné, nevznikne nová placená účast ani pracovní oprávnění z historického záznamu. |
| P12 | E6-06 | Plán a skutečný trénink, budoucí záznam a dvě souběžné soupisky | Nevzniknou dva skutečné tréninky ani dvojí účast; budoucí plán není odpracovaná činnost. |
| P13 | E6-06 | Výsledky DNS/DNF/DSQ, měření m/km, neznámá jednotka a chybějící vazba trenéra | Význam a původ zůstávají; neznámé hodnoty se nehádají a osiřelá návaznost se řeší či výslovně archivuje. |
| P14 | E6-06 | Převod otevřené přihlášky, rezervace, omluvy a již použité náhradní lekce | Jedna platná účast/nárok, správná kapacita; import nevytvoří druhou náhradu ani novou zprávu. |
| P15 | E6-06/08 | Doplnění staré docházky u již uzavřené nebo vyplacené odměny | Zachová se schválená částka a historické vypořádání; případný rozdíl jde k posouzení, nikoli do nové výplaty. |
| P16 | E6-07 | Opakovaný Shoptet katalog proti cílovému ručně upravenému produktu a chybějící položce exportu | Náhled ukáže konflikt a možné vyřazení/změnu skladu; starý export nepřepíše aktuální katalog bez rozhodnutí. |
| P17 | E6-07 | Rozpracovaná objednávka s částečnou úhradou, výdejem a vratkou | Zachované položky a salda, určený systém dokončení; žádné dvojí potvrzení platby, výdeje či refundace. |
| P18 | E6-07 | Fyzická inventura, rezervace v obou systémech a poškozené kusy | Fyzické, rezervované, blokované a dostupné množství jsou oddělené; stará rezervace sníží společnou dostupnost právě jednou. |
| P19 | E6-07/13 | Pilotní a starý e-shop nabízejí poslední kus stejné velikosti | Jedna autorita skladu nebo skutečně oddělené zásoby zabrání dvojímu prodeji; změna webového odkazu sama nestačí. |
| P20 | E6-08 | Faktura ze Shoptetu, stálý VS s úvodními nulami a počáteční pohledávka | Původní čísla i textové VS zachovány; nevystaví se druhá faktura a počátek se nestane novým příjmem. |
| P21 | E6-08 | Rozpor zálohy typu Harrachov a pět podobně zobrazených účtenek | Rozhodne doklad/pohyb a hospodář, ne preference zdroje; soubory se porovnají a výdaje se neduplikují. |
| P22 | E6-08 | Celkový součet peněz sedí, ale dva lidé mají opačně chybné zůstatky | Kontrola po protistranách chybu odhalí; souhrnná nula není úspěch. Přeplatky, odměny a náhrady nejsou smíchány. |
| P23 | E6-08/14 | Platba ke staré objednávce dorazí po finálním exportu | Událost je dohledaná podle reference, přiřazená jedinému případu a vstoupí do správného účetního předání bez duplicity. |
| P24 | E6-09 | Příloha má správnou velikost, ale jiný obsah; soukromý dokument se otevře veřejným URL | Otisk a kontrola přístupu chybu odhalí; takový převod neprojde, i když soubor existuje. |
| P25 | E6-09 | Staré veřejné adresy, formulář, soukromý odkaz, platební callback a e-mail domény | Každý má ověřený režim; veřejné přesměrování nezpřístupní dokument, neztratí platbu a nepoškodí poštu. |
| P26 | E6-10 | Opakování stejné dávky a stejná zdrojová ID s pozměněným obsahem | První je bez duplicity, druhé nové porovnání/konflikt; schválení je svázané s přesným obsahem. |
| P27 | E6-10 | Cílový záznam je změněn mezi náhledem a zápisem | Kontrola odhalí neplatný základ a zápis nepřepíše současný údaj bez nového rozhodnutí. |
| P28 | E6-10 | Pád mezi databázovou dávkou a přesunem souborů; následné opakování | Je známý skutečný stav, žádné ztracené návaznosti nebo dvojí účinky; lze bezpečně pokračovat či vrátit ověřenou část. |
| P29 | E6-11 | Plná zkouška, schválené sloučení duplicit a porovnání řádků/vazeb/součtů | Každá položka má výsledek a vysvětlenou odchylku; počet nemusí slepě odpovídat před deduplikací. |
| P30 | E6-11 | Starší diagnostika porovnává objednávku bez dopravy nebo přeskočí chybějící potřebnou tabulku | Brána používá aktuální kontrakt; chybná/nespuštěná kontrola se nevydává za úspěch. |
| P31 | E6-12 | Úloha záměrně vypnutá oproti zastavené potřebné úloze; nedoručený e-mail | Rozlišené stavy, odpovědná osoba a dohledatelný problém; potřebná agenda má funkční automatizaci nebo přijatý ruční postup. |
| P32 | E6-12 | Obsluha a zástupce pracují bez vývojáře; rodina bez účtu, výpadek a pozdější přepis | Postup použitelný, role správné, dočasné záznamy se doplní jednou s původem a nevznikne fiktivní souhlas. |
| P33 | E6-13 | Živý pilot vybrané skupiny a obchodu při částečném doběhu starého systému | U každého případu je jediná zapisující autorita; obsluha se orientuje, kapacity/peníze/sklad se nerozcházejí. |
| P34 | E6-13 | Pilotní storno, oprava docházky, výměna zboží a účetní předání | Nejde pouze o úspěšnou první objednávku; projdou opravné cesty a účetní přijme konkrétní balík. |
| P35 | E6-14 | Generální zkouška finálního okna se změnovou dávkou a časovou rezervou | Změřená doba převodu, kontrol i návratu; známý nejzazší čas pro zastavení, připravené důkazy k rozhodnutí. |
| P36 | E6-15 | Uzávěra blokuje webový formulář, ale zbývá administrační import či naplánovaný zápis | Kontrola odhalí nezajištěný kanál; finální dávka nezačne bez účinné uzávěry nebo prokazatelného zachycení změn. |
| P37 | E6-15 | Otevření zápisů a zpracování čekajících plateb a zpráv po přepnutí | Správné pořadí, jediná platná reference, žádné dvojí odeslání/upomínka nebo ztracená platba; zaznamenán čas otevření. |
| P38 | E6-16 | Nácvik návratu před otevřením a po nové platbě, objednávce a fyzickém výdeji | První používá ověřený stav; druhý zachová nové operace i externí důsledky a neotevře zastaralý systém bez dorovnání. |
| P39 | E6-17 | První účetní období, opožděná vratka ze starého e-shopu a incident v nové agendě | Sedí počátky, obraty i konečná salda; oprava má verzi, případ má vlastníka a P0 brání převzetí provozu. |
| P40 | E6-18 | Starý systém už není dostupný; potřebujeme historickou fakturu, souhlas, výsledek a opravu starého nákupu | Archiv je čitelný a oprávněně dostupný; pozdější případ má funkční cestu vyřízení, domény a důležité odkazy mají správu. |

## 3. Předávané výstupy budoucí realizace

| Výstup | Minimální obsah |
|---|---|
| Registr přechodu | Potvrzené systémy, agendy, odpovědnosti, datum změny autority, rozsah archivu a podmínky vypnutí |
| Migrační balíky | Manifesty, ověřené zdroje, mapy, pravidla, verze a výsledky dílčích dávek |
| Otevírací protokoly | Osoby/role, aktivní programy, finanční salda, fyzický sklad a rezervace, otevřené případy |
| Protokol obnovy | Konkrétní sada, úplnost DB/souborů/klíčů, cílová izolace, naměřený čas a ověřené příklady |
| Přejímka zkoušky a pilotu | Výsledky scénářů, vady a jejich uzavření, rozhodnutí odpovědných osob pro konkrétní rozsah |
| Protokol ostrého přepnutí | Release, finální dávky, uzávěra a otevření, kontrolní výsledky, rozhodnutí a čekající události |
| Provozní předání | Obsluha, zástupce, návody, dohled, řešení incidentu a účetní převzetí |
| Archiv a odstavení | Umístění a přístupy, původní doklady a soubory, ověřené vyhledání, doběhy, adresy a rozhodnutí o ukončení služby |

Tyto výstupy nyní nevznikly jako skutečné datové či provozní protokoly. Aktuálním výsledkem jsou tři dokumenty zadání etapy 6.

## 4. Zdrojové podklady

Zdroje kódu jsou připnuté k revizi `68b22bd2b3b666dbb4fb571a2731eae5b7f056d9`. Starší dokument popisující určitou fázi importu se nesmí vydávat za současný úplný stav; rozhoduje konkrétní implementace a rozsah jejího kontraktu.

| Značka | Podklad | Co dokládá / omezení |
|---|---|---|
| Z1 | [Porovnání Evidence/KIS](../etapa-0-2026-10-22/03_EVIDENCE_VS_KIS.md), [papírové agendy](../etapa-0-2026-10-22/02_PAPIROVE_AGENDY.md) | Počty přehledů, kandidáti párování, Harrachov a odměny; nejedná se o atomický snímek DB a souborů. |
| Z2 | [Aktuální stav jako rozcestník](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/docs/CURRENT_STATE.md), [nasazení](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/docs/NASAZENI.md), [workflow](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/.github/workflows/deploy-production.yml) | Oddělení merge, deploye, migrace schématu a obchodních dat. Uvedené historické SHA/běhy nejsou důkazem aktuální produkční verze. |
| Z3 | [Databázová záloha](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/bin/db-backup.php), [test obnovy](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/tests/Support/DatabaseBackupMariaDbSmoke.php), [obnovovací postup](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/docs/OBNOVA-DATABAZE.md) | Manifest, otisky, explicitní vlastnictví tabulek/triggery a obnova DB. Neobsahuje automaticky všechny uploady, tajemství, oprávnění DB nebo data cizích aplikací. Historické cesty v návodu se před realizací ověří proti aktuálnímu nasazovacímu postupu. |
| Z4 | [Kontrakt stabilních ID](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/kis_import_field_contract.php), [náhledové běhy](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/kis_import_run_lib.php), [paritní report](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/kis_import_parity_report.php), [sandbox](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/kis_import_sandbox_promotion.php) | Kontroly identity, otisk náhledu, report a omezené zápisy. Sandboxové tabulky nejsou kompletní produkční historie. Starší syntetický `kis-parity-v1` je jiný kontrakt než DB paritní report. |
| Z5 | [Katalogový kontrakt](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/docs/shop-shoptet-import.md), [jednorázová synchronizace](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/shop_catalog_one_time_sync.php), [publikace](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/bin/publish-shoptet-catalog-once.php), [příprava obrázků](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/bin/prepare-shoptet-catalog-assets.php) | Existuje více než původní staging. Synchronizace může měnit sklad, zapisovat korekce a vyřazovat chybějící importované varianty; obrázkový nástroj stahuje a zapisuje soubory. Katalogový import není přenos všech objednávek/dokladů. |
| Z6 | [Sportovní datové zdroje](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/docs/sports-data-quality.md), [sportovní zadání](../etapa-4-2026-10-22/01_PRAVIDLA_PRIJEMKA_A_ZDROJE.md) | Více druhů sportovní historie, strukturovaná i starší data a smluvené cílové významy. Není to hotový převod celé Evidence. |
| Z7 | [Přenos předpisů](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/kis_member_charge_promotion.php), [účetní kontrakt](../etapa-2-2026-10-22/01_KONTRAKT_EXPORTU_PRIJEMKA_A_ZDROJE.md), [klubová administrativa](../etapa-5-2026-10-22/01_PRAVIDLA_PRIJEMKA_A_ZDROJE.md) | Otisk parity, jednoznačná osoba a výslovné omezení přenosu; finanční plán zachovává rozdíl mezi nárokem, skutečnými penězi a počátečním stavem. |
| Z8 | [Soukromé úložiště](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/private_storage.php), [stahování](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/private_download.php), [rodinná oprávnění](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/account_person_role.php) | Cílové nástroje ochrany; převod musí zachovat vlastníka a oprávnění konkrétního objektu. |
| Z9 | [Úplný import osob](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/docs/kis-full-person-import.md), [jeho datový přechod](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/kis_person_cutover.php) | Jiný, nahrazující postup: odstraňuje osoby a související historii/dotčené objednávky. Není vhodný pro nynější aditivní slučování; má vlastní pravidla klíčů a neaktivních účtů. |
| Z10 | [Kontrolní invarianty](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/bin/production-invariants.php), [readiness vstup](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/bin/production-uat-readiness.php), [readiness funkce](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/uat_readiness.php) | Technické kontroly jsou dílčí. Například `invalid_shop_order_totals` porovnává celkovou částku se subtotal minus discount bez dopravy; bránu je nutné sladit s konečným modelem. Readiness není úplná datová ani uživatelská přejímka. |
| Z11 | [Provozní přehled](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/admin_operational_overview.php), [fronta transakčních zpráv](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/transactional_notification_worker.php), [provozní zadání etapy 1](../etapa-1-2026-10-22/00_ETAPA_1_PLAN.md) | Obsluha a dohled mají základ; aktivace, plánovač a skutečné doručení vyžadují samostatný provozní důkaz. |
| Z12 | [Skladový model a převzetí](../etapa-3-2026-10-22/01_SKLADOVY_MODEL_PRIJEMKA_A_ZDROJE.md), [kontrola publikace](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/docs/SHOP_PUBLICATION_ACCOUNTING_GUARD_2026-10-22.md) | Rozlišení fyzických kusů a rezervací; katalog musí respektovat schválené účetní zařazení. Existence publikační kontroly neprokazuje dnešní kompletní produkční připravenost. |

## 5. Omezení přípravy

- Vzdálená zdrojová revize byla obnovena a ověřena. Nebyly spouštěny aplikace, databázové importéry, obnovovací skripty, diagnostické workflow ani produkční zápisové cesty.
- Názvy „preview“, „staging“ a „readiness“ samy nezaručují nulový zápis. Před budoucím spuštěním se ověří celý vstupní bod a prostředí; některé nástroje načítají aplikační DB bootstrap, jiné zapisují kontrolní tabulky či soubory.
- Reálné exporty a zůstatky se v této etapě znovu nestahovaly. Historická čísla slouží k prioritám a seznamu konfliktů; cílové kontrolní hodnoty vzniknou z aktuálních konzistentních snímků.
- Příprava neprokazuje, že lze již dnes vypnout Shoptet, Evidenci nebo jinou službu. To vyžaduje splnění bran pro celý skutečně převzatý rozsah.
