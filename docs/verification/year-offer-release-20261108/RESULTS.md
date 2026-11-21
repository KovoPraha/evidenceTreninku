# UX01 — vydání průvodce celoroční nabídkou

8. 11. 2026: **NASAZENO, technické ověření PASS**. [Plán a postup](../../club-programs.md#ux01--doplnění-celoroční-nabídky-ke-stávajícím-skupinám-8-11-2026).

- [PR #395](https://github.com/KovoPraha/evidenceTreninku/pull/395) sloučen do `main` jako `1c828fbf35372568993aa43af309b96c9a202f1b`. Finální PR head `c6f3b20a`: 11/11 CI PASS, včetně PHPStanu, PHPUnit a obou MariaDB smoke/profilů.
- [Deploy 42492821311](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42492821311) na přesném `1c828fbf` SUCCESS: syntaxe, testy, předletová kontrola, ověřená produkční záloha, migrace, aktivace a serverový HTTP smoke PASS. Přímé HTTP z runneru třikrát timeoutovalo; workflow správně použilo ověření ze serveru (`http:200`).
- `importovat_krouzky=false`, `uat_schvaleno=false`; krok založení produktů kroužků byl přeskočen. V tomto vydání nebyla zadána žádná celoroční cena, vytvořena nabídka ani provedena objednávka/platba.
- Nezávislý anonymní GET po deployi: `booking/cyklisticke_krouzky.php` HTTP 200; nový `club_program_year_offer_admin.php` HTTP 302 na `login.php` (před deployem HTTP 404); stávající `club_program_offers_admin.php` HTTP 302 na login. Přihlášený formulář a publikace variant **NOT RUN**.

Další krok: testerka se správcovskou rolí ověří nový formulář do náhledu, bez publikace, dokud vedení nedodá schválené ceny a nepovolí zveřejnění. [Uživatelský postup](../../club-programs.md#postup-pro-testerku-po-nasazení).
