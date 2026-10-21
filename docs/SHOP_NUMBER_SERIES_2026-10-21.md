# Číselné řady a účetní kategorie e-shopu

## Autorizace a pracovní stav

Vlastník 21. 10. 2026 schválil lokální implementaci RR N KK XXXXX,
účetních kategorií a smíšené řady. Bez produkčního nasazení. Existující VS,
QR instrukce i stálé VS příspěvků zůstávají zachované. Fakturace a účetní
export nejsou součástí tohoto řezu; ukládáme jejich budoucí podklady.

- Větev: `codex/shop-number-series-20261021`.
- Worktree: `C:\xampp\htdocs\evidencePavel-number-series-20261021`.
- Výchozí main: `574328421fff748b48f7d744a3ce454b9b50dd1e`.
- Cizí WIP v původní pracovní složce se neupravuje.

## Plán

1. Přidat neměnnou migraci: účetní nadkategorie/kategorie, mapování
   katalogových kategorií a produktů, roční čítače, rezervace VS, historické
   zařazení objednávky a každé položky a audit. Žádné přečíslování historie.
2. Implementovat transakční přidělení desetimístného VS, ochranu kolizí se
   všemi existujícími platbami i příspěvkovými rezervacemi, roční reset,
   idempotenci a kontrolu vyčerpání. Jedna účetní kategorie = její řada;
   více kategorií = vyhrazená smíšená řada.
3. Zapojit běžný checkout, nákup bez účtu a rychlou přihlášku. Uložit názvy,
   kódy a katalogovou cestu tak, aby přesuny v katalogu neměnily historii.
4. Přidat administraci pro hospodáře a administrátora: řady, mapování,
   roční počáteční číslo, náhled a historii změn. Připojit ji do menu.
5. Doplnit zálohovací/migrační kontrakty, regresní a integrační testy,
   souběh na MariaDB, syntax a úplnou PHP sadu; ověřit dostupnou lokální UI.
6. Aktualizovat tento plán a handoff výsledky, omezeními a návodem.

## Rozhodnutí

- Rok se bere z vytvoření objednávky v Europe/Prague, nikoli data platby.
- Účetní kategorie jsou stabilní číselník nezávislý na navigaci katalogu.
- Smíšená řada má vyhrazený kód 9/00; běžné nadkategorie používají 1–8.
- Přidělená čísla se po stornu nevracejí do oběhu.
- Před nastavením mapování musí existovat zřetelný bezpečný výchozí režim;
  neklasifikované položky se nesmějí vydávat za knihy/kroužky.
- Stávající databáze bez nové migrace dál používá dosavadní checkout;
  nově migrovaná databáze vydává nové VS a historické platby nemění.

## Průběh

Implementace dokončena lokálně. Migrace `20261021210000_shop_number_series`
přidává osm vlastněných tabulek bez aktualizace existujících plateb či objednávek.
Zálohovací kontrakt je `2026-10-21.5`. Čísla se přidělují ve všech třech
nákupních cestách před tvorbou platebního příkazu a QR, uvnitř jejich transakce.
Snapshoty pokrývají produktové, událostní i velodromové položky.

Databázový mutex a aktuální zamykací čtení chrání čítače i při starším
REPEATABLE READ snímku nákupní transakce. Dvojí zpracování stejné objednávky
vrací její vydané číslo. Správa používá revizi proti přepsání jiným správcem,
CSRF, důvod, potvrzení a transakční audit. Kódy kategorií jsou neměnné;
čítač nelze snížit. Vyčerpání odmítne nový nákup bez odečtení skladu nebo
vytvoření platby, zprávy či částečné objednávky.

## Návod pro správce

1. Přepněte se do **Hospodář a platby**. Také administrátor/superadmin
   používá tuto aktivní pracovní pozici; ostatní agendy stránku odmítnou.
2. V **Platby → Další nástroje → Číselné řady a účetní kategorie** otevřete
   `eshop_number_series_admin.php`.
3. Vytvořte či přejmenujte nadkategorii (kód 1–8). Přidejte účetní kategorii
   s dvoumístným kódem 00–99. Změny potvrzujte s důvodem.
4. V části **Přiřazení** vyberte katalogovou kategorii/nadkategorii nebo
   produkt a stiskněte **Načíst přiřazení**. Potom vyberte účetní kategorii
   a uložte. Konkrétní produkt má přednost; jinak se používá výchozí katalogová
   kategorie a její nejbližší nastavený předek. Volba převzetí odstraní výjimku.
