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

`coach_attendance.php?plan_id=…` otevírá docházku přímo z rozvrhu. Očekávané děti načítá podle členství platného v den lekce, doplní rezervované náhradníky a omluvy. Uložená verze má přednost před předvyplněním. Nevyplněný stav zůstává prázdný; rodičovskou omluvu nelze uložit jako neomluvenou absenci. Trenér může při lekci zapsat novou omluvu s povinným důvodem přímo ve formuláři; důvod zůstává v revizi docházky a sám o sobě nezakládá nárok na náhradu. Bez rezervace nelze náhradníka ručně přidat.

První zápis vytvoří skutečný `treninky` záznam navázaný na plán a zkopíruje historický snapshot soupisky. Částečná docházka nebo zápis před koncem lekce vytvoří verzi `draft`; nepřepisuje `trenink_sportovec` ani nečerpá náhradní lekci. Po konci lekce úplný zápis vytvoří `closed` revizi; jen přítomné děti se promítnou do `trenink_sportovec` a stávající náhradový proces se vyhodnotí. `attendance_states` ve `snapshot_json` uzavření rozlišuje přítomnost, neomluvenou absenci a omluvu bez nové paralelní tabulky. Každá oprava je další verze s identitou aktéra v `closed_by`; staré verze zůstávají.

Trenér může zapisovat a opravovat do 72 hodin po konci termínu. Po lhůtě to smí pracovník s aktivní pozicí vedoucího sportu. Při souběhu se kontroluje číslo verze; neaktuální odeslání se odmítne. U již uzavřené docházky se nepřipouští neúplná oprava. Historie verze a její jednotlivé stavy jsou na stránce termínu. Rodič a sportovec vidí konečný stav dítěte v `booking/soupiskove_treninky.php`, včetně náhradní lekce mimo kmenovou soupisku.

## Ověření a provozní hranice

Technické testy této větve: `CoachAttendanceTest` pokrývá časově platnou soupisku, omluvu, částečný a konečný zápis, souběžnou verzi, cizího trenéra a rezervovaného náhradníka. `TrainingClosureTest` pokrývá zachování staré přítomnosti při rozpracovaném záznamu, konečnou absenci a atomické začlenění do transakce. Výsledky celé sady a prohlížečového průchodu jsou v [ověřovacím protokolu](verification/coach-attendance-20261113/RESULTS.md).

Implementace je zatím jen v pracovní větvi. Produkční nasazení ani přihlášené uživatelské ověření se tímto dokumentem netvrdí. Před nasazením je potřeba dokončit zbývající scénáře zadání v izolované instalaci, zvlášť plný provozní tok náhrad a oprávnění vedoucího sportu v prohlížeči. Produkční UAT vyžaduje schválený testovací rozsah podle `PRODUKCNI-UZIVATELSKE-TESTOVANI.md`.

## Akceptační průchod před vydáním

Použít oddělenou instalaci s označenými syntetickými účty: trenér A vlastní skupiny, trenér B jiné skupiny, vedoucí sportu, rodič a dítě s platným placeným členstvím. Připravit dva termíny, datovanou změnu soupisky a jeden vhodný náhradní termín s volným místem. Zaznamenat ID a stav všech testovacích řádků před průchodem; po něm odstranit pouze tyto označené řádky a ověřit zachování starších dat. Bez tohoto průchodu nepokládat funkci za připravenou k nasazení.

| # | Postup a očekávaný výsledek |
|---|---|
| A01 | Trenér A otevře vlastní termín z rozvrhu; stránka ukáže datum, čas a název datované soupisky. |
| A02 | Porovnat termíny před a po změně soupisek a ukončení členství; každý ukáže právě děti platné v jeho den, bez duplicit. |
| A03 | Uložit jedno dítě jako přítomné a další ponechat nevyplněné; vznikne rozpracovaná verze, nevyplněné není absence. |
| A04 | Po konci lekce označit jiné dítě nepřítomné, doplnit všechny stavy a uložit; vznikne uzavřená verze a správný souhrn. |
| A05 | Rodič omluví dítě před termínem; trenér vidí důvod a stav Omluven. Pokus změnit jej na neomluvenou absenci je odmítnut. |
| A06 | Znovu otevřít termín; poslední uložené stavy a předchozí verze jsou viditelné. |
| A07 | Trenér A ve lhůtě 72 hodin opraví stav; nová verze zachová původní historii a aktéra. |
| A08 | Po lhůtě trenér A opravu neuloží; vedoucí sportu s aktivní pozicí ji může provést. |
| A09 | Z včasné rodičovské omluvy a uzavřené neúčasti vznikne právě jeden nárok. Rodič rezervuje vhodný termín; náhradník se objeví jen u něj a má viditelný štítek a ID rezervace. |
| A10 | Zapsat náhradníka jako přítomného a v další rezervaci jako nepřítomného; stav rezervace odpovídá výsledku, kmenové členství a soupiska se nezmění. Bez rezervace nelze přidat cizí dítě. |
| A11 | V historii termínu dohledat všechna dítětem zapsaná rozhodnutí a jejich verze; v historii dítěte datum, skupinu, absenci/omluvu i náhradní účast po pozdějším přesunu skupiny. |
| A12 | Trenér B nemůže termín trenéra A otevřít ani odeslat jeho docházku. |
| A13 | Na telefonu o šířce 390 px projít označení, trenérskou omluvu s důvodem, průběžný souhrn, uložení a potvrzení bez vodorovného posunu. |
| A14 | Otevřít tentýž termín ve dvou nezávislých relacích; po prvním uložení se druhé se starou verzí odmítne a první změna zůstane. |

Pro produkční přejímku se scénáře zopakují jen v odsouhlaseném okně, s vlastníkem určenými TEST účty a auditovaným úklidem. Do protokolu zapsat u každého bodu PASS/FAIL/BLOCKED, roli, čas a důkaz; samotný technický test ani HTTP odpověď nejsou uživatelské potvrzení.
