# P15-SEC380 — bezpečnostní audit registrářského přehledu

Datum: 8. 11. 2026 (Europe/Bratislava). Stav: audit a dokumentační předání HOTOVO;
oprava a úplná syntetická HTTP přejímka OTEVŘENÉ. Vlastník: Marek / vývoj KIS.
Zadání vlastníka: cílený audit PR #380 a dokumentace pro pracovní vlákno.
Bez skutečných osobních údajů, produkčních změn, opravy runtime nebo aktivace.

## Revize, metoda a předání

- Auditovaný [PR #380](https://github.com/KovoPraha/evidenceTreninku/pull/380):
  `ad80e3dcd1f0bf2762e03f87a85600f20aff1202`, načtený 8. 11.; draft, open,
  nesloučený; metadata hlásila mergeable=false. Stav integrace je třeba obnovit.
- Runtime uvedený autorem: `db7a174fa19006380ff2b8f0ed4ee7a7b940c561`.
  Aktuální popis PR již uvádí 11 CI PASS a Playwright PASS; původní informace
  o rozpracovaném CI je zastaralá. Zde jde o tvrzení v PR a jeho protokolu,
  nikoli nově spuštěné nebo nezávisle ověřené CI tohoto auditu.
- Vlastní dokumentační větev `codex/audit-pr380-20261108`, base main `35fdad44`;
  worktree `/workspace/scratch/8918432778bc/audit-pr380`. Zdroj PR čten v odděleném
  checkoutu. Main ještě nemá S4 soubory; odkazy níže jsou proto cesty v auditované
  revizi, nikoli tvrzení, že už jsou v main. Nezačleňovat runtime jako součást auditu.
- Metoda: statické trasování skutečné inicializace, loginu, session, rolí, DB
  služeb, šablony a sdílené hlavičky; porovnání s testovacím harness/maticí.
  Audit žádné portály, produkční DB, účty ani osobní podklady neotevřel.
- Akceptace auditu: konkrétní soubor/scénář/dopad/oprava a rozlišení chyb od
  chybějících testů. Závislost: neintegrovaný S1/S4 a rozhodnutí o pilotu.

## Výsledek

Jeden potvrzený nesoulad ochranné hlavičky v kódu, nízká závažnost.
Statická revize neprokázala obejití přihlášení/registrářské role, SQL injection,
XSS, čtení běhu pod cizím připojením nebo doménový zápis samotného S4 přehledu.
To není záruka absence zranitelností. Chybí propojená HTTP přejímka skutečného
bootstrapu nad syntetickou DB; jednotlivé kontroly již v kódu existují.

## F1 — skutečný bootstrap přepíše no-referrer (LOW, potvrzeno staticky)

**Soubory:** `external_sync_admin.php:9,14`, `includes/init.php:4`, `db.php:4`,
`includes/session_security.php` — `app_session_start()` a
`app_session_send_security_headers()` (`header(..., true)`).

Pořadí: stránka spustí session, nastaví `Referrer-Policy: no-referrer`, potom
načte init. Ten opět volá `app_session_start()`, který ještě před kontrolou
aktivní session nastaví `strict-origin-when-cross-origin` s nahrazením hlavičky.
Totéž provede `db.php`. Specifická politika stránky se již neobnoví.
Cache-Control no-store tímto přepsáním dotčená není.

**Dopad:** navigace/požadavek na stejném originu může odeslat celou URL v Referer,
včetně `connection`, `run` a filtru; cílové logy tak mohou uchovat metadata
synchronizace. Mezi originy se při této politice standardně posílá jen origin.
Nejde o doložený únik jmen, dat narození, obsahu snímku nebo session cookie.
Parametry samy nejsou osobními údaji sportovce, ale mohou označovat klubový rozsah.

**Reprodukce v izolaci:** na syntetické KIS DB přihlásit syntetického registráře
přes skutečný login, otevřít `external_sync_admin.php?connection=TEST-csc&run=TEST-run`.
V raw odpovědi očekávat současné `strict-origin-when-cross-origin` místo deklarovaného
`no-referrer`; otevřít vlastní same-origin testovací odkaz a zaznamenat Referer
bez skutečných dat. Minimální reproduktor bez DB: ve vlastním localhost PHP skriptu
načíst skutečné session_security.php, zavolat app_session_start(), nastavit
no-referrer a znovu app_session_start(); curl -i prokáže přepsání. Tento běh zde NOT RUN.

**Návrh opravy:** ponechat ochranu anonymního odmítnutí a nastavit politiku stránky
znovu po skutečné inicializaci; ideálně sdílený header helper zachová explicitně
přísnější politiku i pro brzké odpovědi/redirecty. Ověřit konečnou hlavičku po celé
hlavičce stránky, při 200/400/403/404/405/503 a expirované session. Samotné přesunutí
za init neřeší jeho předčasný exit. Bez nutnosti měnit autorizaci nebo datový kontrakt.

**Proč dosavadní test hlásí PASS:**
`tests/Support/ExternalSyncRegistrarSandbox.php` nahrazuje session_security.php,
init.php i hlavicka.php. HTTP assertion pro no-referrer tedy neprojde skutečnou
funkcí přepisující hlavičku. Doplnit test se skutečnými funkcemi, neopravovat
pouze očekávání na slabší politiku.

## Kontroly již existující v auditované revizi

| Oblast | Skutečná kontrola | Důkaz / meze |
| --- | --- | --- |
| Přihlášení | `login.php` a `booking/prihlaseni.php` vážou trainer ID na DB session_version a rotují session; samotné GET parametry identitu nevytvářejí | Funkce auth_session_bind_trainer / app_session_mark_authenticated; není to nový login mechanismus S4 |
| Celý bootstrap | external_sync_admin → init → db → auth_session_validate → refresh oprávnění → staffEnforceCurrentRoute; teprve pak S4 čtení | db.php:47–59; neplatná verze/deaktivace vyčistí identitu a request ukončí redirectem |
| Pracovní role | Vlastník trasy registrar v staff_workspaces.php, aktivní prostor + operationalCaseActor ověřující aktivní treneri a aktuální staff_user_positions nebo staff_superadmins | Starý role=admin sám nestačí; superadmin musí mít aktivní registrar prostor |
| Odebrání během relace | DB refresh na každém requestu a opakovaný actor v overview/detail | Odebrání přiřazení před dalším GET je odmítnuto; in-flight odpověď ani již zobrazené HTML nelze zpětně odvolat |
| Jednotlivý snímek | Detail vyžaduje `run.id=? AND connection_id=?`; historie a vazby mají connection, řádky mají ověřené run ID | external_sync_admin_read.php:103–122; testRolesRevocationAndRunScopeFailClosed zkouší cizí run pod jiným connection |
| Stav běhu | Řádky jen complete/review_required; implicitní latest pointer musí být complete | Historické návrhy jsou pro registráře záměrně čitelné, zobrazení je neschvaluje |
| Vstupy | Scalar string max80, whitelist filtrů, kladná číselná stránka max999999; identifikátory v prepared SQL | Pole/SQL fragment ve stránce odmítnut; OFFSET je odvozen z omezeného int; neznámé parametry nezavádějí akce |
| Výstup | Jediný helper htmlspecialchars ENT_QUOTES/ENT_SUBSTITUTE UTF-8, URL přes http_build_query a HTML escaping, ID osoby jako int | Všechna dynamická místa šablony zkontrolována; externí text se nepoužívá jako aktivní URL/HTML |
| Minimalizace | Explicitní allowlist polí zdroje, žádné cookies/tokeny/raw payload v HTML; veřejná chybová zpráva bez SQL | UCI, jméno, datum narození a zdrojový platební stav jsou citlivý oprávněný pracovní obsah; details není přístupová ochrana |
| Zápisy S4 | Služby provádějí SELECT, detail begin/commit/rollback; žádná publikace, ingest dispatch, DDL ani zápis identity | HTTP harness má query_only a hash tabulek, doménový test kontroluje SELECT; pro celou aplikaci platí níže uvedené meze |
| Sdílená hlavička | hlavicka.php načítá staff_notification_bell → staffNotificationInbox; tato cesta pouze SELECT | Označení upozornění za přečtené není součástí tohoto GET |
| CSRF/metody | Autorizovaný non-GET skončí 405 před čtením přehledu; nejsou zde doménové mutace vyžadující CSRF token | Session se může obnovit/rotovat i při odmítnutí metody; to není zápis do osob/snímků |

## Skutečně chybějící důkaz: G1 — propojené syntetické HTTP testy (priorita P2)

Nejde o potvrzenou chybějící autorizaci. `AuthSessionValidationTest.php` již pokrývá
neplatnou verzi, refresh role/oprávnění, zachování druhé identity a rotaci při
revokaci. `SessionSecurityTest.php` pokrývá lifecycle. S4 HTTP/domain testy již
pokrývají jiné role, neaktivního aktéra, odebrání přiřazení, XSS, parametry,
stránkování, cizí run, query_only a hash persistence. Není správné tyto scénáře
vykazovat znovu jako neexistující bezpečnostní kontroly.

**Co doplnit:** HTTP fixture s pravým session_security/init/db/hlavicka a skutečným
loginem nad izolovanou MariaDB, bez kopírování produkční konfigurace. Zachovat cookie
jar mezi requesty a upravovat pouze syntetickou DB z testovacího procesu:

1. Registrar login → přehled/detail 200; rodina, dítě, coach a jiný aktivní prostor
   nesmějí obdržet žádné řádky. Superadmin v jiném prostoru odmítnut, v registrar povolen.
2. Stejná cookie po DELETE přiřazení registrar / odebrání superadmina / aktivni=0 /
   zvýšení session_version: další GET odmítne nebo redirectuje před daty. Samostatně
   otestovat souběžnou validní public identitu; nesmí zachovat přístup trenéra.
3. Timeout idle/absolute a stará cookie po logoutu, chybějící session_version,
   nedostupná auth tabulka: žádný obsah; nerozlišovat úspěch jen podle HTTP 200.
4. Dvě syntetická connection A/B a jejich run: A+runB je 404 bez obsahu B.
   B+runB je pro globálního registráře přípustné podle dnešního modelu.
5. Konečné security/cache hlavičky včetně F1, GET/HEAD/POST/PUT/DELETE; sledovat
   všechny aplikační tabulky a žádné mutační SQL po přihlášení při renderu stránky.

**Očekávaný kontrakt oprávnění:** KIS je jedna klubová aplikace. Registrář dnes
vidí všechna její connection, bez přidělení jednotlivých zdrojů konkrétnímu člověku.
Změna obou parametrů A→B tedy není sama o sobě IDOR. Pokud má být registrář omezen
na konkrétní oddíl/tým/závod, takové ACL zde neexistuje; před rozšířením na více
správních hranic musí vzniknout grant a shodný filtr overview/detail. Současné
zadání takové omezení nedokládá, proto nejde o potvrzený nález cross-tenant úniku.

## Soukromí, zápisy a provozní meze

- No-store/private a no-cache se nastavují před odmítnutím přístupu. Session cookies
  mají Secure/HttpOnly/SameSite=Lax, strict mode; CSP omezuje framing na self.
  F1 je konkrétní odchylka, nikoli selhání všech ochranných hlaviček.
- `c.*` v overview/detail dnes neobsahuje device token_hash (je v jiné tabulce),
  šablona zobrazuje vybrané hodnoty. Explicitní SELECT sloupce jsou vhodné zpevnění
  proti budoucímu růstu schématu, nikoli dnešní doložený únik tajemství.
- Žádné doménové zápisy ve S4 nejsou doloženy. Skutečný bootstrap může měnit PHP
  session (aktivita, rotace); není správné tvrdit absolutní nulové zápisy do všeho.
  Volitelný `VELOCOTA_INTEGRATION` v db.php spouští legacy SSO bridge, který obsahuje
  INSERT/UPDATE treneri. Výchozí config.example.php je false a projekt požaduje OFF.
  Při jiné konfiguraci nutno ověřit samostatně; S4 syntetický hash toto nepokrývá.
  Aktuální produkční konfiguraci tento audit nečetl ani nezměnil.
- Retence starých snímků, provisioning a ověření skutečného scope zdroje jsou otevřené
  etapy S1/S5. UI netvoří právní titul ani retenční pravidlo. Před pilotem určit
  odpovědnost a mazání; nejde o nově prokázanou chybu autorizace tohoto PR.
- Detail prochází celý snímek i při stránkování a načte seznam osob/vazeb. Test 301
  řádků a konstantní počet dotazů nedokládá limit paměti/času při 100000 řádcích.
  Zátěžový syntetický test je doporučení P2 před pilotem, nikoli doložený DoS exploit.

## Ověření tohoto auditu a další krok

| Kontrola | Stav |
| --- | --- |
| Statické trasování a porovnání se skutečnými testy | HOTOVO; výsledky výše |
| PHP/PHPUnit, vlastní HTTP a browser reprodukce | NOT RUN: PHP není v prostředí nainstalované |
| Pokus získat PHP | Scoped instalátor není dostupný; apt-get update selhal na oprávnění setgroups/seteuid; neobcházeno |
| php tools/check-current-docs.php | NOT RUN ze stejného důvodu; nenahrazovat tvrzením PASS |
| git diff --check a doplňková kontrola změněných odkazů/limitů | PASS: git diff --check, místní odkazy změněných dokumentů a limity 80 řádků; nenahrazuje plnou PHP kontrolu |
| Produkce, skutečné osobní údaje, merge/deploy/aktivace | NOT RUN, mimo zadání |

Doporučení pro pracovní vlákno: opravit F1 a přidat pravý bootstrap/header test,
potom G1. Zachovat již existující guardy a syntetické testy. Nevydávat tento audit
za produkční přejímku. Audit nepovoluje nasazení ani integraci s živými portály.

Předání: větev zveřejněná přes GitHub konektor, dokumentační [draft PR #386](https://github.com/KovoPraha/evidenceTreninku/pull/386). Merge/deploy NOT RUN. Přímý git push neměl přihlašovací údaje; použito existující propojení GitHub. CI tohoto předání při zápisu neověřeno.
