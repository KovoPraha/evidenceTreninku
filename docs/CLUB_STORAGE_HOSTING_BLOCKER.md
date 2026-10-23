# Provozní blok: webové PHP nemá přístup k privátnímu úložišti

Stav ověřený 23. 10. 2026, produkční vydání `0b03b564`. Jde o blok
hostingu, nikoli o pokyn k přesunu příloh či změně účetních dat.

## Potvrzený nález

Přihlášená webová diagnostika `uat_readiness_admin.php` vrátila:

| Kontrola | Výsledek |
|---|---|
| Konfigurace z konstanty | ANO |
| Konfigurace z prostředí / konflikt prostředí s konstantou | NE / NE |
| Mimo webroot aplikace | ANO |
| `open_basedir` aktivní / povoluje nakonfigurovaný root | ANO / NE |
| Adresář viditelný / zapisovatelný z webového PHP | NE / NE |
| Dostupný `realpath` / `stat` / efektivní UID | NE / NE / NE |

Webové omezení tedy nepovoluje nakonfigurovanou cestu. Nedostupné UID/stat
neprokazují nesprávného vlastníka ani neexistenci adresáře. Úspěšný CLI
provisioning není důkaz přístupu webového PHP. V dostupném browser inventáři
nebyl přístup do administrace hostingu; tento krok proto zůstává odložený.

## Přesný požadavek pro správce hostingu

1. V konfiguraci **webového PHP pro `kis.kovopraha.cz`**, nikoli pouze CLI,
   povolit jediný připravený privátní root:
   `dirname(realpath(APP_ROOT)) . '/data/kis-private'`, včetně podadresářů.
   Konkrétní absolutní cestu správce převezme z chráněné konfigurace aplikace
   a ověří proti skutečnému umístění. Nepoužívat zkrácenou cestu ze SSH chrootu.
2. Zachovat dosavadní položky a všechna ostatní omezení `open_basedir`.
   Přidat pouze tento adresář; nevypínat `open_basedir` a nepovolovat celý
   hostingový kořen. Případné načtení konfigurace/FPM provede správce standardním
   postupem poskytovatele.
3. Root musí zůstat mimo aplikaci i její veřejný document root. Nepřesouvat
   přílohy do `var/private`, uploadů či jiného veřejného stromu a neobcházet
   kontrolu `privateStorageRoot()`. Nepoužívat `chmod 777`. Pokud následná
   diagnostika prokáže odlišného vlastníka procesu, správce vyřeší přístup
   cíleně pro tento adresář; nerozšiřuje plošně práva existujících dat.
4. Neměnit env/konstantu na jinou cestu a nepřesouvat stávající obsah bez
   samostatného plánu. Aktuální nález nedokládá konflikt konfiguračních zdrojů.

Historický [hostingový runbook](thinline-deploy-runbook.md#L43) uvádí tento
typ cesty jako vhodný v reálném filesystemu. Aktuální webová diagnostika má
přednost před historickým seznamem povolených adresářů. Nejde o oprávnění
agenta měnit hostingové nastavení bez příslušného přístupu.

## Ověření po změně

1. V novém webovém požadavku otevřít přihlášenou UAT diagnostiku. Vyžadovat
   současně `configured`, `outside_webroot`, `directory_exists`, `writable`
   = true a při zapnutém omezení také `open_basedir_allows_configured_root`
   = true. Zaznamenat čas, vydání a booleany; nezveřejňovat cesty či konfiguraci.
   Celková starší UAT připravenost může zůstat blokovaná jinými položkami.
2. Po obnovení přístupu v rámci technické přejímky provést pod stejným
   **webovým PHP procesem** zápis jednoho náhodně pojmenovaného syntetického
   souboru (bez osobních/finančních dat) do tohoto privátního rootu, režim 0600,
   přečtení a porovnání obsahu/hash, následné odstranění a ověření odstranění.
   Zkouška musí být chráněná přihlášením, oprávněním správce a CSRF; žádný
   veřejný diagnostický endpoint ani změny DB. Tento dokument takový endpoint
   nepřidává a netvrdí, že zkouška již proběhla.
3. Pokud read-only panel projde, ale skutečná zkouška ne, blok neuzavírat.
   Ověřit kvótu, oprávnění a runtime hostingu. Teprve obě úspěšná ověření jsou
   technickým podkladem pro obnovení práce se soukromými přílohami.

## Důležitá mez účetního vystavení

Vystavení účetního dokladu **není zkouška bez vedlejších účinků**:

- `accountingDocumentIssue()` nejprve rezervuje číslo, posune čítač řady,
  uloží stav `rendering` a audit `number_reserved`, a tuto první transakci
  **commitne před tvorbou PDF** ([kód](../includes/accounting_documents.php#L165)).
- Selhání úložiště ve druhé fázi rollbackne transakci vydání, takže nevznikne
  úspěšně commitnutý stav `issued` s PDF. Předchozí rezervace čísla zůstává.
  Chybová obsluha následně ukládá `failed` a audit `render_failed`; sama ještě
  může selhat při další chybě DB nebo úklidu souboru. Proto nelze garantovat
  úplný návrat databáze do původního stavu
  ([uložení a catch](../includes/accounting_documents.php#L182)).
- Po odstranění příčiny se opakuje **tentýž doklad se stejným ID a číslem**.
  Nevracet čítač ručně a nevytvářet náhradní duplikát. Toto chování pokrývá
  test `AccountingDocumentsTest::testStorageFailureKeepsNumberAndRetryIssuesSameDocument`
  ([test](../tests/Integration/AccountingDocumentsTest.php#L33)).
- Příprava exportu má vlastní transakci; při chybě souborů rollbackuje DB
  změny rozpracované dávky a pokouší se uklidit vytvořené soubory
  ([kód](../includes/accounting_export.php#L140),
  [catch](../includes/accounting_export.php#L183)). Ani tuto operaci nepoužívat
  jako základní test dostupnosti úložiště.

Tyto závěry jsou ověřením zdrojového kódu a existujícího testového kontraktu,
nikoli provedením chyby vystavení na produkci. Kvůli tomuto bloku nebyl
vystaven syntetický finanční doklad ani změněna produkční evidence.
