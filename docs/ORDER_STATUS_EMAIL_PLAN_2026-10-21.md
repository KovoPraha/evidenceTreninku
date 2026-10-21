# Plán dokončení stavů objednávek a e-mailů

Aktualizace: 21. 10. 2026, Europe/Prague.

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
- Konečná kontrola lokálního prostředí: jedna uložená verze šablony, nula zpráv
  v transakční frontě. Žádný worker nebyl spuštěn.
- Syntetický správce upravil přes UI odběrovou šablonu na verzi 1; text obsahuje
  pouze LOCALHOST/NEPLATIT, ukázkovou recepci a adresu `example.test`.
- Lokální `testovaci_scenare.php` obsahuje u B09 PARTIAL a přesný popis rozsahu.
  Úplný nákup a výdej přes browser nebyl součástí prohlídky. Backendový průchod
  dopravců je ověřen integračními testy; doručení skutečné pošty ověřeno není.
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
