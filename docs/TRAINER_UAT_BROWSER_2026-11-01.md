# Zadání pro jiné vlákno — test trenérského pilotu přes prohlížeč

Připraveno 1. 11. 2026. **Tento dokument není výsledek provedeného běhu.**
Úkolem je provést stejné scénáře T01–T08 jako v
[ručním postupu](TRAINER_UAT_POSTUP_2026-11-01.md), zaznamenat skutečně pozorované
výsledky a předat nálezy. V tomto zadání se neopravuje kód ani nenasazuje.

## Kontext a vstupní kontrola

1. Přečti `CLAUDE.md`, `docs/CURRENT_STATE.md`, `docs/HANDOFF_CURRENT.md` a tento dokument.
   Ověř aktuální Git a doklady pilotu; historický PASS není dnešní UI důkaz.
2. Testuj pouze `https://kis.kovopraha.cz`. Velocota není cílová aplikace,
   i když může být doména e-mailu testera jiná. Nepropojovat identity.
3. Současný fixture: trenér **56**, soupiska **77**, jméno
   `TEST - Trenérský pilot 20261031`; TEST Alfa **11783**, TEST Beta **11784**,
   negativní osoba TEST Mimo rozsah **11785**. Další ID neodhadovat ani neenumerovat.
4. Grant končí **15. 11. 2026 18:17:31** databázového času hostingu. Při vypršení,
   jiné přihlášené identitě, odlišném fixture nebo souběžném lidském testu
   označ běh BLOCKED a vyřeš předpoklad s vlastníkem. Grant sám neprodlužuj.
5. E-mail získáš soukromě od vlastníka; nepřidávej ho do Gitu ani artefaktů.
   Heslo nevyžaduj v chatu, nečti je ze souborů, databáze či správce hesel.
   Vlastník zajistí přihlášení do vyhrazené relace. Nepřebírej správcovskou relaci.
6. Použij dostupné podporované browser nástroje, jejich aktuální dokumentaci
   a pravidla pro přihlášení, změnu hesla a důkazy. Při zastavení ovládání,
   neověřeném cíli nebo bezpečnostní překážce nepřecházej k obcházení ochrany.

## Přesný rozsah akcí

Po zadání spuštění vlastníkem: procházení povolených stránek; vytvoření
nejvýše jednoho vlastního syntetického tréninku; jedna úprava jeho náplně;
čtení jeho výsledku; negativní GET na dvě níže uvedené cesty; mobilní kontrola.
Žádné platby, skutečné osoby, rezervace, spolutrenéři, přílohy, akce,
importy, exporty celé databáze, nové účty, změny rolí ani změny integrací.
Nepoužívej přímé API/SQL zápisy, injekci session, extrakci cookies nebo tokenů
jako náhradu za nefunkční UI. Bez opravy kódu a bez deploye v tomto běhu.

T01 a T07 jsou **asistované vlastníkem**: vlastník odešle požadavek na obnovu,
otevře doručený odkaz a sám zadá i potvrdí nové heslo. Agent nesmí zachytit
heslo nebo resetovací token. Bez součinnosti označ tyto kroky BLOCKED;
neoznač celý test za plně automaticky ověřený. Další zprávy neposílej.

Označení běhu: `TEST-UAT-AUTO-YYYYMMDD-HHMM-kratky_suffix` (Europe/Prague).
Zapiš je před první změnou do protokolu. Ruční a automatický běh nesmějí
současně používat účet 56. Po nejistém uložení zkontroluj Moje tréninky podle
označení; nové odeslání neopakuj naslepo. Upravit lze jen ID vytvořené tímto během.
Testovací záznam nemaž; předáš jeho ID pro případný schválený úklid.

## Scénáře

