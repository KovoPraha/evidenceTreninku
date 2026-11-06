# Sjednocení dokumentace a session handoff — 6. 11. 2026

## Zadání a rozsah

Vlastník požádal aktualizovat dokumentaci podle ověřené reality, sjednotit rozpory,
jasně zachytit zbývající práci a zachovat rozpracované změny. Výslovně zakázal
nové zapínání produkce. Tato relace upravuje pouze dokumentaci; nespouští deploy,
instalaci workeru, rozesílání, import, publikaci nabídky ani produkční zápis.

Pracovní větev `codex/documentation-reconcile-20261106`, izolovaná kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-documentation-reconcile-20261106`.
Výchozí main `9c543ff9`; dokumentační vstupy #334 `8c6537e2` a #333 `7f3ccc6f`.
Původní pracovní kopie a obě existující dokumentační větve se nepřepisují.
Při práci mezitím #333 vstoupilo do main jako `0f3ac591`. Novější souběžný
vstup #335 `a561ed51` doložil publikaci nabídky BAR; také jeho dokumentace
a bezpečné důkazy jsou zachované. Tato relace publikaci neprovedla.
Následně #336 `36500d01` doplnilo chybějící čtecí doklad schváleného a zveřejněného
Závodního týmu. Také tento výsledek a podklady se přebírají bez produkčního zápisu.

## Postup a stav

1. Dokončeno: přečteny aktuální stav, handoff, session ledger, registr práce,
   souběžný plán a produkční protokoly; porovnány s kódem a GitHub Actions.
2. Dokončeno: sjednocení dokumentačních větví #333 a #334, jeden registr další práce,
   oddělení nasazení od publikace a od uživatelského/účetního převzetí.
3. Dokončeno: kontrola odkazů, historických bloků, dokumentačního kontraktu a rozdílu
   proti main; přesný místní výsledek a vzdálené předání níže.

## Opravené rozpory a zachované otevřené body

| Oblast | Doložená skutečnost | Zbývající krok |
| --- | --- | --- |
| Upozornění | #330 nasazené, minutový hostingový běh a 18 přijetí transportem. Produkční browser doložil zvoneček, přesný odkaz a změnu přečtení. | Potvrzení cílové schránky; instalaci/rozesílku ani již doloženou kontrolu neopakovat. P01. |
| Členství – publikace | Původní doklad konceptu i následný souhlas z #333 překonal čtecí průchod #336: aktivní schválený tarif, uložený vystavovatel a zveřejněný produkt 297 / varianta 951, 2200 Kč/měsíc. | Publikaci ani schválení neopakovat. Zbývá skutečné sjednání s klubem ověřeným sportovcem a první platba/PDF/doručení. P05. |
| Členství – automatika | Plán/zápis/pošta povolené, čas 05:23 UTC, doložený ruční prázdný apply --send. | První automatický časový start a skutečná platba/PDF/doručení nejsou doložené. To samo nedokazuje poruchu. P05. |
| BAR | Katalog/přístupy/doména převedené, syntetická provozní přejímka PASS. Novější souběžné #335 dokládá již zveřejněnou nabídku 1 / vydání 1; vlastní anonymní čtení potvrdilo 45 fotografií a žádné hlášení přípravy. | Publikace P03 hotová; zbývá skutečný login obsluhy, zásoby/kasa, první prodej a finanční přejímka P02. Tato relace nic nepublikovala. |
| BAR účetnictví | Kód poskytuje provozní účetní podklady/export, ne zápisy do hlavního účetnictví. Kontrakt a plán adaptéru existují. | Účetní pravidla, implementace adaptéru a přejímka; nejde jen o nezapnutý hotový přepínač. P04. |
| Trenéři | #314 povoluje zastupování i bez přiřazení soupisky; vlastní cíle pouze řadí první. | Rozsahy soukromých profilů jsou samostatné; historických 29 účtů bez rozsahu není důkaz blokace tréninku. |
| Náhrady | Již nasazené, kontrola 6. 11. bez nové chyby. | Přihlášené produkční UAT není uzavřené; není čekající oprava/deploy. |
| Platby | Fio a SumUp aktivní; 45 pohybů bez shodného VS. | Skutečně zaplacená úhrada a lidský průchod; nepárovat podle částky. P07. |
| Evidence členů | Převod 435 počátečních zařazení provedený. | Nový export pro poslední rozdílovou synchronizaci, role-UAT a E8. P06. |
| Dnešní záloha | Před nasazením ověřena záloha 385 tabulek / 2 triggery; historické jiné obnovy PASS. | Izolovaná obnova právě této zálohy zatím neprovedená. P14. |

Původní obsah plánu #333 je zachovaný jako [historický vstup](WORK_PLAN_SOURCE_333.md).
Jeho jedinečné otevřené body nejsou ztracené: další pracovní události P12,
balíček pro testerku P13, dřívější souhlas u členství P05. WORK_PLAN_CURRENT
je kompatibilní odkaz na jediný WORK_REGISTER, nikoli druhá sada stavů.
Při práci dorazily novější doklady #335 a #336. Předchozí přehled pouze konceptů
baru a členství je tak překonaný; oba stavy jsou nyní výslovně aktualizované,
zatímco původní protokoly a rozhodnutí zůstávají historicky zachované.

## Použité důkazy

- [Nasazení 42172409868](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42172409868):
  aktivace `9c543ff9`, migrace current / pending [], kontrola zachování VS, HTTP 200.
- [Provozní status 42173897241](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42173897241):
  skutečný hostingový běh 06:21:04 UTC, 18 sent, 0 pending/processing/failed.
- [Produkční browser a vydání upozornění](../staff-notifications-release-20261106/RESULTS.md),
  [BAR přejímka](../bar-acceptance-20261106/RESULTS.md),
  [náhrady](../makeup-audit-20261106/RESULTS.md),
  [měsíční vydání](../monthly-coach-release-20261105/RESULTS.md),
  [aktivace pošty](../../MEMBERSHIP_MAIL_ACTIVATION_2026-11-05.md).
- Novější [publikace BAR z #335](../bar-menu-publication-20261106/RESULTS.md)
  opravuje dřívější údaj o neveřejném návrhu. Vlastní [anonymní čtecí kontrola](public-menu-check.json)
  dokládá veřejnou nabídku bez přihlášení, publikace či dalšího produkčního zápisu.
- [Čtecí produkční přejímka Závodního týmu #336](../racing-team-status-20261106/RESULTS.md)
  ze 6. 11. kolem 12:00–12:03 CET: aktivní schválený tarif, uložený vystavovatel,
  zveřejněná nabídka a cesta ke sjednání. Použitý účet bez ověřeného sportovce;
  nevznikl skutečný závazek/platba. Tato relace přebírá protokol, neprovádí jeho zápisy.
- Čtecí GitHub kontrola: main `9c543ff9`, otevřené #333/#334; poslední běhy
  `monthly-memberships-production.yml` byly ruční, workflow active, tři
  `KIS_MONTHLY_MEMBERSHIP_*` proměnné true. Nový workflow nebyl spuštěn.
- Kontrolovaný kód: `includes/staff_notifications.php`, `staff_notification_bell.php`,
  `bin/staff-notification-operations.php`, `includes/club_makeup.php`,
  `includes/coach_training_access.php`, `includes/monthly_membership_worker.php`,
  `includes/bar/accounting.php` a denní členský workflow.

## Zachování práce

Původní `C:/xampp/htdocs/evidencePavel` zůstává na své větvi se změnou
`kis_rosters_admin.php` a netrackovanými výstupy. Soubor při kontrole má SHA-256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`;
nebyl přepisován ani zahrnut do commitu. Stávající dokumentační worktrees
a vzdálené větve #333/#334/#335/#336 se touto relací nemění ani nemažou.

