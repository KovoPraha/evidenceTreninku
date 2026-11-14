# Ověření trenérské docházky — 13. 11. 2026

## Pokračování 14. 11. 2026

Vlastník požádal o dokončení řešení blokátoru testerky. Větev po nových
změnách `main` znovu začlenila `origin/main` v `c1836273`. Jediný konflikt
`docs/PLAN_CATALOG.md` byl vyřešen zachováním odkazu na E4-06 i nového stavu
P11-PCAP. `php tools/check-current-docs.php` hlásí 0 chyb, `git diff --check`
prošel. PR #474 je stále pracovní návrh; merge, deploy ani produkční UAT zde
nejsou tvrzeny.

CI na předchozí špičce větve mělo 15 úspěšných a 6 neúspěšných kontrol.
Obě chyby Bootstrap assets skončily před měřením na porovnání společného
`hlavicka.php` s pevným historickým SHA, přestože oba porovnávané HTTP servery
používají stejnou aktuální verzi záhlaví. Kontrola je upravena tak, aby
ověřovala shodu záhlaví obou variant. Čtyři browser profily na MariaDB 11.4
skončily po úspěšné kontrole HTML/rolí/CSRF na neurčené konzolové chybě nebo
selhaném požadavku v základní variantě. Do jejich asercí byly doplněny
konkrétní diagnostické údaje; příčinu a opakovaný výsledek určí nové CI.

Aktualizace 14. 11.: po převzetí aktuálního `origin/main` (`b0d2368d`, merge `fff1207a`) prošlo lokálně 37 cílených testů / 237 kontrol a `php tools/check-current-docs.php` (0 chyb). Původní úplné CI a HTTP/prohlížečové výsledky níže platí pro uvedené starší commity; nové CI po pushi zatím NOT RUN.

Pracovní větev `codex/coach-attendance`, syntetická lokální MariaDB `attendance_demo` na portu 3318. Žádné produkční zápisy.

## Technické kontroly

- `CoachAttendanceTest`: datovaná soupiska, rodičovská i nová trenérská omluva s důvodem, rozpracovaný a konečný zápis, ztracená aktualizace, cizí trenér, rezervovaný náhradník včetně vyčerpání rezervace, následné opravy a historické vazby a lhůta s oprávněním vedoucího sportu — 6 testů / 33 kontrol, PASS.
- `TrainingClosureTest` + `ClubMakeupTest`: verzované uzavření a návaznost na náhrady — 30 testů / 200 kontrol, PASS.
- `AthleteTrainingTimelineTest` + `AthleteTimelineReadOptimizationTest`: 7 testů / 70 kontrol, PASS.
- `StaffWorkspaceRegistryTest`: 14 testů / 2335 kontrol, PASS po zařazení nové trasy do inventáře.
- `composer validate --strict`, PHP syntax, `git diff --check`, `php tools/check-current-docs.php` a `php tools/check-php-style.php` nad dotčenými soubory: PASS.
- `php bin/migrate.php --check --json` nad oddělenou lokální DB: `ok=true`, `current=true`.
- [Souvislý HTTP průchod náhrady](makeup-http-acceptance.json): 31/31 PASS na oddělené MariaDB 10.4.32. Z adaptované syntetické fixture `MakeupJourneyHttpAcceptance.php` se zdrojový trénink uzavřel novým `coach_attendance.php`; následovala včasná omluva, právě jeden nárok, rezervace rodičem, zobrazení náhradníka v cílové docházce, jeho přítomnost a oprava na nedostavení. Zachováno kmenové členství, vyzkoušena kapacita, odvolaný rodičovský přístup a CSRF. Cílové datum se posunulo až po rezervaci pouze v této vlastněné fixture, aby šel trénink uzavřít; nejde o produkční postup ani o časový test rezervace.
- `composer test`: skončil kvůli výchozímu pětiminutovému limitu Composeru při 46 % suite.
- Přímý `php vendor/bin/phpunit --configuration phpunit.xml.dist --no-progress` bez limitu: 2472 testů / 56 664 kontrol, 5 přeskočených, PASS nad dřívější revizí větve. Po doplnění vazby náhradníka, trenérské omluvy a formátování prošlo zacíleně 43 testů / 303 kontrol.
- [CI Testy 43454839175](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43454839175) nad `788a3ec1`: PHPUnit, MariaDB 10.3/11.4 integrační smoke a obě čtecí profilace včetně srovnání plánovače PASS. [PHP quality](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43454838901) a [kontrola dokumentace](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43454838981) PASS.
- [Jádro CI 43474589518](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43474589518) na `1cab13b2` po opravě konfliktního formuláře: PHPUnit, MariaDB 10.3/11.4 integrační smoke i obě čtecí profilace PASS. PHP quality, dokumentace a CodeQL rovněž PASS.
- Novější běh [rodičovského profilu 43464941487](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43464941487) prošel na MariaDB 10.3 i 11.4; zachycené konzolové chyby, selhané požadavky a HTTP chyby byly prázdné. Předchozí běh na 11.4 zaznamenal šest HTTP 404 bez URL, proto zatím není prokázaná příčina ani stabilita tohoto výkonového testu.
- Ostatní výkonové workflow stále nejsou zelené: [Bootstrap assets](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43464941609) porovnává `hlavicka.php` s pevným starším commitem, ačkoli se zde záhlaví nemění. [Historický](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43464941578), [měřicí](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43464941552) a [záhlavní](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43464941550) browser benchmark na MariaDB 11.4 selhal v základní variantě po úspěšné kontrole HTML, rolí a CSRF na konzolové chybě nebo neúspěšném požadavku; 10.3 prošla. Jde o neuzavřené kontroly před nasazením, nikoli o důkaz regrese docházky.
- Na aktuální špičce PR prošly historický a měřicí výkonový profil na obou verzích DB. [Rodičovský 43474588839](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43474588839) a [záhlavní 43474589432](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43474589432) profil na MariaDB 11.4 a [Bootstrap assets 43474588996](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43474588996) na obou verzích zůstaly červené. Rodičovský profil přitom v jiném běhu na obou verzích prošel; nestabilitu a příčinu je nutné uzavřít před vydáním.