| ID | Browser akce | PASS a potřebný důkaz |
|---|---|---|
| T01 | Otevři `/booking/zapomenute_heslo.php?pro=trener`; předej vlastníkovi žádost, doručení a nastavení hesla. Vlastník ověří i odmítnutí použitého odkazu. | Zaznamenej potvrzení vlastníka a následnou přihlašovací stránku bez tokenu. Samotné odeslání formuláře neprokazuje doručení. Označ PASS-assisted, případně BLOCKED. |
| T02 | Vlastník se přihlásí na `/login.php`; znovu přečti viditelnou stránku a ověř testovací identitu a pracovní pozici. | Trenér, žádné dostupné přepnutí na správce. Důkaz stránky po přihlášení bez citlivých údajů. Jiná identita znamená BLOCKED před jakýmkoli zápisem. |
| T03 | Otevři Moji sportovci (`/prehled_sportovcu.php`), vyhledej a otevři obě přidělené osoby; vyhledej kontrolní osobu. | Alfa i Beta dostupné, kontrolní osoba nepřístupná. Nespoléhej na prázdné legacy Moje skupiny; grant je typu soupiska. Zapiš zobrazené testovací ID, neexportuj seznam skutečných osob. |
| T04 | V Zadat trénink (`/formular.php`) vyplň datum v platném grantu, délku 1 h, unikátní označení v náplni a TEST Alfa / TEST Beta přes skutečný UI výběr. Povinné položky dle zobrazeného formuláře, bez dalších vedlejších akcí. Ulož jednou. | V Moje tréninky (`/moje_treninky.php`) je právě jeden záznam s označením. Ověř datum, 1 h, přesně dvě osoby; zapiš skutečně vzniklé ID/URL. Toast nebo HTTP 200 samy nestačí. |
| T05 | Přes UI otevři a uprav vlastní záznam T04. K náplni přidej „– upraveno“, ulož a znovu načti detail/seznam. | Stejné ID, jedna položka, změněná náplň, nezměnění účastníci a délka. Důkaz po reloadu. Neexistující/rozbitý ovládací prvek je nález, ne důvod k přímému API zápisu. |
| T06 | Při platné trenérské relaci proveď GET `/eshop_payments_admin.php` a `/sportovec_karta.php?sportovec_id=11785`. Neodesílej formuláře. | Výslovné odmítnutí bez finančních/osobních dat; stav 403, pokud jej nástroj zpřístupní. Pouhé nezobrazení menu není důkaz. Nečekané odhlášení je neprůkazné pro autorizaci; nejprve rozliš platnost relace. Při úniku dat zastav zápisové kroky a nahlas P1. |
| T07 | Zachovej přihlášenou relaci A. Vlastník provede obnovu v oddělené relaci B, sám zkusí staré a nové heslo. Potom v A obnov chráněnou stránku. | A vyžaduje přihlášení, staré heslo odmítnuto, nové funguje; potvrzení vlastníka o replay. Pokud nástroj neumí dvě izolované relace, BLOCKED, nikoli dvě karty se stejnými cookies. Žádné resetovací URL ve výstupu. |
| T08 | Je-li podporováno, přepni viewport na 390 × 844 a projdi přihlášení, testovací osobu a trénink T04; po skončení override zruš. | Viditelná klíčová tlačítka a čitelný obsah bez useknutí. Screenshot testovacích dat. Jde o emulaci mobilní šířky, nikoli důkaz na skutečném telefonu; při nepodporovaném viewportu BLOCKED. Nevytvářej druhý trénink. |

Každý krok začni aktuální pozorovanou stránkou; po interakci ověř stav.
Lokátory, nabídky a ID používej z právě viditelného UI. Neočekávaný stav
nejprve zaznamenej; automatické opakování nesmí způsobit další zápis.
Na konci se odhlas a ověř, že chráněná stránka vyžaduje přihlášení.

## Výstup pro vlastníka a navazující opravy

Vytvoř `RESULTS.md` a případné anonymizované screenshoty pro tento konkrétní běh.
Ulož je do nového adresáře `docs/verification/trainer-browser-uat-<run-id>/`;
nepřepisuj technické doklady založení z 1. 11. Před uložením/případným commitem
odstraň hesla, tokeny (včetně URL fragmentů), cookies, osobní e-maily a skutečné
údaje sportovců. Pokud snímek nelze bezpečně pořídit, použij stručný textový nález.
Žádné tokeny nečti ani pro redakci: vyhni se jejich zachycení už při pozorování.

```text
Běh / začátek / konec / časové pásmo:
Prostředí / ověřená testovací identita / prohlížeč / viewport:
Předpoklady a případná součinnost vlastníka:
T01: PASS-assisted / FAIL / BLOCKED / NOT RUN — skutečnost — důkaz
T02–T08: každý samostatně, stejné stavy; rozliš automatický a asistovaný výsledek
Vytvořené testovací ID a označení / provedená úprava:
Nálezy: ID kroku, závažnost P1/P2/P3, čas, URL bez tokenu, reprodukce,
        očekávání, skutečnost, opakovatelnost, důkaz
Omezení: co nebylo ověřeno, např. doručení, replay, druhá relace, reálný telefon
Závěr: připraveno / nalezeny chyby / blokováno; žádný PASS pro neprovedené kroky
Odhlášení a zrušení viewport override:
```

V závěru uveď součty výsledků a odkazy na důkazy, žádné domnělé opravy.
T01/T07 asistované vlastníkem nezapočítávej jako plně automatické pokrytí.
Plný běh PASS vyžaduje všech osm scénářů, včetně doložené součinnosti;
jinak jasně pojmenuj omezení. P1 oznam ihned, bez kopírování uniklých dat.

## Text pro zadání v novém vlákně

> V repozitáři EvidencePavel přečti docs/TRAINER_UAT_BROWSER_2026-11-01.md,
> aktuální stav a ruční postup, potom proveď uvedené browser UAT T01–T08.
> Použij výhradně přidělený syntetický pilot. Před zápisem ověř identitu,
> platnost grantu a že na stejném účtu neběží jiný test. Přihlášení a změny
> hesla předej mně; bez mé součinnosti je označ BLOCKED. Zaznamenej skutečné
> výsledky a anonymizované důkazy, včetně vytvořeného testovacího ID.
> Neopravuj kód, nenasazuj a neměň skutečná data ani oprávnění.