5. Podle potřeby nastavte počáteční číslo pro vybranou řadu a rok. Náhled
   zobrazuje například VS `2630100013` a číslo `2026-3-01-00013`. Samotný
   náhled číslo nerezervuje; kolize s dřívějšími VS se při nákupu přeskočí.
6. V dolní části jsou poslední změny i 30 skutečně přidělených čísel.

Výchozí číselník: 1/00 kroužky, 2/00 oddíl, 3/00 zboží, 3/01 knihy,
3/02 oblečení, 8/00 nezařazeno, 9/00 smíšené. Produkty nejsou odhadovány
podle názvu: bez mapování jsou běžné zboží, akce a velodrom v 8/00;
kanonický typ `program` má 1/00. Tyto výchozí druhy lze auditovaně změnit.
Smíšená řada 9/00 se nemapuje na položky; volí se automaticky při více
různých účetních kategoriích v objednávce. Historie názvů, kódů i původní
katalogové cesty zůstává uložená u každé položky.

Rok se určuje v Europe/Prague. Podporovaný rozsah je 2000–2099, maximálně
99 999 vydaných čísel na kategorii a rok. Každý nový rok má samostatný čítač
od 1, pokud správce předem nenastaví vyšší hodnotu. Doprava sama o sobě
nemění řadu objednávky; její částka už má samostatný existující snapshot.

## Ověření a hranice

- Závěrečná úplná PHP sada: **1 190 testů / 26 730 kontrol, úspěch**
  po zpřísnění validace celých čísel a dokončení formulářů. Předchozí úplná
  sada bez lokálního demo configu měla 26 711 kontrol a také prošla.
- Cílená sada: 13 testů / 128 kontrol; běžný nákup, host, rychlá přihláška,
  QR s novým VS, idempotence, smíšené položky, dědění a explicitní přiřazení,
  historické VS, kolize obou příspěvkových rezervací, Praha na hranici roku,
  vyčerpání a rollback včetně skladu a pošty, audit a konflikty editace.
- Nový MariaDB smoke prošel: idempotentní migrace, dvě současná přidělení
  nad starším snímkem, dvojí požadavek pro stejnou objednávku a jeden vítězný
  správce. Je přidaný i do existující CI matice MariaDB 10.3/11.4.
- Existující MariaDB souběžný checkout, potvrzení platby a poslední místo
  v kroužku prošly s novou migrací.
- Záloha i obnova izolované MariaDB prošla: 198 tabulek a nové účetní tabulky
  v kontraktu. Lokální engine je XAMPP MariaDB 10.4.32; vzdálená CI matice
  tohoto řezu zatím spuštěna nebyla.
- Při aplikaci celého historického katalogu hlásí stará neměnná migrace
  `20260922190000_member_fee_standing_orders.php:13` varování chybějícího
  klíče `columns`. Smoke přesto dokončil zálohu/obnovu i checkout úspěšně;
  tato historická migrace se v tomto řezu neupravovala.
- PHP syntaxe, Composer validace a `git diff --check` prošly.
- Lokální browser: správná položka menu, odmítnutí jiné aktivní pozice,
  uložení počátečního čísla 13 pro knihy, uložení produktového mapování,
  načtení uložené hodnoty a audit; konzole bez warning/error. Snímek:
  `tmp/number-series-admin.png` (ignorovaný, syntetická data).

Samostatné demo používá DB `evidence_series_ui_test` na lokálním portu 3319
a web na `http://localhost:8921/`. Všechny změny nastavení při browser testu
jsou syntetické a zůstávají jen v tomto odděleném demu. Existující konfigurace
ani data původního checkoutu se nekopírovaly a neupravovaly.

Lokální web a izolovaná MariaDB jsou ponechané pro prohlídku. PID jsou
v ignorovaných `tmp/http-process.txt` a `tmp/mariadb-process.txt`; data a
`config.php` se necommitují. Syntetické demo přístupy jsou stejné jako v
`docs/localhost-testovani.md`. Ukládání nastavení při browser testu nevytvořilo
žádnou objednávku ani nepotvrdilo úhradu; nákupní zápisy ověřují integrační testy.

Implementace je připravená k lokální revizi. Další krok je kontrola změn a
případné samostatně vyžádané publikování PR; nezapínat produkci ani automaticky
nepřenášet testovací mapování. Při navazujícím řezu znovu ověřit aktuální main
a případný drift zálohovacího kontraktu.

Zatím bez produkčního nasazení, push a merge. Před případným vydáním je
nutné projít PR/CI a samostatně získat souhlas s nasazením; tento dokument
jej neuděluje. Faktury ani účetní export se nevystavují. Podklady pro jejich
budoucí implementaci jsou uložené ve snapshotových tabulkách.
