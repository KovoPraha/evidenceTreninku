# Opakování kroužků po školních rocích

Program, produkt a soupiska mají stálou identitu. Každá nabídka má vlastní datum od/do, variantu, cenu a ID. Objednávková položka ukládá `club_program_offer_id`; pozdější platba se proto vztahuje k původnímu období. Sloupec `season_id` u nabídky zůstává pouze technickou kompatibilitou staršího schématu, nevymezuje platnost nabídky a správce ho nevybírá.

V administraci nabídek lze pro první či druhé pololetí a celý rok jednou zapnout opakování na 1–2 roky dopředu. Každý typ má vlastní pravidlo. Generátor kopíruje schválenou cenu, kapacitu a prodejní okno o příslušný počet roků. Před zápisem vyžaduje aktivní produkt a variantu, kladnou cenu, schválené podmínky platné i pro novou nabídku a nepřekrývající se termín stejného typu. Pokud se cena nebo podmínky změní, správce je upraví na vzorové nabídce; existující objednávky ani již vygenerované nabídky se nepřepisují.

`bin/refresh-club-program-offers.php --dry-run` vrací JSON s `ok`, `database_connected`, `would_create`, `skipped` a `errors`. `--apply` vyžaduje `CLUB_PROGRAM_RECURRENCE_APPLY_ENABLED=true`; běh používá databázový advisory lock, jednotlivé nabídky vytváří transakčně a opakovaný běh je bez duplicit. Souběžné roky jsou oddělené variantami pod stejným produktem. Pro lokální CLI je nutné nastavit `APP_HOST` a mít konfiguraci databáze.

Workflow `.github/workflows/club-program-recurrence-production.yml` je připravené na denní běh v 05:17 UTC a ruční `dry-run`/`apply`. Produkční plán je vypnutý, dokud není environment variable `KIS_CLUB_PROGRAM_RECURRENCE_SCHEDULE_ENABLED=true`. Zápis je samostatně vypnutý, dokud není `KIS_CLUB_PROGRAM_RECURRENCE_APPLY_ENABLED=true`. Před zapnutím ověřit nasazení migrací, ruční dry-run, `ok:true` a očekávané počty; po apply zkontrolovat konkrétní nabídky v administraci a veřejný katalog. Workflow používá již existující SSH secrets a `KIS_APP_HOST` / `KIS_REMOTE_DIR` z produkčního prostředí.

Dosavadní nabídky nemají opakování zapnuté automaticky. U každé skutečně používané varianty kroužku je třeba pravidlo jednou zapnout po kontrole ceny a schválených podmínek. Nadále se žádný školní rok ručně nezakládá.
