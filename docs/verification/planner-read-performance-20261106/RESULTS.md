# P11 / R05 — optimalizace plánovače 6. 11. 2026

**Implementace a ověření dokončené, bez nasazení.** Stejná syntetická data
potvrdila shodné počty účastníků, odpovědi, filtry, upozornění i výsledné HTML.
Měřená část plánovače má o 75–86 % méně SQL a medián je 3,32–6,07× rychlejší.
Nejde o měření celého načtení stránky na produkci.

[PR #353](https://github.com/KovoPraha/evidenceTreninku/pull/353),
[session handoff](../../SESSION_HANDOFF_PLANNER_READ_PERFORMANCE_2026-11-06.md).
Větev `codex/planner-read-performance-20261106`, vlastní kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-planner-performance-20261106`.
Výchozí main `84f5c7a270d2bcedd9c1bd6c627f485d4ade7c6a` / #352.
Měřená implementace je commit `a51934142177d668612986e26b2017c4b0d5e30d`;
CI checkout byl syntetický PR merge `5e93ab532d567f700162ef89a1f311229e9bd931`.
Následné změny doplňují důkazy a strukturální testovací limit, nikoli runtime.

## Co se změnilo a co se zachovalo

- RSVP souhrny plánovače se čtou v dávkách. Čtou pouze ID, data platnosti
  a odpovědi; nečtou jména ani poznámky jen kvůli zobrazení počtů.
- Živé členství a hierarchie se vyhodnocují ke dni každého plánu. Zachovaná
  deduplikace, překryvy, hranice platnosti a ignorování odpovědí nepříslušných osob.
  Snapshot očekávaných osob nenahrazuje dosavadní kanonické živé členství.
- Metadata tabulek jsou sdílená jen v explicitním scope jednoho čtení.
  Role, oprávnění ani data se necachují mezi požadavky/voláními.
- Historické upozornění bez filtru soupisky používá COUNT/MIN místo načtení
  každého starého plánu. S vybranou soupiskou běží dosavadní datované filtrování.
- POST změny, kopírování, autorizace a sdílený `trainingRsvpPlanSummaries`
  zůstávají zachované. V transakci nový reader použije původní cestu včetně zámků.

## Stejná data před / po

CI [42293522435](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42293522435)
PASS na MariaDB **10.3.39 / 11.4.13**, PHP 8.2.35, všech 11 kontrol PR PASS.
Každá verze databáze měří původní a novou aplikaci na témže runneru/serveru,
stejný migrovaný syntetický katalog **347 tabulek / 160 migrací**.
Šest R05 případů: 1/12/52 týdnů, trenér 7 plánů / vedoucí 14 plánů,
20 způsobilých osob. Pět zahřátí, 30 samostatných neinstrumentovaných vzorků
na případ a fázi; SQL/vrácené řádky/EXPLAIN v odděleném procesu.
Celkem pro R05 **720 časových vzorků** v obou databázích a fázích.

| MariaDB | Týdnů | Role | SQL před → po | Vrácené řádky | Medián ms | p95 ms | Zrychlení mediánu |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 10.3 | 1 | trenér | 112 → 28 | 414 → 97 | 26.47 → 7.65 | 27.29 → 7.92 | 3.46× |
| 10.3 | 1 | vedoucí | 203 → 28 | 782 → 125 | 45.70 → 7.89 | 50.12 → 8.07 | 5.79× |
| 10.3 | 12 | trenér | 112 → 28 | 491 → 97 | 26.31 → 7.70 | 27.60 → 7.98 | 3.42× |
| 10.3 | 12 | vedoucí | 203 → 28 | 936 → 125 | 45.78 → 8.00 | 46.94 → 8.51 | 5.72× |
| 10.3 | 52 | trenér | 112 → 28 | 771 → 97 | 26.52 → 7.68 | 27.13 → 8.26 | 3.45× |
| 10.3 | 52 | vedoucí | 203 → 28 | 1496 → 125 | 47.16 → 8.02 | 52.02 → 9.72 | 5.88× |
| 11.4 | 1 | trenér | 112 → 28 | 414 → 97 | 42.45 → 12.36 | 43.55 → 12.58 | 3.43× |
| 11.4 | 1 | vedoucí | 203 → 28 | 782 → 125 | 75.61 → 12.45 | 78.86 → 12.90 | 6.07× |
| 11.4 | 12 | trenér | 112 → 28 | 491 → 97 | 42.66 → 12.60 | 43.35 → 12.71 | 3.39× |
| 11.4 | 12 | vedoucí | 203 → 28 | 936 → 125 | 75.81 → 12.94 | 86.04 → 13.07 | 5.86× |
| 11.4 | 52 | trenér | 112 → 28 | 771 → 97 | 43.13 → 12.99 | 44.16 → 13.22 | 3.32× |
| 11.4 | 52 | vedoucí | 203 → 28 | 1496 → 125 | 77.17 → 13.19 | 79.89 → 13.55 | 5.85× |

Počet SQL/řádků po změně je stabilní přes 1/12/52 týdnů u tohoto fixture.
Přírůstek špičky PHP paměti při 52 týdnech: trenér 526 984 → 388 424 B,
vedoucí 801 952 → 423 896 B, shodně na obou verzích; u jednoho týdne je úspora
paměti menší. Nejde o celkovou paměť procesu ani počet řádků prohledaných DB.

## Parita a regrese

- [Místní JUnit](targeted-tests.xml): **38 testů / 584 assertions PASS**;
  planner JavaScript **4/4 PASS**, cílený PHPStan a styl PASS.
- Plné CI PHPUnit **2310 testů / 42 737 assertions PASS**, unixové soukromé
  úložiště dalších **3 / 45**, integrační smoke obou MariaDB, CodeQL, styl,
  PHPStan i dokumentace PASS. Baseline se snížila o jeden starý PDO nález.
- Na každé MariaDB **11 souhrnových + 216 kombinací filtrů** se rovná původnímu
  algoritmu. Ověřené čerstvé změny mezi čteními i shodné SQL a FOR UPDATE
  při transakci. Samotný souhrn 11 SQL, upozornění 1 SQL / 1 řádek.
- Šest párů na každé MariaDB: striktní shoda celých doménových dat a HTML;
  shoda instrumentovaného/neinstrumentovaného výsledku, READ ONLY sessions,
  čtyři negativní autorizační scénáře a odstranění všech vlastních schémat PASS.
- Stávající párové měření/parita R13 a úplný 24případový profil zůstaly PASS.
- Komparátor navíc hlídá pro tento fixture nejvýše **30 SQL / 130 řádků**
  a striktní zlepšení proti původní verzi. Čas na sdíleném CI není pevné SLA.

## Důkazy a reprodukce

Trvalé reporty [10.3](ci-10.3/planner-comparison.json) a
[11.4](ci-11.4/planner-comparison.json), parita
[10.3](ci-10.3/planner-read-parity.json) /
[11.4](ci-11.4/planner-read-parity.json).
V obou adresářích jsou celé souhrny, cleanup a šest surových R05 profilů
před/po: všech 30 vzorků, SQL/parametry/EXPLAIN, doménová data a otisky zdrojů.
`after/summary.json` zachovává i souhrny všech 24 scénářů. Oddělené
`medical-comparison.json` a `medical-read-parity.json` dokládají zachování R13.

[Zmrazený měřič původní aplikace](before-harness.php.txt), SHA256
`fb4e20677b36124429392b104e13a29b6280e8bd1937c6c0ce304029da9c50a9`,
liší se od původního pouze přidaným HTML SHA po sjednocení CRLF/LF **mimo
časový/paměťový interval**. Kromě této konvence a původní procesní CSP nonce
se HTML nenormalizuje. Pro reprodukci použít testovací workflow PR; vyžaduje
potvrzení SYNTHETIC-ONLY a ověření portu/datadir, vlastní náhodné schéma,
nikoli produkční config/dump. Komparátor spustit na `planner-before` a `after`.

## Zbývá a provozní hranice

1. R06: podle původního měření omezit jednotlivé kontroly účastníků docházky,
   opět s paritou a stejnými syntetickými daty. Samostatné navazující zadání.
2. Plný HTTP/bootstrap/navigace a další role/filtry nejsou tímto měřením pokryté.
   Měřeno skutečné GET tělo a šablona bez společného bootstrapu a navigace.
3. U hierarchického filtru soupisky upozornění stále načítá kandidátní ID/data.
   RSVP čtení roste s vybranými plány a relevantním členstvím/hierarchií;
   nejde o univerzální konstantní náklady pro libovolně velký klub.
4. Produkční nasazení a přihlášené produkční UAT nebyly provedené. Sloučení
   není nasazení; produkční stav určuje [CURRENT_STATE](../../CURRENT_STATE.md).

Původní WIP `kis_rosters_admin.php` zachované, SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.
Místní databáze/HTTP se pro tuto relaci nezakládaly, starší runtime adresáře
se nemazaly. P05/P07, export P06, heslo/T07 a souběžný ranní audit beze změn.
