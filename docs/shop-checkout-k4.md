# První checkout K4

První vertikála K4 je záměrně úzká: přihlášený veřejný účet může koupit pouze
aktivní kanonický produkt typu `goods`, zvolí osobní odběr a dostane bankovní
platební předpis v CZK s QR kódem. Anonymní checkout, kupóny, Stripe, Fio import,
Packeta a placený kroužek zatím nejsou zapnuté.

## Bezpečnostní a účetní kontrakt

- Košík přijímá pouze aktivní publikovaný produkt a aktivní viditelnou variantu
  s pevnou cenou v CZK.
- Cena se v košíku pouze zobrazuje. Při checkoutu se všechny varianty znovu
  načtou pod transakčním zámkem a cenu vždy určí server. Hash souhrnu variant,
  množství, cen a měn zároveň brání tichému přijetí změněné ceny; zákazník musí
  nový souhrn nejprve znovu zobrazit.
- Objednávka ukládá snapshot veřejného názvu, SKU, atributů, jednotkové a řádkové
  částky, měny, příznaku DPH a sazby DPH v basis points.
- Jednorázový checkout klíč se ukládá pouze jako SHA-256 hash. Opakované odeslání
  stejného klíče vrátí původní objednávku a nevytvoří druhou platbu.
- Je-li u varianty spravovaný sklad, checkout jej atomicky sníží pod podmínkou,
  že zůstává nezáporný, a zapíše pohyb `reserve` do
  `shop_inventory_movements`. `NULL` znamená v tomto přírůstku nespravovaný sklad.
- Objednávka, položky, sklad, platba, audit a uzavření košíku vznikají v jedné
  databázové transakci.
- Návrat uživatele ani zobrazení QR kódu nikdy neoznačí platbu jako zaplacenou.
  Administrátor ji potvrdí na `eshop_orders_admin.php` až po skutečné kontrole
  banky, s CSRF tokenem, důvodem a potvrzovacím checkboxem.

## Bankovní konfigurace

Checkout je fail-closed. Bez validního IBAN, názvu účtu a splatnosti nevznikne
objednávka. Produkční hodnoty nastavte mimo Git jako environment proměnné:

```text
SHOP_BANK_IBAN=CZ...
SHOP_BANK_BIC=...
SHOP_BANK_ACCOUNT_LABEL=KOVO Praha
SHOP_BANK_DUE_DAYS=7
```

IBAN prochází kontrolou formátu i MOD-97. Variabilní symbol je desetimístné ID
objednávky. QR payload používá formát Short Payment Descriptor `SPD*1.0` a SVG
se generuje lokálně knihovnou `endroid/qr-code`; platební údaje se neposílají do
externí QR služby.

## Stránky a stavy

- `booking/eshop.php`: katalog a košík přihlášeného zákazníka,
- `booking/objednavka.php?code=...`: vlastníkova objednávka, platební údaje a QR,
- `eshop_orders_admin.php`: administrativní seznam a ruční potvrzení platby.

Objednávka začíná jako `placed` + `payment_status=pending`. Ruční potvrzení
přepne platbu na `paid` a objednávku na `processing`; auditní akce je
`confirm_bank_payment`.

## Migrace a další krok

Schéma vyžaduje migraci `20260803230000_shop_checkout`. Před aktivací PHP musí
být aplikována migračním runnerem. Další přírůstek musí doplnit auditované storno
objednávky s kompenzačním skladovým pohybem, stav přípravy/výdeje, zákaznický
seznam objednávek a teprve potom kupón nebo automatické Fio párování.
