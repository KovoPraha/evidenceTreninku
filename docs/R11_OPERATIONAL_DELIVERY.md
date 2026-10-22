# R11 — provozní zprávy a dohled nad úlohami

Lokální implementace z 22. 10. 2026. Žádná rozesílka, plánovač ani integrace se tím nezapíná.

## Dodané chování

- Vytvoření rezervace, povýšení čekajícího, potvrzení, zamítnutí i zrušení lekce zapisují oznámení do `club_event_notifications` ve stejné transakci jako změnu rezervace. Zámek slotu zůstává společný pro rezervaci a čekací listinu.
- Zpráva má stabilní klíč rezervace / události / příjemce. Opakování workeru neopakuje rezervaci, schválení ani jinou obchodní operaci. Fronta neobsahuje nové bearer odkazy pro schválení: trenér otevírá přihlášený přehled lekcí. Staré již vydané schvalovací odkazy zůstávají kompatibilní; jejich potvrzení nyní atomicky zapisuje oznámení přes callback.
- Před předáním transportu se znovu ověřuje stav a obsah rezervace, konec platnosti, aktuální příjemce, aktivní účet a časově platné schválené zastupování. Změna data, času, adresy či odvolání vazby blokuje starý obsah. Přihlášky a aktivované programy kontrolují svůj aktuální případ a příjemce.
- Stejná kontrola aktuálních oprávnění platí pro připomínky příspěvků a zprávy soupisek. Rodinný týdenní přehled navíc porovnává otisk oprávnění uložený při vytvoření. Historický souhrn bez otisku je bezpečně zadržen k posouzení.
- Výslovné odmítnutí transportem používá omezené opakování s prodlevou. Výjimka transportu nebo opuštěné převzetí znamená nejisté doručení: zpráva zůstane `failed` s důvodem `delivery_unknown`, automaticky se znovu neposílá. SMTP neposkytuje důkaz skutečného přečtení ani obecné zaručení právě jednoho doručení; operátor musí nejistý výsledek ověřit před ručním opakováním.
- Chybějící kontakt vytváří viditelný případ `manual_contact_required`. Obsluha objednávek či superadmin může v transakční frontě zaznamenat osobní předání nebo ověřené doručení s důvodem a potvrzením. Audit `manual_contact_resolved` neoznačí zprávu za odeslanou. Nové ruční opakování ruší účinek tohoto vyřízení v provozním přehledu.
- `cron_upominky.php` pouze transakčně připravuje jednorázové připomínky nezaevidovaných tréninků do fronty; neodesílá je přímo. `upominka_cas` nyní dokládá zařazení; výsledek doručení je ve frontě.
- Staré hromadné rozesílání veřejných profilových odkazů podle kontaktního e-mailu je ukončeno. `odeslat_emaily.php` vysvětlí změnu a odkáže na existující zprávy soupisek; POST vrací 410 bez vytvoření tokenu či zprávy. Oprávnění příjemce se neodvozuje pouze z adresy a pracovníkovi se nepřidává nová role. Před nasazením je třeba tuto změnu obsluze vysvětlit.

Nepovinný dosavadní WebPush rezervace zůstává po commitu jako best-effort doplněk. Garantované trvalé zařazení a nové přejímací scénáře se týkají e-mailu; push není vydáván za doručený e-mail. Testovací sandbox nepovoluje síťový transport.

## Dohled

`includes/operational_jobs.php` poskytuje `operationalJobsStatus(PDO)` pro existující síťovou diagnostiku a provozní přehled. Katalog obsahuje transakční zprávy včetně ověřování účtu a obnovy hesla (obě fronty zpracovává jediný `bin/club-event-notifications.php`, samostatný auth worker neexistuje), tréninkové připomínky, rodinné souhrny, příspěvkové připomínky, zprávy soupisek, expiraci objednávek, bankovní import, nabídky programů a zálohování.

Každá úloha má vlastníka, doporučený interval, poslední doložený úspěch, stav a další krok. Osm aplikačních vstupních bodů zapisuje začátek a skutečný výsledek běhu. Suchý běh expirace a obnovy nabídek nezapisuje úspěšné provedení. Samostatný deploy zálohovací skript není změněn na závislý aplikační worker; chybějící důkaz zálohování se zobrazuje jako nedoložený, nikoli zelený. Poslední úspěchy se ukládají v UTC.

Proměnné `JOB_MONITOR_<JOB_KEY>` přijímají `expected` nebo `disabled`. Jde o deklaraci očekávaného provozu pro dohled, nikoli přepínač skutečného plánovače. Bez deklarace se zobrazí chybějící provozní konfigurace. Žádná proměnná nebyla nastavena na produkci. Dostupnost fronty, zdravotní stav workeru a doručení zprávy jsou různé důkazy.

## Migrace a ověření

Řízená migrace `20261022212000_operational_delivery` přidává `booking_notification_context`, `notification_authorization_snapshots` a `operational_job_runs`. Neobsahuje historický import, reset stávajících osob ani aktivaci služeb. Tabulky musí být součástí zálohovacího kontraktu celého balíku.

Přejímka zahrnuje transakční rollback, opakované zařazení, prodlevu a ukončení opakování, cizí/opuštěný claim, odvolané zastupování, deaktivovaný účet, změněnou adresu, datum i čas lekce, expirované oznámení, chybějící kontakt, audit osobního vyřízení a rozdíl vypnuté/chybně nastavené/opožděné/selhané úlohy. Regresní testy používají SQLite a injektovaný transport, ne síť.

Skutečný souběh dvou PHP procesů na MariaDB ověřuje:

```powershell
C:\xampp\php\php.exe tests/Support/OperationalDeliveryMariaDbRace.php
```

Skript ověří izolovaný descriptor, port 33426 a datový adresář B1, používá výhradně vlastní schéma `evidence_b2_notifications`, vlastní marker a syntetické adresy. Výsledek: 7 kontrol, právě jeden claim, právě jeden soubor místního outboxu, žádné automatické opakování nejistého doručení. Syntetické důkazy zůstávají v odděleném runtime B1. Skript nepracuje s existujícími klubovými daty ani se skutečným transportem.

Finální souhrn společné sady a HTTP přejímky patří do předání celého balíku R07–R12; lokální úspěch nenahrazuje převzetí obsluhou ani souhlas s nasazením.
