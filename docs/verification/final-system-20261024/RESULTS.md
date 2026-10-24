# Balík E — společná technická přejímka systému

Následné ověření 24. 10.: [Linux CI a přejímka v prohlížeči](../linux-browser-20261024/RESULTS.md)
uzavírají níže původně uvedenou mezeru tří přeskočených unixových testů a doplňují
19 úspěšných kontrol v Chrome. Stažení ZIP v tomto profilu Chrome zůstává
blokované. Níže je zachovaný původní rozsah a důkaz předchozího běhu.

24. 10. 2026. Navazuje na balíky A–D, základ `d14ee589`, místní větev
`codex/final-system-acceptance-20261024` v `evidencePavel-finance-b1-20261022`.
Technická přejímka zahrnuje i e-shop a návaznosti mezi agendami. Proběhla pouze
nad syntetickými daty. Produkce nebyla kontaktována ani změněna; neproběhl push,
deploy, skutečný import nebo odesílání zpráv. Uživatelské a účetní převzetí zůstává
k provedení jmenovanou obsluhou.

## Výsledek a důkazy

- Společná sada: **25 úspěšných běhů / 812 kontrol** reálných místních HTTP požadavků a
  provozního měsíce. [Výsledky po scénářích](http-acceptance.json) obsahují i
  kontrolní součet zdroje každého skriptu. Opravené dílčí běhy jsou označené
  `corrected_rerun_at`; nejde o tvrzení, že původní neúspěšný běh prošel.
- Celá jednotková a integrační sada: **1 709 testů, 34 494 assertions, žádná
  chyba ani selhání**; [výsledek PHPUnit](phpunit.json),
  [výpis](phpunit.txt). Tři testy symlinků jsou na Windows přeskočené; jejich
  unixovou hranici je nutné ověřit na Linuxu před nasazením. Linux CI v tomto
  zadání neběžela.
- Šest skutečných MariaDB zkoušek souběhu: výdej/storno, vratky, finanční
  export, skladový snímek, uzavření tréninku a odebrání členských oprávnění.
  [Důkazy](concurrency.json) zahrnují nezávislá spojení a u exportů negativní
  kontrolu, která bez konzistentního snímku skutečně odhalí smíšený výsledek.
- Nákup stejného kurzu pro dvě děti, oblečení, kupón, poslední místo,
  částečné úhrady, přeplatek, výdej, doklad, oprava, vratka a dvě neměnné
  revize předání: [souvislý obchodní běh](family-flow.txt).
- Nová oprava migrace: [10 kontrol na MariaDB](payment-symbol-repair.json),
  včetně zachování hodnot a opakovatelnosti. Místní katalog má 138 migrací,
  žádnou čekající; [kontrola migrací](migrations.json).
- [Kontrola izolace](isolation.json): správný vlastník a databáze, 327
  dostupných vlastních tabulek, soukromé soubory mimo webroot, blokované vnější
  transporty. Nepoužívané volitelné tabulky `ucto_gs_*` nejsou podmínkou tohoto běhu.
- [Záloha](backup.json) a [obnova](backup-restore.json): do nové samostatné
  databáze obnoveno 327 tabulek a 2 triggery, do nového soukromého adresáře
  242 souborů. Ověřeno 107 souborových vazeb, 47 skladových variant, 35 revizí
  uzavření tréninků, 37 výplat, 24 vazeb vrácených plateb a další migrační
  kontrolní součty. Nejde o obnovu produkce ani o dohodnuté RPO/RTO.

## Společné pracovní postupy

