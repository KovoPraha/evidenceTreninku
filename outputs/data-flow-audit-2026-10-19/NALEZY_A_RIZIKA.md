# Nálezy: logika, redundance a slepé cesty — 19. 10. 2026

Zdrojový audit commitu `76ae9eb`. Závažnost vyjadřuje dopad na dokončení procesu, ne potvrzený incident. Opravy v této revizi nebyly provedeny. Detailní filtry a vlastníci procesu jsou v listu **Nálezy** sešitu.

| ID | Priorita | Potvrzené zjištění | Dopad a doporučení |
|---|---|---|---|
| F01 | vysoká | Registrace nejprve zapíše účet a teprve potom volá potlačené `@mail()` bez kontroly výsledku (`booking/registrace.php:100-142`). Neexistuje zde zřejmý ověřovací resend. | Při selhání pošty zůstane neověřený účet a stránka přesto oznámí „poslali jsme“. Zavést trvalý outbox, idempotentní generické „znovu odeslat ověření“, audit doručení a provozní alarm. Zachovat odpověď neprozrazující existenci adresy. |
| F02 | střední | Obnova hesla zneplatní dosavadní token a uloží nový před pokusem o doručení; návratová hodnota odesílače se ignoruje (`includes/password_reset.php:29-59`; `booking/zapomenute_heslo.php:24-38`). | Výpadek pošty může zrušit dosud použitelný odkaz a nový nedoručit. Sjednotit s trvalou frontou; určit pravidlo revokace a obnovení po chybě. |
| F03 | střední | Košík umožní vidět položky pro více sportovců a výběr finančního profilu, ale `shopCheckoutPlace()` je odmítne až při objednání (`booking/eshop.php:158`; `includes/shop_checkout.php:280-285`). | Rodina dojde na konec a musí ručně rozdělit nákup. Ukázat konflikt při přidávání a v košíku; nabídnout rozdělení košíku podle sportovce jedním krokem. Bez změny účetního pravidla. |
| F04 | střední | Pokud účet nemá propojenou osobu, výběr účastníka obsahuje jen prázdnou volbu. Text „v části Moje osoby“ není odkaz (`booking/produkt.php:275-276`; `booking/eshop.php:132`). | Formulář nelze dokončit a uživatel nemá přímé pokračování. Dát CTA na `moje_osoby.php`, zachovat návrat na produkt/košík a předem vysvětlit schvalování vazby. |
| F05 | střední | `booking/rychla_prihlaska.php:21,43-44` po přihlášení odkazuje jen na `produkt.php?id=…`, bez `variant_id`. Výběr termínu na detailu nepřijímá variantu z GET (`booking/produkt.php:223-226`). | Ztrácí se zvolený termín a musí se znovu hledat. Přenést bezpečně validované ID varianty přes login a předvýběr detailu. |
| F06 | střední | Ve výpisu náhrad je pro každou rezervaci tlačítko „Zrušit“, ačkoli služba po limitu zrušení odmítá (`booking/nahrady.php:51`; `includes/club_makeup.php:448-459`). Text navíc tvrdí, že pozdní storno pouze nevrací kredit. | Falešná akce končí chybou. Zobrazit deadline u rezervace, tlačítko po lhůtě schovat/deaktivovat a nabídnout kontakt na trenéra; sjednotit text s pravidlem. |
| F07 | nízká | Zamčený kredit náhrady vede na obecný e-shop (`booking/nahrady.php:47`), přestože kredit nese `carry_offer_id` (`includes/club_makeup.php:218-253`). | Další pololetí se hledá znovu. Odkázat na konkrétní nabídku, nebo jasně uvést, že zatím není zveřejněná. |
| F08 | nízká / technický dluh | Event-only košík se uměle označí jako neprázdný vložením `['service_item'=>true]` do `cart['items']` před podmínkou prázdnosti (`booking/eshop.php:135,158`). | Nyní funkční obezlička, ale view data neodpovídají skutečnému košíku a mohou rozbít nové zpracování položek. Podmínku postavit na součtu skutečných `items + event_items + velodrome_items`. |
| F09 | ověřit provozně | Změna zákaznického stavu vkládá volitelný e-mail do `club_event_notifications` (`includes/shop_order_customer_status.php:115-156`); odeslání vyžaduje `bin/club-event-notifications.php`. Kód ani tato kontrola nedokazují zapnutý produkční plán a doručování. | Před marketingovým příslibem „přijde e-mail“ ověřit CRON/bootstrap, monitoring `pending/failed`, retry a test na vyhrazenou adresu. Neoznačovat za potvrzený incident bez provozního důkazu. |
| F10 | nízká / udržovatelnost | Přímé `mail()` zůstává v několika synchronních registračních, rezervačních a hromadných cestách (`booking/rezervovat.php:213`, `booking/waiting_list.php:120`, `odeslat_emaily.php:218`; některé jiné výskyty jsou legitimní worker transport). | Sjednotit requestové cesty přes outbox, ale samotné worker transporty zachovat. Před migrací oddělit zdroje zpráv, idempotenci a texty. |
| F11 | střední | U klubové akce s přímým předpisem se po potvrzení účasti vytvoří `club_member_charges` a `payments` (`includes/club_calendar.php:369-410`), ale kalendář ukáže pouze „Účast byla potvrzena“ (`booking/klubovy_kalendar.php:43-53`). Přehled přihlášek ukáže stav, nikoli platební odkaz (`booking/krouzky.php:235-257`). | Rodič nemusí zjistit, že má zaplatit, a hledá předpis až ve sportovním profilu. Po potvrzení ukázat částku, splatnost, VS a přímý odkaz na platební detail/QR. |

