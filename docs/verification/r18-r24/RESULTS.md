# Přejímka R18–R24 — izolované prostředí

Ověření ze dne 22. 10. 2026. Platí pro lokální větev
`codex/inventory-r18-r24-20261022`, navazující na `fd402b6d9e12a86d932f376c8eaff76877d9a029`.
Testy nejsou produkční UAT ani schválení účetní metodiky.

## Doložené kontroly

| Oblast | Výsledek a důkaz |
|---|---|
| Celá regresní sada | **1 403 testů / 29 526 kontrol PASS**, bez chyb, selhání a přeskočených testů. [Protokol](phpunit.json). |
| Skladové HTTP úkony | **21 scénářů PASS**: ověřený počátek, příjemka, opakování, cena pouze hospodářem, nezávislá inventura, CSRF a odebrané oprávnění. [Protokol](inventory-http.json). |
| Prodej a předání přes HTTP | **31 scénářů PASS**: prodej bez fiktivního účtu/e-mailu, platná cena, finanční role, částečný výdej, vystavení skutečné testovací faktury a opravného PDF, uvolnění nevydaného zbytku a stav jeho vypořádání. [Protokol](r21-r22-http.json). |
| Vratky a výměny přes HTTP | **33 scénářů PASS**: fyzické převzetí, blokace, vlastnictví reklamovaného kusu, výměna velikosti, navazující reklamace nové varianty a role. [Protokol](r23-returns-http.json). |
| Skladová PDF a účetní export přes HTTP | **16 scénářů PASS**: soukromý archiv, otisky, opakování, odmítnutí cizí role/CSRF, neznámá cena, náhradní revize po doplnění ceny a nezměněné staré PDF/ZIP. [Protokol](r18-r24-stock-export-http.json). |
| Souběh skladu | **9 kontrol PASS**: skuteční dva pracovníci MariaDB v `LOCK WAIT`, poslední kus, opakovaná příjemka a dvě konkurenční vratky posledního vrátitelného kusu. [Protokol](inventory-race.json). |
| Souběh výdeje | **6 kontrol PASS**: opakovaný výdej vznikne jednou; výdej proti stornu nevrátí vydané kusy do skladu. Doloženo skutečné čekání dvou transakcí. [Protokol](r21-r22-race.json). |
| Souběh vratek | **19 kontrol PASS**: limit vrátitelného množství a opakované převzetí jednoho případu nevytvoří další pohyb. [Protokol](r23-returns-race.json). |
| Konzistentní export | **11 kontrol PASS**: nezávislé DB spojení zapíše příjemku mezi čtením pohybů a dávek; export zachová jeden snímek. Negativní kontrola s READ COMMITTED prokáže smíšený stav bez ochrany. [Protokol](stock-export-snapshot-mariadb.json). |
| Obnova DB a souborů | **288 tabulek, 2 triggery, všech 16 nových tabulek, 65 soukromých souborů, 42 ověřených souborových odkazů**; u **14 variant** souhlasí součet pohybů, fyzický stav a FIFO dávky. Obnova do nového schématu a nové soukromé složky. [Protokol](inventory-backup-restore.json). |
| PDF a XLSX | Vykreslené české PDF bez přetečení, 10 listů XLSX, skladové počty jako čísla a žádné aktivní vzorce. Fyzické PDF neprozrazuje pořizovací ceny. [Metadata](r18-r24-artifact-qa.json), [PDF](r18-r24-stock-sample.pdf), [náhled](r18-r24-stock-sample.png). |
| Syntaxe | **54 PHP souborů PASS**. [Protokol](php-lint.json). |
| Migrace | **125 aktuálních migrací**, čtyři nové aplikované pouze v syntetickém prostředí. Zálohovací kontrakt `2026-10-22.4`. |

HTTP část má dohromady **101 scénářů**. Počty HTTP, souběhů a jednotkových testů
se nesčítají do jednoho čísla. Obnovované soubory a záznamy jsou syntetické.

## Opravy z integrace a nezávislé revize

- Starý skladový editor, jednorázový import ani převod na kroužek nesmějí
  přepsat převzatou skladovou historii.
- Souběžná vratka používá po čekání na zámek aktuální čtení součtu vrácených
  kusů. Výchozí snímek REPEATABLE READ nesmí připustit druhý vrácený kus.
- Vystavené, rozpracované číslované i nevydané opravné doklady se zahrnují do
  kontroly množství k plnění. Obecná cenová sleva s nulovým množstvím sama
  neblokuje oprávněný výdej.
- Částečná skutečná úhrada brání automatické expiraci a provoznímu stornu.
  Nevydaný zbytek má samostatné doložené uvolnění bez fiktivního výdeje či úhrady.
- Kladný doplatek výměny lze pokrýt pouze skutečným příjmem, nikdy záporným
  výdajem. Kontrola výměny ověřuje i původní peníze, uvolnění a skutečné vratky.
- Zrušení již fakturované výměny vyžaduje opravu doplatku a skutečné vrácení
  přijatých peněz. Samotné označení finančního případu nestačí.
- Navazující vratka náhradní varianty zachovává její cenu/SKU a umožňuje
  dohledatelné vazby na více původních opravných dokladů.
- Rozpočet původního kupónu je celočíselný a vychází z historického snímku,
  včetně kombinací zboží a služeb. Neznámý historický rozpis se neodhaduje.
- Skladové formuláře používají skutečné položky objednávek a Kč, společné
  zabezpečené styly a CSP. Uživateli se nepředkládá JSON ani cena v haléřích.

## Opakování

V izolované pracovní kopii s běžícím sandboxem:

```powershell
$env:APP_HOST='localhost'
& C:/xampp/php/php.exe vendor/phpunit/phpunit/phpunit
& C:/xampp/php/php.exe tests/Support/ShopInventoryHttpAcceptance.php
& C:/xampp/php/php.exe tests/Support/ShopFulfillmentHttpAcceptance.php
& C:/xampp/php/php.exe tests/Support/ShopReturnsHttpAcceptance.php
& C:/xampp/php/php.exe tests/Support/AccountingStockHttpAcceptance.php
& C:/xampp/php/php.exe tests/Support/ShopInventoryMariaDbRace.php
& C:/xampp/php/php.exe tests/Support/ShopFulfillmentMariaDbRace.php
& C:/xampp/php/php.exe tests/Support/ShopReturnsMariaDbRace.php
& C:/xampp/php/php.exe tests/Support/AccountingStockSnapshotMariaDbTest.php
```

Obnova navíc vyžaduje novou syntetickou zálohu v runtime `backups-r18-r24`
a `tests/Support/InventoryBackupRestore.php`. Skript nikdy neobnovuje přes
původní DB/soubory. HTTP prodejní test dočasně nastaví pouze smyšlený IBAN
v izolovaném konfiguráku a obnoví jeho původní bajty; neposílá platby ani zprávy.

Skutečná inventura, nastavení účetní, převzetí historických objednávek, provozní
přejímka a nasazení nebyly provedeny. Export stále přiznává neúplnost celého
oddílu do doplnění výdajů, záloh a odměn v navazujících balících.
