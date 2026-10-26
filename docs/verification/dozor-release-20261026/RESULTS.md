# Nasazení oprav dozoru F1–F3 — 26. 10. 2026

## Rozsah a stav

Vlastník výslovně povolil sloučení a nasazení. Práce probíhá v izolovaných
worktrees mimo původní checkout; cizí WIP zůstává nedotčené. Aktivace integrací,
import skutečných dat a schválení plného UAT nejsou součástí tohoto vydání.

Všech pět PR je sloučeno a nasazeno jako
`fc0d3779306e70362e013f088f30974725bbf8ff`.
[Deploy 40330758548](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40330758548)
skončil SUCCESS; živá identita byla následně ověřena pouze čtením.

## Sloučené změny

Chráněný main vyžaduje lineární historii, proto proběhly squash merges.
Každý další PR byl přesměrován na aktuální main, přijal předchůdce a prošel
novým CI. Při aktualizacích byla ověřena shoda stromu souborů s prověřeným headem.

| PR | Head bezprostředně před merge | Výsledný commit main |
| --- | --- | --- |
| #196 | `88eb7a2b249c038d2fb1737b518a5383c263aa8b` | `4684503ccbf5bc271fba20bcc50582afc1f93534` |
| #199 | `a64e837dd0b41e3f72656f1900aabc98ae81d7f7` | `545fdcda6b28a58e0d64ffd90bc0bb189a9441a4` |
| #197 | `7d9017554869c28b975403126021dea15ff217ad` | `48df8d9628e2efdda539193d9e4845bcf842eaac` |
| #198 | `8a94b30c0c2293c6a7c16c15969716853af59e4a` | `099a9e6e4d556aca97d77716ab937d45cefa8f0e` |
| #200 | `2ccf35aab35f20a0193e2c57c5fb3def0529c290` | `fc0d3779306e70362e013f088f30974725bbf8ff` |

`git diff 65a61b5bb23f5966c02ed6337fe5d5ccdea9e524 fc0d3779306e70362e013f088f30974725bbf8ff`
je prázdný: finální main má přesně obsah dříve prověřeného společného stavu.
Konflikty posledního merge byly jen v deploy výjimkách a průběžných zápisech;
finální obsah zůstal zachovaný. [Implementace a negativní zkoušky](../../DOZOR_F1_F3_2026-10-26.md).

## CI finálního main

Všechny následující běhy patří `fc0d3779306e70362e013f088f30974725bbf8ff`:

- [Testy 40330507391](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40330507391):
  1 748 testů / 35 593 assertions PASS; MariaDB 10.3/11.4 PASS;
  unixové symlinkové regrese 3 testy / 45 assertions, bez přeskočení.
