# COACH-ROSTER-01 — Moje skupiny a soupisky

Datum: 10. 11. 2026. Stav: PR #436 po přenosu na `main` s opravou #438, před vydáním. Vlastník: vývoj KIS; provozní přijetí: pověřený trenér a registrář. Větev `codex/coach-groups-rosters-20261110`, samostatný worktree původně z `fb593ad4`.

## Zadání a dosavadní stav

Testerka žádá přehled přidělených kroužků a soupisek s aktuálními dětmi, počty a bezpečným filtrem. Současné `moje_skupiny.php` ukazuje podskupiny a historii tréninků, nikoli členy. `coach_scope_grants` je výslovné časově omezené přidělení trenéra; rodičovská skupina sama nepřenáší oprávnění na podřízené soupisky (`CoachScopeTest`). `club_roster_members` je kanonická evidence členství. `memberRosterRead()` je určeno registráři a jeho view obsahuje mutační formuláře, proto se pro trenéra nepoužije přímo. Používá se `coachScopeTargetPredicate()` a stejná časová pravidla aktivního členství jako v kanonické soupisce, bez dědění podřízených jmen.

## Rozsah první verze

- Samostatná čtecí stránka `moje_soupisky.php` v pozici Trenér, položka „Moje skupiny a soupisky“. Původní stránka historie skupin zůstane dosažitelná odkazem.
- Seznam pouze přímo svěřených aktivních týmů. Žádné dědění práv z nadřazené skupiny. Server znovu ověří přístup i při přímém zadání ID v URL.
- Aktuální aktivní členové podle `club_roster_members` a jeho časových intervalů; bez čekajících registračních žádostí a bez historických jmen ukončených členů. Tyto stavy se projeví počtem a prázdným/aktuálním seznamem. Zobrazení čekajících a ukončených jmen vyžaduje samostatné oprávnění a návrh.
- Jméno, příjmení a případně datum narození pouze pro oprávněnou soupisku; žádné kontakty, RČ, fakturace ani odkazy do administrace osob. Filtr jména funguje jen uvnitř povolené soupisky.
- Období z kanonické sezony. Kapacita a místo/čas jen u jednoznačné platné nabídky přiřazené této soupisce; více nabídek s odlišnou kapacitou nesmí vytvořit zavádějící součet. Volná místa mají vycházet z existující kapacitní služby, která zohlední i čekající platební rezervace.
- Docházka: v této etapě jen orientační odkaz na existující trenérskou agendu, bez nových zápisů.

## Akceptace

1. Trenér vidí pouze své aktuálně svěřené soupisky a aktivní děti; cizí ID vrátí 403. Odvolaný nebo vypršený grant zmizí okamžitě po dalším čtení.
2. Schválená registrace se zobrazí až po vytvoření kanonického členství; ukončení členství ji odstraní. Bez duplicitních osob při více zdrojových nárocích.
3. Filtr jména a volba soupisky nepřekročí autorizovaný rozsah. Výstup nemá citlivé ani finanční sloupce a stránka nemá zápisové akce.
4. Přehled funguje při 360/390 px i na desktopu; registrářské editorové trasy se nemění.
5. Syntetické testy zahrnou Aligátory, cizí soupisku, změnu členství, odvolání grantu a nepřítomnost osobních/fakturačních údajů. Produkční čtecí UAT Aligátorů až po vydání, bez vytváření další skutečné registrace.

## Výsledek a otevřené kroky

Nová `moje_soupisky.php`, čtecí služba `includes/coach_roster_read.php` a položka trenérského rozcestníku. [Místní protokol](verification/coach-group-rosters-20261110/RESULTS.md) obsahuje syntetické testy a meze. Legacy `podskupiny` mají vlastní historickou obrazovku; tato etapa pracuje s kanonickými `club_teams` a soupiskami. Pro Aligátory je nutné ověřit konkrétní grant `scope_kind=team` na cílovou soupisku. Bez tohoto přidělení se jména bezpečně nezobrazí.

[PR #436](https://github.com/KovoPraha/evidenceTreninku/pull/436) měl všech 11 kontrol CI na `8ad1365f` PASS; po přenosu na `f39f3157` se kontroly opakují. Vlastník výslovně požádal o nasazení obou reportů. Před vydáním proběhl syntetický scénář Aligátorů; skutečné přihlášené ověření Aligátorů po schválení registrace a propojení s kanonickou soupiskou zůstává provozní UAT. Skutečný počet dětí, cena a personální přidělení se z názvu skupiny neodvozují.
