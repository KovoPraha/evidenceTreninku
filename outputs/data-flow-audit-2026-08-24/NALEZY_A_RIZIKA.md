# Nálezy a rizika z kontroly datových toků

Datum: 24. 8. 2026
Auditovaný Git stav: `30a357551b1e9f483055bf190260deb491d36823`
Metoda: pouze statická kontrola zdrojového kódu. Nálezy nejsou potvrzením aktuální produkční konfigurace ani skutečného výskytu poškozených dat.

## Implementační aktualizace po auditu

V pracovním stromu nad aktuálním `main` (`11f7b1fe883b16b3ccd2d35fe0f4f674fb405647`) byly následně implementovány tyto opravy:

- **H1 + M1 uzavřeno:** `edit_zavod.php` a `import_vysledku_zavodu.php` jsou fail-closed kompatibilní stuby; POST vrací 410 a neobsahují SQL ani upload.
- **H2 uzavřeno:** `sync_evidence.php` zůstal upload/mapování/preview-only; přímý writer osob a skupin byl ze souboru odstraněn.
- **H3 zásadně opraveno:** čtyři kanonické writery tréninků a závodů používají kompenzační file transaction se stagingem, finalize, retire a rollbackem.
- **M3 uzavřeno:** odkazy z rezervace, čekací listiny a cronu používají `appUrl()`.
- **M4 uzavřeno:** UCI XLSX používá dedikovaný `private://uci-temp/...` klíč mimo webroot; stará cesta je blokovaná v Apache a průběžně uklízená.
- **M5 uzavřeno:** `db.php` již nespouští `auto_migrace.php`; DDL zůstává pouze v explicitním `bin/migrate.php`.
- **M2 zůstává otevřeno:** sjednocení přímých e-mailů s existující durable frontou vyžaduje samostatný větší řez.
- **L1 zůstává rozhodnutím vlastníka:** rozsah write capability veřejného profilového tokenu nebyl změněn.

Původní důkazy níže zůstávají zachované jako auditní snapshot před opravou.

## Souhrn priorit

| Priorita | Původně | Otevřeno po opravách | Téma |
|---|---:|---:|---|
| HIGH | 3 | 0 | paralelní zápisové cesty a neatomické DB/filesystem změny |
| MEDIUM | 5 | 1 | zbývá sjednocení přímého doručování zpráv s durable frontou |
| LOW / rozhodnutí | 1 | 1 | bearer veřejné karty s právem zápisu poznámky |

## H1 – Paralelní endpoint `edit_zavod.php` obchází kanonický kontrakt závodu

Závažnost: **HIGH**
Typ: integrita dat / nejednotný writer
Stav: **vyřešeno v implementaci 29. 8. 2026**; původní důkaz níže

### Důkaz

- Kanonický create formulář odesílá do `ulozit_zavod.php` (`formular_zavod.php:161`).
- Kanonická editace odesílá do `update_zavod.php` (`edit_zavod_form.php:309`).
- `edit_zavod.php` je přesto samostatně volatelný POST endpoint; vyžaduje pouze přihlášeného trenéra a CSRF, nikoli `canAccess('sprava_zavodu')` (`edit_zavod.php:3-15`).
- Navzdory názvu nevytváří editaci, ale nový `INSERT INTO zavody`, bez `kategorie`, `url_vysledky` a bez normalizovaných měření (`edit_zavod.php:76-89`).
- Účastníky hledá jen podle jediného textového `sportovci.jmeno`; nenalezené jméno založí jako nového sportovce (`edit_zavod.php:112-134`).
- Soubor zůstává i v registru obsluhovaných staff rout (`includes/staff_workspaces.php:263`). V první straně nebyl nalezen formulář s action na tento endpoint.

### Dopad

Přímý nebo historický klient může založit závod jiným způsobem než UI. Výsledkem mohou být závody bez současného kontraktu, duplicitní osoby a jiná oprávnění než u kanonického handleru.

### Doporučení

Endpoint odstranit/vracet 410, nebo z něj udělat pouze delegaci do jednoho kanonického writeru. Přidat test, že existují právě dva povolené závodní writery (create/update) a že každý používá `sportsMeasurementRowsFromPost()` a stejné oprávnění.

## H2 – Starší KIS wizard zapisuje přímo do kanonických osob mimo fingerprintovaný promote tok

