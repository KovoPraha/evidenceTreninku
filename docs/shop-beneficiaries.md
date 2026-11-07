# Příjemce položky e-shopu

<!-- plan-catalog-status -->
> Stav při auditu 7. 11. 2026: Příjemce položky je zapojený v dnešním checkoutu; původní „bez UI“ popisuje M1.1, nikoli aktuální aplikaci.
> Evidence plánu: [katalog](PLAN_CATALOG.md), registr P07. [shop-checkout-k4.md](shop-checkout-k4.md) · [shop-program-payment-verification.md](shop-program-payment-verification.md)


Původní stav M1.1: samotný technický kontrakt před zapojením do tehdejšího UI.

Objednávající účet zůstává uložený na `shop_orders.account_id`. Konkrétní osoba,
které se položka týká, je volitelně uložená na
`shop_cart_items.beneficiary_sportovec_id` a při checkoutu se zkopíruje jako
neměnný snapshot do `shop_order_items.beneficiary_sportovec_id`.

Fyzické zboží zůstává zpětně kompatibilní a může mít příjemce `NULL`. Budoucí
služby budou konkrétního příjemce vyžadovat ve svém doménovém toku; tento krok
zatím nepovoluje checkout typů `club_event` ani `camp`.

## Bezpečnostní pravidla

- Příjemcem smí být pouze sportovec s aktivní schválenou vazbou `self` nebo
  `guardian` k přihlášenému účtu.
- Server vazbu ověřuje při nastavení příjemce i znovu uvnitř checkout transakce.
- Příjemce je součástí fingerprintu košíku; změna vyžaduje nové potvrzení
  checkoutu.
- Účet smí měnit jen položku svého aktivního košíku.
- Odvolání vazby po objednávce nemaže ani nepřepisuje historický snapshot.
- Rodinný read-only dotaz vrací jen položky osob dostupných přes právě aktivní
  vazbu. Nákupy zboží bez příjemce do tohoto pohledu nepatří.

## API pro následné zapojení

- `shopCartSetBeneficiary($pdo, $accountId, $cartItemId, $sportovecId)` nastaví
  příjemce; hodnota `NULL` ho bezpečně odebere.
- `shopBeneficiaryOrderItemsForAccount($pdo, $accountId)` vrátí položky pro
  aktivně spravované osoby nebo vlastní profil.
- `shopBeneficiaryActiveRelation(...)` a `shopBeneficiaryAssertAccessible(...)`
  tvoří společnou autorizační hranici pro budoucí službové checkouty.

UI výběru dítěte a společný rodinný portál patří do následné integrace s rodinným
profilem. Příjemce služby se nikdy nesmí přijmout pouze ze skrytého HTML pole bez
této serverové kontroly.
