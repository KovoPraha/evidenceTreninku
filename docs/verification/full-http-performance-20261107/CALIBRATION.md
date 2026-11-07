# Kalibrace měřidla, 7. 11. 2026

Tyto přípravné chyby nebyly vydávané za aplikační regresi ani za platné vzorky.

1. Dřívější minimální GET fixture neměl konfiguraci pro skutečný login:
   chyběl vlastní rate-limit pepper a legacy zákaznický `heslo_hash`, auto-ID
   a výchozí registrační timestamp. Doplňovaly se pouze nově vytvořený testovací
   config/schéma a fixture helper; normální login nebyl obcházený. Žádná chyba
   loginu z kalibrace není v 12 úspěšných loginech nebo 930 GET vzorcích.
2. Zdravotní data mají 9k Platné / 1k Odvoláno; Čeká a Platnost skončila
   jsou prázdné filtry. Odvoláno bylo doplněné jako dalších 35 GET po základní
   sérii, ostatní vzorky i data zachované. Resume ověřuje stejné prostředí a
   nezměněné existující definice případů; nepoužívat jej po změně fixture.
3. První DOM selektor počítal i šest řádků navigační tabulky. Byl zacílený
   přes skutečné hlavičky zdravotní tabulky; finálních 31 DOM kontrol PASS.
4. Diagnostický ID guard nejprve nepřijímal `_` v názvech pozic. Po rozšíření
   stejného bezpečného názvu souboru na alfanumerické/underscore/hyphen byla
   celá 65případová diagnostika opakovaná. Prepend měřič je izolovaný v closure,
   nezasahuje do globálních proměnných aplikace. Finální výstupy mají striktní
   HTML paritu a nezměněné kontrolní součty; první neúplný průchod se nearchivuje
   jako výsledek. PHP/SQL instrumentace není zapnutá během prostých HTTP/browser časů.
5. Browser CLI argument s inline JS byl špatně předaný přes Windows shell;
   další navigační a měřicí funkce se předávaly přes `run-code --filename`.
   Tato neprovedená navigace není měřený vzorek ani přihlášení jiné role.
6. Chrome neumožnil `Browser.getBrowserCommandLine`, protože nemá
   `--enable-automation`. Kontrola vlivu observeru tento nepovinný údaj označí
   jako neznámý a pokračuje nativní navigací; žádné flags se nedohadují ani
   neobcházejí jinou cestou. Platný native-check JSON obsahuje všech šest pozorování.

Celá aplikace zůstává beze změny, zdroje shodné s `feb8fe8c`. Pozorované localhost
favicon 404 a externí Bootstrap CSS jsou v hlavním výsledku zachované jako
zjištění, nikoli odfiltrované chyby. Syrové HTML, hesla, cookie jars a config
nejsou verzované. Po dokončení jsou vlastní služby zastavené a schéma uklizené.
