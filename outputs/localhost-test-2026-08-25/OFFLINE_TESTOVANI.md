# Offline testování EvidencePavel na localhostu

Stav připravený dne 25. 8. 2026. Tento dokument je určený k použití bez
internetu a bez další pomoci. Všechny níže uvedené účty a údaje jsou syntetické
a patří pouze do lokální databáze.

## 1. První instalace a další spuštění

Na novém počítači je při první přípravě potřeba internet pouze pro naklonování
GitHub repozitáře a instalaci zamčených Composer balíčků. Potom:

1. Uložte projekt jako `C:\xampp\htdocs\evidencePavel`.
2. Dvakrát klikněte na `PRIPRAVIT_LOCALHOST_TESTOVANI.cmd`.
3. Počkejte na zelenou větu `První localhost instalace je hotová.`
4. Teprve potom lze počítač odpojit od internetu.

Příprava vytvoří novou samostatnou databázi pouze ze schématu bez dat a
syntetických fixture. Žádný produkční ani obnovovací databázový dump není
součástí GitHub repozitáře.

Při každém dalším spuštění:

1. V Průzkumníku otevřete `C:\xampp\htdocs\evidencePavel`.
2. Dvakrát klikněte na `START_LOCALHOST_TESTOVANI.cmd`.
3. Počkejte na zelenou větu `EvidencePavel je připravena k offline testování.`
4. Otevřete `http://localhost/evidencePavel/`.
5. Okno spouštěče lze zavřít. Apache i databáze zůstanou běžet.

Tlačítko MySQL v XAMPP Control Panelu pro toto testování nepotřebujete.
Evidence používá vlastní čistou lokální databázi na portu 3308. Spouštěč ji
zapne sám a současně neukončí jiné lokální databáze.

## 2. Testovací účty

### Administrátor s maximálním oprávněním

- Přihlášení: `http://localhost/evidencePavel/login.php`
- Jméno: `localhost-admin`
- E-mail účtu: `admin@localhost.test`
- Heslo: `LocalhostAdmin123!`
- Stav: aktivní superadministrátor
- Výchozí pozice: Správce systému
- Dostupné pozice: všech osm

Administrátor má tyto samostatné pracovní pozice:

1. Trenér
2. Vedoucí sportu
3. Registrář členů a KIS
4. Koordinátor programů a sportovišť
5. Správce katalogu e-shopu
6. Zákaznická péče a objednávky
7. Hospodář a platby
8. Správce systému

Pozor: nabídky jednotlivých pozic se záměrně neslučují. Pokud stránka oznámí,
že patří do jiné pracovní pozice, nejde o chybějící oprávnění. Otevřete
`http://localhost/evidencePavel/pracovni_pozice.php`, přepněte správnou pozici
a stránku otevřete znovu.

### Rodič / zákazník

- Přihlášení: `http://localhost/evidencePavel/booking/prihlaseni.php`
- E-mail: `rodic@localhost.test`
- Heslo: `Localhost123!`
- Stav: aktivní a ověřený účet
- Připojené osoby: dvě testovací osoby

### Sportovec s omezeným přístupem

- Přihlášení: `http://localhost/evidencePavel/booking/sportovec_prihlaseni.php`
- Jméno: `localhost-sportovec`
- Heslo: `LocalhostSportovec123!`
- Stav: aktivní

Sportovec smí vidět vlastní sportovní údaje, tréninky, soupisky, události a
platby. Nesmí spravovat rodinu ani objednávky. Otevření `moje_osoby.php` nebo
`moje_objednavky.php` ho proto správně vrátí na rodičovské přihlášení.

## 3. Co nikdy nedělat

- Nikdy neposílejte částku z testovacího QR kódu.
- Vše s označením `LOCALHOST`, `NEPLATIT` nebo bankovním kódem `9999` je test.
- Nezapínejte ostrý Stripe, Fio import, e-mailové rozesílání ani produkční CRON.
- Nepoužívejte skutečná jména, e-maily, telefony, zdravotní údaje ani soubory.
- Nezkoušejte produkční adresu `kis.kovopraha.cz`.
- Pokud obrazovka neobsahuje označení localhostu, před zápisem zkontrolujte
  adresní řádek. Musí začínat `http://localhost/evidencePavel/`.

## 4. Rychlá kontrola prostředí

Po spuštění proveďte tyto čtyři kroky:

1. Otevřete `http://localhost/evidencePavel/`.
   Očekáváno: titul `Kovopraha – klubový portál` a odkazy na e-shop, velodrom,
   rodičovské přihlášení a vstup pro trenéry.
2. Otevřete
   `http://localhost/evidencePavel/assets/vendor/bootstrap/bootstrap.min.css`.
   Očekáváno: dlouhý text CSS, nikoli stránka 404.
3. Otevřete
   `http://localhost/evidencePavel/assets/vendor/bootstrap/bootstrap.bundle.min.js`.
   Očekáváno: JavaScript začínající komentářem `Bootstrap v5.3.3`.
