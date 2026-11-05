# Měsíční členství a zastupování trenérů — vydání 5. 11. 2026

**Obě změny jsou sloučené a technicky nasazené.** [PR #314](https://github.com/KovoPraha/evidenceTreninku/pull/314), živé SHA `9b0dec083a650df933b09ecd4545d782069daa62`, [deploy 42064630068](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42064630068) SUCCESS. Nasazení schválil vlastník pro obě změny. Nové finanční automatizace, pravidelný plán a rozesílání se nezapínaly; existující Fio a SumUp zůstávají aktivní.

## Ověření společné verze

- Všech 11 kontrol PR PASS. [Linux CI 42063339966](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42063339966) i deploy: **2054 testů / 39237 assertions bez skipů**, samostatné unixové hranice 3/45. MariaDB 10.3/11.4 včetně nového měsíčního toku, souběhů, PDF a obnovy PASS. JavaScript 23/23, PHPStan, styl, CodeQL, anonymní sweep a dokumentace PASS.
- Sloučený strom je přesně shodný s ověřeným headem `a7a167b8897609748e7424a5db28bb1a2e900339`: strom `3a643be4d267dfe7f1d67cbb434ec5a9b69c056b`. [CI main](main-ci.json) SUCCESS. [PR](pr314.json), [kontroly](pr-checks.json), [deploy](deploy-run.json).
- První CI zachytilo neplatné nové řádky v inline JavaScriptu po formátování a rozdíl HTML rodinného přehledu proti referenční šabloně. Klientský kód byl obnoven se zachováním tréninkového kontextu; rodinná stránka má jen nový odkaz a stejný odkaz v srovnávací šabloně. Ochranné testy se nevypínaly. Rozhodující jsou nové zelené běhy výše.

## Produkce, migrace a záloha

[Čtecí audit po nasazení](post-audit-run.json) potvrdil přesné živé SHA a běh. [Porovnání](release-comparison.json): konfigurace i dosavadní integrační flags beze změny, migrační ledger 150 → 151, schéma current / pending []. Aplikována migrace `20261105150000_monthly_memberships`; nasazovací kontrola potvrdila nezměněné dosavadní VS. Historické source manifesty auditu nejsou úplnou inventurou souborů tohoto vydání.

Čerstvá záloha `evidence_2026-11-05_162921_1ae4ab9e.sql.gz` obsahuje **347 tabulek / 2 triggery**, SHA-256 `568ff63f469f70911efa757d98c82374466a85562392f9e4527cf3b427a17947`. [Skutečná izolovaná obnova 42065531972](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42065531972) PASS: checksum, všechny počty řádků a triggery. Jde o bod návratu před novou migrací; produkční DB se neobnovovala.

[HTTP před](pre-http.json) **48/48**, [HTTP po](post-http.json) **54/54 PASS**. Šest přidaných kontrol ověřuje ochranu nové správy, zákaznické stránky a PDF, CLI a implementačních souborů. Stávající katalog a stejné tři jednorázové produkty zůstávají dostupné. [Pomocník](http-smoke.py) používá jen anonymní GET.

## Přihlášená kontrola a měsíční náhled

[Chrome](browser.json) v trenérském kontextu vlastníka reprodukoval prázdné soupisky před nasazením. Po nasazení přímý zápis i plánování nabízejí všechny aktivní cíle; změna data na 6. 11. funguje a hledání TEST vrací také syntetickou osobu mimo dosavadní rozsah. Ve finančním kontextu se načte správa měsíčních tarifů. Původní pracovní kontext vedoucího sportu byl vrácen. Nezakládal se ostrý trénink ani tarif.

[Produkční dry-run 42066187538](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42066187538) SUCCESS: [náhled](monthly-preview.json) má apply=false, 0 členství, 0 předpisů, 0 dokladů, 0 zpráv a žádnou chybu. Provozní proměnné `KIS_MONTHLY_MEMBERSHIP_*` nejsou nastavené v repozitáři ani prostředí production, plán proto neběží. První dva čtecí běhy byly ještě ve frontě nahrazeny následným drillem; nevykonaly žádný krok. Dokládají se až výše uvedené dokončené běhy.

## Provozní hranice

Trenérská oprava je použitelná ihned. Vlastní svěřence/soupisky určuje dosavadní přiřazení; žádná přiřazení se neodhadovala ani neměnila. Obyčejný trenérský login, zápis zastupujícího trenéra v produkci a přednost vlastních skupin jsou pokryté místními testy, nikoli novým ostrým zápisem.

Měsíční implementace a databáze jsou nasazené. Pro ostrý prodej je třeba založit samostatnou nabídku, schválit cenu, podmínky, soupisku a účetní metodiku, a pak aktivovat zpracování a odesílání podle [runbooku](../../MONTHLY_MEMBERSHIP_RUNBOOK.md). Žádné existující jednorázové nabídky se nepřeváděly. Skutečné sjednání zákazníkem, platba bankou, produkční PDF a doručení upomínky zůstávají neověřené. Nasazení nebylo označeno jako úplná lidská/účetní přejímka (uat_schvaleno=false).
