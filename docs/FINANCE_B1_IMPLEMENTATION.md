# B1 — ochrana uzavřené finanční evidence

Předání k 22. 10. 2026. **Implementace a automatická přejímka prvního balíku jsou dokončené lokálně. Produkce nebyla změněna.** Nejde o dokončení celých etap 0–6 ani o účetní převzetí hospodářem.

Rozsah odpovídá balíku B1 (R04 + R05) ze sjednoceného realizačního plánu. Práce proběhla ve větvi `codex/finance-b1-20261022`, v samostatné pracovní kopii `C:\xampp\htdocs\evidencePavel-finance-b1-20261022`, ze základu `68b22bd2b3b666dbb4fb571a2731eae5b7f056d9`. Původní checkout a jeho rozpracovaná změna `kis_rosters_admin.php` zůstaly zachovány. Nebyl proveden push, merge ani deploy.

## Výsledné chování

- **Účtenky a finanční akce:** uzavřenou akci nelze běžně změnit, otevřít nebo smazat. Nelze k ní přidat účtenku ani její účtenku upravit, smazat, vyměnit přílohu nebo přesunout do jiné akce. U přesunu se kontroluje skutečný zdroj načtený z DB i cílová akce.
- **Zámky a audit:** kontrola stavu, zápis a audit jsou ve stejné transakci. Čekající požadavek po dokončení uzavření znovu načte chráněný stav. Souběžný zápis nemůže obejít kontrolu předchozím načtením formuláře.
- **Přílohy:** selhání SQL nebo auditu vrátí původní soubor; nezůstane nový osiřelý upload. Úspěšné smazání či výměna zachovává dosavadní skrytý archiv `.deleted-*`. Pokud přesun do archivu po commit selže, soubor zůstane obnovitelný pod skrytým názvem a chyba se zaloguje.
- **Nepřímá změna účtenky:** nelze smazat vozidlo, pokud by databázové `ON DELETE SET NULL` změnilo účtenku uzavřené akce. Vozidlo bez takové vazby lze dál smazat.
- **Odměny:** změny probíhají přes jeden společný transakční postup. Uzavřené či vyplacené období se běžnou editací nebo hromadnou sazbou nepřepíše. Opakované uzavření je viditelně odmítnuto a původní souhrn zůstane zachován. Hromadná sazba může založit nové otevřené období, pokud předchozí už skončilo.
- **Nejasná historie:** chybějící historický souhrn se nevypočítává dnešními pravidly. Zobrazí se „Nevyřešeno: chybí souhrn“ a neznámá částka se nezahrnuje jako domnělý příjem či odměna. Vyplacené, ale stále otevřené období blokuje hromadnou změnu a nezobrazuje se jako aktivní kredit.
- **Oprávnění:** zůstává existující model pracovních pozic a oprávnění. Každá mutace odměn navíc vyžaduje existující `kreditni_obdobi`, včetně rychlé akce z přehledu. Běží původní ověření CSRF, aktuální aktivity účtu a odebraných pozic.
- **Obsluha:** uzavřené finanční formuláře jsou jen pro čtení, odmítnutí obsahuje konkrétní případ a předání hospodáři. Neexistuje skrytá výjimka pro přepis historie.

Nevznikla nová produkční migrace ani nová soustava rolí. Existující příznak „vyplaceno“ stále není dokladem skutečné platby; platební vypořádání patří do navazujícího R34.

## Inventář chráněných zápisů

| Agenda | Cesty |
|---|---|
| Účtenka, soubor a přesun | `uctenky/uloz.php`, `uctenky/smazat.php` |
| Finanční akce, stav, smazání | `udalosti/uloz.php`, `udalosti/uzavrit.php`, `udalosti/smazat.php` |
| Nepřímé odstranění vazby na vozidlo | `vozidla/smazat.php` |
| Odměny — vytvoření/editace, uzavření, označení vyplacení | `sprava_sportovec_obdobi.php` |
| Rychlé uzavření a označení vyplacení | `prehled_kreditu.php` |
| Hromadná změna sazeb | `hromadne_odmeny.php` |

Běžný samostatný endpoint pro mazání období odměn neexistuje; neznámé akce se odmítají. Historické schéma/backfill a importy nejsou v tomto balíku měněné. Doplňující zobrazení byla sjednocena v kartě, detailu a trénincích sportovce.

## Ověřené výsledky

| Ověření | Výsledek |
|---|---|
| Celá PHPUnit sada po změnách | **PASS — 1 237 testů, 27 132 kontrol** |
| Skutečné HTTP požadavky nad izolovanou plnou DB | **PASS — 54 kontrol** |
| MariaDB — souběhy finanční akce a účtenky | **PASS — 2 pořadí zápisu** |
| MariaDB — odměny | **PASS — 3 souběhy, 17 kontrol** |
| Izolace prostředí | **PASS — samostatný datadir/port, sessions a úložiště, zakázané externí transporty, lokální mail sink** |
| Zastavení a opětovné spuštění sandboxu | **PASS — ověřené procesy ukončeny, data zachována, nové spuštění a kontrola izolace úspěšné** |
| Syntaxe PHP a kontrola rozdílů | **PASS** |