4. Na hlavní stránce zúžte okno a použijte tlačítko `Přepnout navigaci`.
   Očekáváno: nabídka se otevře i bez internetu.

Bootstrap, Bootstrap Icons, písma i ovládací JavaScript jsou uložené lokálně.
Editor oznámení používá bez internetu obyčejné textové pole místo volitelného
TinyMCE; odeslání formuláře zůstává funkční.

## 5. Administrátorský průchod

Přihlaste se jako `localhost-admin`. Na stránce pracovních pozic musí být štítek
`Superadmin`.

### Trenér

Přepněte pozici `Trenér` a otevřete:

- `http://localhost/evidencePavel/club_calendar.php`
- `http://localhost/evidencePavel/formular.php`
- `http://localhost/evidencePavel/planovac.php`

Očekáváno: klubový kalendář, zadání tréninku a plánovač. Pro zápis vytvořte
trénink s názvem začínajícím `LOCALHOST TEST`, vyberte pouze testovací osoby a
po uložení ověřte nový řádek v přehledu.

### Vedoucí sportu

Přepněte pozici `Vedoucí sportu` a otevřete:

- `http://localhost/evidencePavel/sprava_vsech_treninku.php`
- `http://localhost/evidencePavel/sprava_zavodu.php`
- `http://localhost/evidencePavel/prehled_vsech_vykazu.php`

Očekáváno: přehled tréninků, závodů a výkazů. Staré přímé zapisovače závodu jsou
záměrně ukončené; editace pokračuje přes aktuální formulář.

### Registrář členů a KIS

Přepněte pozici `Registrář členů a KIS` a otevřete:

- `http://localhost/evidencePavel/sprava_sportovcu.php`
- `http://localhost/evidencePavel/kis_rosters_admin.php`
- `http://localhost/evidencePavel/eshop_identity_admin.php`

Očekáváno: správa sportovců, testovací soupisky a propojení účtů. Import KIS
nejprve pouze nahrajte a prohlédněte v náhledu. Nic nepropagujte, pokud zdroj
obsahuje skutečná osobní data.

### Koordinátor programů a sportovišť

Přepněte pozici `Koordinátor programů a sportovišť` a otevřete:

- `http://localhost/evidencePavel/club_program_offers_admin.php`
- `http://localhost/evidencePavel/eshop_events_admin.php`
- `http://localhost/evidencePavel/verejny_velodrom_admin.php`

Očekáváno: nabídka `LOCALHOST podzimní kroužek 2026`, testovací události a dvě
hodiny velodromu dne 1. 6. 2027.

### Správce katalogu e-shopu

Přepněte pozici `Správce katalogu e-shopu` a otevřete:

- `http://localhost/evidencePavel/eshop_produkt_admin.php`
- `http://localhost/evidencePavel/eshop_coupons_admin.php`

Očekáváno: publikovaný testovací katalog a kupón `LOCAL10`. Kupón lze použít
jen na lokální objednávce.

### Zákaznická péče a objednávky

Přepněte pozici `Zákaznická péče a objednávky` a otevřete:

- `http://localhost/evidencePavel/eshop_orders_admin.php`

Očekáváno: seznam objednávek včetně historických lokálních testů. Novou
objednávku potvrzujte jen jako syntetickou. Žádné peníze se neposílají.

### Hospodář a platby

Přepněte pozici `Hospodář a platby` a otevřete:

- `http://localhost/evidencePavel/eshop_payments_admin.php`
- `http://localhost/evidencePavel/member_charges_admin.php`
- `http://localhost/evidencePavel/prehled_kreditu.php`

Očekáváno: platby a vratky, členský předpis `LOCAL-REMINDER-001` a kredity.
Testovací předpis i QR jsou označené jako neplatitelné.

### Správce systému

Přepněte pozici `Správce systému` a otevřete:

- `http://localhost/evidencePavel/sprava_pracovnich_pozic.php`
- `http://localhost/evidencePavel/diagnostika_site_admin.php`
- `http://localhost/evidencePavel/testovaci_scenare.php`

Očekáváno: pracovní pozice, diagnostika a lokální testovací centrum. V testovacím
centru lze demo bezpečně znovu připravit tlačítkem pro reset lokálních dat.

## 6. Rodičovský průchod

Odhlaste administrátora a přihlaste se jako `rodic@localhost.test`.

Postupně otevřete:

1. `http://localhost/evidencePavel/booking/moje_osoby.php`
2. `http://localhost/evidencePavel/booking/sportovni_prehled.php`
3. `http://localhost/evidencePavel/booking/moje_programy.php`
4. `http://localhost/evidencePavel/booking/moje_rezervace.php`
5. `http://localhost/evidencePavel/booking/moje_objednavky.php`
6. `http://localhost/evidencePavel/booking/eshop.php`
7. `http://localhost/evidencePavel/booking/krouzky.php`
8. `http://localhost/evidencePavel/booking/velodrom.php`
9. `http://localhost/evidencePavel/booking/klubovy_kalendar.php`
10. `http://localhost/evidencePavel/booking/treninky.php`
11. `http://localhost/evidencePavel/booking/verejny_profil.php`

