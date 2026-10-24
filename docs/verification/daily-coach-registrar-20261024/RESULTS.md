# Balík A — Každodenní práce trenéra a registráře

Datum: 24. 10. 2026. **Implementováno a ověřeno lokálně, nenasazeno.**
Větev `codex/daily-coach-registrar-20261024`, výchozí `origin/main` `f2626b86`.
Zadání: [funkční analýza mimo e-shop](../../NONSHOP_FUNCTIONAL_ANALYSIS_2026-10-24.md).

## Výsledek

| Úkol | Implementované chování | Hranice |
| --- | --- | --- |
| A1 / F01 — práce vedoucího sportu | Provozní přehled skutečně vykreslí případy neuzavřené docházky, včetně data a náplně. Lze určit odpovědnou osobu, termín a další krok. | Přidělení úkolu samo neuzavře docházku. |
| A2 / F02 — práce s lekcí | Z rozvrhu a přípravy vede odkaz do jednoho kontextu: plán, odpovědi, osobní omluva, skutečný trénink a uzavření. Lze hledat lekci podle názvu a data. Omluva přebírá lekci a osobu. | Omluva není skutečná účast; existující pravidla náhrad se nemění. |
| A2 — dokončení zápisu | Po uložení skutečného tréninku se otevře jeho uzavření; opakované odeslání zachová stejný cíl a nevytvoří druhý trénink. | Skutečnou účast potvrzuje pracovník výslovně. |
| A3 / F04 — sportovní účast | Akce se vybírá názvem; starší lze vyhledat. Oprava se otevře z existující účasti, předvyplní její údaje a interní revizi. | Při souběžné změně zůstane původní revize i rozepsaný návrh; pracovník musí výslovně načíst aktuální záznam. |
| A4 / F05 — dohledatelnost | Prohlídky, finanční případy, výdaje, zálohy a odměny mají hledání, stav, období, počet výsledků a stránky po 25. Starší záznamy již nejsou uříznuté na prvních 100/200. | Trenér nadále vidí pouze vlastní odměny. Finanční zápisové postupy se nemění. |
| A5 / F02 — práce s osobou | Registrář v editoru osoby vidí sedm oblastí: identitu, rodinné přístupy, přihlášky/souhlasy, členství, soupisky, zdravotní podklady a osobní přijetí do programu. Odkazy přebírají osobu. | Přehled neuděluje oprávnění, nepotvrzuje členství ani úplnost souhlasů. Osobní obsluha bez online účtu zůstává možná. |

Při HTTP přejímce byla zjištěna další skutečná překážka: vedoucí sportu měl přístup
k plánu a uzavření, ale registr tras blokoval přípravu lekce, nový trénink a jeho
uložení. Doplněna výslovná delegace těchto trenérských tras a jejich našeptávače
osob / dostupnosti sportovišť. Vlastnictví agend se nemění; finanční pracovník
ani registrář tím nezískávají přístup k zápisu tréninku. Trenérovi vede návratový
odkaz z uzavření do jeho vlastní historie.

## Izolace a důkazy

- Pracovní kopie: `C:\xampp\htdocs\evidencePavel-finance-b1-20261022`.
- Web: pouze `http://127.0.0.1:18086`; MariaDB pouze port `33426`, schema `evidence_b1`.
- Ověřený vlastník prostředí, skutečný port/datadir a syntetický descriptor.
- Soukromé úložiště a sessions jsou v lokálním runtime mimo webroot.
- Vnější odesílání vypnuté; pouze syntetická data `LOCALHOST NEPLATIT`.
- Nový HTTP skript odmítne jiný host, schema, URL, datadir nebo vlastníka checkoutu.
- Žádný přístup k produkci, deploy, produkční testovací zápis ani migrace schématu.
- E-shop, sklady, nákup a účetní exporty nejsou předmětem této implementace.

## Ověření

| Ověření | Výsledek | Důkaz |
| --- | --- | --- |
| Cílené jednotkové a integrační testy | **135 testů / 2433 assertions PASS** | [PHPUnit](phpunit.txt) |
| Nový průchod přes PHP formuláře a MariaDB | **32 scénářů PASS** | [HTTP protokol](http-acceptance.json) |
| Existující HTTP přejímka docházky / sportovní činnosti / prohlídek / výdajů / odměn | **25 + 23 + 22 + 47 + 25 scénářů PASS** | [Souhrn](baseline-http.json) |
| PHP lint | 24 upravených nebo nových PHP souborů PASS | Lokální CLI |
| Kontrola whitespace | `git diff --check` PASS | Lokální Git |

Nové testy výslovně ověřují dohledání záznamů za původním limitem 200, doslovné
hledání `%`/`_`, parametry SQL, neplatná období, zachování trenérova rozsahu,
kanonické vyhodnocení zdravotního stavu a nepřepsání souběžné opravy. Přes HTTP
prošel osobní omluvný zápis → RSVP bez potvrzené účasti → skutečný trénink →
uzavření, včetně opakovaného odeslání. Pouhé čtení nových přehledů nevytvořilo
záznam sportovní účasti, uzavření, omluvy, prohlídky, papírového podkladu ani soupisky.

Spuštění v tomto připraveném B1 prostředí:

```powershell
& C:\xampp\php\php.exe tests/Support/DailyCoachRegistrarHttpAcceptance.php
& C:\xampp\php\php.exe vendor/phpunit/phpunit/phpunit --filter 'DailyWorkReadModelsTest|OperationalCasesTest|TrainingClosureTest|TrainingClosureFormTest|SportsActivityTest|MedicalClearanceTest|ClubExpensesTest|ClubRewardsTest|SportovecEditorTest|SportovecEditorDetailsTest|TrainingSubmission|StaffWorkspaceRegistryTest|SportovecEditorWiringTest|TrainingRosterBridgeWiringTest|TrainingPlannerViewTest|TrainingCalendarWiringTest'
```

## Rozsah závěru a navazující práce

Toto je lokální funkční implementace balíku A a automatizovaná přejímka jeho
postupů, nikoli důkaz nasazení ani lidská vizuální přejímka produkce. Nový průchod
používá skutečné HTTP formuláře a ověřuje jejich HTML; neověřuje vzhled v prohlížeči.

Seznam zdravotních podkladů vyhodnocuje metadata odpovídajících záznamů hromadně
stejným algoritmem jako detail a až potom stránkuje. Dokumenty a jejich úložiště
se do čtecího modelu seznamu nedostávají. Pro rozsah malého oddílu je to zvolený
postup; není to výkonnostní certifikace pro rozsáhlé archivy.

Zůstávají samostatné balíky B–D: život osoby a rozhodnutí o členství, podrobnější
administrativa akcí, sezonní postupy a komunikace. Redesign finančních zápisových
formulářů patří do C. Pravidla členství, povinnost zdravotních podkladů, sazby,
schvalovatelé a sporné identity nebyly doplněny odhadem. Společná přejímka včetně
e-shopu patří až do E.
