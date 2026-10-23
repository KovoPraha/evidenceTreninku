# Finanční základ R13–R17

Lokální implementace navazující na B1 a R07–R12, 22. 10. 2026. Pracovní větev
`codex/finance-r13-r17-20261022`, základ `0e23e9e766079ce658bbc7687fed7f19b53779de`.
Vlastník autorizoval implementaci se subagenty a ověření v izolovaném prostředí.
**Produkce, vzdálený Git, nasazení, odesílání a aktivace integrací jsou mimo tento úkol.**

## Co přibylo

| Úkol | Existující základ | Doplnění |
|---|---|---|
| R13 — skutečné peníze | Platební požadavky, Fio podklady, rodinné a klubové příjmy a příděly. | Jednotný peněžní pohyb s identitou zdroje a aliasy, skutečným datem, účtem a podkladem. Rozlišení původního příjmu od odvozeného `paid`. Převzetí vyžaduje doložené datum a účet. Příděly a jejich uvolnění nevytvářejí peníze. |
| R14 — strany a členění | Osoby, katalogové zařazení a příjemce položky. | Verzovaný vystavovatel a schválená účetní pravidla s podkladem. Samostatný odběratel, uvedený plátce a účastník. Neměnné položkové zařazení dokumentu a samostatný rozpis skutečného pohybu na schválená pravidla. |
| R15 — doklady | Číslování objednávek/VS a obchodní podklady. | Samostatné dokladové řady, koncept, náhled, vydání a soukromé PDF s otiskem. Rezervované číslo se po chybě PDF znovu nepřiděluje jinému dokladu. Faktura, členský doklad, PPD/VPD a opravný doklad mají vazbu na původní případ nebo peníze. |
| R16 — pokladna a účty | Potvrzování hotovosti a bankovní reference. | Pojmenované pokladny, banky a zúčtovací účty poskytovatelů, doložené počátky, pohyby, skutečný stav a uzavření odsouhlasení. Vnitřní převody a samostatné poplatky se nevydávají za další tržbu. |
| R17 — opravy a vratky | Původní celé storno a potvrzení odeslané vratky. | Položková/částková oprava originálu, samostatné rozhodnutí a postupné skutečné výplaty. Kontrola součtu oprav a volného příjmu, historie uvolnění přídělů a nezávislé převzetí zboží. Společná ochrana proti dvojímu vyplacení starou a novou cestou. |
| První export | PhpSpreadsheet a kontrakt etapy 2. | Neměnný ZIP s XLSX, JSON, dostupnými PDF a manifestem SHA-256. Opakování požadavku vrací stejnou revizi; nová revize nahrazuje předchozí, nepočítá se k ní. Přístup a stažení jsou auditované. |

Částky se ověřují v celých haléřích. První verze pracuje v CZK a režimu
neplátce DPH; neplátcovství není vykázáno jako sazba 0 %. Vydáním dokladu
nevzniká druhá pohledávka, úhrada, členství ani skladový pohyb. Původní VS se
nemění. Brány a Fio zůstávají v dosavadním vypnutém provozním režimu.

## Obsluha

V pracovní pozici **Hospodář a platby** jsou čtyři nové vstupy:

1. **Peněžní evidence a pokladna** — založit účet s ověřeným počátkem,
   převzít doložené zdroje, ověřit jejich rozdělení, zadat účetní členění,
   zaznamenat skutečný výdaj/převod a odsouhlasit konečný stav.
2. **Účetní doklady** — výslovně uložit schváleného vystavovatele, pravidla a
   řady. Načíst konkrétní objednávku, předpis nebo peněžní pohyb, ověřit strany
   a položky, prohlédnout náhled a samostatně vydat PDF. Změnu nevydaného
   konceptu provést před přidělením čísla. Vydaný originál se neopravuje přepisem.
3. **Opravy a částečné vratky** — nejprve opravný doklad a podle potřeby
   doložené uvolnění přídělu; potom rozhodnutí a teprve po skutečné výplatě
   její potvrzení s datem, účtem a referencí. Převzetí zboží pouze zaznamenává
   fyzický stav. Nezvyšuje prodejný sklad — ten dokončí R23.