- [PHP quality 40330507279](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40330507279):
  PHPStan 0 chyb, všech 10 očekávaných negativních nálezů kontraktu zachováno,
  sweep 32 veřejných / 2 CLI / 237 chráněných / 0 chyb;
  navazující regrese 34 testů / 2 257 assertions PASS. Styl se spouští jen pro PR:
  [poslední head #200, 40330206439](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40330206439)
  prošel kontrolou stylu; následný merge má identický strom souborů.
- [CodeQL 40330508180](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40330508180)
  a [dokumentace 40330507231](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40330507231): PASS.

## Nasazení

Protected workflow bylo spuštěno z main s `potvrzeni=NASADIT`,
`uat_schvaleno=false`, `importovat_krouzky=false`. Jeho head byl před aktivací
ověřen proti uvedenému SHA. Ochrany větve ani prostředí se neměnily.

- Záloha `evidence_2026-10-26_132959_603f0a19.sql.gz`: **336 tabulek, 2 triggery**,
  SHA-256 `65a52411198376b1624f7d8fca347227550b919a22b1cd2014893c94cba8551d`.
- Migrations apply i check: `current=true`, `pending=[]`; katalog 140 migrací.
  Samostatný počet záznamů ledgeru je 141 a zůstal beze změny.
- Dosavadní variabilní symboly: `unchanged=true` (8 plateb, oba sledované
  registry stálých VS prázdné). Import katalogu byl přeskočen.
- Soukromé úložiště zachovalo konfiguraci; žádné přílohy nebyly přesunuty.
  Cleanup nic neodstranil; migrace příloh: candidates=0, missing=2, errors=0.
- Aktivace PASS, soukromý klíč přes HTTP 403, přímý veřejný smoke z runneru 200.
  Kopie produkční konfigurace z release byla závěrečným krokem odstraněna.

[Strojový výtah deploy důkazů](deployment-verification.json) neobsahuje dump,
konfiguraci, klíče ani obsah soukromých příloh.

## Produkční ověření

- Anonymní GET přes ověřené TLS, bez cookies a následování redirectů:
  **24 / 24 PASS**. Index, login a CSS 200; oba exporty 302 na `login.php`
  s přesně prázdným tělem; šest JSON endpointů 403. Ebook, outputs, tools,
  PHPStan konfigurace/cache, tečkové cesty včetně vnořené a URL-encoded tečky
  a syntetické ACME cesty 403. Sonda neukládá těla odpovědí.
  [Jednotlivé URL a výsledky](post-http.json).
- [Čtecí dopad 40331347090](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40331347090):
  živé SHA i run ID odpovídají deployi, UAT false. Konfigurace má totožný hash;
  Fio import/automatické potvrzování, SumUp, Stripe, Velocota vypnuté,
  Packeta flag nedefinovaný. KIS canonical write zůstává false.
  Sledované nové tabulky, migrační ledger a stav příloh beze změny.
  [Porovnání před/po](release-comparison.json).
- [Databázové invarianty 40331478579](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40331478579):
  **16 / 16 PASS**, bez porušení; [výsledky](invariants.json).
- [Účetní/skladová kontrola 40331571209](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40331571209):
  **14 účetních + 20 skladových kontrol PASS**, pouze čtení;
  [výsledky a omezení](accounting.json). Osm skutečných runtime SHA-256
  odpovídá Git objektům nasazeného commitu; [porovnání](runtime-comparison.json).
- [Izolovaná obnova 40331688272](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40331688272):
  ověřeny SHA-256 a manifest přesně zálohy vytvořené při tomto deployi;
  obnova do dočasné MariaDB 11.4, počty řádků všech **336 tabulek** a názvy
  **2 triggerů** shodné, úklid dočasných dat PASS. Produkční DB se neobnovovala.
  [Strojový výsledek](restore.json).

## Hranice ověření

Předběžný čtecí snapshot produkce:
[40329062828](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40329062828), PASS.
Živý release byl `d12e7c792ce796260f3fc5d07b25a395c310fcca`, UAT false;
Fio import/automatické potvrzování, SumUp, Stripe a Velocota vypnuté.
Stejné dvě chybějící přílohy účtenek hlásila kontrola již před nasazením.

Čtecí nástroj obsahuje historický manifest `old_source/new_source`; jeho
výsledky nejsou důkazem shody souborů tohoto nového vydání. Pro toto vydání
se používá živá release identita, log aktivace, osm aktuálně porovnaných runtime
souborů a konkrétní HTTP chování. Nejde o hashovou inventuru celého webrootu.

Účetní a řízené skladové tabulky jsou prázdné, 855 variant nemá řízený skladový
zůstatek. Žádná skutečná objednávka, faktura, export nebo vratka nebyla pro test
vytvořena. Technické PASS nenahrazuje lidské/účetní převzetí, inventuru,
pilot D12–D14 ani dodání provozních dat. Obnova certifikátu nebyla spuštěna;
F2 dokládá rozhodnutí a zákaz HTTP cest, nikoli úspěšnou ACME obnovu.
Toto je vlastní ověření nasazení, nikoli nová nezávislá přejímka vlákna dozoru.