| Průchod | Ověřený výsledek | Důkaz / důležitá hranice |
|---|---|---|
| Běžný týden trenéra a registráře | Osoba a její případy, oprávněná skupina, plán bez kolize, omluva, skutečná docházka, uzavření a sportovní podklad | DailyCoachRegistrar, ClubFoundation, TrainingClosure, SportsActivity, MedicalClearance; odvolaná role a cizí skupina jsou odmítnuté |
| Přijetí, průběh a ukončení účasti | Osobní i online přijetí, výslovný plátce, příspěvek schválený pro období, podklady a historie osoby | MemberAdmission, PersonLifeDocuments, členské integrační testy; nákup ani skupina samy neurčují členství v oddílu |
| Rodič / sportovec a nákup | Stejný kurz pro dvě děti v jedné objednávce, účast konkrétních osob, omezená kapacita a oddělená oprávnění | Checkout MariaDB, rodinné integrační testy; bez oprávnění k dítěti nevzniká účast ani přístup k jeho podkladům |
| Klubová akce | Skutečná sportovní účast, soukromá příloha výdaje, záloha, vyúčtování a nezávislé schválení odměny | ClubAdministration, ClubExpenses, ClubRewards, ClubOperatingMonth; vlastní nárok se automaticky neschválí |
| Prodej a vratka | Fyzický sklad, rezervace, výdej, vrácené zboží, kontrola stavu, finanční oprava a skutečné vrácení peněz | Inventory, Fulfillment, Returns, AccountingStock; vrácení zboží samo neznamená vrácení peněz |
| Měsíc účetní | Skutečné peněžní pohyby, přiřazení k případům, doklady a neměnný archiv s vazbami na objednávky, výdaje a odměny | AccountingLedger/Documents/Adjustments/Export, ClubAccounting a souvislý měsíc; uzavřená evidence se nepřepisuje |
| Pokračování sezony a přechod | Časově platné skupiny, náhled pokračování, deduplikace rodiče, zadržení zprávy po zániku vztahu, verzovaná mapa starých agend | SeasonOperations, ClubCutover a obnova; nezapíná rozesílku ani nepřevádí skutečné osoby |

Počty kontrol v jednotlivých skriptech nejsou počtem unikátních uživatelských
požadavků. HTTP ověření používá skutečné session, role, CSRF, routy, databázi
a soubory. Vizuální prohlídka v prohlížeči ani lidské odklikání celého průvodce
v tomto balíku neproběhly.

## Opravené vady

### Migrace opakovaného variabilního symbolu

Historická migrace z 22. 9. skládala indexy chybně kvůli chybějícím závorkám
smyčky. Podle pořadí indexů přehlédla unikátnost samotného variabilního symbolu
a vypisovala varování. Taková instalace mohla odmítnout další měsíční platbu
se stejným dohodnutým symbolem.

Původní migrační soubor zůstává beze změny. Loader použije opravené vykonání
pouze pro přesné ID a dvě známé původní byte varianty LF/CRLF. Běžná kontrola
integrity migrační historie zůstává zachovaná; neznámý obsah se nepovažuje za
povolenou opravu. Nová následná migrace opravuje již vytvořené databáze.

Oprava odstraňuje jen unikátní index samotného `payments.variable_symbol`.
Zachovává unikátní identitu pohledávky, složené indexy i unikátní přidělení
trvalého symbolu. Nemění čísla plateb, symboly včetně úvodních nul, částky,
historické řádky ani kontrolní součty již evidovaných migrací. V tomto zadání
byla aplikovaná pouze v izolovaných databázích.

### Historické odměny

Tři staré obrazovky nabízely zápis, který server již správně odmítal po zavedení
kanonické agendy. Nyní vysvětlují historický režim, vedou přímo na **Výkazy a
odměny** a mají neaktivní zastaralá odesílací tlačítka. Čtení historie a GET
filtry zůstávají dostupné. Přímý podvržený POST je nadále odmítnut serverem;
změna nepřepočítává historické sazby ani neschvaluje nároky.

### Přesnost testů

- Windows scanner nyní rozpozná přímé kladné `function_exists` před volitelnou
  funkcí. Nezakrývá nechráněné, negované ani booleanem rozšířené volání.
- Testy používají skutečné 303/302 přesměrování konkrétních endpointů.
- Úspěšný plán trenéra dostal nekolidující čas; ochrana konfliktů se nemění.
- Staré odměny se testují jako historická evidence; nový schvalovací a výplatní
  postup ověřuje samostatný test současné agendy.
- Test snímku účetního exportu má správné klíče rolí a sloupce objednávky.
  Původní neindexovaná testovací tabulka blokovala oba účty při čtení oprávnění,
  takže nedokázala provést zamýšlený souběh. Produkční zámky nebyly oslabeny.

