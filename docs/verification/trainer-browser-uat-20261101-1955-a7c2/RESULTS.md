# Trenérský browser UAT — 20261101-1955-a7c2

Historický protokol před nasazením opravy. Následné vydání PR #269 a omezené
produkční ověření jsou v [protokolu nasazení](../trainer-reset-release-20261101/RESULTS.md).
Původní výsledky ani hlášení vlastníka se zpětně nemění na PASS.

Protokol konkrétního průchodu, 1. 11. 2026, Europe/Prague. První část byla blokována přihlášením; níže jsou aktuální výsledky po součinnosti vlastníka. Žádný neprovedený krok není označen PASS.

- Označení běhu: `TEST-UAT-AUTO-20261101-1955-a7c2`.
- Čas prvního přesně zaznamenaného kontrolního bodu: 2026-11-01 19:55:36 Europe/Prague.
- První předání: 2026-11-01 19:57:32 Europe/Prague. Navázání po potvrzení vlastníka: kontrolní bod 20:59:57 Europe/Prague; pozorování T02–T06 a mobilní kontroly do 21:03:32 Europe/Prague. Konec druhé části a závěrečný kontrolní bod: **2026-11-01 21:07:48 Europe/Prague**.
- Cíl: https://kis.kovopraha.cz.
- Pracovní kopie: vyhrazené worktree `evidencePavel-trainer-pilot-20261031`; výchozí Git čistý, větev `codex/trainer-uat-guides-20261101`, HEAD `66014555cc3b3c48a5852f72de7f37bb3ad39b30`.
- Po fetch: origin/main `97a0ff213a871639f7708d99613282f73652302e`. [PR #268](https://github.com/KovoPraha/evidenceTreninku/pull/268) MERGED 2026-11-01 19:23:31 Europe/Prague. Pět požadovaných dokumentů je shodných s origin/main.
- Přečteno: CLAUDE.md, CURRENT_STATE.md, HANDOFF_CURRENT.md, oba požadované UAT postupy a doklady založení pilotu. Historické výsledky nejsou započteny do dnešního browser UAT.
- Prohlížeč: existující Google Chrome s podporovaným browser rozhraním, samostatná testovací karta. Existující správcovská karta nebyla převzata ani použita jako testovací identita. Karty stejného profilu nejsou izolované relace.
- Ověřená identita: `TEST - Trenérský pilot 20261031`, pozice Trenér, vlastní trenér 56 potvrzen vybraným zamčeným polem ve formuláři; soupiska 77, TEST Alfa 11783, TEST Beta 11784, kontrolní osoba 11785.
- Grant a aktuální rozsah: čtecí kontrola [41339100369](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41339100369) SUCCESS, `action=status`, `mode=operational`, bez osobního e-mailu ve vstupu. Pozorování 2026-11-01 19:56:05 Europe/Prague: `read_only=true`, trenér 56, soupiska 77, členové 11783/11784, `visible_test_people=2`, `scope_ready=true`, `outside_scope_visible=false`, `reset_eligible=true`. Grant `2026-11-01 18:17:31` až `2026-11-15 18:17:31` databázového času; aktuální DB predikát rozsahu vrací obě osoby. Kontrola ověřuje aktivní účet, jedinou pracovní pozici coach, absenci superadmina a jediný neodvolaný grant. Žádné `create`, prodloužení nebo změna oprávnění. Tento technický důkaz nenahrazuje T02/T03/T06 v prohlížeči.
- Souběžné použití účtu: vlastník výslovně potvrdil, že jiný test neběží, před první změnou.
- Součinnost: vlastník potvrdil skutečné doručení e-mailu, nastavení hesla a přihlášení. Dodatečně na přímou otázku k opětovnému otevření již použitého odkazu odpověděl: „Ne, odkaz umožnil další změnu hesla.“ T01 proto hodnocen FAIL podle asistovaného hlášení; agent druhé uložení hesla nepozoroval. Hesla, schránka a obnovovací odkazy se nečtou.
- Výchozí desktop: Chrome, screenshot 1521 × 667 px obsahu (se svislým posuvníkem); mobilní emulace `innerWidth=390`, `innerHeight=844`, obsahová šířka 375 px kvůli posuvníku. Konkrétní verze Chrome nezjišťována.

| Krok | Stav | Skutečně pozorovaný výsledek / důkaz | Způsob |
|---|---|---|---|
| T01 | FAIL | Obnova, skutečné doručení a nastavení hesla potvrzeny vlastníkem; následná platná testovací relace pozorována agentem. Vlastník dodatečně uvádí, že již použitý odkaz umožnil další změnu hesla. To odporuje očekávanému odmítnutí. Nález **T01-P2-01**, podrobnosti níže. | Asistované hlášení vlastníka; druhé uložení hesla a technická příčina nebyly automaticky ověřeny. |
| T02 | PASS | `/prehled_sportovcu.php` i `/pracovni_pozice.php` zobrazují testovací identitu a Trenér; rozcestník nenabízí přepnutí pozice ani správce. Vlastní trenér 56 ve formuláři. Důkaz: textová pozorování níže a [T04](T04-created.jpg). | Ruční přihlášení vlastníkem, následná kontrola automaticky. |
| T03 | PASS | Přehled ukazuje právě dvě osoby, odkazy `/sportovec_detail.php?id=11783` a `?id=11784` otevřely správné profily. Hledání `TEST Mimo rozsah` potvrzené Enterem vrací „Pro zadané filtrovací podmínky nebyli nalezeni žádní sportovci.“ Důkaz: text níže, [mobilní Alfa](T08-person-mobile.jpg). | Automaticky přes UI. |
| T04 | PASS | Jediné odeslání vytvořilo trénink **1604**, datum 2026-11-01, 1.00 h, správné označení a přesně Alfa/Beta; žádný spolutrenér, rezervace, příloha nebo měření. V Moje tréninky právě jedna položka; editovací odkaz nese ID 1604. [Důkaz](T04-created.jpg). | Automaticky přes UI, technická kontrola grantu samostatně. |
| T05 | PASS | Otevřen UI odkaz `edit_trenink.php?id=1604`; jediné uložení doplnilo „– upraveno“. Nový GET a reload Moje tréninky: jediná položka, stejný editovací odkaz/ID, 1.00 h, datum a přesně stejní účastníci. [Důkaz po reloadu](T05-edited.jpg). | Automaticky přes UI. |
| T06 | PASS | Před zápisem otevřeny obě zadané adresy při platné trenérské relaci; obě výslovně „Přístup odepřen“ / „Tato stránka patří do jiné pracovní pozice“, bez finančních či osobních dat. Návrat na svěřence znovu ukázal platnou testovací relaci. Důkaz: přesná textová pozorování níže. | Automaticky; pouze GET, HTTP status nástroj neposkytl. |
| T07 | BLOCKED | Relace A v Chrome ponechána přihlášená na svěřencích během žádosti o součinnost. Vlastník vyzván k další obnově v jiném prohlížeči (např. Firefox), zkoušce hesel, replay a odhlášení B. Do dokončení části nepřišlo potvrzení ani důkaz resetu v B. Kontrolní reload A stále ukazoval platnou testovací relaci; bez potvrzené změny hesla to není důkaz selhání revokace. Potom A řádně odhlášena. Dvě karty Chrome nebyly považovány za izolaci. | Asistované, neověřeno; chybí potvrzená izolovaná B, další obnova a výsledky zkoušek hesel/replay. |
| T08 | PASS | Emulace 390 × 844: trénink i TEST Alfa čitelné, tlačítka dostupná posunem, `scrollWidth=clientWidth=375` bez vodorovného přetečení. Po odhlášení přihlašovací formulář: šířka 390, výška 844, bez přetečení, dostupná obě pole, tlačítko a obnova hesla. [Účastníci a ovládání tréninku](T08-training-controls.jpg), [profil](T08-person-mobile.jpg), [přihlášení](T08-login-mobile.jpg). Viewport vrácen. | Automatická vizuální emulace; skutečný telefon ani opakované mobilní přihlášení heslem nebyly testovány. |

## Průběžné důkazy

- Browser pozorování: `/booking/zapomenute_heslo.php?pro=trener`, titul „Obnova hesla — Kovopraha“, nadpis „Obnova trenérského hesla“, prázdné pole „E-mail trenérského účtu“, tlačítko „Poslat odkaz pro obnovu“ a odkaz na `/login.php`. Textový důkaz neobsahuje osobní údaje ani tokeny.
- Git a PR ověřeny aktuálním fetch a dotazem na stav PR, nikoli převzetím historického PASS.

## Zápisy, nálezy a dokončení

- Vytvoření tréninku: **1**; ID: **1604**; úpravy: **1**. Finální náplň `TEST-UAT-AUTO-20261101-1955-a7c2 – upraveno`. Záznam ponechán pro diagnostiku, nic se nemaže.
- Nález **T01-P2-01**: vlastník hlásí možnost další změny hesla již použitým odkazem. Bez automatické reprodukce a bez určení příčiny. Chybějící součinnost T07 není klasifikována jako P2.
- Bez oprav kódu, merge, nasazení, změn účtů či oprávnění nebo jiných provozních akcí. Čtecí workflow používá dočasné diagnostické soubory a nemění aplikační data.
- Závěrečné odhlášení a ochrana stránky: **PASS**. Kliknutí na „Odhlásit“ z platné testovací relace vrátilo veřejný portál. Následný nový GET `/prehled_sportovcu.php` převedl na `/login.php` s nadpisem „Evidence tréninků“ a tlačítkem „Přihlásit se“, bez zobrazení svěřenců. [Bezpečný snímek po odhlášení](T08-login-mobile.jpg). Před uložením snímku byla automaticky předvyplněná pole vyprázdněna; žádné heslo se nečetlo ani neodesílalo.
- Viewport override nastaven pouze pro emulaci a po kontrole profilu zrušen. Po navigaci nástroj jednou vracel vizuálně zmenšený snímek; opětovné nastavení stejného viewportu obnovilo správný snímek při nezměněných DOM rozměrech. Nehodnoceno jako chyba aplikace.
- Aktuální výsledek po dodatečném hlášení vlastníka: **6 PASS, 1 FAIL, 1 BLOCKED, 0 NOT RUN**. T01 FAIL-assisted, T07 není proveden a zůstává odložen vlastníkem. Nejde o úplné UAT ani obecné schválení produkce.
- Doporučený navazující zásah: prioritně prověřit odmítnutí již použitého odkazu na serveru při odeslání nové změny hesla a rozlišit zobrazení formuláře od skutečně přijaté druhé změny. Pokud se opakovaná změna potvrdí, opravit jednorázovost a atomické spotřebování odkazu a ověřit opravu. Příčina zde není určena; v tomto běhu se kód neopravuje ani nenasazuje. T07 provést později v nově navázané A a izolované B. Historické technické testy nález ani blokaci nenahrazují.
- Změněny pouze nové dokumentační artefakty tohoto běhu. Žádný commit, push, merge, deploy, účetní či finanční operace, rezervace, import, změna účtů nebo oprávnění.

## Textový důkaz autorizace a uložení

- T02: titul „Trenér – pracovní rozcestník“, jméno `TEST - Trenérský pilot 20261031`, pozice „Trenér“. Žádný přepínač na správce. Čtecí kontrola grantu navíc ověřila jedinou pozici coach a absenci superadmina.
- T06, `https://kis.kovopraha.cz/eshop_payments_admin.php`: titul „Přístup odepřen“, nadpis „Tato stránka patří do jiné pracovní pozice“, vlastník agendy „Hospodář a platby“, pouze odkaz zpět na rozcestník.
- T06, `https://kis.kovopraha.cz/sportovec_karta.php?sportovec_id=11785`: stejný titul/nadpis, vlastník agendy „Registrář členů a KIS“, pouze odkaz zpět. Žádný obsah osoby.
- T03: přehled bez filtrů obsahoval právě řádky Alfa/Beta a „2 sportovců“. Oba profily zobrazily správné testovací jméno a datum narození syntetického fixture, prázdný e-mail. Žádné skutečné sportovce nebylo třeba otevírat.
- T04 před odesláním: datum 2026-11-01, délka 1, náplň s označením; ze všech checkboxů vybrány pouze soupiska 77 a vlastní trenér 56, účastníci v UI přesně Alfa/Beta. Kategorie je podle formuláře nepovinná, ponechána prázdná.
- T04 po jediném kliknutí došlo při následném čtení k timeoutu browser nástroje. Formulář nebyl znovu odeslán; nový GET Moje tréninky našel jediný záznam. Z něj ověřeny obsah, datum, 1.00 h, účastníci a skutečný odkaz `edit_trenink.php?id=1604`.
- T05 po jediném uložení byl proveden nový GET a reload seznamu; DOM akordeonu obsahoval právě jeden trénink a jediný editovací odkaz se stejným ID. Uložené hodnoty odpovídají snímku.
- Do protokolu a screenshotů nejsou ukládány osobní e-maily, hesla, cookies, resetovací odkazy, formulářové klíče ani jména skutečných osob. Společné názvy filtrů a nabídka spolutrenérů nebyly použity k přístupu k dalším osobám.

## Navazující samostatná kontrola po odložení asistovaných kroků

1. 11. 2026, kontrolní bod 21:18:53 Europe/Prague a bezprostředně navazující browser průchod. Vlastník výslovně odložil dosud chybějící asistované kroky a není nyní dostupný. V tomto kontrolním bodu byly T01/T07 BLOCKED (odloženo vlastníkem), nikoli PASS. Následující dodatečné hlášení později změnilo výsledek T01 na FAIL.

Nová testovací karta ve stejném Chrome ověřila, že přihlášená testovací relace po předchozím řádném odhlášení neexistuje. Byly samostatně provedeny následující GET navigace, bez zadávání nebo čtení přihlašovacích údajů:

| Vyžádaná stránka | Skutečný výsledek | Hodnocení |
|---|---|---|
| `/prehled_sportovcu.php` | Přechod na `/login.php`, nadpis „Evidence tréninků“, testovací identita nezobrazena. | PASS ochrany po odhlášení |
| `/moje_treninky.php` | Přechod na `/login.php`, nadpis „Evidence tréninků“, viditelné tlačítko „Přihlásit se“. | PASS ochrany po odhlášení |
| `/edit_trenink.php?id=1604` | Přechod na `/login.php`, stejný nadpis a přihlašovací tlačítko, obsah tréninku nezobrazen. | PASS ochrany po odhlášení |
| `/formular.php` | Přechod na `/login.php`, stejný nadpis a přihlašovací tlačítko, tréninkový formulář nezobrazen. | PASS ochrany po odhlášení |

Jde o doplňující anonymní kontrolu, nikoli náhradu ověření oprávnění v platné relaci T06 nebo revokace po změně hesla T07. Nebyl založen ani upraven další záznam; celkově stále jedno vytvoření a jedna úprava, ID 1604. Přihlašovací formulář nebyl odeslán. Viewport nebyl znovu měněn a pomocná karta byla uzavřena. Další neprovedené přihlášené scénáře nad původní rozsah nejsou zahrnuty do výsledku. Tehdejší výsledek byl **6 PASS, 0 FAIL, 2 BLOCKED**; aktuální výsledek po dodatečném hlášení T01 je **6 PASS, 1 FAIL, 1 BLOCKED**.

## T01-P2-01 — hlášené opakované použití obnovovacího odkazu

- Krok / závažnost: **T01, P2 – nefunkční bezpečnostní postup obnovy hesla**. Přístup k cizím datům nebyl pozorován; neklasifikuje se jako P1 podle stupnice tohoto zadání.
- Čas evidence hlášení: **2026-11-01 21:23:19 Europe/Prague**. Přesný čas uživatelského opakování není znám.
- Prohlížeč / viewport: vlastník prováděl asistovanou část mimo pozorování agenta; konkrétní prohlížeč a viewport druhého použití nejsou potvrzeny. Automatizovaná část běhu používala Chrome, jeho parametry nelze vydávat za parametry tohoto uživatelského kroku.
- Vstupní URL: `https://kis.kovopraha.cz/booking/zapomenute_heslo.php?pro=trener`. Přesná cesta cílové resetovací stránky není v důkazech zaznamenána; odkaz s tokenem se nevyžaduje ani neukládá.
- Reprodukční postup podle scénáře: 1) Vyžádat trenérskou obnovu. 2) Otevřít doručený odkaz a nastavit heslo. 3) Znovu otevřít stejný již použitý odkaz. 4) Zkusit další změnu hesla. Agent tyto asistované kroky neprováděl.
- Očekávání: již použitý odkaz je odmítnut a další změnu hesla neumožní.
- Skutečnost / důkaz: vlastník v odpovědi na otázku, zda aplikace další použití odmítla, uvedl přesně **„Ne, odkaz umožnil další změnu hesla.“** To je podklad FAIL-assisted. Dříve samostatně potvrdil doručení, nastavení hesla a přihlášení.
- Omezení: není samostatně doloženo, zda při druhém pokusu došlo až k úspěšnému uložení dalšího hesla, nebo vlastník posuzoval možnost zobrazeného formuláře. Agent nepotvrzuje skutečný zápis druhé změny, opakovatelnost ani technickou příčinu. Bezpečná kontrolovaná reprodukce je navazující práce se součinností vlastníka.
- Screenshot: záměrně nepořízen; neukládají se resetovací odkazy ani údaje z heslového formuláře. Důkaz je asistované textové hlášení.
- Akce v tomto běhu: aktualizován protokol a výsledek T01; žádná další obnova, změna hesla, oprava kódu nebo produkční změna nebyla provedena.
