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

Lokální důkazy jsou oddělené od následných produkčních kontrol uvedených níže.

## Předběžné CI a oprava srovnávacího prostředí

Hlava `efdd7a2a`: [CI 42360862108](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42360862108)
prošla PHPUnit **2337 testů / 43257 assertions**, integračními smoke na MariaDB
10.3 i 11.4 a samostatnou linuxovou kontrolou privátního úložiště. PHPStan,
styl, dokumentace a CodeQL také PASS. Dva profily čtení odmítly porovnání,
protože připnuté historické aplikace měly 160 položek migračního ledgeru / 347 tabulek a aktuální 161 / 349.
Workflow nyní přidává tutéž novou migraci dokladů do všech tří izolovaných
srovnávacích kopií; historický aplikační kód zůstává připnutý a kontroly
shody dat/HTML, počtu tabulek/migrací i oprávnění zůstávají povinné.
Nová sada automatizace včetně chráněné aktivace: **12 testů / 233 assertions PASS**.
Vlastní místní web 18086 a syntetická MariaDB 33427 byly po přejímce zastavené.

## Finální integrace

[11 kontrol PASS](ci-summary.json) na hlavě `635c97e2`, včetně opravené srovnatelnosti
schémat na MariaDB 10.3/11.4. [PR #355](https://github.com/KovoPraha/evidenceTreninku/pull/355)
sloučeno 7. 11. v 10:02:26 UTC jako `9460ee76`.
[Čtecí výchozí produkční kontrola](production-before.json), běh 42361514869:
minutový plán aktivní a aktuální, 18 dřívějších zpráv, žádné čekající/chybové zprávy.

## Nasazení, aktivace a přirozený worker — PASS

- [Deploy 42362258878](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42362258878)
  SUCCESS, přesný merge `9460ee76`; [souhrn](deploy-summary.json).
  Opakovaně 2337 testů / 43257 assertions, schéma current / pending [],
  zachování dosavadních VS a HTTP 200. Import kroužků a úplné lidské UAT false.
- Před migrováním ověřena záloha 387 tabulek / 2 triggerů, 6 311 972 bajtů;
  její název a SHA-256 v souhrnu. Obnova této konkrétní produkční generace NOT RUN;
  izolovaný integrační test záloh a obnovy nového schématu prošel v CI.
- [Status po nasazení](production-deployed.json), běh 42362619915, potvrdil
  nový worker a dosud vypnuté pověření. Schválený vystavovatel je verze 1,
  Tělovýchovná jednota KOVO, z.s., IČ 00541753.
- [Aktivace 42362688999](production-enabled.json) přes chráněné `shop_enable`
  SUCCESS: verze pověření 1, zapnuto pro nové objednávky. Původní schválení
  a finanční oprávnění ověřila služba, nebyly vytvořené session ani role.
- [Nezávislý status 42362771928](production-after.json) v 10:13:31 UTC:
  zapnuto, poslední přirozený hostingový tick **10:13:03 UTC / 11:13:03 CET**,
  `ok=true`, interval 60 s. Doklady 0 / zprávy 0 / chyby 0 / attention 0,
  fronta prázdná. Dřívějších 18 pracovních zpráv beze změny. Nových registrovaných
  objednávek zatím 0, žádné historické hromadné vystavení nebo rozeslání.
- [Anonymní HTTP kontrola](http-after.json): úvod, e-shop i Závodní tým 297
  HTTP 200; fakturační administrace bez přihlášení přesměruje na login (302).

**Meze:** celý finanční tok byl vykonán na syntetických místních datech,
se skutečnými PDF a zachycenými přílohami, nikoli s reálným bankovním převodem.
Produkční e-mail s novým PDF nebyl doručením do externí schránky potvrzen.
Computer Use ukončilo produkční ovládání, protože neumělo spolehlivě určit URL
Chrome ve Windows; produkční vizuální UAT je NOT RUN. Aktivace a status jsou
prokázané chráněným workflow. Částečné účetní opravy nadále řídí hospodář;
nový e-mail potvrzuje každou zaúčtovanou dílčí výplatu, ne pouhé rozhodnutí.
Měsíční členství zůstává samostatným tokem; žádné členství ani nový závazek
nebyly kvůli této produkční kontrole sjednány.
