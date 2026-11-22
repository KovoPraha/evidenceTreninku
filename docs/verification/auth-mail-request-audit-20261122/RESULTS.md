# AUTH-MAIL-04 — požadavek na obnovu bez záznamu ve frontě

Datum 22. 11. 2026. Větev `codex/auth-mail-reset-request-audit-20261122` z
`origin/main` `2341f16c`. [Plán](../../identity-email-queue.md#auth-mail-04--dohledatelnost-požadavku-na-obnovu-hesla-22-11-2026).

| Kontrola | Stav | Důkaz a mez |
| --- | --- | --- |
| Průchod formulářem | PASS lokálně | `PasswordResetRequestPageTest`: skutečný POST kopie veřejné stránky se syntetickou SQLite DB vytvořil auditní referenci a při oprávněném účtu outbox; neznámá adresa dostala stejné obecné sdělení. Není to produkční browser ani doručení. |
| Vyhledání a předčasná ukončení | PASS lokálně | `PasswordResetRequestAuditTest`: aktivní ověřený účet s odlišnými velikostmi písmen se zařadí; neaktivní účet, neplatné CSRF, vstup nad 160 znaků a šestý požadavek v 15minutovém limitu mají odlišný interní důvod bez outboxu. Veřejná odpověď existenci účtu neprozradí. |
| Atomické zařazení | PASS lokálně | Token, outbox a audit `queued` mají společnou transakci. Chyba šifrování i vynucené selhání `INSERT INTO auth_mail_outbox` vrátí token/outbox a v auditu ponechá `failed` s kódem `mail_enqueue_error`; chybu hledání účtu odlišuje `lookup_error`. Log obsahuje referenci a třídu výjimky, bez identifikátoru či tokenu. |
| Trenérská větev | PASS lokálně | Shodný auditní postup pro existující trenérskou větev formuláře; token a outbox se vážou na jednu referenci. |
| Migrace, regresní testy a styl | PASS lokálně | 37 cílených testů / 436 tvrzení včetně migrací, starých resetů a nových větví; `composer style:check` 0 chyb, `php tools/check-current-docs.php` 0 chyb, `git diff --check` 0 chyb. |
| PHPStan změněných souborů | PASS lokálně | Devět změněných PHP souborů analyzováno bez chyby; úplná lokální analýza byla přerušena pro dlouhý běh a zbývá na CI. |
| Úplné CI a produkční vydání | IN PROGRESS / NOT RUN | [PR #517](https://github.com/KovoPraha/evidenceTreninku/pull/517) otevřen, CI běží; merge, migrace/nasazení v produkci a lidské UAT neproběhly. |
| Konkrétní nahlášený pokus | NEURČENO | Před nasazením této evidence neexistuje reference; bez přesného času a adresy, dostupných serverových logů a schránky nelze určit, zda šlo o limit, validaci, účet nebo chybu zařazení. Žádná produkční data nebyla čtena ani měněna. |

Audit ukládá pouze náhodné ID, oblast, výsledek, strojový důvod, čas a případné
ID outboxu. Správce jej vyhledá podle reference v `auth_mail_admin.php`; následný
stav zprávy se čte z fronty. Pokud selže samotný zápis auditu nebo DB, žádost
se nezařadí a reference s třídou chyby se zaznamená v serverovém logu. Uživatel
vidí jen neutrální informaci. Auditní tabulka zatím nemá politiku retence;
provozní správce musí určit dobu uchování před dlouhodobým provozem. Další krok:
dokončit CI a rozhodnout o sloučení PR a řízeném nasazení migrace/kódu;
potom ověřit POST na kontrolním účtu bez
odeslání skutečnému rodiči a provozní dohledání reference.
