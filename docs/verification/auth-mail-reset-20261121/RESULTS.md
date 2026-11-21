# AUTH-MAIL-02 — třídenní reset a řízené nové zprávy

Datum 21. 11. 2026. Větev `codex/auth-mail-reset-72h-reissue-20261121` z `origin/main` `eb78c45b`.

| Kontrola | Stav | Důkaz a mez |
| --- | --- | --- |
| Výchozí produkční stav | PASS | [Čtecí workflow 44906494999](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44906494999) v 12:59 UTC: rodičovská fronta `pending=0`, `sent=7`, `cancelled=2`, `failed=0`, oba zrušené řádky `public reset`; heartbeat 12:59:03 UTC úspěšný. Bez adres a tokenů. |
| Identita a oprávnění přesně dvou příjemců | PASS | [Čtecí inventura 44909136270](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44909136270): `ready=true`, staré řádky 3 a 8, dva různé stále oprávněné účty, shodná aktuální adresa, žádná novější žádost ani platný aktivní reset. Výstup neobsahoval příjemce ani tokeny. |
| TTL 72 hodin a bezpečná jednorázová náhrada | PASS lokálně | `AuthMailOutboxTest` a `PasswordResetTest`: 24 testů, 204 tvrzení; ověřen text a expirace 3 dny, dva různé cíle, idempotence, odmítnutí novější žádosti a změněné adresy. Produkční operace zatím NOT RUN. |
| Syntaxe, formát a dokumentace | PASS lokálně | `php -l` pro změněné PHP, PHP CS Fixer 3.95.27, `php tools/check-current-docs.php` a `git diff --check`. |
| CI, merge a produkční nasazení | PASS | [PR #510](https://github.com/KovoPraha/evidenceTreninku/pull/510) sloučen jako `ccac61abf28b19f1af2202dd70fbd5b9cc25a7c4`, 11/11 CI PASS. [Deploy 44908781060](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44908781060) SUCCESS na témže SHA: záloha, migrace, aktivace a HTTP smoke PASS; UAT/import false. |
| Vystavení dvou nových zpráv | PASS | [Jednorázová operace 44909270205](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44909270205): `queued=2`, staré řádky 3 a 8 označeny jako nahrazené, nové řádky 10 a 11; expirace 24. 11. 2026 13:31:35 UTC (72 hodin). Žádný nový účet. Opakovaný běh je kódem odmítnut. |
| Přirozený běh a přijetí transportem | PASS | [Čtecí stav 44909334238](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44909334238) v 13:32 UTC: heartbeat `processed=2`, `sent=2`, `failed=0`, `cancelled=0`; rodičovská fronta `pending=0`, `sent=9`, `failed=0`, původní `cancelled=2`. Klubových sedm zpráv zůstalo čekat. |
| Doručení do schránky | NOT RUN | `sent` znamená přijetí PHP `mail()`, nikoli potvrzení příjemcem. Kontrola obou schránek včetně spamu ani mail/MTA logů nebyla dostupná. |

Původní zrušené odkazy se neposlaly znovu. Nové tokeny a šifrované zprávy vznikly v jedné DB transakci po opětovné kontrole oprávnění; tokeny nebyly vypsány do workflow ani Git. Další krok je potvrdit doručení obou nových e-mailů příjemci, případně prověřit hostingový mail/MTA transport. Záložní režim je oddělený [koncept AUTH-MAIL-03](../../identity-email-queue.md#auth-mail-03--záložní-provoz-rodičovské-pošty-21-11-2026).
