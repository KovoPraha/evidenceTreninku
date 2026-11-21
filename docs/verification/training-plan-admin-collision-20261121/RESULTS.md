# TRAINING-PLAN-ACL-01 — místní ověření, 21. 11. 2026

Prostředí: Windows, PHP 8.2.12, PHPUnit 11.5.56, izolovaná SQLite paměťová fixture. Bez produkčních zápisů či osobních dat. Větev `codex/training-plan-admin-collision-20261121` z `11505aed`.

| Kontrola | Výsledek | Rozsah a meze |
| --- | --- | --- |
| `php vendor/bin/phpunit tests/Integration/TrainingClosureTest.php tests/Integration/CoachTrainingAccessTest.php tests/Unit/TrainingCalendarWiringTest.php` | PASS: 21 testů, 104 tvrzení | Role, přesný čas kolize, nemožnost bypassu běžným trenérem, blokace soupisky/sportoviště, dvojí potvrzení a změna návrhu. Nenahrazuje HTTP/UAT formuláře. |
| `php -l` změněných PHP souborů a `git diff --check` | PASS | Syntaktická a whitespace kontrola. |
| `php tools/check-current-docs.php` | PASS: 80 řádků CURRENT a HANDOFF, 5803 místních odkazů, 0 chyb | Deset starších neregistrovaných podkladů bylo zařazeno jako historické či referenční do katalogu a indexu bez změny jejich obsahu či stavu. |
| Přihlášený formulář, MariaDB, Linux CI, merge, nasazení, produkční přejímka | NOT RUN | Další krok po PR: Linux CI a kontrolovaný test správce i běžného trenéra v prohlížeči. |

Jeden termín s více soupiskami používá existující `training_roster_links`; plánovač nyní nevytváří další termíny kvůli souběhu trenéra. Sériové přiřazení `serie_id` aktualizuje jen ID právě vytvořených termínů.
