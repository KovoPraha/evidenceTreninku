# Jednorázový nákup kroužku — 7. 11. 2026

Stav: KONTROLA DOKONČENA, dvě funkční návaznosti chybí. Zadání vlastníka: ověřit jinou část aplikace po kontrole
členství, konkrétně jednorázový nákup kroužku od výběru dítěte po doklad,
upozornění, aktivaci, storno a vratku. Návaznost: [P07](../../WORK_PLAN_CURRENT.md).

Výchozí main `89edfdbe`, větev `codex/one-time-course-uat-20261107`, worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-one-time-course-uat-20261107`.
Jiné rozpracované větve a nedokončené produkční UAT členství zůstávají zachované.

## Plán a hranice

1. Připravit vlastní izolované prostředí z verzovaného schématu a syntetických dat.
2. Ověřit objednávku pro propojené dítě, platební údaje, opakování požadavků,
   syntetickou úhradu a vznik právě jednoho zařazení do soupisky.
3. Ověřit dokumenty a obsah/frontu upozornění; externí doručení oddělit od lokálního transportu.
4. Ověřit storno a vratku, zachování účetní historie a ochranu cizích údajů.
5. Zapsat důkazy, případné závady a další kroky; aktualizovat handoff a registr.

Bez produkčních peněz, nového nasazení nebo hromadné rozesílky. Reálná platba
a přístup do testovací schránky nejsou dostupné, jejich přejímka proto není
součástí místního důkazu. Odložený skutečný tok měsíčního členství se tím neuzavírá.

## Výsledek a otevřené návaznosti

Jednorázový kroužek lze koupit pro propojené dítě, úhrada aktivuje účast a
soupisku. Storno účast ukončí a předá vratku hospodáři; potvrzení vratky se
zobrazí rodiči. **Automatické vystavení a odeslání účetního PDF není součástí
tohoto toku.** Účetní modul PDF umí vystavit samostatně, což bylo také ověřeno.

| Kontrola | Výsledek | Důkaz |
| --- | --- | --- |
| Výběr propojeného dítěte a jednorázová objednávka 249 Kč | PASS, jedna objednávka / platba, VS 2610000001, splatnost 14. 11. | [Výchozí stav](pending.json), [obrazovka](order-pending.png) |
| Úhrada, právě jedna účast a vazba na soupisku | PASS v prohlížeči a DB; osoba 2, účast 1, soupiska 1, vazba 8 | [Stav po úhradě](paid.json), [rodič vidí aktivní kurz](course-active.png) |
| E-mail po přijetí platby | PASS pro obsah, frontu a místní transport; 1 zpráva, 1 pokus, opakované zpracování prázdné | [Zachycená zpráva](captured-mail.json), [fronta](mail.json) |
| Storno zaplaceného kroužku | PASS; `refund_required`, účast `cancelled`, pouze odvozená vazba `removed` | [Stav po stornu](cancelled.json) |
| Potvrzení celé vratky 249 Kč | PASS přes UI hospodáře; rodič vidí „Peníze vráceny“, historie zachovaná | [Finální stav](refunded.json) |
| Opakování a souběh, kapacita, cizí dítě, platba před/po ověření identity | PASS v cílených testech a MariaDB smoke | [JUnit](phpunit.xml), [MariaDB](mariadb-smoke.txt) |
| Dvě děti, sleva, částečné platby a přeplatek | PASS; objednávka 539,99 Kč, příjmy 545 Kč, kredit 5,01 Kč správného dítěte | [Účetní souhrn](accounting/verification.json), MariaDB smoke |
| Samostatné vystavení účetního PDF, opravný doklad, vratka 10 Kč, export | PASS; neměnné PDF a revize ZIP/XLSX, kontroly hashů | [PDF](accounting/LOCALHOST-NEPLATIT-faktura.pdf), [vizuální kontrola](accounting/invoice-preview.png), [revize 2](accounting/LOCALHOST-NEPLATIT-rev2.zip) |
| Měsíční členství se jednorázovým nákupem nevytvoří | PASS; 0 před i po celé operaci | Datové snímky výše |
| Produkční nákup, skutečný bankovní import/úhrada a příjem v poštovní schránce | NOT RUN | Izolovaný test nepředstavuje produkční přejímku P05/P07 |

### N1 — chybí automatické účetní PDF pro běžný nákup kroužku

Po vytvoření i po úhradě nové objednávky zůstalo `accounting_documents` prázdné.
Kanonický `shopOrderConfirmPaymentInTransaction()` v
[shop_checkout.php](../../../includes/shop_checkout.php) aktivuje program
a volá `shopPaymentNotificationEnqueue()`, nevystavuje účetní doklad.
Zachycený e-mail potvrzuje úhradu a odkazuje na objednávky, neobsahuje doklad.
Samostatné [účetní služby](../../../includes/accounting_documents.php) umějí
návrh, vydání PDF, opravu i stažení. Jejich úspěšný test neznamená automatické
napojení na košík. Stav je **chybějící automatizace**, nikoli chyba renderování PDF.

Další krok P07: určit pravidlo vydání pro jednorázovou službu, navázat existující
účetní služby a bezpečný přístup zákazníka k dokladu, deduplikované upozornění
a opravu při stornu. Nepřenášet měsíční číselnou řadu nebo účetní klasifikaci
bez výslovného pravidla. Implementace této návaznosti v této kontrolní session neproběhla.

### N2 — běžný košík nemá úplnou zákaznickou komunikaci

Po vytvoření objednávky nevznikla žádná položka `club_event_notifications`.
Platební údaje/QR byly dostupné na stránce, nikoli v novém e-mailu.
Po úhradě vznikl právě jeden `shop_payment_received`; po stornu ani potvrzení
vratky nepřibyla další zpráva. Rodič změnu vidí v účtu.
Závěr se týká **běžného košíku přihlášeného rodiče**; rychlá přihláška má
odlišný tok, jehož zprávy ověřuje `ParentJourneyAcceptanceTest`.

Další krok P07: sjednotit potvrzení objednávky s platebními údaji a oznámení
storna/vratky. Nejprve doplnit očekávané události a deduplikaci, potom ověřit
skutečné doručení. Tato session neposílala žádné externí e-maily.

## Prostředí a provedení

Testovaný commit `89edfdbee5fbddbe05e0134d94f79ba0cb4143d9`, PHP 8.2.12,
MariaDB 10.4.32, 7. 11. 2026 Europe/Prague. Nová lokální databáze `evidence_b1`
na `127.0.0.1:33427`, web `127.0.0.1:18086`, prázdné verzované schéma,
migrace, `products-valid.csv` a `seed-local-demo.php`. Žádný produkční dump.
Soukromá data/session mimo webroot, Fio/SumUp/Stripe/Velocota vypnuté,
webové `mail`, cURL a socketové transporty zakázané. Prohlížečový test používá
fiktivní banku 9999 a popisek NEPLATIT. Účetní smoke používá svou verzovanou
bankovní fixture; všechny uložené doklady jsou označeny LOCALHOST NEPLATIT.

Objednávka `KP261107A6EDB29BF7` vznikla přes UI rodiče pro Sportovce B LOCALHOST.
Platbu, storno a vratku provedl seedovaný demo superadmin v příslušném
pracovním kontextu. Přímý vstup do objednávek z kontextu financí byl odmítnut;
po přepnutí pozice dostupný. Nejde o plné UAT samostatných omezených rolí.
Rodič byl pro následné kontroly oddělen v Chrome, obsluha v IAB; přihlášení
obsluhy v původním společném prohlížeči přepne veřejnou identitu, proto tam
rodičovská objednávka správně nebyla dostupná.

Příprava nejprve narazila na nefunkční novou MariaDB instanci v AppData
(poškozený index systémové `mysql.db`, chyby Aria). Byla zastavena, nepoužita
pro důkazy a zachována pro diagnostiku. Úspěšná instance vznikla samostatně
v `C:/xampp/tmp/course-uat-20261107-db`; běžela pod řízenou terminálovou session.
Původní B1 sandbox ani cizí pracovní kopie se neměnily. První pokus o objednávku
správně odmítl prázdný IBAN testovacího configu; po nastavení verzované fiktivní
banky nákup prošel. Nejde o zjištění z produkční konfigurace.

## Reprodukce technických kontrol

`composer install --no-interaction --prefer-dist` podle lockfile, poté:

```powershell
php vendor/phpunit/phpunit/phpunit --filter 'ParentJourneyAcceptanceTest|ShopCheckoutTest|ClubProgramPaymentLifecycleTest|AccountingDocumentsTest|TransactionalNotificationWorkerTest' --log-junit docs/verification/one-time-course-uat-20261107/phpunit.xml
```

Výsledek **96 testů / 1 963 assertions, bez chyb a přeskočených testů**.
MariaDB acceptance používá vlastní nové schéma se jménem
`evidence_shop_checkout_smoke_test_course20261107`, které po běhu odstraní:

```powershell
$env:EVIDENCE_MARIADB_SMOKE_HOST='127.0.0.1'
$env:EVIDENCE_MARIADB_SMOKE_PORT='33427'
$env:EVIDENCE_SHOP_CHECKOUT_SMOKE_DB='evidence_shop_checkout_smoke_test_course20261107'
$env:EVIDENCE_FAMILY_ACCOUNTING_OUTPUT='docs/verification/one-time-course-uat-20261107/accounting'
php tests/Support/ShopCheckoutMariaDbSmoke.php
```

Spouštět pouze proti vlastní syntetické instanci; smoke dané testovací schéma
na začátku nahrazuje. Obsahuje skutečné souběžné procesy, účetní PDF/XLSX/ZIP
a detekci záměrného poškození vlastních souborů. Vizuální kontrola PDF:
jedna čitelná stránka, česká diakritika, obě děti a období, sleva a celková částka.
Snímky hlavního UI toku pořizuje [omezený lokální CLI pomocník](capture-local.php);
`mail` znamená pouze místní zachycení do JSON, nikdy SMTP nebo skutečnou schránku.

## Předání

Kontrola je dokončena; N1/N2 zůstávají otevřené v P07. Bez změny aplikačního
kódu, bez nasazení, bez nové aktivace automatů. Výsledky navazují na
[hlavní handoff](../../HANDOFF_CURRENT.md) a [jediný registr práce](../../WORK_PLAN_CURRENT.md).
Finální kontrola `tools/check-current-docs.php`: PASS, CURRENT_STATE 80 řádků,
986 místních odkazů, 0 chyb; `git diff --check` PASS. Všechny odkazy tohoto
protokolu existují; [kontrola snímků](verification-checks.txt) PASS.
Vlastní web zastaven, úspěšná MariaDB korektně ukončena; nefunkční první
instance po neúspěšném ukončení zastavena ověřeným PID. [Záznam úklidu](runtime-stopped.txt).
Syntetická data zůstávají místně, žádné služby této kontroly neběží.
Dokumentace a důkazy jsou odeslané v [PR #355](https://github.com/KovoPraha/evidenceTreninku/pull/355).
Při předání je PR otevřené, bez merge a nasazení; CI ověřit přímo u PR.
Dokumentační začlenění nevyžaduje deploy a neodstraňuje N1/N2.