## Ukázkové předání účetní

Nově vytvořený syntetický příklad obsahuje objednávku za **539,99 Kč**, skutečné
příjmy **545,00 Kč**, přeplatek **5,01 Kč**, opravu **−10,00 Kč** a skutečnou
vratku **10,00 Kč**. Čisté peníze jsou **535,00 Kč**, čisté doklady **529,99 Kč**;
rozdíl **5,01 Kč** zůstává přeplatkem. Částky pocházejí z ověřeného běhu, nejsou
vzorem účetní klasifikace skutečného oddílu.

- [Faktura PDF](accountant-sample/LOCALHOST-NEPLATIT-faktura.pdf).
- [Podklady XLSX](accountant-sample/LOCALHOST-NEPLATIT-podklady.xlsx) z první revize.
- [Původní neměnný archiv](accountant-sample/LOCALHOST-NEPLATIT-rev1.zip).
- [Navazující archiv po opravě a vratce](accountant-sample/LOCALHOST-NEPLATIT-rev2.zip).
- [Ověřené částky](accountant-sample/verification.json) a [SHA-256 souborů](accountant-sample/sha256.json).

Samostatný [provozní měsíc](club-operating-month.json) propojuje trénink,
schválenou odměnu, zálohu a výdaje na akci, prodej oblečení a vývoz pro účetní;
kontrolovaný konečný zůstatek syntetického účtu je **4 800,00 Kč**.
Archivy/PDF/XLSX byly programově ověřeny, tato práce nepředstavuje jejich nové
vizuální schválení nebo převzetí účetní. Skutečné podklady nebyly nikomu odeslány.

## Co zůstává odložené

[Registr D01–D20](../../CLUB_COMPLETION_DECISIONS.md) zůstává platný. Zejména:

1. D01/D02/D04–D06: skutečné řady dokladů, klasifikace, sazby a titul odměn,
   schvalovatelé. Technický PASS tyto volby neurčuje.
2. D03/D07–D11/D17: skutečná pravidla členství, sporné identity a podklady,
   inventura, úplnost starých webů a retence. Neproběhl skutečný převod dat.
3. D12–D16: jmenovaná obsluha, povolené integrace, pilot a jeho termín,
   podmínky obnovy, nasazení, první skutečný měsíc účetní a ukončení starých služeb.
4. D18: konkrétní zdravotní podklady a rozhodnutí o účasti při jejich absenci;
   D19/D20: případné rozšíření rodinné a trenérské samoobsluhy podle registru.

Další konkrétní krok: jmenovaná obsluha provede pět společných průchodů
„týden / osoba / akce / měsíc / sezona“ v izolované kopii a účetní posoudí obě
revize vzorku. [Existující uživatelský průvodce](../../acceptance-20261023/README.md)
zůstává k lidskému vyplnění, jeho dřívější stav NEPROVEDENO se automaticky nemění.
Přístup je popsán v [místním návodu](../../CLUB_LOCAL_REVIEW.md). Po rozhodnutích
a samostatném povolení nasazení následuje Linux kontrola, plán migrace/zálohy
a omezený pilot. Nasazení není součástí tohoto výsledku.

## Opakování technické přejímky

Z uvedené pracovní kopie v PowerShellu, pouze s vlastním připraveným B1:

```powershell
& tests/Support/FinalSystemAcceptance.ps1
& C:/xampp/php/php.exe vendor/phpunit/phpunit/phpunit --colors=never
& C:/xampp/php/php.exe tests/Support/PaymentVariableSymbolMigrationMariaDb.php
```

První skript před jakýmkoli zápisem ověří vlastníka runtime a kontrolu izolace.
Nové běhy přidávají pouze označené syntetické případy; nejsou návodem k testovacím
zápisům na produkci. Jednotlivý opravovaný scénář lze spustit přes `-Only`.
Pro nezávislý souběh použijte šest pojmenovaných skriptů z `concurrency.json`;
všechny kontrolují identitu lokální MariaDB a používají oddělená schémata.
