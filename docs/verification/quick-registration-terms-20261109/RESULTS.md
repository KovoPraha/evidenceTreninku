# QR01 — syntetické ověření rychlé přihlášky, 9. 11. 2026

Prostředí: izolovaný worktree z `origin/main` `fa8315eb`, PHP 8.2.12, PHPUnit 11.5.56, SQLite v paměti. Test používá skutečný fragment HTML z `booking/rychla_prihlaska.php` a službu `shopProgramQuickCheckoutPlace()`. Produkt 501 / varianta 601 a kontakty `example.test` jsou syntetické; produkční produkt 298 a skutečná platba zůstaly beze zápisu.

| Kontrola | Výsledek |
| --- | --- |
| Skrytá pole HTML: názvy a verze všech čtyř základních podmínek a aktivního volitelného ČSC, dekódované jako PHP POST | PASS |
| Aktivace ČSC po načtení starého formuláře | PASS: odmítnuto; účet a objednávka nevznikly |
| Změna povinného textu po načtení formuláře | PASS: odmítnuto; účet, žádost, souhlasy, objednávka, položky a platba beze zápisu |
| Nové načtení aktuálních verzí včetně ČSC, úplný rodičovský checkout | PASS: objednávka a platba vytvořeny, odmítnutí volitelného ČSC zapsáno s přesnou verzí |
| Dosavadní celý rodičovský scénář od rychlé objednávky přes ověření e-mailu, platbu, schválení osoby a soupisku | PASS |
| Volitelný ČSC zvolený i nezvolený v registrační službě | PASS v `AthleteRegistrationTest` |

První běh: `php vendor/bin/phpunit --configuration phpunit.xml.dist tests/Integration/ParentJourneyAcceptanceTest.php tests/Integration/AthleteRegistrationTest.php tests/Integration/RegistrationTextsTest.php` — 20 testů / 388 kontrol PASS. PHP lint upravené stránky a testu, `php tools/check-php-style.php`, `php tools/check-current-docs.php` a `git diff --check` PASS. [PR #416](https://github.com/KovoPraha/evidenceTreninku/pull/416) následně prošel 11 CI kontrolami a byl sloučen.

Omezení syntetického průchodu: skutečná přihláška produktu 298, vytvoření skutečné objednávky a reálná platba nebyly provedeny. Test používá stejné PHP služby a skutečný HTML fragment skrytých polí; produkční nasazení a veřejné čtení jsou ověřeny zvlášť níže. Doprava e-mailu zůstává bez živého ověření.

První merge: [PR #416](https://github.com/KovoPraha/evidenceTreninku/pull/416) měl všech 11 kontrol PASS a byl sloučen do `main` jako `5977897f44b60acbf54a2722ade1c63db75b8ae6`.

Navazující kontrola před vydáním: při změně verze na chybové odpovědi nyní server znovu načte aktuální texty a smaže dřívější `registration_acknowledged` i volitelný souhlas ČSC. Zachová je pouze při přesné shodě všech registračních a programových verzí. Test pokrývá aktivaci ČSC, změnu povinné verze, změnu programové verze a shodnou verzi: 20 testů / 395 kontrol PASS. [PR #419](https://github.com/KovoPraha/evidenceTreninku/pull/419) prošel 11 CI kontrolami a byl sloučen jako `e3f299037517c35b96752edfbf40dfa0e8e41f94`.

## Produkční vydání a veřejné čtení

Po schválení vlastníkem spuštěn [workflow 42637712723](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42637712723) s `potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`. `headSha=e3f299037517c35b96752edfbf40dfa0e8e41f94`, závěr SUCCESS 9. 11. 2026 14:49 UTC. Runner: 2412 testů / 55266 kontrol PASS; ověřená záloha, aktuální migrace bez čekajících změn, zachování variabilních symbolů, aktivace a přímý HTTP smoke 200 PASS. Krok importu kroužků byl SKIPPED.

Veřejný GET [produktu 298](https://kis.kovopraha.cz/booking/produkt.php?id=298) vrátil HTTP 200 a odkaz na variantu 952. Veřejný GET `booking/rychla_prihlaska.php?product_id=298&variant_id=952` vrátil HTTP 200 a přesně tyto názvy a hodnoty skrytých polí:

| Účel | Odesílaná verze |
| --- | --- |
| `term_version[member_data_notice]` | `member-data-notice-2026-10-20-v1` |
| `term_version[birth_number_legal_notice]` | `birth-number-legal-notice-2026-10-20-v1` |
| `term_version[photo_internal]` | `photo-internal-2026-08-16-v1` |
| `term_version[photo_public]` | `photo-public-2026-08-16-v1` |
| `term_version[csc_birth_number_sharing]` | `csc-birth-number-sharing-2026-10-20-v1` |
| `program_term_version[program_cancellation]` | `v1` |
| `program_term_version[program_consent]` | `v1` |

Volitelný checkbox `csc_birth_number_sharing` i povinné `registration_acknowledged` byly přítomny. Opakovaný GET vrátil stejných sedm polí; odpověď má `Cache-Control: no-store, max-age=0`. Aktuální verze se načítají z databáze při každém GET; přepnutí verze mezi GET a POST bylo ověřeno izolovaným testem výše. Živá objednávka produktu 298, skutečná platba, příjem e-mailu a lidská přejímka zůstávají NOT RUN.
