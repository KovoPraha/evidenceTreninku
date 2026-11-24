# KIS-ACCESS-STATUS-01 — ověření implementace (24. 11. 2026)

Stav: implementace pushnuta jako `68476b43` do `codex/account-membership-concept-20261124`, [draft PR #527](https://github.com/KovoPraha/evidenceTreninku/pull/527). [Plán a akceptace](../../ACCOUNT_ACTIVATION_AND_MEMBER_STATUS_2026-11-24.md#implementace-po-schválení-24-11-2026), [registr](../../WORK_PLAN_CURRENT.md#kis-access-status-01--aktivace-a-stavy-členství-24-11-2026), [rozhodnutí o sportovcích](../../DECISION_LOG.md#d2026-11-24-07--samostatný-přístup-sportovce-pro-všechny-profily).

| Kontrola | Výsledek |
| --- | --- |
| Historický veřejný e-mail, první heslo vypnutého importu, zákaz automatického schválení vazeb, změna adresy, fronta | PASS — `ExistingEmailTrustTest`, 3 scénáře; navazující `PasswordResetTest` a `AuthMailOutboxTest` PASS. |
| Automatický vypnutý login sportovce, sdílená/unikátní adresa, první heslo, zákaz znovuaktivace po ručním vypnutí | PASS — `AthleteEmailTrustTest`, 2 scénáře / 18 tvrzení. |
| Sjednání členství, idempotence, soupisky, první předpis, platební štítek, upozornění po toleranci a uzavření po úhradě | PASS — `MonthlyMembershipTest`, 19 scénářů / 100 tvrzení. |
| Náhled rozvrhu kroužku při čekající platbě bez účasti | PASS — `ClubProgramAccountOverviewTest`, 7 scénářů / 68 tvrzení. |
| Kompletní migrační katalog v izolovaném SQLite, idempotence | PASS — `AuthSecurityMigrationTest`, 1 scénář / 94 tvrzení. |
| Celá integrační sada | PASS — `vendor/bin/phpunit tests/Integration`: 1727 testů, 31253 tvrzení, 2 podmíněně přeskočené. Předchozí chyby ve zjednodušených fixture byly opraveny; konečný běh je bez chyb. |
| Jednotkové testy a smlouva zálohy | Dva úplné běhy po 831 testech našly postupně chybějící tři tabulky v `EVIDENCE_TABLES` a nesynchronizovaný očekávaný sloupcový kontrakt. Oba problémy opraveny; `DeployWorkflowContractTest` + `DatabaseBackupMariaDbSmokeContractTest` finálně PASS (16 testů / 523 tvrzení). Úplná sada po druhé opravě dosud NOT RUN; nové CI čeká. |
| Statická analýza, styl a dokumentace | PASS — PHPStan level 5 bez chyb; `php tools/check-php-style.php` (29 změněných PHP souborů, 0 chyb); `php tools/check-current-docs.php` (5982 odkazů, 0 chyb); `git diff --check` bez chyb. |
| MariaDB migrační a zálohovací smoke | NOT RUN místně — na `127.0.0.1:3306` neběží MariaDB. Testovací schéma doplněno o pole, která nové migrace čtou; ověření čeká na CI. |
| První CI po push `68476b43` | [Dokumentace](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45439171854), [PHP quality](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45439171340) a úplný PHPUnit v [Testy](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45439171604) PASS. MariaDB/performance jobs FAIL: minimalizované fixture bez `sportovci.email`, samostatná fixture pracovních notifikací bez nové tabulky a výkonová pojistka vyžadující byte-shodu celého `includes/club_program.php`. Fixture doplněny a pojistka zúžena na skutečně neměněný administrativní kód. Opravný CI běh čeká na push. |
| Produkční souhrnná inventura adres, duplicit, neplatných kontaktů, počtu automatických loginů a plateb | NOT RUN — před produkčním převodem provést pouze agregované čtení, bez osobních exportů. |
| Produkční migrace, rozesílka, doručení do schránek, přihlášené UAT rodiče/sportovce/správce | NOT RUN. `sent` ve frontě bude dokládat jen přijetí transportem. |

Před vydáním ověřit, že rozdělení e-mailů sportovců na jedinečné/sdílené odpovídá skutečným kontaktům. U sdílené adresy se reset vybírá loginem sportovce, viditelným správci na kartě. Osoby bez doručitelné adresy dostanou vypnutý login, ale samoobslužný odkaz až po bezpečném doplnění a ověření adresy. Nové veřejné registrace po pevném časovém předělu dál ověřují e-mail běžným způsobem.
