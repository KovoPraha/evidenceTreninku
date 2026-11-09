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

Příkaz: `php vendor/bin/phpunit --configuration phpunit.xml.dist tests/Integration/ParentJourneyAcceptanceTest.php tests/Integration/AthleteRegistrationTest.php tests/Integration/RegistrationTextsTest.php` — 20 testů / 388 kontrol PASS. PHP lint upravené stránky a testu, `php tools/check-php-style.php`, `php tools/check-current-docs.php` a `git diff --check` PASS. [PR #416](https://github.com/KovoPraha/evidenceTreninku/pull/416) otevřený; CI běží.

Omezení: skutečná přihláška produktu 298 v produkčním prohlížeči, vytvoření skutečné objednávky a reálná platba nebyly provedeny. Syntetický průchod používá stejné PHP služby a skutečný HTML fragment skrytých polí, ale netestuje živé nasazení ani dopravu e-mailu.

Merge: [PR #416](https://github.com/KovoPraha/evidenceTreninku/pull/416) měl všech 11 kontrol PASS a byl sloučen do `main` jako `5977897f44b60acbf54a2722ade1c63db75b8ae6`. Produkční deploy a živé UAT zatím NOT RUN; ruční workflow vyžaduje potvrzení `NASADIT`.

Navazující kontrola před vydáním: při změně verze na chybové odpovědi nyní server znovu načte aktuální texty a smaže dřívější `registration_acknowledged` i volitelný souhlas ČSC. Zachová je pouze při přesné shodě všech registračních a programových verzí. Test pokrývá aktivaci ČSC, změnu povinné verze, změnu programové verze a shodnou verzi: 20 testů / 395 kontrol PASS. Tato úprava je v [PR #419](https://github.com/KovoPraha/evidenceTreninku/pull/419); jeho CI zatím čeká na opakování po změně headu.
