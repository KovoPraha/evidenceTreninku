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

## Postup a stav

1. Dokončeno: přečteny aktuální stav, handoff, session ledger, registr práce,
   souběžný plán a produkční protokoly; porovnány s kódem a GitHub Actions.
2. Dokončeno: sjednocení dokumentačních větví #333 a #334, jeden registr další práce,
   oddělení nasazení od publikace a od uživatelského/účetního převzetí.
3. Probíhá: kontrola odkazů, historických bloků, dokumentačního kontraktu a rozdílu
   proti main; přesný výsledek kontrol a stav předání budou doplněné níže.

## Opravené rozpory a zachované otevřené body

| Oblast | Doložená skutečnost | Zbývající krok |
| --- | --- | --- |
| Upozornění | #330 nasazené, minutový hostingový běh a 18 přijetí transportem. Produkční browser doložil zvoneček, přesný odkaz a změnu přečtení. | Potvrzení cílové schránky; instalaci/rozesílku ani již doloženou kontrolu neopakovat. P01. |
| Členství – publikace | Původní protokol dokládá koncept varianty 951; #333 zaznamenává následný souhlas s údaji TJ KOVO, tarifem a publikací. | Doklad dokončení chybí. Nejprve pouze ověřit stav; netvrdit ani jistou nepublikaci, ani dokončený prodej. P05. |
| Členství – automatika | Plán/zápis/pošta povolené, čas 05:23 UTC, doložený ruční prázdný apply --send. | První automatický časový start a skutečná platba/PDF/doručení nejsou doložené. To samo nedokazuje poruchu. P05. |
| BAR | Katalog/přístupy/doména převedené, syntetická provozní přejímka PASS, poslední doklad nabídky 1 je neveřejný návrh. | Skutečný login obsluhy, zásoby/kasa, první prodej, publikace a finanční přejímka. P02–P03. |
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
- Čtecí GitHub kontrola: main `9c543ff9`, otevřené #333/#334; poslední běhy
  `monthly-memberships-production.yml` byly ruční, workflow active, tři
  `KIS_MONTHLY_MEMBERSHIP_*` proměnné true. Nový workflow nebyl spuštěn.
- Kontrolovaný kód: `includes/staff_notifications.php`, `staff_notification_bell.php`,
  `bin/staff-notification-operations.php`, `includes/club_makeup.php`,
  `includes/coach_training_access.php`, `includes/monthly_membership_worker.php`,
  `includes/bar/accounting.php` a denní členský workflow.

## Důkazní hranice

Nasazení a provoz se dokládají existujícími dokončenými běhy a protokoly, nikoli
novým produkčním testem této relace. Starší datované protokoly se nepřepisují
jako aktuální stav. Chybějící doklad dokončení není důkaz chybějícího souhlasu
ani důkaz, že se operace nestala. Při navázání nejprve pouze ověřit stav.
