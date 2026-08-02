# Bezpečnost PHP session

Všechny webové vstupní PHP soubory spouštějí session přes
`includes/session_security.php` a funkci `app_session_start()`. Přímé volání
`session_start()` mimo tento helper není povolené. Helper je idempotentní, takže
jej lze bezpečně zavolat z hlavního souboru i z následně vložené hlavičky.

## Vynucená politika

- vlastní název cookie `EVIDENCESESSID`,
- `session.use_strict_mode=1` a `session.use_only_cookies=1`,
- cookie má `HttpOnly`, `SameSite=Lax` a cestu `/`,
- `Secure` je zapnuté vždy na Linuxu, pro každý nelokální host a také pro
  lokální HTTPS,
- odpověď posílá `Referrer-Policy: strict-origin-when-cross-origin`,
- autentizovaná session expiruje po 7 200 sekundách neaktivity,
- autentizovaná session expiruje nejpozději po 43 200 sekundách od přihlášení,
- ID aktivní autentizované session se mění každých 900 sekund,
- úspěšné přihlášení vždy změní ID session i CSRF token,
- po expiraci se stará session i cookie odstraní a request dostane novou
  anonymní session bez původních dat.

Výjimka pro HTTP bez `Secure` platí pouze na důvěryhodně lokálním Windows a jen
pro `localhost`, loopback adresu nebo jméno končící `.local` či `.test`. Samotný
podvržený Host header proto na produkčním Linuxu cookie neoslabí. Na běžné
produkční doméně je `Secure` zapnuté i v případě, že proxy nepředá PHP informaci
o HTTPS.

Hodnoty 7 200 / 43 200 / 900 sekund jsou pro první bezpečnostní increment
**prozatímní a konfigurovatelné**. Helper je čte z konstant
`APP_SESSION_IDLE_TIMEOUT`, `APP_SESSION_ABSOLUTE_TIMEOUT` a
`APP_SESSION_ROTATION_INTERVAL`; policy funkce navíc přijímá override hodnoty
pro deterministické testy. Změnu produkčních hodnot je nutné udělat jako
vědomé provozní rozhodnutí a doplnit regresní test.

## Přechody identity a odhlášení

`app_session_mark_authenticated()` se volá po úspěšném přihlášení trenéra,
veřejného uživatele, ověření emailu a při vytvoření Evidence identity ze SSO.
U nové autentizované session nastaví počátek absolutního limitu, poslední
aktivitu a čas poslední rotace. Pokud se do stejné session přidává druhá
identita, zachová nejstarší počátek absolutního limitu; druhé přihlášení tedy
nemůže prodloužit maximální životnost původní identity.
Autentizovaná session pod novým názvem cookie, které pouze chybějí časová
metadata, dostane metadata při prvním requestu. Přechod z dřívější výchozí
cookie `PHPSESSID` na `EVIDENCESESSID` ale znamená záměrné jednorázové odhlášení
stávajících uživatelů při nasazení.

Hlavní `logout.php` odstraní všechna session data, serverovou session i cookie.
Veřejné odhlášení odstraní pouze veřejnou identitu. Pokud ve stejné session
zůstává trenérská identita, zachová ji, ale změní session ID a CSRF token. Pokud
žádná jiná identita nezůstává, session i cookie úplně zničí.
Oba endpointy přijímají pouze `POST` s platným CSRF tokenem.

Ověření e-mailu a potvrzení žluté rezervace používají pouze hash tokenu v DB,
pevnou expiraci a atomickou single-use spotřebu. Token je v e-mailu ve fragmentu
URL a server ho dostane až přes `POST` s CSRF. Booking odkaz před změnou vždy
zobrazí náhled. Viz [auth-one-time-tokens.md](auth-one-time-tokens.md).

## Navazující stav

Databázová `session_version`, atomický login rate limit, tokenové migrace a
CSRF/POST logout jsou implementované v navazujících F0 větvích. Stále zbývá
samostatný bezpečný reset hesla, permission cache a produkční ověření migrací.

Evidence je samostatný produkt. Volitelný legacy SSO bridge očekávající klíče
`velo_*` není součástí cílové architektury a `VELOCOTA_INTEGRATION` musí zůstat
`false`. Případné budoucí sdílení identity vyžaduje nové samostatné rozhodnutí.
