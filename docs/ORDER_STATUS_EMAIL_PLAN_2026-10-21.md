# Plán dokončení stavů objednávek a e-mailů

Aktualizace: 21. 10. 2026, Europe/Prague.

## Aktuální autorizace vydání 21. 10. 2026

Vlastník následně výslovně schválil commit, push a nasazení na produkci.
Tím nahrazuje dřívější zákaz vzdálených kroků níže; zákaz skutečných e-mailů
při ověřování a odložení fakturace trvají. Lokální simulace Zásilkovny byla
v ignorovaném configu vypnuta (`SHOP_PACKETA_LOCAL_DEMO=false`).

- [x] Vypnout lokální simulaci; config a testovací artefakty nevstupují do Gitu.
- [x] Začlenit aktuální `origin/main` včetně PR #154–155; zachovat oba nové
  datové kontrakty, migrační checksummy a ruční editace ostatních worktrees.
- [x] Ověřit PHPUnit, migrace/zálohu a souběh editoru; push, PR a zelené CI.
- [x] Sloučit chráněným postupem a nasadit přes `deploy-production.yml`,
  `potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`.
- [x] Ověřit SHA, zálohu, migrace a živý přehled stavů, editor/náhled a frontu.
  Bez ukládání produkčních šablon, přepnutí objednávek a běhu poštovního workeru.
- [x] Zapsat odkazy na PR/CI/deploy, produkční důkazy a aktuální bod do handoffů.

