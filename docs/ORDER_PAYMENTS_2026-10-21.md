# Přijetí hotovosti místo převodu

Navazující ověření 21. 10. 2026: **celý TEST cyklus převod → hotovost →
příjem → storno → vratka prošel v produkčním UI**. Samostatná objednávka
`KP2610218B7C69C006` za 1 Kč skončila Stornováno/Vráceno; sklad nové TEST
varianty prošel 1 → 0 → 1 a produkt je stažený. Skutečné peníze ani objednávky
se neměnily. Podrobný [protokol](ORDER_CASH_REFUND_PRODUCTION_2026-10-21.md)
obsahuje oddělená TEST data, audit a hranice ověření. Níže je předchozí průchod.

Stav 21. 10. 2026: změna je sloučená v [PR #150](https://github.com/KovoPraha/evidenceTreninku/pull/150)
a nasazená na <https://kis.kovopraha.cz/>. Aplikační SHA:
`f813264b16ffd49224bd41a908adc33d9687af88`.
[Produkční deploy 39590481900](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39590481900)
prošel včetně ověřené zálohy, 107 aktuálních migrací bez čekajících kroků,
aktivace release a HTTP 200. `uat_schvaleno=false`, `importovat_krouzky=false`.
Skutečné objednávky ani platby nebyly při ověřování změněny.
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
Po aktualizaci základu prošlo [CI PR 39590307956](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39590307956):
**1 155 testů / 26 040 kontrol**, MariaDB 10.3 i 11.4. CodeQL prošel.
[CI výsledného main 39590445587](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39590445587)
je také úspěšné. Produkční workflow zopakovalo kontrolu PHP, Composer a testy.

Samostatná HTTP kontrola po nasazení: homepage a veřejný e-shop vracejí 200;
`eshop_orders_admin.php` a `eshop_payments_admin.php` vracejí nepřihlášenému
302 na `login.php`. Odpovědi neobsahují PHP fatal/parse chybu ani warning.

## Dokončené přihlášené ověření formuláře 21. 10. 2026

Původní výpadek připojení Chrome byl překonán novou kartou a dokumentovaným
DOM ovládáním. Kontrola proběhla na produkci po úspěšném navazujícím
[deployi 39593524611](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39593524611)
nad SHA `f5d6276838239ace6426aa8c326c01a213730a36`, který obsahuje i platební změnu.

Ověřeno na již existující objednávce testovacího účtu, bez platebního zápisu:

- Stránka plateb odmítá nesprávnou aktivní pracovní pozici.
- Z objednávky funguje **Přejít na Hospodáře a přijmout platbu**:
  přepne pozici a otevře platbu přesné objednávky s filtrem `q`.
- Výchozí je převod; lze vybrat hotovost a vyplnit poznámku.
- Způsob úhrady, poznámka a checkbox přijetí celé částky jsou povinné.
  Poznámka má limit 1000 znaků. Prázdná poznámka i nezaškrtnuté potvrzení
  mají v prohlížeči `validity.valueMissing=true`; po vyplnění je poznámka platná.
- Potvrzovací formulář používá POST a obsahuje CSRF ochranu; hodnota tokenu
  se nikam neexportovala.
- Částka v potvrzovacím textu odpovídá objednávce. Checkbox zůstal nezaškrtnutý
  a tlačítko **Zaevidovat přijatou platbu** nebylo stisknuto.
- Formulář byl vizuálně zkontrolován; konzole neobsahovala warning ani error.
- Volba a poznámka byly vyčištěny. Po obnovení stránky zůstal výchozí převod.
  Po návratu do objednávek byl celý text řádku shodný s počátečním stavem:
  čekající úhrada, převod, stejná částka i poslední událost. Původní pracovní
  pozice Registrář členů a KIS byla obnovena.

Výsledek: **PASS pro přihlášené ověření formuláře bez zápisu platby**.
Žádná platba ani vratka, včetně testovací, nebyla potvrzena. Prohlížečová kontrola
neověřovala serverové zpracování platebního POST; to pokrývají integrační testy.
Plné UAT aplikace zůstává samostatné. Lokální snímek formuláře:
`output/browser/cash-payment-form-2026-10-21.png` (není součástí Git repozitáře).
