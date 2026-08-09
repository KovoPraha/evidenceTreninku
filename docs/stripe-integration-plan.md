# Plán Stripe integrace pro Evidence

Stav: slice 1 je připravena lokálně za výchozím vypnutým flagem. Produkční
aktivace, Stripe účet ani ostré klíče nejsou součástí tohoto řezu.

## Rozhodnutí

Použijeme hostovaný **Stripe Checkout Session s redirectem**, ne Payment
Element. Zákazník zadává kartu jen na stránce Stripe; Evidence nehostuje
kartový formulář ani nepřenáší údaje karty. Tím je integrační a PCI rozsah
menší a odpovídá malému klubovému e-shopu. Payment Element by přinesl více
frontendového stavu, vlastní obsluhu chyb a širší bezpečnostní a přístupnostní
odpovědnost bez přínosu pro první řez.

Stripe je pouze nový platební vstup do existujícího lifecycle. Nesmí měnit
objednávku, sklad, přihlášku, program ani rezervaci přímo. Jediná cesta
`pending -> paid` zůstává sdílená kanonická funkce vedle
`shopOrderAdminConfirmBankPayment()`; Stripe ji volá uvnitř stejné DB
transakce se systémovým aktérem a auditním zdrojem `stripe`.

## Mapování na data

- `payments.payment_source`: `bank_transfer` nebo `stripe`; u čekající
  objednávky zůstává `bank_transfer`, dokud skutečně zaplacený Stripe webhook
  atomicky nepotvrdí zdroj. Pouhé otevření Stripe stránky neruší možnost QR.
- `payments.stripe_checkout_session_id`: vazba objednávky na Checkout Session,
  unikátní a použitá pro lookup webhooku. Opakované vytvoření používá stabilní
  Stripe idempotency key odvozený pouze z interního order/payment ID.
- `payments.stripe_payment_intent_id`: reference pro budoucí refundaci; není
  důkazem zaplacení sama o sobě.
- `stripe_webhook_events.event_id`: primární idempotency klíč příchozího
  eventu. Tabulka ukládá typ, SHA-256 těla, stav a případné payment ID, nikdy
  celé webhook tělo ani kartová data.
- Částka a měna Checkout Session se čtou výhradně z neměnných snapshotů
  `shop_orders.total_minor/currency` a `payments.amount_minor/currency`.
  Neshoda nebo jiný než `placed/pending/pending` stav selže bez vytvoření či
  potvrzení platby; request částku ani měnu nepředává.

Aktuální repozitář má legacy `includes/auto_migrace.php` a `SCHEMA_VERSION`
záměrně zmrazené na 2.20.2 a jejich neměnnost hlídají testy. Proto je změna
správně v idempotentní číslované migraci
`migrations/20260809090000_stripe_checkout.php`, kterou produkční workflow
aplikuje před aktivací release. Nová tabulka je ve vlastnickém kontraktu DB
záloh. Zmrazený baseline se nezvyšuje.

## Checkout a webhook

1. Na detailu vlastní čekající objednávky se tlačítko „Zaplatit kartou” ukáže
   jen když je `STRIPE_ENABLED=true` a všechny tři klíče i důvěryhodný
   `APP_BASE_URL` projdou fail-closed kontrolou. Test/live prefix tajného a
   publishable klíče se musí shodovat.
2. POST s CSRF vytvoří Checkout Session přes tenkou testovatelnou vrstvu.
   Success/cancel URL vznikají z `APP_BASE_URL`, nikdy z HTTP `Host`.
3. `booking/stripe_webhook.php` přijímá jen POST, čte raw body a hlavičku
   `Stripe-Signature`, ověřuje podpis pomocí `STRIPE_WEBHOOK_SECRET` a vždy
   posílá `Cache-Control: no-store`. Chybný podpis vrací 400, vypnutý Stripe
   404, interní chyba 500 pro Stripe retry.
4. `checkout.session.completed` se přijme jen pro `mode=payment` a
   `payment_status=paid`. Session ID, metadata order/payment ID, amount a
   currency se musí shodovat se serverovým snapshotem.
