# Přijetí hotovosti místo převodu

Stav 21. 10. 2026: lokální implementace na větvi `codex/order-cash-payment`,
základ `origin/main` = `2d86a5c3 (aktualizováno před vydáním)`.
Pracovní kopie: `C:\xampp\htdocs\evidencePavel-order-payments-20261021`.
Produkční nasazení ani změny skutečných objednávek nebyly provedeny.
Databázové schéma se nemění; skutečný způsob úhrady se ukládá do existujících
polí `payments.method` a `payments.payment_source`.

## Jak přijmout platbu

1. Ve správě otevřete **Objednávky** a vyhledejte objednávku podle kódu,
   zákazníka, e-mailu nebo variabilního symbolu.
2. U nezaplacené objednávky použijte **Přejít na Hospodáře a přijmout platbu**.
   Přepne se pracovní pozice a otevře se příslušná platba. Potřebujete
   přiřazenou pozici **Hospodář a platby**.
3. V poli **Skutečný způsob úhrady** zvolte **Hotově – změnit z převodu
   a přijmout platbu**. Pro skutečný příjem na účet ponechte **Bankovním převodem**.
4. Zapište, jak jste peníze převzali nebo ověřili, případně číslo dokladu.
5. Zkontrolujte částku a potvrďte přijetí celé částky zvoleným způsobem.
6. Klikněte na **Zaevidovat přijatou platbu**.
7. Objednávka je zaplacená a předaná k přípravě. V přehledu objednávek
   i zákaznickém detailu je vidět **Hotově**. Historie obsahuje převzetí
   hotovosti, přechod z převodu, pracovníka, čas a poznámku.

Stejný formulář je dostupný přímo v pracovní pozici **Hospodář a platby**
na stránce **Platby a vratky**.

## Pravidla

- Změna způsobu a potvrzení přijetí probíhají společně v jedné transakci.
  Pouhé zvolení hotovosti nic neukládá.
- Příjem musí potvrdit přihlášený hospodář přes formulář chráněný CSRF.
- Objednávka se znovu nevytváří a sklad se znovu neodečítá.
- Používá se společný platební přechod včetně aktivace kroužků, akcí,
  velodromu a zařazení oznámení zákazníkovi do stávající fronty.
- Opakované potvrzení stejné hotovostní platby nepřidává druhou platbu,
  druhou aktivaci ani druhé oznámení.
- Již zaplacenou nebo stornovanou objednávku nelze tímto formulářem
  přepsat na jiný způsob platby. Pozdější bankovní/online potvrzení
  hotovostní úhradu nepřepíše.
- U objednávky s přidělenými příjmy z finančního přehledu se hotovost
  tímto formulářem nepotvrzuje; je nutné nejprve vypořádat existující úhrady.
- Při stornu zaplacené hotovostní objednávky se nejprve vytvoří požadavek
  na vratku. Skutečné vrácení hospodář samostatně potvrdí referencí transakce
  nebo pokladního dokladu. Samotné storno neznamená vrácení peněz.
- Nejde o vytvoření či tisk účetního dokladu ani samostatnou pokladní knihu.

## Ověření

Regresní testy pokrývají převzetí hotovosti, audit, platební zdroj,
opakované potvrzení, aktivaci kroužku a vrácení celé transakce při chybě
auditu nebo kapacity, nesoulad částky, částečnou úhradu, pozdní potvrzení
jiným zdrojem a storno s vratkou. Testovací databáze jsou syntetické SQLite
v paměti; skutečné objednávky, platby a bankovní účty se nepoužily.

PHP syntaxe pěti změněných aplikačních souborů a `composer validate --strict`
prošly. Kompletní `composer test`: **1 148 testů / 25 883 kontrol**, úspěch;
`git diff --check` bez chyb. Nových je osm integračních testů.
MariaDB ani produkční či prohlížečové UAT této změny nebyly provedeny.
