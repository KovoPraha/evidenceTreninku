# Následná kontrola skladů, prodeje a vratek — 23. 10. 2026

Kontrola navázala na R18–R24 a základ `7aff7884`. Pouze izolovaná pracovní
kopie `evidencePavel-finance-b1-20261022`, syntetický localhost a nové vlastněné
testovací databáze. Žádná změna produkce, externí služby ani nasazení.

## Potvrzené a opravené chyby

1. **Zrušení výměny přehlédlo skutečně přijaté peníze.** Pokud hospodář
   zaznamenal příjem doplatku, ale ještě ho nepřidělil, běžné storno obsluhou
   zrušilo případ a uvolnilo rezervaci. Stejně mohlo přehlédnout dříve
   přidělený a následně pouze uvolněný příjem. Obě situace nyní vyžadují
   samostatné finanční vypořádání; samotné uvolnění přídělu není vrácení peněz.
   Regresní test před opravou skončil `Expected rejection`.
2. **Převzetí kusu k výměně obnovovalo nárok na jiný dobropisovaný kus.**
   Objednávka 2 ks → výdej 1 ks → převzetí vydaného kusu k výměně → opravný
   doklad na nevydaný druhý kus. Druhý kus šel přesto vydat, protože výpočet
   odečetl také fyzické převzetí pro výměnu. Nový společný výpočet nároku
   odečítá konečné vratky, nikoli převzetí k dobrovolné výměně. Ochrana platí
   i před rezervací náhrady. Pozdější konečné vrácení náhradní varianty se
   naopak započítá přes doloženou vazbu případu na původní položku.
   Stejný výpočet používá uvolnění nevydaného zbytku. Test před opravou
   selhal na `Exchange reception must not free another financial entitlement`.
3. **Souběžné odebrání role nebo superadmina neblokovalo zápis.** Původní
   `SELECT ... EXISTS(...) FOR UPDATE` uzamkl trenéra, ale poddotaz mohl
   používat starý snímek oprávnění. Skutečný MariaDB test před opravou
   dovolil 4 ze 6 konkurenčních kontrol (sklad/vratky × role/superadmin);
   deaktivaci správně odmítl. Nový sdílený skladový helper čte samostatně
   aktuálního trenéra, granty a superadmina se zámkem. Po opravě všech
   **6 scénářů skutečně čeká na InnoDB LOCK WAIT a odmítne zápis**;
   počet neoprávněných syntetických zápisů je 0.

## Ověření

- `php vendor/phpunit/phpunit/phpunit tests/Integration/ShopFulfillmentTest.php tests/Integration/ShopReturnsTest.php tests/Integration/ShopInventoryTest.php`:
  **52 testů / 547 assertions PASS**. Čtyři nové regresní testy pokrývají
  nepřidělený příjem, uvolněný příděl, výměnu versus dobropis a konečnou vratku náhrady.
- `ShopAuthorizationMariaDbRace.php`: **6 kontrol PASS**, nová DB
  `evidence_shop_auth_ab855cc1`; před opravou `evidence_shop_auth_ba94addd`
  prokázala čtyři neoprávněné syntetické zápisy. Obě DB jsou pouze nové
  vlastněné testovací databáze. [Finální protokol](shop-authorization-race.json).
- `ShopFulfillmentMariaDbRace.php`: **6 kontrol PASS**, dva souběžné scénáře,
  opakovaný výdej a výdej proti stornu. [Protokol](r21-r22-race.json).
- `ShopReturnsMariaDbRace.php`: **19 kontrol PASS**, včetně migrací a dvou
  skutečných souběhů nad množstvím a opakovaným převzetím.
  [Protokol](r23-returns-race.json).
- Skutečné HTTP: výdej/prodej **31**, vratky **33**, sklad **21** kontrol PASS
  (**85 celkem**). [Výdej](r21-r22-http.json), [vratky](r23-returns-http.json).
  Skladový běh `LOCALHOST NEPLATIT R18 f393a185a2` prošel všemi 21 kontrolami;
  jeho stávající nástroj vypisuje výsledek do konzole, nevytváří JSON soubor.
- Šest dotčených PHP souborů prošlo lintem; `git diff --check` bez chyb.

Nový test souběhu ověřuje skutečný port 33426, datový adresář a příznaky
syntetického prostředí před vytvořením databáze. Zámkové kontroly opravnění
jsou testy doménových helperů; nejde o náhradu lidské provozní přejímky.
Žádná nová migrace, změna účetního ledgeru ani automatické vrácení peněz.
