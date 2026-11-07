# Předání P11 / R13 — zdravotní přehled, 6. 11. 2026

## Zadání a oprávnění

Autonomně optimalizovat zdravotní přehled podle měření, ověřit přínos na
stejných syntetických datech, zachovat chování i rozpracovanou práci.
**Bez produkčních změn, nasazení a aktivací.**

Větev `codex/medical-read-performance-20261106`, kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-medical-performance-20261106`.
Původní kopie `C:/xampp/htdocs/evidencePavel` se nepřepíná; WIP
`kis_rosters_admin.php` má při převzetí SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.
Převzat main `f7c944e6`, zachováno i souběžné předání #351 / `1a0a1888`.
Provozní stav jiné relace nadále určuje [CURRENT_STATE](CURRENT_STATE.md).

## Implementace a ověření

- Běžný seznam nejprve vybere 25 sledování a načte pouze jejich stavová metadata.
- Stavový filtr má stejný kanonický výpočet včetně úplných řetězců nahrazení,
  hranic dat a odvolání. Průchod přenáší minimální metadata, drží historii jednoho
  sledování a dvě stránky ID/stavů; úplné údaje se dočtou jen pro vybranou stránku.
  MySQL buffering i kurzor se řádně obnoví/uzavřou včetně výjimky.
- Výběr osoby hledá a stránkuje po 25, případně drží jednu vybranou osobu.
  Nevylučuje archivní osoby. Kontext výběru je oddělený od filtru seznamu.
  Nativní formuláře fungují bez AJAX; pomocný skript zachovává neodeslaný výběr
  při hledání a stránkování a má platnou CSP nonce.
- Writer, rozhodování, historie a soukromé stahování zachovávají dosavadní chování;
  starší dotčené soubory přeformátované kvůli povinnému stylu.
- SQLite regrese 32 testů / 14 132 assertions PASS, plný PHPStan před úpravou
  streamu a cílená analýza finálního readeru PASS, styl PASS. MariaDB 10.4:
  36 paritních kombinací proti původnímu algoritmu PASS, zachování nastavení PDO.
- Skutečný místní browser: vyhledání osoby #10100, zachování volby při jiném
  hledání a na druhé stránce, původní formulář POST 303 → správný detail 200.
  [Důkazy](verification/medical-read-performance-20261106/browser/checks.json).

[PR #352](https://github.com/KovoPraha/evidenceTreninku/pull/352),
[výsledky, metoda a stav měření](verification/medical-read-performance-20261106/RESULTS.md).
Devět srovnávacích případů místně i párové CI 10.3/11.4 PASS;
[trvalé výsledky a meze](verification/medical-read-performance-20261106/CI-VERIFICATION.md).
Finální stav všech povinných kontrol a začlenění je u PR. Sloučení není nasazení.

## Další postup a zbývající práce

1. Finální srovnání devíti R13 scénářů před/po archivované: 100/1000/10000
   sledování × vše / osoba / Platné, 540 časových vzorků místně; obě CI verze PASS.
   Současný úplný profil má 24 scénářů, historický #349 měl 21. První CI navíc
   odhalilo zastaralý požadavek přesně dvou SQL v integračním assessmentu; opraven
   a doplněn přísnější limit řádků/paměti bez oslabení soukromí či doménové parity.
2. Stav povinných kontrol a začlenění ověřit u PR. Vlastní schémata odstraněná,
   server MariaDB a HTTP zastavené, původní WIP ověřené shodným otiskem.
3. Stavový filtr pro úplný počet stále musí vyhodnotit všechny kandidáty.
   Nezaměňovat úsporu PHP paměti a vrácených řádků za omezený počet řádků
   prohledaných databází. Časové zrychlení netvrdit bez srovnání.
4. Samostatně zbývají R05 metadata/RSVP plánovače, R06 ověřování účastníků,
   další role/filtry a plné HTTP/bootstrap/nav měření výkonu. Celé K0.2 není uzavřené.
5. Produkční nasazení ani UAT nyní neprovádět. P05/P07, export P06, heslo/T07,
   skutečné podklady a souběžný ranní audit zachovávají své předchozí závislosti.

Nepokračovat původní variantou se 200 kandidáty na dávku: u velkého stavového
filtru opakovala příliš mnoho dotazů. Vývojová CSP chyba nového skriptu byla
opravena před finálním browser průchodem; nejde o produkční závadu.

## Aktualizace převzetí 7.11.2026 — větší data a aktuální R05

Tento výše uvedený postup je historický popis konkrétní etapy. R06 a celé HTTP
už jsou hotové, nečekají na novou implementaci. [P11-SCALE předání](SESSION_HANDOFF_MEDICAL_PLANNER_SCALE_2026-11-07.md)
dokládá100k zdravotních sledování,520týdnů a1000členství,2160primárních časů
a540časů aktuálního#374, přímou shodu domén a bezpečný úklid. Starý R05 nález
14554 řádků má nyní ověřenou opravu#374; znovu ji neimplementovat ani nenasazovat.
Dále R13 stavové čtení, řízený Linux100k, složitější historie a mobil/souběh.
Provozní stav výhradněCURRENT; tato relace produkci neměnila.
