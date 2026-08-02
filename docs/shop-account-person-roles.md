# Vazby účtů, rodičů a sportovců

Tento modul je bezpečnostní základ pro budoucí přihlášky na kroužky, kurzy a
zážitky. Přihlášený veřejný účet je objednatel; sportovec nebo dítě je samostatná
osoba z klubové evidence.

## Co je nyní možné

Přihlášený uživatel otevře `booking/moje_osoby.php` a odešle žádost s rolí,
jménem a datem narození osoby. Veřejná stránka nikdy nenabízí seznam sportovců
ani sama nehledá shodu. Jeden účet může mít nejvýše pět současně čekajících
žádostí a stejná opakovaná žádost se nevytvoří podruhé.

Administrátor otevře `eshop_identity_admin.php`, zkontroluje podklady a ručně
vybere odpovídajícího sportovce. Nadále může také založit vazbu přímo. Vztahy jsou:

- `self` – účet spravuje vlastní profil,
- `guardian` – účet rodiče nebo zástupce spravuje dítě.

Schválení, zamítnutí i zrušení vyžaduje nebo zaznamenává důvod. Každá změna se
uloží do samostatné auditní historie. Schválení žádosti a vytvoření vazby je jedna
transakce; při konfliktu se neuloží ani jedna část. Opakované odeslání stejné
žádosti, schválení nebo zrušení je idempotentní a nevytvoří duplicitní událost.

Budoucí e-shop smí nabídnout účastníka pouze tehdy, když je vazba schválená a
časově platná a veřejný účet je aktivní a má ověřený e-mail.

## Co se záměrně neděje

- Žádná vazba nevzniká automaticky podle shodného e-mailu nebo jména.
- Modul neslučuje podobné osoby.
- Modul nezapisuje do KIS.
- Samotné odeslání žádosti nezpřístupní dítě ani sportovce.

## Nasazení

Kód vyžaduje migrace `20260802230000_account_person_roles` a
`20260802233000_account_person_claim_requests`. Standardní deploy je provede
migrátorem před přepnutím nové verze. Před nasazením lze stav ověřit příkazem
`php bin/migrate.php --check`; samotná kontrola databázi nemění.
