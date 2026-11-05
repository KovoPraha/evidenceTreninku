# Dokumentace EvidencePavel / KIS

Aktualizováno: 5. 11. 2026, Europe/Prague

Evidence tréninků, e-shop a KIS jsou jedna aplikace s jedním repozitářem,
webrootem, migračním katalogem a kanonickou databází osob. Produkce běží na
<https://kis.kovopraha.cz/>.

## Začněte zde

| Dokument | Účel |
|---|---|
| [TV programy](TV-PROGRAMY.md) | Obsluha TV, editor obrázků a textů, veřejné adresy a zveřejňování carouselů |
| [Měsíční členství přes e-shop](MONTHLY_MEMBERSHIP_RUNBOOK.md) | Nový tok, platby, doklady, upomínky, samostatná aktivace a hranice ověření; [plán](MONTHLY_MEMBERSHIP_PLAN.md) |
| [Tréninky a zastupování bez povinné soupisky](COACH_TRAINING_ACCESS_2026-11-05.md) | Vlastní cíle první, ostatní dostupné; nové pravidlo, přiřazení a hranice ověření |
| [Pracovní plán evidence členů bez sezon](MEMBER_EVIDENCE_WORK_PLAN_2026-11-02.md) | Aplikační E1–E5 sloučeny, čtecí inventura a technický nácvik E6 prošly; konkrétní cleanup a E7/E8 otevřené |
| [Aktuální stav](CURRENT_STATE.md) | Poslední ověřené SHA, CI, deploy, funkční rozsah a provozní hranice |
| [Aktuální předání](HANDOFF_CURRENT.md) | Bezpečné převzetí práce na jiné stanici a synchronizace s GitHubem |
| [Hostingový Fio plán a 45 pohybů](verification/fio-followup-20261105/RESULTS.md) | Skutečné automatické importy, heartbeat a úplné čtecí posouzení čekajících pohybů |
| [Ostré Fio a SumUp](verification/live-payments-20261105/ACTIVATION.md) | Aktivace, rozsah 223 produktů, import historie a doložené meze finančního ověření |
| [Nasazení druhé funkční vlny PR #274](verification/functional-round2-release-20261102/RESULTS.md) | Sloučení, chráněný deploy, záloha, čtecí porovnání a produkční HTTP/browser ověření |
| [Druhý funkční průchod 2. 11.](verification/functional-uat-round2-20261102/RESULTS.md) | 35 místních kontrol, kupóny/sklad/akce/velodrom, simulovaná úhrada a vratka; oprava odděleného demo zboží a programu |
| [Nasazení funkčních oprav PR #272](verification/functional-release-20261102/RESULTS.md) | Review, CI, chráněný deploy, záloha, produkční HTTP/browser důkazy a limity ověření |
| [Funkční browser ověření 2. 11.](verification/functional-uat-20261102/RESULTS.md) | Veřejná produkce, místní syntetické průchody, tři UI opravy a jejich regrese |
| [Nasazení opravy obnovovacího odkazu](TRAINER_RESET_RELEASE_2026-11-01.md) | Review a merge PR #269, produkční ověření a zbývající asistované UAT |
| [Autonomní ověření trenérské obnovy](verification/trainer-recovery-autonomous-20261101-2341/RESULTS.md) | Skutečné doručení, platný odkaz, mobilní formulář a izolované testy dvou relací bez změny produkčního hesla |
| [Interní test trenérů](TRAINER_UAT_POSTUP_2026-11-01.md) | Scénáře T01–T08, očekávané výsledky a hlášení chyb |
| [Checklist pro trenéry](TRAINER_UAT_CHECKLIST_2026-11-01.md) | Krátký text připravený k přeposlání účastníkům |
| [Browser UAT pro jiné vlákno](TRAINER_UAT_BROWSER_2026-11-01.md) | Stejné scénáře, přesný rozsah automatizace, důkazy a asistované kroky s heslem |
| [Komplexní plán refaktoringu celé aplikace](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md) | Aktuální dispozice W6–W15, přijaté změny a důvody nepřijatých kandidátů |
| [Závěrečná technická přejímka refaktoringu](REFACTORING_CLOSURE_2026-10-30.md) | W6–W15 sloučeno a CI PASS; finální nasazení blokuje DB disk full, živé vydání v CURRENT_STATE |
| [Finální CI a provozní incident](verification/refactoring-final-release-20261030/RESULTS.md) | Záloha odmítla nasazení před migrací/aktivací, aktuální HTTP dopad a podklady pro hosting |
| [Registr hlavních cest K0.1](REFACTORING_ROUTE_REGISTER_2026-10-29.md) | 16 kritických cest: oprávnění, služby, hlavní tabulky, vedlejší účinky, testy a otevřená měření |
| [W6.2 — výpočet kapacity](REFACTORING_WAVE6_2026-10-29.md) | Dokončená čistá extrakce, přímé porovnání s původním kódem a ověřené produkční vydání |
| [W6.3 — paměť kapacity](REFACTORING_W63_2026-10-29.md) | Nasazené postupné vyhodnocení řádků, migrovaný profil a paměťový rozpočet |
| [W7.1a — rodinný přístup](REFACTORING_W71_2026-10-29.md) | Nasazená cílená kontrola jedné osoby, stejné autorizační podmínky a čtyři SQL |
| [W7.1b — souběhová autorizace](REFACTORING_W71B_2026-10-29.md) | Nasazená ochrana RSVP a náhrad po revokaci během čekání na zámek; 32 souběhových scénářů |
| [W7.2 — celý rodinný přehled](REFACTORING_W72_2026-10-29.md) | Nasazené cílení profilů, porovnání celé stránky v MariaDB 10.3/11.4; SQL −14 %, řádky −25,7 % pro 20 osob |
| [W7.3 — metadata a sekce](REFACTORING_W73_2026-10-30.md) | Nasazený rozsah metadat pouze pro GET; SQL −66,5 %, metadata −93,8 %, stejné výstupy a historie |
| [W7.4 — dávkové členství](REFACTORING_W74_2026-10-30.md) | Nasazeno, celý GET 752 → 619 SQL; členské dotazy 160 → 27, čerstvá autorizace |
| [W7.5 — stránkování registráře](REFACTORING_W75_2026-10-30.md) | Nasazeno, stránka 50 z 10 000 osob načte 53 místo 10 003 řádků |
| [W7.6 / W8.1 — časová osa](REFACTORING_W76_W81_2026-10-30.md) | Nasazeno, roční profil 4176 → 16 SQL; shodná historie a čerstvé členství |
| [První vlna refaktoringu](REFACTORING_WAVE1_2026-10-28.md) | Příprava z 28. 10. 2026: měření detailu a navigace, tři implementační balíky, přejímací podmínky a dokumentační inventář |
| [Finanční základ R13–R17](FINANCE_R13_R17_IMPLEMENTATION.md) | Lokální peněžní evidence, účetní doklady, vratky a první export; [ověření](verification/r13-r17/RESULTS.md) |
| [Produkční nasazení](NASAZENI.md) | Ruční chráněný deploy z GitHub Actions, zálohy a řešení chyb |
| [Produkční UAT](PRODUKCNI-UZIVATELSKE-TESTOVANI.md) | Identity, brány, testovací data a bezpečný úklid |
| [Localhost testování](localhost-testovani.md) | Příprava izolované lokální databáze a syntetických dat |
| [Pravidla migrací](../migrations/README.md) | Neměnné číslované databázové migrace |

Při rozporu má přednost čerstvý `origin/main`, skutečné workflow, migrace a
testy, potom `CURRENT_STATE.md` a `HANDOFF_CURRENT.md`.

## Tematická dokumentace

| Oblast | Dokumenty |
|---|---|
| Klubové programy a kroužky | [club-programs.md](club-programs.md), [training-roster-bridge.md](training-roster-bridge.md), [kis-roster-policies.md](kis-roster-policies.md), [účast na tréninku a návrh náhrad](TRAINING-RSVP-AND-MAKEUPS-2026-10-14.md) |
| E-shop a objednávky | [shop-checkout-k4.md](shop-checkout-k4.md), [shop-beneficiaries.md](shop-beneficiaries.md), [shop-account-person-roles.md](shop-account-person-roles.md), [shop-catalog-publication.md](shop-catalog-publication.md) |
| Platby | [Přijetí hotovosti a postup vratky](ORDER_PAYMENTS_2026-10-21.md), [produkční TEST cyklus hotovosti a vratky](ORDER_CASH_REFUND_PRODUCTION_2026-10-21.md), [shop-program-payment-verification.md](shop-program-payment-verification.md), [fio-readonly-import-k4.md](fio-readonly-import-k4.md), [stripe-integration-plan.md](stripe-integration-plan.md) |
| Číselné řady a účetní kategorie | [Návod a implementace](SHOP_NUMBER_SERIES_2026-10-21.md), [produkční vydání a zachování VS](SHOP_NUMBER_SERIES_RELEASE_2026-10-21.md), [kontrola před zveřejněním](SHOP_PUBLICATION_ACCOUNTING_GUARD_2026-10-22.md), [její produkční ověření](SHOP_PUBLICATION_ACCOUNTING_RELEASE_2026-10-22.md) |
| Akce a soupisky | [club-events-k3.md](club-events-k3.md), [club-event-roster-targets.md](club-event-roster-targets.md), [kis-teams-rosters.md](kis-teams-rosters.md) |
| Bezpečnost a osoby | [auth-one-time-tokens.md](auth-one-time-tokens.md), [auth-revocation-rate-limit.md](auth-revocation-rate-limit.md), [rodne-cislo-bezpecnost.md](rodne-cislo-bezpecnost.md), [identita a pravidla shody osob](PERSON_FOUNDATION_R07_R08.md) |
| Současná testerová vlna | [UAT-OPRAVY-2026-10-07.md](UAT-OPRAVY-2026-10-07.md) |
| UX navigace a kalendáře | [UX-NAVIGACE-A-KALENDARE-2026-10-08.md](UX-NAVIGACE-A-KALENDARE-2026-10-08.md) |
| Datové toky a rizika | [grafická mapa XLSX](../outputs/data-flow-audit-2026-10-07/DATOVE_TOKY_APLIKACE.xlsx), [aktuální mapa kódu](../outputs/data-flow-audit-2026-10-07/DATOVE_TOKY_KOD.md), [aktuální nálezy](../outputs/data-flow-audit-2026-10-07/NALEZY_A_RIZIKA.md), [historický audit 24. 8.](../outputs/data-flow-audit-2026-08-24/DATOVE_TOKY_KOD.md) |

## Aktuální funkční oblasti

- evidence tréninků, měření, sportovců, skupin, podskupin a závodů;
- plánovač tréninků, soupisky, RSVP a trenérský přehled účasti;
- rodinné a sportovní účty a vazby na kanonické osoby;
- e-shop pro zboží, kroužky, programy, členství a placené klubové akce;
- klubové programy, období, kapacity, podmínky a přechod do soupisek;
- klubový kalendář, události, soustředění a registrace;
- členské příspěvky, bankovní platby a administrativní párování;
- veřejný velodrom, individuální lekce a rezervace sportovišť;
- audit, pracovní pozice, oprávnění, importní kontroly a exporty.

## Technologie

- PHP 8.2+, procedurální aplikační vrstva a PDO;
- MariaDB; CI ověřuje kompatibilitu s 10.3 a 11.4;
- Bootstrap 5.3.3, Bootstrap Icons 1.11.3 a vanilla JavaScript;
- Composer se zamčenými verzemi v `composer.lock`;
- PHPUnit 11;
- Apache/XAMPP lokálně a chráněný SSH/rsync deploy z GitHub Actions.

## Historické dokumenty

Datované audity, roadmapy, staré handoffy, implementační prompty a soubor
`plan-eshop-tymova-evidence/SESSION_HANDOFF.md` zůstávají v repozitáři jako
auditní a rozhodovací stopa. Jejich SHA, počty testů, verze a označení
„plánováno“ popisují dobu vzniku. Nejsou autoritou pro aktuální checkout.

Velké dokumenty `uzivatelska-prirucka.md`, `technicka-dokumentace.md`,
`databazove-schema.md` a `vyvojarsky-pruvodce.md` jsou historický základ
baseline 2.20. Aktuální doplňky jsou vedené tematicky a přesný stav určuje kód,
migrace a testy.

- [Implementace evidence členů bez sezon a postup převodu](MEMBER_EVIDENCE_IMPLEMENTATION_2026-11-02.md) — aplikační rozsah, kompatibilita a zbývající E6–E8.
- [Podklady produkčního převodu členství](MEMBER_EVIDENCE_PRODUCTION_TRANSITION_2026-11-02.md) — aktuální inventura, hranice zálohy a konkrétní otevřená rozhodnutí.
