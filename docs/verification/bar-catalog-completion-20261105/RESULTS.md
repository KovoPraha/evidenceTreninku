# Dokončení správy katalogu baru

5. 11. 2026, pokračování B3 v draft PR #306. [Plán a další krok](../../BAR_INTEGRATION_PLAN_2026-11-05.md). Implementace a ověření používají pouze syntetická místní data; skutečný zdroj ani produkční prostředí se nemění.

## Výsledek

Správce baru vytváří a upravuje kategorie včetně pořadí. Původní vazby, zdrojové ID a barevný příznak se zachovávají. Použitou kategorii nelze odstranit ani tehdy, pokud obsahuje pouze archivované produkty; nejprve je třeba produkty přesunout. Prázdnou kategorii lze odstranit s důvodem. Změny i odstranění vyžadují aktuální verzi a vznik auditu ve stejné transakci.

Produkt má samostatný formulář nahrání a odebrání fotografie. HTTP vstup přijímá pouze skutečně nahraný soubor, ověřuje JPEG/PNG/WebP podle obsahu a proveditelného dekódování, velikost a rozměry. Původní název souboru od klienta se nepoužívá. Soubory jsou soukromé a neměnné podle obsahu. Jeden obrázek lze sdílet; jeho nahrazení se promítne pouze do vybraného produktu. Starší obrázky se nemažou, aby zůstaly obnovitelné. Bez vazby na produkt se nevydávají ani přihlášenému správci. Opakování stejného obrázku nezvyšuje verzi ani nevytváří další audit.

Po zjištění souběžné úpravy se vrátí konflikt a formulář ponechá odmítnutou verzi. Další stisk tlačítka tak nemůže nepozorovaně přepsat novější data; uživatel musí stránku obnovit a změnu zkontrolovat. Ochrana platí pro kategorii i produkt, fotografii a recepturu. Obsluha baru nemá přístup ke kategoriím, uploadu ani editaci.

## Ověření

| Kontrola | Výsledek |
| --- | --- |
| Celý PHPUnit | 2083 testů / 39467 assertions, bez selhání, 3 unixové testy přeskočené na Windows |
| Cílená sada | 93 testů / 2612 assertions PASS; zahrnuje nové chování, migrace, zálohu a registr přístupů |
| Nové doménové scénáře | Sdílené fotografie a výměna jednoho produktu, odebrání a nepřístupný starý obrázek, vadný obrázek, opakování, stale verze, revokace, kolize názvu, archivované produkty a rollback při selhání auditu PASS |
| Statická analýza | PHPStan 838 souborů PASS, žádné nové potlačení |
| Styl a Composer | Styl všech 51 změněných PHP souborů proti main, lint 15 PHP souborů této etapy, `composer validate --strict` a `git diff --check` PASS |
| Anonymní HTTP | 244 chráněných tras, 34 veřejných výjimek, 2 CLI, 0 chyb |
| MariaDB 10.4.32 | Opakovaná nová migrace, úprava/odstranění kategorie, stale verze, připojení/odebrání fotografie, opakování původního převodu a zachovaná zásoba PASS |
| Skutečná syntetická obnova DB | 309 tabulek PASS, včetně nového sloupce verze kategorie v ownership kontraktu 2026-11-05.3 |
| Místní browser | Správce: vytvoření, přejmenování a odstranění prázdné kategorie; skutečný výběr souboru a multipart upload, odebrání fotografie a následné HTTP 404 staré vazby. Lucka: přihlášení jménem a HTTP 403 správy kategorií PASS |
| Dvě skutečné záložky | Novější úprava kategorie uložená; první odeslání staršího formuláře i jeho opakování vrátí HTTP 409. Kontrola DB potvrdila zachovaný novější název a verzi 2, bez přepsání starým formulářem |

První úplný běh zachytil novou stránku ještě nezařazenou do Git inventáře anonymního testu. Po jejím zařazení proběhla cílená i úplná sada znovu úspěšně. Další nové baseline výjimky se nepřidávaly. Linux CI přesného headu zůstává samostatnou branou pro MariaDB 10.3/11.4 a unixové symlink scénáře.

Snímky místního browseru jsou v ignorovaném `output/playwright/`. Testovací PHP server má odesílání mailu vypnuté; žádná reálná zpráva, platba ani účetní zápis nevznikly. Originální BAR WIP a původní webroot zůstaly bez editace. Pokladna, kasy, storna, kelímky a odběr vlastní spotřeby pokračují v B4; nejsou tímto výsledkem označené za dokončené. Produkční deploy ani skutečný převod nebyly provedené.
