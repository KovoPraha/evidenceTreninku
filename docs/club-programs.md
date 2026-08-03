# Kroužkové programy, období a účasti (M1.4)

## Doménový model

- `club_programs` je stabilní služba, například Cyklistická škola. Nemění se při novém pololetí.
- `club_program_offers` je konkrétní nabízené období. Váže program, školní sezonu, cílovou soupisku a právě jednu produktovou variantu e-shopu.
- `club_program_enrollments` je historická účast jednoho sportovce v jednom období. Uchovává přesnou platnost a zdrojovou objednávkovou položku.
- `club_program_enrollment_events` je audit aktivace. Historie se při prodloužení nepřepisuje.

## Bezpečnost aktivace

Aktivace nepřijímá jen ID objednávky. `clubProgramActivateOrderItem()` v jedné transakci ověří:

1. objednávková položka patří přihlášenému účtu a mapuje se na konkrétní nabídku;
2. položka má množství 1 a beneficiary snapshot;
3. účet má k beneficiary právě teď schválenou vazbu `self` nebo `guardian`;
4. objednávka není stornovaná a nabídka je aktivní;
5. nenulová položka má potvrzený stav `paid`; nulová položka je aktivovatelná bez platby;
6. kapacita ještě není vyčerpána.

MySQL dotaz zamyká řádek nabídky přes `FOR UPDATE`. Kontrola kapacity a vložení účasti proto používají stejný serializační bod. Unikátní klíče na `(offer_id,sportovec_id)` a `source_order_item_id` tvoří druhou idempotentní ochranu.

Po aktivaci se sportovec právě jednou přidá do cílové školní soupisky se zdrojem `shop`. Existující aktivní členství se zachová. Ukončené členství se automaticky neobnovuje a vyžaduje kontrolu správce. Chyba vrátí zpět účast, členství i oba audity.

## Uživatelský postup

1. Správce připraví školní sezonu a soupisku v `kis_rosters_admin.php`.
2. V `club_programs_admin.php` vytvoří stabilní program a nabídku období. Vybere existující publikovanou variantu e-shopu, termíny, kapacitu a cílovou soupisku.
3. Rodič v `booking/eshop.php` přidá položku a zvolí jedno ze schválených dětí. Běžné fyzické zboží zůstává kompatibilní bez beneficiary.
4. Po potvrzení platby otevře `booking/moje_programy.php` a účast aktivuje.
5. Stejná stránka zachovává přehled minulých i současných období.

## Omezení první verze

- Stávající checkout umí prodat pouze katalogový `offer_type=goods` a vyžaduje kladný celkový bankovní checkout. Programová nabídka proto dočasně mapuje existující prodejnou variantu; nulovou položku umí bezpečně aktivovat doménová služba, ale běžné UI zatím nevytvoří samostatnou nulovou objednávku.
- Potvrzená platba zatím automaticky nespouští aktivaci. Rodič ji bezpečně spustí v části Moje kroužky. Pozdější worker může volat stejnou idempotentní službu.
- Storno nebo vratka již aktivované účasti se automaticky nepropíše. Bude vyžadovat samostatný auditovaný lifecycle krok.
