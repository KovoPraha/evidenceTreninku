# Předání P11 / R05 — plánovač, 6. 11. 2026

## Zadání a oprávnění

Autonomně optimalizovat plánovač podle měření, ověřit přínos na stejných
syntetických datech, zachovat chování a rozpracované změny.
**Bez produkčních změn, nasazení a aktivací.**

Větev `codex/planner-read-performance-20261106`, vlastní pracovní kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-planner-performance-20261106`.
Výchozí main `84f5c7a270d2bcedd9c1bd6c627f485d4ade7c6a` / #352.
Původní kopie `C:/xampp/htdocs/evidencePavel` se nepřepíná. WIP
`kis_rosters_admin.php` při převzetí i závěrečné kontrole SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.

## Výsledek a ověření

Hotové dávkové RSVP souhrny plánovače a COUNT/MIN pro historické upozornění
bez filtru soupisky. Datum členství/hierarchie, překryvy, odpovědi, všechny
filtry a výsledné HTML zachované. Metadata sdílená pouze v explicitním čtecím
scope, data/role se necachují. Sdílené původní RSVP čtení ani POST se nemění;
v transakci se používá původní cesta se stejnými kontrolami a zámky.

[PR #353](https://github.com/KovoPraha/evidenceTreninku/pull/353),
[trvalé výsledky, surové profily a přesná metoda](verification/planner-read-performance-20261106/RESULTS.md).
Měřená implementace `a5193414`, CI syntetický merge `5e93ab53`;
[CI 42293522435](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42293522435)
všech 11 kontrol PASS. Následná dokumentační revize přidává důkazy a limit
30 SQL / 130 řádků pro stejný fixture; její finální CI/merge ověřit u PR.

- Stejné 1/12/52 týdny × trenér/vedoucí, párově na MariaDB 10.3.39/11.4.13,
  pět zahřátí a 30 vzorků/fázi/případ: **720 časových vzorků R05** celkem.
- SQL **112/203 → 28**, vrácené řádky při 52 týdnech **771/1496 → 97/125**.
  Medián měřeného GET těla/šablony **3,32–6,07× rychlejší**, nikoli HTTP SLA.
- Na každé DB všech šest párů: celá data i HTML shodné; 11 souhrnových
  a 216 filtrových paritních případů PASS, čerstvost a transakční zámky PASS.
- Místně 38 testů / 584 assertions, JavaScript 4/4, styl/PHPStan PASS.
  Plné CI 2310 testů / 42 737 assertions, unixové hranice 3/45,
  smoke obou MariaDB, CodeQL a dokumentace PASS. R13 parita/měření zachované.
- READ ONLY, autorizační negativa a odstranění syntetických schémat PASS.
  Místní DB/HTTP pro tuto relaci nevznikly, starší runtime adresáře se nemažou.

## Zbývající práce a bezpečné pokračování

1. Stav finálního povinného CI a začlenění ověřit u PR #353. Začlenění
   samo neznamená vydání na produkci; aktuální release jen v
   [CURRENT_STATE](CURRENT_STATE.md), který tato relace nemění.
2. Samostatně R06: dávkové ověřování účastníků podle původního měření.
   Navázat původním fixture a striktní paritou, zachovat zápisové guardy.
3. Plné HTTP/bootstrap/navigace, další role a výkonnost hierarchického filtru
   dosud nezměřené. Při filtru soupisky historické upozornění stále čte
   kandidátní ID/data; nejde o dokončenou optimalizaci všech možných filtrů.
4. Větší počty osob/týmů a širší datová období posoudit podle měření.
   Vrácené řádky nejsou počet řádků prohledaných DB a přírůstek špičky PHP
   není celková paměť procesu. Celé K0.2 zůstává otevřené.
5. Produkci ani nasazení R05/R13 nezapínat bez dalšího zadání. P05/P07,
   export P06, heslo/T07 a souběžný ranní audit mají své původní závislosti.

[Jediný průběžný registr práce](WORK_PLAN_CURRENT.md),
[předchozí R13 předání](SESSION_HANDOFF_MEDICAL_READ_PERFORMANCE_2026-11-06.md).

## Aktualizace převzetí 7.11.2026 — větší data a aktuální R05

Tento výše uvedený postup je historický popis konkrétní etapy. R06 a celé HTTP
už jsou hotové, nečekají na novou implementaci. [P11-SCALE předání](SESSION_HANDOFF_MEDICAL_PLANNER_SCALE_2026-11-07.md)
dokládá100k zdravotních sledování,520týdnů a1000členství,2160primárních časů
a540časů aktuálního#374, přímou shodu domén a bezpečný úklid. Starý R05 nález
14554 řádků má nyní ověřenou opravu#374; znovu ji neimplementovat ani nenasazovat.
Dále R13 stavové čtení, řízený Linux100k, složitější historie a mobil/souběh.
Provozní stav výhradněCURRENT; tato relace produkci neměnila.