Závažnost: **HIGH**
Typ: integrita osob a členství / paralelní importní architektura
Stav: **vyřešeno v implementaci 29. 8. 2026**; původní důkaz níže

### Důkaz

- `sync_evidence.php` povoluje vstup každému, kdo má konfigurovatelné `canAccess('sync_evidence')` (`sync_evidence.php:1-8`).
- Serverová execute větev vyžaduje CSRF a field kontrakt bez blockerů, ale nepřijímá uložený preview fingerprint, důvod ani samostatné explicitní potvrzení (`sync_evidence.php:568-583`). Atribut `data-confirm` je pouze klientské potvrzení (`sync_evidence.php:1355-1366`).
- `executeSync()` přímo aktualizuje `sportovci` (`sync_evidence.php:698-700`), při namapovaných soupiskách smaže všechny stávající skupinové/podskupinové vazby a vloží nové (`sync_evidence.php:718-730`) a může založit novou osobu (`sync_evidence.php:774-787`).
- Vedle toho `kis_sync_center.php` nabízí oddělené fingerprintované, důvodované a potvrzené sandbox/charge promote a rollback funkce (`includes/kis_import_sandbox_promotion.php:101`, `includes/kis_member_charge_promotion.php:255`).

### Dopad

Mezi preview a execute se mohou změnit kanonická data nebo ruční vazby. Starší cesta může přepsat novější ruční rozhodnutí a obchází bezpečnostní model, který už aplikace používá u novějších KIS promote toků.

### Doporučení

Změnit `sync_evidence.php` na upload/mapování/preview-only. Kanonický zápis provádět jednou společnou promote funkcí, která na serveru ověří fingerprint, očekávané verze řádků, aktéra, pracovní pozici, důvod a explicitní potvrzení. Zachovat rollback/event log.

## H3 – Databázová transakce a souborové změny nejsou atomické

Závažnost: **HIGH**
Typ: integrita příloh
Stav: **vyřešeno v implementaci 29. 8. 2026**; původní důkaz níže

### Důkaz

- Nový trénink přesune upload v `ulozit_trenink.php:80`, ale DB transakce začne až na `ulozit_trenink.php:101`; catch provede pouze DB rollback (`ulozit_trenink.php:310-313`).
- Nový závod přesune fotografie a výsledkové soubory na `ulozit_zavod.php:66,102`, transakce začne až na `ulozit_zavod.php:122` a catch soubory neuklidí (`ulozit_zavod.php:225-229`).
- Editace tréninku přejmenuje odebrané obrázky na `smazano_*` před zahájením transakce (`update_trenink.php:98-103` versus `update_trenink.php:138`). Při pozdější DB chybě rollback obnoví starou DB hodnotu, ale soubor už má jiné jméno.
- Editace závodu přesouvá nové soubory před transakcí (`update_zavod.php:96,125`) a soft-delete existujících souborů volá uvnitř DB transakce (`update_zavod.php:190-215`), avšak filesystem se při DB rollbacku nevrací.

### Dopad

Chyba validace/SQL po přesunu souboru může vytvořit orphan soubor nebo naopak ponechat DB odkaz na přejmenovaný soubor. U editace může uživatel přijít o dostupnou přílohu, i když DB změna skončí rollbackem.

### Doporučení

Nahrávat nejprve do soukromého stagingu. V DB transakci zapisovat pouze plán změn a nové klíče; fyzické publikování/soft-delete dokončit po commitu idempotentním finalize krokem. Na catch vždy uklidit nové staging soubory a obnovit původní jména. Vhodný je malý file-operation journal/outbox.

## M1 – XLS/XLSX import závodních výsledků nejprve maže a pak páruje pouze podle jména

Závažnost: **MEDIUM**
Typ: integrita výsledků / dormant legacy endpoint
Stav: **vyřešeno v implementaci 29. 8. 2026**; původní důkaz níže

### Důkaz

- Po načtení workbooku handler bez transakce vynuluje `poradi`, `cas` a `body` všem účastníkům závodu (`import_vysledku_zavodu.php:50-58`).
- Řádky zpracovává postupně do prvního prázdného jména (`import_vysledku_zavodu.php:60-67`).
- Párování používá pouze `sportovci.jmeno`, nikoli stabilní ID, celé jméno + narození/UCI ani explicitní preview (`import_vysledku_zavodu.php:69-89`).
- Po dokončení redirectuje na `edit_zavod.php?id=...`, který při GET pouze přesměruje na přehled, místo na `edit_zavod_form.php` (`import_vysledku_zavodu.php:94-96`, `edit_zavod.php:7-10`).

