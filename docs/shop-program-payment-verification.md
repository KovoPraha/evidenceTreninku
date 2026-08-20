# Ověření platebního životního cyklu kroužku (R12)

Ověřeno: 21. 8. 2026, Europe/Prague

## Rozsah a závěr

R12 nepřidává nový platební kód. Stávající bankovní/QR cesta je doložená od
košíku přes vznik objednávky a záznamu v `payments` až po automatickou účast a
soupisku po potvrzení platby. Storno zaplacené objednávky účast ukončí a označí
platbu jako čekající na vratku; samostatné potvrzení vratky pak uzavře finanční
stav bez druhého ukončení účasti.

Ověření proběhlo jen nad izolovanými lokálními databázemi a testovacími
bankovními údaji. Na produkci nevznikla objednávka, platba, účast ani členství a
žádná hodnota bankovního účtu nebyla přečtena nebo změněna.

## Doložený průchod

1. Košík a checkout

   - `shopCheckoutPlace()` znovu ověřuje serverovou cenu, prodejnost, příjemce,
     podmínky kroužku, kapacitu a bankovní nastavení uvnitř transakce.
   - V jedné transakci vznikne `shop_orders`, neměnný snapshot položky v
     `shop_order_items`, právě jeden čekající řádek `payments` a auditní událost.
   - Platba obsahuje snapshot účtu, částku, měnu, variabilní symbol, splatnost a
     SPD payload. Z něj se vykreslí QR jako datové SVG. Opakování stejného
     idempotency klíče nevytvoří druhou objednávku ani platbu.
   - Neplatné bankovní nastavení ukončí checkout před zápisem. Změna účtu v
     administraci nemění účet, variabilní symbol ani QR již existující
     objednávky.

2. Potvrzení bankovní platby

   - `shopOrderAdminConfirmBankPayment()` vyžaduje administrátora, důvod a
     výslovné potvrzení; bez nich se stav nezmění.
   - `shopOrderConfirmPaymentInTransaction()` zamkne platbu a objednávku,
     nastaví `payments.status='paid'`, objednávku přepne na zaplacené zpracování
     a ve stejné transakci volá `clubProgramActivatePaidOrderInTransaction()`.
   - `clubProgramActivateOrderItemInTransaction()` vytvoří právě jednu aktivní
     `club_program_enrollments` a aktivní `club_roster_members`, případně obnoví
     dřívější shopové členství. Zapíše audit aktivace a notifikaci. Opakované
     potvrzení je idempotentní.
   - Pokud je kapacita mezitím plná, příjemce už není oprávněný nebo by vznikla
     duplicitní aktivní účast, celá transakce se vrátí: platba zůstane čekající a
     nevznikne částečná účast ani soupiska.

3. Storno a vratka

   - `shopOrderAdminCancel()` vyžaduje administrátora, důvod a výslovné
     potvrzení. U zaplacené objednávky nastaví platbu na `refund_required` a ve
     stejné transakci volá `clubProgramCancelOrderInTransaction()`.
   - Aktivní účast se označí jako `cancelled`, uloží čas, důvod a administrátora
     ukončení. Shopové členství v soupisce se odstraní pouze tehdy, pokud stejná
     osoba nemá pro stejný tým jinou aktivní programovou účast.
   - `shopOrderAdminConfirmRefund()` vyžaduje samostatné potvrzení, referenci a
     důvod. Nejdřív ověřuje, že už nezůstala aktivní účast, a teprve potom mění
     `payments.status` na `refunded` a zapisuje audit vratky.
   - Opakované storno i opakované potvrzení vratky jsou beze změny. Vratka
     nevytváří druhé ukončení účasti ani další změnu soupisky.

## Automatizované důkazy

- `ClubProgramPaymentLifecycleTest`: 14 testů / 312 assertions. Pokrývá vznik
  účasti a soupisky po platbě, rollback při plné kapacitě a ztrátě oprávnění,
  ochranu proti duplicitě, ukončení při stornu, následnou vratku, opakovaný nákup
  i zachování soupisky při jiné aktivní účasti.
- `ShopCheckoutTest`: 16 testů / 331 assertions. Pokrývá čekající `payments`,
  SPD/QR, idempotenci, explicitní a auditované potvrzení platby, storno před i po
  platbě a samostatné potvrzení vratky.
- `ShopBankSettingsTest`: 6 testů / 120 assertions. Pokrývá prioritu databázového
  nastavení, fail-closed neplatného záznamu, neměnnost snapshotu staré objednávky
  a nezapisující ukázkové QR.
- Plný MariaDB checkout/payment/last-place smoke prošel na 10.3.39 i 11.4.0;
  ověřuje stejný tok na databázovém enginu používaném v produkci. Na obou
  verzích současně prošla migrace `check → apply → check` a záloha s obnovou
  všech 119 vlastněných tabulek.

## Úkoly vlastníka

- Po přihlášení jako administrátor otevřít
  `https://kis.kovopraha.cz/eshop_bank_admin.php` a očima potvrdit, že zobrazený
  IBAN patří správnému klubovému účtu. Produkční bankovní nastavení je platné a
  bankovní checkout funguje; nejde o doplnění chybějících proměnných.
- Pokud má být vedle bankovní/QR cesty spuštěna platba kartou, dodat a bezpečně
  nastavit produkční Stripe live konfiguraci a její zapnutí schválit jako
  samostatný produkční krok. R12 Stripe nezapíná a nepoužívá žádný live klíč.
