# Závěrečná technická přejímka — sjednocený stav

24. 10. 2026, izolovaná pracovní kopie `evidencePavel-finance-b1-20261022`.
**Ve vymezeném technickém rozsahu balíku E nezůstává známý nevyřešený blokátor.**
To neznamená lidské převzetí, úplnost skutečných dat oddílu ani povolení produkce.

## Co bylo nyní dokončeno

1. Zkontrolováno všech **43 scénářů** přejímacího průvodce a jejich technické
   návaznosti. [Mapa pokrytí](POKRYTI.md), [strojová mapa](scenario-coverage.json)
   uvádějí konkrétní sady a odložená rozhodnutí. Nejde o nové odklikání 188 kroků
   v prohlížeči. Původní lidské stavy nebyly přepsány.
2. Zdrojové SHA všech **25 původních HTTP sad** odpovídají uloženému důkazu
   812 kontrol. Aplikační soubory jsou shodné s Linuxem ověřeným commitem
   `ad4ba012aa4cc6c7b59f6b0c02343db16a9f86ec`; od něj se změnily dokumenty a nyní
   nový místní přejímací test. [Audit návaznosti důkazů](evidence-audit.json).
3. Doplněna chybějící souvislá **HTTP přejímka náhrad tréninků: 23 kontrol PASS**.
   [Výsledek](makeup-http.json), [zdroj](../../../tests/Support/MakeupJourneyHttpAcceptance.php).
   Test je připojen do `FinalSystemAcceptance.ps1`; ověřeno jeho spuštění přes `-Only`.
4. Čerstvá cílená sada náhrad, rodinného kalendáře, týdenních souhrnů a transakční
   fronty: **32 testů / 221 assertions, bez selhání a přeskočení**.
   [Výpis](focused.txt), [JUnit](focused.xml). Testy odesílání používají místní
   výstup nebo náhradu transportu; nedošlo ke kontaktování skutečných příjemců.
5. Opraven zastaralý hlavní souhrn, který stále označoval stažení ZIP za blokované.
   Historické protokoly zůstávají dohledatelné; aktuální stav je od nich oddělený.

Nový test vytváří vlastní označené syntetické rodiny, kurzy a předpoklady placené
účasti. Tyto přípravné řádky nejsou důkaz nové skutečné platby nebo nákupu.
Vlastní ověřované kroky omluvy, uzávěrky, nastavení kapacity, rezervace a storna
probíhají přes skutečné lokální HTTP routy se session a CSRF.

## Nově ověřený souvislý průchod náhrad

- Osobní papírová omluva dvou účastníků se zachovaným skutečným časem přijetí.
- Pouhý přepis docházky nevytvoří nárok; nároky vzniknou po výslovném uzavření lekce.
- Trenér povolí jedno náhradní místo; podvržený CSRF nastavení nezapíše.
- Rodič vidí vlastní dítě, cizí rodič nemůže otevřít profil ani za něj rezervovat.
- Podvržený rodinný CSRF a dvojí odeslání nárok neutratí znovu.
- Obsazená kapacita odmítne druhého účastníka a zachová jeho nárok.
- Včasné storno zachová historii, vrátí nárok a uvolní místo druhému účastníkovi.
- Odvolaná rodičovská vazba okamžitě zablokuje čtení i starý formulář; rezervace
  se při odmítnutém požadavku nezmění.

Při přípravě testu byly opraveny dva předpoklady samotné zkušební sady: historická
lekce potřebuje původní snímek pravidla náhrad a pomocný HTTP klient musí připustit
lokální cestu `booking/`. Produkční ochrany ani pravidla se kvůli testu neuvolnily.
Nová vada aplikace nebyla potvrzena; aplikační kód nebyl měněn.

## Aktuální technické brány

