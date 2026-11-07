# P11 / R06 — optimalizace docházky 7. 11. 2026

**Implementace a párové měření dokončené, bez nasazení.** Otevření docházky
trenérem na stejných syntetických datech zachovává všechna data i výsledné HTML.
Při 200 účastnících kleslo SQL **244 → 45**, medián měřené části je
**2,30–2,48× rychlejší**. U 10 osob jde o menší přínos **1,07–1,10×**,
u 50 osob **1,34–1,40×**. Objem vrácených dat a špička PHP paměti se nezměnily.
Nejde o celé načtení stránky ani produkční SLA.

[PR #354](https://github.com/KovoPraha/evidenceTreninku/pull/354),
[session handoff](../../SESSION_HANDOFF_ATTENDANCE_READ_PERFORMANCE_2026-11-07.md).
Větev `codex/attendance-read-performance-20261107`, pracovní kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-attendance-performance-20261107`.
Base main `89edfdbee5fbddbe05e0134d94f79ba0cb4143d9` / #353.
Aplikační změna `b98961ae77246f4a9cbd0bdd5015e1f29b79fef6`; měřicí revize
`b639c7e0cfefd26ab4a717b955337b5c6c883cad`, syntetický CI merge
`d96fa34e226830bff1eeb8fddc4dec1ee49a6dbd`. Následné změny doplňují důkazy
a předání, nikoli runtime.

## Implementace a zachované chování

`coachTrainingAssertPeople` nejprve vždy čerstvě ověří pracovní oprávnění.
Mimo transakci ověřuje existenci unikátních ID po dávkách nejvýše 500.
Čte pouze ID, žádná jména, poznámky ani soukromé profily, a mezi voláními
necachuje osoby ani role. Chybějící/neplatná osoba vyvolá stejnou chybu.

V transakci je zachovaná původní jednotlivá kontrola a přesné pořadí SQL,
včetně dosavadních autorizačních zámků. U prázdných vstupů zůstává i původní
prepare. Jde o sdílený validátor; dávkování platí pro jeho volání mimo
transakci. Samotný writer, POST, pořadí doménových guardů, historie, verze,
papírový původ i formulář zůstávají zachované.

## Platné měření před / po

[CI 42352255371](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42352255371),
MariaDB **10.3.39 / 11.4.13**, PHP 8.2.35. Každá databázová verze má párové
porovnání na témže runneru/serveru a stejném migrovaném syntetickém katalogu
**347 tabulek / 160 migrací**. Devět R06 případů: **10/50/200 účastníků ×
1/12/52 revizí**, role trenéra. Pět zahřátí a 30 neinstrumentovaných vzorků
na případ/fázi; SQL/vrácené řádky/EXPLAIN samostatně. Celkem **1080 časových
vzorků R06** v obou verzích a fázích, dalších 36 SQL vzorků.

Původní a nový běh mají úplné stejné pořadí 24 případů R05 → R06 → R13.
R06 nabídka lekcí závisí na předchozím R05 seedu. Datum vytvoření syntetických
revizí je v obou fázích výslovně stejné (`CURRENT_DATE 18:00:00`), protože
HTML tento čas zobrazuje. Obě úpravy se týkají přípravy dat mimo měřený interval,
nikoli normalizace výstupu nebo úpravy aplikace. Neplatná první porovnání
byla odmítnutá, časy nejsou zahrnuté níže; [kalibrace a důkazy](CALIBRATION.md).

| MariaDB | Osob | Revizí | SQL před → po | Řádků před = po | Medián ms | p95 ms | Zrychlení mediánu |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 10.3 | 10 | 1 | 54 → 45 | 290 | 7.53 → 6.85 | 7.86 → 7.11 | 1.10× |
| 10.3 | 10 | 12 | 54 → 45 | 301 | 7.44 → 6.93 | 7.68 → 10.45 | 1.07× |
| 10.3 | 10 | 52 | 54 → 45 | 341 | 7.56 → 6.91 | 8.36 → 7.37 | 1.09× |
| 10.3 | 50 | 1 | 94 → 45 | 410 | 9.75 → 7.08 | 11.09 → 7.52 | 1.38× |
| 10.3 | 50 | 12 | 94 → 45 | 421 | 9.62 → 7.15 | 10.21 → 8.01 | 1.34× |
| 10.3 | 50 | 52 | 94 → 45 | 461 | 9.82 → 6.99 | 12.38 → 7.18 | 1.40× |
| 10.3 | 200 | 1 | 244 → 45 | 860 | 17.56 → 7.23 | 18.02 → 7.75 | 2.43× |
| 10.3 | 200 | 12 | 244 → 45 | 871 | 17.88 → 7.22 | 19.05 → 7.30 | 2.48× |
| 10.3 | 200 | 52 | 244 → 45 | 911 | 17.66 → 7.13 | 18.53 → 7.50 | 2.48× |
| 11.4 | 10 | 1 | 54 → 45 | 290 | 12.03 → 11.17 | 13.22 → 11.84 | 1.08× |
| 11.4 | 10 | 12 | 54 → 45 | 301 | 12.13 → 11.06 | 16.99 → 12.14 | 1.10× |
| 11.4 | 10 | 52 | 54 → 45 | 341 | 12.20 → 11.37 | 14.57 → 12.26 | 1.07× |
| 11.4 | 50 | 1 | 94 → 45 | 410 | 15.41 → 11.39 | 16.59 → 12.03 | 1.35× |
| 11.4 | 50 | 12 | 94 → 45 | 421 | 15.45 → 11.42 | 16.46 → 12.50 | 1.35× |
| 11.4 | 50 | 52 | 94 → 45 | 461 | 15.43 → 11.47 | 16.09 → 12.35 | 1.34× |
| 11.4 | 200 | 1 | 244 → 45 | 860 | 27.55 → 11.96 | 32.10 → 12.90 | 2.30× |
| 11.4 | 200 | 12 | 244 → 45 | 871 | 28.32 → 11.90 | 36.08 → 12.51 | 2.38× |
| 11.4 | 200 | 52 | 244 → 45 | 911 | 28.76 → 12.10 | 32.12 → 12.56 | 2.38× |

SQL je stabilní **45** přes všechny tři velikosti a historie tohoto fixture.
Úspora SQL 16,67 / 52,13 / 81,56 % podle počtu osob. Časové mediány se
zlepšily ve všech 18 párech, p95 není univerzálně lepší: např. 10 osob /
12 revizí na 10.3 má 7,68 → 10,45 ms. Sdílený runner neposkytuje časové SLA.
Špička přírůstku PHP paměti je před/po shodná: podle počtu osob a revizí
269 696–315 488 B. Nejde o celkovou paměť procesu ani řádky prohledané DB.

## Parita, bezpečnost a regrese

- Místně **29 testů / 206 assertions PASS**, [JUnit](targeted-tests.xml).
  Cílený PHPStan, styl a baseline budget PASS: 497 položek / 2146 nálezů,
  bez nových nebo zvětšených výjimek. Žádné závislosti ani migrace se neměnily.
- Plné CI PHPUnit **2315 testů / 42 846 assertions PASS**, unixové hranice
  **3 / 45**. Ověřují se i existující autorizační souběhy, opravy docházky,
  papírový původ, cizí trénink a ostatní integrační kontrakty. Finální úplný
  CI/merge je u PR; tento dokument nedokládá produkční UAT ani nasazení.
- Na každé MariaDB **84 kombinací vstupů/rolí** proti zmrazenému validátoru:
  prázdné, duplicity, neplatné/chybějící osoby, 10/50/200/1200 osob, trenér,
  vedoucí, superadmin, neaktivní účet, chybná role a nepřihlášený actor.
- Čerstvé smazání a opětovné vložení osoby i odebrání role ověřené.
  V transakci přísná shoda SQL/parametrů/pořadí a autorizačních FOR UPDATE
  pro čtyři úspěšné i chybové vstupy. Žádné zámky se nepřidávaly/nevypouštěly.
- Samostatný validátor pro 200 osob **202 → 3 SQL** (včetně dvou čerstvých
  kontrol role); pro 1200 osob **5 SQL**, dávky nejvýše 500 parametrů.
- Všech devět GET párů na obou verzích: celá doménová data **i celé HTML**
  shodné; shoda instrumentovaného/neinstrumentovaného výstupu, READ ONLY,
  čtyři autorizační negativa a odstranění schémat PASS. Požadavek parity
  nebyl během kalibrací oslabený.
- Stávající párové R05/R13 měření a parita, všech 24 aktuálních případů PASS.
  Komparátor pro tento fixture hlídá nejvýše **45 SQL / 1000 vrácených řádků**,
  snížení SQL a nezvýšení počtu řádků. Časový limit se na runneru nevynucuje.

## Trvalé důkazy a reprodukce

Reporty [10.3](ci-10.3/attendance-comparison.json) /
[11.4](ci-11.4/attendance-comparison.json), parita
[10.3](ci-10.3/attendance-read-parity.json) /
[11.4](ci-11.4/attendance-read-parity.json).
V obou adresářích jsou `attendance-before` a `after`: celý souhrn 24 případů,
cleanup a devět surových R06 profilů 07–15 před/po, všechny vzorky, SQL,
parametry, EXPLAIN a otisky zdrojů. R05/R13 mají vlastní comparison/parity JSON.

[Zmrazený původní měřič](before-harness.php.txt), SHA256 po LF
`847888e87e6a479cad6cf922f892021ef40528a4bd9f454ce728f4d9ebc5fde5`,
se od měřiče v `89edfdbe` liší pouze dvěma řádky deterministického času v seedu.
Aktuální měřič navíc zaznamenává otisky dvou služeb mimo měřený interval.
Měřená těla jsou beze změny; HTML se normalizuje jen původní procesní CSP nonce
a CRLF/LF. `source_sha` identifikuje skutečný checkout, historické pole
`baseline_application_sha` je původ původního měřidla, nikoli base této opravy.

Reprodukce přes testovací workflow PR. Vlastní localhost testovací server,
potvrzení SYNTHETIC-ONLY, explicitní port/datadir, ověření skutečné identity,
vlastní náhodné schéma a finally DROP. Měřit úplné pořadí matice a porovnat
`attendance-before` / `after`; nepoužívat samostatné `--route R06` jako párový
baseline k úplnému následnému profilu. Žádný produkční config nebo dump.
Místně nevznikla MariaDB ani HTTP služba; lokální chování ověřené na SQLite.

## Zbývá a hranice pokračování

1. Finální kontroly a začlenění ověřit u PR #354; sloučení není nasazení.
2. Samostatně plné HTTP/bootstrap/navigace a browser výkon celé stránky,
   další role a plánem propojená docházka. Celé K0.2 zůstává otevřené.
3. Historické upozornění R05 s vybraným hierarchickým filtrem stále čte
   kandidátní ID/data. Další objemy osob/týmů/historie posuzovat měřením.
4. Produkční nasazení a přihlášené produkční UAT nebyly provedené; nyní je
   nezapínat. [CURRENT_STATE](../../CURRENT_STATE.md) se touto relací nemění.

Původní WIP `kis_rosters_admin.php` má shodný SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.
Starší runtime adresáře se nemazaly. P05/P07, export P06, heslo/T07 a ranní
audit jiné relace mají původní závislosti; žádné jejich běhy se neopakovaly.
