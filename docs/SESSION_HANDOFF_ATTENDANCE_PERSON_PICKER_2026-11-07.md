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

1. Finální CI/review/přijetí #366 bez deploye; konkrétní SHA/checks u PR.
2. Celé HTTP/browser společné verze s #367. Dávkové lekce už hotové; starých
   219 SQL před integrací není aktuální počet a není to úkol znovu implementovat.
3. R13/R05 objemy/kandidáti, rows examined, fyzický mobil/síť/CPU/souběh a R01–R16.
4. Produkční deploy/přejímka pickeru vyžadují nové explicitní zadání vlastníka.
   Podklady ostatních P01–P14 zůstávají v registru; K0.2 není uzavřené.