## Prohlížečový průchod

Playwright CLI, Chromium, lokální PHP 8.2 server a syntetické účty. Před testem byl pouze v oddělené DB posunut čas demo plánu #1 na 13:00–14:30 téhož dne, aby šlo po skončení termínu bezpečně zkoušet zápis.

1. V aktivní pozici trenéra byl v plánovači u termínu vidět odkaz „Zapsat docházku“, který otevřel správný plán a jedno dítě z datované soupisky.
2. Na šířce 390 px se zobrazil stav „Nevyplněno“ odlišně od absence, velké volby i průběžný souhrn bez vodorovného přetékání. [Snímek](mobile-coach-attendance.png).
3. Volba „Přítomen“ a uložení vytvořily uzavřenou verzi 1 a potvrzení úspěchu. Opětovné otevření ukázalo uložený stav.
4. Oprava na „Nepřítomen“ vytvořila verzi 2, zachovala verzi 1 v historii a upravila souhrn.
5. Po přihlášení syntetického rodiče stránka `booking/soupiskove_treninky.php` u dítěte zobrazila „Nepřítomen“ u stejného termínu.
6. Na trenérské stránce nebyla hlášena chyba ani varování v konzoli. Jediná chyba na úvodním přihlášení byla chybějící favicon.
7. Po doplnění názvu datované soupisky se na telefonu zobrazily obě skupiny. Trenér přímo na šířce 390 px zadal omluvu s důvodem; vznikla verze 3 a rodičovská historie po pozdější opravě zobrazila stav „Omluven“, datum, čas a původní skupiny. Aktuální snímek ukazuje novou podobu stránky.
8. Dvě nezávislé relace otevřely stejnou verzi. Po uložení v první relaci druhá dostala HTTP 422, staré volby zůstaly označené jako neuložené, tlačítko se zablokovalo a odkaz načetl poslední uložený stav. Bezprostředně po odmítnutí bylo v DB právě pět verzí a stav z prvního uložení; odmítnutí nevytvořilo další revizi.
9. Druhý syntetický trenér dostal na cizí termín HTTP 403. Na starším termínu viděl trenér po 72 hodinách neaktivní uložení; vedoucí sportu stejný termín otevřel a uzavřel jako verzi 1.
10. Po poslední úpravě rozložení má stránka při šířce 390 px `scrollWidth=390`; tlačítko a vysvětlení jsou oddělené. Snímek byl obnoven po této úpravě.

Tyto průchody neověřily produkci ani fyzické zařízení. Placený náhradový cyklus byl přihlášeně ověřen přes HTTP formuláře a doménové testy, nikoli celý v grafickém prohlížeči. Stav zbývajících kontrol před vydáním:

| Scénář | Dosavadní důkaz | Zbývá |
| --- | --- | --- |
| Rodič omluví včas → trenér uzavře neúčast → vznikne právě jeden nárok → rodič jej vidí a rezervuje vhodný termín → náhradník je přítomen nebo chybí | Souvislý HTTP průchod 31/31 PASS, včetně opravy cílové účasti | Vizuální průchod rodiny a trenéra se skutečnou rezervací; úklid vlastní fixture |
| Pozdní omluva, přítomné dítě a plný nebo nepřípustný náhradní termín | Dosavadní testy náhrad | Prohlížečové ověření návaznosti nové docházky |
| Trenér cizí skupiny, oprava po 72 hodinách, vedoucí sportu a dvě současně otevřená okna | Integrační testy i samostatné prohlížečové relace PASS | Produkční uživatelské potvrzení |
| Omluva přijatá trenérem při lekci s důvodem | Integrační test, mobilní uložení a rodičovská historie PASS | Produkční uživatelské potvrzení |

Produkční UAT se skutečnými uživateli a zápisy se provede až v nově schváleném rozsahu. Starší testovací okno z `PRODUKCNI-UZIVATELSKE-TESTOVANI.md` skončilo a není oprávněním k novým zápisům.

Lokální PHP server a oddělená MariaDB na portu 3318 byly po průchodu zastaveny a port již nenaslouchá. Úklid syntetického datového adresáře `C:\xampp\mysql\attendance-local-data` a lokálních session souborů je **BLOCKED**: automatická kontrola příkazů odmítla odstranění jako „blocked by policy“. Databáze ani server neběží; odstranění souborů nelze tvrdit. Verze v PR ani produkční data tím nejsou dotčeny.