Očekáváno: žádná stránka nevrátí přihlášení, 403 ani hlášku o nedostupné
databázi. `Moje osoby` obsahují dvě testovací osoby.

### Test objednávky bez skutečné platby

1. Otevřete `Kroužky`.
2. Vyberte nabídku obsahující `LOCALHOST` a `NEPLATIT`.
3. Vyberte testovací dítě a vložte položku do košíku.
4. Zkontrolujte snapshot souhlasu a cenu.
5. Vytvořte objednávku.
6. Zkontrolujte, že QR a bankovní údaje nesou označení `NEPLATIT` nebo kód
   banky `9999`.
7. QR neskenujte a platbu neodesílejte.
8. Administrátor může lokální platbu ručně označit jako uhrazenou a ověřit
   vznik přihlášky.

## 7. Průchod sportovce

Odhlaste rodiče a přihlaste se jako `localhost-sportovec`.

1. Očekávaná cílová stránka je
   `http://localhost/evidencePavel/booking/muj_sport.php`.
2. Zkontrolujte vlastní tréninky, soupisky, události a platby.
3. Otevřete
   `http://localhost/evidencePavel/booking/moje_objednavky.php`.
4. Očekáváno: přesměrování na rodičovské přihlášení.
5. Stejně ověřte `booking/moje_osoby.php`.

Přesměrování v krocích 3 až 5 je bezpečnostní vlastnost, nikoli chyba.

## 8. Obnovení čistého demo stavu

Nejjednodušší cesta:

1. Přihlaste `localhost-admin`.
2. Přepněte pozici `Správce systému`.
3. Otevřete `http://localhost/evidencePavel/testovaci_scenare.php`.
4. Použijte reset lokálního dema.

Příkazová cesta pro zkušeného uživatele:

```powershell
cd C:\xampp\htdocs\evidencePavel
$env:APP_HOST='localhost'
php bin\seed-local-demo.php
```

Seed obnoví známá hesla, aktivuje účty, znovu udělí všech osm pozic a
superadministrátora a vrátí bezpečné testovací scénáře do výchozího stavu.

## 9. Co dělat při problému

### Stránka hlásí 503 nebo nedostupnou databázi

1. Zavřete prohlížečovou kartu.
2. Znovu spusťte `START_LOCALHOST_TESTOVANI.cmd`.
3. Počkejte na zelenou zprávu.
4. Otevřete hlavní stránku znovu.

### Spouštěč hlásí, že port 3308 používá jiná služba

Nespouštějte další MySQL ručně. Vyfotografujte celé okno s chybou. Testování
datových zápisů ukončete, dokud nebude port uvolněn.

### Stránka patří do jiné pracovní pozice

Otevřete `pracovni_pozice.php`, přepněte pozici uvedenou v hlášce a stránku
otevřete znovu.

### Vzhled nebo nabídky nejsou správné

1. Stiskněte `Ctrl+F5`.
2. Otevřete dva lokální soubory z kapitoly 4.
3. Pokud některý vrátí 404, testování přerušte a poznamenejte přesnou adresu.

### Zapomenuté nebo změněné heslo

Spusťte reset dema podle kapitoly 8. Obnoví se přístupy uvedené v kapitole 2.

### Chyba 500, prázdná stránka nebo poškozená data

Neopakujte stejný zápis. Poznamenejte:

- přesnou adresu stránky,
- čas na minuty,
- použitý účet a pracovní pozici,
- poslední stisknuté tlačítko,
- přesný text chyby nebo fotografii.

Potom ukončete daný scénář. Ostatní nezávislé scénáře lze testovat dál.

## 10. Ověřený technický stav

- Databázové migrace: 70 z 70, žádná čekající.
- Automatické testy: 743 testů, 10 275 tvrzení, vše prošlo.
- PHP syntaxe: 579 souborů, 0 chyb.
- Prohlížeč: všech 8 pracovních pozic potvrzeno.
- Administrace: 21 hlavních obrazovek bez 403/503 a bez chyb konzole.
- Rodičovský portál: 11 hlavních obrazovek bez 403/503.
- Sportovec: přihlášení funguje a rodičovské stránky jsou správně odmítnuté.
- Lokální Bootstrap, JavaScript, ikony a písma: HTTP 200.
- Čistá záloha po migracích a seedu:
  `C:\xampp\mysql\recovery\evidence-clean-backups\evidence_2026-08-25_074608_eb11f107.sql.gz`
- SHA-256 zálohy:
  `5fc547c59813239d76024b7c07920c32ba853dcae50dfd9e794abe4141f00742`

Původní poškozené databázové soubory nebyly smazány. Úplná fyzická kopie je v
`C:\xampp\mysql\recovery\data-before-recovery-20260825-0830` a záchranný
logický export v
`C:\xampp\mysql\recovery\evidence-recovered-20260825.sql`. Pro běžné
testování používejte pouze spouštěč a čistou databázi
`C:\xampp\mysql\evidence-local-data`.
