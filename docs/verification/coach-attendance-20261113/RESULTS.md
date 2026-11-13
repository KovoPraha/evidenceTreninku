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
- Přímý `php vendor/bin/phpunit --configuration phpunit.xml.dist --no-progress` bez limitu: 2472 testů / 56 664 kontrol, 5 přeskočených, PASS nad dřívější revizí větve. Po doplnění vazby náhradníka, trenérské omluvy a formátování prošlo zacíleně 43 testů / 303 kontrol. Celou aktuální revizi ověřuje CI v PR #474.

## Prohlížečový průchod

Playwright CLI, Chromium, lokální PHP 8.2 server a syntetické účty. Před testem byl pouze v oddělené DB posunut čas demo plánu #1 na 13:00–14:30 téhož dne, aby šlo po skončení termínu bezpečně zkoušet zápis.

1. V aktivní pozici trenéra byl v plánovači u termínu vidět odkaz „Zapsat docházku“, který otevřel správný plán a jedno dítě z datované soupisky.
2. Na šířce 390 px se zobrazil stav „Nevyplněno“ odlišně od absence, velké volby i průběžný souhrn bez vodorovného přetékání. [Snímek](mobile-coach-attendance.png).
3. Volba „Přítomen“ a uložení vytvořily uzavřenou verzi 1 a potvrzení úspěchu. Opětovné otevření ukázalo uložený stav.
4. Oprava na „Nepřítomen“ vytvořila verzi 2, zachovala verzi 1 v historii a upravila souhrn.
5. Po přihlášení syntetického rodiče stránka `booking/soupiskove_treninky.php` u dítěte zobrazila „Nepřítomen“ u stejného termínu.
6. Na trenérské stránce nebyla hlášena chyba ani varování v konzoli. Jediná chyba na úvodním přihlášení byla chybějící favicon.

Tento průchod neověřil produkci, fyzické zařízení ani úplný provozní tok náhradní lekce. Tyto body zůstávají samostatnou akceptací před nasazením.
