# Docházka trenéra z rozvrhu — audit a stav realizace

Datum: 13. 11. 2026. Výchozí kód: `origin/main` na `bc8da209fc469df58f91e27a2fce2340ba47f785`.
Původní lokální checkout obsahoval jinou rozpracovanou práci; tato změna vznikla v samostatném worktree.

## Audit dosavadního toku

| Oblast | Zjištěný zdroj a chování |
|---|---|
| Plánovač | `planovac.php` zobrazuje konkrétní `planovane_treninky`; dosud vedl na obecný formulář skutečného tréninku a oddělené uzavření. |
| Soupiska | `training_roster_links` a `training_roster_expected` drží datovaný snapshot. `kisRosterEffectiveMemberIds` vyhodnocuje platnost člena v den termínu; více soupisek vrací jedno ID sportovce. |
| Skutečná účast | `treninky` a `trenink_sportovec` jsou stávající kanonické záznamy přítomnosti. `training_closures` a `training_closure_revisions` drží verzované uzavření, aktéra a optimistickou kontrolu verze. |
| Omluvy | `training_rsvps.response='not_going'` a `training_personal_excuses` jsou zdroje omluvy. RSVP není samo o sobě skutečná docházka. |
| Náhradníci | `club_makeup_reservations` propojuje náhradníka s cílovým plánem; `clubMakeupFinalizeTarget` po uzavření rozliší účast a nedostavení bez změny kmenové soupisky. |
| Oprávnění | Aktivní pracovní pozice `coach` nebo `sports_lead`, vlastnictví plánu, CSRF a kontrola aktéra při transakčním zápisu. Původní uzavření už odmítalo starou verzi. |
| Historie dítěte | `athleteTrainingTimeline` zahrnuje plánované i uskutečněné tréninky, ale samotné nepřítomné děti v `trenink_sportovec` nemá. |

## Napojení nové funkce

`coach_attendance.php?plan_id=…` otevírá docházku přímo z rozvrhu. Očekávané děti načítá podle členství platného v den lekce, doplní rezervované náhradníky a omluvy. Uložená verze má přednost před předvyplněním. Nevyplněný stav zůstává prázdný; rodičovskou omluvu nelze uložit jako neomluvenou absenci. Bez rezervace nelze náhradníka ručně přidat.

První zápis vytvoří skutečný `treninky` záznam navázaný na plán a zkopíruje historický snapshot soupisky. Částečná docházka nebo zápis před koncem lekce vytvoří verzi `draft`; nepřepisuje `trenink_sportovec` ani nečerpá náhradní lekci. Po konci lekce úplný zápis vytvoří `closed` revizi; jen přítomné děti se promítnou do `trenink_sportovec` a stávající náhradový proces se vyhodnotí. `attendance_states` ve `snapshot_json` uzavření rozlišuje přítomnost, neomluvenou absenci a omluvu bez nové paralelní tabulky. Každá oprava je další verze s identitou aktéra v `closed_by`; staré verze zůstávají.

Trenér může zapisovat a opravovat do 72 hodin po konci termínu. Po lhůtě to smí pracovník s aktivní pozicí vedoucího sportu. Při souběhu se kontroluje číslo verze; neaktuální odeslání se odmítne. U již uzavřené docházky se nepřipouští neúplná oprava. Historie verze a její jednotlivé stavy jsou na stránce termínu. Rodič a sportovec vidí konečný stav dítěte v `booking/soupiskove_treninky.php`, včetně náhradní lekce mimo kmenovou soupisku.

## Ověření a provozní hranice

Technické testy této větve: `CoachAttendanceTest` pokrývá časově platnou soupisku, omluvu, částečný a konečný zápis, souběžnou verzi, cizího trenéra a rezervovaného náhradníka. `TrainingClosureTest` pokrývá zachování staré přítomnosti při rozpracovaném záznamu, konečnou absenci a atomické začlenění do transakce. Výsledky celé sady a prohlížečového průchodu jsou v [ověřovacím protokolu](verification/coach-attendance-20261113/RESULTS.md).

Implementace je zatím jen v pracovní větvi. Produkční nasazení ani přihlášené uživatelské ověření se tímto dokumentem netvrdí. Před nasazením je potřeba dokončit zbývající scénáře zadání v izolované instalaci, zvlášť plný provozní tok náhrad a oprávnění vedoucího sportu v prohlížeči. Produkční UAT vyžaduje schválený testovací rozsah podle `PRODUKCNI-UZIVATELSKE-TESTOVANI.md`.