## Ověření a předání

První místní ověření: `php tools/check-current-docs.php` PASS,
80 řádků CURRENT_STATE / 820 odkazů / 104 historických bloků, 0 chyb;
`CurrentDocumentationContractTest` **2 testy / 14 assertions PASS**;
371 místních odkazů ve 14 Markdown souborech bez chyby, `git diff --check` PASS.
Po zapracování novějšího BAR předání: `check-current-docs` PASS,
**80 řádků / 833 místních odkazů / 104 zachovaných archivních bloků / 0 chyb**.
Dokumentační testy znovu **2 / 14 PASS**. Rozšířená kontrola všech 25 změněných
Markdown souborů: **1160 místních odkazů, 0 chyb**, žádné konfliktní značky.
`git diff --check` PASS a kontrolní hash původního WIP beze změny.
Rozdíl tvoří pouze dokumentace, projektové instrukce a bezpečné důkazy;
aplikační PHP/JS, migrace, workflow ani produkční konfigurace se nemění.
Nejde o nový test runtime, plateb či přihlášeného produkčního UI.

Po posledním souběžném dokladu členství #336: **80 řádků / 871 odkazů /
105 zachovaných archivních bloků / 0 chyb**. Dokumentační kontrakt **2 / 14 PASS**,
kontrola **1222 místních odkazů ve 29 Markdown souborech bez chyby**,
`git diff --check` PASS. Nový archivní blok pochází ze zachovaného předání #336;
původní bloky se nepřepisují.
Před předáním zachován také mezitím sloučený main `d8215ba7` (#335), jehož
strom byl shodný s již připojeným zdrojovým předáním; konflikty souběžné
dokumentace vyřešené bez vypuštění jeho změn.

Souborové předání je v [PR #337](https://github.com/KovoPraha/evidenceTreninku/pull/337),
které sjednocuje #333/#334/#335/#336. Aktuální CI a stav sloučení určuje PR.
Po zelených kontrolách lze začlenit pouze tuto dokumentaci; produkční deploy
ani změna přepínačů nejsou součástí předání. Při další session číst aktuální main,
CURRENT_STATE, HANDOFF_CURRENT a jediný WORK_REGISTER, nikoli znovu slučovat
historické pracovní větve podle jejich názvu.

## Důkazní hranice

Nasazení a provoz se dokládají existujícími dokončenými běhy a protokoly, nikoli
novým produkčním testem této relace. Starší datované protokoly se nepřepisují
jako aktuální stav. Chybějící doklad dokončení není důkaz chybějícího souhlasu
ani důkaz, že se operace nestala. Při navázání nejprve pouze ověřit stav.