První běh celé sady odhalil zastaralý wiring test vyžadující původní funkci úklidu uploadu. Byl aktualizován na transakční rollback a zachování archivu. Následný úplný běh je výše uvedený PASS. Nejde o vynechání testu nebo potlačení chyby. Nezávislá revize doplnila porovnání celého souborového inventáře po selhání uploadu; HTTP přejímka nyní prokazuje také nepřítomnost osiřelých souborů.

Zkouška restartu opravila dvě chyby ve spouštěčích: předání loopback adresy do `mysqladmin` a čekání na úplné ukončení/start databáze. Finální postup čeká na konec konkrétního procesu a ověřuje skutečný SQL dotaz, nikoli pouze existenci naslouchajícího portu. Po úspěšné zkoušce je sandbox ponechán spuštěný pouze na loopbacku.

### Návaznost na deset přejímacích bodů B1

| Bod plánu | Důkaz | Stav |
|---|---|---|
| 1. Oprávněný otevřený zápis / nepovolaná pozice | HTTP vytvoření a editace; trenér dostane 403 i přímým POST. | Automaticky ověřeno |
| 2. Neměnnost uzavřené akce a účtenek | HTTP přidání, editace, delete, příloha, editace/otevření/delete akce. | Automaticky ověřeno |
| 3. Obě strany přesunu | HTTP přesun z uzavřeného zdroje i do uzavřeného cíle; DB zůstává stejná. | Automaticky ověřeno |
| 4. Souběh a soubory při chybě | Dvě reálná MariaDB spojení v obou pořadích; HTTP audit trigger vyvolá selhání po práci se souborem, porovnají se DB i všechny názvy a SHA-256 souborů. | Automaticky ověřeno |
| 5. Souhrn při prvním a opakovaném uzavření | HTTP první uzavření, opakování oběma stránkami; MariaDB close-vs-close. | Automaticky ověřeno |
| 6. Editace a hromadné sazby | PHPUnit + HTTP placené/uzavřené období a paid-open konflikt; MariaDB close-vs-save a close-vs-bulk. | Automaticky ověřeno |
| 7. Historie bez souhrnu | Žádný přepočet; zobrazená konkrétní hláška a neznámá částka. | Automaticky ověřeno |
| 8. Rozsah zápisů a audit | Přesné porovnání chráněných řádků, transakční audit a rollback dávky; změny nepřepisují platební tabulky ani VS. | Automaticky ověřeno pro B1 |
| 9. CSRF a odebrání přístupu | HTTP chybný CSRF, deaktivovaný účet (303), odebraná pracovní pozice existující session (403). | Automaticky ověřeno |
| 10. Srozumitelnost blokace a přijetí omezení hospodářem | HTML/error odpovědi ověřeny programově; blokace uvádí případ a další postup. | Technicky ověřeno; osobní převzetí hospodářem neprovedeno |

HTTP test používá skutečné routy, middleware a nahrání souboru. Přihlášení pro syntetické účty připravuje CLI session fixture; nejde o test přihlašovacího formuláře ani ruční uživatelskou prohlídku. Obchodní souhlas hospodáře nelze nahradit automatickým testem.

## Reprodukce a důkazy

Postup prostředí: [FINANCE_B1_SANDBOX.md](FINANCE_B1_SANDBOX.md).

```powershell
php vendor/bin/phpunit --configuration phpunit.xml.dist
php tests/Support/FinanceB1HttpAcceptance.php
php tests/Support/FinancialEventMariaDbRace.php
php bin/finance-b1-reward-concurrency.php
```

HTTP skript odmítá jinou databázi/port/URL. Race odměn používá vlastní označenou databázi `evidence_b1_rewards`; pracuje pouze s testovacími tabulkami. Přejímka nevytváří skutečné platby ani zprávy.

Lokální důkazy jsou v `C:\Users\Marek\AppData\Local\EvidencePavelB1\finance-b1-20261022`: `environment.json`, `isolation-check.json`, `migrations.json`, `http-acceptance.json`, `phpunit.xml` a `phpunit-output.txt`. Runtime soubory, syntetické session, `config.php` a knihovny nejsou součástí commitu. Procesy se zastavují pouze skriptem ověřujícím vlastníka konkrétního sandboxu; data ani důkazy se nemažou.

## Meze balíku a další návaznost

Zůstávají plánované: auditovaný opravný postup uzavřeného případu (R31/R34), skutečné výplaty a jejich doklady, společný peněžní model a export, sklady, migrace historie a provozní přejímka. B1 neposkytuje crash-safe dvoufázovou transakci mezi souborovým systémem a DB při pádu celého OS; chrání běžné chybové průchody requestu a SQL. Uzavřený souhrn odměny není přepočítáván při změně tréninku; širší uzavírání sportovních podkladů patří do R26/R34.

Následujícím realizačním balíkem podle plánu jsou společné osoby, členství, rodinná obsluha a oprávnění R07–R12. Nasazení B1, zapnutí služeb a převod reálných dat zůstávají samostatnými kroky; v této práci nebyly provedeny.