| Oblast | Stav a důkaz | Co tím není potvrzeno |
|---|---|---|
| Propojení osob, sportu, akcí, obchodu a financí | [Balík E](../final-system-20261024/RESULTS.md): 25 HTTP běhů / 812 kontrol; zdroje nyní znovu ověřeny | Nové provedení celé sady v tomto kroku |
| Linux a dříve přeskočené symlinky | [Linux](../linux-browser-20261024/RESULTS.md): 1 709 testů / 34 526 assertions, bez přeskočení; samostatná unixová brána 3/45 | Nasazení na hosting; tři testy jsou podmnožina plné sady |
| MariaDB kompatibilita a souběhy | Linux MariaDB 10.3/11.4 PASS; [šest místních souběhových zkoušek](../final-system-20261024/concurrency.json), včetně posledního náhradního místa | Zátěžová certifikace nebo libovolná jiná konfigurace DB |
| Izolace a migrace | [Čtecí probe](isolation.json) PASS, 327 tabulek, vypnuté vnější transporty; předchozí katalog 138 migrací bez čekajících, schéma nyní neměněno | Stav nebo migrace produkce |
| Záloha a obnova | [Obnova](../final-system-20261024/backup-restore.json): nová DB, 327 tabulek, 242 soukromých souborů, 107 vazeb | Odsouhlasené RPO/RTO nebo obnova skutečné produkce |
| Desktopový prohlížeč | [19 původních kontrol](../linux-browser-20261024/RESULTS.md); poslední stažení uzavřeno navazujícím protokolem | Kompletní mobilní a víceprohlížečová matice |
| ZIP a předání účetní | [Předání](../accountant-handover-20261024/RESULTS.md): stažení v Chrome, 2 × 647 kontrol obsahu, 99 HTTP kontrol, předání/vrácení/převzetí; staré ZIPy neměnné | Účetní metodika nebo podpis skutečné účetní |
| Náhrady a oprávnění rodiče | Nových 23 HTTP kontrol PASS + integrační a souběhové důkazy | Schválení skutečných klubových pravidel náhrad |
| Komunikační fronty a rodinný kalendář | Cílená sada 32/221 PASS zahrnuje odebrání vztahu, opt-out, opakování a místní transport | Skutečné doručení přes externí služby nebo povolení plánovače |

Počty z různých řádků se nesčítají: řada důkazů se překrývá. Celá Linux sada
nebyla znovu spuštěna, protože ověřený aplikační kód se nezměnil. Nová sada je
místní Windows/MariaDB HTTP přejímka, nikoli nově deklarovaný Linux test.

## Co skutečně zbývá

Následující položky byly ponechány otevřené; žádná odpověď nebyla odhadnuta:

| Navazující práce | Rozhodnutí / vlastník | Připravený podklad |
|---|---|---|
| Vystavovatel, řady, členění, titul a sazby odměn, účetní program | D01, D02, D04–D06; hospodář a účetní | Ověřený vzorek XLSX/PDF/ZIP, oddělené kroky schválení a úhrady |
| Pravidla členství, zdravotních podkladů a skutečné personální vztahy | D03, D07, D17, D18; vedení a registrář | Ověřené technické postupy; žádné automatické sloučení ani domyšlené pravidlo |
| Skutečné počátky, nesrovnalosti, inventura, úplnost starých zdrojů a retence | D07–D11; vlastníci dat | Migrační a obnovovací důkazy, karanténa sporných případů |
| Případné další samoobslužné funkce rodiny a trenérů | D19, D20 | Stávající osobní vyřízení; nové právo k citlivým údajům nebylo přidáno |
| Jmenovaná obsluha provede vlastní přejímku, účetní posoudí skutečné předání | D13, D16 | [Uživatelský průvodce](../../acceptance-20261023/README.md), 43 lidských scénářů stále NEPROVEDENO |
| Integrace, pilot, jediný zapisující systém, okno přechodu, návrat a nasazení | D12–D16 | Ověřená izolace a pracovní registr přechodu; bez aktivace služeb |

Úplný [registr D01–D20](../../CLUB_COMPLETION_DECISIONS.md) zůstává autoritativní.
Žádná produkční změna, push, merge, deploy, import skutečných dat nebo odeslání
zprávy nebyly v tomto kroku provedeny.

## Opakování nových kontrol

Z vlastní připravené izolované pracovní kopie:

```powershell
& tests/Support/FinalSystemAcceptance.ps1 -Only MakeupJourneyHttpAcceptance
& C:/xampp/php/php.exe vendor/phpunit/phpunit/phpunit --colors=never --filter 'ClubMakeupTest|FamilyCalendarFeedTest|FamilyWeeklyDeliveryTest|TransactionalNotificationWorkerTest'
```

První příkaz před zápisem ověří vlastníka runtime a čtecí probe. Samotný test navíc
kontroluje pevný loopback, název DB, port, datadir a syntetický descriptor.
Každé opakování vytváří nové označené místní případy.
