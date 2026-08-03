# KIS: stabilni serie, sezony a politiky soupisek

Tento prirustek M1.2 oddeluje stabilni skupinu od jeji soupisky pro konkretni obdobi.
Je urcen pro localhost prototyp; sam neprovadi zadny automaticky presun sportovcu.

## Datovy model

- `club_team_series` je stabilni definice skupiny (napr. U15, Draha nebo Krouzek utery).
- `club_seasons` ma `season_type`: `school_year` nebo `calendar_year`.
- `club_teams` je rocni soupiska a muze mit `series_id`.
- `club_roster_members` zustava historicke M:N clenstvi osoby na soupisce.
- Legacy tym bez `series_id` je platny a pri nahledu se chova jako `manual`.

Existujici sezony se pri migraci konzervativne oznaci podle hranice roku: obdobi v
jednom roce jako `calendar_year`, obdobi pres dva roky jako `school_year`.

## Povolené kombinace

| Typ serie | Kalendar | Politika |
|---|---|---|
| `hobby` | skolni rok | `renewal_required` nebo `manual` |
| `age` | kalendarni rok | `age_progression` nebo `manual` |
| `discipline` | kalendarni rok | `carry_forward` nebo `manual` |
| `special` | oba | `manual` |

Vekova serie muze uvest `next_series_id` a bud vekove meze, nebo explicitni rozsah
rocniku narozeni. Obe sady pravidel soucasne jsou zakazane. Naslednik je nullable,
aby slo nejdrive zalozit jednotlive clanky rady a chybejici konfiguraci videt v preview.

## Read-only preview

`kisRosterPreviewRollover()` vraci pro aktivni cleny navrh `await_renewal`,
`age_progression`, `carry_forward`, `manual_review`, `target_team_required` nebo
`configuration_required`. Funkce neotvira transakci a nezapisuje do clenu ani auditu.

Administrace `kis_rosters_admin.php` umoznuje zalozit serii, oba typy sezon a rocni
soupisku. Nahled cilove sezony je GET operace. Vsechny zmeny zustavaji POST+CSRF a
vyzaduji opravneni `sync_evidence`.

## Co zamerne chybi

- automaticke provedeni rolloveru,
- individualni vyjimky,
- mapovani na legacy treninkove `skupiny` a `podskupiny`,
- import nebo zapis do stareho KIS.

Tyto kroky navazuji az po rucnim overeni datoveho modelu a uzivatelskeho preview.
