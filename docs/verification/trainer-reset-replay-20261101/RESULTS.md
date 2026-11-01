# Prověření obnovovacího odkazu a lokální oprava

1. 11. 2026, Europe/Prague. Bez nasazení a bez produkčních změn.
Základ: `97a0ff213a871639f7708d99613282f73652302e` (origin/main).
Větev: `codex/trainer-reset-replay-20261101`.

## Výsledek vyšetřování

Hlášení vlastníka z browser UAT uvádělo, že již použitý trenérský odkaz
umožnil další změnu hesla. Hlášení samo nerozlišuje zobrazení formuláře
od druhého úspěšného uložení. Skutečný produkční token ani heslo se nečetly.

**Potvrzený nedostatek:** původní `booking/nove_heslo.php` vždy zobrazil
heslový formulář a platnost odkazu kontroloval až při odeslání hesla.
Izolované vykreslení nezměněné původní stránky se syntetickým SQLite PDO
potvrdilo heslová pole na počátečním GET pro trenérský i veřejný režim.
Stejné vykreslení upravené stránky heslová pole neobsahuje a nabízí pouze
předběžnou kontrolu odkazu. Nejde o produkční browser reprodukci.

**Druhý zápis nepotvrzen:** nezměněný původní test trenérské zápisové služby
z původního worktree prošel samostatně (1 test / 14 assertions): odkaz se
spotřebuje, zvýší se verze relace a tentýž i dříve vydaný další odkaz se odmítnou.
Kód vyžaduje `consumed_at IS NULL`, platnost, aktuální identitu a verzi relace;
v MySQL znovu čte pod `FOR UPDATE` uvnitř transakce. Nové testy navíc výslovně
ověřují nezměněný první hash hesla a verzi relace po pokusu o opakování.
Z tohoto vyšetřování nelze odvodit, že se produkční druhá změna skutečně uložila.

## Oprava

- Po otevření odkazu se fragment odstraní z adresy a předá pouze token
  do CSRF chráněného POST `action=check`. Tento POST nemění heslo ani relaci.
- Heslový formulář se vykreslí pouze pro aktuálně platný, nevyužitý a oprávněný
  odkaz. Použitý, expirovaný, neplatný nebo odvolaný odkaz dostane odmítnutí
  bez heslových polí, s možností vyžádat nový odkaz.
- Samotné uložení stále opakuje zamykající kontrolu a spotřebování v transakci;
  předběžná kontrola není oprávněním k pozdějšímu zápisu.
- Při neshodě nebo nedostatečné délce hesel zůstane platný odkaz ve formuláři,
  aby šlo opravit vstup. Původní JavaScript jej při chybě přepisoval prázdným
  fragmentem, který již dříve odstranil z adresy.
- Zůstává oddělen trenérský a veřejný/sportovní účel. Parametr `pro=trener`
  se zachová. Žádná migrace nebo změna datového schématu.

## Testy a hranice důkazu

- Cílená sada: **20 testů / 215 assertions PASS** před formátovacími úpravami.
  Zahrnuje skutečně vykreslovanou kopii PHP stránky se skutečnými helpery
  session, CSRF, resetu a UI; nahrazen je pouze bootstrap produkční DB
  izolovaným syntetickým SQLite PDO. Žádný produkční config se nenačítá.
- Vykreslený průchod pro trenéra i veřejný účet: počáteční GET bez heslových
  polí → platný check bez spotřebování → neshoda hesel → krátké heslo →
  první úspěšná změna → opakovaný check i přímé opakované odeslání odmítnuty.
  První uložený hash a verze relace po odmítnutí zůstávají shodné.
- Použitý formulář po změně účtu a expirovaný odkaz neumožní zadat heslo;
  chybný CSRF a syntakticky neplatný token se odmítnou.
- Sportovní účet: čtecí předběžná kontrola, první změna, opakování odmítnuto,
  stejný hash/verze a právě jedna auditní událost.
- JavaScript: **2/2 PASS**, skutečná fragmentová větev stránky spuštěna v Node VM:
  odstranění fragmentu před token-only POST, zachování trenérského režimu,
  odmítnutí chybějícího fragmentu bez odeslání. Test přidán do Linux CI.
- Celý lokální PHPUnit po formátovacích úpravách: **1900 testů / 38141 assertions,
  3 Windows skipy**, exit 0, 4 min 44 s. Windows výsledek není plným Linux PASS.
- PHPStan level 5: **bez chyb**, žádné rozšíření baseline (513 položek / 2347
  dřívějších nálezů beze změny). Composer validate --strict PASS; changed-file
  style 7 PHP souborů / 0 chyb; syntaxe všech 7 změněných PHP PASS.
- Anonymní inventory: public 32, CLI 2, protected 237, errors 0. Dokumentační
  kontrakt a git diff --check PASS. Linux ověření včetně platformních testů
  a MariaDB matic patří ke kontrolám připraveného PR; jejich živý stav je u PR.

Neproběhla produkční obnova, e-mail, změna hesla nebo nová browser UAT s opravou.
Původní produkční nález T01 se tím neoznačuje jako PASS. T07 zůstává odložen.
Produkce zůstává na dosavadním vydání; merge ani deploy nejsou součástí úkolu.
