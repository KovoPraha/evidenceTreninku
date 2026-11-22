# AUTH-MAIL-04 — požadavek na obnovu bez záznamu ve frontě

Datum 22. 11. 2026. Větev `codex/auth-mail-reset-request-audit-20261122` z
`origin/main` `2341f16c`. [Plán](../../identity-email-queue.md#auth-mail-04--dohledatelnost-požadavku-na-obnovu-hesla-22-11-2026).

| Kontrola | Stav | Důkaz a mez |
| --- | --- | --- |
| Průchod formulářem | PASS lokálně | `PasswordResetRequestPageTest`: skutečný POST kopie veřejné stránky se syntetickou SQLite DB vytvořil auditní referenci a při oprávněném účtu outbox; neznámá adresa dostala stejné obecné sdělení. Není to produkční browser ani doručení. |
| Vyhledání a předčasná ukončení | PASS lokálně | `PasswordResetRequestAuditTest`: aktivní ověřený účet s odlišnými velikostmi písmen se zařadí; neaktivní účet, neplatné CSRF, vstup nad 160 znaků a šestý požadavek v 15minutovém limitu mají odlišný interní důvod bez outboxu. Veřejná odpověď existenci účtu neprozradí. |
| Atomické zařazení | PASS lokálně | Token, outbox a audit `queued` mají společnou transakci. Chyba šifrování i vynucené selhání `INSERT INTO auth_mail_outbox` vrátí token/outbox a v auditu ponechá `failed` s kódem `mail_enqueue_error`; chybu hledání účtu odlišuje `lookup_error`. Log obsahuje referenci a třídu výjimky, bez identifikátoru či tokenu. |
| Trenérská větev | PASS lokálně | Shodný auditní postup pro existující trenérskou větev formuláře; token a outbox se vážou na jednu referenci. |
| Migrace, regresní testy a styl | PASS lokálně | 38 cílených testů / 458 tvrzení včetně migrací, starých resetů a nových větví; `composer style:check` 0 chyb, `php tools/check-current-docs.php` 0 chyb, `git diff --check` 0 chyb. |
| PHPStan změněných souborů | PASS lokálně i CI | Devět změněných PHP souborů analyzováno lokálně bez chyby; úplný [CI běh PHPStan/stylu](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44969352471) PASS. |
| Historický assetový kontrakt | PASS v CI | První CI běh odmítl změnu `booking/zapomenute_heslo.php`, protože starší kontrakt povoloval v této stránce pouze odstranění duplicitního CSS. Kontrakt nyní výslovně připouští přesný otisk revidované resetovací stránky, zatímco ostatní tři stránky porovnává jako dříve. HTTP profil pro reset ověřuje odpověď, CSS, CSRF a přesně dva nové auditní záznamy; běžná aplikační data musí zůstat beze změny. Obě verze MariaDB [prošly](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44969352488). |
| Historické testy a profily čtení | PASS v CI | První úplný PHPUnit skončil jediným selháním starého zdrojového testu, který hledal limiter v souboru formuláře. Test nyní kontroluje skutečnou obslužnou službu; úplný [PHPUnit](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44969352504) 2 546 testů / 60 504 tvrzení PASS. Starší profily porovnávaly počet tabulek s historickou verzí: artefakt CI potvrzuje 350/163 před a 355/165 po (tabulky/migrační řádky). Komparátory připouštějí přesně +5/+2: čtyři tabulky existujícího makeup řezu a jedinou auditní tabulku AUTH-MAIL-04. Obě verze MariaDB prošly. |
| Úplné CI a merge | PASS | [PR #517](https://github.com/KovoPraha/evidenceTreninku/pull/517) na finálním headu `4f78015a` prošel 13/13 kontrolami; squash merge do `main` je `641493a6`. |
| Chráněné produkční vydání | PASS technicky | [Workflow 44972109908](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44972109908) SUCCESS na přesném `641493a6`: ověřená záloha před změnou, migrace `apply/check` s `pending=[]`, aktivace, zákaz HTTP přístupu ke klíči a HTTP 200. `uat_schvaleno=false`, import kroužků přeskočen. |
| Živý formulář | PASS omezeně | GET veřejné stránky HTTP 200 s CSRF polem; POST s náhodnou neexistující adresou `example.invalid` a platným CSRF vrátil HTTP 200, obecné sdělení a referenci `98cf68a74759432441d049ed2415cbb6`. Nešlo o oprávněný účet ani zkoušku doručení. |
| Živá rodičovská fronta | PASS čtecí stav | [Status 44972640696](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44972640696) na nasazeném kódu: `pending=0`, `processing=0`, `failed=0`; poslední hostitelský běh byl úspěšný. Stav `sent` znamená přijetí transportem, nikoli doručení do schránky. Souhrnný `attention_required=true` souvisí s oddělenou klubovou frontou. |
| Přihlášená administrace a oprávněný reset | NOT RUN | Novou referenci nebylo možné přes admin UI dohledat bez přihlášení; prohlížeč skončil na loginu. Nebyl použit skutečný ani kontrolní rodičovský účet, žádný resetovací e-mail se testem neposílal. Doručení a lidská přejímka zůstávají otevřené. |
| Konkrétní nahlášený pokus | NEURČENO | Před nasazením této evidence neexistuje reference; bez přesného času a adresy, dostupných serverových logů a schránky nelze určit, zda šlo o limit, validaci, účet nebo chybu zařazení. Žádná produkční data nebyla čtena ani měněna. |

Audit ukládá pouze náhodné ID, oblast, výsledek, strojový důvod, čas a případné
ID outboxu. Správce jej vyhledá podle reference v `auth_mail_admin.php`; následný
stav zprávy se čte z fronty. Pokud selže samotný zápis auditu nebo DB, žádost
se nezařadí a reference s třídou chyby se zaznamená v serverovém logu. Uživatel
vidí jen neutrální informaci. Auditní tabulka zatím nemá politiku retence;
provozní správce musí určit dobu uchování před dlouhodobým provozem. Další krok:
správce přihlášeně dohledá uvedenou syntetickou referenci a na kontrolním účtu
ověří oprávněný reset včetně stavu fronty a doručení do vlastní schránky.
