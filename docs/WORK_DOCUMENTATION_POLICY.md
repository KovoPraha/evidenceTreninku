# Povinná souborová dokumentace práce

Trvalé zadání vlastníka z 6.–7. 11. 2026 platí pro všechny vývojáře a vlákna:
veškeré implementační plány, koncepty, záměry, zamýšlené postupy, změny,
rozhodnutí a výsledky se průběžně zapisují do verzovaných souborů repozitáře.
GitHub musí obsahovat kontext potřebný k pokračování i bez původního chatu.
Chat ani interní paměť nejsou zdrojem projektového stavu. Při převzetí je nutné
odpovídající soubory znovu načíst, nikoli jen odkázat na vzpomínku na jejich obsah.
[AGENTS](../AGENTS.md) a [CLAUDE](../CLAUDE.md) odkazují na tento postup.

## Kam patří informace

| Obsah | Kanonické místo |
| --- | --- |
| Současné nasazení, flags, důkazy a rozcestník aktivních plánů | [CURRENT_STATE](CURRENT_STATE.md), nejvýše 80 řádků |
| Stručné převzetí, poslední vývoj, blokátory, další kroky a odkazy na plány | [HANDOFF_CURRENT](HANDOFF_CURRENT.md), nejvýše 80 řádků; toto je aktuální session handoff |
| Veškerá otevřená, plánovaná a odložená práce včetně konceptů | Jediný [WORK_PLAN_CURRENT](WORK_PLAN_CURRENT.md); [WORK_REGISTER](WORK_REGISTER.md) jen odkazuje sem |
| Cíl, varianty, zamýšlený postup, rozsah, akceptace a etapy konkrétní změny | Existující tematický soubor v `docs/` nebo nový datovaný plán; nesepisovat druhý konkurenční plán |
| Výsledky, prostředí, PASS/FAIL/NOT RUN a bezpečné důkazy | Tematický soubor nebo `docs/verification/<téma>/RESULTS.md` |
| Co a kdy vzniklo, změnilo stav, bylo předáno či dokončeno | [EVENT_LOG](EVENT_LOG.md), průběžný deník s odkazy na plán, rozhodnutí, důkazy a PR |
| Proč byla zvolena varianta, kdo rozhodl, co ji nahradilo; dohledání uzavřených plánů | [DECISION_LOG](DECISION_LOG.md), společný rozhodovací rozcestník |
| Historické podrobnosti | Datované tematické soubory, archivy a [CHANGELOG-PROVOZ](CHANGELOG-PROVOZ.md); bez přepisování starých výsledků na dnešní |
| Starší vstupní cesta session handoffu | [SESSION_HANDOFF](plan-eshop-tymova-evidence/SESSION_HANDOFF.md), pouze rozcestník do 25 řádků |

## Životní cyklus každé práce

1. **Načtení a zahájení:** ověř Git a WIP, načti CURRENT, handoff, registr,
   tematický plán a související rozhodnutí. Nový požadavek/koncept nebo změnu
   rozsahu zapiš do odpovídajícího souboru ještě před implementací.
2. **Registrace:** přidej či aktualizuj položku registru. Uveď ID, vlastníka,
   větev/worktree, stav, další krok a závislosti. Odpovídající soubor musí být
   dohledatelný z CURRENT i handoffu, přímo nebo přes jasně označenou položku
   společného registru. Oba krátké přehledy aktualizuj ve stejné změně.
3. **Průběžný zápis:** po změně postupu, rozsahu, výsledku či blokátoru obnov
   tematický plán, registr a oba přehledy; do deníku přidej významnou událost.
   Podstatnou volbu zapiš do rozhodovacího logu: varianty, důvod, rozhodující
   osoba/pokyn, datum, dopad, odkazy. Nejasný záměr neoznačuj jako schválený.
4. **Ověření:** ulož příkazy/metodu, prostředí, výsledek a omezení. Implementace,
   CI, merge, deploy, aktivace a lidské převzetí jsou samostatné skutečnosti.
   Neprovedený krok je NOT RUN. Aktuální skutečnosti ověřuj v jejich zdroji;
   historická autorizace není nové povolení produkční operace.
5. **Předání/kompaktace:** ještě před koncem práce ulož hotové i otevřené kroky,
   blokátory a další konkrétní akci do souborů. CURRENT a handoff pouze shrnují
   a odkazují; nesmějí růst o denní historii. Chat je stručný doprovod.
6. **Sdílení:** kontrola dokumentace → commit příslušných souborů spolu se změnou
   → push vlastní větve → PR → kontroly → merge podle oprávnění. Zapiš skutečný
   stav předání; lokální soubor nebo otevřený PR ještě není společný main.
   Před integrací načti změny souběžných PR a zachovej jejich věcný obsah.
7. **Uzavření:** do plánu zapiš výsledek a zbývající etapy; do deníku uzavření
   a trvalé odkazy. V rozhodovacím logu přidej významné rozhodnutí nebo odkaz
   do rejstříku uzavřených prací. Teprve potom zkrať aktivní přehledy.
   Dokončený plán nemaž; musí být dostupný z logu i po ukončení vlákna.

Stavy: KONCEPT → PLÁNOVÁNO → ROZPRACOVÁNO → K OVĚŘENÍ → HOTOVO;
ODLOŽENO a BLOKOVÁNO vyžadují podmínku pokračování. ZAMÍTNUTO/NAHRAZENO
vyžadují důvod a odkaz na rozhodnutí/nástupce. „HOTOVO“ vždy upřesňuje rozsah
(např. implementace hotová, produkční přejímka otevřená). U drobné opravy stačí
doplnit existující tematický soubor; povinné odkazy a evidence stále platí.

## Minimální obsah tematického plánu

- ID/téma, datum, stav, vlastník a související položka registru.
- Cíl a rozsah, oprávnění, varianty/důvody a vazba na rozhodnutí.
- Zamýšlený postup, etapy, závislosti a ověřitelné podmínky dokončení.
- Větev, base/worktree, průběžné výsledky a odkazy na důkazy/PR.
- Otevřené kroky, blokátory a další konkrétní akce.

## Stručnost, historie a kontrola

CURRENT a hlavní handoff mají každý nejvýše 80 řádků. Nevkládej dlouhé odstavce
do jediné řádky jen kvůli limitu. Seznam celé práce patří do registru; podrobná
předání do tematických souborů. Deník se po zvětšení rozdělí na datované části
s trvalým rozcestníkem; původní odkazy a rozhodovací ID musí zůstat dohledatelné.
Přesun historie musí zachovat obsah, odkazy a důkaz zachování. Staré programové
boardy nejsou druhou aktuální frontou. Konkrétní automatizované bloky
`membership-morning-*` v aktuálních souborech zachovej pro serverový publikátor.

Povinně spusť `php tools/check-current-docs.php` a `git diff --check`.
Kontrola hlídá délky přehledů, základní propojení, místní odkazy a integritu
archivů. Věcnou úplnost a skutečnost tvrzení musí zkontrolovat autor/reviewer.
Citlivé podklady zůstávají v soukromém řízeném úložišti; v Gitu jen bezpečný
souhrn/reference. Hesla, tokeny, session, dumpy a osobní přílohy se necommitují.
