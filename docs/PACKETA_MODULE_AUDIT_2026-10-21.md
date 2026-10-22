# Zásilkovna: audit funkčnosti a plán dokončení

Aktualizace: 22. 10. 2026, Europe/Prague. Původní analýza nasazeného modulu byla
následným rozhodnutím vlastníka rozšířena o lokální přípravu jednoduchého
ručního provozu. Po autorizaci vlastníka byl modul 22. 10. 2026 nasazen
s vypnutou dopravou a simulací. Skutečné zásilky/e-maily ani fakturace
nejsou součástí této etapy; aktuální produkční důkazy jsou níže.

Pracovní větev: `codex/packeta-module-audit-20261021`, worktree
`C:\xampp\htdocs\evidencePavel-order-status-system-mail-20261021`.
Nasazený aplikační základ: `574328421fff748b48f7d744a3ce454b9b50dd1e`.

## Postup auditu

- [x] Načíst handoff, produkční report a současné zdroje; oddělit původní WIP.
- [x] Ověřit aktuální požadavky widgetu/feedu, klientského účtu, podání a API
  proti oficiální dokumentaci; nezaměnit zákaznický cílový box a místo podání.
- [x] Prověřit checkout, nabídku produktů, validaci, platby, přípravu/podání,
  tracking, storno, e-mailový transport, přístupová práva a výpadkové scénáře.
- [x] Zapsat konkrétní mezery a důkazy ze souborů/řádků, pořadí dokončení,
  provozní úkoly „kdo/kde/co“, rozhodnutí vlastníka a akceptační bránu.
- [x] Aktualizovat handoff odkazem a předat srozumitelný závěr vlastníkovi.

## Výchozí zjištění

Nasazené řešení dnes vybírá cílový český Z-BOX v checkoutu a následně ručně
eviduje štítek/podací údaje vytvořené mimo KIS a potvrzení podání do Z-BOXu.
Není zatím automatickým konektorem zakládání a sledování zásilek.
Při poslední přímé produkční kontrole při vydání byla cena 0, veřejný klíč
prázdný a doprava vypnutá; stav účtu Zásilkovny a oprávnění odesílatele nejsou
tímto reportem ověřeny. Lokální simulace zůstává vypnutá.

## Závěr a doporučení

### Autorizace vydání 22. 10. 2026

Vlastník výslovně schválil commit, push a nasazení připravených úprav.
Dopravu i simulaci ponechat vypnutou, neodesílat skutečné e-maily/zásilky.
[Průběh vydání a produkční důkazy](PACKETA_RELEASE_2026-10-22.md) mají
přednost před staršími zákazy nasazení níže. Fakturace nadále odložená.

