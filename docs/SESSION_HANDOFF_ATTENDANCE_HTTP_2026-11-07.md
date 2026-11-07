# P11 R06 — společné celé HTTP/browser, 7. 11. 2026

Místní měření a ověření HOTOVO, žádná změna aplikace nebo produkce touto relací.
[Plán](ATTENDANCE_PERSON_PICKER_2026-11-07.md),
[výsledky / důkazy / reprodukce](verification/attendance-integrated-http-20261107/RESULTS.md).
Větev `codex/attendance-integrated-http-20261107`, worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-attendance-http-20261107`.
Plán před měřením3ca7e028; přesný stav výsledkového PR/CI a sdílení ověřit u větve.

## Skutečně ověřeno

- Nový jediný dataset: 350tabulek/161migrací, 10221osob, 728plánů/52týdnů,
  200účastníků/52revizí a 10kzdravotních sledování/30kzáznamů. Žádný reseed.
- Kontrolní d958fd6e před oběma změnami →společná faae75e8, osm hashů shodných
  s Git blobem. Novější přijaté #370/#372 jsou dokumentace/testy a provozní doklad.
- 21HTTP případů na variantu /540 pozitivních časů; 234Chrome pozorování
  všech9 kladných případů a tří rolí. Standardní login12účtů a skutečné UI.
  Všechny role/filtry a 18DOM kontrol PASS, žádné podvržené session.
- Velká docházka HTML1,37MB →92,5kB; Chrome warm6,40 →0,35s, HTTP945 →113ms.
  Široké hledání lekcí SQL203 →72, Chrome6,39 →0,45s; seznam SQL36 →23.
  Běžný detail SQL59 →72 kvůli čerstvým kontrolám, netvrdit plošný pokles SQL.
- Samostatné diagnostiky celého HTML: shoda s prostým HTTP, 0aplikačních DML/DDL,
  20doménových tabulek před/po stejné. Kompletní pomalejší vzorky ponechané;
  první neúplný networkidle běh vedoucího zopakovaný celý.
- JS/noJS obou současných draftů v obou směrech, Enter/paging/host/prázdný
  výběr/390px PASS. 44HTTP kontrol včetně CSRF/cizí lekce/neplatné find_* PASS.
- Až po časování skutečné syntetické UAT: revize53/201/paper, stale422bez přepsání,
  legacyAPI1002/phone a browser tlačítko1001/paper v06:00 ověřené vDB.
  Odebraná role/deaktivovaný účet odmítnuté, původní celé řádky obnovené.
- Nové pomůcky: PHP syntaxe/styl, PowerShell parsery a Python compile PASS.
  Žádný nový aplikační kód; další úplné CI výsledkové větve určuje její PR.

## Dvě rozdílná měření a skutečná produkce

Přijaté #372 má Linux pár2ef20aed →faae75e8, tedy samotný picker při shodných
už optimalizovaných lekcích. Tento Windows pár měří obě úpravy a všech9GET
scénářů, ne jen3detaily. Po změně v obou studiích72SQL pro široké lekce;
203 vs56 před změnou není rozpor. Absolutní časy mezi stroji/verzemi nesčítat.
Výsledky jsou lokální/CI syntetika, nikoli produkční SLA nebo fyzický mobil.

[Provozní doklad #372](verification/attendance-picker-release-20261107/RESULTS.md)
je samostatně povolené vydání druhé relace; release56f5ce08, deploy42380611292.
Deploy neopakovat. Tato relace nic na produkci nespustila ani nezměnila.
Release/flags má pouze CURRENT. Přihlášené produkční UAT je stále oddělené.

## Úklid a zbývající práce

Vlastní runtime `C:/xampp/tmp/kis-attendance-http-20261107-e999e34b` zastavený,
adresář ponechaný. Schema/účet/oba configy/credentials/cookies a soukromé
browser callbacky/logy odstraněné; všechny vlastní browsery zavřené. TCP18105 a33469
odmítají spojení. 55 bezpečných artefaktů má SHA manifest a kontrolu tajemství.
Původní WIP `kis_rosters_admin.php` zůstal SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.

1. R13/R05 objemy/kandidáti a skutečné rows examined podle plánu P11.
2. Fyzický mobil, řízená síť/CPU a více souběžných uživatelů; 390px zde je funkční emulace.
3. Přihlášené produkční převzetí podle samostatného zadání; zde žádný produkční test/zápis.
4. Další R01–R16 a ostatní lidské/podkladové závislosti v registru. K0.2 otevřené.

Stav CI/review/merge je u výsledkového PR této větve; hotové měření/implementaci
neopakovat jen kvůli staršímu „čeká“ v historickém předání. Vlastní D11/E36/E37
nekolidují s přijatým provozním D10 a E30–E35.

Výsledkové [PR #373](https://github.com/KovoPraha/evidenceTreninku/pull/373) předává tuto dokončenou místní etapu; konkrétní CI/head/merge ověřit přímo u PR. Produkční operace nejsou součástí tohoto PR.
