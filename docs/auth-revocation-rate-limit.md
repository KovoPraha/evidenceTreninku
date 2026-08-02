# Revokace přihlášení a omezení pokusů

Tento přírůstek přidává číslovanou migraci
`20260802120000_auth_revocation_rate_limit` a dvě společné bezpečnostní vrstvy:

- `includes/auth_session.php` ověřuje aktivitu účtu a `session_version`,
- `includes/auth_rate_limit.php` omezuje neúspěšná přihlášení.

## Pořadí nasazení

Kód očekává aplikovanou číslovanou migraci. Deploy proto musí po záloze nejprve
spustit migrační runner a teprve potom zpřístupnit nový PHP kód. Samotné vložení
souboru do `migrations/` produkční databázi nemění a tato práce produkční apply
neprovádí.

Migrace přidává `session_version` s výchozí hodnotou `1` do tabulek `treneri` a
`verejni_uzivatele`. Dále vytváří `auth_login_limits`. Migrace je idempotentní,
má read-only `verify` a její SQLite větev slouží pro izolovaný integrační test;
produkční větev používá MySQL/InnoDB.

## Revokace session

Při přihlášení nebo ověření emailu se do PHP session uloží ID účtu a aktuální
`session_version`. Po vytvoření PDO připojení `db.php` ověří každou přítomnou
trenérskou i veřejnou identitu:

- účet stále existuje,
- účet je aktivní,
- verze v session přesně odpovídá verzi v databázi.

Chybějící nebo odlišná verze je neplatná. Identita se odstraní, ID session a
CSRF token se změní a aktuální request skončí HTTP 401. Ukončení requestu je
záměrné: některé historické endpointy kontrolují roli ještě před načtením
`db.php` a pouhé odstranění session klíče by nezabránilo pokračování mutace.

Změna hesla trenéra a změna jeho role ve `sprava_treneru.php` zvýší verzi.
Úprava pouze jména nebo emailu ji nezvýší. Smazaný účet přestane validací
procházet automaticky. Transparentní přehashování stejného hesla při loginu ani
CLI migrátor legacy hesel verzi nezvyšují, protože nemění přihlašovací údaj.
V aktuálním kódu není samostatný flow pro deaktivaci trenéra nebo veřejného
účtu; budoucí deaktivační flow musí ve stejné transakci zvýšit `session_version`.

## Rate limiting

Trenérský a veřejný login kontrolují dvě dimenze: normalizovaný přihlašovací
údaj a `REMOTE_ADDR`. Do databáze se neukládá původní email, uživatelské jméno
ani IP; ukládá se pouze SHA-256 hash oddělený scope a typem dimenze. Aplikace
nedůvěřuje `X-Forwarded-For`.

Prozatímní konfigurovatelné výchozí hodnoty jsou:

- nejvýše 5 neúspěšných pokusů,
- okno 900 sekund,
- blokace 900 sekund.

Konstanty `AUTH_RATE_LIMIT_MAX_ATTEMPTS`, `AUTH_RATE_LIMIT_WINDOW_SECONDS` a
`AUTH_RATE_LIMIT_BLOCK_SECONDS` lze definovat před načtením helperu. Blokace i
neplatné heslo vracejí stejnou obecnou uživatelskou zprávu. Úspěšné ověření
hesla smaže limiter konkrétního účtu. IP limiter zůstává nezávislý, aby jej
nešlo vymazat přihlášením do jiného platného účtu ze stejné adresy.

## Otevřené body

- Změna globální tabulky oprávnění se stále nepromítne do již nacachovaného
  `$_SESSION['opravneni']`; samostatný increment má zavést globální permission
  version nebo přestat oprávnění dlouhodobě cachovat.
- Hash bez tajného pepperu neobsahuje raw PII, ale u známých emailů je možné
  kandidátní hodnotu offline ověřit. Před vyšší privacy úrovní lze přejít na
  HMAC s produkčním pepperem a rotací.
- Tabulka limiteru potřebuje provozní retenční úklid starých řádků; není součástí
  přihlašovacího requestu ani tohoto incrementu.
- Reset hesla, remember-me, expirované emailové/booking tokeny a redesign SSO
  zůstávají mimo tento krok.