## Stav lokální implementace 19. 10. 2026

| Nález | Stav | Zbývá |
|---|---|---|
| F01–F02 | Lokálně opraveno: šifrovaná transakční fronta, resend a bezpečný reset | Migrace, aktivní worker a test doručení na produkci. |
| F03 | Částečně: konflikt se ukáže a checkout je vypnutý; příjemce se při jedné osobě předvolí | Jednoklikové rozdělení na více objednávek vyžaduje samostatný účetní a idempotentní kontrakt. |
| F04–F08 | Lokálně opraveno v uživatelských cestách | Browser UAT po nasazení. |
| F09 | Otevřeno | Provozní důkaz CRONu, fronty a doručení. |
| F10 | Otevřeno | Inventarizovat a migrovat jiné synchronní e-maily. |
| F11 | Lokálně opraveno: odkaz a platební detail přímého předpisu | Browser UAT a kontrola produkčních platebních údajů. |

Původní řádky níže popisují auditovaný zdrojový commit, nikoli aktuální stav pracovní kopie. Viz `docs/identity-email-queue.md`.

## Doporučené pořadí původního auditu

1. F01–F02: spolehlivost ověření identity a resetu bez porušení anti-enumeration.
2. F03–F06 a F11: odstranit konkrétní slepé cesty a ztrátu kontextu; doplnit regresní testy se dvěma dětmi, přímým akčním předpisem a s časem za lhůtou.
3. F09: samostatně prověřit provozní doručování; zdrojový kód nestačí.
4. F07–F08 a F10: zkrácení cesty a odstranění redundance při další úpravě daných modulů.

## Co není nález

- Oddělení zákaznického stavu objednávky od platby a expedice je záměrná vícerozměrná stavová logika.
- Odmítnutí nákupu pro více sportovců v jedné objednávce chrání správnost salda; nález F03 je pozdní a nedostatečně vysvětlené odmítnutí, nikoli požadavek ochranu vypnout.
- Fio kandidát není automaticky úhrada; read-only import a ruční potvrzení jsou správně oddělené.
