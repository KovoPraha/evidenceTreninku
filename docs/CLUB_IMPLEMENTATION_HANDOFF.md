# Společný systém oddílu — předání místní implementace

Pracovní kopie: `C:\xampp\htdocs\evidencePavel-finance-b1-20261022`.
Větev: `codex/migration-cutover-r38-r44-20261023`.
Stav: 23. 10. 2026. Toto předání rozlišuje implementaci, místní ověření
a skutečné převzetí provozu. Žádný balík tohoto realizačního plánu nebyl
v rámci práce nasazen a žádná skutečná agenda nebyla převedena.

## Co lze zkontrolovat nyní

[Návod k místní prohlídce](CLUB_LOCAL_REVIEW.md) obsahuje adresu, umístění
dvou testovacích přístupů a doporučené pořadí pracovních postupů. Prostředí
obsahuje pouze syntetické případy v oddělené databázi a soukromém úložišti.
Zahrnuje i záměrně neúplné a zamítnuté případy pro negativní přejímku.

| Úkoly | Místní výsledek | Co vyžaduje skutečné podklady nebo převzetí |
|---|---|---|
| R01–R03 | Zachované zadání a registr odložených rozhodnutí; výslovné oddělení osoby, účtu, plátce, členství a finančních skutečností | Jmenovaní vlastníci, metodika účetní, konzistentní výchozí exporty, úplný seznam nahrazovaných webů |
| R04–R06 | Izolovaný test, ochrana uzavřené finanční historie, nácvik obnovy DB a soukromých souborů | Produkční rozsah záloh a klíčů, provozní cíle obnovy a jejich přejímka |
| R07–R12 | Identita a ruční řešení shod, členství, pracovní rozsahy, papírové podklady, fronty a provozní případy | Konkrétní historické identity, skutečné osoby a pravidla obsluhy |
| R13–R17 | Jedna peněžní evidence, účetní doklady a opravy, příděly a exportní základ | Vystavovatel, řady, účetní členění a schválený vzor předání |
| R18–R24 | Skladové doklady, fyzický stav, rezervace, inventura, prodej na místě, výdeje, vratky a výměny | Skutečná inventura a schválené počátky, používané obchodní podmínky |
| R25–R30 | Osobní přijetí včetně plátce bez účtu, příspěvky, rozvrh, uzavřená skutečná docházka, sportovní historie, prohlídky a navazující agenda | Skutečné sazby, rozhodnutí o členství a pravidla zdravotní způsobilosti |
| R31–R35 | Výdaje, zálohy, předložení a schválení odměn, skutečné výplaty, opravné verze a klubová rozhodnutí | Skuteční schvalovatelé, účetní titul odměn a případné mzdy/odvody; podmíněné agendy se neurčují odhadem |
| R36–R37 | Rozšířené XLSX/ZIP/PDF předání, salda, oddělené předání a převzetí, souvislý syntetický účetní scénář | Přijetí konkrétního měsíce účetní a uživatelská přejímka obsluhou |
| R38–R39, R41 | Syntetický aditivní převod, explicitní mapy, náhled, schválení konkrétní verze, kontrolní součty, opakování a karanténa; finanční počátky a porovnání existujících případů | Zdrojové exporty a potvrzené mapy; nepodporovaný otevřený starý případ zůstává v karanténě nebo se výslovně dokončí ve starém systému |
| R40, R42 | Verzovaný registr zdrojů, příloh, URL, vlastníků a integrací; kontrolní snímky a porovnání změn; nácvik obnovy | Úplný obsah webů, skutečné přílohy a oprávnění, provozní nastavení integrací, vlastník podpory a návratový postup pro externí transakce |
| R43–R44 | Technické podklady pro pilot, přechod a pozdější stabilizaci; trvale uzavřená brána produkční autorizace | Skutečný pilot, samostatně povolený přechod, první převzatý účetní měsíc a výslovné odstavení starých služeb |

## Podrobné předání a důkazy

- [R07–R12: společné základy](CLUB_FOUNDATION_R07_R12.md).
- [R13–R17: peníze a doklady](FINANCE_R13_R17_IMPLEMENTATION.md).
- [R18–R24: sklad a obchod](INVENTORY_R18_R24_IMPLEMENTATION.md).
- [R25–R30: členové a sport](MEMBERSHIP_SPORTS_R25_R30_IMPLEMENTATION.md).
- [R31–R37: klubová administrativa a účetní předání](CLUB_ADMINISTRATION_R31_R37_IMPLEMENTATION.md).
- [R38–R44: migrace a technická příprava přechodu](CLUB_MIGRATION_R38_R44_IMPLEMENTATION.md).
- [Závěrečné místní ověření](verification/r38-r44/RESULTS.md).

Každý balík má vlastní protokol. Starší počty testů popisují tehdejší revizi;
závěrečný protokol určuje ověření posledního společného stavu.

## Odložené body a další postup

[Registr D01–D16](CLUB_COMPLETION_DECISIONS.md) soustřeďuje všechny odpovědi
vyžadující vlastníka, účetní nebo skutečný zdroj. Nepotvrzená hodnota není nula,
domněnka o shodě není sloučení a označení „archiv“ není vypořádání závazku.
Dokud chybí skutečná metodika a zdroje, účetní předání pravdivě ukazuje svou
neúplnost. Není tím potvrzena připravenost nahradit Shoptet a Evidenci v provozu.

Po místní kontrole následuje vyřešení D01–D14, příprava konkrétních zdrojových
adaptérů podle ověřených exportů, autorizovaný převod nanečisto a kontrola
rozdílů. Teprve poté může proběhnout skutečný omezený pilot. Produkční zápisy,
nasazení, zapnutí integrací a přepnutí potřebují samostatné rozhodnutí D15;
ukončení starých služeb navíc převzetí prvního měsíce podle D16.

Současný migrační zapisovač má pouze syntetický režim. Tento záměrný limit
nelze obejít parametrem příkazové řádky. Připravený návratový náhled ukazuje
nové platby a další změny; neprovádí automatické obnovení staré databáze přes
novější provoz. Půjčované vybavení zůstává mimo zadaný rozsah.
