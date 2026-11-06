# Průběžný plán a rozpracovanost

Aktualizováno 6. 11. 2026, Europe/Prague. Tento rozcestník eviduje práci a další kroky; živé vydání a přepínače určuje [CURRENT_STATE](CURRENT_STATE.md), převzetí [HANDOFF_CURRENT](HANDOFF_CURRENT.md), minulost [provozní historie](CHANGELOG-PROVOZ.md).

## Pravidlo vlastníka

Vše, na čem se pracuje a co je plánované, zapisovat průběžně přímo do souborů v repozitáři a propojit se související dokumentací. Každý nový úkol dostane tematický plán nebo položku existujícího plánu a odkaz zde. Zaznamenat zadání, oprávnění, stav, dokončené kroky, důkazy, otevřené otázky, další krok a pracovní větev/kopii. Odpověď v chatu ani externí Page tento záznam nenahrazuje. Neopisovat živé SHA do více dokumentů; podrobná fakta odkazovat z jednoho kanonického místa. Tajemství a skutečné osobní údaje zůstávají mimo Git.

## Předání aktuální práce

- Ověření Závodního týmu dokončeno čtecím produkčním průchodem: aktivní schválený tarif, uložený vystavovatel, veřejná nabídka a cesta ke sjednání PASS; účet bez ověřeného sportovce správně čeká na propojení. [Protokol](verification/racing-team-status-20261106/RESULTS.md). [Dokumentační PR #336](https://github.com/KovoPraha/evidenceTreninku/pull/336). Větev `codex/racing-team-status-20261106`, izolovaná kopie `C:\Users\Marek\Documents\Codex\evidencePavel-staff-notifications-20261106`; změny pouze dokumentace.


- BAR: na výslovný pokyn zveřejněná nabídka 1 / vydání 1, 45 produktů a 45 fotografií; [publikační plán](BAR_MENU_PUBLICATION_2026-11-06.md), [živé důkazy](verification/bar-menu-publication-20261106/RESULTS.md), [session handoff](SESSION_HANDOFF_BAR_2026-11-06.md). Dokumentace a trvalé pravidlo souborového zápisu v [PR #335](https://github.com/KovoPraha/evidenceTreninku/pull/335), větev `codex/bar-menu-publication-20261106`, izolovaná kopie `C:\Users\Marek\Documents\Codex\evidencePavel-bar-integration-20261105`. Aktuální kontroly a sloučení určuje PR; aplikační deploy není potřeba.
- Upozornění: implementace, sloučení, nasazení, instalace minutového běhu a produkční zvoneček dokončené. [Plán](STAFF_NOTIFICATIONS_2026-11-06.md), [důkazy](verification/staff-notifications-release-20261106/RESULTS.md), [aktuální stav](CURRENT_STATE.md).
- Otevřená mez ověření: příjemce zatím nepotvrdil doručení do schránky. Transport přijal 18 skutečných zpráv; kvůli potvrzení neposílat další kopie. Případné potvrzení připsat do protokolu.
- Předchozí dokumentační předání: souborový provozní stav, historie, pravidlo v CLAUDE, handoff a tento rozcestník. Pracovní větev `codex/staff-notifications-release-20261106`, izolovaná kopie `C:\Users\Marek\Documents\Codex\evidencePavel-staff-notifications-20261106`. Původní `C:\xampp\htdocs\evidencePavel` obsahuje nesouvisející rozpracovanost; nepřepisovat ji.
- Předání této dokumentace: [PR #333](https://github.com/KovoPraha/evidenceTreninku/pull/333). Kontrola 80 řádků / 814 odkazů / 104 archivních bloků bez chyby, dalších 111 místních odkazů bez chyby, dokumentační testy 2/14 PASS. Sloučení podléhá zeleným kontrolám na přesném headu; aktuální výsledek určuje odkazovaný PR. Dokumentační commit nevyžaduje další produkční deploy a nemění živé SHA.

## Navazující kroky

| Oblast | Stav / další krok | Podklady |
| --- | --- | --- |
| Závodní tým a fakturační údaje | Dokončeno a ověřeno 6. 11.: aktivní schválený tarif 2200 Kč/měsíc, zveřejněný produkt 297 / varianta 951, uložené údaje TJ KOVO. Sjednání vyžaduje klubem ověřeného sportovce. Znovu nezakládat ani nepublikovat. | [Aktuální důkaz](verification/racing-team-status-20261106/RESULTS.md), [runbook](MONTHLY_MEMBERSHIP_RUNBOOK.md) |
| Skutečný celý tok členství | Doložit první skutečné sjednání, předpis/doklad, příchozí bankovní platbu se shodným VS, přiřazení a doručení. Plná cena i během měsíce, ukončení ke konci měsíce, více disciplín bez příplatku. Jednorázové nákupy zůstávají zachované. | [Runbook](MONTHLY_MEMBERSHIP_RUNBOOK.md), [aktivace pošty](MEMBERSHIP_MAIL_ACTIVATION_2026-11-05.md), [Fio](verification/fio-followup-20261105/RESULTS.md) |
| Texty nabídky členství | Kandidát další úpravy: pro členství nahradit obecný štítek „kroužek“, katalogové „Detail a zájem“ a blok o termínu přesnějšími texty. Není to zjištěná blokace prodeje; v čtecí kontrole se runtime neupravoval. | [Nález a snímky](verification/racing-team-status-20261106/RESULTS.md) |
| Další pracovní události | Záměr rozšířit společný systém je zachycen; konkrétní další typy a jejich příjemci zatím nejsou vybrané. Při doplnění určit vlastníka, bezpečný odkaz, vznik/ukončení, deduplikaci a testy. Push a eskalace nejsou součástí dokončené první verze. | [Registr a hranice upozornění](STAFF_NOTIFICATIONS_2026-11-06.md) |
| Trenérské zastupování | Funkce je nasazená; vlastní svěřenci/soupisky určují pořadí, ne zákaz zastoupení. Přidělování skutečných rozsahů řešit podle klubového rozhodnutí. | [Přístup trenérů](COACH_TRAINING_ACCESS_2026-11-05.md), [vydání](verification/monthly-coach-release-20261105/RESULTS.md) |
| Sdílený ChatGPT projekt pro testerku | Dřívější zadání: instrukce asistenta, průvodce, scénáře, hlášení a průběžné čerpání z GitHubu. Dokončení balíčku a aktuální způsob sdílení nejsou v této relaci doložené. Před navázáním dohledat existující materiály, propojit je sem a ověřit aktuální možnosti ChatGPT. | [Dokumentační rozcestník](README.md), [uživatelské testování](PRODUKCNI-UZIVATELSKE-TESTOVANI.md) |
| BAR – provozní převzetí | Nabídka 1 / vydání 1 již zveřejněná a anonymně ověřená. Zbývá skutečné přihlášení Lucky, fyzické zásoby, vlastní kasa a první reálný prodej/uzávěrka, reálná bezhotovostní platba a účetní propojení. Neopakovat převod ani nezakládat druhou nabídku. | [Plán](BAR_INTEGRATION_PLAN_2026-11-05.md), [publikace](verification/bar-menu-publication-20261106/RESULTS.md), [session handoff](SESSION_HANDOFF_BAR_2026-11-06.md), [účetní adaptér](BAR_ACCOUNTING_ADAPTER_PLAN_2026-11-05.md) |
| Evidence členů | Poslední synchronizace čeká na nový export; role-UAT a E8 otevřené. Provedený převod neopakovat. | [E0–E8](MEMBER_EVIDENCE_WORK_PLAN_2026-11-02.md), [důkazy převodu](verification/member-evidence-cutover-20261102/RESULTS.md) |
| Trenérský pilot | Lidské UAT a T07 se skutečným heslem nedokončené; heslo zadává vlastník. Pilotní grant neprodlužovat automaticky. | [Scénáře](TRAINER_UAT_POSTUP_2026-11-01.md), [pilot](TRAINER_PILOT_OPERATIONAL_2026-11-01.md) |
| Klubové účetní převzetí | D12–D14, skutečná inventura/ocenění, dohody/částky, rozsahy a chybějící podklady zůstávají rozhodnutím a přejímkou klubu. Zelené CI je nenahrazuje. | [Rozhodnutí](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md) |

Tento rozcestník nepřiděluje automaticky oprávnění k novým produkčním zásahům. Již udělená konkrétní oprávnění trvají; při navázání nejdřív ověřit, co bylo skutečně dokončeno. Dřívější datované plány zůstávají historickou stopou a nemají přebít aktuální důkazy.
