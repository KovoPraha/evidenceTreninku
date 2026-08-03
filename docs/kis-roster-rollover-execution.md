# M1.5 – skutečné provedení obnovy soupisek

Obnova je vždy dvoukroková: administrátor nejprve vytvoří read-only náhled a teprve jeho konkrétní fingerprint může explicitním POSTem provést. Požaduje se CSRF token, role `admin`, potvrzovací checkbox a slovní důvod. Neexistuje cron ani vazba na dnešní datum.

## Politiky

- `age_progression` přesune aktivního člena do týmu následné věkové série v cílové kalendářní sezoně.
- `carry_forward` jej přenese do stejné disciplínové série v cílové sezoně.
- `renewal_required` a `manual` zůstávají bez automatické mutace.
- Individuální auditovaná výjimka může sportovce přeskočit nebo přepsat cílový tým. Target override umožňuje také schválený přechod z kroužku do závodního týmu bez nové osoby.

Zdrojové členství se nemaže. Uzavře se den před začátkem cílové sezony; cílové členství používá stejné `sportovec_id`, začíná prvním dnem cílové sezony a má otevřený konec.

## Audit a idempotence

Migrace `20260804170000_kis_roster_rollover_execution` přidává:

- `club_roster_rollover_exceptions` a append-only `club_roster_rollover_exception_events`,
- `club_roster_rollover_runs` s unikátním fingerprintem náhledu,
- `club_roster_rollover_run_items` se snapshoty každého rozhodnutí.

Každé skutečné zavření a otevření členství má navíc událost v existujícím `club_roster_events`. Opakovaný POST stejného fingerprintu vrátí původní výsledek a nic znovu nemění. Změna soupisky nebo výjimky starý fingerprint zneplatní. Celý běh je jedna transakce; chyba vrátí zdroj i cíl do původního stavu.