4. **Účetní podklady** — zvolit období a popis revize, vytvořit a stáhnout
   pracovní archiv. Zkontrolovat Manifest, nepřevzaté zdroje, chybějící PDF,
   členění a odsouhlasení. Další příprava stejného období vytváří novou
   dohledatelnou náhradní revizi. Automatické rozesílání není zapnuté.

Finanční zápisy vyžadují aktuální oprávnění, CSRF na webu, důvod a výslovné
potvrzení. Samotná pozice trenéra, registráře, obsluhy objednávek nebo správce
systému nedává přístup k exportu. Pracovní superadmin zůstává v dosavadním
aplikačním modelu oprávnění. Uzavřené účty a předchozí ochrana finančních
událostí/odměn z B1 se běžnou opravou neobcházejí.

## Význam exportu a jeho hranice

Listy: **Manifest, Doklady, Polozky, Penize, Parovani, Cleneni_penez,
Predpisy, Saldo, Vydaje, Sklad**. Čísla dokladů, reference a ostatní ID jsou
text; částky jsou číselné hodnoty. Uživatelský text se nevyhodnocuje jako
vzorec. Starší pohyb uvedený pro vazby je označen **MIMO OBRAT**. Příděly,
členění ani dokumenty se k peněžnímu obratu znovu nepřičítají.

V této dodávce je každý balík záměrně označen **NEÚPLNÝ — pracovní rozsah
R13–R17**. Jde o první kostru exportu požadovanou realizačním plánem, nikoli
o dokončení R36. `Predpisy` uvádí vazby vydaných dokladů na původní povinnost;
nepředstírá úplnou historickou knihu všech pohledávek. `Saldo` obsahuje
počátky/obraty účtů, nepřidělené peníze a doložené vratky k vyplacení.
Výdaje/zálohy/odměny pracovníků, skladové ocenění a úplné historické saldo
se připojí v navazujících balících. Neznámá pořizovací cena není nula.

Výběr období používá skutečné datum případu/pohybu. Snapshot uchovává také
čas evidence a vytvoření revize; pozdější příděl nebo opravené členění se
může projevit jen v novém archivu, ne přepsáním staženého balíku. Manifest
uvádí nahrazovanou revizi a změněné záznamy. Skutečné předání, převzetí účetní,
úplné měsíční uzavření a rozsah dodatečných oprav dokončí R36/R37.

## Data a obnova

Čtyři nové migrace `20261022220000_accounting_ledger`,
`20261022221000_accounting_documents`, `20261022222000_accounting_adjustments`,
`20261022223000_accounting_exports`. Migrační katalog má 121 položek.
Ownership kontrakt `2026-10-22.3` přidává 18 trvalých účetních tabulek.
PDF a ZIP jsou mimo webroot v soukromých kategoriích `accounting-documents`
a `accounting-exports`. Čtení vyžaduje kontrolu oprávnění a otisku souboru.

Opakovatelné podpůrné zkoušky mají přesnou kontrolu syntetického sandboxu
`127.0.0.1:33426`, aplikační DB `evidence_b1`, HTTP `127.0.0.1:18086`.
Soukromá data jsou pod `%LOCALAPPDATA%\EvidencePavelB1\finance-b1-20261022`.
Zkoušky souběhu mají vlastní nová schémata, obnova vytváří nový cíl a
nepřepisuje zdrojovou DB ani původní soukromý adresář.

Výsledky, skutečné počty kontrol a omezení důkazů: [přejímací protokol](verification/r13-r17/RESULTS.md).

## Co je nutné před skutečným používáním

- Schválit účetní/hospodářem skutečného vystavovatele, účetní členění,
  pravidla vydání, splatnost a návaznost dokladových řad na Shoptet.
  Syntetická konfigurace testů není takovým schválením.
- Doložit skutečné účty, pokladny, počáteční stavy, neověřená data a zdrojové
  reference. Nevytvářet historické příjmy jen z příznaku „zaplaceno“.
- Dokončit navazující sklad a klubovou administrativu, R36/R37 a účetní
  převzetí vzorového měsíce; kompatibilita importu do konkrétního programu
  účetní zatím není potvrzena.
- Zvlášť schválit produkční nasazení, případnou migraci historie a aktivaci
  jednotlivých služeb. Tento úkol žádný z těchto kroků neprovádí.