Příprava vydání: začleněn `61ed7385` (PR #155) v commitu `11db284d`.
Migrační katalog zachovává nové systémové e-maily i vlastní registrační
souhlasy; kombinovaný zálohovací kontrakt je `2026-10-21.4`. Nová migrace
e-mailů zachovává původní LF bytes/checksum. Po integraci: PHPUnit
**1 178 testů / 26 530 kontrol, OK**, MariaDB souběh editorů a zachování staré
zprávy OK, záloha/obnova **190 tabulek, OK**. Smoke e-mailů přidán i do CI matice
MariaDB 10.3/11.4. Lokální DB před kontrolou neběžela; znovu spuštěna pouze
soukromá instance :3310, bez resetu dat. Známé varování historické migrace
o `columns` nezabránilo úspěšné obnově; historická migrace se neměnila.

Výchozí produkční kontrola přes přihlášený prohlížeč: fronta obsahuje právě
1 pending, 0 processing/failed/sent a pokusy 0. Předmět+tělo uložené zprávy #1
má SHA-256 (oddělovač LF)
`a6cb0ad2a10944e8ec55cd091ad93322a7586b66385e93bb8c8ca6421a82562b`.
Příjemce, obsah a přístupové odkazy se do veřejného reportu nekopírují.
Žádná zpráva nebyla vrácena do fronty ani odeslána.

Vydání sloučeno v [PR #156](https://github.com/KovoPraha/evidenceTreninku/pull/156)
na aplikační SHA `574328421fff748b48f7d744a3ce454b9b50dd1e`.
[CI 39603132938](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39603132938)
(PHPUnit a MariaDB 10.3/11.4) a CodeQL 39603127501 jsou zelené.
[Deploy 39603396933](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39603396933)
úspěšně skončil na shodném SHA s oběma volitelnými příznaky false.
Záloha 236 tabulek / 2 triggerů, migrace 110/110 aktuální, pending=[], HTTP 200.
Živě ověřen přehled stavů, výchozí editor/náhled a fronta; původní zpráva #1
zachovala otisk uvedený výše. Konečná fronta: 3 pending / 0 pokusů / 0 sent.
Dvě zprávy vznikly souběžným testem hotovosti v 14:51:55 a 14:53:58, tedy před
aktivací vydání v 14:56:27; nejde o zprávy z migrace nebo našeho náhledu.
Žádný poštovní worker spuštěn nebyl. Ostrá Zásilkovna zůstává vypnutá: cena 0,
prázdný veřejný klíč a nezaškrtnuté povolení; simulace není aktivní.
Podrobné důkazy a omezení jsou v
[produkčním reportu](ORDER_STATUS_EMAIL_RELEASE_2026-10-21.md).
Předávací dokumentace je na `codex/order-status-release-proof-20261021`, stejný
worktree; tato větev nemění aplikační kód a nevyžaduje další deploy.

## Schválený rozsah a pracovní bod

Vlastník schválil lokální opravu Zásilkovny, zpřehlednění systémových a vlastních
stavů a editaci systémových e-mailů. Výslovně zakázal nasazení; fakturaci
odložil. Není schválen push, merge, změna produkčních dat, odesílání skutečných
e-mailů ani zapnutí workeru/CRONu.

- Výchozí kód: `f813264b16ffd49224bd41a908adc33d9687af88` (`origin/main`).
- Pracovní větev: `codex/order-status-system-mail-20261021`.
- Pracovní složka: `C:\xampp\htdocs\evidencePavel-order-status-system-mail-20261021`.
- Původní checkout obsahuje cizí WIP; nepřepisovat ani nepřidávat do commitů.
- Aktuální pracovní stav: lokálně implementováno a ověřeno; bez nasazení.

## Lokální implementace

### 1. Zásilkovna

- [x] Odstranit zachycení Z-BOXu obecnou větví osobního výdeje.
- [x] Osobní odběr, PPL a Z-BOX musí mít navzájem oddělené akce v UI.
- [x] Z-BOX: příprava → sledovací a podací kód → fyzické podání.
- [x] Zachovat serverové ověření úhrady, způsobu předání a skutečného podání.
- [x] Ověřit nesprávné akce a opakování bez duplicitních událostí/e-mailů.

### 2. Srozumitelné stavy

- [x] Ve správě zobrazit i nesmazatelné systémové stavy a popis jejich účinků.
- [x] Oddělit skutečný stav objednávky, zákaznické upřesnění a stav platby.
- [x] Vlastní štítek nesmí zakrývat skutečný průběh ani potvrzovat úhradu.
- [x] Popsat dokončení výdeje/expedice bez tvrzení, že dopravce již doručil balík.
- [x] Zachovat dosavadní systémové přechody, aktivaci programů a historická data.

Administrativní dokončení kroužků a výdej po položkách smíšeného košíku jsou
navazující změna životního cyklu; nezavádět je skrytě pouhým přejmenováním
stávajícího `processing` na „Vyřízeno“.

### 3. Systémové e-mailové šablony

- [x] Editor stávajících systémových e-mailů objednávek, platby, odběru a dopravců.
- [x] Předmět, text, povolené proměnné a náhled se syntetickými údaji.
- [x] U odběru možnost uvést místo, čas a kontakt; interní poznámku nezveřejňovat.
- [x] Historie verzí s autorem/důvodem, ochrana proti přepsání souběžné úpravy.
- [x] Ve frontě uchovat použitou verzi a hotový obsah zprávy.
- [x] Nové šablony použít pro nové zprávy; již zařazené zprávy nepřepisovat.
- [x] Zachovat unikátní klíče oznámení a jejich transakční zařazení.
- [x] Žádné odesílání e-mailu z editoru nebo při testování webového formuláře.
- [x] Doplnit migrační a zálohovací kontrakt všech nových tabulek/sloupců.

Fakturační zprávy, nové automatické rozesílky a dokladové přílohy patří do
odložené fakturační části. Rozsah šablon se musí shodovat se skutečnými
implementovanými událostmi; nezobrazovat nefunkční přepínače.

### 4. Ověření a předání

- [x] Regresní testy výběru výdejových akcí pro všechny způsoby dopravy.
- [x] Integrační test uložení šablony, historie, náhledu, chybné proměnné,
  neoprávněné změny, souběhu a neměnnosti dříve zařazené zprávy.
- [x] Regresní testy checkoutu, potvrzení úhrady, programů a dopravců.
- [x] PHPUnit, PHP lint, migrační a zálohovací kontrakty, diff check.
- [x] Lokální browser průchod se syntetickými daty; žádná skutečná pošta.
- [x] Doplnit výsledky, omezení a návazný postup do tohoto souboru a handoffu.

## Odložené dokončení a fakturace

Původní celkový plán zachovává tyto navazující kroky:

1. Jednoznačné administrativní dokončení služeb a všech položek smíšené
   objednávky; nezávislé na trvání účasti v kroužku.
2. Ruční vystavení faktury s náhledem, kontrolou údajů a jedinečnou číselnou
   řadou; následně odsouhlasená automatizace podle typu prodeje.
3. Neměnný snapshot vystavitele, odběratele, položek, částek a účetních údajů,
   uložené PDF a bezpečné stažení. Opravy navazujícím dokladem.
4. Rozšíření fronty o přílohy a samostatné sledování vystavení, tvorby PDF
   a předání e-mailu poštovnímu transportu. Opakování používá stejný doklad.
5. Storno po vystavení faktury řeší samostatnou opravu dokladu a vratku;
   dokončené fyzické předání se nepřepisuje podle chyby e-mailu.
6. Produkční aktivace až po samostatném rozhodnutí a řízeném ověření doručení.

Od vlastníka před fakturací potřebujeme určit:

| Rozhodnutí | Potřebné upřesnění |
|---|---|
| Hlavní systém dokladů | Vystavuje KIS, nebo přebírá již vystavené faktury? |
| Vystavitel a účetní režim | Subjekt, IČO/DIČ, účet; účetní potvrdí typy dokladů, DPH a rozhodná data. |
| Číselná řada | Nová řada nebo návaznost; formát a počáteční číslo bez kolize. |
| Spouštěcí události | Pravidla pro zboží, kroužky, ostatní služby a přijaté platby. |
| Odběratel | Možnost firmy/zaměstnavatele odlišného od rodiče a účastníka. |
| Odesílání | Automaticky nebo ručně; odesílací a kontaktní adresa. |
| Historické objednávky | Doporučeno jednotlivé ověření, žádné plošné vystavení/rozeslání. |

## Průběžný záznam

### Navazující autorizace lokálního browser průchodu

Vlastník 21. 10. 2026 požaduje dokončit celý nákup a výdej v lokálním
prohlížeči včetně Zásilkovny a kontroly e-mailové fronty. Stále bez nasazení,
push/merge a skutečných e-mailů. Použít tutéž izolovanou databázi a standardní
UI. Externí výběr Z-BOXu nahradit explicitním localhost simulátorem se
syntetickými boxy, bez klíčů a bez volání dopravce. Skutečnou mapu dopravce,
vytvoření štítku u dopravce a fyzické doručení tím nelze certifikovat.

- [x] Doplnit striktně lokální simulátor výběru Z-BOXu a jeho bezpečnostní test.
- [x] Přes správu katalogu založit a zveřejnit syntetické skladové zboží.
- [x] Přes zákaznický formulář vytvořit nákup s osobním odběrem.
- [x] Přes hospodáře potvrdit syntetickou úhradu, přes objednávky přípravu/výdej.
- [x] Totéž projít pro Z-BOX včetně výběru boxu, štítku a potvrzení podání.
- [x] Ve frontě ověřit příjemce, částky, obsah, šablonu/verzi a počty zpráv.
- [x] Ověřit, že příprava Z-BOXu neposílá odběrový e-mail a interní podací kód
  není v zákaznické zprávě; po podání je odstraněn.
- [x] Zkontrolovat sklad, zákaznický konečný stav, opakování a nulové odesílání.
- [x] Doplnit přesné výsledky a screenshoty, B09 a oba handoffy.

- 21. 10. 2026: uložen plán a hranice autorizace před implementací.
- Další aktualizace musí uvést dokončené kroky, přesné výsledky kontrol,
  zbývající práci a bod, odkud pokračovat. Nasazení zůstává zakázané.


## Výsledek lokální implementace

- Zásilkovna používá oddělený výběr akce. Regrese pokrývá nezaplacenou objednávku,
  přípravu, zákaz osobního výdeje, chybějící štítek, fyzické podání a opakování.
  Podací kód se po podání odstraní a zákaznický e-mail jej neobsahuje.
- Hlavní stav v administraci, zákaznickém seznamu i detailu je vždy systémový.
  Vlastní upřesnění je samostatné a zobrazuje se jen během vytvoření/přípravy.
- Editor obsahuje šest skutečně existujících systémových zpráv: platba, osobní
  odběr, PPL, Zásilkovna, nákup bez účtu a rychlá přihláška. Nezavádí žádné nové
  automatické události ani editor ověřovacích/resetovacích e-mailů identity.
- Šablony mají historii, důvod a autora; zastaralý editor nemůže přepsat novější
  verzi. Náhled neukládá šablonu ani zprávu a používá výhradně ukázková data.
- Zpráva ve frontě ukládá přesný text a klíč/verzi šablony. Fronta je ukazuje
  v náhledu; změna šablony nepřepisuje původní zprávy.
- Nová migrace: `20261021170000_shop_system_email_templates`.
  Přidává dvě tabulky a dva sloupce původu šablony v existující frontě.
  Zálohovací kontrakt je `2026-10-21.3`.

### Dokončené kontroly

| Kontrola | Výsledek |
|---|---|
| Celá PHPUnit sada po integraci aktuálního main | 1 166 testů / 26 263 kontrol, OK |
| Následná cílená regrese šablon, akcí a společné fronty | 28 testů / 564 kontrol, OK |
| Závěrečná regrese checkoutu, stavů, šablon a pracovních rolí | 59 testů / 2 060 kontrol, OK |
| MariaDB souběh dvou editorů | právě jeden zápis, druhý odmítnut; historie neztracena |
| Opakované použití nové migrace | OK, stará zpráva zachována |
| MariaDB záloha a obnova | 188 tabulek, OK, včetně nového ownership kontraktu |
| Lokální migrační check | current=true, 109 migrací, pending=[] |
| PHP lint změněných/nových PHP a git diff --check | OK |
| Browser | přehled, editor, náhled, uložení verze, historie, nepovolená proměnná a zákaz přístupu z jiné pozice |
| Browser konzole editoru | bez chyb a varování |

### Lokální prostředí a rozsah důkazu

- URL: `http://localhost/evidencePavel-order-status-system-mail-20261021/eshop_order_statuses_admin.php`.
- Samostatná MariaDB: `127.0.0.1:3310`, databáze `evidence_status_mail_local`.
  Nastavení je pouze v ignorovaném `config.php`, nevzniklo kopírováním produkce.
- Použitá lokální MariaDB je 10.4.32. Matice 10.3/11.4 na GitHub CI v tomto
  řezu spuštěna nebyla, protože vzdálené kroky nejsou autorizované.
- Kontrola po první prohlídce editoru: jedna uložená verze šablony, nula zpráv
  v transakční frontě. Navazující úplný nákup je popsán níže. Worker nespouštěn.
- Syntetický správce upravil přes UI odběrovou šablonu na verzi 1; text obsahuje
  pouze LOCALHOST/NEPLATIT, ukázkovou recepci a adresu `example.test`.
- První prohlídka zanechala B09 PARTIAL, protože ještě neobsahovala browser
  nákup/výdej. Navazující průchod níže tento nedostatek uzavřel jako lokální PASS;
  doručení skutečné pošty ani doprava tím ověřeny nejsou.
- Screenshot editoru: `var/local-status-mail-review/editor.jpg` (lokální artefakt).
- Při úplné inicializaci/backup smoke vydává již existující migrace
  `20260922190000_member_fee_standing_orders.php:13` varování o klíči `columns`.
  Výsledný katalog je aktuální a obnova prošla; tato neměnná historická migrace
  nebyla v tomto řezu upravována.

### Další krok

Lokální implementace schváleného rozsahu je připravena k revizi. Před případným
převzetím na jinou větev zkontrolovat nové commity `origin/main`, přiměřeně
zopakovat dotčené kontroly a získat samostatný souhlas k push/merge/deploy.
Fakturaci, číselné řady, přílohy ani změnu dokončování kroužků tento řez nezavádí.
Žádné skutečné e-maily, platební brány, CRONy ani produkční data nebyly měněny.

- Integrace 21. 10. 2026: lokální změna převedena také na `06e0a9b1` (PR #151–153).
  Zachovány nové názvy registračních textů a jejich zálohovací sloupce; společný
  kontrakt zvýšen na `2026-10-21.3`. Po integraci prošlo znovu všech 1 166
  testů / 26 263 kontrol, MariaDB souběh editorů i záloha a obnova 188 tabulek.
  Lokální katalog obsahuje 109 aktuálních migrací bez čekajících kroků.
- Nová migrace má v `.gitattributes` výslovně LF, aby Git při checkoutu na
  Windows nezměnil její byte-sensitive checksum. Při rebase byla obnovena
  původní LF podoba; obsah migrace ani databázový ledger se nepřepisovaly.

## Úplný lokální browser průchod 21. 10. 2026

Dokončeno přes skutečné formuláře lokálního webu v prohlížeči. Produkční ani
zdrojový Shoptet se neměnil. Data nebyla zakládána SQL příkazy; SQL sloužilo
jen k následnému čtení skladových pohybů, auditu a fronty.

### Prostředí a simulace Zásilkovny

- Doplněn výslovný režim `SHOP_PACKETA_LOCAL_DEMO === true`, povolený pouze
  v ignorovaném lokálním `config.php`. Nestačí obecné `JE_LOKALNE`: HTTP host,
  serverová a klientská adresa musí být loopback, stejně jako případný APP_HOST.
  Parametr URL/formuláře režim nezapne. Bez příznaku zůstává reálná validace
  veřejného klíče a dostupnosti dopravce zachovaná.
- V testovacím režimu UI označí simulaci a nabídne jediný syntetický bod
  `999000001`, „LOCALHOST TEST Z-BOX – NEODESÍLAT“. Nezavádí Packeta script,
  nevolá feed, nepoužívá skutečné klíče; neznámé ID odmítne. Server stále
  ověřuje dopravu, cenu, zboží a ukládá snapshot boxu běžným checkoutem.
- Bezpečnostní/regresní testy pokrývají chybějící/vzdálené adresy, cizí doménu,
  výchozí vypnutí, absenci carrier scriptu, syntetický resolver a opětovné
  odmítnutí po změně klienta na vzdálenou adresu: 3 testy / 20 kontrol, OK.
- Opravena i neinicializovaná `$pplSelected` ve společném formuláři dopravy.
  Produkční větev mapy Zásilkovny ani PPL se testovacím režimem nenahrazuje.

### Objednávky a sklad

Přes správu katalogu založen produkt/varianta 5, SKU `KP-LOCAL-STATUS-MAIL-001`,
cena 125 Kč, počáteční sklad 10 ks, povolen osobní odběr i doprava a zveřejněno
pouze v tomto lokálním katalogu.

| Průchod | Objednávka | Ověřený výsledek |
|---|---|---|
| Nákup s účtem | `KP2610210B2799C10B` | 1 ks, 125 Kč, syntetický účet `admin@localhost.test`; zákaznický košík → QR/převod → hospodář potvrdil testovací převod → příprava → osobní výdej → `completed/paid`, UI „Vydáno“. |
| Nákup bez účtu, Z-BOX | `KP26102146FFE0B1F9` | 2 ks 250 Kč + doprava 79 Kč = 329 Kč, `zbox@localhost.test`; chybějící výběr boxu blokován → testovací box → objednávka/QR → hospodář změnil převod na hotovost a přijal testovací platbu → příprava → údaje štítku → podání → `completed/paid`, UI „Podáno do Z-BOXu“. |

- Sklad skutečně `10 → 9 → 7`. Právě dva rezervační pohyby (-1 a -2), žádný
  další odečet při úhradě, přípravě nebo výdeji.
- U každé objednávky právě jedna událost každého očekávaného přechodu. Koncové
  UI nenabízí opakování výdeje/podání. Obnovení zákaznických stránek nevytvořilo
  další objednávku ani zprávu; opakovaný POST stejného přechodu nadále pokrývá
  integrační regrese `ShopCheckoutTest`.
- Z-BOX po přípravě nabídl formulář štítku, nikoli osobní výdej. Uloženy pouze
  syntetické údaje `Z1234567890` a interní devítimístný kód. Po simulovaném
  fyzickém podání zůstal tracking, `packeta_consignment_code IS NULL`.
- Zákaznická stránka před podáním ukázala „Připraveno k odeslání“ a vysvětlení;
  po podání „Podáno do Z-BOXu“ s očekáváním dalších pokynů od dopravce. Nikdy
  netvrdí, že už byla zásilka doručena. Interní poznámka ani podací kód se
  nezobrazily.

### Skutečná lokální fronta

V administraci otevřeny náhledy všech pěti uložených zpráv a porovnány příjemce,
položky/částky, druh dopravy a verze. Všechny zůstaly `pending`, `attempts=0`,
`sent_at=NULL`; `processing=failed=sent=0`. Žádný worker, CRON, mail transport
ani skutečné e-maily nebyly spuštěny.

| ID | Objednávka | Typ zprávy | Příjemce | Verze |
|---|---|---|---|---|
| 1 | osobní odběr | `shop_payment_received` – 125 Kč, osobní odběr | `admin@localhost.test` | 0 |
| 2 | osobní odběr | `shop_order_ready` – položka, místo, čas, kontakt | `admin@localhost.test` | 1 |
| 3 | Z-BOX | `shop_guest_order_placed` – 329 Kč, VS, box, bezpečný localhost odkaz | `zbox@localhost.test` | 0 |
| 4 | Z-BOX | `shop_payment_received` – 329 Kč, příprava k odeslání | `zbox@localhost.test` | 0 |
| 5 | Z-BOX | `shop_packeta_dispatched` – tracking, box, adresa | `zbox@localhost.test` | 0 |

- Nákup s účtem v dosavadním toku nemá úvodní platební e-mail; údaje zobrazí
  detail s QR. Proto jsou pro první objednávku dvě zprávy a pro hosta tři.
- Z-BOX `mark_ready` ani uložení štítku nezařazuje odběrový e-mail. Jediné
  dopravní oznámení vzniklo při potvrzení podání. Dokončení osobního odběru
  nepřidalo další zprávu. Žádné duplicitní typy/události.
- Ve všech pěti tělech je nulový výskyt interního podacího kódu i označení
  interní police. Syntetické adresy používají `.test`.
- Po zařazení zprávy #2 byla přes editor uložena verze 2 s jinou recepcí,
  časem a kontaktem. Náhled #2 stále ukazuje verzi 1, původní 16–18 hodin
  a původní kontakt. SHA-256 předmětu+těla před/po je shodně
  `d8c5cd332abed680bad5919729ace01f8017b26815f79fca6cf87edf1d1cd15f`.
- B09 přes formulář `testovaci_scenare.php` uložen jako **PASS** s popisem obou
  průchodů a omezení. Nejde o celkové UAT všech scénářů. Browser konzole fronty
  bez chyb a varování.

### Lokální důkazy a hranice

Po doplnění localhost simulace prošla znovu **celá PHPUnit sada: 1 169 testů /
26 302 kontrol, OK** (PHP 8.2.12). PHP lint všech pěti změněných/nových PHP
souborů a `git diff --check` prošly. Browser akceptační hub potvrzuje 109/109
migrací ve shodě s kódem. Nová simulace nemění databázové schéma; předchozí
MariaDB souběh editorů a záloha/obnova 188 tabulek zůstávají platným důkazem
pro nezměněnou migraci.

Artefakty zůstávají v `var/local-status-mail-review/` (místní, neverzované):
`pickup-completed.jpg`, `zbox-checkout.jpg`, `zbox-completed.jpg`,
`queue-frozen-v1.jpg`, `queue-zbox.jpg`, `b09-pass.jpg`, `db-verification.tsv`.
TSV neobsahuje přístupový token hosta ani těla se soukromým odkazem.
Výsledek B09 je v ignorovaném `var/acceptance-feedback.json`.

Fyzické předání, skutečná platba, mapa/feed dopravce, založení a tisk skutečného
štítku, vložení balíku a doručení e-mailu byly mimo rozsah této lokální zkoušky.
Štítek aplikace stále sama nevystavuje přes API: v ostrém provozu se založí
ručně v Klientu Zásilkovny a následně zaznamená v KIS. Ani tuto funkci, ani
fakturaci testovací simulace nezavádí.

Při pokračování otevřít tento plán a Git stav izolovaného worktree. Lokální
databáze obsahuje dvě dokončené testovací objednávky a pět čekajících zpráv;
nespouštět nad nimi skutečný poštovní transport. Aktivní pozice obnovena na
Zákaznickou péči a objednávky. Připraveno k revizi, bez push/merge/deploy.

Lokální implementační commity: `55f43e68` a `690fefd8`. Na konci práce ukazuje
již existující remote reference `origin/main` navíc dokumentační commit
`e7e0d44d` (PR #154, ověření hotovostního formuláře); nebyl do tohoto řezu
automaticky integrován. Nemění PHP ani schéma. Před budoucím slučováním převzít
jeho dokumentaci a zachovat oba aktuální handoffy. V tomto průchodu nebyl
proveden fetch, push, merge ani deploy.
