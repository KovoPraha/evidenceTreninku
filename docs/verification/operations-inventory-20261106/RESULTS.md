# Provozní inventura a návaznosti — 6. 11. 2026

Tento záznam je datovaný snímek. Živý stav patří do [CURRENT_STATE](../../CURRENT_STATE.md),
rozpracovaná a plánovaná práce do [WORK_REGISTER](../../WORK_REGISTER.md).

## Ověřené změny proti předchozímu přehledu

- PR #330 je sloučený jako `9c543ff9ef7870d3503ec7f38fc05f2b376d0a82`.
  [Produkční nasazení 42172409868](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42172409868)
  dokončeno SUCCESS. Předchozí označení „nenasazeno“ už neplatí.
- [Čtecí provozní status 42173897241](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42173897241)
  v 07:21:36 Europe/Prague: 3 čekající žádosti, 3 události, 18 osobních upozornění,
  fronta 18 sent / 0 pending / 0 processing / 0 failed / 0 cancelled.
  [Sanitizovaný status](staff-status.json) dokládá skutečný hostingový běh
  v 07:21:04 a `recent_successful_hosting_run=true` bez nových zpráv v tomto běhu.
  `executable=false` je zachovaný diagnostický údaj; nezaměňovat jej za selhání,
  když status současně dokládá skutečný hostingový běh. Stav sent není potvrzení
  doručení do cílové schránky. Příjemci a osobní údaje se do této dokumentace nekopírují.
- [BAR provozní přejímka](../bar-acceptance-20261106/RESULTS.md) doplnila
  syntetický browser prodejů, kelímků, storna, uzávěrky a vlastní spotřeby.
  V produkci vznikl pouze neveřejný návrh nabídky 1 se 45 produkty; skutečný
  login Lucky, inventura, kasa/prodej, publikace a účtování zůstávají oddělené.
- [Omluvenky a náhrady](../makeup-audit-20261106/RESULTS.md) jsou nasazené a
  synteticky ověřené; dokumentační PR #331 sloučený. Bez nové runtime opravy.

## Větve a místní zbytky

Inventura před sloučením #330 prošla 290 místních větví a otevřené PR.
Samostatné větve měsíčního členství a zastupování trenérů jsou začleněné
v PR #314, Fio v #102 a následných vydáních, W11 v #252, W8.5 v #243,
klubové implementace přes společné integrační PR. Neshoda commitů po squash
merge sama neznamená chybějící funkci. Návratové větve se neslučují dopředně.

Zachované lokální zbytky: popisky legacy `kis_rosters_admin.php`, historické
poznámky v pracovní kopii objednávkových e-mailů a pomocné syntetické testy.
Rozšíření BAR fixture následně vstoupilo do PR #332. Dočasná auditní kopie
s hromadně chybějícími soubory není kandidát k nasazení. Nic se nemaže/resetuje
bez posouzení vlastníka souborů; úklid nemá přednost před zachováním WIP.

## Rozsah této práce

Pouze dokumentační aktualizace a čtení existujících dokladů. Žádná nová instalace,
rozesílka, publikace, produkční zápis ani nasazení aplikace. Budoucí kroky,
závislosti a meze oprávnění jsou zapsané v propojeném registru práce.
