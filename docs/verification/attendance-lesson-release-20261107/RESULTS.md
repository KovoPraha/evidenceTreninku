# P11 katalog a R06 lekce — schválené nasazení, 7. 11. 2026

HOTOVO v rozsahu těchto dvou čtecích optimalizací. Vlastník výslovně zadal
ověřené vhodné změny nasadit, commitnout a implementovat další doložený přínos.
[Plán R06](../../ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md),
[katalog](../../CATALOG_READ_PERFORMANCE_2026-11-07.md),
[předání](../../SESSION_HANDOFF_ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md).

## Merge, CI a nasazení

- Katalog [#364](https://github.com/KovoPraha/evidenceTreninku/pull/364)
  sloučený `c70939d45b53c718083f01110d26b6cf12fb35b0`,
  [deploy 42372002993](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42372002993)
  SUCCESS; [samostatný původní protokol a jeho mez CSP sondy](../catalog-release-20261107/RESULTS.md).
- Další implementace: dávkové aktuální čtení nabídky lekcí,
  [#367](https://github.com/KovoPraha/evidenceTreninku/pull/367) sloučené
  `269684c234698cb40ae661e4080e318d4a05cfd3`.
- Finální integrovaná větev `f5bd650fda4bedb6fbe5a373485f85804561d9b7`:
  [Testy 42372966108](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42372966108)
  PASS, všech pět jobů včetně obou nativních integračních matic.
  Linux **2355 testů / 46183 assertions**, zvlášť unixové hranice 3/45.
  Kvalita 42372966069, dokumentace 42372966050 a CodeQL 42372964140 PASS.
  Požadované branch-protection kontroly dodržené, main aktuální při merge.
- [Deploy 42374036053](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42374036053)
  **SUCCESS** na přesném sloučeném SHA. Před dispatch ověřený vzdálený main,
  po dispatch ověřená shoda headSha běhu. Standardní chráněné workflow;
  `potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`.
  [Bezpečný záznam všech kroků](deployment.json): syntaxe/testy, předletová
  kontrola, vytvoření/ověření zálohy, migrační kontrola, zachování původních VS,
  aktivace, HTTP smoke a úklid kopie konfigurace PASS; import přeskočený.

Žádné nové migrace, změny obchodních dat, pracovní role, přiřazení osob,
platby, aktivace bran, importy nebo rozesílky nebyly součástí této práce.
Schválení UAT se nezapínalo. Stávající provozní přepínače se tímto zadáním neměnily.
Aktuální release vlastní [CURRENT_STATE](../../CURRENT_STATE.md).

## Shodné výsledky a přínos

- Katalog: [480 párových měření](../catalog-read-performance-20261107/RESULTS.md)
  na MariaDB 10.3/11.4, striktně shodné celé karty/ceny/pořadí/menu/počty.
  Syntetických 5000 produktů: **202,18 → 87,13 ms** (10.3),
  **340,46 → 198,57 ms** (11.4); PHP špička **71,59 → 16,82 MiB**.
  Lehký globální průchod zůstává, plné karty jen pro stránku; více malých SQL.
- Lekce: [720 střídavých párů](../attendance-lesson-read-performance-20261107/RESULTS.md),
  kompletní řádky/pořadí a transakční SQL shodné. Cíleně 19/993,
  kombinovaně s katalogem 25/2801 PASS. Aktuální role znovu ověřené po dávce;
  odebrání role/deaktivace/smazání plánů a výjimky pokryté. Bez cache mezi voláními.
  50 nabídek trenéra **153 → 6 SQL**, medián **41,47 → 1,77 ms** (10.3)
  a **19,99 → 0,97 ms** (11.4); superadmin 254 → 9 SQL.
  Transakční smyčka a zápisové služby/uzavření docházky zachované.

Časy jsou měření syntetického serverového čtení, nikoli celé HTTP/browser,
produkční SLA nebo důkaz stejného násobku zrychlení celé stránky.
Paměť helperu lekcí může mírně narůst kvůli mapě nejvýše 50 ID.

## Produkční čtecí kontrola

Před tímto druhým vydáním byl znovu pořízen snímek veřejného katalogu již
opravenou normalizací v2: [before.json](before.json). Po aktivaci
[after.json](after.json): **5/5 HTTP 200**, shodný normalizovaný celý `main`
a navigace, produktová ID/pořadí i počet 222 nabídek. Případy: výchozí,
cena, strana 2, hledání `cyklo` (prázdné), záměrně prázdné hledání.
[Sonda](snapshot.php.txt) odstraňuje skripty, oba náhodné CSP atributy a tokenové
hodnoty. Ukládá pouze veřejná ID, počty a SHA256; žádné HTML/cookies/session.
Pět sériových GETů není výkonnostní zátěžový test.

Tento PASS platí pro katalog před/po nasazení #367, **nenahrazuje neprokazatelný
úplný DOM otisk před prvním nasazením #364**. Jeho původní neúplná normalizace
a zachované neúspěšné vyhodnocení jsou v samostatném katalogovém protokolu.

[Anonymní GET docházky](anonymous-lesson.json) odmítnut přihlášením;
[čtecí sonda](anonymous-lesson.php.txt), bez cookies, POST a ukládání odpovědi.
První sonda měla chybný prefix `/booking/` a vrátila 404; [původní výsledek](initial-wrong-route.json)
zachovaný jako neúspěšný. Ověřená skutečná trasa je `/training_closure_admin.php`.
Přihlášené produkční UI UAT lekcí **NOT RUN**. Ověření nevytvářelo osoby,
role, lekce ani docházku a nepotvrzovalo skutečnou účast.

## WIP, předání a zbývající práce

Původní checkout `C:/xampp/htdocs/evidencePavel`, větev
`codex/fio-auto-reconciliation-recovery`, WIP `kis_rosters_admin.php` a výstupy
ponechané. SHA256 souboru před/po stále
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.
Práce a výsledkové předání v izolovaných worktrees mimo původní checkout.
Staré zastavené datadiry se nemažou; dřívější odmítnutý úklid se neobcházel.

Další P11: omezit načítání 10221 osobních voleb (1,37 MB / cca 6 s v původní
analýze), potom nové celé HTTP/browser, mobil a souběh; další objemy R13/R05.
Tyto větší UI etapy ani celé K0.2 nejsou tímto prohlášené za hotové.
Výsledková dokumentace ve větvi `codex/performance-release-handoff-20261107`;
sdílení přes samostatný dokumentační PR, bez dalšího deploye.
