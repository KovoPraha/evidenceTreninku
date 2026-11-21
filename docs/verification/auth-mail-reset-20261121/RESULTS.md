# AUTH-MAIL-02 — třídenní reset a řízené nové zprávy

Datum 21. 11. 2026. Větev `codex/auth-mail-reset-72h-reissue-20261121` z `origin/main` `eb78c45b`.

| Kontrola | Stav | Důkaz a mez |
| --- | --- | --- |
| Výchozí produkční stav | PASS | [Čtecí workflow 44906494999](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44906494999) v 12:59 UTC: rodičovská fronta `pending=0`, `sent=7`, `cancelled=2`, `failed=0`, oba zrušené řádky `public reset`; heartbeat 12:59:03 UTC úspěšný. Bez adres a tokenů. |
| Identita a oprávnění přesně dvou příjemců | NOT RUN | Bude ověřeno chráněnou čtecí inventurou před vydáním nových odkazů. |
| TTL 72 hodin a bezpečná jednorázová náhrada | PASS lokálně | `AuthMailOutboxTest` a `PasswordResetTest`: 24 testů, 204 tvrzení; ověřen text a expirace 3 dny, dva různé cíle, idempotence, odmítnutí novější žádosti a změněné adresy. Produkční operace zatím NOT RUN. |
| Syntaxe, formát a dokumentace | PASS lokálně | `php -l` pro změněné PHP, PHP CS Fixer 3.95.27, `php tools/check-current-docs.php` a `git diff --check`. |
| CI, merge a produkční nasazení | NOT RUN | Žádný nový kód ještě není nasazen. |
| Vystavení dvou nových zpráv | NOT RUN | Zrušené řádky se neopakují; po nasazení se vydají nové tokeny a frontové řádky. |
| Přirozený běh, `mail()` a doručení do schránky | NOT RUN | Doručení do schránky musí být ověřeno zvlášť. |
