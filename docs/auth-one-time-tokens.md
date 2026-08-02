# Jednorázové tokeny a bezpečné odhlášení

Stav: implementováno v commitu `4b683ee` na větvi
`codex/auth-one-time-tokens`, bez produkčního nasazení.

## Pokryté odkazy

- ověření e-mailu veřejného účtu: platnost 24 hodin,
- potvrzení nebo zamítnutí žluté rezervace: platnost 48 hodin.

Do databáze se ukládá pouze účelově oddělený SHA-256 hash náhodného tokenu a
čas expirace. Platný token lze spotřebovat právě jednou; atomický podmíněný
`UPDATE` současně změní cílový stav a token odstraní. Neaktivní účet,
zpracovaná rezervace, expirovaný odkaz a opakované použití selžou stejně.

Nové tokeny se neposílají v query parametru. E-mailový odkaz ho nese ve fragmentu URL,
který prohlížeč neodesílá serveru ani do běžného access logu. Stránka fragment
odstraní z adresního řádku a odešle token spolu s CSRF přes `POST`. U booking
odkazu první POST pouze zobrazí náhled; změnu provede až druhé výslovné
potvrzení. Pouhé otevření odkazu nebo běžný e-mailový scanner rezervaci nezmění.
Před migrací již odeslané query odkazy zůstávají kompatibilní, ale GET pouze
předvyplní potvrzovací formulář; ani legacy odkaz se nikdy nespotřebuje bez
výslovného kliknutí uživatele.

## Migrace

`20260802133000_one_time_tokens` přidává:

- `verejni_uzivatele.verifikacni_token_expires_at`,
- `verejne_rezervace.potvrzovaci_token_expires_at`.

Stávající plaintext tokeny migrace převede na hash. Původní ověřovací odkaz má
expiraci `registrovan + 24 hodin`, booking odkaz `cas_rezervace + 48 hodin`.
Neplatný legacy formát se bezpečně zneplatní. Migrace je číslovaná a nesmí se
aplikovat request-time náhradou za `bin/migrate.php`.

## Odhlášení

Trenérské i veřejné odhlášení přijímá pouze `POST` s platným CSRF tokenem a po
úspěchu vrací redirect `303`. Veřejné odhlášení odstraní také uloženou revokační
verzi veřejné identity, přitom může zachovat současně přihlášeného trenéra.

## Ověření

- PHPUnit pokrývá vydání, purpose separation, expiraci, single-use a migraci,
- wiring test hlídá fragmentové odkazy a POST + CSRF logout,
- izolovaný MariaDB smoke ověřuje idempotentní migraci, backfill a runtime
  spotřebu obou typů tokenu.

Produkční migrace ani deploy nejsou součástí tohoto přírůstku.
