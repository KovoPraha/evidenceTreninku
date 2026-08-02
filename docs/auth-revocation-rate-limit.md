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

**Nový PHP kód se nesmí nasadit, dokud hosting nemá nastavený
`AUTH_RATE_LIMIT_PEPPER`.** Musí jít o tajný, náhodný řetězec dlouhý nejméně
32 znaků, uložený mimo Git — přednostně v environment proměnné, případně pouze
v ignorovaném produkčním `config.php`. Chybějící nebo krátká hodnota způsobí
bezpečné odmítnutí loginu. Hodnotu nevypisujte do logu ani do deploy reportu.

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
Legacy SSO bridge je v cílové konfiguraci vypnutý. Pokud by byl po novém
samostatném rozhodnutí někdy aktivován, jeho bezpečnostní kontrakt při odebrání
identity ukončí aktuální request; nejde však o plánovanou provozní integraci.

Změna hesla trenéra a změna jeho role ve `sprava_treneru.php` zvýší verzi.
Úprava pouze jména nebo emailu ji nezvýší. Smazaný účet přestane validací
procházet automaticky. Transparentní přehashování stejného hesla při loginu ani
CLI migrátor legacy hesel verzi nezvyšují, protože nemění přihlašovací údaj.
V aktuálním kódu není samostatný flow pro deaktivaci trenéra nebo veřejného
účtu; budoucí deaktivační flow musí ve stejné transakci zvýšit `session_version`.

## Rate limiting

Trenérský a veřejný login kontrolují dvě dimenze: normalizovaný přihlašovací
údaj a `REMOTE_ADDR`. Do databáze se neukládá původní email, uživatelské jméno
ani IP; ukládá se pouze HMAC-SHA-256 s povinným tajným pepperem, oddělený verzí,
scope a typem dimenze. Aplikace nedůvěřuje `X-Forwarded-For`.

Pokus se rezervuje atomicky v jedné databázové transakci ještě před ověřením
hesla. Zámek obou hashovaných dimenzí zaručí, že souběžný burst nemůže nejprve
projít oddělenou kontrolou a až následně zapsat neúspěch. Rezervace neúspěšného
ověření už sama tvoří pokus; po úspěchu se limiter účtu smaže a ze sdílené IP
dimenze se vrátí právě rezervace úspěšného requestu. Předchozí neúspěchy z téže
IP zůstávají započtené.

Rezervace i dokončení úspěchu zamykají oba existující buckety podle vzestupně
seřazeného `key_hash`. Úspěch je nejprve oba uzamkne a teprve potom maže bucket
účtu a upravuje IP bucket. Stejné deterministické pořadí `SELECT ... FOR UPDATE`
na InnoDB odstraňuje opačný lock cycle `identifier → IP` proti rezervaci.

Prozatímní konfigurovatelné výchozí hodnoty jsou:

- nejvýše 5 neúspěšných pokusů,
- okno 900 sekund,
- blokace 900 sekund.

Konstanty `AUTH_RATE_LIMIT_MAX_ATTEMPTS`, `AUTH_RATE_LIMIT_WINDOW_SECONDS` a
`AUTH_RATE_LIMIT_BLOCK_SECONDS` lze definovat před načtením helperu. Blokace i
neplatné heslo vracejí stejnou obecnou uživatelskou zprávu. Úspěšné ověření
hesla smaže limiter konkrétního účtu. IP limiter zůstává nezávislý kromě vrácení
jediné úspěšné rezervace, takže jej nelze vymazat přihlášením do jiného platného
účtu ze stejné adresy.

## Otevřené body

- Změna globální tabulky oprávnění se stále nepromítne do již nacachovaného
  `$_SESSION['opravneni']`; samostatný increment má zavést globální permission
  version nebo přestat oprávnění dlouhodobě cachovat.
- Rotace `AUTH_RATE_LIMIT_PEPPER` změní všechny klíče limiteru. Vyžaduje
  koordinovaný provozní postup a následný úklid starých neodpovídajících řádků.
- Tabulka limiteru potřebuje provozní retenční úklid starých řádků; není součástí
  přihlašovacího requestu ani tohoto incrementu.
- Reset hesla, remember-me, expirované emailové/booking tokeny a redesign SSO
  zůstávají mimo tento krok.
