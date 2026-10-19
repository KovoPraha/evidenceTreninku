# Datové toky EvidencePavel / KIS — revize 19. 10. 2026

Zdrojový stav: `76ae9eb` (`main`), bez produkčních zápisů. Jde o statický audit kódu, nikoli potvrzení dostupnosti služby, aktuálních produkčních dat nebo doručení e-mailů. Historické snímky z 24. 8. a 7. 10. 2026 zůstávají beze změny. Editovatelný inventář formulářů a tras je v `DATOVE_TOKY_APLIKACE.xlsx`; heuristický inventář vždy čtěte společně s ručně ověřenými toky, protože SQL zápis bývá ve službě, nikoli ve vstupním souboru.

## Implementační dodatek (lokální pracovní strom, 19. 10. 2026)

Tento dodatek mění návrh toku, nikoli výše uvedený historický zdrojový snímek. Registrace `booking/registrace.php` → účet + hash tokenu + šifrovaný `auth_mail_outbox` v jedné transakci → CLI worker → e-mail → `booking/overeni.php`. Nedoručení řeší `booking/znovu_overit_email.php`; starý čekající odkaz se zruší a existence adresy se neprozradí. Obnova `booking/zapomenute_heslo.php` → `password_reset_tokens` + šifrovaný outbox ve stejné transakci; dřívější platný resetovací odkaz zůstává použitelný, dokud se jeden nepoužije nebo nevyprší. Worker ověřuje aktuální oprávnění a cílovou adresu. Správce řeší selhání přes `auth_mail_admin.php`, bez čtení tokenů. Provozní detaily v `docs/identity-email-queue.md`.

E-shop před odesláním zobrazuje konflikt více sportovců, volí jediného možného příjemce salda a nenabízí neproveditelný checkout. Detail programu a rychlá přihláška zachovávají `variant_id` přes přihlášení. Chybějící vazba osoby vede přímo na `booking/moje_osoby.php` s bezpečným návratem. Náhrady ukazují přesnou lhůtu storna a po ní nenabízejí neproveditelnou akci; uzamčený kredit míří na navazující nabídku. U přímého předpisu klubové akce vede potvrzení na `booking/platba_predpisu.php`, s částkou, VS, účtem a QR, přístupné jen platícímu účtu.

Lokální integrační testy ověřují tokeny/frontu a návazné domény. Produkční migrace, CRON, skutečné doručení a browser UAT **nejsou potvrzeny**. Jednoklikové rozdělení rodinného košíku nebylo zavedeno, aby nevznikaly vícečetné finanční objednávky bez samostatného účetního kontraktu; konflikt je nyní zřejmý před odesláním. Další synchronní mailové cesty zůstávají podle F10.

## Jak číst mapu

`uživatel / externí událost → formulář nebo webhook → identita a oprávnění → serverová validace → služba a transakce → tabulky / soubor → audit a asynchronní efekt → stav viditelný člověku`.

Rozlišujte **vytvořeno**, **zaplaceno**, **připraveno/vydáno**, **zařazeno do programu či akce** a **e-mail odeslán**. Nejde o ekvivalentní stavy. Úspěšný návrat z platební brány ani vložení do fronty nedokazují úhradu či doručení.

## 1. Zdroje a hranice důvěry

| Zdroj | Identita / přístup | Důležitá hranice |
|---|---|---|
| Trenér / administrátor | `trener_id`, `canAccess()` a konkrétní oprávnění | Výběr osoby, finanční potvrzení a hromadné změny musí znovu ověřit server. |
| Veřejný účet / rodič | `verejny_uzivatel_id`, schválené vazby `self`/`guardian` | Identifikátor dítěte z formuláře není oprávnění. |
| Sportovec | samostatná session `sportovec_pristup_id` | Přístup k vlastnímu RSVP/profilu není administrace ani rodičovský účet. |
| Anonymní zájemce / kupující | formulář a jednorázový token | Po rychlém nákupu vzniká omezený přístup k objednávce, ne obecné přihlášení. |
| Veřejná karta | náhodný bearer token + případná session vazba | Čtení nesmí být zaměněno za právo k zápisu poznámky. |
| Platební poskytovatel | podepsaný webhook + serverová kontrola platby | Browser návrat není účetní potvrzení. |
| Banka / import | read-only Fio a ruční potvrzení | Importovaný kandidát není potvrzená platba. |
| CLI/worker | explicitní host, konfigurace a provozní plán | Fronta bez běžícího workeru neznamená doručení. |

## 2. Cesty člověka a konečné stavy

