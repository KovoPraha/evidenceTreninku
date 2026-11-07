# KIS sezóny, týmy a soupisky

<!-- plan-catalog-status -->
> Stav při auditu 7. 11. 2026: Původní import/sezonní model je historický; změněné části nahrazuje schválený bezsezonní plán. Importy neopakovat podle staré specifikace.
> Evidence plánu: [katalog](PLAN_CATALOG.md), registr P06. [MEMBER_EVIDENCE_WORK_PLAN_2026-11-02.md](MEMBER_EVIDENCE_WORK_PLAN_2026-11-02.md) · [RESULTS.md](verification/member-evidence-cutover-20261102/RESULTS.md)


První funkční přírůstek KIS/F2 zavádí interní model sezóny, týmu a členství na
soupisce. Externí KIS se nemění a zůstává zdrojem pro shadow porovnání.

## Datový kontrakt

- `club_seasons` drží neměnné období sezóny a unikátní kód.
- `club_teams` patří právě jedné sezóně a má kód unikátní v sezóně.
- `club_roster_members` drží jednu historicky obnovitelnou vazbu týmu a
  sportovce. Odebrání řádek nemaže, ale nastaví `removed` a `valid_to`.
- `club_roster_events` auditují vytvoření týmu, přidání, odebrání a obnovení.
- Při zařazení se ukládá snapshot `sportovci.uciid`; změna živého UCI ID tak
  zpětně nepřepíše historický doklad.
- Archivovaného sportovce nelze zařadit. Opakované přidání aktivního člena je
  idempotentní.

Administrace je na `kis_rosters_admin.php`, vyžaduje oprávnění
`sync_evidence`, POST a CSRF. Zdroj `manual` znamená ruční rozhodnutí. Hodnota
`kis_shadow` je připravená pro budoucí výslovně potvrzený návrh importu; sama se
z importu zatím nevytváří.

Schéma přidává migrace `20260804090000_kis_teams_rosters`. Další přírůstek má
porovnat textové soupisky z KIS preview s interními týmy a zobrazit návrhy
`add/remove/conflict` bez automatické změny členství.
