# W11.2 — ověření příjemce bez celého publika

Základ W11.1 `73f30692298c12f0d84ef581aa8118fd3b057b87`.
[RED](recipient-red.txt): při 1000 osobách jediné rozhodnutí načítalo
2005 řádků, rozpočet byl nejvýše deset. Nový helper mění pouze čtení
uvnitř `rosterMessageProcessOne`; náhled, fingerprint, enqueue a doručení
se zachovávají.

## Výsledky

[Celý migrovaný MariaDB katalog](recipient-profile.json), FK zapnuté,
vlastní náhodná localhost DB uklizená v finally. Navíc ke 20 základním
syntetickým členům vloženo 100/1000/10 000 osob/účtů/vazeb. Obě varianty
vracejí stejné rozhodnutí, po každém objemu je ověřena okamžitá revokace.
Počty přes instrumentované PDO zvlášť, latence obyčejné PDO, 2 warmups /
10 vzorků; lokální běh sdílel host s testy, nejde o produkční SLA.

| Přidaných osob | SQL před → po | Řádky před → po | Medián ms před → po | Paměť navíc před → po |
| --- | ---: | ---: | ---: | ---: |
| 100 | 6 → 5 | 223 → 4 | 13,72 → 5,64 | 181 096 → 89 256 B |
| 1000 | 6 → 5 | 2023 → 4 | 35,37 → 4,57 | 1 323 400 → 89 256 B |
| 10 000 | 6 → 5 | 20 023 → 4 | 541,41 → 2,91 | 13 557 968 → 89 256 B |

Počet hran hierarchie může ovlivnit načtené řádky; tato varianta cíleně
omezuje členy/příjemce, nemění původní výpočet potomků. Nemá persistentní
ani operation-local cache autority. Metadata helperu zůstávají čerstvá.

[Cíleně](targeted.txt): 8 testů / 73 assertions. Porovnání s původním
náhledem zahrnuje cyklus hierarchie, více osob stejného účtu, konec členství
včetně dneška, výhradní konec role, budoucí/odebrané role, změnu adresy,
case-sensitive shodu e-mailu, aktivitu/ověření účtu, skupiny i sezóny,
NULL sezónu a chybějící tabulku. Existující worker test ověřuje revokaci
již zařazené zprávy bez zavolání senderu. [PHPUnit](phpunit.txt) a
[PHPStan](phpstan.txt) PASS. Plná Windows sada: 1857 testů / 37743 assertions,
tři POSIX skipy; Linux a obě MariaDB budou potvrzeny v CI.

Žádné skutečné rozesílání, změna harmonogramu nebo aktivace integrace.
