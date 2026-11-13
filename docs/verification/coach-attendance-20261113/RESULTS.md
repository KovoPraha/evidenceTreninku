# Ověření trenérské docházky — 13. 11. 2026

Pracovní větev `codex/coach-attendance`, syntetická lokální MariaDB `attendance_demo` na portu 3318. Žádné produkční zápisy.

## Technické kontroly

- `CoachAttendanceTest`: datovaná soupiska, rodičovská i nová trenérská omluva s důvodem, rozpracovaný a konečný zápis, ztracená aktualizace, cizí trenér, rezervovaný náhradník včetně vyčerpání rezervace, následné opravy a historické vazby a lhůta s oprávněním vedoucího sportu — 6 testů / 33 kontrol, PASS.
- `TrainingClosureTest` + `ClubMakeupTest`: verzované uzavření a návaznost na náhrady — 30 testů / 200 kontrol, PASS.
- `AthleteTrainingTimelineTest` + `AthleteTimelineReadOptimizationTest`: 7 testů / 70 kontrol, PASS.
- `StaffWorkspaceRegistryTest`: 14 testů / 2335 kontrol, PASS po zařazení nové trasy do inventáře.
- `composer validate --strict`, PHP syntax, `git diff --check`, `php tools/check-current-docs.php` a `php tools/check-php-style.php` nad dotčenými soubory: PASS.
- `php bin/migrate.php --check --json` nad oddělenou lokální DB: `ok=true`, `current=true`.
- `composer test`: skončil kvůli výchozímu pětiminutovému limitu Composeru při 46 % suite.
- Přímý `php vendor/bin/phpunit --configuration phpunit.xml.dist --no-progress` bez limitu: 2472 testů / 56 664 kontrol, 5 přeskočených, PASS nad dřívější revizí větve. Po doplnění vazby náhradníka, trenérské omluvy a formátování prošlo zacíleně 43 testů / 303 kontrol.
- [CI Testy 43454839175](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43454839175) nad `788a3ec1`: PHPUnit, MariaDB 10.3/11.4 integrační smoke a obě čtecí profilace včetně srovnání plánovače PASS. [PHP quality](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43454838901) a [kontrola dokumentace](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43454838981) PASS.
- Novější běh [rodičovského profilu 43464941487](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43464941487) prošel na MariaDB 10.3 i 11.4; zachycené konzolové chyby, selhané požadavky a HTTP chyby byly prázdné. Předchozí běh na 11.4 zaznamenal šest HTTP 404 bez URL, proto zatím není prokázaná příčina ani stabilita tohoto výkonového testu.
- Ostatní výkonové workflow stále nejsou zelené: [Bootstrap assets](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43464941609) porovnává `hlavicka.php` s pevným starším commitem, ačkoli se zde záhlaví nemění. [Historický](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43464941578), [měřicí](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43464941552) a [záhlavní](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43464941550) browser benchmark na MariaDB 11.4 selhal v základní variantě po úspěšné kontrole HTML, rolí a CSRF na konzolové chybě nebo neúspěšném požadavku; 10.3 prošla. Jde o neuzavřené kontroly před nasazením, nikoli o důkaz regrese docházky.

## Prohlížečový průchod

Playwright CLI, Chromium, lokální PHP 8.2 server a syntetické účty. Před testem byl pouze v oddělené DB posunut čas demo plánu #1 na 13:00–14:30 téhož dne, aby šlo po skončení termínu bezpečně zkoušet zápis.

1. V aktivní pozici trenéra byl v plánovači u termínu vidět odkaz „Zapsat docházku“, který otevřel správný plán a jedno dítě z datované soupisky.
2. Na šířce 390 px se zobrazil stav „Nevyplněno“ odlišně od absence, velké volby i průběžný souhrn bez vodorovného přetékání. [Snímek](mobile-coach-attendance.png).
3. Volba „Přítomen“ a uložení vytvořily uzavřenou verzi 1 a potvrzení úspěchu. Opětovné otevření ukázalo uložený stav.
4. Oprava na „Nepřítomen“ vytvořila verzi 2, zachovala verzi 1 v historii a upravila souhrn.
5. Po přihlášení syntetického rodiče stránka `booking/soupiskove_treninky.php` u dítěte zobrazila „Nepřítomen“ u stejného termínu.
6. Na trenérské stránce nebyla hlášena chyba ani varování v konzoli. Jediná chyba na úvodním přihlášení byla chybějící favicon.

Tento průchod neověřil produkci ani fyzické zařízení. Pro vydání zbývá izolovaný přihlášený průchod se dvěma trenéry, vedoucím sportu, rodičem a placeným syntetickým členstvím:

| Scénář | Dosavadní důkaz | Zbývá |
| --- | --- | --- |
| Rodič omluví včas → trenér uzavře absenci → vznikne právě jeden nárok → rodič jej vidí a rezervuje vhodný termín → náhradník je přítomen nebo chybí | Jednotlivé přechody pokrývají integrační testy | Celý tok v prohlížeči včetně úklidu rezervace |
| Pozdní omluva, přítomné dítě a plný nebo nepřípustný náhradní termín | Dosavadní testy náhrad | Prohlížečové ověření návaznosti nové docházky |
| Trenér cizí skupiny, oprava po 72 hodinách, vedoucí sportu a dvě současně otevřená okna | Integrační testy oprávnění a verze | Prohlížečový průchod se samostatnými relacemi |
| Omluva přijatá trenérem při lekci s důvodem | Integrační test a historie revizí | Kontrola na telefonu a v rodičovské historii |

Produkční UAT se skutečnými uživateli a zápisy se provede až v nově schváleném rozsahu. Starší testovací okno z `PRODUKCNI-UZIVATELSKE-TESTOVANI.md` skončilo a není oprávněním k novým zápisům.