**Nasazeno:** [PR #162](https://github.com/KovoPraha/evidenceTreninku/pull/162),
SHA `b6b4251548fc6a99a4c654dc00a70589cc8d2386`,
[deploy 39776493246](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39776493246).
Záloha 246 tabulek / 2 triggerů, 112 aktuálních migrací, původní VS
nezměněny, HTTP 200. Po nasazení přes UI: **cena 150 Kč, doprava vypnutá,
simulace vypnutá, klíč prázdný**. Checkbox fyzického produktu a samostatné
PPL zobrazeny; fronta beze změny **3 pending / 0 pokusů / 0 sent** se shodným
otiskem tabulky. Žádné nové objednávky, zásilky, e-maily ani spuštění workeru.
Lokální veřejný klíč se nepřenášel. Podrobné důkazy a limity v release reportu.

### Propojení veřejného klíče a skutečná mapa — dokončeno 22. 10. 2026

Vlastník autorizoval převzetí veřejného klíče do lokálního KIS a ověření
skutečné mapy českých Z-BOXů. Bez nasazení, odesílání a vytváření zásilek.
Po obnovení přihlášení vlastníkem byl veřejný klíč převzat z položky
„Klíč API“ v klientské podpoře Packety a uložen přes lokální administraci.
API heslo nebylo převzato. Hodnota veřejného klíče není v dokumentaci,
skriptech ani důkazových snímcích; je pouze v lokální databázi.

- [x] Ověřen lokální správce a nastavení přes skutečné UI; DB
  `127.0.0.1:3310/evidence_status_mail_local`, doprava vypnuta, cena 150 Kč,
  veřejný klíč prázdný, `SHOP_PACKETA_LOCAL_DEMO=false`.
- [x] Připraven formulář `booking/rychly_nakup.php?product_id=5&variant_id=5`
  syntetického zboží bez vyplnění kontaktů a bez potvrzení objednávky.
  Výchozí počet objednávek 2, `club_event_notifications` 5 pending / 0 pokusů,
  `auth_mail_outbox` i `roster_message_recipients` prázdné, `email_log` 0.
- [x] Veřejný klíč délky 16 uložen přes UI, lokální audit změny #3.
- [x] Doprava dočasně povolena pouze v izolované lokální DB; skutečný
  widget v6 s `vendors: [{country:'cz',group:'zbox'}]` otevřen v checkoutu
  produktu/varianty 5/5. Simulace po celou dobu false.
- [x] Skutečná mapa i české Z-BOXy načteny v in-app prohlížeči. Vyhledán
  **Z-BOX Praha 10, Strašnice, Nad Kapličkou 3324/15 (TJ Kovo Praha),
  ID 22312**. Tlačítko „Vybrat box“ uzavřelo mapu a callback vyplnil box
  do checkoutu. Součet 125 Kč zboží + 150 Kč doprava = **275 Kč**.
- [x] Skutečná serverová funkce `shopPacketaResolveBox('22312', null, key)`
  prošla proti validačnímu API bez mocku: platný CZ Z-BOX, adresa
  **Nad Kapličkou 3324/15, 100 00 Praha**. API vrací jméno pouze „Z-BOX“;
  úplný název výše pochází z mapy. Běžná pobočka vratek **39209** i
  syntetické ID **999000001** byly stejnou funkcí správně odmítnuty.
- [x] Po zkoušce doprava přes UI znovu vypnuta, audit změny #4. Konečný stav:
  **enabled=0, price_minor=15000, délka veřejného klíče=16, simulace=false**.
  Klíč zachován pro další lokální práci. Při výběru nebyly vyplňovány
  kontakty ani stisknuto objednání; nic nebylo placeno, podáno či odesláno.
- [x] DB po zkoušce shodná s výchozím stavem: **2 objednávky, 5 pending /
  0 pokusů**, auth a roster fronty prázdné, email_log 0. Worker neběžel.
  Žádný commit/push/deploy, změna produkce ani fakturace.

Důkazy v `var/local-status-mail-review/`:
`packeta-live-map-20261022.png` (skutečná mapa a detail),
`packeta-live-selected-20261022.png` (callback do formuláře),
`verify-packeta-live.php` (lokální CLI kontrola; HTTP přístup odmítnut,
čte klíč pouze z izolované DB, neobsahuje jeho hodnotu).

**Pozorování výkonu:** první načtení mapových dat trvalo několik minut,
pak vyhledání i výběr fungovaly. Paralelní kontrola starého celého v5 feedu
narazila na timeout 12 s po HTTP 200 a 360 047 přijatých bytech (cURL 28).
Aktuální checkout tento feed nepoužívá; jeho skutečná bodová validace výše
prošla. Timeout feedu není vydáván za chybu klíče ani za ověřenou připravenost
produkční sítě. Dočasně otevřený Chrome widget při kontrole zůstával prázdný;
úspěšný mapový důkaz pochází z in-app prohlížeče.

**Zbývá před ostrým provozem:** obchodní připravenost účtu a ruční štítek /
fyzické podání a doručení po samostatné autorizaci. Veřejný klíč ani povolení
dopravy se tímto krokem do produkce nepřenášely. Starší výsledky s prázdným
klíčem a neověřenou mapou níže jsou historie před touto kontrolou.

### Živé nastavení klientského účtu Packeta — 21. 10. 2026

Vlastník následně zpřístupnil přihlášený účet „Kovo Praha“ a autorizoval
jeho běžné nastavení pro IČO 00541753, Nad Kapličkou 15, Praha 10,
kontakt Marek Mixa, 724 116 963. Níže je skutečný stav v Packetě, nikoli
další nasazení KIS. Starší údaj „účet ještě neexistuje“ je tím překonaný.

- [x] Kontakt #679131 upraven z „Kovo Praha“ na **Marek Mixa**; správný
  telefon +420724116963 a existující e-mail `mixa@kovopraha.cz` zachovány.
- [x] Založena firemní/fakturační adresa #718887: **Tělovýchovná jednota
  KOVO, z.s.**, IČO **00541753**, **Nad Kapličkou 3324/15, 100 00 Praha 10**,
  Česká republika. Úplná adresa ověřena proti webu KOVO, právní název proti
  jeho e-shopu; nebylo tvrzeno živé ověření v ARES.
- [x] Marek přiřazen jako kontakt pro fakturační oddělení, reklamace a vratky.
  Neudělováno oprávnění snižovat dobírku; DIČ ani bankovní účet neodhadovány.
- [x] Po uživatelově výslovném „potvrzuji“ (souhlas s VOP pro vytvoření
  odesílatele) uložen odesílatel #577459: název **Kovo Praha**, interní
  označení **kovopraha**, typ **Společnost**, vazba na adresu #718887.
  Platby kartou při výdeji vypnuté; samoobslužný vratkový portál vypnutý.
  Ruční předem uhrazené zásilky tím nezavádějí dobírku.
- [x] V „Nastavení povolených poboček“ uloženo pouze **Česká republika**,
  skupina **Z-Boxy povoleno**, běžné **Pickup points zakázáno**, externí
  výdejní místa zakázána; dalších 37 zemí zakázáno. UI upozorňuje, že
  změny se mohou projevit až za několik hodin. Individuální bod ve formuláři
  byl uložen s původní volbou „dle nastavení země“, nevznikla výjimka pro cizí zemi.
- [x] V podpoře potvrzena přítomnost položek API klíče a API hesla;
  hodnoty nebyly vypsány, kopírovány do dokumentace ani uloženy do KIS.
- [x] Prohlédnut prázdný formulář „Podat zásilku“ (odesílatel, příjemce,
  cílové místo, hmotnost kg, rozměry **mm**, hodnota, CZK, dobírka). Žádné
  údaje zásilky nevyplněny, žádné uložení/podání neprovedeno. Seznam podaných
  zásilek zobrazuje „Žádné položky nebyly nalezeny“.
- [x] Ceník v účtu dostupný s filtrem CZ → CZ a datem; nebyla přijata
  placená doplňková služba ani tvrzena konkrétní konečná přepravní cena.
- [x] **Vratkové místo:** vlastník vybral **Praha 10, Strašnice,
  Počernická 3492/1a (ID 39209)**. Uloženo u adresy #718887; Packeta
  potvrdila úspěšné uložení a po opětovném otevření formuláře zůstalo
  vybrané ID 39209. Tím nahrazeno automaticky přiřazené depo Olomouc #9936.
  Standardní režim přijímání vratek zachován. Nejde o vytvoření zásilky.
- [ ] Před prvním použitím potvrdit obchodní připravenost účtu; přístup
  do formuláře a existence API údajů samy neprokazují úspěšné podání.
- [x] Veřejný klíč propojen s lokálním KIS a skutečná mapa ověřena
  22. 10. 2026 dle výsledku výše. Produkční dopravu, skutečné zásilky a poštu
  bez dalšího souhlasu neaktivovat.

Aktuální správa účtu:
[Kontakty](https://client.packeta.com/cs/contacts),
[Firemní adresa](https://client.packeta.com/cs/invoice-addresses/edit/718887),
[Odesílatel](https://client.packeta.com/cs/senders/edit/577459),
[Povolené pobočky](https://client.packeta.com/cs/user-branch-settings/edit),
[API údaje](https://client.packeta.com/cs/support).
Podklady názvu/adresy:
[web KOVO](https://www.kovopraha.cz/),
[identifikace v e-shopu](https://shop.kovopraha.cz/obchodni-podminky/).
Lokální důkazy: `var/local-status-mail-review/packeta-account-contact.png`,
`packeta-account-company-address.png`, `packeta-account-sender.png`,
`packeta-account-cz-zbox.png`, `packeta-account-return-39209.png`.
Žádná hodnota API hesla se nefotografovala.

### Schválené zjednodušení od vlastníka — 21. 10. 2026

- Ruční provoz, české cílové Z-BOXy, podání KOVO pouze přes Z-BOX.
- Odesílatel, expedice i vratky: Kovo Praha. Účet je již nastaven dle živého
  ověření výše; veřejný klíč a skutečná mapa ověřeny zatím pouze lokálně.
- Všechny fyzické prodávané produkty lze zaslat; u produktu checkbox
  „Lze zaslat Zásilkovnou“. Kroužky se nikdy neposílají. Účasti na akcích,
  lekce a jiné služby nejsou balíkem a zůstávají mimo fyzickou přepravu.
- Jedna administrátorem měnitelná cena za objednávku, výchozí 150 Kč.
- Ruční zadání zásilky v Klientu, tisk jeho štítku, přepis tracking/podacího
  kódu do KIS a potvrzení fyzického podání. Bez API pro zakládání, PDF
  úložiště, webhooků a automatického sledování.
- Následná práce lokálně; bez dalšího nasazení, aktivace či skutečné pošty.

Údaje se vyplní ručně v Klientu, ale firemní přepravní štítek musí být
vygenerovaný a vytištěný. Ručně popsaný balík kódem/jménem není náhradou
e-shopového štítku. [Oficiální FAQ pro e-shopy](https://www.zasilkovna.cz/zbox-podani).

### Lokální realizační checklist tohoto zjednodušení

- [x] Samostatná způsobilost produktu pro Packetu a checkbox; kroužky blokovat
  serverově, zachovat dosavadní nastavení PPL.
- [x] Výchozí cena 150 Kč, editace v existující administraci, nabídka vypnutá.
- [x] Opravit konfiguraci mapy a doplnit přehled údajů pro ruční expedici.
- [x] Ověřit katalog a nákup účet/guest, cenu a zákaz nezpůsobilého košíku.
- [x] Zapsat výsledky a zbývající podmínky aktivace do handoffů.

### Výsledek lokální realizace

1. **Produkt:** samostatné `shop_products.packeta_shipping_enabled`, checkbox
   v novém produktu a hlavních údajích; nezávislý na původní volbě PPL.
   Migrace `20261021200000_shop_packeta_manual_products` povolí dosavadní
   fyzické zboží (`goods` + `product`), kroužky a služby nechá nepovolené.
   Nový ručně založený fyzický produkt má checkbox ve formuláři zaškrtnutý.
   Backend nepovolí kroužek ani při podvrženém příznaku; odškrtnutí blokuje
   zaslání v account i guest checkoutu. Změny produktu mají stávající audit.
2. **Dopravné:** jedna upravitelná cena v `eshop_packeta_admin.php`. Migrace
   inicializuje 150 Kč jen u nenastavené dopravy; nepřepisuje již zvolenou
   cenu. Lokálně přes skutečný formulář změněno z historických testovacích
   79 Kč na 150 Kč a nabídka vypnuta. Staré objednávky si ponechávají svou
   původní cenu; cena zobrazená před odesláním musí souhlasit se serverem.
3. **Mapa/validace:** opraven `group:zbox`; výběr serverově ověřuje aktuální
   validační endpoint se stejným CZ/Z-BOX rozsahem, nikoli jen starý feed.
   Odmítá také HTTP 200 s `isValid=false`, cizí zemi, nebox a neúplnou odpověď.
   Ostré spojení/mapa se skutečným klíčem nejsou ověřené, účet chybí.
4. **Ruční expedice:** objednávka zobrazuje cíl včetně ID, telefon, příjemce,
   e-mail, dopravné, tracking a odesílatele Kovo Praha; odkaz do Klienta a
   návod ručně vyplnit zásilku a vytisknout jeho štítek.
5. **Oprava/storno:** samostatná auditovaná oprava údajů štítku před podáním,
   potvrzení kontroly v Klientu a případného zrušení původní zásilky.
   Zadání stejného trackingu u jiné objednávky je blokováno; při MySQL jsou
   zápisy štítků serializovány zámkem konfiguračního řádku. Storno objednávky
   se štítkem vyžaduje zvláštní potvrzení zrušení v Klientu a nepodání balíku;
   tato podmínka se kontroluje i serverově. KIS stále nevolá rušící API.
6. **Pošta:** nevzniklo nové odesílání, nebyl spuštěn worker. Zůstává dnešní
   zpráva o podání a verzovaný editor. Ostrý CRON/transport/backlog se řeší
   před samostatnou aktivací pošty.

**Ověření:** celá PHPUnit sada **1 183 testů / 26 590 assertions PASS**;
MariaDB backup + restore smoke **190 tabulek PASS**, kontrakt
`2026-10-21.5` zahrnuje nový příznak. Historické varování neměnné migrace
`20260922190000_member_fee_standing_orders.php:13` o `columns` se při smoke
opakuje stejně jako při předchozím testu; není novou chybou této etapy.
Lokální katalog migrací **111, aktuální, pending []**.

Nové automatické scénáře: account i guest 125 Kč zboží + 150 Kč doprava =
275 Kč, nezávislost na PPL, odmítnutí odškrtnutého produktu, zachování ceny
staré objednávky, odmítnutí zastaralé očekávané ceny po změně na 180 Kč,
opakovaná migrace bez přepsání administrátorské ceny, blokace duplicitního
štítku, oprava kódu, storno bez/potvrzené v Klientu a jediné oznámení podání.
Jde o syntetické integrační testy, nikoli skutečnou platbu nebo přepravu.

Browser localhost: cena 150 Kč uložena, přepínač nabídky vypnutý; produkt #5
odškrtnut, uložen, znovu zaškrtnut a uložen; expediční přehled starší
syntetické objednávky viditelný. Jde o nové ověření administrace, ne nový
browserový průchod skutečnou mapou. Doklady:
`var/local-status-mail-review/packeta-price-150.png`,
`packeta-product-checkbox.png`, `packeta-manual-phpunit.xml`.
Konečné lokální nastavení: `enabled=0`, `price_minor=15000`, délka klíče 0,
`SHOP_PACKETA_LOCAL_DEMO=false`. Fronta zůstala **5 pending / 0 pokusů**.

**Co ještě před prvním ostrým použitím:** založit/schválit účet pro Kovo Praha,
nastavit kontakty a odesílatele, dodat veřejný klíč, ověřit skutečnou mapu
a adresátní kontakty, ručně zkontrolovat váhu/rozměry/hodnotu balení proti
aktuálním podmínkám a vytisknout platný štítek. Automatický výpočet balení,
založení zásilky, tisk přes KIS a přepravní tracking jsou mimo jednoduchý
ruční rozsah. Při plném boxu obsluha zkusí jiný podací Z-BOX; KIS se do té
doby nedokončuje. Nepřevzaté zásilky/vratky vyřizuje Kovo Praha v Klientu;
po fyzickém vrácení se sklad/peníze řeší samostatně, žádné automatické
naskladnění nebo refundace. Kontaktní a testovací schránka nebyly určeny.

Starší podrobný audit a návrhy níže jsou podklad; API etapa D není součástí
schváleného jednoduchého řešení. Zbývá registrace účtu a pozdější řízené
ověření se skutečným klíčem; podpis podmínek a ostrý test nejsou nyní povoleny.

Samotný klíč a zapnutí nabídky nestačí. Doporučuji nejprve dokončit bezpečně
obsluhovatelný ruční provoz (etapy A–C níže), poté samostatně rozhodnout
o automatickém API propojení. Dosavadní lokální simulace prokázala interní
tok objednávky, nikoli ostrou mapu, oprávnění účtu, štítek ani doručení.

**Cílový Z-BOX zákazníka a místo podání KOVO jsou nezávislé volby.**
Packeta umožňuje podání přes Z-BOX pro různé cíle. Náš tok však cílový
`packeta_zbox` spojuje s povinným podáním přes Z-BOX. Pobočka nebo svoz
nemají odpovídající dokončení v KIS.
[Oficiální postup](https://docs.packeta.com/cs/guides/box-consignment).

Audit pracuje se zdroji po dokumentačním PR #158
(`06c3b5a611b91f01001489671b7c6f27bade595d`). Původní checkout a cizí WIP jsou
zachovány. Produkční účet Packety ani CRON nebyly v této analýze ověřovány;
z počtu zpráv ve frontě nelze odvozovat jejich připravenost.

## 1. Co existuje a jak to funguje

| Oblast | Implementace a důkaz | Význam / omezení |
| --- | --- | --- |
| Konfigurace | `eshop_packeta_admin.php:7`, `includes/shop_packeta.php:9`, `:88` | Cena za objednávku, veřejný 16znakový klíč, přepínač nabídky; potvrzení/důvod a audit. Aktivace kontroluje formát, ne spojení ani oprávnění účtu. Vyžaduje kladnou cenu. |
| Katalog | `includes/shop_admin_templates.php:89`, `includes/shop_manual_catalog.php:295`, `includes/shop_ppl.php:37` | Jen fyzické zboží s „Osobní odběr i zaslání“. Všechny položky musí být způsobilé. Hmotnost/rozměry balíku nejsou podmínkou. |
| Checkout | `booking/eshop.php:109`, `booking/rychly_nakup.php:52`, `includes/shop_ppl_widget.php:64` | Účet i nákup bez registrace; CZ Z-BOX, sdílený formulář s PPL. Akce, lekce a smíšené košíky boxovou dopravu nemají. |
| Ověření cíle | `includes/shop_packeta.php:25`, `:48`, `:63` | Server bere adresu z feedu; kontroluje ID, CZ, zbox, viditelnost, aktivní stav. Cache 4 hodiny, bez aktuálního validačního endpointu widgetu. |
| Cena a data | `includes/shop_checkout.php:358`, `:386`; `includes/shop_guest_checkout.php:127`, `:151` | Server přičítá dopravu, odmítá změněnou očekávanou cenu a ukládá box/telefon/cenu jako snapshot. Sleva košíku je před přičtením dopravy; platba obsahuje dopravu. |
| Platba a štítek | `includes/shop_checkout.php:1084`, `eshop_orders_admin.php:91` | Pouze připravená objednávka s potvrzenou úhradou v objednávce i `payments`. Štítek vzniká mimo KIS; operátor opíše `Z` + 10 číslic a 9místný podací kód. KIS neověří jejich existenci ani vazbu na zákazníka/objednávku. |
| Podání | `includes/shop_checkout.php:1105`, `eshop_orders_admin.php:92` | Ruční potvrzení vložení a zavření schránky. Transakčně `completed`, datum, smazání podacího kódu, e-mail do fronty. Opakování nevytvoří druhé oznámení. |
| Stavy | `includes/shop_order_customer_status.php:18`, `:53` | `ready` = „Připraveno k odeslání“, `completed` = „Podáno do Z-BOXu“. Dokončení expedice, nikoli důkaz doručení zákazníkovi. |
| Zákazník | `booking/objednavka.php:75`, `:92` | Vidí box a prosté sledovací číslo, chybí tracking odkaz a skutečný stav dopravce. Interní podací kód se nezobrazuje. |
| E-mail | `includes/shop_payment_notification.php:272`, `includes/shop_system_email.php:31` | `shop_packeta_dispatched` až po podání, transakčně, s verzí šablony a ochranou proti duplicitnímu vložení. Obsahuje tracking/cíl, nikoli podací kód. Příprava boxové objednávky neposílá zprávu o osobním odběru. |
| Storno | `includes/shop_checkout.php:896`, `:943` | Před dokončením vrátí sklad, případně vyžádá vratku platby, smaže podací kód. **Nezruší zásilku dopravce.** Po dokončení zde standardní storno/vrácení zboží není. |
| Úložiště | `migrations/20261019130000_shop_packeta_zbox.php:56` | Sloupce objednávky pro jedinou zásilku; chybí evidence štítků, pokusů, více balíků a přepravních událostí. |

Hotovost v KIS znamená předem evidovanou úhradu KOVO, nikoli dobírku dopravce.
Doručení na adresu, zahraničí, dobírka a externí výdejní místa nejsou součástí
stávajícího modulu.

### Co již bylo ověřeno

Viz [produkční report](ORDER_STATUS_EMAIL_RELEASE_2026-10-21.md) a
[plán lokálního ověření](ORDER_STATUS_EMAIL_PLAN_2026-10-21.md).
Browserový lokální test prošel nákupem a simulovaným podáním do syntetického
boxu, změnou skladu a vznikem fronty bez skutečného transportu.
`tests/Integration/ShopCheckoutTest.php:77` pokrývá placenou přípravu, zákaz
podání bez štítku, smazání kódu, opakované podání a editovanou šablonu;
`tests/Unit/ShopPacketaLocalDemoTest.php:11` hlídá explicitní localhost guard.
To nepokrývá níže uvedená ostrá rozhraní. V tomto auditu se aplikační testy
znovu nespouštěly, kód se neměnil.

## 2. Co zařídit mimo aplikaci: kdo, kde, co

| Kdo | Kde | Co zajistit | Doklad hotového kroku |
| --- | --- | --- | --- |
| Marek / odpovědná osoba KOVO | Klientská sekce Packety | Správný firemní účet, schválení účtu, platná služba a aktuální smluvní ceník/podmínky. | Účet může vytvořit zásilku požadované služby; určen vlastník. |
| Správce účtu Packeta | Informace o uživateli → Kontakty, Fakturační adresy, Odesílatelé | Kontakty, firemní údaje, odesílatel a jeho „Označení“, vratková adresa. Oddělit provoz KIS a původního e-shopu proti duplicitním zásilkám. | Správný odesílatel a údaje na vzoru štítku. |
| Správce účtu | Nastavení povolených poboček | Povolit Česko a Z-BOXy; ostatní země/služby jen podle schváleného rozsahu. | Skutečná mapa nabízí správnou službu. |
| Správce účtu + KIS | Klientská podpora → API údaje; KIS → Doručení Zásilkovnou | Veřejný klíč pro ruční režim; **ne API heslo do widgetu**. Pro automatizaci navíc privátní heslo na serveru a přesné označení odesílatele (`eshop`). | Spojení ověřeno, heslo není v HTML, Gitu ani logu. |
| Marek | Cena dopravy KIS + smluvní ceník | Prodejní cena za objednávku, obaly/příplatky, více kusů. Nulovou cenu dopravy nyní nelze aktivovat. | Schválená cena a pravidla; doprava zdarma vyžaduje úpravu. |
| Expedice | Sklad / podací místo | Odpovědná osoba, balení, váha/měření, tiskárna a formát štítku, náhradní postup při plném boxu. | Čitelný štítek a pracovní postup. |
| Správce hostingu | PHP / síť / plánovač | cURL, CA certifikáty, DNS/HTTPS k widgetu a feedu, zapisovatelná soukromá cache; pro API pracovní úlohy a REST spojení. | Diagnostika služeb/chyb, nejen HTTP 200 hlavní stránky. |
| Správce pošty + hostingu | Doména, transport, CRON | From/Reply-To, ověření domény a doručitelnosti, schválená fronta, plánovač, dohled a testovací schránka. | Později řízený test do určené schránky; nyní jen náhled/lokální outbox. |

Postup účtu vychází z
[oficiálního nastavení klientské sekce](https://docs.packeta.com/cs/guides/client-section/account-setup).
Existující účet ze zdrojového e-shopu může být použitelný, ověřeno to není.
Přihlášení do `shop.kovopraha.cz` nedokládá přístup ani nastavení Packety.

### Pokud bude KOVO podávat přes Z-BOX

Firemní balík potřebuje řádně vytištěný přepravní štítek a devítimístný
podací kód; nejde o spotřebitelské podání mobilní aplikací. Kód je v Klientu
„Podané zásilky → Podací kód“, přes API jej vrací `packetInfo` jako
`consignPassword`. Jeden balík do jedné schránky.
[Oficiální návod](https://docs.packeta.com/cs/guides/box-consignment).

Pro tento způsob podání jsou uváděny schránky S 7 × 43 × 60 cm,
M 16 × 43 × 60 cm a L 35 × 43 × 60 cm (výška × šířka × délka) a maximální
udaná hodnota 10 000 Kč. Jde o omezení **podání přes Z-BOX**, nikoli všech
způsobů odeslání do cílového Z-BOXu. Ověřit i aktuální smluvní hmotnostní
a obsahové limity, nepřebírat univerzální kg limit odhadem. První scan
dopravce může přijít až po svozu; ruční potvrzení není potvrzení dopravce.
[Informace Zásilkovny pro e-shopy](https://www.zasilkovna.cz/blog/nove-muzete-podavat-zasilky-do-z-boxu-co-to-prinese-vasemu-e-shopu).

## 3. Mezery a opravy před zapnutím

P0 = blokuje doporučené zapnutí; P1 = potřebné pro spolehlivý provoz.
Jde o návrhy, dosud neimplementované.

| Priorita | Zjištění | Konkrétní dokončení |
| --- | --- | --- |
| P0 | `includes/shop_ppl_widget.php:64` používá `vendors:[{country:'cz',carrierId:'czzbox'}]`. Současný kontrakt interního boxu je `country:'cz',group:'zbox'`; `carrierId` označuje externího dopravce. | Opravit konfiguraci podle dokumentace, ověřit skutečnou mapu pro účet i guest. Nesoulad je potvrzen zdrojově; konkrétní dopad na živou mapu se skutečným klíčem nebyl měřen. |
| P0 | `includes/shop_packeta.php:25–74`: jen až čtyři hodiny starý feed, bez aktuální validace pravidel widgetu/účtu. | POST na `https://widget.packeta.com/v6/pps/api/widget/v1/validate`, stejné parametry/služby jako mapa, kontrola `isValid` i při HTTP 200, chyb 400/401 a výpadku. Uchovat serverový snapshot; znovu ověřit před vytvořením zásilky. |
| P0 | `eshop_orders_admin.php:100`: doručovací blok pouze pro PPL. U Packety chybí expediční přehled cíle a telefonu. | Přidat box ID/název/adresu, příjemce, telefon/e-mail, dopravné a tracking; kopírovatelný podklad pro Klienta a kontrolu proti štítku. Obsluha nesmí potřebovat DB nebo soukromý zákaznický odkaz. |
| P0 | `includes/shop_ppl.php:37`: způsobilost nemá váhu, balení, hodnotu ani součet množství. | Určit výrobky/kombinace, údaje variant nebo konzervativní expediční pravidla; váha včetně obalu, rozměry zabaleného celku, hodnota, maximální počet kusů. Pro ruční MVP známé kombinace a jeden balík. Nelze bez pravidla balení jen násobit rozměry. |
| P0 | `includes/shop_checkout.php:1084`, `:1105`: povinný podací kód a vložení do boxu pro každé doručení Packeta. | Rozhodnout místo podání. Při výhradním Z-BOXu jasná omezení a náhradní postup. Při pobočce/svozu oddělit cíl a podání v datech i akcích, nevyžadovat boxový kód. |
| P0 | Již uložený štítek nelze opravit; kontroluje se formát, nikoli duplicita mezi objednávkami. | Auditovaná oprava/výměna před podáním a unikátnost aktivního trackingu; potvrdit vazbu v Klientu a zrušení nahrazeného štítku. Dnešní událost „Zásilka založena v Zásilkovně“ je tvrzení operátora, ne API důkaz. |
| P0 | Storno KIS nezruší dopravce a smaže podací kód (`includes/shop_checkout.php:943`). | U štítku zvláštní potvrzení „zrušeno v Klientu“ před dokončením storna KIS; po podání samostatný postup vratky/reklamace. U odeslaného zboží nevracet sklad automaticky. API později `cancelPacket` s možností odmítnutí. |
| P0 pro slíbené e-maily | Fronta není doručení a společný worker odešle i jiné čekající zprávy. | Inventura obou front, schválení starých položek, transport/CRON a bezpečně vymezený test. Nezačínat prostým spuštěním celého workeru. |
| P1 | Čtyřhodinová cache je v sezoně řidší než doporučení; obnova drží společný lock, síť může prodloužit checkout transakci. | Pravidelná obnova mimo checkout, sezonně hodinově; stáří/chyby, omezené čekání na lock, řízené retry 409. Neověřitelný cíl odmítnout, osobní odběr zachovat. Cache ani validace nerezervují kapacitu. |
| P1 | `includes/shop_checkout.php:366`: jen obecný telefonní regex. | Normalizovat předvolbu a formát přijímaný Packetou, stejné pravidlo účet/guest/ruční podklad. |
| P1 | Zákazník má jen prosté číslo a obsluha nemá obdobný přehled jako PPL. | Oficiální tracking odkaz do detailu/e-mailu; následně skutečný přepravní stav a čas poslední aktualizace. |
| P1 | Banner „připraven podle konfigurace“ ověřuje jen syntaxi a cURL. | Rozlišit nastaveno / spojení ověřeno / oprávnění účtu / nabízeno; diagnostika bez založení balíku a bez tajemství. |

Zdroj kontraktu a validačního endpointu:
[Widget](https://docs.packeta.com/docs/pudo-delivery/widget).
Aktualizace a atributy feedu:
[Packeta PUDOs](https://docs.packeta.com/docs/pudo-delivery/packeta-pudos).
Čtyřhodinová cache sama o sobě neznamená nefunkčnost; nemá být jediným
dokladem aktuální dostupnosti cíle při zapnutí a expedici.

## 4. E-maily a fronta

Text je již upravitelný v
`eshop_order_statuses_admin.php?email=shop_packeta_dispatched`.
Editor obsahuje náhled/verze, nikoli poštovní účet nebo CRON. Úprava ovlivní
nově zařazené zprávy; existující fronta si drží uložený text. Obsah musí
říkat „podáno“, nikoli „doručeno“.

KIS neposkytuje vlastní výdejní PIN cílového boxu. U konkrétního účtu
a řízeného testu ověřit předání kontaktů i následné notifikace dopravce.
**Interní devítimístný podací kód KOVO se nesmí poslat zákazníkovi jako
kód k vyzvednutí.**

`bin/club-event-notifications.php:18` má výchozí transport `mail`.
`includes/transactional_notification_worker.php:104` zpracovává
`auth_mail_outbox` i `club_event_notifications`, bez filtru jen na Packetu.
Příkaz bez `--status` může skutečně rozesílat i čekající ověřovací zprávy.
Tento audit jej nespouští. Diagnostika `--status` neprokazuje plánovač ani
doručení do schránky.

`includes/club_event_notification.php:160` používá PHP `mail()`; explicitně
nastavuje pouze Content-Type, odesílatel závisí na prostředí. `true` znamená
přijetí transportem, nikoli doručení. Existují opakované pokusy (max. 5)
a obnovení dlouho zpracovávané zprávy; pád mezi odesláním a DB potvrzením
může výjimečně způsobit duplicitní fyzické doručení.

Před aktivací: From/Reply-To, doménové ověření pošty, limity hostingu,
klíče auth fronty, posouzení testovacích/starých položek, soukromý spouštěč
CRONu a dohled na `failed`/staré `pending`. Navázat na postup pro Thinline
v `docs/club-events-k3.md:76`. Připravit filtr konkrétních zpráv/příjemce
nebo jinak bezpečně oddělený test; do dalšího povolení jen localhost
`local-outbox`. Pozdější schválený test musí ověřit schránku i hlavičky.

Poslední živá kontrola při dnešním vydání: 3 pending / 0 pokusů / 0 sent.
To je údaj z reportu, nikoli nové ověření nebo souhlas k rozeslání.

## 5. Pořadí dokončení

### A — Vlastník, účet a expedice

1. Rozsah: CZ cílové Z-BOXy, předem zaplaceno, jeden balík, vybrané výrobky;
   nezávisle určit podání KOVO (box / pobočka / svoz).
2. Ověřit účet, odesílatele a služby; bezpečně dodat veřejný klíč.
   Privátní heslo potřebujeme až pro API fázi.
3. Určit cenu, balení/limity, pracovníky, vratky a kontaktní/testovací schránku.

### B — Dokončení ručního modulu lokálně

1. Widget, aktuální serverová validace a diagnostika bez vytvoření zásilky.
2. Balíkové limity, telefon a stejné kontroly účet/guest.
3. Expediční přehled a podklad do Klienta, oprava/výměna/unikátnost štítku,
   bezpečné storno a náhradní způsob podání.
4. Oddělení cíle od podání podle rozhodnutí; správné popisky, tracking odkaz.
5. Šablony a bezpečný test fronty, inventura backlogu; pouze lokální transport.

### C — Účet, řízený pilot a aktivace (samostatně povolit)

Nejprve skutečná mapa a validace bez vytvoření zásilky. Potom po konkrétním
schválení testovací balík/štítek a případně jeden skutečný průchod přepravou.
Poslední krok je zveřejnění dopravy a pošta po vyřešení staré fronty.
Schválení předchozího vydání nebylo schválením tvorby zásilek nebo rozeslání.

### D — Plné API propojení: samostatná etapa vývoje

| Část | Co implementovat | Co musí být zajištěno |
| --- | --- | --- |
| Konfigurace | Privátní API heslo jako serverové tajemství, označení odesílatele, služba/balení; rozlišit admina a expedici. | Schválený účet; žádné heslo v klientském widgetu, Gitu ani logu. |
| Zakládání | API klient + `createPacket`: číslo objednávky, příjemce, kontakty, cíl, hodnota, váha, měna, `cod=0` pro uhrazené objednávky. | Pravidlo udané hodnoty, ne slepě částka včetně dopravného; opravitelné chyby účtu/cíle/parametrů. |
| Evidence | Nová migrace `shop_shipments` + události/pokusy: order, provider ID, tracking, snapshot cíle/odesílatele/balení, stav operace, přepravy, chyby/časy. | Aktualizovat migrační a backup/restore kontrakty, oprávnění a retenci. Jeden balík pro MVP, rozšiřitelný model. |
| Odolnost | Vlastní idempotence a per-order lock; „výsledek neznámý“ při timeoutu po requestu; dohledání před novým založením. | Nepředpokládat deduplikaci `createPacket` jen podle čísla objednávky. Síťové volání mimo dlouhou DB transakci. |
| Štítky | Správná Packeta/carrier label metoda, tisk A4/A6 podle vybavení, autorizovaný opakovaný tisk, soukromé úložiště mimo webroot. | Čitelný štítek a historie zrušených/náhradních zásilek. |
| Podací kód | `packetInfo` → `consignPassword` jen pro podání přes Z-BOX, jen expedici, bez logů a zákaznických výstupů. | Retence po podání; dnešní plaintext v řádku a možné kopie v zálohách zahrnout do přístupových pravidel. |
| Sledování | Samostatný přepravní stav, `packetStatus`/`packetTracking`, odkaz a čas poslední synchronizace; přijetí/přeprava/připraveno u dopravce/doručeno/vratka/problém. | Worker a dohled; nepřepisovat automaticky platbu a sklad. Ruční podání není scan dopravce. |
| Push tracking | Volitelný HTTPS webhook: HMAC SHA256 nad timestamp + tečka + raw body, bezpečné porovnání, deduplikace a uložení událostí, zpracování pozdního pořadí. | Registrace u podpory a Signing Key. Event ID není podepsané; podpis ověřit před deduplikací, doplnit pravidla proti replay a dohledání stavů. |
| Storno/vratky | `cancelPacket` a potvrzení výsledku před fyzickým přijetím; vratka/reklamace po přijetí odděleně od peněz/skladu. | Ošetřit odmítnuté storno (`CancelNotAllowedFault`); nevracet sklad bez skutečné vratky. |
| Dohled | Poslední sync, neznámé výsledky, chyby a staré zásilky; oprávněná ruční náprava a audit bez tajemství. | CRON, odpovědná obsluha a postup při výpadku Packety. |

API poskytuje SOAP nebo REST/XML; pro PHP lze navázat REST klientem.
Packeta neuvádí samostatný sandbox. Test na produkčním účtu vytváří skutečná
data; vhodný je samostatný testovací odesílatel a řízené zrušení testů.
[API a testování](https://docs.packeta.com/docs/getting-started/packeta-api),
[API metody](https://docs.packeta.com/docs/api-reference/api-methods),
[podepisovaný push tracking](https://docs.packeta.com/docs/packet-tracking/pushtracking).
Konkrétní label metodu a úplné mapování stavových kódů vybrat podle schválených
služeb. Tento návrh není hotový API konektor. Fakturace do něj nyní nepatří.

## 6. Rozhodnutí potřebná od Marka

1. **Cíle:** jen CZ Z-BOXy pro začátek, nebo i výdejní pobočky?
   Doporučení: začít CZ Z-BOXy.
2. **Podání KOVO:** box / pobočka / svoz; kdo a odkud expeduje a co při plném
   boxu? Přímo ovlivňuje kroky a data modulu.
3. **Automatizace:** nejprve ruční Klient se spolehlivým KIS workflow, nebo
   rovnou API a štítky? Doporučení: nejprve A–C.
4. **Účet:** který Packeta účet a odesílatel patří KOVO, kdo jej spravuje
   a zajistí přístupy/služby?
5. **Cena/limity:** dopravné, případně zdarma, produkty/kombinace, maximální
   balení a hodnota, jeden vs. více balíků.
6. **Komunikace/test:** kontakt podpory a odesílatele, vlastník fronty/vratek,
   testovací schránka a pozdější rozsah povoleného ostrého testu.

Hesla do chatu nepotřebujeme; stačí určit správce a bezpečné serverové předání.

## 7. Akceptační brána

- [ ] Účet a služby ověřené; veřejný klíč není zaměněný za heslo.
- [ ] Skutečná mapa na desktopu/mobilu, účet/guest, zavření a změna cíle.
- [ ] Server odmítne podvržený bod, pobočku místo boxu, cizí zemi, neplatný
  klíč, nedostupný box, překročenou váhu/rozměry/hodnotu a nesprávný košík;
  výpadek nezanechá částečnou objednávku. Test i HTTP 200 + `isValid=false`.
- [ ] Cena dopravy souhlasí v souhrnu/objednávce/platbě; změna ceny vyžádá nový
  souhlas; více kusů splňuje balíkové limity.
- [ ] Expedice má údaje bez DB; blokace nezaplacených/stornovaných, správný
  štítek, bezpečná oprava, opakované kliknutí bez druhé zásilky/oznámení.
- [ ] Čitelný štítek a proveditelné podání; postup při plném boxu.
- [ ] Podací kód nevstupuje do zákaznických výstupů ani auditu; po podání
  je odstraněn z aktivní evidence, zálohy zůstávají chráněné.
- [ ] Po podání správný stav a tracking odkaz, žádné předčasné „doručeno“.
  Pro API fázi duplicitní/opožděné události a timeout s neznámým výsledkem.
- [ ] Storno objednávky nezanechá aktivní zásilku; postup pro vratku a oddělené
  potvrzení vrácení zboží/peněz.
- [ ] Lokální outbox odpovídá šablonám/verzím; backlog posouzen; pozdější
  povolený test prokáže přijetí schránkou, dohled zachytí selhání.
- [ ] Simulace na produkci nedostupná, lokálně standardně vypnutá; aktivace
  nabídky i skutečné pošty má samostatný doložený souhlas.

## 8. Stav analytické práce a návaznost

- [x] Handoff, zdroje, doložené vydání a stávající testy prověřeny.
- [x] Aktuální oficiální widget/feed, účet, podání a API ověřeny.
- [x] Odděleny zdrojové mezery a neověřený ostrý provoz.
- [x] Sepsány úkoly kdo/kde/co, rozhodnutí a akceptační brána.
- [x] Odkaz doplněn do obou handoffů a původního plánu stavů/e-mailů.
- [ ] Rozhodnutí vlastníka a autorizace navazující realizace.
- [ ] Implementace, ostrý přepravní test a aktivace — dosud neprovedeny.

Navazuje na [plán stavů a e-mailů](ORDER_STATUS_EMAIL_PLAN_2026-10-21.md).
Vstupní předání: [HANDOFF_CURRENT](HANDOFF_CURRENT.md) a
[session handoff](plan-eshop-tymova-evidence/SESSION_HANDOFF.md).
