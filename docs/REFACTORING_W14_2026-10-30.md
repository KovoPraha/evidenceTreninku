# W14 — měřitelná brána technického dluhu

PHPStan zůstává na level 5 se stejnou detekcí nedefinovaných proměnných,
nesprávných návratových typů a mutovatelného stavu PDO. Nový kontrolní nástroj
porovnává každý potlačený nález s výchozí větví: soubor, identifier, přesný
message a počet. Nový nález ani navýšení nelze zakrýt snížením jiné položky.
Neznámý formát nebo duplicita se odmítne. V CI PR porovnává aktuální základ,
push na main předchozí commit; žádné přegenerování baseline se nespouští.

Aktuální inventura: 515 položek / 2350 potlačených nálezů, beze změny.
[Adresářový rozpis](verification/refactoring-w14-20261030/baseline-budget.json).
Tato vlna nedeklaruje odstranění veškerého legacy dluhu; jeho maskování
novou baseline je nyní automaticky blokováno. Další zpřesnění typů musí mít
doménové testy a být skutečným snížením, nikoli obecnou výjimkou v konfiguraci.

Udržitelné fixtures byly doplněny ve W11–W12: náhodné DB, lokální transport,
cleanup a přenosné souběhy/obnova. W13 přidala behaviorální JS kontrakt
skutečných labelů místo aserce řetězce zdrojového kódu. Zachována inventura
neveřejných tras a negativní bezpečnostní kontrakty.

Poslední ověřený W11 CI run 41087429597: PHPUnit 4m33s, MariaDB smoke
10.3/11.4 4m06s/5m22s, oddělené profily 4m10s/4m18s. Dřívější rozdělení
profilů zachovává souběžné joby; další zkrácení vynecháním testů se neprovádí.
W12 přidává skutečnou obnovu a export, proto není slibována nižší celková
latence CI. Povinné scénáře zůstávají aktivní na obou databázových verzích.

Ověření: tři cílené testy (7 asercí), včetně nového nálezu při nižším součtu,
navýšení při stejném součtu a neznámého formátu. Před sloučením PHPStan,
jeho 10 negativních nálezů, celý PHPUnit, styl a CI.