### Dopad

Výjimka nebo nejednoznačné jméno může po předchozím vynulování zanechat částečné/nesprávné výsledky. Duplicitní jména mohou způsobit chybu subquery nebo aktualizaci nesprávné osoby.

### Doporučení

Buď endpoint odstranit spolu s legacy routou, nebo zavést staging+preview, stabilní párovací klíč, explicitní řešení neshod a jednu transakci pro celý clear-and-apply. Redirect opravit na kanonický detail/edit formulář.

## M2 – Část e-mailů a push notifikací obchází trvalou frontu a UI hlásí úspěch i bez doručení

Závažnost: **MEDIUM**
Typ: spolehlivost vedlejších efektů / nejednotný datový tok
Stav: **otevřeno**; potvrzeno zdrojovým kódem

### Důkaz

- Registrace commitne účet a následný návrat `mail()` ignoruje, přesto nastaví success (`booking/registrace.php:104-120`).
- Obnova hesla commitne token a volá callback; boolean `false` z callbacku se nekontroluje, zachytí se jen výjimka (`includes/password_reset.php:47-60`, `booking/zapomenute_heslo.php:27-38`).
- Rezervace lekce po DB zápisu best-effort spouští push a přímý mail, ale následně hlásí potvrzení/odeslání žádosti (`booking/rezervovat.php:163-208`).
- Správa lekcí a čekací listina mění stav a přímo volají `mail()` (`individualni_lekce_sprava.php:47-105`, `booking/waiting_list.php`).
- `cron_upominky.php` považuje `mail() === true` za odeslání a zapíše `upominka_cas` (`cron_upominky.php:108-115`).
- Naproti tomu platba, členské připomínky a týdenní souhrny už používají trvalou frontu/outbox.

### Dopad

DB stav může být správný, ale uživatel/trenér zprávu nedostane a systém nemá spolehlivý retry ani jednotnou auditní stopu. `mail() === true` navíc znamená pouze přijetí lokálním transportem, ne doručení do schránky.

### Doporučení

Rozšířit existující `club_event_notifications`/worker, nezakládat druhou frontu. Vkládat notifikaci uvnitř stejné transakce jako doménovou změnu; worker má řešit claim, retry, expiraci a transport. UI má říkat „změna uložena, oznámení zařazeno“, nikoli „e-mail odeslán“.

## M3 – Tři notifikační odkazy používají starý host místo kanonického `APP_BASE_URL`

Závažnost: **MEDIUM**
Typ: konfigurační drift / chybné odkazy
Stav: **vyřešeno v implementaci 29. 8. 2026**; původní důkaz níže

### Důkaz

- Produkční fallback `APP_BASE_URL` je `https://kis.kovopraha.cz` (`config.php:33-42`, shodně `config.example.php:30-40`).
- Cron upomínek používá `https://data.kovopraha.cz/evidence` (`cron_upominky.php:22`).
- Push rezervace používá stejný starý absolutní odkaz (`booking/rezervovat.php:168-173`).
- Čekací listina skládá starý booking base (`booking/waiting_list.php:75`).

### Dopad

E-mail/push může uživatele poslat na historickou nebo neexistující cestu, případně přes jiné cookies/redirecty než kanonická aplikace.

### Doporučení

Všechny odkazy stavět přes `appUrl()`/`APP_BASE_URL`; odstranit lokální absolutní konstanty. Přidat statický test zakazující first-party výskyty historického hostu mimo výslovnou kompatibilní konfiguraci.

## M4 – UCI upload se na 24 hodin ukládá do webrootu `uploads/temp`

Závažnost: **MEDIUM**
Typ: důvěrnost dočasných souborů
Stav: **vyřešeno v implementaci 29. 8. 2026**; původní důkaz níže

### Důkaz

- `export_uci.php` vytváří `uploads/temp`, ukládá tam uživatelem nahrané XLSX a drží cestu v session (`export_uci.php:47-70,101-116`).
- Cleanup odstraňuje soubory až po 24 hodinách nebo při resetu (`export_uci.php:47-55,125-134`).
- Kořenový `.htaccess` zakazuje pouze vybrané citlivé podadresáře a spustitelné přípony; `uploads/temp/*.xlsx` není výslovně blokováno (`.htaccess:17-21`). `Options -Indexes` brání listingu, nikoli přímému stažení známé URL.

