# AUTH-MAIL-01 — průběžné ověření

Datum 21. 11. 2026. Větev `codex/auth-mail-worker-repair-20261121` z `origin/main` `11505aed`.

| Kontrola | Výsledek | Podklad a mez |
| --- | --- | --- |
| Zdrojový výběr | PASS | `authMailClaimAttempt()` čte pouze splatný `pending`; trenérský režim má filtr `target_type=trainer AND kind=reset`. |
| Produkční plán společného workeru v repozitáři | FAIL | `bin/club-event-notifications.php` existuje, ale žádný odpovídající produkční schedule/hostitelský instalátor nebyl nalezen. |
| Skutečný stav hostitelského CRONu, DB a logů | NOT RUN | Čeká čtecí chráněná diagnostika; počty z bug reportu nejsou nezávisle potvrzené. |
| SMTP / transport | NOT RUN | Implementace volá PHP `mail()`; konfiguraci hostingu ani skutečné doručení dosud neověřeno. |
| Odeslání a doručení `verify`/`reset` | NOT RUN | Žádný produkční worker ani TEST zpráva v této práci spuštěna. |
| Izolace rodičovské fronty | PASS | SQLite test vybírá `public verify`, `public reset` a `child reset`; trenérský řádek zůstává `pending` s nula pokusy. |
| Vlastněný instalátor CRONu | PASS | Dočasný lokální adresář: instalace, idempotence, heartbeat a odmítnutí cizího souboru. |
| Cílené regrese | PASS | PHPUnit: 21 testů, 183 tvrzení (`AuthMailOutboxTest`, `TrainerPasswordResetTest`, `TransactionalNotificationWorkerTest`, `ParentAuthMailSchedulerTest`). |
| Syntaxe, dokumentace a whitespace | PASS | `php -l` pro 4 změněné PHP soubory, `php tools/check-current-docs.php`, `git diff --check`, `composer validate --strict`. |
| Místní kontrola PHP CS Fixer | NOT RUN | Místní `vendor` neobsahuje `php-cs-fixer`; rozhodující kontrola stylu proběhne v CI PR. |

Nula pokusů u prošlých `pending` ukazuje na neproběhlý claim; sama o sobě
nevysvětluje, zda chybí plán, bootstrap selhává, nebo je plán vypnutý. Jako
první se ověří skutečný server bez zápisů. Neprovádět ruční retry jednotlivých
řádků. Staré odkazy při prvním zpracování validovat; neplatné zrušit bez odeslání.

Kód je zatím pouze na vývojové větvi. `status` na produkci, instalace, přirozený
tick, SMTP/hostingové logy, kontrolní schránky, merge a deploy jsou **NOT RUN**.
Při známém odmítnutí transportu zůstává zpráva do pátého pokusu `pending` s
`last_error`, potom `failed`; nejisté doručení jde do `failed` bez automatického
opakování. Administrace ukazuje `last_error` i u pending zpráv.