5. Zápis event ID, uložení PaymentIntent, kanonický přechod objednávky,
   aktivace navázaných služeb a audit proběhnou v jedné DB transakci.
   Duplicitní event ID vrátí 2xx bez druhého přechodu. Ostatní eventy se
   evidují jako `ignored`, zalogují a dostanou 2xx ACK.

## Lifecycle, storno, expirace a sklad

- Stripe potvrzuje jen přesně čekající `placed/pending/pending` objednávku.
  Zaplacenou, stornovanou, expirovanou, refundovanou nebo nekonzistentní
  objednávku nesmí vrátit do života.
- Sklad se rezervuje už při kanonickém vytvoření objednávky. Stripe Session
  proto žádný skladový pohyb nedělá. Pending storno/expirace dál používá
  existující auditované vrácení skladu právě jednou.
- Po přechodu na `paid/processing` používá správní storno stávající cestu:
  vrátí sklad a nastaví `refund_required`. Stripe nesmí provést vratku ani
  změnit stav jen podle návratové success URL v prohlížeči.
- Pozdní completed event po stornu/expiraci selže uzavřeně a objednávku
  neobnoví. Před zapnutím live režimu je nutné doplnit provozní reconciliation
  pro takový vzácný případ a sladit expiraci Stripe Session s lokální expirací.

## Refundace — navazující slice

Refund se spouští až nad kanonickým `refund_required` a pouze pro
`payment_source=stripe` s uloženým PaymentIntent. Backend vytvoří Stripe Refund
s idempotency key odvozeným z payment/order ID. Samotné přijetí API requestu
nesmí změnit objednávku na `refunded`; konečný sdílený přechod se provede až po
ověřeném `refund.updated`/`charge.refunded` výsledku a musí auditovat systémový
zdroj, Stripe refund ID a částku. První verze má podporovat jen celou vratku;
částečné vratky vyžadují nové produktové rozhodnutí a ledger model.

Bankovní `shopOrderAdminConfirmRefund()` zůstane pro bankovní převody. Stripe
varianta použije vedle něj společné refund jádro, stejně jako tato slice sdílí
paid jádro, aby se neduplikovalo SQL ani pravidla stavu.

## Test mode, live mode a nasazení

1. Vlastník založí klubový Stripe účet, dokončí právní/KYC údaje, výplatní účet,
   oprávnění uživatelů, 2FA a kontakty pro spory/refundace.
2. Nejprve se použijí pouze test klíče `sk_test_…`, `pk_test_…` a test webhook
   secret. `STRIPE_ENABLED` zůstane false, dokud není migrace nasazena a webhook
   dosažitelný přes produkční HTTPS.
3. Na Thinline nelze předpokládat externí env ani CLI argumenty. Trvalé runtime
   hodnoty patří do ignorovaného serverového `config.php` jako konstanty,
   případně do jím načteného soukromého putenv bootstrap souboru. Release je
   nesmí obsahovat; workflow dál kopíruje serverový `config.php` a sestavuje
   `vendor/` na GitHub runneru.
4. Test režim se zapne samostatným vlastnickým rozhodnutím, ověří se test karta,
   chybný podpis, replay, storno, expirace a reconciliation. Teprve po písemném
   přijetí se v samostatném řezu vymění všechny hodnoty konzistentně za live,
   zaregistruje live webhook a zapne flag.
5. Rollback aplikace znamená nejprve `STRIPE_ENABLED=false`; webhook potom 404 a
   UI zmizí. Již přijaté platby a `refund_required` záznamy se musí provozně
   dokončit, nesmí se mazat migrace ani idempotency evidence.

## Odpovědnost vlastníka před aktivací

- založit a vlastnit Stripe účet klubu, KYC, bankovní účet, 2FA a role;
- bezpečně dodat test/live secret, publishable key a webhook secret mimo Git;
- určit doménu a zaregistrovat přesný HTTPS webhook endpoint;
- schválit obchodní texty, poplatky, refund/dispute proces a odpovědnou osobu;
- rozhodnout datum zapnutí test režimu a později samostatně live režimu;
- před live aktivací přijmout navazující refund/reconciliation slice.