### Dopad

Pokud nahraná šablona obsahuje předvyplněná osobní data nebo se URL dostane do logu/hlášení, soubor lze po dobu retence vydat přímo Apachem bez aplikační autorizace.

### Doporučení

Použít systémový temp nebo `PRIVATE_STORAGE_ROOT` mimo webroot, session ukládat pouze opaque klíč a po exportu soubor okamžitě odstranit ve `finally`. Defense-in-depth: blokovat celé `uploads/temp` v Apache.

## M5 – Legacy DDL se stále může spustit v běžném webovém requestu

Závažnost: **MEDIUM**
Typ: provozní dostupnost / schema drift
Stav: **vyřešeno v implementaci 29. 8. 2026**; původní důkaz níže

### Důkaz

- Každé načtení `db.php` zahrne `includes/auto_migrace.php` (`db.php:36-37`).
- Soubor deklaruje zmražený baseline 2.20.2 a nové změny směruje do `bin/migrate.php` (`includes/auto_migrace.php:6-10`).
- Při stavu `missing` nebo `pending` však request získá advisory lock a pokračuje v DDL/seedu (`includes/auto_migrace.php:30-50` a následné DDL bloky).

### Dopad

První běžný request po chybějícím/nižším trackeru může provést rozsáhlé DDL s webovou DB rolí a časovým limitem requestu. Chyba se zkusí znovu při dalších requestech, což zhoršuje incident a komplikuje jednoznačné vlastnictví migrace.

### Doporučení

Po potvrzeném baseline změnit auto-migraci na read-only fail-closed kontrolu nebo ji z webového bootstrapu odstranit. DDL ponechat pouze v explicitním `bin/migrate.php --apply` před aktivací release.

## L1 – Veřejný profilový bearer token současně opravňuje ke čtení i zápisu poznámky

Závažnost: **LOW / produktové rozhodnutí**
Typ: capability URL / rozsah oprávnění
Stav: **otevřené produktové rozhodnutí**; token je kryptograficky silný a odpovědi mají `no-referrer`/`no-store`

### Důkaz

- Token je 64 hex znaků z `random_bytes(32)` (`includes/public_profile_token.php:4-12`).
- `sportovec_treninky.php?hash=` zpřístupní kartu bez přihlášení a vloží token do JS (`sportovec_treninky.php:69-76,988-1012`).
- Stejný bearer + anonymní session CSRF umožní upsert `sportovec_poznamka`, pokud trénink patří sportovci (`ajax_sportovec_poznamka.php:13-64`).

### Dopad

Kdokoli, komu je URL přeposlána nebo unikne z historie/logu, získá kromě čtení i právo měnit poznámky. CSRF chrání proti cizímu webu, nikoli proti držiteli bearer URL.

### Doporučení

Potvrdit, zda je zápis poznámky zamýšlená capability. Pokud ano, popsat to v UI a přidat snadnou rotaci/odvolání tokenu. Pokud ne, oddělit read token od krátkodobého write tokenu nebo vyžadovat přihlášený sportovní účet.

## Pozitivní kontroly potvrzené v kódu

- Přihlašovací session, CSRF a revokační verze jsou sjednocené; rate limit zamyká řádky transakčně.
- Checkout má idempotency klíč, fingerprint, serverové snapshoty, lock ordering a fail-closed kontrolu ceny/kapacity.
- Banka i Stripe používají jeden kanonický platební přechod a stejnou transakční notifikační frontu.
- Stripe ověřuje podpis, event ID, metadata, částku a měnu a neukládá raw payload.
- KIS a Shoptet mají oddělené dry-run/staging/report fáze a zdrojové hashe.
- Soukromé přílohy mají opaque klíč, MIME allowlist, uložení mimo webroot a autorizovaný výdej.
- Auditní helper rediguje hesla, secrets, tokeny, cookies a CSRF před uložením detailu (`includes/funkce.php:53-76`).

## Doporučené pořadí nápravy

1. Přesměrovat zbývající přímé e-maily do existující durable fronty (`M2`).
2. Produktově potvrdit write oprávnění veřejného profilového tokenu (`L1`).

Ostatních sedm položek auditu bylo 29. 8. 2026 implementačně uzavřeno a je kryto regresními testy.
