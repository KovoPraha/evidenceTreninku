# P11 R06 — předání výběru osob, 7. 11. 2026

Zadání splněné místně, bez deploye a se zachováním WIP.
[Plán](ATTENDANCE_PERSON_PICKER_2026-11-07.md),
[finální důkazy](verification/attendance-person-picker-20261107/final/RESULTS.md),
[PR #366](https://github.com/KovoPraha/evidenceTreninku/pull/366): stav finálního CI/merge ověřit u PR.
Větev `codex/attendance-person-picker-20261107`, worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-attendance-picker-20261107`.

## Hotovo a důležitá oprava

- Hledání/paging po 50 plus všichni vybraní, hosté dostupní, počet účastníků
  neomezený na 50. Nativní čtecí POST, žádný nový JS/endpoint/cache oprávnění.
- Obě části patří do jednoho formuláře: hledání zachová současně rozepsanou
  docházku i omluvu včetně prázdného výběru, verze, původu a sousední stránky.
  První dva nezávislé formuláře měly tuto mezeru; první výsledky jsou historické.
  Prefixy omluvy oddělené, původní API stále podporované. Zápisová tlačítka
  neblokují required pole sousední části, serverová validace zůstává původní.
- Nový stejný seed před/po: 10221 osob, 728 plánů, 200 účastníků, 52 revizí,
  10k zdravotních sledování / 30k záznamů. 540 HTTP časů + 78 Chrome pozorování.
  HTML 1,37 MB → 92 kB; warm Chrome trenér **4,79 → 0,59 s**, vedoucí
  **5,80 → 0,62 s**, druhý trenér **5,61 → 0,40 s**. Lokální řízený viewport,
  žádné produkční SLA. SQL někde stouplo: 59 → 75, fresh checks zachované.
- JS/noJS, obě současně rozepsané části, 390 px, role/CSRF, všech 10221 osob
  ve 205 stranách obou rolí PASS. 44 HTTP kontrol, revize 53/201/paper, stale422,
  skutečná UI omluva 1001/paper a legacy JSON 1002/phone ověřené v DB.
- Čtecí měření/diagnostiky 0 DML/DDL a 13 nezměněných checksums; zápisové UAT
  až následně. 36 testů / 380 assertions, PHPStan a styl 9 PHP PASS.
- CI 10.3/11.4 reader parita; staré měřidlo sdílí aktuální UI, původní validator.
  Import opraven v generované kopii i pro workers; archivovaný harness a přísná
  HTML/doménová parita zůstávají. Finální výsledky CI patří k finálnímu PR head.

## Úklid a integrace

První runtime `kis-picker-20261107-d6aeea40` i finální
`kis-picker-final-20261107-76d29d23` jsou zastavené, adresáře ponechané.
Vlastní schema/účty/oba configy/credentials/cookies odstraněné, browsery zavřené,
TCP 18095 a 33459 odmítají spojení. Původní `kis_rosters_admin.php` stále SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.

Měřený base d958fd6e; převzatý main 269684c2 obsahuje cizí #347, #364 a #367.
Jejich dokumentace/provozní výsledky zachované. Novější training_day.php po #367
není součást tohoto celého časového páru; integrované testy/CI zvlášť.
Žádná produkční operace tohoto vlákna. Release/flags číst z CURRENT.

## Zbývá podle priority

Finální CI/review/přijetí #366 mají konkrétní SHA a stav u PR; při převzetí
neopakovat již hotový krok. Tato relace nepovoluje deploy.

1. Celé HTTP/browser společné verze s #367. Dávkové lekce už hotové; starých
   219 SQL před integrací není aktuální počet a není to úkol znovu implementovat.
2. R13/R05 objemy/kandidáti, rows examined, fyzický mobil/síť/CPU/souběh a R01–R16.
3. Produkční deploy/přejímka pickeru vyžadují nové explicitní zadání vlastníka.
   Podklady ostatních P01–P14 zůstávají v registru; K0.2 není uzavřené.

Integrační ověření 9d4a7538 po převzetí #367: **50 testů / 1294 assertions PASS**,
PHP quality CI 42375011506 SUCCESS; [doklad](verification/attendance-person-picker-20261107/final/integration.json).
Celé HTTP společné verze tím není přeměřené. Finální CI/merge hlavy u #366.

Integrační CI 42375011560: všech 18 párů HTML/data PASS, selhal starý cap 45 SQL.
Nový přesný celostránkový budget 56 = 45 + 14 picker − 3 dávkové lekce; žádná změna aplikace.
Rows cap1000, relativní úspora a samostatný validator budget3/200 a5/1200 zůstávají.
Aktuální training_day.php nyní sdílený i v generované before kopii.
[Přehrání 10.3](verification/attendance-person-picker-20261107/final/ci-budget-replay-10.3.json), [11.4](verification/attendance-person-picker-20261107/final/ci-budget-replay-11.4.json),
[odmítnutí 57 SQL, 1001 řádků a vadného HTML](verification/attendance-person-picker-20261107/final/comparator-guards.json) PASS.
Nový celý nativní CI běh a jeho výsledek evidují kontroly finální hlavy PR.

Před finálním CI převzaté DOC03 #368 / 8e9cdce1: priority i provozní stav karet/cen zachované, žádná změna aplikace. E22 patří přijatému DOC03, naše zahájení je E26.

## Pokračování podle nového zadání vlastníka

7. 11. 2026: dokončit #366, ověřit výsledky a změřit před/po; pokud kontroly
projdou, commitnout, sloučit a nasadit. Dřívější zákaz deploye je historický.
Neaktivovat nové integrace a neměnit skutečné osoby, role ani docházku.
Izolované pokračování `codex/attendance-picker-completion-20261107`, worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-attendance-picker-completion-20261107`.
Původní picker worktree i jeho rozpracované dokumentační sloučení a výstupy zachované.

1. Převzít #369 / `2ef20aed`, zachovat přijaté release/priority a automatické bloky.
2. Na jediném novém syntetickém localhost fixture měřit aktuální main před pickerem
   a potom stejný main s pickerem; shodné #367 na obou stranách, stejné session a data.
3. Celé HTTP včetně rolí/negativních případů, skutečný Chrome před/po;
   zvlášť ověřit všechny osoby a oba drafty, původní API a writer kontrakty.
   Původní hotové testy zbytečně neopakovat, pokud jejich aplikační zdroje zůstaly stejné.
4. Finální požadované CI, commit/push do #366 bez přepsání cizí práce,
   merge aktuálního main a standardní chráněný deploy bez importu/UAT flagu.
5. Čtecí produkční kontrola a průběžný release protokol; přihlášené skutečné zápisy neprovádět.
   Výsledky a zbývající meze uložit do plánu, registru a obou handoffů.

Aktuální stav: integrační dokumentační konflikty vyřešené v izolované kopii;
nové měření a deploy NOT RUN. Přijaté E23 lekcí a E26–29 / D09 pickeru z d62c3ad2 zachované; nové pokračování E30 / D10. Dočasné pracovní přeznačení v 75801847 sjednocené.

Převzatý souběžný PR head d62c3ad2 bez změny aplikačního kódu. Nové oprávnění a pokračování vlastní [D10](DECISION_LOG.md#d2026-11-07-10) / E30.

Nové společné měření: [průběžný protokol](verification/attendance-person-picker-20261107/integrated/RESULTS.md). Izolovaná Windows kalibrace selhala v Aria, server zastavený; pokračuje verzovaný Linux CI HTTP/Chrome pár, nikoli další oprava cizí DB.
