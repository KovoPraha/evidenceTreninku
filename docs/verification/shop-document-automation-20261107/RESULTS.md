# Automatické doklady a zprávy objednávek — 7. 11. 2026

Rozsah a oprávnění: [plán](../../SHOP_DOCUMENT_AUTOMATION_2026-11-07.md),
[P07](../../WORK_PLAN_CURRENT.md). Vlastník zadal implementaci, ověření,
sloučení a nasazení; aktuální vydání eviduje [CURRENT_STATE](../../CURRENT_STATE.md).

## Ověřený lokální tok

Vlastní syntetická MariaDB 10.4.32, port 33427, databáze `evidence_b1`;
PHP 8.2.12, web pouze `127.0.0.1:18086`, vnější mail a platební integrace vypnuté.
Konfigurace nepochází z produkce. Zprávy přebírá testovací sender bez síťového
transportu; ukládá obsah a skutečné PDF přílohy. Produkční doručení do schránky
se tím nepotvrzuje.

- **PASS UI:** přihlášený hospodář uložil syntetického vystavovatele a zapnul
  verzi pověření 1. [Nastavení](settings-local.png).
- **PASS UI:** rodič vybral propojeného Sportovce A LOCALHOST a jednorázový
  kroužek 249 Kč, doplnil fakturační adresu a vytvořil
  `KP261107BE5C4A205A`, VS `2610000002`. [Košík](checkout-billing.png).
- **PASS služby + fronta:** [vytvoření](placed.json) zařadilo a zachytilo jeden
  e-mail; žádný účetní doklad ani skutečná platba nevznikly.
- **PASS služby + fronta + UI:** [potvrzení syntetické úhrady](paid.json)
  aktivovalo přihlášku, vystavilo `FA-2026-000001` na 249 Kč a zachytilo
  potvrzení úhrady i samostatný e-mail s PDF. Rodič vidí
  [odkaz na doklad](paid-document.png) a skutečně jej stáhl přes autorizovaný
  detail objednávky. SHA-256 staženého PDF je shodné s přílohou a evidencí:
  `beb8f77c43b92e341dbf3e0cc1a71923da649666207e2c49c0516220ed6acdff`.
- **PASS služby + fronta:** [storno](cancelled.json) ukončilo přihlášku,
  převedlo platbu do `refund_required`, vystavilo `OPR-2026-000001` na −249 Kč
  a zachytilo storno i opravné PDF. Původní PDF se nezměnilo. Storno samo
  neoznámilo vrácení peněz.
- **PASS služby + fronta + UI:** [potvrzení syntetické vratky](refunded.json)
  změnilo stav na `refunded` a zachytilo právě jeden e-mail. Rodič vidí
  [vrácenou platbu a oba doklady](refunded-documents.png).
- **PASS idempotence:** po každé fázi druhý běh workeru vydal 0 dokladů
  a odeslal 0 zpráv. Celkem šest událostí, každá jeden pokus, dva doklady.
- **PASS hranice rozsahu:** starší objednávka z původního auditu nebyla
  automaticky přefakturována ani znovu obeslána; počet měsíčních členství zůstal 0.

Platební/storno/vratkové přechody provedl izolovaný
[CLI přejímkový nástroj](capture-local.php) přes stejné kanonické služby jako
administrace. Zákaznické vytvoření a stažení proběhlo v prohlížeči.
Nešlo o skutečný bankovní převod ani externí e-mail.

## Artefakty a automatické testy

- [Faktura PDF](doklad-1.pdf), [opravný doklad PDF](doklad-2.pdf),
  [vizuální kontrola faktury](invoice-preview.png): čitelné údaje, účastník,
  období, cena, číslování a účet. Soubor není výzvou ke skutečné platbě.
- První cílený běh `ShopDocumentAutomationTest`: **8 testů / 158 assertions PASS**.
  Pokrývá celý tok, opakování, storno před workerem, povinnou adresu,
  storno bez platby, selhání PDF a zachování čísla, hosta, cizí doklad,
  odmítnutí/nejasný transport, odnětí finančního oprávnění a MIME hlavičky.
- Regresní výběr checkout/parent/accounting/transactional/templates:
  **71 testů / 1115 assertions PASS**.
- Následný výběr automatizace/šablony/měsíční členství:
  **31 testů / 302 assertions PASS**.
- Finální výběr automatizace/šablony/měsíční členství/účetní opravy:
  **51 testů / 496 assertions PASS** ([JUnit](focused-junit.xml)). Zahrnuje
  také vypnuté pověření bez zpětného rozesílání, deduplikaci částečné výplaty
  a odmítnutí porušené přílohy před zavoláním poštovního transportu.

Výsledky finálního CI, sloučení, nasazení a aktivace budou doplněny po
dokončení. Tyto lokální důkazy je nenahrazují.
