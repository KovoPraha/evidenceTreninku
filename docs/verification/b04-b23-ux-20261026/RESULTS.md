# B04/B23 – UX opravy 26. 10. 2026

Výchozí revize: `5df4b027` (`origin/main`). Větev: `codex/b04-b23-ux-20261026`.

## Implementováno

- Nepřihlášené vstupy do B04/B23 přesměrují na přihlášení a uchovají bezpečnou návratovou adresu. Povolené jsou pouze dvě konkrétní stránky a číselné identifikátory / SHA-256 otisk; cizí adresy, akce, pole a řídicí znaky končí na pracovním rozcestníku. Návrat neruší kontrolu pracovní pozice a neopakuje původní POST.
- Společný přihlašovací formulář uvádí „Přihlášení do KIS“.
- Nedostupný závod má standardní stránku s vysvětlením, návratem na seznam a pracovní rozcestník; zachován HTTP 404. Dodatečné odmítnutí oprávnění má vysvětlení vlastníka agendy a návrat, se stavem 403. Stávající centrální odmítnutí nesprávného pracovního kontextu zůstává zachováno.
- Prázdný seznam závodů ukazuje popis dalšího kroku; při aktivních filtrech nabízí jejich zrušení. Neprohlašuje, že v celé databázi nejsou závody, protože přehled zobrazuje dostupné záznamy podle svého původního dotazu.
- KIS rozlišuje neexistující běh, nesprávný stav a chybějící archivovaný export. Zachovány všechny kontroly manifestu, otisku, oprávnění i omezení zápisu na izolaci.
- Průvodce používá „Nahrání souborů“, „Náhled“ a „Uživatelé z KIS“.

## Ověření

- PHP lint: osm změněných aplikačních PHP souborů bez chyby; `git diff --check` bez chyby.
- Cílená sada `StaffLoginReturnTest|KisReviewHandoffTest|RaceResultsTest|AuthWiringTest|SharedUiShellTest`: **43 testů / 295 assertions, PASS**.
- Celá lokální sada: **1 743 testů / 35 111 assertions, bez selhání; 3 přeskočené testy**. V tomto kroku nebyl proveden nový Linux CI běh.
- Chrome, výhradně `http://127.0.0.1:18098`: anonymní vstup B04 → přihlášení syntetickým účtem → návrat na původní ID → standardní nedostupný závod → seznam. PASS.
- Prázdný seznam bez filtru → filtr trenéra → odlišné vysvětlení → Zrušit filtry → původní stav. PASS.
- Registrář → neexistující KIS běh → přesné hlášení → funkční návrat do centra. PASS.
- Úvodní obrazovka průvodce: nové české popisky. PASS, bez nahrání souborů.
- Odhlášení → anonymní vstup B23 → přihlášení → návrat do B23 se zachovaným parametrem → odmítnutí nesprávného výchozího sportovního kontextu. PASS; návrat nepřiděluje oprávnění.
- Vizuální kontrola desktopového zobrazení: `empty-races.png`, `missing-kis-run.png`. Mobilní layout nebyl samostatně testován.

## Izolace a stav předání

Vlastní checkout, HTTP 18098, MariaDB 10.3.39 na 33438, vlastní syntetická databáze a soukromé soubory/sessions mimo webroot. Runtime `%LOCALAPPDATA%/EvidencePavelB1/b04-b23-ux-v2-20261026`, ověřen vlastník, identita databáze a její datadir. Odchozí transporty a integrace vypnuté. Browserový seed vytvořil jen syntetického správce; žádné skutečné osoby ani importy.

První nová instance XAMPP MariaDB 10.4 se při vytváření schématu zablokovala na InnoDB semaforech. Po ověření vlastnictví byla zastavena; její soubory zůstaly zachovány v původním `b04-b23-ux-20261026`. Druhé prostředí používá již dostupnou binární instalaci MariaDB 10.3 a vlastní nový datadir. Nastavení ani data jiných prostředí se neměnila.

Pomocné startovací/seed skripty, lokální konfigurace a vendor nejsou součástí commitu. Nasazení ani sloučení do main nebylo provedeno. Produkce v tomto kroku nebyla otevřena ani měněna. Úpravy textů nepředstavují dokončenou přejímku reálného závodu nebo importu.