| Cesta | Vstup → zápis / výstup | Co musí být jasné po dokončení |
|---|---|---|
| Založení účtu | `booking/registrace.php` → `verejni_uzivatele`, hash verifikačního tokenu → `booking/overeni.php` | Účet čeká na ověření, odkaz má expiraci a musí existovat možnost bezpečného opakování doručení. |
| Obnova hesla | `booking/zapomenute_heslo.php` → `password_reset_tokens` → `booking/nove_heslo.php` | Neprozrazovat existenci účtu, ale mít cestu při nedoručeném e-mailu. |
| Připojení dítěte | `booking/moje_osoby.php` → žádost/vazba účtu a sportovce | Po schválení se osoba objeví ve výběru programu/akce; předtím jasný stav žádosti. |
| E-shop a program | `booking/produkt.php` / `booking/eshop.php` → košík → `shopCheckoutPlace()` → objednávka + platba | Doklad/QR nebo poskytovatel; zvolený účastník a termín se nesmí ztratit. |
| Rychlá přihláška | `booking/rychla_prihlaska.php` → osoba, žádost, objednávka → `booking/objednavka.php` | Objednávka je vytvořená, ale platba a kontrola identity jsou samostatné. |
| Úhrada | bankovní potvrzení nebo podepsaný SumUp/Stripe webhook → kanonická platební změna | Stav platby viditelný na objednávce; teprve potvrzená úhrada aktivuje navázané plnění. |
| Klubová akce | `club_calendar.php` → publikace; `booking/klubovy_kalendar.php` → bezplatná přihláška, přímý akční předpis nebo produktový košík | Tři cenové politiky mají odlišné dokončení: zdarma, přímý předpis s potvrzenou účastí, nebo účast vázaná na úhradu objednávky. |
| Trénink a náhrady | plán → RSVP / omluva → docházka → kredit → rezervace náhrady | Důvod zamítnutí, časová lhůta a další dostupný termín musí být vidět před pokusem o zápis. |
| Měsíční příspěvek | plán → náhled → generování `club_member_charges` + `payments` | Předpis má jednoznačný měsíc, splatnost, stav a stálý VS. |
| Roční úhrada | výběr sportovce + 12 měsíců → náhled → jedna `club_finance_receipts` + 12 měsíčních předpisů/alokací | Výhoda je explicitní sleva v náhledu; měsíční historie zůstává čitelná. |
| Saldo a omezení | předpisy + nezaplacené objednávky − potvrzené příjmy → profil / rozhodnutí o přihlášení | Dluh vstupuje do restrikce až po konfigurované lhůtě; přeplatek se přiděluje kontrolovatelně. |
| Stav objednávky | administrátorská definice → změna stavu → audit + případná fronta zpráv | Zákaznický stav, platba a expedice jsou odlišné osy; e-mail je asynchronní. |

## 3. Úložiště, vazby a vedlejší efekty

| Oblast | Kanonická data | Vedlejší efekt / výstup |
|---|---|---|
| Identita | `verejni_uzivatele`, `sportovci`, vazby rodiny, `password_reset_tokens`, revokační údaje | Verifikační a obnovovací e-mail; session. |
| Sportovní evidence | `treninky`, `zavody`, soupiska/účast, `mereni_zaznamy`, vazební tabulky | Audit, obrázky v soukromém nebo řízeném úložišti, profil a export. |
| Plány, RSVP, náhrady | plánované tréninky, odpovědi, omluvy, `club_makeup_credits`, `club_makeup_reservations`, `club_makeup_events` | Účast, kapacita, uzávěrka a dostupnost náhrad. |
| Katalog / objednávka | `shop_products`, `shop_variants`, `shop_carts`, `shop_orders`, `shop_order_items`, `payments`, pohyby skladu | Potvrzení platby, programové/akční/velodromové aktivace, QR, objednávkový stav. |
| Akce | `club_events`, termíny, registrace, plánovaní účastníci, akční předpisy | Kalendář, čekací listina, notifikační fronta. |
| Finance | `member_fee_plans`, `club_member_charges`, `club_finance_receipts`, `club_finance_allocations`, `club_finance_settings` | Profilové saldo, blokace přihlášení, audit potvrzení. |
| Komunikace | `club_event_notifications`, fronta soupiskových zpráv, další účelové fronty | Worker claim/retry; stav fronty není potvrzení doručení člověku. |
| Importy | archiv/hash, staging, náhled, explicitní promote a audit běhu | Kanonické osoby, členství, produkty či platby až po rozhodnutí. |

Kanonické vazby a větve jsou ve sloupci **Úložiště** a **Další krok / chyba** v sešitu. List **Vstupní body** inventarizuje přímo čtené GET/POST/FILES klíče a přímé SQL výskyty; nedokazuje kompletní datovou linii přes `includes/`.

## 4. Nově důležité návaznosti oproti auditu 7. 10.

- Saldo (`includes/club_finance.php`) kombinuje měsíční předpisy, přiřazené nezaplacené objednávky a ověřené příjmy. `clubFinancePastGrace()` testuje až den po `due_on + grace_days`; otevřená částka a blokující částka jsou rozdílné hodnoty. Objednávka po expiraci se do dluhu nepočítá ani před dokončením expiračního workeru.
- Roční úhrada (`includes/member_fee_annual.php`) vytváří jednu příjmovou položku a po jednom měsíčním předpisu/alokaci za měsíc, nikoli alternativní roční závazek.
- Programové/akční/velodromové položky mají účastníka. Checkout v `includes/shop_checkout.php` odmítá košík s více účastníky kvůli jedné finanční atribuci objednávky. Rozhraní to má sdělit před finálním kliknutím.
- Konfigurovatelné zákaznické stavy (`includes/shop_order_customer_status.php`) se ukládají odděleně od technického `status` a `payment_status`. Při změně vzniká auditní `shop_order_events` a volitelně `club_event_notifications`; k odeslání je potřeba worker.
- Omluva a náhrada (`includes/club_makeup.php`) jsou samostatné zápisy s podmínkami platného zápisu, lhůty, kapacity a věku. Pozdní pokus o zrušení rezervace je backendem odmítnut.
- U klubových akcí se `pricing_policy='product_variants'` převádí do košíku, `pricing_policy='free'` potvrzuje účast přímo a další placená politika vytváří při potvrzení účasti samostatný `club_member_charges` a `payments` (`includes/club_calendar.php:412-425`). Tyto dvě placené cesty se nesmí v mapě zaměnit.

## 5. Limity důkazu a kontrolní otázky před produkčním závěrem

Tato revize neprovedla přihlášený průchod rolí, neměnila produkční data, neposlala e-mail ani nesimulovala bankovní platbu. Před označením toku jako provozně ověřeného je potřeba testovacími účty projít úspěch i chybu, zkontrolovat skutečný schválený stav workerů a stav front, potvrdit aktuální migrace/tabulky a časovou zónu produkce. Výsledky této statické kontroly proto nejsou „UAT prošlo“.
