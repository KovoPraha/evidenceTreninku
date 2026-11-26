# KIS-ROSTER-CALENDAR-01 — ověření 26. 11. 2026

Prostředí: Windows, PHP 8.2.12, PHPUnit 11.5.56; izolovaná SQLite fixture.
Žádný produkční zápis, změna tréninku ani členství. Větev
`codex/athlete-roster-calendar-20261126` z `origin/main` `cbc0da53`.
Implementační commit `882cce77` je pushnutý do [PR #543](https://github.com/KovoPraha/evidenceTreninku/pull/543).

| Kontrola | Výsledek | Hranice |
| --- | --- | --- |
| Zdrojové cesty veřejný HTML/ICS, osobní sportovec, rodič a soukromý ICS | PASS, čtecí audit | Veřejný SELECT používá jen `je_verejny`; osobní cesty ověřují identitu, cíl `training_roster_links` a časové členství `club_roster_members`. |
| `AthleteRosterCalendarSeparationTest` | PASS 1 test / 4 tvrzení | Dvě soupisky, cizí cíl, poslední platný den, den po odebrání, zrušené členství a veřejné tréninky obou skupin. Izolovaná SQLite, nikoli přihlášený profil. |
| Stávající `TrainingRsvpTest`, `FamilyCalendarReadOptimizationTest`, `AthleteTimelineReadOptimizationTest` | PASS 19 testů / 143 tvrzení | Regrese osobního RSVP, rodičovského kalendáře a historické časové projekce. |
| `php -l` změněných PHP souborů | PASS | Syntaxe. |
| `php tools/check-current-docs.php`, `composer validate --strict`, `php tools/check-php-style.php`, `git diff --check` | PASS | Místní kontrakt dokumentace, Composer a styl. |
| První CI `882cce77` | FAIL v historických paritách | Stable assets očekává nezměněný `includes/ui_shell.php`; Workspace assets navíc hlásí starší drift `hlavicka.php` proti fixnímu SHA. PHP quality a dokumentace PASS. Změna společné navigace vrácena, nové CI čeká. |
| Finální kódový CI na merge `a600db94` | PASS | [Testy 45866513743](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45866513743): PHPUnit 2560/60730, unixové hranice 3/45, MariaDB 10.3/11.4 smoke a čtecí profily. [PHP kvalita 45866513803](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45866513803), [dokumentace 45866513967](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45866513967) a [CodeQL 45866507883](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45866507883) PASS. |
| PR review | NOT RUN | [PR #543](https://github.com/KovoPraha/evidenceTreninku/pull/543) zůstává otevřený. |
| Merge / deploy / přihlášené KIS UAT | NOT RUN | Samostatné kroky. |

Přihlášená přejímka: sportovec se dvěma platnými soupiskami otevře „Můj sport“
a uvidí termíny obou; v osobním přehledu neuvidí cizí Techniku MTB.
Otevře veřejný rozvrh, odkaz „Moje tréninky ze soupisek“ jej vrátí do osobního
kalendáře. Ve veřejném rozvrhu uvidí všechny publikované termíny včetně
Techniky MTB. Po odebrání ze soupisky ověří budoucí osobní termín i historický
poslední platný den. Totéž rodič v přehledu svěřené osoby; prohlédnout i
soukromý rodinný ICS. UAT nesmí měnit skutečné členství pouze kvůli testu.
