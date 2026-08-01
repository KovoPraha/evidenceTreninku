# Bezpečnost PHP session

Všechny webové vstupní PHP soubory spouštějí session přes
`includes/session_security.php` a funkci `app_session_start()`. Přímé volání
`session_start()` mimo tento helper není povolené. Helper je idempotentní, takže
jej lze bezpečně zavolat z hlavního souboru i z následně vložené hlavičky.

## Vynucená politika

- vlastní název cookie `EVIDENCESESSID`,
- `session.use_strict_mode=1` a `session.use_only_cookies=1`,
- cookie má `HttpOnly`, `SameSite=Lax` a cestu `/`,
- `Secure` je zapnuté pro každý nelokální host a také pro lokální HTTPS,
- odpověď posílá `Referrer-Policy: strict-origin-when-cross-origin`,
- autentizovaná session expiruje po 7 200 sekundách neaktivity,
- autentizovaná session expiruje nejpozději po 43 200 sekundách od přihlášení,
- ID aktivní autentizované session se mění každých 900 sekund,
- úspěšné přihlášení vždy změní ID session i CSRF token,
- po expiraci se stará session i cookie odstraní a request dostane novou
  anonymní session bez původních dat.

Lokální host je `localhost`, loopback adresa nebo jméno končící `.local` či
`.test`. Na běžné produkční doméně je proto cookie `Secure` i v případě, že
proxy nepředá PHP informaci o HTTPS.

Hodnoty 7 200 / 43 200 / 900 sekund jsou pro první bezpečnostní increment
**prozatímní a konfigurovatelné**. Helper je čte z konstant
`APP_SESSION_IDLE_TIMEOUT`, `APP_SESSION_ABSOLUTE_TIMEOUT` a
`APP_SESSION_ROTATION_INTERVAL`; policy funkce navíc přijímá override hodnoty
pro deterministické testy. Změnu produkčních hodnot je nutné udělat jako
vědomé provozní rozhodnutí a doplnit regresní test.

## Přechody identity a odhlášení

`app_session_mark_authenticated()` se volá po úspěšném přihlášení trenéra,
veřejného uživatele, ověření emailu a při vytvoření Evidence identity ze SSO.
Nastaví počátek absolutního limitu, poslední aktivitu a čas poslední rotace.
Autentizovaná session pod novým názvem cookie, které pouze chybějí časová
metadata, dostane metadata při prvním requestu. Přechod z dřívější výchozí
cookie `PHPSESSID` na `EVIDENCESESSID` ale znamená záměrné jednorázové odhlášení
stávajících uživatelů při nasazení.

Hlavní `logout.php` odstraní všechna session data, serverovou session i cookie.
Veřejné odhlášení odstraní pouze veřejnou identitu. Pokud ve stejné session
zůstává trenérská identita, zachová ji, ale změní session ID a CSRF token. Pokud
žádná jiná identita nezůstává, session i cookie úplně zničí.

## Záměrně mimo tento increment

Tato změna nepřidává databázovou `session_version`, rate limiting, reset hesla,
tokenové migrace, CSRF/POST variantu logoutu ani novou politiku SSO linkování.
Do zavedení databázové revokace proto změna hesla, role nebo aktivity účtu sama
o sobě okamžitě nezruší již vydanou session. Tyto body zůstávají samostatnými
blokátory W0-C.

Současný volitelný SSO bridge očekává klíče `velo_*` ve stejné PHP session.
Před zapnutím `VELOCOTA_INTEGRATION` je proto nutné integračně potvrdit společný
název a rozsah cookie, nebo bridge nahradit výměnou jednorázového autorizačního
kódu. Samostatný provoz Evidence má ve vzorové konfiguraci integraci vypnutou.
