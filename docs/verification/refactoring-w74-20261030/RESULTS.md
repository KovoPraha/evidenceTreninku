# W7.4 — ověření dávkového členství

Základ `e65a1f5fc873f0a307b81261ed06548eb0623a24`. Změna produkčního kódu
je omezena na `includes/family_portal.php`: po čerstvé autorizaci dávky nejvýše
400 deduplikovaných ID, bez uchování dat mezi voláními. Jedna osoba používá
původní čtení, prázdný seznam nečte členství. Výstup obsahuje stejné čtyři
členské klíče, pokud existují; ostatní osobní sloupce nejsou vraceny.

## Kontrakty a měření

RED: nový rozpočet hlavního přehledu očekával 21 dotazů, původní reader provedl
40; test selhal před implementací. GREEN: 14 cílených testů / 190 assertions.
Nové kontrakty zahrnují 0/1/5/20/399/400/401/1000 osob, pořadí, duplicity,
změnu členství, revokaci, cizí účet a chybějící volitelné sloupce.
[Nativní MariaDB](native-membership.txt): 3 testy / 64 assertions.

Celou stránku porovnává `FamilyMembershipPageProfile.php` se zmrazeným
W7.3 readerem `FamilyMembershipPortalW73.txt`. Baseline worker používá izolovanou
kopii nezměněných runtime souborů a jedinou záměnu readeru; tak všechny sekce
skutečně volají původní funkci. Mtime assets se zachovává. Bootstrap se nahrazuje
syntetickým přihlášeným PDO, skutečná data ani síťové služby se nepoužívají.
Normalizuje se jen CSP nonce a okamžik pozorování finančního zůstatku.

| Osob | SQL před → po | Dotazy členství před → po |
| --- | --- | --- |
| 0 | 43 → 43 | 0 → 0 |
| 1 | 144 → 144 | 8 → 8 |
| 5 | 272 → 244 | 40 → 12 |
| 20 | 752 → 619 | 160 → 27 |

[Celý GET](local-full-page.json), [historie +1000 účastí](local-history.json),
[POST s neplatným CSRF](local-post.json): shodná data, HTML, ostatní SQL včetně
parametrů a pořadí, shodný počet načtených řádků. Pro 20 osob úspora 133 SQL
(17,7 %), členských dotazů 83,1 %. POST jedné osoby 249 → 249.
Pět střídavých měření plain PDO je odděleno od instrumentovaného průchodu.
Lokální čas byl ovlivněn souběžícími kontrolami a různým umístěním kopie zdrojů;
není důkazem produkční latence. Rozpočet SQL je deterministický, čas posoudí CI.

Historický W7.2 profil dál zachovává přesný rozpočet načtených řádků
`2*N*(N-1)`; počet ušetřených dotazů nyní zahrnuje i dávku prvního seznamu:
`N*N-ceil(N/400)`. Pro 20 osob 2510 → 2111 SQL, úspora 399. W7.3 profil
metadata scope dál porovnává shodné ostatní SQL: 2111 → 619 SQL.

## Kontroly a meze

[PHPUnit Windows](phpunit-windows.txt): 1824 testů / 36151 assertions,
3 POSIX skips. [PHPStan](phpstan.txt) bez chyb, baseline nezvětšena.
Styl a aktuální dokumentace PASS. Linux a MariaDB 10.3/11.4 jsou součástí CI;
jejich výsledky a produkční důkazy budou doplněny po dokončení.
Ověření není lidské UAT a nezapíná žádnou integraci.
