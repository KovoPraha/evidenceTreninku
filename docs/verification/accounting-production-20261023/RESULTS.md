# Nasazení účetních oprav a kontrola bez prohlížeče

23. 10. 2026. Na výslovné zadání vlastníka nasazeny ověřené účetní opravy PR #184.
Testovací obchodní a účetní zápisy výhradně v izolovaných databázích. Produkční
kontroly pouze čtecí; žádné vystavení faktury, vytvoření exportu, úhrada, vratka
ani odeslání podkladů účetní.

## Nasazení

- Release **ebc3081cbee3a1b38f75bcc2989e4bf6800b1cb9**.
- [Chráněné nasazení 40025423618](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40025423618): **SUCCESS**.
- Deploy znovu spustil **1669 testů / 33341 assertions**, audit závislostí a lint.
- Před aktivací ověřena záloha **330 tabulek**, konfigurace a soukromé úložiště.
  Konfigurace úložiště zůstala zachovaná, žádné přesouvání příloh. Běžná dočasná
  souborová sonda úložiště se vytvořila a odstranila; nejde o obchodní testovací data.
- Migrace: **pending=[]**, žádná nová databázová migrace. Dosavadní VS: **unchanged=true**.
- Import kroužků vypnutý. Žádné zapnutí platebních/bankovních integrací.
- HTTP smoke **200**; runner měl síťový timeout, proto proběhl standardní SSH/HTTP
  fallback ze serveru. Nezávislé HTTP ověření z pracovní stanice také **200**.
- Čtecí diagnostika vrátila přesné SHA a run ID z živého release. SHA-256 všech tří
  účetních souborů souhlasí s bajty Git blobů tohoto commitu, nikoli s Windows CRLF
  pracovní kopií. Důkaz: `runtime-hash-proof.json`.

Původní main CI 40024592961 skončilo na HTTP 502 Packagistu; opakování téhož
commitu prošlo. Kontrola závislostí nebyla vypnutá ani obejitá.

## Produkce: co bylo skutečně zkontrolováno

[Databázové invarianty 40025941556](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40025941556):
**16 kontrol, všechny bez porušení, žádné přeskočené schema**. Zahrnují objednávky,
řádky, částky, vazby a duplicity plateb i základní vazby osob a tréninků.

[Účetní audit 40026086361](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40026086361):
**SUCCESS**, konzistentní databázová transakce READ ONLY. Výstup pouze souhrnné
počty, výsledky kontrol, release a otisky kódu. Diagnostické soubory běžely mimo
webroot přes chráněný workflow a po dokončení se odstranily.

| Evidence | Skutečný počet |
| --- | ---: |
| Objednávky | 8 |
| Účetní doklady | 0 |
| Účetní peněžní pohyby | 0 |
| Příděly příjmů | 0 |
| Rozhodnutí a výplaty účetních vratek | 0 |
| Archivované účetní exporty | 0 |

Všech 14 účetních kontrol vrátilo nulu nesrovnalostí, ale prázdná evidence
**není produkční důkaz průchodu doklad–export**. V produkci nebylo co ověřovat
na úrovni existujících PDF/ZIP; počet zkontrolovaných PDF a položek archivů je 0.
Neodvozujeme z osmi objednávek povinnost zpětně automaticky vystavit doklady.

Samostatné HTTP GET bez přihlášení ověřily přístupové hranice: administrace
objednávek/exportů/vratek přesměrují na login, doklady vrátí 403, soukromá release
metadata mají 403. Stahování dokladů a exportů bez oprávnění také odmítnuto.
Nevznikly žádné testovací objednávky ani účetní události stažení/předání.

## Izolované ověření návaznosti a nové diagnostiky

PR #185 přidal opakovatelnou čtecí diagnostiku, nikoli další nasazení aplikace.
Pomocný skript vyžaduje CLI a potvrzení, nepoužívá aplikační bootstrap/db.php,
nevolá generování ani stahování dokladů a archiv otevírá READONLY.

- [CI 40025835881](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40025835881):
  **1674 testů / 33371 assertions**, MariaDB **10.3 i 11.4 PASS**.
- [CodeQL 40025833503](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40025833503): PASS.
- Lokálně **18 cílených testů / 472 assertions** a nativní MariaDB smoke PASS.
- Dvě děti, zboží, sleva, dílčí úhrady, přeplatek, faktura PDF, export XLSX/ZIP,
  cenová oprava, vratka a druhá neměnná revize exportu prošly aplikačními funkcemi.
- Záměrně poškozený snapshot, PDF, částka vratky, ZIP a vnitřní položka archivu
  za správným vnějším hashem byly odhaleny. Pouze jednorázová izolovaná fixture.
- Po obnově fixture audit znovu prošel; počty událostí dokladů a exportů se
  čtecí diagnostikou nezměnily. Testy ověřují též chybějící schema, odmítnutí
  transakce volajícího a zachování původního režimu připojení.

## Meze a další provozní krok

Opravy jsou nasazené a technicky ověřené. Uživatelská prohlížečová přejímka,
schválení účetní metodiky a počátečních stavů ani převzetí účetní tím nevznikají.
Export nadále oprávněně nese označení NEÚPLNÝ do jejich potvrzení. Při prvním
skutečném vystavení dokladu/exportu lze zopakovat `overit-ucetni-vazby` a ověřit
už také existující produkční PDF a archivy. Kvůli testu je nezakládáme.
