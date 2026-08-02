# Vazby účtů, rodičů a sportovců

Tento modul je bezpečnostní základ pro budoucí přihlášky na kroužky, kurzy a
zážitky. Přihlášený veřejný účet je objednatel; sportovec nebo dítě je samostatná
osoba z klubové evidence.

## Co je nyní možné

Administrátor otevře `eshop_identity_admin.php`, vybere veřejný účet, sportovce a
vztah:

- `self` – účet spravuje vlastní profil,
- `guardian` – účet rodiče nebo zástupce spravuje dítě.

Schválení i zrušení vyžaduje důvod. Každá změna se uloží do samostatné auditní
historie. Opakované odeslání stejného schválení nebo zrušení je idempotentní a
nevytvoří duplicitní událost.

Budoucí e-shop smí nabídnout účastníka pouze tehdy, když je vazba schválená a
časově platná a veřejný účet je aktivní a má ověřený e-mail.

## Co se záměrně neděje

- Žádná vazba nevzniká automaticky podle shodného e-mailu nebo jména.
- Modul neslučuje podobné osoby.
- Modul nezapisuje do KIS.
- Veřejný uživatel zatím sám neposílá žádost o vazbu; tu po ověření podkladů
  zakládá administrátor.

## Nasazení

Kód vyžaduje migraci `20260802230000_account_person_roles`. Standardní deploy ji
provede migrátorem před přepnutím nové verze. Před nasazením lze stav ověřit
příkazem `php bin/migrate.php --check`; samotná kontrola databázi nemění.
